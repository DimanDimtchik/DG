<?php
declare(strict_types=1);

/**
 * Auswertung des Buchungsjournals: Kontenübersicht (Salden je Konto) und
 * Kontoauszug (Einzelbuchungen mit Saldenvortrag), inkl. Saldenübertragung
 * aus Vorjahren.
 */
final class LedgerRepository
{
        /**
     * Journal für alle Belege neu aufbauen. Gibt Anzahl verarbeiteter Belege zurück.
     * @return int
     */
    public static function backfillAll(): int
    {
        if (!Database::isConfigured()) {
            return 0;
        }
        MigrationRunner::runPending();

        $ids = Database::pdo()->query('SELECT id FROM dg_vouchers ORDER BY id ASC')->fetchAll(PDO::FETCH_COLUMN);
        $count = 0;
        foreach ($ids as $id) {
            LedgerPostingService::rebuildForVoucher((int) $id);
            $count++;
        }

        return $count;
    }

    /**
     * availableYears.
     *
     * @return list<int> Jahre mit Buchungen, absteigend, inkl. aktuellem Jahr.
     */
        public static function availableYears(): array
    {
        $years = [];
        if (Database::isConfigured()) {
            MigrationRunner::runPending();
            $rows = Database::pdo()->query('SELECT DISTINCT fiscal_year FROM dg_ledger_postings ORDER BY fiscal_year DESC')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($rows as $y) {
                $years[] = (int) $y;
            }
        }
        $current = (int) date('Y');
        if (!in_array($current, $years, true)) {
            $years[] = $current;
        }
        rsort($years);

        return $years;
    }

        /**
     * Kontenübersicht: pro Konto Umsatz Soll/Haben, Saldenvortrag und Saldo.
     * @param int $year Geschäftsjahr
     * @param array $opts Filteroptionen
     * @return array{accounts: list<array<string, mixed>>, totals: array<string, float>}
     */
    public static function accountOverview(int $year, array $opts = []): array
    {
        $result = ['accounts' => [], 'totals' => ['debit' => 0.0, 'credit' => 0.0, 'opening' => 0.0, 'balance' => 0.0]];
        if (!Database::isConfigured()) {
            return $result;
        }
        MigrationRunner::runPending();
        $pdo = Database::pdo();
        $meta = self::accountMeta();
        $dateFrom = trim((string) ($opts['date_from'] ?? ''));
        $dateTo = trim((string) ($opts['date_to'] ?? ''));
        $usePeriod = $dateFrom !== '' && $dateTo !== ''
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1
            && ($dateFrom !== sprintf('%04d-01-01', $year) || $dateTo !== sprintf('%04d-12-31', $year));

        // Bewegungen (ohne Saldenvortrag), optional mit Zeitraum.
        $movement = [];
        if ($usePeriod) {
            $stmt = $pdo->prepare(
                "SELECT account_number, side, SUM(amount) AS total, COUNT(*) AS cnt
                 FROM dg_ledger_postings
                 WHERE fiscal_year = :y AND source <> 'opening_balance'
                   AND posting_date BETWEEN :from AND :to
                 GROUP BY account_number, side"
            );
            $stmt->execute(['y' => $year, 'from' => $dateFrom, 'to' => $dateTo]);
        } else {
            $stmt = $pdo->prepare(
                "SELECT account_number, side, SUM(amount) AS total, COUNT(*) AS cnt
                 FROM dg_ledger_postings
                 WHERE fiscal_year = :y AND source <> 'opening_balance'
                 GROUP BY account_number, side"
            );
            $stmt->execute(['y' => $year]);
        }
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $acc = (string) $row['account_number'];
            $movement[$acc] ??= ['debit' => 0.0, 'credit' => 0.0, 'cnt' => 0];
            $movement[$acc][(string) $row['side']] = round((float) $row['total'], 2);
            $movement[$acc]['cnt'] += (int) $row['cnt'];
        }

        // Explizite Saldenvorträge des Jahres, ggf. um Vorperioden-Bewegungen ergänzt.
        $opening = self::openingBalances($year);
        if ($usePeriod && $dateFrom !== sprintf('%04d-01-01', $year)) {
            foreach (self::movementTotalsBefore($year, $dateFrom) as $acc => $mov) {
                $opening[$acc] = round(($opening[$acc] ?? 0.0) + (float) $mov['debit'] - (float) $mov['credit'], 2);
            }
        }

        $accounts = array_unique(array_merge(array_keys($movement), array_keys($opening)));
        $search = mb_strtolower(trim((string) ($opts['search'] ?? '')));
        $showEmpty = (bool) ($opts['show_empty'] ?? false);

        if ($showEmpty) {
            $accounts = array_unique(array_merge($accounts, array_keys($meta)));
        }

        $list = [];
        foreach ($accounts as $acc) {
            $acc = (string) $acc;
            $mov = $movement[$acc] ?? ['debit' => 0.0, 'credit' => 0.0, 'cnt' => 0];
            $open = $opening[$acc] ?? 0.0;
            $debit = round((float) $mov['debit'], 2);
            $credit = round((float) $mov['credit'], 2);
            $balance = round($open + $debit - $credit, 2);
            $hasActivity = $debit != 0.0 || $credit != 0.0 || $open != 0.0;
            if (!$showEmpty && !$hasActivity) {
                continue;
            }
            $name = (string) ($meta[$acc]['name'] ?? '');
            $section = (string) ($meta[$acc]['section'] ?? '');
            if ($search !== '') {
                $haystack = mb_strtolower($acc . ' ' . $name);
                if (!str_contains($haystack, $search)) {
                    continue;
                }
            }
            $list[] = [
                'account_number' => $acc,
                'name' => $name,
                'section' => $section,
                'debit' => $debit,
                'credit' => $credit,
                'opening' => round($open, 2),
                'balance' => $balance,
                'count' => (int) $mov['cnt'],
            ];
            $result['totals']['debit'] = round($result['totals']['debit'] + $debit, 2);
            $result['totals']['credit'] = round($result['totals']['credit'] + $credit, 2);
            $result['totals']['opening'] = round($result['totals']['opening'] + $open, 2);
            $result['totals']['balance'] = round($result['totals']['balance'] + $balance, 2);
        }

        usort($list, static fn (array $a, array $b): int => strnatcmp((string) $a['account_number'], (string) $b['account_number']));
        $result['accounts'] = $list;

        return $result;
    }

    /**
     * Buchungssätze eines Belegs (für Anzeige im Belegformular).
     *
     * @return list<array<string, mixed>>
     */
    public static function postingsForVoucher(int $voucherId): array
    {
        if (!Database::isConfigured() || $voucherId < 1) {
            return [];
        }
        MigrationRunner::runPending();
        $meta = self::accountMeta();

        $stmt = Database::pdo()->prepare(
            "SELECT * FROM dg_ledger_postings
             WHERE voucher_id = :id AND source = 'voucher'
             ORDER BY fiscal_year ASC, posting_date ASC, id ASC"
        );
        $stmt->execute(['id' => $voucherId]);
        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $acc = (string) ($row['account_number'] ?? '');
            $contra = (string) ($row['contra_account'] ?? '');
            $rows[] = [
                'id' => (int) ($row['id'] ?? 0),
                'fiscal_year' => (int) ($row['fiscal_year'] ?? 0),
                'date' => (string) ($row['posting_date'] ?? ''),
                'account_number' => $acc,
                'account_name' => (string) ($meta[$acc]['name'] ?? ''),
                'contra_account' => $contra,
                'contra_name' => (string) ($meta[$contra]['name'] ?? ''),
                'person_account' => (string) ($row['person_account'] ?? ''),
                'side' => (string) ($row['side'] ?? 'debit'),
                'side_label' => (string) ($row['side'] ?? 'debit') === 'credit' ? 'H' : 'S',
                'amount' => round((float) ($row['amount'] ?? 0), 2),
                'tax_rate' => (int) ($row['tax_rate'] ?? 0),
                'tax_key' => (string) ($row['tax_key'] ?? ''),
                'tax_key_label' => VoucherTaxKeys::label((string) ($row['tax_key'] ?? '')),
                'document_field1' => (string) ($row['document_field1'] ?? ''),
                'document_field2' => (string) ($row['document_field2'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
            ];
        }

        return $rows;
    }

        /**
     * Kontoauszug eines Kontos: Saldenvortrag + chronologische Einzelbuchungen mit laufendem Saldo.
     * @param string $accountNumber Kontonummer
     * @param int $year Geschäftsjahr
     * @return array{account: array<string, mixed>, opening: float, rows: list<array<string, mixed>>, closing: float, debit: float, credit: float}
     */
    public static function accountStatement(string $accountNumber, int $year, array $opts = []): array
    {
        $accountNumber = preg_replace('/\s+/', '', $accountNumber) ?? '';
        $meta = self::accountMeta();
        $account = [
            'account_number' => $accountNumber,
            'name' => (string) ($meta[$accountNumber]['name'] ?? ''),
            'section' => (string) ($meta[$accountNumber]['section'] ?? ''),
        ];
        $out = ['account' => $account, 'opening' => 0.0, 'rows' => [], 'closing' => 0.0, 'debit' => 0.0, 'credit' => 0.0];
        if (!Database::isConfigured() || $accountNumber === '') {
            return $out;
        }
        MigrationRunner::runPending();
        $pdo = Database::pdo();

        $dateFrom = trim((string) ($opts['date_from'] ?? ''));
        $dateTo = trim((string) ($opts['date_to'] ?? ''));
        $usePeriod = $dateFrom !== '' && $dateTo !== ''
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1;

        $opening = self::openingBalances($year)[$accountNumber] ?? 0.0;
        if ($usePeriod && $dateFrom !== sprintf('%04d-01-01', $year)) {
            $prior = self::movementTotalsBefore($year, $dateFrom)[$accountNumber] ?? ['debit' => 0.0, 'credit' => 0.0];
            $opening = round($opening + (float) $prior['debit'] - (float) $prior['credit'], 2);
        }
        $out['opening'] = round($opening, 2);
        $running = round($opening, 2);

        if ($usePeriod) {
            $stmt = $pdo->prepare(
                "SELECT p.*, v.voucher_type, v.supplier_name, v.invoice_number
                 FROM dg_ledger_postings p
                 LEFT JOIN dg_vouchers v ON v.id = p.voucher_id
                 WHERE p.account_number = :acc AND p.fiscal_year = :y AND p.source <> 'opening_balance'
                   AND p.posting_date BETWEEN :from AND :to
                 ORDER BY p.posting_date ASC, p.id ASC"
            );
            $stmt->execute(['acc' => $accountNumber, 'y' => $year, 'from' => $dateFrom, 'to' => $dateTo]);
        } else {
            $stmt = $pdo->prepare(
                "SELECT p.*, v.voucher_type, v.supplier_name, v.invoice_number
                 FROM dg_ledger_postings p
                 LEFT JOIN dg_vouchers v ON v.id = p.voucher_id
                 WHERE p.account_number = :acc AND p.fiscal_year = :y AND p.source <> 'opening_balance'
                 ORDER BY p.posting_date ASC, p.id ASC"
            );
            $stmt->execute(['acc' => $accountNumber, 'y' => $year]);
        }
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $side = (string) $row['side'];
            $amount = round((float) $row['amount'], 2);
            $debit = $side === 'debit' ? $amount : 0.0;
            $credit = $side === 'credit' ? $amount : 0.0;
            $running = round($running + $debit - $credit, 2);
            $out['debit'] = round($out['debit'] + $debit, 2);
            $out['credit'] = round($out['credit'] + $credit, 2);
            $out['rows'][] = [
                'id' => (int) $row['id'],
                'date' => (string) $row['posting_date'],
                'voucher_id' => $row['voucher_id'] !== null ? (int) $row['voucher_id'] : null,
                'contra_account' => (string) $row['contra_account'],
                'contra_name' => (string) ($meta[(string) $row['contra_account']]['name'] ?? ''),
                'description' => (string) $row['description'],
                'invoice_number' => (string) ($row['invoice_number'] ?? ''),
                'tax_rate' => (int) $row['tax_rate'],
                'tax_key' => (string) ($row['tax_key'] ?? ''),
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $running,
                'source' => (string) $row['source'],
            ];
        }

        $out['closing'] = $running;

        return $out;
    }

        /**
     * Saldenvortrag je Konto für ein Jahr (vorzeichenbehaftet: Soll − Haben).
     * @param int $year Geschäftsjahr
     * @return array<string, float>
     */
    public static function openingBalances(int $year): array
    {
        $result = [];
        if (!Database::isConfigured()) {
            return $result;
        }
        $pdo = Database::pdo();

        $stmt = $pdo->prepare(
            "SELECT account_number,
                    SUM(CASE WHEN side='debit' THEN amount ELSE -amount END) AS signed
             FROM dg_ledger_postings
             WHERE fiscal_year = :y AND source = 'opening_balance'
             GROUP BY account_number"
        );
        $stmt->execute(['y' => $year]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[(string) $row['account_number']] = round((float) $row['signed'], 2);
        }
        if ($result !== []) {
            return $result;
        }

        // Kein formeller Abschluss: kumulierte Bestandskonten-Salden aus Vorjahren übernehmen.
        $meta = self::accountMeta();
        $stmt = $pdo->prepare(
            "SELECT account_number,
                    SUM(CASE WHEN side='debit' THEN amount ELSE -amount END) AS signed
             FROM dg_ledger_postings
             WHERE fiscal_year < :y
             GROUP BY account_number"
        );
        $stmt->execute(['y' => $year]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $acc = (string) $row['account_number'];
            $section = (string) ($meta[$acc]['section'] ?? '');
            if (!LedgerAccounts::carriesForward($acc, $section)) {
                continue;
            }
            $signed = round((float) $row['signed'], 2);
            if ($signed != 0.0) {
                $result[$acc] = $signed;
            }
        }

        return $result;
    }

    /**
     * @return array<string, array{debit: float, credit: float}>
     */
    private static function movementTotalsBefore(int $year, string $dateFrom): array
    {
        $result = [];
        if (!Database::isConfigured() || $dateFrom === sprintf('%04d-01-01', $year)) {
            return $result;
        }

        $stmt = Database::pdo()->prepare(
            "SELECT account_number, side, SUM(amount) AS total
             FROM dg_ledger_postings
             WHERE fiscal_year = :y AND source <> 'opening_balance'
               AND posting_date >= :year_start AND posting_date < :from
             GROUP BY account_number, side"
        );
        $stmt->execute([
            'y' => $year,
            'year_start' => sprintf('%04d-01-01', $year),
            'from' => $dateFrom,
        ]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $acc = (string) ($row['account_number'] ?? '');
            $result[$acc] ??= ['debit' => 0.0, 'credit' => 0.0];
            $result[$acc][(string) ($row['side'] ?? 'debit')] = round((float) ($row['total'] ?? 0), 2);
        }

        return $result;
    }

    /**
     * accountMeta.
     *
     * @return array<string, array{name: string, section: string}>
     */
        public static function accountMeta(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        if (!Database::isConfigured()) {
            return $cache;
        }
        try {
            $skr = ChartOfAccountsSettings::activeSkrType();
            $stmt = Database::pdo()->prepare('SELECT account_number, name, section FROM dg_chart_accounts WHERE skr_type = :skr');
            $stmt->execute(['skr' => $skr]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $cache[(string) $row['account_number']] = [
                    'name' => (string) ($row['name'] ?? ''),
                    'section' => (string) ($row['section'] ?? ''),
                ];
            }
        } catch (Throwable) {
            $cache = [];
        }

        return $cache;
    }
}
