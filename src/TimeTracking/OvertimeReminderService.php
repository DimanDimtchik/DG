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
        $recipient = CalendarNotificationSettings::notifyAdminEmail();

        foreach ($lots as $lot) {
            $lotId = (int) ($lot['id'] ?? 0);
            if ($lotId < 1) {
                $result['skipped']++;
                continue;
            }

            $label = trim((string) ($lot['display_name'] ?? ''));
            if ($label === '') {
                $label = trim((string) ($lot['company_name'] ?? ''));
            }
            if ($label === '') {
                $label = 'Mitarbeiter #' . (int) ($lot['contact_id'] ?? 0);
            }

            $remaining = (int) ($lot['minutes_remaining'] ?? 0);
            $expiresAt = (string) ($lot['expires_at'] ?? '');
            $message = OvertimeLotRepository::buildReminderMessage($label, $remaining, $expiresAt);

            if ($emailEnabled && $recipient !== '' && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                try {
                    self::sendReminderEmail($recipient, $label, $message, $expiresAt);
                    $result['sent']++;
                } catch (Throwable $e) {
                    $result['errors'][] = $label . ': ' . $e->getMessage();
                    continue;
                }
            } else {
                $result['sent']++;
            }

            OvertimeLotRepository::markReminderSent($lotId);
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
     * @return list<array<string, mixed>>
     */
    public static function pendingForUi(): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        $cfg = TimeTrackingSettings::config();
        if (empty($cfg['overtime_reminder_enabled'])) {
            return [];
        }

        return OvertimeLotRepository::pendingReminders(date('Y-m-d'));
    }

    private static function sendReminderEmail(string $recipient, string $employeeLabel, string $message, string $expiresAt): void
    {
        $deadline = date('d.m.Y', strtotime($expiresAt) ?: time());
        $subject = 'Überstunden-Abbau: ' . $employeeLabel . ' (Frist ' . $deadline . ')';
        $html = '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><a href="' . htmlspecialchars(App::publicBaseUrl() . '/app?page=zeiterfassung-team', ENT_QUOTES, 'UTF-8') . '">'
            . 'Teamübersicht Zeiterfassung öffnen</a></p>';

        MailService::send(new MailMessage(
            subject: $subject,
            htmlBody: $html,
            to: [$recipient],
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
