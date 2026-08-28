<?php
declare(strict_types=1);

/** Erinnerungen für Überstunden-Abbau (5-Monats-Regel vor 6-Monats-Frist). */
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
        $lots = OvertimeLotRepository::lotsDueForReminder($today);
        if ($lots === []) {
            return ['sent' => 0, 'skipped' => 0, 'errors' => []];
        }

        $result = ['sent' => 0, 'skipped' => 0, 'errors' => []];
        $emailEnabled = !empty($cfg['overtime_reminder_email']) && MailSettings::isConfigured();

        $mappedLots = [];
        foreach ($lots as $lot) {
            $mapped = OvertimeLotRepository::mapRowPublic($lot, $today);
            if ($mapped !== null) {
                $mappedLots[] = $mapped;
            }
        }
        if ($mappedLots === []) {
            return $result;
        }

        if ($emailEnabled) {
            try {
                self::sendManagerDigest($mappedLots);
                $result['sent']++;
            } catch (Throwable $e) {
                $result['errors'][] = 'Verantwortliche: ' . $e->getMessage();
            }

            $byContact = [];
            foreach ($mappedLots as $lot) {
                $contactId = (int) ($lot['contact_id'] ?? 0);
                if ($contactId < 1) {
                    continue;
                }
                $byContact[$contactId][] = $lot;
            }

            foreach ($byContact as $contactId => $contactLots) {
                $employeeEmail = OvertimeNotificationRecipients::employeeEmailForContact($contactId);
                if ($employeeEmail === null) {
                    $result['skipped']++;
                    continue;
                }
                try {
                    self::sendEmployeeReminder($employeeEmail, $contactLots);
                    $result['sent']++;
                } catch (Throwable $e) {
                    $label = (string) ($contactLots[0]['label'] ?? 'Mitarbeiter #' . $contactId);
                    $result['errors'][] = $label . ': ' . $e->getMessage();
                }
            }
        } else {
            $result['sent'] = count($mappedLots);
        }

        foreach ($mappedLots as $lot) {
            $lotId = (int) ($lot['id'] ?? 0);
            if ($lotId > 0) {
                OvertimeLotRepository::markReminderSent($lotId);
            }
        }

        return $result;
    }

    /**
     * Täglicher Autostart (wie Mahnwesen) — einmal pro Tag.
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
     * @return array{due: list<array<string, mixed>>, overdue: list<array<string, mixed>>}
     */
    public static function pendingForUi(): array
    {
        if (!Database::isConfigured()) {
            return ['due' => [], 'overdue' => []];
        }

        $cfg = TimeTrackingSettings::config();
        if (empty($cfg['overtime_reminder_enabled'])) {
            return ['due' => [], 'overdue' => []];
        }

        return OvertimeLotRepository::pendingRemindersGrouped(date('Y-m-d'));
    }

    /**
     * @param list<array<string, mixed>> $lots
     */
    private static function sendManagerDigest(array $lots): void
    {
        $recipients = OvertimeNotificationRecipients::managerEmailsForDigest();
        if ($recipients === []) {
            throw new RuntimeException('Keine E-Mail-Adresse für Verantwortliche gefunden.');
        }

        $subject = 'Überstunden-Abbau: ' . count($lots) . ' offene Position(en)';
        $items = [];
        foreach ($lots as $lot) {
            $items[] = '<li>' . htmlspecialchars((string) ($lot['message'] ?? ''), ENT_QUOTES, 'UTF-8') . '</li>';
        }
        $html = '<p>Folgende Überstunden sind zum Abbau vorgesehen (Stunden verbleiben im Konto, auch nach Frist):</p>'
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
     * @param list<array<string, mixed>> $lots
     */
    private static function sendEmployeeReminder(string $employeeEmail, array $lots): void
    {
        $label = (string) ($lots[0]['label'] ?? 'Mitarbeiter');
        $subject = 'Ihre Überstunden: Abbau bis ' . (string) ($lots[0]['expires_display'] ?? '');
        $items = [];
        foreach ($lots as $lot) {
            $items[] = '<li>' . htmlspecialchars((string) ($lot['employee_message'] ?? $lot['message'] ?? ''), ENT_QUOTES, 'UTF-8') . '</li>';
        }
        $html = '<p>Guten Tag ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>folgende Überstunden stehen bei Ihnen noch zum Abbau an. Nicht abgebaute Stunden '
            . 'bleiben im Zeitkonto erhalten und müssen weiterhin eingeplant werden:</p>'
            . '<ul>' . implode('', $items) . '</ul>'
            . '<p><a href="' . htmlspecialchars(App::publicBaseUrl() . '/app?page=zeiterfassung', ENT_QUOTES, 'UTF-8') . '">'
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
     * @param array{sent: int, skipped: int, errors: list<string>} $result
     */
    private static function logAutoRun(array $result): void
    {
        $line = date('c') . ' overtime-reminder: gesendet=' . ($result['sent'] ?? 0)
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
