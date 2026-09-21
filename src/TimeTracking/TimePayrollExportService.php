<?php
declare(strict_types=1);

/**
 * Zeiterfassung Z6b/Z6c: CSV-Monats-Lohnzeiten-Export + DATEV-Übergabe + Protokoll.
 * Keine eigene Lohnabrechnung.
 */
final class TimePayrollExportService
{
    public static function canExport(User $user): bool
    {
        return TimeClockService::canViewTeam($user);
    }

    /**
     * @return array{
     *   year_month: string,
     *   rows: list<array<string, mixed>>,
     *   totals: array<string, float|int>
     * }
     */
    public static function monthDataset(string $yearMonth): array
    {
        $yearMonth = TimeMonthReportService::normalizeYearMonth($yearMonth);
        $rows = [];
        $totSched = 0;
        $totWork = 0;
        $totBreak = 0;
        $totOt = 0;
        $totVac = 0.0;
        $totSick = 0.0;
        $totCorr = 0;

        foreach (TimeMonthReportService::staffOptions() as $opt) {
            $cid = (int) ($opt['id'] ?? 0);
            if ($cid < 1) {
                continue;
            }
            $label = (string) ($opt['label'] ?? ('#' . $cid));
            $personal = self::personalNumber($cid);
            try {
                $report = TimeMonthReportService::monthReport($cid, $yearMonth);
            } catch (Throwable) {
                continue;
            }
            $totals = is_array($report['totals'] ?? null) ? $report['totals'] : [];
            $sched = (int) ($totals['scheduled_minutes'] ?? 0);
            $work = (int) ($totals['worked_minutes'] ?? 0);
            $break = (int) ($totals['break_minutes'] ?? 0);
            $ot = (int) ($totals['overtime_minutes'] ?? 0);
            $vac = self::absenceDaysInMonth($cid, $yearMonth, 'vacation');
            $sick = self::absenceDaysInMonth($cid, $yearMonth, 'sick');
            $corr = self::correctionMinutesInMonth($cid, $yearMonth);

            $totSched += $sched;
            $totWork += $work;
            $totBreak += $break;
            $totOt += $ot;
            $totVac += $vac;
            $totSick += $sick;
            $totCorr += $corr;

            $rows[] = [
                'contact_id' => $cid,
                'personal_number' => $personal,
                'name' => $label,
                'soll_minutes' => $sched,
                'ist_minutes' => $work,
                'pause_minutes' => $break,
                'ueberstunden_minutes' => $ot,
                'urlaub_tage' => $vac,
                'krank_tage' => $sick,
                'korrektur_minutes' => $corr,
            ];
        }

        return [
            'year_month' => $yearMonth,
            'rows' => $rows,
            'totals' => [
                'soll_minutes' => $totSched,
                'ist_minutes' => $totWork,
                'pause_minutes' => $totBreak,
                'ueberstunden_minutes' => $totOt,
                'urlaub_tage' => round($totVac, 1),
                'krank_tage' => round($totSick, 1),
                'korrektur_minutes' => $totCorr,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $dataset
     */
    public static function toCsv(array $dataset): string
    {
        $lines = [];
        $lines[] = self::csvLine([
            'Personalnummer',
            'Name',
            'Soll_Minuten',
            'Ist_Minuten',
            'Pause_Minuten',
            'Ueberstunden_Minuten',
            'Urlaub_Tage',
            'Krank_Tage',
            'Korrektur_Minuten',
        ]);
        foreach ($dataset['rows'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $lines[] = self::csvLine([
                (string) ($row['personal_number'] ?? ''),
                (string) ($row['name'] ?? ''),
                (string) (int) ($row['soll_minutes'] ?? 0),
                (string) (int) ($row['ist_minutes'] ?? 0),
                (string) (int) ($row['pause_minutes'] ?? 0),
                (string) (int) ($row['ueberstunden_minutes'] ?? 0),
                self::numDays((float) ($row['urlaub_tage'] ?? 0)),
                self::numDays((float) ($row['krank_tage'] ?? 0)),
                (string) (int) ($row['korrektur_minutes'] ?? 0),
            ]);
        }
        $t = is_array($dataset['totals'] ?? null) ? $dataset['totals'] : [];
        $lines[] = self::csvLine([
            '',
            'SUMME',
            (string) (int) ($t['soll_minutes'] ?? 0),
            (string) (int) ($t['ist_minutes'] ?? 0),
            (string) (int) ($t['pause_minutes'] ?? 0),
            (string) (int) ($t['ueberstunden_minutes'] ?? 0),
            self::numDays((float) ($t['urlaub_tage'] ?? 0)),
            self::numDays((float) ($t['krank_tage'] ?? 0)),
            (string) (int) ($t['korrektur_minutes'] ?? 0),
        ]);
        $lines[] = self::csvLine(['#', 'Monat', (string) ($dataset['year_month'] ?? '')]);
        $lines[] = self::csvLine(['#', 'Hinweis', 'CSV-Standard Z6b — pruefbar gegen Monatsblatt; keine Netto-Lohnberechnung.']);

        return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
    }

    public static function filename(string $yearMonth): string
    {
        $yearMonth = TimeMonthReportService::normalizeYearMonth($yearMonth);
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'crm');
        if (class_exists('FirmSwitcherService')) {
            $host = FirmSwitcherService::normalizeHost($host);
        }
        $host = preg_replace('/[^a-z0-9._-]/', '', strtolower($host)) ?? 'crm';
        if ($host === '') {
            $host = 'crm';
        }

        return sprintf('lohn-zeiten-%s-%s.csv', $yearMonth, $host);
    }

    /**
     * @return array{filename: string, csv: string, row_count: int, export_id: int}
     */
    public static function exportCsv(User $user, string $yearMonth): array
    {
        if (!self::canExport($user)) {
            throw new RuntimeException('Keine Berechtigung für Lohn-Export (nur HR/Admin/full).');
        }
        $dataset = self::monthDataset($yearMonth);
        $csv = self::toCsv($dataset);
        $fname = self::filename($yearMonth);
        $rowCount = count($dataset['rows'] ?? []);
        $id = TimePayrollExportRepository::insert(
            (string) ($dataset['year_month'] ?? $yearMonth),
            'csv',
            $fname,
            $rowCount,
            (int) ($user->id ?? 0)
        );

        return [
            'filename' => $fname,
            'csv' => $csv,
            'row_count' => $rowCount,
            'export_id' => $id,
        ];
    }

    /**
     * DATEV Lohn Zeitenübergabe (Z6c) — benötigt Berater-/Mandantennummer.
     *
     * @return array{filename: string, csv: string, row_count: int, export_id: int}
     */
    public static function exportDatev(User $user, string $yearMonth): array
    {
        if (!self::canExport($user)) {
            throw new RuntimeException('Keine Berechtigung für Lohn-Export (nur HR/Admin/full).');
        }
        $dataset = self::monthDataset($yearMonth);
        $built = TimePayrollDatevExporter::build($dataset);
        $id = TimePayrollExportRepository::insert(
            (string) ($dataset['year_month'] ?? $yearMonth),
            'datev_lohn',
            $built['filename'],
            $built['count'],
            (int) ($user->id ?? 0)
        );

        return [
            'filename' => $built['filename'],
            'csv' => $built['content'],
            'row_count' => $built['count'],
            'export_id' => $id,
        ];
    }

    private static function personalNumber(int $contactId): string
    {
        $c = ContactRepository::findById($contactId);
        if ($c === null) {
            return (string) $contactId;
        }
        $raw = $c->employeeData ?? [];
        if (is_array($raw)) {
            $mapped = trim((string) ($raw['datev_personnel_number'] ?? ''));
            if ($mapped !== '') {
                return $mapped;
            }
        }
        $login = trim((string) ($c->login ?? ''));
        if ($login !== '') {
            return $login;
        }

        return (string) $contactId;
    }

    private static function absenceDaysInMonth(int $contactId, string $yearMonth, string $type): float
    {
        if (!class_exists('TimeAbsenceRepository') || !TimeAbsenceRepository::tableReady()) {
            return 0.0;
        }
        $from = $yearMonth . '-01';
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $from);
        if ($dt === false) {
            return 0.0;
        }
        $to = $dt->modify('last day of this month')->format('Y-m-d');
        $sum = 0.0;
        foreach (TimeAbsenceRepository::listOverlappingRange($from, $to, 'approved') as $row) {
            if ((int) ($row['contact_id'] ?? 0) !== $contactId) {
                continue;
            }
            if ((string) ($row['type'] ?? '') !== $type) {
                continue;
            }
            $aFrom = (string) ($row['date_from'] ?? '');
            $aTo = (string) ($row['date_to'] ?? '');
            if ($aFrom === '' || $aTo === '') {
                continue;
            }
            $iFrom = max($aFrom, $from);
            $iTo = min($aTo, $to);
            if ($iTo < $iFrom) {
                continue;
            }
            if ($aFrom >= $from && $aTo <= $to) {
                $sum += (float) ($row['days_count'] ?? 0);
            } else {
                $sum += TimeAbsenceRepository::countWorkingDays($iFrom, $iTo);
            }
        }

        return round($sum, 1);
    }

    private static function correctionMinutesInMonth(int $contactId, string $yearMonth): int
    {
        if (!TimeCorrectionRepository::tableReady()) {
            return 0;
        }
        $from = $yearMonth . '-01';
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $from);
        if ($dt === false) {
            return 0;
        }
        $to = $dt->modify('last day of this month')->format('Y-m-d');

        return TimeCorrectionRepository::sumDeltaForRange($contactId, $from, $to);
    }

    private static function numDays(float $v): string
    {
        return number_format($v, 1, ',', '');
    }

    /**
     * @param list<string> $cols
     */
    private static function csvLine(array $cols): string
    {
        $out = [];
        foreach ($cols as $c) {
            $c = str_replace('"', '""', $c);
            $out[] = '"' . $c . '"';
        }

        return implode(';', $out);
    }
}
