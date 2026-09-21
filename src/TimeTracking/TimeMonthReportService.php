<?php
declare(strict_types=1);

/**
 * Zeiterfassung Z2c: Monatsblatt Soll/Ist/Diff + CSV (ArbZG-Nachweis, kein DATEV).
 */
final class TimeMonthReportService
{
    /**
     * @return array{
     *   year_month: string,
     *   contact_id: int,
     *   contact_label: string,
     *   days: list<array{
     *     date: string,
     *     date_display: string,
     *     weekday: string,
     *     scheduled_minutes: int,
     *     worked_minutes: int,
     *     break_minutes: int,
     *     diff_minutes: int,
     *     overtime_minutes: int,
     *     scheduled_display: string,
     *     worked_display: string,
     *     break_display: string,
     *     diff_display: string,
     *     overtime_display: string,
     *     source: 'aggregated'|'live'|'empty'
     *   }>,
     *   totals: array{
     *     scheduled_minutes: int,
     *     worked_minutes: int,
     *     break_minutes: int,
     *     diff_minutes: int,
     *     overtime_minutes: int,
     *     scheduled_display: string,
     *     worked_display: string,
     *     break_display: string,
     *     diff_display: string,
     *     overtime_display: string
     *   }
     * }
     */
    public static function monthReport(int $contactId, string $yearMonth): array
    {
        if ($contactId < 1 || !preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            throw new InvalidArgumentException('Kontakt und Monat (JJJJ-MM) erforderlich.');
        }

        $from = $yearMonth . '-01';
        $dtFrom = DateTimeImmutable::createFromFormat('Y-m-d', $from);
        if ($dtFrom === false) {
            throw new InvalidArgumentException('Ungültiger Monat.');
        }
        $to = $dtFrom->modify('last day of this month')->format('Y-m-d');
        $daysInMonth = (int) $dtFrom->format('t');

        $aggregated = [];
        if (Database::isConfigured()) {
            foreach (TimeWorkDayRepository::listForContactRange($contactId, $from, $to) as $row) {
                $d = (string) ($row['work_date'] ?? '');
                if ($d !== '') {
                    $aggregated[$d] = $row;
                }
            }
        }

        $employeeData = self::employeeDataForContact($contactId);
        $weekdayLabels = ['', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
        $days = [];
        $sumSched = 0;
        $sumWork = 0;
        $sumBreak = 0;
        $sumOt = 0;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%s-%02d', $yearMonth, $day);
            $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);
            $wd = $dt !== false ? (int) $dt->format('N') : 0;

            // Z3d: Soll immer live (Schicht schlägt Aggregation).
            $scheduled = TimeScheduleService::scheduledMinutesFor($contactId, $date);
            $scheduleSource = TimeScheduleService::scheduleSource($contactId, $date);
            $shift = TimeScheduleService::shiftAssignmentFor($contactId, $date);
            $shiftName = is_array($shift) ? (string) ($shift['template_name'] ?? '') : '';

            if (isset($aggregated[$date])) {
                $row = $aggregated[$date];
                $worked = max(0, (int) ($row['worked_minutes'] ?? 0));
                $break = max(0, (int) ($row['break_minutes'] ?? 0));
                $source = 'aggregated';
            } else {
                $summary = TimeClockService::daySummary($contactId, $date);
                $worked = max(0, (int) ($summary['worked_minutes'] ?? 0));
                $break = max(0, (int) ($summary['break_minutes'] ?? 0));
                $events = is_array($summary['events'] ?? null) ? $summary['events'] : [];
                $source = $events !== [] ? 'live' : 'empty';
            }

            $overtime = 0;
            if (
                $source !== 'empty'
                && EmployeeData::overtimeAllowed($employeeData)
                && !EmployeeData::isMinijob($employeeData)
                && $scheduled > 0
            ) {
                $overtime = max(0, $worked - $scheduled);
            }

            $diff = $worked - $scheduled;
            $sumSched += $scheduled;
            $sumWork += $worked;
            $sumBreak += $break;
            $sumOt += $overtime;

            $dayWarnings = [];
            if ($worked > ArbzgComplianceService::MAX_DAILY_MINUTES) {
                $dayWarnings[] = 'ArbZG: >10 h (Soft)';
            }
            if (
                $scheduleSource === 'shift'
                && $scheduled > 0
                && $worked > 0
                && abs($worked - $scheduled) >= 60
            ) {
                $dayWarnings[] = 'Ist weicht ≥1 h vom Schicht-Soll ab';
            }

            $days[] = [
                'date' => $date,
                'date_display' => $dt !== false ? $dt->format('d.m.Y') : $date,
                'weekday' => $weekdayLabels[$wd] ?? '',
                'scheduled_minutes' => $scheduled,
                'worked_minutes' => $worked,
                'break_minutes' => $break,
                'diff_minutes' => $diff,
                'overtime_minutes' => $overtime,
                'scheduled_display' => TimeClockService::formatMinutes($scheduled),
                'worked_display' => TimeClockService::formatMinutes($worked),
                'break_display' => TimeClockService::formatMinutes($break),
                'diff_display' => self::formatSignedMinutes($diff),
                'overtime_display' => TimeClockService::formatMinutes($overtime),
                'schedule_source' => $scheduleSource,
                'shift_name' => $shiftName,
                'source' => $source,
                'arbzg_flags' => $dayWarnings,
            ];
        }

        $sumDiff = $sumWork - $sumSched;

        return [
            'year_month' => $yearMonth,
            'contact_id' => $contactId,
            'contact_label' => self::contactLabel($contactId),
            'days' => $days,
            'totals' => [
                'scheduled_minutes' => $sumSched,
                'worked_minutes' => $sumWork,
                'break_minutes' => $sumBreak,
                'diff_minutes' => $sumDiff,
                'overtime_minutes' => $sumOt,
                'scheduled_display' => TimeClockService::formatMinutes($sumSched),
                'worked_display' => TimeClockService::formatMinutes($sumWork),
                'break_display' => TimeClockService::formatMinutes($sumBreak),
                'diff_display' => self::formatSignedMinutes($sumDiff),
                'overtime_display' => TimeClockService::formatMinutes($sumOt),
            ],
        ];
    }

    /**
     * CSV mit BOM, Semikolon — GoBD-nachvollziehbar, kein DATEV-Lohn.
     */
    public static function toCsv(array $report): string
    {
        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            throw new RuntimeException('CSV-Puffer konnte nicht geöffnet werden.');
        }
        fprintf($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            'Datum',
            'Wochentag',
            'Soll_Minuten',
            'Ist_Minuten',
            'Pause_Minuten',
            'Diff_Minuten',
            'Ueberstunden_Minuten',
            'Soll',
            'Ist',
            'Pause',
            'Diff',
            'Ueberstunden',
            'Quelle',
            'Kontakt_ID',
            'Kontakt',
            'Monat',
        ], ';');

        foreach ($report['days'] as $day) {
            if (!is_array($day)) {
                continue;
            }
            fputcsv($out, [
                (string) ($day['date'] ?? ''),
                (string) ($day['weekday'] ?? ''),
                (int) ($day['scheduled_minutes'] ?? 0),
                (int) ($day['worked_minutes'] ?? 0),
                (int) ($day['break_minutes'] ?? 0),
                (int) ($day['diff_minutes'] ?? 0),
                (int) ($day['overtime_minutes'] ?? 0),
                (string) ($day['scheduled_display'] ?? ''),
                (string) ($day['worked_display'] ?? ''),
                (string) ($day['break_display'] ?? ''),
                (string) ($day['diff_display'] ?? ''),
                (string) ($day['overtime_display'] ?? ''),
                (string) ($day['source'] ?? ''),
                (int) ($report['contact_id'] ?? 0),
                (string) ($report['contact_label'] ?? ''),
                (string) ($report['year_month'] ?? ''),
            ], ';');
        }

        $totals = is_array($report['totals'] ?? null) ? $report['totals'] : [];
        fputcsv($out, [
            'SUMME',
            '',
            (int) ($totals['scheduled_minutes'] ?? 0),
            (int) ($totals['worked_minutes'] ?? 0),
            (int) ($totals['break_minutes'] ?? 0),
            (int) ($totals['diff_minutes'] ?? 0),
            (int) ($totals['overtime_minutes'] ?? 0),
            (string) ($totals['scheduled_display'] ?? ''),
            (string) ($totals['worked_display'] ?? ''),
            (string) ($totals['break_display'] ?? ''),
            (string) ($totals['diff_display'] ?? ''),
            (string) ($totals['overtime_display'] ?? ''),
            'totals',
            (int) ($report['contact_id'] ?? 0),
            (string) ($report['contact_label'] ?? ''),
            (string) ($report['year_month'] ?? ''),
        ], ';');

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return is_string($csv) ? $csv : '';
    }

    public static function normalizeYearMonth(?string $raw): string
    {
        $raw = trim((string) $raw);
        if (preg_match('/^\d{4}-\d{2}$/', $raw)) {
            return $raw;
        }

        return date('Y-m');
    }

    /**
     * Zielkontakt: eigen oder (bei canViewTeam) gewählter Staff.
     */
    public static function resolveContactId(User $user, ?int $requestedId): ?int
    {
        $own = ContactRepository::findStaffContactIdForUser($user);
        $requestedId = $requestedId !== null && $requestedId > 0 ? $requestedId : null;

        if ($requestedId === null) {
            return $own;
        }
        if ($own !== null && $requestedId === $own) {
            return $own;
        }
        if (!TimeClockService::canViewTeam($user)) {
            return $own;
        }
        if (!self::isStaffContact($requestedId)) {
            return $own;
        }

        return $requestedId;
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public static function staffOptions(): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        $stmt = Database::pdo()->query(
            "SELECT id, display_name, company_name
             FROM dg_contacts
             WHERE contact_role IN ('dg_eigenmitarbeiter', 'administrator', 'mitarbeiter')
             ORDER BY display_name ASC, id ASC"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $label = trim((string) ($row['display_name'] ?? ''));
            if ($label === '') {
                $label = trim((string) ($row['company_name'] ?? ''));
            }
            if ($label === '') {
                $label = 'Kontakt #' . $id;
            }
            $out[] = ['id' => $id, 'label' => $label];
        }

        return $out;
    }

    private static function isStaffContact(int $contactId): bool
    {
        foreach (self::staffOptions() as $opt) {
            if ((int) ($opt['id'] ?? 0) === $contactId) {
                return true;
            }
        }

        return false;
    }

    private static function contactLabel(int $contactId): string
    {
        $c = ContactRepository::findById($contactId);
        if ($c === null) {
            return 'Kontakt #' . $contactId;
        }
        $label = trim($c->displayName);
        if ($label === '') {
            $label = trim($c->companyName);
        }
        if ($label === '') {
            $label = 'Kontakt #' . $contactId;
        }

        return $label;
    }

    /**
     * @return array<string, mixed>
     */
    private static function employeeDataForContact(int $contactId): array
    {
        $contact = ContactRepository::findById($contactId);
        if ($contact === null) {
            return EmployeeData::empty();
        }
        $raw = $contact->employeeData ?? [];

        return is_array($raw) ? EmployeeData::sanitize($raw) : EmployeeData::empty();
    }

    private static function formatSignedMinutes(int $minutes): string
    {
        $sign = $minutes < 0 ? '-' : '';
        $abs = abs($minutes);

        return $sign . TimeClockService::formatMinutes($abs);
    }
}
