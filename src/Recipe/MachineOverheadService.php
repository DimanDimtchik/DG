<?php
declare(strict_types=1);

/**
 * Gemeinkosten €/Maschinenstunde — lesend aus FiBu + Belegung (E1a/E1c).
 * Keine Buchungen.
 */
final class MachineOverheadService
{
    /**
     * @param array<string, mixed>|null $cfg MachineOverheadSettings::get()-Shape
     * @return array{
     *   enabled: bool,
     *   total_overhead_cost: float,
     *   machine_hours: float,
     *   machine_hours_source: string,
     *   overhead_per_machine_hour: ?float,
     *   warnings: list<string>,
     *   period: array{mode: string, fiscal_year: int, date_from: string, date_to: string},
     *   account_numbers: list<string>,
     *   label: string,
     *   computed_at: string
     * }
     */
    public static function compute(?array $cfg = null): array
    {
        $cfg = MachineOverheadSettings::normalize($cfg ?? MachineOverheadSettings::get());
        $warnings = [];
        $computedAt = date('c');
        $period = self::resolvePeriod($cfg, $warnings);

        $empty = [
            'enabled' => !empty($cfg['enabled']),
            'total_overhead_cost' => 0.0,
            'machine_hours' => 0.0,
            'machine_hours_source' => 'none',
            'overhead_per_machine_hour' => null,
            'warnings' => $warnings,
            'period' => $period,
            'account_numbers' => $cfg['account_numbers'],
            'label' => (string) ($cfg['label'] ?? ''),
            'computed_at' => $computedAt,
        ];

        if (!Database::isConfigured()) {
            $warnings[] = 'Datenbank nicht konfiguriert.';
            $empty['warnings'] = $warnings;

            return $empty;
        }

        $accounts = $cfg['account_numbers'];
        if ($accounts === []) {
            $warnings[] = 'Keine Gemeinkosten-Konten hinterlegt.';
        }

        $totalCost = self::sumLedgerCost($accounts, $period, $warnings);
        [$hours, $hoursSource] = self::resolveMachineHours($cfg, $period, $warnings);

        $rate = null;
        if ($hours >= 0.0005) {
            $rate = round($totalCost / $hours, 4);
        } else {
            $warnings[] = 'Keine Maschinenstunden — Aufschlag nicht berechenbar.';
        }

        return [
            'enabled' => !empty($cfg['enabled']),
            'total_overhead_cost' => round($totalCost, 2),
            'machine_hours' => round($hours, 4),
            'machine_hours_source' => $hoursSource,
            'overhead_per_machine_hour' => $rate,
            'warnings' => $warnings,
            'period' => $period,
            'account_numbers' => $accounts,
            'label' => (string) ($cfg['label'] ?? ''),
            'computed_at' => $computedAt,
        ];
    }

    /**
     * @param array<string, mixed> $cfg
     * @param list<string> $warnings
     * @return array{mode: string, fiscal_year: int, date_from: string, date_to: string}
     */
    private static function resolvePeriod(array $cfg, array &$warnings): array
    {
        $mode = (string) ($cfg['period_mode'] ?? MachineOverheadSettings::PERIOD_FISCAL_YEAR);
        $year = (int) ($cfg['fiscal_year'] ?? date('Y'));
        if ($mode === MachineOverheadSettings::PERIOD_DATE_RANGE) {
            $from = (string) ($cfg['date_from'] ?? '');
            $to = (string) ($cfg['date_to'] ?? '');
            if ($from === '' || $to === '') {
                $warnings[] = 'Zeitraum unvollständig — Fallback Geschäftsjahr.';
                $mode = MachineOverheadSettings::PERIOD_FISCAL_YEAR;
            } else {
                return [
                    'mode' => MachineOverheadSettings::PERIOD_DATE_RANGE,
                    'fiscal_year' => (int) substr($from, 0, 4),
                    'date_from' => $from,
                    'date_to' => $to,
                ];
            }
        }

        return [
            'mode' => MachineOverheadSettings::PERIOD_FISCAL_YEAR,
            'fiscal_year' => $year,
            'date_from' => sprintf('%04d-01-01', $year),
            'date_to' => sprintf('%04d-12-31', $year),
        ];
    }

    /**
     * @param list<string> $accounts
     * @param array{mode: string, fiscal_year: int, date_from: string, date_to: string} $period
     * @param list<string> $warnings
     */
    private static function sumLedgerCost(array $accounts, array $period, array &$warnings): float
    {
        if ($accounts === [] || !self::tableExists('dg_ledger_postings')) {
            if ($accounts !== [] && !self::tableExists('dg_ledger_postings')) {
                $warnings[] = 'Hauptbuch-Tabelle fehlt — Kostensumme 0.';
            }

            return 0.0;
        }

        $skrType = ChartOfAccountsSettings::activeSkrType();
        $sum = 0.0;
        $pdo = Database::pdo();
        $year = (int) $period['fiscal_year'];
        $from = (string) $period['date_from'];
        $to = (string) $period['date_to'];
        $useRange = $period['mode'] === MachineOverheadSettings::PERIOD_DATE_RANGE
            || $from !== sprintf('%04d-01-01', $year)
            || $to !== sprintf('%04d-12-31', $year);

        foreach ($accounts as $acc) {
            if (ChartAccountRepository::findByNumber($acc, $skrType) === null) {
                $warnings[] = 'Unbekanntes Konto: ' . $acc;
            }
            if ($useRange) {
                $stmt = $pdo->prepare(
                    "SELECT side, SUM(amount) AS total
                     FROM dg_ledger_postings
                     WHERE fiscal_year = :y
                       AND account_number = :acc
                       AND source <> 'opening_balance'
                       AND posting_date BETWEEN :from AND :to
                     GROUP BY side"
                );
                $stmt->execute(['y' => $year, 'acc' => $acc, 'from' => $from, 'to' => $to]);
            } else {
                $stmt = $pdo->prepare(
                    "SELECT side, SUM(amount) AS total
                     FROM dg_ledger_postings
                     WHERE fiscal_year = :y
                       AND account_number = :acc
                       AND source <> 'opening_balance'
                     GROUP BY side"
                );
                $stmt->execute(['y' => $year, 'acc' => $acc]);
            }
            $debit = 0.0;
            $credit = 0.0;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $side = (string) ($row['side'] ?? '');
                $amt = round((float) ($row['total'] ?? 0), 2);
                if ($side === 'debit') {
                    $debit = $amt;
                } elseif ($side === 'credit') {
                    $credit = $amt;
                }
            }
            $sum += ($debit - $credit);
        }

        return round($sum, 2);
    }

    /**
     * @param array<string, mixed> $cfg
     * @param array{mode: string, fiscal_year: int, date_from: string, date_to: string} $period
     * @param list<string> $warnings
     * @return array{0: float, 1: string}
     */
    private static function resolveMachineHours(array $cfg, array $period, array &$warnings): array
    {
        $fromBooking = self::sumBookingHours($period);
        if ($fromBooking >= 0.0005) {
            return [$fromBooking, 'booking'];
        }
        $manual = $cfg['manual_machine_hours'] ?? null;
        if (is_float($manual) || is_int($manual)) {
            $m = (float) $manual;
            if ($m >= 0.0005) {
                if ($fromBooking < 0.0005) {
                    $warnings[] = 'Keine Belegungsstunden — Manualwert für Maschinenstunden genutzt.';
                }

                return [$m, 'manual'];
            }
        }

        return [0.0, 'none'];
    }

    /**
     * Produktions-Belegungen aktiver Maschinen im Zeitraum (Überlappung), Status ≠ cancelled.
     *
     * @param array{mode: string, fiscal_year: int, date_from: string, date_to: string} $period
     */
    private static function sumBookingHours(array $period): float
    {
        if (!self::tableExists('dg_machine_booking_windows') || !self::tableExists('dg_work_centers')) {
            return 0.0;
        }
        $fromDt = $period['date_from'] . ' 00:00:00';
        $toDt = $period['date_to'] . ' 23:59:59';
        $stmt = Database::pdo()->prepare(
            "SELECT COALESCE(SUM(
                GREATEST(
                    0,
                    TIMESTAMPDIFF(
                        SECOND,
                        GREATEST(b.starts_at, :from_dt),
                        LEAST(b.ends_at, :to_dt)
                    )
                )
             ), 0) / 3600.0 AS hours
             FROM dg_machine_booking_windows b
             INNER JOIN dg_work_centers w ON w.id = b.work_center_id AND w.is_active = 1
             WHERE b.booking_status <> 'cancelled'
               AND b.booking_type = 'production'
               AND b.starts_at < :to_dt2
               AND b.ends_at > :from_dt2"
        );
        $stmt->execute([
            'from_dt' => $fromDt,
            'to_dt' => $toDt,
            'from_dt2' => $fromDt,
            'to_dt2' => $toDt,
        ]);
        $hours = (float) ($stmt->fetchColumn() ?: 0);

        return max(0.0, round($hours, 4));
    }

    private static function tableExists(string $table): bool
    {
        try {
            $stmt = Database::pdo()->query("SHOW TABLES LIKE " . Database::pdo()->quote($table));

            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }
}
