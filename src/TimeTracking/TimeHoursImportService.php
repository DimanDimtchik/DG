<?php
declare(strict_types=1);

/**
 * Import von Arbeitsstunden aus Excel/CSV (Shiftbase, Crewmeister, eigene Tabellen).
 * Schreibt Stempel-Events (source=import) und aggregiert Arbeitstage.
 */
final class TimeHoursImportService
{
    private const MAX_BYTES = 5_242_880; // 5 MB
    private const DEFAULT_START = '08:00';

    public static function canImport(?User $user): bool
    {
        if ($user === null || !Database::isConfigured()) {
            return false;
        }

        return DepartmentAccess::canAccessModule($user, 'zeiterfassung')
            && TimeClockService::canViewTeam($user);
    }

    /**
     * @param array<string, mixed> $file $_FILES[…]
     * @return array{
     *   imported_days: int,
     *   imported_events: int,
     *   skipped: int,
     *   errors: list<string>,
     *   message: string
     * }
     */
    public static function importUpload(array $file, User $user, array $post): array
    {
        if (!self::canImport($user)) {
            throw new RuntimeException('Kein Recht zum Stunden-Import.');
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Bitte eine Excel- (.xlsx) oder CSV-Datei hochladen.');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        $orig = (string) ($file['name'] ?? 'import.csv');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new InvalidArgumentException('Upload ungültig.');
        }
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new InvalidArgumentException('Datei zu groß (max. 5 MB).');
        }

        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, InstallImportSourcePresets::tabularExtensions(), true)) {
            throw new InvalidArgumentException('Nur Excel (.xlsx) oder CSV.');
        }

        $source = TimeHoursImportPresets::normalize((string) ($post['import_source'] ?? 'other'));
        $onConflict = (string) ($post['on_conflict'] ?? 'skip');
        if (!in_array($onConflict, ['skip', 'replace'], true)) {
            $onConflict = 'skip';
        }

        $dir = DG_ROOT . '/storage/time-hours-import';
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Import-Verzeichnis nicht anlegbar.');
        }
        $stored = $dir . '/' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!move_uploaded_file($tmp, $stored)) {
            throw new RuntimeException('Datei konnte nicht gespeichert werden.');
        }

        try {
            return self::importFile($stored, $source, $onConflict, (int) ($user->id ?? 0));
        } finally {
            // Datei bleibt für Audit im storage; kein Soft-Delete nötig
        }
    }

    public static function sendTemplateDownload(): void
    {
        $csv = InstallCsvHelper::templateCsv(
            ['E-Mail', 'Login', 'Name', 'Datum', 'Beginn', 'Ende', 'Pause_Minuten', 'Stunden'],
            [
                'E-Mail' => 'max.mustermann@firma.de',
                'Login' => '1001',
                'Name' => 'Max Mustermann',
                'Datum' => '15.09.2026',
                'Beginn' => '08:00',
                'Ende' => '17:00',
                'Pause_Minuten' => '30',
                'Stunden' => '',
            ]
        );
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="arbeitsstunden-import-vorlage.csv"');
        echo $csv;
        exit;
    }

    /**
     * @return array{
     *   imported_days: int,
     *   imported_events: int,
     *   skipped: int,
     *   errors: list<string>,
     *   message: string
     * }
     */
    private static function importFile(string $path, string $source, string $onConflict, int $createdBy): array
    {
        MigrationRunner::runPending();
        $rows = InstallCsvHelper::readRows($path);
        if ($rows === [] || count($rows) < 2) {
            throw new InvalidArgumentException('Datei enthält keine Datenzeilen.');
        }

        $map = InstallCsvHelper::mapColumns($rows[0], TimeHoursImportPresets::aliases($source));
        if ($map['work_date'] === null) {
            throw new InvalidArgumentException('Spalte „Datum“ fehlt bzw. nicht erkannt.');
        }
        $hasIdentity = $map['email'] !== null || $map['login'] !== null
            || $map['full_name'] !== null || $map['first_name'] !== null || $map['last_name'] !== null;
        if (!$hasIdentity) {
            throw new InvalidArgumentException('Mitarbeiter-Spalte fehlt (E-Mail, Login/Personalnummer oder Name).');
        }
        $hasTime = $map['clock_in'] !== null || $map['clock_out'] !== null
            || $map['worked_hours'] !== null || $map['worked_minutes'] !== null;
        if (!$hasTime) {
            throw new InvalidArgumentException('Zeit-Spalten fehlen (Beginn/Ende oder Stunden).');
        }

        /** @var array<string, list<array{clock_in: string, clock_out: string, break_minutes: int}>> $grouped */
        $grouped = [];
        $errors = [];
        $skipped = 0;
        $staffIndex = self::buildStaffIndex();

        for ($i = 1, $n = count($rows); $i < $n; $i++) {
            $line = $rows[$i];
            if (!is_array($line) || InstallCsvHelper::isEmptyRow($line)) {
                continue;
            }
            $raw = InstallCsvHelper::rowFromMap($map, $line);
            $lineNo = $i + 1;

            $contactId = self::resolveContactId($raw, $staffIndex);
            if ($contactId === null) {
                $skipped++;
                if (count($errors) < 40) {
                    $label = self::identityLabel($raw);
                    $errors[] = "Zeile {$lineNo}: Mitarbeiter nicht gefunden ({$label}).";
                }
                continue;
            }

            $date = self::parseDate((string) ($raw['work_date'] ?? ''));
            if ($date === null) {
                $skipped++;
                if (count($errors) < 40) {
                    $errors[] = "Zeile {$lineNo}: Datum ungültig.";
                }
                continue;
            }

            $segment = self::buildSegment($raw, $date);
            if ($segment === null) {
                $skipped++;
                if (count($errors) < 40) {
                    $errors[] = "Zeile {$lineNo}: Beginn/Ende oder Stunden nicht lesbar.";
                }
                continue;
            }

            $key = $contactId . '|' . $date;
            $grouped[$key][] = $segment;
        }

        $importedDays = 0;
        $importedEvents = 0;
        $touchedContacts = [];

        foreach ($grouped as $key => $segments) {
            [$contactIdStr, $date] = explode('|', $key, 2);
            $contactId = (int) $contactIdStr;
            $existing = TimeClockRepository::eventsForContact($contactId, $date);

            if ($existing !== []) {
                if ($onConflict === 'skip') {
                    $skipped++;
                    if (count($errors) < 40) {
                        $errors[] = "{$date} Kontakt #{$contactId}: bereits Stempeldaten — übersprungen.";
                    }
                    continue;
                }
                TimeClockRepository::deleteForContactDay($contactId, $date);
            }

            usort(
                $segments,
                static fn (array $a, array $b): int => strcmp($a['clock_in'], $b['clock_in'])
            );

            $note = 'Import ' . TimeHoursImportPresets::normalize($source);
            foreach ($segments as $seg) {
                $events = self::eventsForSegment($seg);
                foreach ($events as [$type, $at]) {
                    TimeClockRepository::insert(
                        $contactId,
                        $type,
                        $at,
                        TimeClockRepository::SOURCE_IMPORT,
                        $note,
                        $createdBy > 0 ? $createdBy : null
                    );
                    $importedEvents++;
                }
            }

            TimeWorkDayService::aggregateContactDay($contactId, $date);
            $touchedContacts[$contactId] = true;
            $importedDays++;
        }

        foreach (array_keys($touchedContacts) as $cid) {
            try {
                OvertimeLotRepository::syncAccrualsFromWorkDays((int) $cid);
            } catch (Throwable) {
                // Soft: Aggregation hat Priorität
            }
        }

        $msg = sprintf(
            'Stunden-Import: %d Tag(e), %d Stempel-Event(s). Übersprungen/Hinweise: %d.',
            $importedDays,
            $importedEvents,
            $skipped
        );

        return [
            'imported_days' => $importedDays,
            'imported_events' => $importedEvents,
            'skipped' => $skipped,
            'errors' => $errors,
            'message' => $msg,
        ];
    }

    /**
     * @return array{
     *   by_email: array<string, int>,
     *   by_login: array<string, int>,
     *   by_personnel: array<string, int>,
     *   by_name: array<string, int>
     * }
     */
    private static function buildStaffIndex(): array
    {
        $byEmail = [];
        $byLogin = [];
        $byPersonnel = [];
        $byName = [];

        foreach (TimeMonthReportService::staffOptions() as $opt) {
            $id = (int) ($opt['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $c = ContactRepository::findById($id);
            if ($c === null) {
                continue;
            }
            $email = strtolower(trim((string) ($c->email ?? '')));
            if ($email !== '' && !isset($byEmail[$email])) {
                $byEmail[$email] = $id;
            }
            $email2 = strtolower(trim((string) ($c->email2 ?? '')));
            if ($email2 !== '' && !isset($byEmail[$email2])) {
                $byEmail[$email2] = $id;
            }
            $login = strtolower(trim((string) ($c->login ?? '')));
            if ($login !== '' && !isset($byLogin[$login])) {
                $byLogin[$login] = $id;
            }
            $raw = is_array($c->employeeData ?? null) ? $c->employeeData : [];
            $pnr = strtolower(trim((string) ($raw['datev_personnel_number'] ?? '')));
            if ($pnr !== '' && !isset($byPersonnel[$pnr])) {
                $byPersonnel[$pnr] = $id;
            }
            $label = strtolower(trim((string) ($opt['label'] ?? '')));
            if ($label !== '' && !isset($byName[$label])) {
                $byName[$label] = $id;
            }
            $dn = strtolower(trim((string) ($c->displayName ?? '')));
            if ($dn !== '' && !isset($byName[$dn])) {
                $byName[$dn] = $id;
            }
        }

        return [
            'by_email' => $byEmail,
            'by_login' => $byLogin,
            'by_personnel' => $byPersonnel,
            'by_name' => $byName,
        ];
    }

    /**
     * @param array<string, string> $raw
     * @param array{
     *   by_email: array<string, int>,
     *   by_login: array<string, int>,
     *   by_personnel: array<string, int>,
     *   by_name: array<string, int>
     * } $index
     */
    private static function resolveContactId(array $raw, array $index): ?int
    {
        $email = strtolower(trim((string) ($raw['email'] ?? '')));
        if ($email !== '' && isset($index['by_email'][$email])) {
            return $index['by_email'][$email];
        }

        $login = strtolower(trim((string) ($raw['login'] ?? '')));
        if ($login !== '') {
            if (isset($index['by_login'][$login])) {
                return $index['by_login'][$login];
            }
            if (isset($index['by_personnel'][$login])) {
                return $index['by_personnel'][$login];
            }
        }

        $full = trim((string) ($raw['full_name'] ?? ''));
        if ($full === '') {
            $full = trim(
                trim((string) ($raw['first_name'] ?? '')) . ' ' . trim((string) ($raw['last_name'] ?? ''))
            );
        }
        $fullKey = strtolower($full);
        if ($fullKey !== '' && isset($index['by_name'][$fullKey])) {
            return $index['by_name'][$fullKey];
        }

        return null;
    }

    /** @param array<string, string> $raw */
    private static function identityLabel(array $raw): string
    {
        foreach (['email', 'login', 'full_name'] as $k) {
            $v = trim((string) ($raw[$k] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }
        $n = trim(trim((string) ($raw['first_name'] ?? '')) . ' ' . trim((string) ($raw['last_name'] ?? '')));

        return $n !== '' ? $n : 'ohne Kennung';
    }

    /**
     * @param array<string, string> $raw
     * @return array{clock_in: string, clock_out: string, break_minutes: int}|null
     */
    private static function buildSegment(array $raw, string $date): ?array
    {
        $break = self::parseBreakMinutes((string) ($raw['break_minutes'] ?? ''));
        $in = self::parseDateTimeOnDay((string) ($raw['clock_in'] ?? ''), $date);
        $out = self::parseDateTimeOnDay((string) ($raw['clock_out'] ?? ''), $date);

        if ($in !== null && $out !== null) {
            $inTs = strtotime($in);
            $outTs = strtotime($out);
            if ($inTs === false || $outTs === false) {
                return null;
            }
            if ($outTs <= $inTs) {
                // Nachtschicht / Ende am Folgetag
                $out = date('Y-m-d H:i:s', $outTs + 86400);
            }

            return ['clock_in' => $in, 'clock_out' => $out, 'break_minutes' => $break];
        }

        $workedMin = self::parseWorkedMinutes(
            (string) ($raw['worked_hours'] ?? ''),
            (string) ($raw['worked_minutes'] ?? '')
        );
        if ($workedMin === null || $workedMin < 1) {
            return null;
        }

        if ($in === null) {
            $in = $date . ' ' . self::DEFAULT_START . ':00';
        }
        $baseTs = strtotime($in);
        if ($baseTs === false) {
            return null;
        }
        $out = date('Y-m-d H:i:s', $baseTs + (($workedMin + $break) * 60));

        return ['clock_in' => $in, 'clock_out' => $out, 'break_minutes' => $break];
    }

    /**
     * @param array{clock_in: string, clock_out: string, break_minutes: int} $seg
     * @return list<array{0: string, 1: string}>
     */
    private static function eventsForSegment(array $seg): array
    {
        $in = $seg['clock_in'];
        $out = $seg['clock_out'];
        $break = max(0, (int) $seg['break_minutes']);
        $events = [[TimeClockRepository::EVENT_CLOCK_IN, $in]];

        if ($break > 0) {
            $inTs = strtotime($in);
            $outTs = strtotime($out);
            if ($inTs !== false && $outTs !== false && $outTs > $inTs) {
                $span = (int) floor(($outTs - $inTs) / 60);
                $work = max(0, $span - $break);
                $midOffset = (int) floor($work / 2);
                $breakStart = date('Y-m-d H:i:s', $inTs + ($midOffset * 60));
                $breakEnd = date('Y-m-d H:i:s', $inTs + (($midOffset + $break) * 60));
                if (strtotime($breakEnd) < $outTs) {
                    $events[] = [TimeClockRepository::EVENT_BREAK_START, $breakStart];
                    $events[] = [TimeClockRepository::EVENT_BREAK_END, $breakEnd];
                }
            }
        }

        $events[] = [TimeClockRepository::EVENT_CLOCK_OUT, $out];

        return $events;
    }

    private static function parseDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        // Excel-Seriennummer
        if (preg_match('/^\d{5}(?:\.\d+)?$/', $value)) {
            $serial = (float) $value;
            $unix = (int) round(($serial - 25569) * 86400);
            if ($unix > 0) {
                return gmdate('Y-m-d', $unix);
            }
        }
        foreach (['Y-m-d', 'd.m.Y', 'd.m.y', 'd/m/Y', 'd-m-Y', 'm/d/Y'] as $fmt) {
            $dt = DateTimeImmutable::createFromFormat('!' . $fmt, $value);
            if ($dt instanceof DateTimeImmutable) {
                return $dt->format('Y-m-d');
            }
        }
        $ts = strtotime($value);

        return $ts !== false ? date('Y-m-d', $ts) : null;
    }

    private static function parseDateTimeOnDay(string $value, string $date): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        // Volles Datum/Zeit
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'd.m.Y H:i:s', 'd.m.Y H:i', 'd.m.y H:i'] as $fmt) {
            $dt = DateTimeImmutable::createFromFormat('!' . $fmt, $value);
            if ($dt instanceof DateTimeImmutable) {
                return $dt->format('Y-m-d H:i:s');
            }
        }
        if (preg_match('/\d{4}-\d{2}-\d{2}/', $value)) {
            $ts = strtotime($value);
            if ($ts !== false) {
                return date('Y-m-d H:i:s', $ts);
            }
        }
        // Nur Uhrzeit
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $m)) {
            $h = (int) $m[1];
            $i = (int) $m[2];
            $s = isset($m[3]) ? (int) $m[3] : 0;
            if ($h > 23 || $i > 59 || $s > 59) {
                return null;
            }

            return sprintf('%s %02d:%02d:%02d', $date, $h, $i, $s);
        }
        // Excel Zeitanteil 0.5 = 12:00
        if (preg_match('/^0?\.\d+$/', $value) || (is_numeric($value) && (float) $value >= 0 && (float) $value < 1)) {
            $frac = (float) $value;
            $seconds = (int) round($frac * 86400);

            return $date . ' ' . gmdate('H:i:s', $seconds);
        }

        return null;
    }

    private static function parseBreakMinutes(string $value): int
    {
        $value = trim(str_replace(',', '.', $value));
        if ($value === '') {
            return 0;
        }
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $value, $m)) {
            return ((int) $m[1] * 60) + (int) $m[2];
        }
        if (is_numeric($value)) {
            $n = (float) $value;
            // Werte < 15 als Stunden interpretieren (0.5 = 30 Min), sonst Minuten
            if ($n > 0 && $n < 15 && str_contains($value, '.')) {
                return (int) round($n * 60);
            }

            return (int) round($n);
        }

        return 0;
    }

    private static function parseWorkedMinutes(string $hoursRaw, string $minutesRaw): ?int
    {
        $minutesRaw = trim($minutesRaw);
        if ($minutesRaw !== '' && is_numeric(str_replace(',', '.', $minutesRaw))) {
            return max(0, (int) round((float) str_replace(',', '.', $minutesRaw)));
        }
        $hoursRaw = trim(str_replace(',', '.', $hoursRaw));
        if ($hoursRaw === '') {
            return null;
        }
        if (preg_match('/^(\d{1,3}):(\d{2})$/', $hoursRaw, $m)) {
            return ((int) $m[1] * 60) + (int) $m[2];
        }
        if (is_numeric($hoursRaw)) {
            return (int) round(((float) $hoursRaw) * 60);
        }

        return null;
    }
}
