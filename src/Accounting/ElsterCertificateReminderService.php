<?php
declare(strict_types=1);

/**
 * Erinnerung vor Ablauf des ELSTER-Softwarezertifikats.
 * Empfänger: Buchhaltung; Geschäftsführung nur, wenn kein Buchhalter hinterlegt ist.
 */
final class ElsterCertificateReminderService
{
    /**
     * @return array{sent: int, skipped: int, errors: list<string>, recipients: list<string>, threshold: ?int}
     */
    public static function runAutomatic(?string $today = null): array
    {
        $today = $today ?? date('Y-m-d');
        $result = [
            'sent' => 0,
            'skipped' => 0,
            'errors' => [],
            'recipients' => [],
            'threshold' => null,
        ];

        if (!Database::isConfigured()) {
            return $result;
        }

        $stored = SettingsStore::get(ElsterSettings::STORE_KEY, ElsterSettings::defaults());
        if (empty($stored['reminder_enabled'])) {
            $result['skipped']++;

            return $result;
        }

        $expiresAt = trim((string) ($stored['certificate_expires_at'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiresAt)) {
            $result['skipped']++;

            return $result;
        }

        $days = ElsterCertificateStorage::daysUntilExpiry($expiresAt);
        if ($days === null) {
            $result['skipped']++;

            return $result;
        }

        $sentMap = is_array($stored['reminder_sent'] ?? null) ? $stored['reminder_sent'] : [];
        $threshold = self::nextUnsentThreshold($days, $expiresAt, $sentMap);
        if ($threshold === null) {
            $result['skipped']++;

            return $result;
        }

        $key = $expiresAt . ':' . $threshold;

        $recipients = self::reminderRecipients();
        $result['recipients'] = $recipients;
        $result['threshold'] = $threshold;

        if ($recipients === []) {
            $result['errors'][] = 'Keine Empfänger (Buchhaltung/GF) mit E-Mail gefunden.';
            $result['skipped']++;

            return $result;
        }

        if (!MailSettings::isConfigured()) {
            $result['errors'][] = 'SMTP nicht konfiguriert — Erinnerung nicht gesendet.';
            $result['skipped']++;

            return $result;
        }

        try {
            self::sendMail($recipients, $expiresAt, $days, $threshold);
            $sentMap[$key] = $today;
            $stored['reminder_sent'] = $sentMap;
            SettingsStore::set(ElsterSettings::STORE_KEY, $stored);
            $result['sent'] = 1;
            self::log('sent threshold=' . $threshold . ' to=' . implode(',', $recipients));
        } catch (Throwable $e) {
            $result['errors'][] = $e->getMessage();
            self::log('error: ' . $e->getMessage());
        }

        return $result;
    }

    public static function runIfDue(): void
    {
        if (!Database::isConfigured()) {
            return;
        }

        $today = date('Y-m-d');
        $stateFile = DG_ROOT . '/storage/elster/reminder-auto-state.json';
        $state = [];
        if (is_file($stateFile)) {
            $raw = json_decode((string) file_get_contents($stateFile), true);
            $state = is_array($raw) ? $raw : [];
        }
        if (($state['last_run'] ?? '') === $today) {
            return;
        }

        $result = self::runAutomatic($today);
        $dir = dirname($stateFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        @file_put_contents($stateFile, json_encode([
            'last_run' => $today,
            'sent' => (int) ($result['sent'] ?? 0),
            'skipped' => (int) ($result['skipped'] ?? 0),
            'threshold' => $result['threshold'],
            'last_error' => ($result['errors'] ?? []) !== []
                ? implode('; ', $result['errors'])
                : null,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * Buchhaltung zuerst; GF nur wenn keine Buchhaltungs-Empfänger.
     *
     * @return list<string>
     */
    public static function reminderRecipients(): array
    {
        $buchhaltung = self::emailsForBuchhaltung();
        if ($buchhaltung !== []) {
            return $buchhaltung;
        }

        return self::emailsForGeschaeftsfuehrung();
    }

    /**
     * @return list<string>
     */
    public static function emailsForBuchhaltung(): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();
        $emails = [];
        foreach (DepartmentRepository::allWithMembers() as $department) {
            $deptId = (string) ($department['id'] ?? '');
            $name = mb_strtolower((string) ($department['name'] ?? ''));
            $isAccounting = $deptId === 'dept-buchhaltung'
                || str_contains($name, 'buchhaltung')
                || str_contains($name, 'finanz');
            if (!$isAccounting) {
                $modules = is_array($department['modules'] ?? null) ? $department['modules'] : [];
                $level = (string) ($modules['buchhaltung'] ?? 'none');
                $isAccounting = $level === 'full';
            }
            if (!$isAccounting) {
                continue;
            }
            foreach ($department['members'] ?? [] as $member) {
                $email = self::emailForMember($member);
                if ($email !== null) {
                    $emails[] = $email;
                }
            }
        }

        return self::uniqueEmails($emails);
    }

    /**
     * @return list<string>
     */
    public static function emailsForGeschaeftsfuehrung(): array
    {
        if (!Database::isConfigured()) {
            return self::companyFallback();
        }
        MigrationRunner::runPending();
        $emails = [];
        foreach (DepartmentRepository::allWithMembers() as $department) {
            $deptId = (string) ($department['id'] ?? '');
            $name = mb_strtolower((string) ($department['name'] ?? ''));
            $isExecutive = $deptId === 'dept-geschaeftsfuehrung'
                || str_contains($name, 'geschäftsführ')
                || str_contains($name, 'geschaeftsfuehr');
            if (!$isExecutive) {
                continue;
            }
            foreach ($department['members'] ?? [] as $member) {
                $email = self::emailForMember($member);
                if ($email !== null) {
                    $emails[] = $email;
                }
            }
        }
        $emails = self::uniqueEmails($emails);

        return $emails !== [] ? $emails : self::companyFallback();
    }

    /**
     * @param list<string> $recipients
     */
    private static function sendMail(array $recipients, string $expiresAt, int $days, int $threshold): void
    {
        $company = CompanySettings::displayName();
        $label = trim((string) (ElsterCertificateStorage::meta()['certificate_label'] ?? ''));
        $loginUrl = ElsterCertificateStorage::LOGIN_URL;
        $settingsUrl = SettingsRegistry::tabUrl('elster');

        if ($days < 0) {
            $subject = 'ELSTER-Zertifikat abgelaufen — ' . ($company !== '' ? $company : 'CRM');
            $lead = 'Das ELSTER-Softwarezertifikat ist seit ' . abs($days) . ' Tag(en) abgelaufen (Ablauf: '
                . self::formatDe($expiresAt) . ').';
        } elseif ($days === 0) {
            $subject = 'ELSTER-Zertifikat läuft heute ab — ' . ($company !== '' ? $company : 'CRM');
            $lead = 'Das ELSTER-Softwarezertifikat läuft heute ab.';
        } else {
            $subject = 'ELSTER-Zertifikat: noch ' . $days . ' Tag(e) gültig — '
                . ($company !== '' ? $company : 'CRM');
            $lead = 'Erinnerung (Schwelle ' . $threshold . ' Tage): Das Zertifikat läuft am '
                . self::formatDe($expiresAt) . ' ab (noch ' . $days . ' Tag(e)).';
        }

        $html = '<p>' . htmlspecialchars($lead, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        if ($label !== '') {
            $html .= '<p>Datei: <strong>' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</strong></p>';
        }
        $html .= '<p>Bitte rechtzeitig in Mein ELSTER verlängern (nur solange das Zertifikat noch gültig ist) '
            . 'und die neue Datei unter Einstellungen → ELSTER im CRM hinterlegen.</p>';
        $html .= '<p><a href="' . htmlspecialchars($loginUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '">Zur ELSTER-Anmeldeseite</a></p>';
        $html .= '<p><a href="' . htmlspecialchars($settingsUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '">ELSTER-Einstellungen im CRM</a></p>';
        $html .= '<p style="color:#666;font-size:12px">Das CRM meldet Sie nicht automatisch bei ELSTER an. '
            . 'Zertifikat und Passwort bleiben im CRM (Vorbereitung für spätere ERiC-Abgaben).</p>';

        MailService::send(new MailMessage(
            to: $recipients,
            subject: $subject,
            htmlBody: $html
        ));
    }

    /**
     * Nächste fällige, noch nicht gesendete Schwelle (90 → 40 → 30 → 2).
     *
     * @param array<string, mixed> $sentMap
     */
    private static function nextUnsentThreshold(int $daysLeft, string $expiresAt, array $sentMap): ?int
    {
        $thresholds = ElsterCertificateStorage::REMINDER_DAYS;
        if ($daysLeft < 0) {
            $thresholds = [2];
        }
        foreach ($thresholds as $threshold) {
            if ($daysLeft > $threshold && $daysLeft >= 0) {
                continue;
            }
            $key = $expiresAt . ':' . $threshold;
            if (empty($sentMap[$key])) {
                return $threshold;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $member
     */
    private static function emailForMember(array $member): ?string
    {
        $uid = (int) ($member['user_id'] ?? 0);
        if ($uid > 0) {
            $user = UserRepository::findById($uid);
            if ($user !== null) {
                $email = strtolower(trim($user->email));
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return $email;
                }
            }
        }
        $cid = (int) ($member['contact_id'] ?? 0);
        if ($cid > 0) {
            return OvertimeNotificationRecipients::employeeEmailForContact($cid);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function companyFallback(): array
    {
        $email = strtolower(trim(CompanySettings::mailEmail()));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [$email];
        }

        return [];
    }

    /**
     * @param list<string> $emails
     * @return list<string>
     */
    private static function uniqueEmails(array $emails): array
    {
        $out = [];
        $seen = [];
        foreach ($emails as $email) {
            $email = strtolower(trim($email));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;
            $out[] = $email;
        }

        return $out;
    }

    private static function formatDe(string $ymd): string
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $ymd);

        return $dt !== false ? $dt->format('d.m.Y') : $ymd;
    }

    private static function log(string $line): void
    {
        $dir = DG_ROOT . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        @file_put_contents(
            $dir . '/elster-certificate-reminder.log',
            date('c') . ' ' . $line . "\n",
            FILE_APPEND
        );
    }
}
