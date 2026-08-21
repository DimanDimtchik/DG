<?php
declare(strict_types=1);

/** Umsatzsteuer-Voranmeldung (UStVA) — Kennziffern aus Belegen und §13b-Snapshots. */
final class UstvaReportService
{
    /**
     * @return array<string, string>
     */
    public static function kennzifferLabels(): array
    {
        return [
            '81' => 'Steuerpflichtige Umsätze 19 %',
            '86' => 'Steuerpflichtige Umsätze 7 %',
            '83' => 'Steuer auf Umsätze 19 %',
            '35' => 'Steuer auf Umsätze 7 %',
            '62' => 'Abziehbare Vorsteuer 19 %',
            '63' => 'Abziehbare Vorsteuer 7 %',
            '66' => 'Abziehbare Vorsteuer (Summe)',
            '37' => 'Verbleibende Umsatzsteuer-Vorauszahlung',
            '46' => '§13b Leistungen EU (Bemessungsgrundlage)',
            '47' => '§13b Steuer EU',
            '48' => '§13b Bauleistungen 19 %',
            '49' => '§13b Bauleistungen 7 %',
            '67' => '§13b abziehbare Vorsteuer',
            '84' => '§13b Leistungen Drittland (Bemessungsgrundlage)',
            '85' => '§13b Steuer Drittland',
        ];
    }

    /**
     * @return array{
     *   year: int,
     *   month: int|null,
     *   period_label: string,
     *   positions: list<array{kz: string, label: string, net: float, tax: float, amount: float}>,
     *   payable: float
     * }
     */
    public static function report(int $year, ?int $month = null): array
    {
        if (!Database::isConfigured()) {
            return self::emptyReport($year, $month);
        }
        MigrationRunner::runPending();

        /** @var array<string, array{net: float, tax: float}> $agg */
        $agg = [];

        $where = 'v.is_draft = 0 AND YEAR(v.voucher_date) = :y';
        $params = ['y' => $year];
        if ($month !== null && $month >= 1 && $month <= 12) {
            $where .= ' AND MONTH(v.voucher_date) = :m';
            $params['m'] = $month;
        }

        $sql = "SELECT v.id, v.voucher_type, v.net_amount, v.tax_amount, v.tax_rate,
                       v.reverse_charge_type, v.ustva_snapshot,
                       vl.net_amount AS line_net, vl.tax_amount AS line_tax, vl.tax_rate AS line_rate
                FROM dg_vouchers v
                LEFT JOIN dg_voucher_lines vl ON vl.voucher_id = v.id
                WHERE {$where}
                ORDER BY v.id ASC";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        $processedRc = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            $voucherId = (int) ($row['id'] ?? 0);
            $type = VoucherRepository::normalizeVoucherType((string) ($row['voucher_type'] ?? 'expense'));
            $rcType = trim((string) ($row['reverse_charge_type'] ?? ''));

            if ($rcType !== '' && !isset($processedRc[$voucherId])) {
                $processedRc[$voucherId] = true;
                self::mergeReverseCharge($agg, (string) ($row['ustva_snapshot'] ?? ''));
                continue;
            }
            if ($rcType !== '') {
                continue;
            }

            $net = round((float) ($row['line_net'] ?? 0), 2);
            $tax = round((float) ($row['line_tax'] ?? 0), 2);
            $rate = (int) ($row['line_rate'] ?? 0);
            if ($net <= 0 && $tax <= 0) {
                $net = round((float) ($row['net_amount'] ?? 0), 2);
                $tax = round((float) ($row['tax_amount'] ?? 0), 2);
                $rate = (int) ($row['tax_rate'] ?? 19);
            }
            if ($net <= 0 && $tax <= 0) {
                continue;
            }

            $sign = self::directionSign($type);
            $net *= $sign;
            $tax *= $sign;

            if (LedgerAccounts::isIncomeDirection($type)) {
                if ($rate === 7) {
                    self::addAgg($agg, '86', $net, 0.0);
                    self::addAgg($agg, '35', 0.0, $tax);
                } else {
                    self::addAgg($agg, '81', $net, 0.0);
                    self::addAgg($agg, '83', 0.0, $tax);
                }
            } else {
                if ($rate === 7) {
                    self::addAgg($agg, '63', 0.0, $tax);
                } else {
                    self::addAgg($agg, '62', 0.0, $tax);
                }
            }
        }

        $inputTotal = round(
            ($agg['62']['tax'] ?? 0.0) + ($agg['63']['tax'] ?? 0.0) + ($agg['67']['tax'] ?? 0.0),
            2
        );
        if ($inputTotal > 0) {
            $agg['66'] = ['net' => 0.0, 'tax' => $inputTotal];
        }

        $outputTotal = round(
            ($agg['83']['tax'] ?? 0.0)
            + ($agg['35']['tax'] ?? 0.0)
            + ($agg['47']['tax'] ?? 0.0)
            + ($agg['48']['tax'] ?? 0.0)
            + ($agg['49']['tax'] ?? 0.0)
            + ($agg['85']['tax'] ?? 0.0),
            2
        );
        $payable = round($outputTotal - $inputTotal, 2);
        if ($payable != 0.0) {
            $agg['37'] = ['net' => 0.0, 'tax' => $payable];
        }

        $labels = self::kennzifferLabels();
        $positions = [];
        foreach ($agg as $kz => $values) {
            $net = round((float) ($values['net'] ?? 0), 2);
            $tax = round((float) ($values['tax'] ?? 0), 2);
            if ($net == 0.0 && $tax == 0.0) {
                continue;
            }
            $amount = $tax != 0.0 ? $tax : $net;
            $positions[] = [
                'kz' => $kz,
                'label' => $labels[$kz] ?? 'Kennziffer ' . $kz,
                'net' => $net,
                'tax' => $tax,
                'amount' => round($amount, 2),
            ];
        }
        usort($positions, static fn (array $a, array $b): int => strnatcmp($a['kz'], $b['kz']));

        return [
            'year' => $year,
            'month' => $month,
            'period_label' => self::periodLabel($year, $month),
            'positions' => $positions,
            'payable' => $payable,
        ];
    }

    /**
     * @param array<string, array{net: float, tax: float}> $agg
     */
    private static function mergeReverseCharge(array &$agg, string $snapshotJson): void
    {
        if ($snapshotJson === '') {
            return;
        }
        try {
            $decoded = json_decode($snapshotJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return;
        }
        if (!is_array($decoded)) {
            return;
        }
        foreach ($decoded as $pos) {
            if (!is_array($pos)) {
                continue;
            }
            $kz = trim((string) ($pos['kz'] ?? ''));
            if ($kz === '') {
                continue;
            }
            self::addAgg(
                $agg,
                $kz,
                round((float) ($pos['net'] ?? 0), 2),
                round((float) ($pos['tax'] ?? 0), 2)
            );
        }
    }

    /**
     * @param array<string, array{net: float, tax: float}> $agg
     */
    private static function addAgg(array &$agg, string $kz, float $net, float $tax): void
    {
        if ($kz === '' || ($net == 0.0 && $tax == 0.0)) {
            return;
        }
        if (!isset($agg[$kz])) {
            $agg[$kz] = ['net' => 0.0, 'tax' => 0.0];
        }
        $agg[$kz]['net'] = round($agg[$kz]['net'] + $net, 2);
        $agg[$kz]['tax'] = round($agg[$kz]['tax'] + $tax, 2);
    }

    private static function directionSign(string $voucherType): float
    {
        return in_array(
            VoucherRepository::normalizeVoucherType($voucherType),
            ['income_reduction', 'expense_reduction', 'credit'],
            true
        ) ? -1.0 : 1.0;
    }

    private static function periodLabel(int $year, ?int $month): string
    {
        if ($month === null || $month < 1 || $month > 12) {
            return 'Jahr ' . $year;
        }
        $months = [
            1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
            5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
        ];

        return ($months[$month] ?? '') . ' ' . $year;
    }

    /**
     * @return array{year: int, month: int|null, period_label: string, positions: list<array<string, mixed>>, payable: float}
     */
    private static function emptyReport(int $year, ?int $month): array
    {
        return [
            'year' => $year,
            'month' => $month,
            'period_label' => self::periodLabel($year, $month),
            'positions' => [],
            'payable' => 0.0,
        ];
    }
}
