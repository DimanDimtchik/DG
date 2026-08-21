<?php
declare(strict_types=1);

/**
 * Pre-Close-Checkliste vor dem Jahresabschluss — auch für Kunden ohne Steuerberater (DIY).
 */
final class FiscalCloseService
{
    /**
     * @return list<array{id: string, label: string, status: string, detail: string, href: string}>
     */
    public static function checklist(int $year): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();

        $items = [];
        $items[] = self::itemDrafts($year);
        $items[] = self::itemOpenItems($year);
        $items[] = self::itemJournalBalance($year);
        $items[] = self::itemBalanceSheet($year);
        $items[] = self::itemUnbalancedVouchers($year);
        $items[] = self::itemBankOpen($year);

        return $items;
    }

    public static function canClose(int $year): bool
    {
        foreach (self::checklist($year) as $item) {
            if (($item['status'] ?? '') === 'error') {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws RuntimeException
     */
    public static function assertCanClose(int $year): void
    {
        $blocking = [];
        foreach (self::checklist($year) as $item) {
            if (($item['status'] ?? '') === 'error') {
                $blocking[] = (string) ($item['label'] ?? '');
            }
        }
        if ($blocking !== []) {
            throw new RuntimeException(
                'Jahresabschluss blockiert: ' . implode('; ', $blocking)
            );
        }
    }

    /**
     * @return array{done: int, total: int, errors: int, warnings: int}
     */
    public static function summary(int $year): array
    {
        $done = 0;
        $errors = 0;
        $warnings = 0;
        foreach (self::checklist($year) as $item) {
            $status = (string) ($item['status'] ?? '');
            if ($status === 'ok') {
                $done++;
            } elseif ($status === 'error') {
                $errors++;
            } elseif ($status === 'warn') {
                $warnings++;
            }
        }

        return [
            'done' => $done,
            'total' => count(self::checklist($year)),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{id: string, label: string, status: string, detail: string, href: string}
     */
    private static function itemDrafts(int $year): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM dg_vouchers WHERE is_draft = 1 AND YEAR(voucher_date) = :y'
        );
        $stmt->execute(['y' => $year]);
        $count = (int) $stmt->fetchColumn();

        return [
            'id' => 'drafts',
            'label' => 'Keine Beleg-Entwürfe',
            'status' => $count === 0 ? 'ok' : 'error',
            'detail' => $count === 0
                ? 'Alle Belege sind final gebucht.'
                : sprintf('%d Entwurf/Entwürfe im Jahr %d — bitte veröffentlichen oder löschen.', $count, $year),
            'href' => '/app?page=buchhaltung-belege&year=' . $year . '&draft=1',
        ];
    }

    /**
     * @return array{id: string, label: string, status: string, detail: string, href: string}
     */
    private static function itemOpenItems(int $year): array
    {
        $opos = OpenItemsRepository::list();
        $yearItems = array_filter(
            $opos['items'],
            static fn (array $row): bool => (int) substr((string) ($row['voucher_date'] ?? ''), 0, 4) === $year
        );
        $count = count($yearItems);
        $total = round(array_sum(array_map(
            static fn (array $row): float => (float) ($row['open_amount'] ?? 0),
            $yearItems
        )), 2);

        return [
            'id' => 'opos',
            'label' => 'Offene Posten (OPOS)',
            'status' => $count === 0 ? 'ok' : 'warn',
            'detail' => $count === 0
                ? 'Keine offenen Forderungen/Verbindlichkeiten aus ' . $year . '.'
                : sprintf(
                    '%d offene Posten (%.2f €) aus %d — vor Abschluss prüfen oder als bewusst offen dokumentieren.',
                    $count,
                    $total,
                    $year
                ),
            'href' => '/app?page=buchhaltung-opos',
        ];
    }

    /**
     * @return array{id: string, label: string, status: string, detail: string, href: string}
     */
    private static function itemJournalBalance(int $year): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT ROUND(SUM(CASE WHEN side='debit' THEN amount ELSE 0 END), 2) AS d,
                    ROUND(SUM(CASE WHEN side='credit' THEN amount ELSE 0 END), 2) AS c
             FROM dg_ledger_postings
             WHERE fiscal_year = :y"
        );
        $stmt->execute(['y' => $year]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['d' => 0, 'c' => 0];
        $diff = round(abs((float) $row['d'] - (float) $row['c']), 2);

        return [
            'id' => 'journal',
            'label' => 'Buchungsjournal ausgeglichen',
            'status' => $diff <= 0.01 ? 'ok' : 'error',
            'detail' => $diff <= 0.01
                ? sprintf('Soll = Haben (%.2f €).', (float) $row['d'])
                : sprintf('Differenz Soll/Haben: %.2f € — Journal prüfen.', $diff),
            'href' => '/app?page=buchhaltung-kontenuebersicht&year=' . $year,
        ];
    }

    /**
     * @return array{id: string, label: string, status: string, detail: string, href: string}
     */
    private static function itemBalanceSheet(int $year): array
    {
        $bs = FinancialReportsService::balanceSheet($year);
        $aktiva = round((float) ($bs['totals']['aktiva'] ?? 0), 2);
        $passiva = round((float) ($bs['totals']['passiva'] ?? 0), 2);
        $diff = round(abs($aktiva - $passiva), 2);

        return [
            'id' => 'bilanz',
            'label' => 'Bilanz stimmig',
            'status' => $diff <= 0.05 ? 'ok' : 'warn',
            'detail' => $diff <= 0.05
                ? sprintf('Aktiva %.2f € · Passiva inkl. Ergebnis %.2f €.', $aktiva, $passiva)
                : sprintf(
                    'Aktiva %.2f € vs. Passiva %.2f € (Diff. %.2f €) — Bilanz prüfen.',
                    $aktiva,
                    $passiva,
                    $diff
                ),
            'href' => '/app?page=buchhaltung-auswertungen&year=' . $year . '&type=bilanz',
        ];
    }

    /**
     * @return array{id: string, label: string, status: string, detail: string, href: string}
     */
    private static function itemUnbalancedVouchers(int $year): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM (
                SELECT voucher_id,
                       ROUND(SUM(CASE WHEN side='debit' THEN amount ELSE 0 END), 2) AS d,
                       ROUND(SUM(CASE WHEN side='credit' THEN amount ELSE 0 END), 2) AS c
                FROM dg_ledger_postings
                WHERE fiscal_year = :y AND source = 'voucher' AND voucher_id IS NOT NULL
                GROUP BY voucher_id
                HAVING ABS(d - c) > 0.01
             ) AS t"
        );
        $stmt->execute(['y' => $year]);
        $count = (int) $stmt->fetchColumn();

        return [
            'id' => 'voucher_balance',
            'label' => 'Belegbuchungen ausgeglichen',
            'status' => $count === 0 ? 'ok' : 'error',
            'detail' => $count === 0
                ? 'Jeder Beleg ist im Journal ausgeglichen (Soll = Haben).'
                : sprintf('%d Beleg(e) mit unbalancierten Journalzeilen.', $count),
            'href' => '/app?page=buchhaltung-belege&year=' . $year,
        ];
    }

    /**
     * @return array{id: string, label: string, status: string, detail: string, href: string}
     */
    private static function itemBankOpen(int $year): array
    {
        $open = BankTransactionRepository::list('open');
        $yearOpen = array_filter(
            $open,
            static fn (array $row): bool => (int) substr((string) ($row['booking_date'] ?? ''), 0, 4) === $year
        );
        $count = count($yearOpen);

        return [
            'id' => 'bank',
            'label' => 'Bankabgleich',
            'status' => $count === 0 ? 'ok' : 'warn',
            'detail' => $count === 0
                ? 'Keine offenen Bankumsätze aus ' . $year . '.'
                : sprintf('%d Bankumsätze aus %d noch nicht zugeordnet.', $count, $year),
            'href' => '/app?page=buchhaltung-bankabgleich',
        ];
    }
}
