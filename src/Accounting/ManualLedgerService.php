<?php
declare(strict_types=1);

/** Manuelle Journalbuchungen (freie Buchungssätze ohne Beleg). */
final class ManualLedgerService
{
    /**
     * @param list<array{account_number: string, side: string, amount: float, tax_key?: string, description?: string, contra_account?: string}> $lines
     */
    public static function createBatch(string $batchDate, string $description, array $lines, ?int $userId): int
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }
        MigrationRunner::runPending();

        $batchDate = self::sanitizeDate($batchDate);
        $year = (int) substr($batchDate, 0, 4);
        if (FiscalYearService::isClosed($year)) {
            throw new InvalidArgumentException('Geschäftsjahr ' . $year . ' ist abgeschlossen.');
        }

        $normalized = self::normalizeLines($lines);
        self::assertBalanced($normalized);

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO dg_manual_journal_batches (batch_date, description, fiscal_year, created_by)
                 VALUES (:batch_date, :description, :fiscal_year, :created_by)'
            );
            $stmt->execute([
                'batch_date' => $batchDate,
                'description' => mb_substr(trim($description), 0, 500),
                'fiscal_year' => $year,
                'created_by' => $userId !== null && $userId > 0 ? $userId : null,
            ]);
            $batchId = (int) $pdo->lastInsertId();

            $insert = $pdo->prepare(
                'INSERT INTO dg_ledger_postings
                    (fiscal_year, posting_date, voucher_id, manual_batch_id, account_number, contra_account,
                     side, amount, tax_rate, tax_key, description, document_field1, document_field2, source)
                 VALUES
                    (:fiscal_year, :posting_date, NULL, :manual_batch_id, :account_number, :contra_account,
                     :side, :amount, 0, :tax_key, :description, :document_field1, :document_field2, :source)'
            );

            foreach ($normalized as $line) {
                $insert->execute([
                    'fiscal_year' => $year,
                    'posting_date' => $batchDate,
                    'manual_batch_id' => $batchId,
                    'account_number' => $line['account_number'],
                    'contra_account' => $line['contra_account'],
                    'side' => $line['side'],
                    'amount' => $line['amount'],
                    'tax_key' => $line['tax_key'],
                    'description' => $line['description'],
                    'document_field1' => $line['document_field1'],
                    'document_field2' => '',
                    'source' => 'manual',
                ]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $batchId;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listBatches(int $year): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'SELECT b.*,
                    (SELECT COUNT(*) FROM dg_ledger_postings p WHERE p.manual_batch_id = b.id) AS line_count,
                    (SELECT ROUND(SUM(CASE WHEN side=\'debit\' THEN amount ELSE 0 END), 2)
                     FROM dg_ledger_postings p WHERE p.manual_batch_id = b.id) AS total_debit
             FROM dg_manual_journal_batches b
             WHERE b.fiscal_year = :y
             ORDER BY b.batch_date DESC, b.id DESC'
        );
        $stmt->execute(['y' => $year]);
        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $row;
        }

        return $rows;
    }

    public static function deleteBatch(int $batchId): void
    {
        if (!Database::isConfigured() || $batchId < 1) {
            return;
        }
        MigrationRunner::runPending();

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT fiscal_year FROM dg_manual_journal_batches WHERE id = :id');
        $stmt->execute(['id' => $batchId]);
        $year = (int) $stmt->fetchColumn();
        if ($year > 0 && FiscalYearService::isClosed($year)) {
            throw new RuntimeException('Abgeschlossenes Geschäftsjahr — manuelle Buchung kann nicht gelöscht werden.');
        }

        $pdo->prepare('DELETE FROM dg_ledger_postings WHERE manual_batch_id = :id AND source = :source')
            ->execute(['id' => $batchId, 'source' => 'manual']);
        $pdo->prepare('DELETE FROM dg_manual_journal_batches WHERE id = :id')->execute(['id' => $batchId]);
    }

    /**
     * @param list<array<string, mixed>> $lines
     * @return list<array<string, mixed>>
     */
    private static function normalizeLines(array $lines): array
    {
        $skr = ChartOfAccountsSettings::activeSkrType();
        $out = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $account = preg_replace('/\D/', '', (string) ($line['account_number'] ?? '')) ?? '';
            $amount = round(abs((float) str_replace(',', '.', (string) ($line['amount'] ?? '0'))), 2);
            $side = (string) ($line['side'] ?? 'debit') === 'credit' ? 'credit' : 'debit';
            if ($account === '' || $amount <= 0.0) {
                continue;
            }
            if (ChartAccountRepository::findByNumber($account, $skr) === null) {
                throw new InvalidArgumentException('Konto ' . $account . ' nicht im Kontenrahmen gefunden.');
            }
            $contra = preg_replace('/\D/', '', (string) ($line['contra_account'] ?? '')) ?? '';
            if ($contra === '') {
                $contra = LedgerAccounts::carryForwardAccount($skr);
            }
            $desc = trim((string) ($line['description'] ?? ''));
            $out[] = [
                'account_number' => $account,
                'contra_account' => mb_substr($contra, 0, 16),
                'side' => $side,
                'amount' => $amount,
                'tax_key' => VoucherTaxKeys::sanitizeTaxKey((string) ($line['tax_key'] ?? '')),
                'description' => $desc,
                'document_field1' => mb_substr(trim((string) ($line['document_field1'] ?? '')), 0, 36),
            ];
        }
        if (count($out) < 2) {
            throw new InvalidArgumentException('Mindestens zwei Buchungszeilen (Soll und Haben) erforderlich.');
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    private static function assertBalanced(array $lines): void
    {
        $debit = 0.0;
        $credit = 0.0;
        foreach ($lines as $line) {
            if ($line['side'] === 'debit') {
                $debit = round($debit + (float) $line['amount'], 2);
            } else {
                $credit = round($credit + (float) $line['amount'], 2);
            }
        }
        if (abs($debit - $credit) > 0.005) {
            throw new InvalidArgumentException(
                'Buchungssatz unbalanciert: Soll ' . number_format($debit, 2, ',', '.')
                . ' €, Haben ' . number_format($credit, 2, ',', '.') . ' €'
            );
        }
    }

    private static function sanitizeDate(string $date): string
    {
        $ts = strtotime(trim($date));

        return $ts !== false ? date('Y-m-d', $ts) : date('Y-m-d');
    }
}
