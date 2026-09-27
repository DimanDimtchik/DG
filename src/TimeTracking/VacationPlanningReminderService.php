<?php
declare(strict_types=1);

/**
 * Urlaubsplanung: Statistik (% geplant) + Erinnerungsmail unter Schwelle
 * (Standard 60 %, Auto Ende März / Anfang April).
 */
final class VacationPlanningReminderService
{
    public const DEFAULT_THRESHOLD_PERCENT = 60.0;
    /** Auto-Fenster: 25.03. – 07.04. (inkl.) */
    public const AUTO_WINDOW_START_MD = '03-25';
    public const AUTO_WINDOW_END_MD = '04-07';

    /**
     * @return list<array{
     *   contact_id: int,
     *   label: string,
     *   days_entitled: float,
     *   days_carried: float,
     *   days_budget: float,
     *   days_planned: float,
     *   days_approved: float,
     *   percent: float|null,
     *   below_threshold: bool,
     *   threshold_percent: float,
     *   email: string|null,
     *   auto_reminder_sent_at: string|null,
     *   last_reminder_sent_at: string|null
     * }>
     */
    public static function statsForYear(int $year): array
    {
        if ($year < 2000 || $year > 2100) {
            return [];
        }
        $threshold = self::thresholdPercent();
        $rows = [];
        foreach (TimeMonthReportService::staffOptions() as $opt) {
            $cid = (int) ($opt['id'] ?? 0);
            if ($cid < 1) {
                continue;
            }
            $balance = TimeVacationEntitlementRepository::balance($cid, $year);
            $budget = round((float) $balance['days_entitled'] + (float) $balance['days_carried'], 1);
            $planned = TimeAbsenceRepository::plannedVacationDaysInYear($cid, $year);
            $approved = (float) $balance['days_used'];
            $percent = null;
            $below = false;
            if ($budget > 0) {
                $percent = round(($planned / $budget) * 100, 1);
                $below = $percent < $threshold;
            }
            $rows[] = [
                'contact_id' => $cid,
                'label' => (string) ($opt['label'] ?? ('#' . $cid)),
                'days_entitled' => (float) $balance['days_entitled'],
                'days_carried' => (float) $balance['days_carried'],
                'days_budget' => $budget,
                'days_planned' => $planned,
                'days_approved' => $approved,
                'percent' => $percent,
                'below_threshold' => $below,
                'threshold_percent' => $threshold,
                'email' => OvertimeNotificationRecipients::employeeEmailForContact($cid),
                'auto_reminder_sent_at' => VacationPlanningReminderRepository::latestSentAt($cid, $year, 'auto'),
                'last_reminder_sent_at' => VacationPlanningReminderRepository::latestSentAt($cid, $year),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $pa = $a['percent'];
            $pb = $b['percent'];
            if ($pa === null && $pb === null) {
                return strcmp((string) $a['label'], (string) $b['label']);
            }
            if ($pa === null) {
                return 1;
            }
            if ($pb === null) {
                return -1;
            }
            if ($pa === $pb) {
                return strcmp((string) $a['label'], (string) $b['label']);
            }

            return $pa <=> $pb;
        });

        return $rows;
    }

    public static function thresholdPercent(): float
    {
        $cfg = TimeTrackingSettings::config();
        $v = (float) ($cfg['vacation_planning_reminder_threshold'] ?? self::DEFAULT_THRESHOLD_PERCENT);

        return max(1.0, min(100.0, round($v, 1)));
    }

    public static function isInAutoWindow(?string $ymd = null): bool
    {
        $ymd = $ymd ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-(\d{2}-\d{2})$/', $ymd, $m)) {
            return false;
        }
        $md = $m[1];

        return $md >= self::AUTO_WINDOW_START_MD && $md <= self::AUTO_WINDOW_END_MD;
    }

    /**
     * Automatischer Lauf (Fenster März/April, einmal pro MA/Jahr).
     *
     * @return array{sent: int, skipped: int, errors: list<string>}
     */
    public static function runAutomatic(?string $today = null): array
    {
        $cfg = TimeTrackingSettings::config();
        if (empty($cfg['vacation_planning_reminder_enabled'])) {
            return ['sent' => 0, 'skipped' => 0, 'errors' => []];
        }
        $today = $today ?? date('Y-m-d');
        if (!self::isInAutoWindow($today)) {
            return ['sent' => 0, 'skipped' => 0, 'errors' => []];
        }
        $year = (int) substr($today, 0, 4);

        return self::sendToBelowThreshold($year, 'auto', false);
    }

    public static function runIfDue(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        $cfg = TimeTrackingSettings::config();
        if (empty($cfg['vacation_planning_reminder_enabled'])) {
            return;
        }
        $today = date('Y-m-d');
        $state = self::loadAutoState();
        if (($state['last_run'] ?? '') === $today) {
            return;
        }
        try {
            $result = self::runAutomatic($today);
            self::saveAutoState([
                'last_run' => $today,
                'sent' => (int) ($result['sent'] ?? 0),
                'skipped' => (int) ($result['skipped'] ?? 0),
                'last_error' => ($result['errors'] ?? []) !== []
                    ? implode('; ', $result['errors'])
                    : null,
            ]);
        } catch (Throwable $e) {
            self::saveAutoState([
                'last_run' => $today,
                'sent' => 0,
                'skipped' => 0,
                'last_error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Manuell: alle unter Schwelle (oder einen Kontakt).
     *
     * @return array{sent: int, skipped: int, errors: list<string>}
     */
    public static function sendManual(User $actor, int $year, ?int $onlyContactId = null): array
    {
        if (!TimeClockService::canViewTeam($actor)) {
            throw new RuntimeException('Keine Berechtigung für Urlaubsplanungs-Erinnerungen.');
        }
        if ($year < 2000 || $year > 2100) {
            throw new InvalidArgumentException('Ungültiges Jahr.');
        }
        if (!MailSettings::isConfigured()) {
            throw new RuntimeException('E-Mail ist nicht konfiguriert (Einstellungen → E-Mail).');
        }

        return self::sendToBelowThreshold($year, 'manual', true, $onlyContactId);
    }

    /**
     * @return array{sent: int, skipped: int, errors: list<string>}
     */
    private static function sendToBelowThreshold(
        int $year,
        string $channel,
        bool $forceResend,
        ?int $onlyContactId = null,
    ): array {
        $result = ['sent' => 0, 'skipped' => 0, 'errors' => []];
        $emailOk = MailSettings::isConfigured();
        $stats = self::statsForYear($year);
        $sentRows = [];

        foreach ($stats as $row) {
            $cid = (int) $row['contact_id'];
            if ($onlyContactId !== null && $cid !== $onlyContactId) {
                continue;
            }
            if (empty($row['below_threshold'])) {
                if ($onlyContactId !== null) {
                    $result['errors'][] = 'Mitarbeiter liegt nicht unter der Planungsschwelle.';
                }
                continue;
            }
            if ((float) ($row['days_budget'] ?? 0) <= 0) {
                $result['skipped']++;
                continue;
            }
            if ($channel === 'auto' && !$forceResend && VacationPlanningReminderRepository::hasAutoReminder($cid, $year)) {
                $result['skipped']++;
                continue;
            }

            $email = $row['email'] ?? null;
            if ($email === null || $email === '') {
                $result['skipped']++;
                $result['errors'][] = ($row['label'] ?? '#' . $cid) . ': keine E-Mail.';
                continue;
            }
            if (!$emailOk) {
                $result['skipped']++;
                $result['errors'][] = 'E-Mail nicht konfiguriert.';
                break;
            }

            try {
                self::sendEmployeeMail($email, $row, $year);
                VacationPlanningReminderRepository::markSent(
                    $cid,
                    $year,
                    $channel,
                    (float) ($row['percent'] ?? 0),
                    (float) ($row['days_planned'] ?? 0),
                    (float) ($row['days_budget'] ?? 0),
                );
                $result['sent']++;
                $sentRows[] = $row;
            } catch (Throwable $e) {
                $result['errors'][] = ($row['label'] ?? '#' . $cid) . ': ' . $e->getMessage();
            }
        }

        if ($sentRows !== [] && $channel === 'auto') {
            try {
                self::sendHrDigest($sentRows, $year);
            } catch (Throwable $e) {
                $result['errors'][] = 'HR-Digest: ' . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function sendEmployeeMail(string $email, array $row, int $year): void
    {
        $label = (string) ($row['label'] ?? 'Mitarbeiter');
        $threshold = (float) ($row['threshold_percent'] ?? self::thresholdPercent());
        $percent = $row['percent'] !== null
            ? number_format((float) $row['percent'], 1, ',', '')
            : '—';
        $planned = number_format((float) ($row['days_planned'] ?? 0), 1, ',', '');
        $budget = number_format((float) ($row['days_budget'] ?? 0), 1, ',', '');
        $subject = sprintf('Erinnerung: Urlaubsplanung %d', $year);
        $html = '<p>Guten Tag ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>Bisher sind für <strong>' . (int) $year . '</strong> nur '
            . '<strong>' . htmlspecialchars($percent, ENT_QUOTES, 'UTF-8') . '&nbsp;%</strong> '
            . 'Ihres Jahresurlaubs geplant ('
            . htmlspecialchars($planned, ENT_QUOTES, 'UTF-8') . ' von '
            . htmlspecialchars($budget, ENT_QUOTES, 'UTF-8') . ' Tagen).</p>'
            . '<p>Bitte planen Sie möglichst frühzeitig mindestens '
            . htmlspecialchars(number_format($threshold, 0, ',', ''), ENT_QUOTES, 'UTF-8')
            . '&nbsp;% Ihres Anspruchs ein (beantragt oder genehmigt), damit Vertretung und Betrieb planbar bleiben.</p>'
            . '<p><a href="' . htmlspecialchars(App::publicBaseUrl() . '/app?page=zeiterfassung-urlaub&year=' . $year, ENT_QUOTES, 'UTF-8') . '">'
            . 'Urlaub beantragen</a></p>';

        MailService::send(new MailMessage(
            subject: $subject,
            htmlBody: $html,
            to: [$email],
        ));
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private static function sendHrDigest(array $rows, int $year): void
    {
        $recipients = OvertimeNotificationRecipients::managerEmailsForDigest();
        if ($recipients === []) {
            return;
        }
        $threshold = self::thresholdPercent();
        $items = [];
        foreach ($rows as $row) {
            $pct = $row['percent'] !== null
                ? number_format((float) $row['percent'], 1, ',', '') . ' %'
                : '—';
            $items[] = '<li>' . htmlspecialchars((string) ($row['label'] ?? ''), ENT_QUOTES, 'UTF-8')
                . ' — ' . htmlspecialchars($pct, ENT_QUOTES, 'UTF-8')
                . ' (' . htmlspecialchars(number_format((float) ($row['days_planned'] ?? 0), 1, ',', ''), ENT_QUOTES, 'UTF-8')
                . ' / ' . htmlspecialchars(number_format((float) ($row['days_budget'] ?? 0), 1, ',', ''), ENT_QUOTES, 'UTF-8')
                . ' Tage)</li>';
        }
        $html = '<p>Automatische Erinnerung Urlaubsplanung ' . (int) $year
            . ' (unter ' . htmlspecialchars(number_format($threshold, 0, ',', ''), ENT_QUOTES, 'UTF-8')
            . '&nbsp;% geplant) — an folgende Mitarbeiter gesendet:</p>'
            . '<ul>' . implode('', $items) . '</ul>'
            . '<p><a href="' . htmlspecialchars(App::publicBaseUrl() . '/app?page=zeiterfassung-urlaub&year=' . $year, ENT_QUOTES, 'UTF-8') . '">'
            . 'Urlaubsstatistik öffnen</a></p>';

        MailService::send(new MailMessage(
            subject: sprintf('Urlaubsplanung %d: %d Erinnerung(en) gesendet', $year, count($rows)),
            htmlBody: $html,
            to: $recipients,
        ));
    }

    /** @return array<string, mixed> */
    private static function loadAutoState(): array
    {
        $path = DG_ROOT . '/storage/vacation-planning-reminder-state.json';
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
            @mkdir($dir, 0755, true);
        }
        @file_put_contents(
            $dir . '/vacation-planning-reminder-state.json',
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}
