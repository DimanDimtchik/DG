<?php
declare(strict_types=1);

/** Persistenz der TSE-Signaturen zu Barzahlungen. */
final class TseTransactionRepository
{
    public const STATE_ACTIVE = 'ACTIVE';
    public const STATE_FINISHED = 'FINISHED';
    public const STATE_CANCELLED = 'CANCELLED';
    public const STATE_FAILED = 'FAILED';

    public static function tableExists(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        $stmt = Database::pdo()->query("SHOW TABLES LIKE 'dg_tse_transactions'");

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function upsert(array $data): int
    {
        MigrationRunner::runPending();
        if (!self::tableExists()) {
            throw new RuntimeException('TSE-Tabelle fehlt.');
        }

        $paymentId = (int) ($data['voucher_payment_id'] ?? 0);
        $existing = $paymentId > 0 ? self::findByPaymentId($paymentId) : null;

        $fields = [
            'voucher_id' => (int) ($data['voucher_id'] ?? 0),
            'voucher_payment_id' => $paymentId,
            'tse_tx_id' => (string) ($data['tse_tx_id'] ?? ''),
            'tss_id' => (string) ($data['tss_id'] ?? ''),
            'tse_client_id' => (string) ($data['tse_client_id'] ?? ''),
            'state' => (string) ($data['state'] ?? self::STATE_ACTIVE),
            'tx_number' => isset($data['tx_number']) ? (int) $data['tx_number'] : null,
            'revision' => isset($data['revision']) ? (int) $data['revision'] : null,
            'signature_value' => $data['signature_value'] ?? null,
            'signature_algorithm' => $data['signature_algorithm'] ?? null,
            'signature_counter' => $data['signature_counter'] ?? null,
            'qr_code_data' => $data['qr_code_data'] ?? null,
            'time_start' => isset($data['time_start']) ? (int) $data['time_start'] : null,
            'time_end' => isset($data['time_end']) ? (int) $data['time_end'] : null,
            'client_serial_number' => $data['client_serial_number'] ?? null,
            'tss_serial_number' => $data['tss_serial_number'] ?? null,
            'receipt_type' => $data['receipt_type'] ?? null,
            'amount_gross' => round((float) ($data['amount_gross'] ?? 0), 2),
            'error_message' => $data['error_message'] ?? null,
        ];

        if ($existing !== null) {
            $set = [];
            $params = ['id' => (int) $existing['id']];
            foreach ($fields as $col => $value) {
                $set[] = $col . ' = :' . $col;
                $params[$col] = $value;
            }
            Database::pdo()->prepare(
                'UPDATE dg_tse_transactions SET ' . implode(', ', $set) . ' WHERE id = :id'
            )->execute($params);

            return (int) $existing['id'];
        }

        $cols = array_keys($fields);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $cols);
        Database::pdo()->prepare(
            'INSERT INTO dg_tse_transactions (' . implode(', ', $cols) . ')
             VALUES (' . implode(', ', $placeholders) . ')'
        )->execute($fields);

        return (int) Database::pdo()->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByPaymentId(int $paymentId): ?array
    {
        if ($paymentId < 1 || !Database::isConfigured() || !self::tableExists()) {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_tse_transactions WHERE voucher_payment_id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $paymentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function latestFinishedForVoucher(int $voucherId): ?array
    {
        if ($voucherId < 1 || !Database::isConfigured() || !self::tableExists()) {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_tse_transactions
             WHERE voucher_id = :id AND state = :state
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['id' => $voucherId, 'state' => self::STATE_FINISHED]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param list<int> $paymentIds
     * @return array<int, array<string, mixed>>
     */
    public static function mapByPaymentIds(array $paymentIds): array
    {
        $ids = [];
        foreach ($paymentIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === [] || !Database::isConfigured() || !self::tableExists()) {
            return [];
        }
        $placeholders = [];
        $params = [];
        $i = 0;
        foreach ($ids as $id) {
            $key = 'id' . $i++;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_tse_transactions WHERE voucher_payment_id IN (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($params);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            $out[(int) $row['voucher_payment_id']] = $row;
        }

        return $out;
    }

    public static function hasFinishedForVoucher(int $voucherId): bool
    {
        return self::latestFinishedForVoucher($voucherId) !== null;
    }

    public static function paymentIsSigned(int $paymentId): bool
    {
        $row = self::findByPaymentId($paymentId);

        return is_array($row) && (string) ($row['state'] ?? '') === self::STATE_FINISHED;
    }
}
