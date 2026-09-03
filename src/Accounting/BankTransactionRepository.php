<?php
declare(strict_types=1);

/** Persistenz für importierte Bankumsätze. */
final class BankTransactionRepository
{
    /**
     * @param array<string, mixed> $data
     * @return array{id: int, skipped: bool, reason: string}
     */
    public static function insertOrSkip(array $data): array
    {
        $fingerprint = self::fingerprintFor($data);
        if ($fingerprint !== '') {
            $existing = self::findByFingerprint($fingerprint);
            if ($existing !== null) {
                return [
                    'id' => (int) ($existing['id'] ?? 0),
                    'skipped' => true,
                    'reason' => 'duplicate',
                ];
            }
        }

        return [
            'id' => self::insert($data, $fingerprint),
            'skipped' => false,
            'reason' => '',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function insert(array $data, ?string $fingerprint = null): int
    {
        MigrationRunner::runPending();
        $pdo = Database::pdo();
        $fingerprint ??= self::fingerprintFor($data);
        $stmt = $pdo->prepare(
            'INSERT INTO dg_bank_transactions
                (import_batch, transaction_date, value_date, amount, currency, counterparty_name,
                 counterparty_iban, reference_text, end_to_end_id, transaction_fingerprint, match_status)
             VALUES
                (:import_batch, :transaction_date, :value_date, :amount, :currency, :counterparty_name,
                 :counterparty_iban, :reference_text, :end_to_end_id, :transaction_fingerprint, :match_status)'
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
            'transaction_fingerprint' => $fingerprint,
            'match_status' => 'open',
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fingerprintFor(array $data): string
    {
        $endToEnd = strtoupper(trim((string) ($data['end_to_end_id'] ?? '')));
        if ($endToEnd !== '' && !self::isMeaninglessEndToEndId($endToEnd)) {
            return hash('sha256', 'e2e:' . $endToEnd);
        }

        $date = (string) ($data['transaction_date'] ?? '');
        $amount = number_format(round((float) ($data['amount'] ?? 0), 2), 2, '.', '');
        $iban = strtoupper(str_replace(' ', '', (string) ($data['counterparty_iban'] ?? '')));
        $reference = mb_strtolower(trim((string) ($data['reference_text'] ?? '')));

        return hash('sha256', implode('|', [$date, $amount, $iban, $reference]));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByFingerprint(string $fingerprint, ?int $excludeId = null): ?array
    {
        if ($fingerprint === '' || !Database::isConfigured()) {
            return null;
        }
        MigrationRunner::runPending();

        $sql = 'SELECT * FROM dg_bank_transactions WHERE transaction_fingerprint = :fp';
        $params = ['fp' => $fingerprint];
        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }
        $sql .= ' ORDER BY id ASC LIMIT 1';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
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

    /**
     * Setzt Fingerabdrücke für Altbestände nach Migration.
     *
     * @return int Anzahl aktualisierter Zeilen
     */
    public static function backfillFingerprints(): int
    {
        if (!Database::isConfigured()) {
            return 0;
        }
        MigrationRunner::runPending();
        $pdo = Database::pdo();
        $stmt = $pdo->query(
            "SELECT id, transaction_date, amount, counterparty_iban, reference_text, end_to_end_id
             FROM dg_bank_transactions
             WHERE transaction_fingerprint = '' OR transaction_fingerprint IS NULL"
        );
        if ($stmt === false) {
            return 0;
        }

        $update = $pdo->prepare('UPDATE dg_bank_transactions SET transaction_fingerprint = :fp WHERE id = :id');
        $count = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            $fp = self::fingerprintFor($row);
            $update->execute(['fp' => $fp, 'id' => (int) $row['id']]);
            $count++;
        }

        return $count;
    }

    private static function isMeaninglessEndToEndId(string $endToEnd): bool
    {
        return in_array($endToEnd, ['NOTPROVIDED', 'NOT PROVIDED', 'E2EID', 'NONE', 'NULL', 'N/A'], true)
            || str_starts_with($endToEnd, 'NOTPROVIDED');
    }
}
