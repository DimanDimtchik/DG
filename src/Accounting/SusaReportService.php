<?php
declare(strict_types=1);

/** Summen- und Saldenliste (SuSa). */
final class SusaReportService
{
    /**
     * @return array{
     *   period_label: string,
     *   accounts: list<array<string, mixed>>,
     *   totals: array{opening: float, debit: float, credit: float, balance: float}
     * }
     */
    public static function report(AccountingPeriodFilter $period): array
    {
        $overview = LedgerRepository::accountOverview($period->year, [
            'date_from' => $period->dateFrom,
            'date_to' => $period->dateTo,
            'show_empty' => false,
        ]);

        return [
            'period_label' => $period->label,
            'accounts' => $overview['accounts'],
            'totals' => $overview['totals'],
        ];
    }
}
