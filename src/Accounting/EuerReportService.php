<?php
declare(strict_types=1);

/** Einnahmen-Überschuss-Rechnung (EÜR) aus Erfolgskonten des Buchungsjournals. */
final class EuerReportService
{
    /**
     * @return array{
     *   year: int,
     *   income: list<array{account_number: string, label: string, amount: float}>,
     *   expense: list<array{account_number: string, label: string, amount: float}>,
     *   totals: array{income: float, expense: float, result: float}
     * }
     */
    public static function report(int $year): array
    {
        $pl = FinancialReportsService::profitLoss($year);
        $income = [];
        foreach ($pl['income'] as $row) {
            $amount = round((float) ($row['pl_amount'] ?? 0), 2);
            if ($amount == 0.0) {
                continue;
            }
            $income[] = [
                'account_number' => (string) ($row['account_number'] ?? ''),
                'label' => (string) ($row['label'] ?? ''),
                'amount' => $amount,
            ];
        }
        $expense = [];
        foreach ($pl['expense'] as $row) {
            $amount = round((float) ($row['pl_amount'] ?? 0), 2);
            if ($amount == 0.0) {
                continue;
            }
            $expense[] = [
                'account_number' => (string) ($row['account_number'] ?? ''),
                'label' => (string) ($row['label'] ?? ''),
                'amount' => $amount,
            ];
        }

        return [
            'year' => $year,
            'income' => $income,
            'expense' => $expense,
            'totals' => $pl['totals'],
        ];
    }
}
