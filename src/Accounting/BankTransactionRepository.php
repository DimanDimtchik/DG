<?php
declare(strict_types=1);

/** Persistenz für importierte Bankumsätze. */
final class BankTransactionRepository
{
    /**
     * @param array<string, mixed> $data
     */
    public static function insert(array $data): int
    {
        MigrationRunner::runPending();
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO dg_bank_transactions
                (import_batch, transaction_date, value_date, amount, currency, counterparty_name,
                 counterparty_iban, reference_text, end_to_end_id, match_status)
             VALUES
                (:import_batch, :transaction_date, :value_date, :amount, :currency, :counterparty_name,
                 :counterparty_iban, :reference_text, :end_to_end_id, :match_status)'
        );
        $stmt->execute([
            'import_batch' => (string) ($data['import_batch'] ?? ''),
            'transaction_date' => (string) ($data['transaction_date'] ?? date('Y-m-d')),
            'value_date' => $data['value_date'] ?? null,
            'amount' => round((float) ($data['amount'] ?? 0), 2),
            'currency' => (string) ($data['currency'] ?? 'EUR'),
            'counterparty_name' => mb_substr((string) ($data['counterparty_name'] ?? ''), 0, 191),
            'counterparty_iban' => mb_substr((string) ($data['counterparty_iban'] ?? ''), 0, 34),
            'reference_text' => mb_substr((string) ($data['reference_text'] ?? ''), 0, 500),
            'end_to_end_id' => mb_substr((string) ($data['end_to_end_id'] ?? ''), 0, 64),
            'match_status' => 'open',
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function list(string $status = 'open'): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();

        $sql = 'SELECT t.*, v.invoice_number, v.supplier_name
                FROM dg_bank_transactions t
                LEFT JOIN dg_vouchers v ON v.id = t.matched_voucher_id';
        $params = [];
        if ($status === 'open' || $status === 'matched' || $status === 'ignored') {
            $sql .= ' WHERE t.match_status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY t.transaction_date DESC, t.id DESC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $amount = round((float) ($row['amount'] ?? 0), 2);
            $row['amount_display'] = number_format($amount, 2, ',', '.') . ' €';
            $rows[] = $row;
        }

        return $rows;
    }

    public static function markMatched(int $transactionId, int $voucherId): void
    {
        MigrationRunner::runPending();
        Database::pdo()->prepare(
            'UPDATE dg_bank_transactions SET match_status = :status, matched_voucher_id = :vid WHERE id = :id'
        )->execute(['status' => 'matched', 'vid' => $voucherId, 'id' => $transactionId]);
    }

    public static function markIgnored(int $transactionId): void
    {
        MigrationRunner::runPending();
        Database::pdo()->prepare(
            'UPDATE dg_bank_transactions SET match_status = :status WHERE id = :id'
        )->execute(['status' => 'ignored', 'id' => $transactionId]);
    }

    public static function findById(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_bank_transactions WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}
