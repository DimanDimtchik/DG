<?php
declare(strict_types=1);

/**
 * Jahresabschluss: Geschäftsjahr sperren und Saldenvortrag der Bestandskonten
 * (Aktiva/Passiva) ins Folgejahr übertragen. Erfolgskonten (Aufwand/Ertrag)
 * werden nicht vorgetragen (GuV/EÜR-Ergebnis fließt über den Saldenvortrag).
 */
final class FiscalYearService
{
    /**
     * list.
     *
     * @return array<int, array{year: int, status: string, closed_at: ?string, note: string}>
     */
        public static function list(): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();
        $rows = Database::pdo()->query('SELECT * FROM dg_fiscal_years ORDER BY year DESC')->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'year' => (int) $row['year'],
                'status' => (string) $row['status'],
                'closed_at' => $row['closed_at'] !== null ? (string) $row['closed_at'] : null,
                'note' => (string) ($row['note'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Liefert den Status eines Geschäftsjahres
     * @param int $year Geschäftsjahr
     * @return string
     */
    public static function status(int $year): string
    {
        if (!Database::isConfigured()) {
            return 'open';
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare('SELECT status FROM dg_fiscal_years WHERE year = :y');
        $stmt->execute(['y' => $year]);
        $status = $stmt->fetchColumn();

        return $status === 'closed' ? 'closed' : 'open';
    }

    /**
     * Prüft, ob ein Geschäftsjahr abgeschlossen ist
     * @param int $year Geschäftsjahr
     * @return bool
     */
    public static function isClosed(int $year): bool
    {
        return self::status($year) === 'closed';
    }

        /**
     * Jahr abschließen: Saldenvortrag der Bestandskonten ins Folgejahr buchen.
     * @param int $year Geschäftsjahr
     * @param int|null $userId Benutzer-ID
     * @return array{carried: int, equity: float, next_year: int}
     * @throws RuntimeException
     */
    public static function closeYear(int $year, ?int $userId): array
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }
        MigrationRunner::runPending();
        $pdo = Database::pdo();

        if (self::isClosed($year)) {
            throw new RuntimeException('Geschäftsjahr ' . $year . ' ist bereits abgeschlossen.');
        }

        $nextYear = $year + 1;
        $openingDate = sprintf('%04d-01-01', $nextYear);
        $carryAccount = LedgerAccounts::carryForwardAccount(ChartOfAccountsSettings::activeSkrType());
        $meta = LedgerRepository::accountMeta();

        // Schlusssalden des Jahres = Saldenvortrag + Bewegung.
        $opening = LedgerRepository::openingBalances($year);
        $movement = self::movementSigned($year);
        $accounts = array_unique(array_merge(array_keys($opening), array_keys($movement)));

        $pdo->beginTransaction();
        try {
            // Alte Vorträge des Folgejahres entfernen (idempotenter Wiederabschluss).
            $pdo->prepare("DELETE FROM dg_ledger_postings WHERE fiscal_year = :y AND source = 'opening_balance'")
                ->execute(['y' => $nextYear]);

            $insert = $pdo->prepare(
                "INSERT INTO dg_ledger_postings
                    (fiscal_year, posting_date, voucher_id, account_number, contra_account, side, amount, tax_rate, description, source)
                 VALUES
                    (:fiscal_year, :posting_date, NULL, :account_number, :contra_account, :side, :amount, 0, :description, 'opening_balance')"
            );

            $carried = 0;
            $equity = 0.0;
            foreach ($accounts as $acc) {
                $acc = (string) $acc;
                $section = (string) ($meta[$acc]['section'] ?? '');
                if (!LedgerAccounts::carriesForward($acc, $section)) {
                    continue; // Erfolgskonten nicht vortragen.
                }
                $signed = round(($opening[$acc] ?? 0.0) + ($movement[$acc] ?? 0.0), 2);
                if ($signed == 0.0) {
                    continue;
                }
                $side = $signed > 0 ? 'debit' : 'credit';
                $amount = round(abs($signed), 2);
                // Vortrag auf das Konto selbst …
                $insert->execute([
                    'fiscal_year' => $nextYear,
                    'posting_date' => $openingDate,
                    'account_number' => $acc,
                    'contra_account' => $carryAccount,
                    'side' => $side,
                    'amount' => $amount,
                    'description' => 'Saldenvortrag ' . $year,
                ]);
                // … und Gegenbuchung auf das Saldenvortragskonto (Bilanz bleibt ausgeglichen).
                $insert->execute([
                    'fiscal_year' => $nextYear,
                    'posting_date' => $openingDate,
                    'account_number' => $carryAccount,
                    'contra_account' => $acc,
                    'side' => $side === 'debit' ? 'credit' : 'debit',
                    'amount' => $amount,
                    'description' => 'Saldenvortrag ' . $year . ' · Konto ' . $acc,
                ]);
                $carried++;
                $equity = round($equity + $signed, 2);
            }

            self::upsertYear($pdo, $year, 'closed', $userId);
            self::upsertYear($pdo, $nextYear, 'open', null);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return ['carried' => $carried, 'equity' => $equity, 'next_year' => $nextYear];
    }

        /**
     * Abschluss zurücknehmen: Vorträge des Folgejahres entfernen, Jahr wieder öffnen.
     * @param int $year Geschäftsjahr
     * @return void
     * @throws RuntimeException
     */
    public static function reopenYear(int $year): void
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }
        MigrationRunner::runPending();
        $pdo = Database::pdo();

        if (self::isClosed($year + 1)) {
            throw new RuntimeException('Bitte zuerst das Folgejahr ' . ($year + 1) . ' wieder öffnen.');
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM dg_ledger_postings WHERE fiscal_year = :y AND source = 'opening_balance'")
                ->execute(['y' => $year + 1]);
            $stmt = $pdo->prepare("UPDATE dg_fiscal_years SET status = 'open', closed_at = NULL, closed_by = NULL WHERE year = :y");
            $stmt->execute(['y' => $year]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

        /**
     * GuV-Ergebnis (Ertrag − Aufwand) eines Jahres als Vorschau.
     * @param int $year Geschäftsjahr
     * @return array{income: float, expense: float, result: float}
     */
    public static function profitLossPreview(int $year): array
    {
        $movement = self::movementSigned($year);
        $meta = LedgerRepository::accountMeta();
        $income = 0.0;
        $expense = 0.0;
        foreach ($movement as $acc => $signed) {
            $section = (string) ($meta[$acc]['section'] ?? '');
            if ($section === 'ertrag') {
                $income = round($income - $signed, 2); // Ertrag hat Haben-Saldo (negativ signed)
            } elseif ($section === 'aufwand') {
                $expense = round($expense + $signed, 2); // Aufwand hat Soll-Saldo (positiv signed)
            }
        }

        return ['income' => $income, 'expense' => $expense, 'result' => round($income - $expense, 2)];
    }

        /**
     * Liefert Bewegungssaldo je Konto
     * @param int $year Geschäftsjahr
     * @return array<string, float> Bewegungssaldo (Soll − Haben) je Konto, ohne Saldenvortrag.
     */
    private static function movementSigned(int $year): array
    {
        $out = [];
        $stmt = Database::pdo()->prepare(
            "SELECT account_number,
                    SUM(CASE WHEN side='debit' THEN amount ELSE -amount END) AS signed
             FROM dg_ledger_postings
             WHERE fiscal_year = :y AND source <> 'opening_balance'
             GROUP BY account_number"
        );
        $stmt->execute(['y' => $year]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[(string) $row['account_number']] = round((float) $row['signed'], 2);
        }

        return $out;
    }

    /**
     * Legt ein Geschäftsjahr an oder aktualisiert es
     * @param PDO $pdo PDO-Verbindung
     * @param int $year Geschäftsjahr
     * @param string $status Statuswert
     * @param int|null $userId Benutzer-ID
     * @return void
     */
    private static function upsertYear(PDO $pdo, int $year, string $status, ?int $userId): void
    {
        $closedAt = $status === 'closed' ? date('Y-m-d H:i:s') : null;
        $stmt = $pdo->prepare(
            'INSERT INTO dg_fiscal_years (year, status, closed_at, closed_by)
             VALUES (:year, :status, :closed_at, :closed_by)
             ON DUPLICATE KEY UPDATE status = VALUES(status), closed_at = VALUES(closed_at), closed_by = VALUES(closed_by)'
        );
        $stmt->execute([
            'year' => $year,
            'status' => $status,
            'closed_at' => $closedAt,
            'closed_by' => $status === 'closed' ? $userId : null,
        ]);
    }
}
