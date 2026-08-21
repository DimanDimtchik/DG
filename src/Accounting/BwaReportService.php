<?php
declare(strict_types=1);

/** Betriebswirtschaftliche Auswertung (BWA) — vereinfachte GuV-Struktur. */
final class BwaReportService
{
    /**
     * @return array{
     *   period_label: string,
     *   lines: list<array{key: string, label: string, amount: float, level: int}>,
     *   totals: array{revenue: float, costs: float, result: float}
     * }
     */
    public static function report(AccountingPeriodFilter $period): array
    {
        $overview = LedgerRepository::accountOverview($period->year, [
            'date_from' => $period->dateFrom,
            'date_to' => $period->dateTo,
            'show_empty' => false,
        ]);

        $revenue = 0.0;
        $material = 0.0;
        $personnel = 0.0;
        $other = 0.0;

        foreach ($overview['accounts'] as $row) {
            $section = (string) ($row['section'] ?? '');
            $acc = (string) ($row['account_number'] ?? '');
            $balance = round((float) ($row['balance'] ?? 0), 2);
            if ($balance == 0.0) {
                continue;
            }
            if ($section === 'ertrag') {
                $revenue = round($revenue + (-$balance), 2);
                continue;
            }
            if ($section !== 'aufwand') {
                continue;
            }
            $amount = $balance;
            if (self::isPersonnelAccount($acc)) {
                $personnel = round($personnel + $amount, 2);
            } elseif (self::isMaterialAccount($acc)) {
                $material = round($material + $amount, 2);
            } else {
                $other = round($other + $amount, 2);
            }
        }

        $costs = round($material + $personnel + $other, 2);
        $result = round($revenue - $costs, 2);

        $lines = [
            ['key' => 'revenue', 'label' => 'Umsatzerlöse', 'amount' => $revenue, 'level' => 0],
            ['key' => 'material', 'label' => 'Materialaufwand / Wareneinsatz', 'amount' => $material, 'level' => 1],
            ['key' => 'personnel', 'label' => 'Personalkosten', 'amount' => $personnel, 'level' => 1],
            ['key' => 'other', 'label' => 'Sonstige betriebliche Aufwendungen', 'amount' => $other, 'level' => 1],
            ['key' => 'result', 'label' => 'Betriebsergebnis', 'amount' => $result, 'level' => 0],
        ];

        return [
            'period_label' => $period->label,
            'lines' => $lines,
            'totals' => [
                'revenue' => $revenue,
                'costs' => $costs,
                'result' => $result,
            ],
        ];
    }

    private static function isPersonnelAccount(string $accountNumber): bool
    {
        return str_starts_with($accountNumber, '41') || str_starts_with($accountNumber, '60');
    }

    private static function isMaterialAccount(string $accountNumber): bool
    {
        return str_starts_with($accountNumber, '3') || str_starts_with($accountNumber, '5');
    }
}
