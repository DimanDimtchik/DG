<?php
declare(strict_types=1);

/** Bons + Positionen (K1j Stub). */
final class CashReceiptRepository
{
    private const TABLE = 'dg_cash_receipts';
    private const LINES = 'dg_cash_receipt_lines';

    public static function tableExists(): bool
    {
        return CashRegisterTable::tableExists(self::TABLE)
            && CashRegisterTable::tableExists(self::LINES);
    }

    /** @return array<string, mixed>|null */
    public static function findById(int $id): ?array
    {
        return CashRegisterTable::findById(self::TABLE, $id);
    }

    /** @return list<array<string, mixed>> */
    public static function listLines(int $receiptId): array
    {
        if ($receiptId <= 0 || !CashRegisterTable::tableExists(self::LINES)) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_cash_receipt_lines WHERE receipt_id = :rid ORDER BY line_no ASC, id ASC'
        );
        $stmt->execute(['rid' => $receiptId]);
        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $row;
        }

        return $rows;
    }

    /** @param array<string, mixed> $data */
    public static function insertReceipt(array $data): int
    {
        return CashRegisterTable::insert(self::TABLE, [
            'location_id' => (int) ($data['location_id'] ?? 0),
            'register_id' => (int) ($data['register_id'] ?? 0),
            'session_id' => (int) ($data['session_id'] ?? 0),
            'tse_assignment_id' => isset($data['tse_assignment_id']) ? (int) $data['tse_assignment_id'] : null,
            'voucher_id' => isset($data['voucher_id']) ? (int) $data['voucher_id'] : null,
            'receipt_number' => (int) ($data['receipt_number'] ?? 0),
            'receipt_number_display' => trim((string) ($data['receipt_number_display'] ?? '')),
            'status' => trim((string) ($data['status'] ?? 'draft')) ?: 'draft',
            'receipt_type' => trim((string) ($data['receipt_type'] ?? 'sale')) ?: 'sale',
            'storno_of_receipt_id' => isset($data['storno_of_receipt_id']) ? (int) $data['storno_of_receipt_id'] : null,
            'operator_id' => isset($data['operator_id']) ? (int) $data['operator_id'] : null,
            'total_gross' => round((float) ($data['total_gross'] ?? 0), 2),
            'total_net' => round((float) ($data['total_net'] ?? 0), 2),
            'vat_summary_json' => $data['vat_summary_json'] ?? null,
            'payment_summary_json' => $data['payment_summary_json'] ?? null,
            'tse_tx_id' => trim((string) ($data['tse_tx_id'] ?? '')),
            'transaction_number' => isset($data['transaction_number']) ? (int) $data['transaction_number'] : null,
            'tse_serial' => trim((string) ($data['tse_serial'] ?? '')),
            'signature_counter' => trim((string) ($data['signature_counter'] ?? '')),
            'signature_value' => $data['signature_value'] ?? null,
            'signature_algorithm' => trim((string) ($data['signature_algorithm'] ?? '')),
            'qr_code_data' => $data['qr_code_data'] ?? null,
            'time_start' => isset($data['time_start']) ? (int) $data['time_start'] : null,
            'time_end' => isset($data['time_end']) ? (int) $data['time_end'] : null,
            'started_at' => (string) ($data['started_at'] ?? date('Y-m-d H:i:s')),
            'finished_at' => $data['finished_at'] ?? null,
            'notes' => trim((string) ($data['notes'] ?? '')),
        ]);
    }

    /** @param array<string, mixed> $data */
    public static function updateReceipt(int $id, array $data): void
    {
        CashRegisterTable::update(self::TABLE, $id, $data);
    }

    /** @param array<string, mixed> $data */
    public static function insertLine(array $data): int
    {
        return CashRegisterTable::insert(self::LINES, [
            'receipt_id' => (int) ($data['receipt_id'] ?? 0),
            'line_no' => (int) ($data['line_no'] ?? 1),
            'article_id' => (int) ($data['article_id'] ?? 0),
            'article_number' => trim((string) ($data['article_number'] ?? '')),
            'title' => trim((string) ($data['title'] ?? '')),
            'unit' => trim((string) ($data['unit'] ?? 'Stück')) ?: 'Stück',
            'quantity' => round((float) ($data['quantity'] ?? 1), 3),
            'unit_price_gross' => round((float) ($data['unit_price_gross'] ?? 0), 2),
            'gross_amount' => round((float) ($data['gross_amount'] ?? 0), 2),
            'tax_rate' => (int) ($data['tax_rate'] ?? 19),
        ]);
    }

    public static function deleteLinesForReceipt(int $receiptId): void
    {
        if ($receiptId <= 0 || !CashRegisterTable::tableExists(self::LINES)) {
            return;
        }
        Database::pdo()->prepare('DELETE FROM dg_cash_receipt_lines WHERE receipt_id = :rid')
            ->execute(['rid' => $receiptId]);
    }
}
