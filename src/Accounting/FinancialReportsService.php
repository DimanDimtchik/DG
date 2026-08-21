<?php
declare(strict_types=1);

/** Bilanz und GuV (Kontenebene) aus dem Buchungsjournal. */
final class FinancialReportsService
{
    /**
     * @return array{aktiva: list<array<string, mixed>>, passiva: list<array<string, mixed>>, totals: array<string, float>, result: float}
     */
    public static function balanceSheet(int $year): array
    {
        $overview = LedgerRepository::accountOverview($year, ['show_empty' => false]);
        $aktiva = [];
        $passiva = [];
        $totals = ['aktiva' => 0.0, 'passiva' => 0.0];

        foreach ($overview['accounts'] as $row) {
            $section = (string) ($row['section'] ?? '');
            if ($section === 'aktiva') {
                $aktiva[] = $row;
                $totals['aktiva'] = round($totals['aktiva'] + (float) ($row['balance'] ?? 0), 2);
            } elseif ($section === 'passiva') {
                $passiva[] = $row;
                $totals['passiva'] = round($totals['passiva'] - (float) ($row['balance'] ?? 0), 2);
            }
        }

        usort($aktiva, static fn (array $a, array $b): int => strnatcmp((string) $a['account_number'], (string) $b['account_number']));
        usort($passiva, static fn (array $a, array $b): int => strnatcmp((string) $a['account_number'], (string) $b['account_number']));

        $pl = FiscalYearService::profitLossPreview($year);

        return [
            'aktiva' => $aktiva,
            'passiva' => $passiva,
            'totals' => $totals,
            'result' => (float) ($pl['result'] ?? 0.0),
        ];
    }

    /**
     * @return array{income: list<array<string, mixed>>, expense: list<array<string, mixed>>, totals: array{income: float, expense: float, result: float}}
     */
    public static function profitLoss(int $year): array
    {
        $overview = LedgerRepository::accountOverview($year, ['show_empty' => false]);
        $income = [];
        $expense = [];
        $totals = ['income' => 0.0, 'expense' => 0.0, 'result' => 0.0];

        foreach ($overview['accounts'] as $row) {
            $section = (string) ($row['section'] ?? '');
            $balance = round((float) ($row['balance'] ?? 0), 2);
            if ($balance == 0.0) {
                continue;
            }
            if ($section === 'ertrag') {
                $amount = round(-$balance, 2);
                if ($amount != 0.0) {
                    $row['pl_amount'] = $amount;
                    $income[] = $row;
                    $totals['income'] = round($totals['income'] + $amount, 2);
                }
            } elseif ($section === 'aufwand') {
                $amount = $balance;
                if ($amount != 0.0) {
                    $row['pl_amount'] = $amount;
                    $expense[] = $row;
                    $totals['expense'] = round($totals['expense'] + $amount, 2);
                }
            }
        }

        usort($income, static fn (array $a, array $b): int => strnatcmp((string) $a['account_number'], (string) $b['account_number']));
        usort($expense, static fn (array $a, array $b): int => strnatcmp((string) $a['account_number'], (string) $b['account_number']));
        $totals['result'] = round($totals['income'] - $totals['expense'], 2);

        return [
            'income' => $income,
            'expense' => $expense,
            'totals' => $totals,
        ];
    }
}
