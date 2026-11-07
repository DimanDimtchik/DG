<?php
declare(strict_types=1);

/** Zahlungen je Bon (K1j / K7c). */
final class CashPaymentRepository
{
    private const TABLE = 'dg_cash_payments';

    public static function tableExists(): bool
    {
        return CashRegisterTable::tableExists(self::TABLE);
    }

    /** @return array<string, mixed>|null */
    public static function findById(int $id): ?array
    {
        return CashRegisterTable::findById(self::TABLE, $id);
    }

    /** @return list<array<string, mixed>> */
    public static function listByReceipt(int $receiptId): array
    {
        if ($receiptId <= 0 || !self::tableExists()) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_cash_payments WHERE receipt_id = :rid ORDER BY line_no ASC, id ASC'
        );
        $stmt->execute(['rid' => $receiptId]);
        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $row;
        }

        return $rows;
    }

    public static function nextLineNo(int $receiptId): int
    {
        if ($receiptId <= 0 || !self::tableExists()) {
            return 1;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT COALESCE(MAX(line_no), 0) FROM dg_cash_payments WHERE receipt_id = :rid'
        );
        $stmt->execute(['rid' => $receiptId]);

        return ((int) $stmt->fetchColumn()) + 1;
    }

    /** @param array<string, mixed> $data */
    public static function insert(array $data): int
    {
        return CashRegisterTable::insert(self::TABLE, [
            'receipt_id' => (int) ($data['receipt_id'] ?? 0),
            'line_no' => (int) ($data['line_no'] ?? 1),
            'payment_method' => trim((string) ($data['payment_method'] ?? '')),
            'amount' => round((float) ($data['amount'] ?? 0), 2),
            'currency' => strtoupper(substr(trim((string) ($data['currency'] ?? 'EUR')), 0, 3)) ?: 'EUR',
            'gift_voucher_id' => isset($data['gift_voucher_id']) ? (int) $data['gift_voucher_id'] : null,
            'external_ref' => trim((string) ($data['external_ref'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
        ]);
    }

    public static function delete(int $paymentId): void
    {
        if ($paymentId <= 0 || !self::tableExists()) {
            return;
        }
        Database::pdo()->prepare('DELETE FROM dg_cash_payments WHERE id = :id')
            ->execute(['id' => $paymentId]);
    }

    public static function deleteForReceipt(int $receiptId): void
    {
        if ($receiptId <= 0 || !self::tableExists()) {
            return;
        }
        Database::pdo()->prepare('DELETE FROM dg_cash_payments WHERE receipt_id = :rid')
            ->execute(['rid' => $receiptId]);
    }

    /** @return array<string, string> */
    public static function methodLabels(): array
    {
        return [
            'cash' => 'Bar',
            'ec' => 'EC',
            'card' => 'Karte',
            'voucher' => 'Gutschein',
        ];
    }
}
