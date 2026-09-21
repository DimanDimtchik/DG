<?php
declare(strict_types=1);

/**
 * Zeiterfassung Z5b: Urlaubs-/Überstunden-Rückstellung Preview + CSV.
 * Keine Ledger-Buchung (Z5c).
 */
final class TimeProvisionService
{
    public static function canPreview(User $user): bool
    {
        return MenuRegistry::canAccessBuchhaltung($user);
    }

    /**
     * @return array{
     *   year: int,
     *   stichtag: string,
     *   config: array<string, mixed>,
     *   rows: list<array<string, mixed>>,
     *   totals: array{vacation: float, overtime: float, total: float},
     *   warnings: list<string>
     * }
     */
    public static function preview(int $year): array
    {
        $year = max(2000, min(2100, $year));
        $cfg = TimeTrackingSettings::config();
        $stichtag = sprintf('%04d-12-31', $year);
        $warnings = [];
        $defaultDaily = (float) ($cfg['provision_daily_cost'] ?? 0);
        if ($defaultDaily <= 0) {
            $warnings[] = 'Firmen-Tageskostensatz ist 0 — Beträge sind 0 bis Settings gesetzt sind.';
        }
        $social = (float) ($cfg['provision_social_factor'] ?? 1);
        $otEnabled = !empty($cfg['provision_ot_enabled']);
        $method = (string) ($cfg['provision_cost_method'] ?? 'workdays_260');
        $divisor = $method === 'calendar_365' ? 365.0 : 260.0;

        $rows = [];
        $sumVac = 0.0;
        $sumOt = 0.0;

        foreach (TimeMonthReportService::staffOptions() as $opt) {
            $cid = (int) ($opt['id'] ?? 0);
            if ($cid < 1) {
                continue;
            }
            $label = (string) ($opt['label'] ?? ('#' . $cid));
            $balance = TimeVacationEntitlementRepository::balance($cid, $year);
            $restDays = max(0.0, (float) ($balance['days_rest'] ?? 0));
            $dailyCost = self::dailyCostForContact($cid, $defaultDaily, $divisor);
            $vacAmount = round($restDays * $dailyCost * $social, 2);

            $otMinutes = 0;
            $otHours = 0.0;
            $otAmount = 0.0;
            if ($otEnabled && class_exists('OvertimeLotRepository')) {
                OvertimeLotRepository::syncAccrualsFromWorkDays($cid);
                $otMinutes = max(0, OvertimeLotRepository::sumRemainingMinutes($cid));
                $otHours = round($otMinutes / 60, 2);
                $hourly = $dailyCost > 0 ? round($dailyCost / 8, 4) : 0.0;
                $otAmount = round($otHours * $hourly * $social, 2);
            }

            $sumVac += $vacAmount;
            $sumOt += $otAmount;

            $rows[] = [
                'contact_id' => $cid,
                'label' => $label,
                'rest_days' => round($restDays, 1),
                'days_entitled' => (float) ($balance['days_entitled'] ?? 0),
                'days_carried' => (float) ($balance['days_carried'] ?? 0),
                'days_used' => (float) ($balance['days_used'] ?? 0),
                'daily_cost' => $dailyCost,
                'social_factor' => $social,
                'vacation_amount' => $vacAmount,
                'ot_minutes' => $otMinutes,
                'ot_hours' => $otHours,
                'ot_amount' => $otAmount,
                'row_total' => round($vacAmount + $otAmount, 2),
            ];
        }

        return [
            'year' => $year,
            'stichtag' => $stichtag,
            'config' => [
                'cost_method' => $method,
                'divisor' => $divisor,
                'default_daily_cost' => $defaultDaily,
                'social_factor' => $social,
                'ot_enabled' => $otEnabled,
                'account_vacation_expense' => (string) ($cfg['provision_account_vacation_expense'] ?? ''),
                'account_vacation_liability' => (string) ($cfg['provision_account_vacation_liability'] ?? ''),
                'account_ot_expense' => (string) ($cfg['provision_account_ot_expense'] ?? ''),
                'account_ot_liability' => (string) ($cfg['provision_account_ot_liability'] ?? ''),
                'booking_text' => sprintf(
                    'Urlaubsrückstellung %d / Stichtag %s / Berechnung CRM',
                    $year,
                    $stichtag
                ),
            ],
            'rows' => $rows,
            'totals' => [
                'vacation' => round($sumVac, 2),
                'overtime' => round($sumOt, 2),
                'total' => round($sumVac + $sumOt, 2),
            ],
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<string, mixed> $preview
     */
    public static function toCsv(array $preview): string
    {
        $lines = [];
        $otOn = !empty($preview['config']['ot_enabled']);
        $header = [
            'Kontakt_ID',
            'Name',
            'Rest_Urlaub_Tage',
            'Tageskostensatz',
            'Sozialfaktor',
            'Betrag_Urlaub',
        ];
        if ($otOn) {
            $header[] = 'Ueberstunden_Stunden';
            $header[] = 'Betrag_Ueberstunden';
        }
        $header[] = 'Summe';
        $lines[] = self::csvLine($header);

        foreach ($preview['rows'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $cols = [
                (string) (int) ($row['contact_id'] ?? 0),
                (string) ($row['label'] ?? ''),
                self::num((float) ($row['rest_days'] ?? 0)),
                self::num((float) ($row['daily_cost'] ?? 0)),
                self::num((float) ($row['social_factor'] ?? 0)),
                self::num((float) ($row['vacation_amount'] ?? 0)),
            ];
            if ($otOn) {
                $cols[] = self::num((float) ($row['ot_hours'] ?? 0));
                $cols[] = self::num((float) ($row['ot_amount'] ?? 0));
            }
            $cols[] = self::num((float) ($row['row_total'] ?? 0));
            $lines[] = self::csvLine($cols);
        }

        $totals = is_array($preview['totals'] ?? null) ? $preview['totals'] : [];
        $sumCols = ['', 'SUMME', '', '', '', self::num((float) ($totals['vacation'] ?? 0))];
        if ($otOn) {
            $sumCols[] = '';
            $sumCols[] = self::num((float) ($totals['overtime'] ?? 0));
        }
        $sumCols[] = self::num((float) ($totals['total'] ?? 0));
        $lines[] = self::csvLine($sumCols);

        $cfg = is_array($preview['config'] ?? null) ? $preview['config'] : [];
        $lines[] = self::csvLine(['#', 'Jahr', (string) (int) ($preview['year'] ?? 0)]);
        $lines[] = self::csvLine(['#', 'Stichtag', (string) ($preview['stichtag'] ?? '')]);
        $lines[] = self::csvLine(['#', 'Methode', (string) ($cfg['cost_method'] ?? '')]);
        $lines[] = self::csvLine([
            '#',
            'Konten_Urlaub',
            (string) ($cfg['account_vacation_expense'] ?? '') . ' / ' . (string) ($cfg['account_vacation_liability'] ?? ''),
        ]);
        $lines[] = self::csvLine(['#', 'Hinweis', 'Vorschlag — mit Steuerberater prüfen. Keine Buchung in Z5b.']);

        return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Tageskostensatz: optional employee_data.provision_daily_cost, sonst Firmen-Default.
     * Methode workdays_260 / calendar_365 betrifft nur Doku/Divisor-Anzeige bei Jahresgehalt-Ableitung.
     */
    private static function dailyCostForContact(int $contactId, float $defaultDaily, float $divisor): float
    {
        $contact = ContactRepository::findById($contactId);
        if ($contact !== null) {
            $raw = $contact->employeeData ?? [];
            if (is_array($raw)) {
                $override = $raw['provision_daily_cost'] ?? '';
                if (is_string($override)) {
                    $override = str_replace(',', '.', trim($override));
                }
                if ($override !== '' && $override !== null && (float) $override > 0) {
                    return round((float) $override, 2);
                }
                // Optionale Ableitung aus Jahresgehalt (Zahl in salary)
                $salary = trim((string) ($raw['salary'] ?? ''));
                if ($salary !== '' && preg_match('/(\d+[.,]?\d*)/', $salary, $m)) {
                    $annual = (float) str_replace(',', '.', $m[1]);
                    // Heuristik: Werte > 500 als Jahresbrutto, sonst als Monatsbrutto×12
                    if ($annual > 0 && $annual < 500) {
                        $annual *= 12;
                    }
                    if ($annual > 0 && $divisor > 0) {
                        return round($annual / $divisor, 2);
                    }
                }
            }
        }

        return round($defaultDaily, 2);
    }

    private static function num(float $v): string
    {
        return number_format($v, 2, ',', '');
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
