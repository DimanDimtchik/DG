<?php
declare(strict_types=1);

/** Erinnerungen bei ArbZG-Verstoß: Ø > 48 h/Woche in 6 Kalendermonaten (WD 6/097/19). */
final class OvertimeReminderService
{
    /**
     * @return array{sent: int, skipped: int, errors: list<string>}
     */
    public static function runAutomatic(): array
    {
        $cfg = TimeTrackingSettings::config();
        if (empty($cfg['overtime_reminder_enabled'])) {
            return ['sent' => 0, 'skipped' => 0, 'errors' => []];
        }

        $today = date('Y-m-d');
        if (!ArbzgComplianceService::shouldSendMonthlyReminders($today)) {
            return ['sent' => 0, 'skipped' => 0, 'errors' => []];
        }

        $violations = ArbzgComplianceService::violationsForCompletedPeriod($today);
        if ($violations === []) {
            return ['sent' => 0, 'skipped' => 0, 'errors' => []];
        }

        $pending = [];
        foreach ($violations as $violation) {
            $contactId = (int) ($violation['contact_id'] ?? 0);
            $periodTo = (string) ($violation['period_to'] ?? '');
            if ($contactId < 1 || $periodTo === '') {
                continue;
            }
            if (ArbzgReminderRepository::hasReminder($contactId, $periodTo)) {
                continue;
            }
            $pending[] = $violation;
        }

        if ($pending === []) {
            return ['sent' => 0, 'skipped' => 0, 'errors' => []];
        }

        $result = ['sent' => 0, 'skipped' => 0, 'errors' => []];
        $emailEnabled = !empty($cfg['overtime_reminder_email']) && MailSettings::isConfigured();

        if ($emailEnabled) {
            try {
                self::sendManagerDigest($pending);
                $result['sent']++;
            } catch (Throwable $e) {
                $result['errors'][] = 'Verantwortliche: ' . $e->getMessage();

                return $result;
            }

            foreach ($pending as $violation) {
                $contactId = (int) ($violation['contact_id'] ?? 0);
                $employeeEmail = OvertimeNotificationRecipients::employeeEmailForContact($contactId);
                if ($employeeEmail === null) {
                    $result['skipped']++;
                    self::markViolationReminder($violation);
                    continue;
                }
                try {
                    self::sendEmployeeReminder($employeeEmail, $violation);
                    $result['sent']++;
                    self::markViolationReminder($violation);
                } catch (Throwable $e) {
                    $label = (string) ($violation['label'] ?? 'Mitarbeiter #' . $contactId);
                    $result['errors'][] = $label . ': ' . $e->getMessage();
                }
            }
        } else {
            $result['sent'] = count($pending);
            foreach ($pending as $violation) {
                self::markViolationReminder($violation);
            }
        }

        return $result;
    }

    /**
     * Täglicher Autostart — E-Mail nur am 1. des Monats nach abgeschlossenem 6-Monats-Zeitraum.
     */
    public static function runIfDue(): void
    {
        if (!Database::isConfigured()) {
            return;
        }

        $cfg = TimeTrackingSettings::config();
        if (empty($cfg['overtime_reminder_enabled'])) {
            return;
        }

        $today = date('Y-m-d');
        $state = self::loadAutoState();
        if (($state['last_run'] ?? '') === $today) {
            return;
        }

        try {
            $result = self::runAutomatic();
            self::saveAutoState([
                'last_run' => $today,
                'sent' => (int) ($result['sent'] ?? 0),
                'skipped' => (int) ($result['skipped'] ?? 0),
                'last_error' => ($result['errors'] ?? []) !== []
                    ? implode('; ', $result['errors'])
                    : null,
            ]);
            if ($result['sent'] > 0 || $result['errors'] !== []) {
                self::logAutoRun($result);
            }
        } catch (Throwable $e) {
            self::saveAutoState([
                'last_run' => $today,
                'sent' => 0,
                'skipped' => 0,
                'last_error' => $e->getMessage(),
            ]);
            self::logAutoRun(['sent' => 0, 'skipped' => 0, 'errors' => [$e->getMessage()]]);
        }
    }

    /**
     * @return array{violations: list<array<string, mixed>>}
     */
    public static function pendingForUi(): array
    {
        if (!Database::isConfigured()) {
            return ['violations' => []];
        }

        $cfg = TimeTrackingSettings::config();
        if (empty($cfg['overtime_reminder_enabled'])) {
            return ['violations' => []];
        }

        return ['violations' => ArbzgComplianceService::currentViolations()];
    }

    /**
     * @param list<array<string, mixed>> $violations
     */
    private static function sendManagerDigest(array $violations): void
    {
        $recipients = OvertimeNotificationRecipients::managerEmailsForDigest();
        if ($recipients === []) {
            throw new RuntimeException('Keine E-Mail-Adresse für Verantwortliche gefunden.');
        }

        $months = (int) ($violations[0]['months'] ?? ArbzgComplianceService::evaluationMonths());
        $subject = sprintf('ArbZG: %d Mitarbeiter mit Ø > 48 h/Woche (%d Monate)', count($violations), $months);
        $items = [];
        foreach ($violations as $violation) {
            $line = (string) ($violation['message'] ?? '');
            $avg = (string) ($violation['avg_weekly_display'] ?? '');
            if ($avg !== '') {
                $line .= ' (Ø ' . $avg . ' h/Woche)';
            }
            $items[] = '<li>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        $html = '<p>Prüfung nach ArbZG / Bundestag-WD 6/097/19 — folgende Mitarbeiter überschreiten den '
            . '6-Monats-Durchschnitt von 48 Stunden pro Woche:</p>'
            . '<ul>' . implode('', $items) . '</ul>'
            . '<p><a href="' . htmlspecialchars(App::publicBaseUrl() . '/app?page=zeiterfassung-team', ENT_QUOTES, 'UTF-8') . '">'
            . 'Teamübersicht Zeiterfassung öffnen</a></p>';

        MailService::send(new MailMessage(
            subject: $subject,
            htmlBody: $html,
            to: $recipients,
        ));
    }

    /**
     * @param array<string, mixed> $violation
     */
    private static function sendEmployeeReminder(string $employeeEmail, array $violation): void
    {
        $label = (string) ($violation['label'] ?? 'Mitarbeiter');
        $months = (int) ($violation['months'] ?? ArbzgComplianceService::evaluationMonths());
        $avg = (string) ($violation['avg_weekly_display'] ?? '');
        $subject = sprintf('ArbZG: Ihr Wochendurchschnitt über 48 h (%d Monate)', $months);
        $message = (string) ($violation['employee_message'] ?? ArbzgComplianceService::buildEmployeeMessage($months));
        $html = '<p>Guten Tag ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
        if ($avg !== '') {
            $html .= '<p>Ihr errechneter Durchschnitt: <strong>' . htmlspecialchars($avg, ENT_QUOTES, 'UTF-8')
                . ' Stunden pro Woche</strong>.</p>';
        }
        $html .= '<p><a href="' . htmlspecialchars(App::publicBaseUrl() . '/app?page=zeiterfassung', ENT_QUOTES, 'UTF-8') . '">'
            . 'Zeiterfassung öffnen</a></p>';

        MailService::send(new MailMessage(
            subject: $subject,
            htmlBody: $html,
            to: [$employeeEmail],
        ));
    }

    /** @return array<string, mixed> */
    private static function loadAutoState(): array
    {
        $path = DG_ROOT . '/storage/overtime-reminder-state.json';
        if (!is_file($path)) {
            return [];
        }
        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $state */
    private static function saveAutoState(array $state): void
    {
        $dir = DG_ROOT . '/storage';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        file_put_contents(
            $dir . '/overtime-reminder-state.json',
            json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX,
        );
    }

    /**
     * @param array<string, mixed> $violation
     */
    private static function markViolationReminder(array $violation): void
    {
        ArbzgReminderRepository::markSent(
            (int) ($violation['contact_id'] ?? 0),
            (string) ($violation['period_from'] ?? ''),
            (string) ($violation['period_to'] ?? ''),
            (int) round((float) ($violation['avg_weekly_minutes'] ?? 0)),
        );
    }

    /**
     * @param array{sent: int, skipped: int, errors: list<string>} $result
     */
    private static function logAutoRun(array $result): void
    {
        $line = date('c') . ' arbzg-reminder: gesendet=' . ($result['sent'] ?? 0)
            . ', übersprungen=' . ($result['skipped'] ?? 0);
        if (($result['errors'] ?? []) !== []) {
            $line .= ' FEHLER: ' . implode('; ', $result['errors']);
        }
        $line .= "\n";
        $logDir = DG_ROOT . '/storage/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }
        file_put_contents($logDir . '/overtime-reminder.log', $line, FILE_APPEND | LOCK_EX);
    }
}
