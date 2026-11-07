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

    /**
     * Neueste Bons (K7a Liste).
     *
     * @return list<array<string, mixed>>
     */
    public static function listRecent(int $limit = 50, ?int $registerId = null): array
    {
        if (!self::tableExists()) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT r.*,
                       reg.code AS register_code, reg.name AS register_name,
                       l.code AS location_code, l.name AS location_name
                FROM dg_cash_receipts r
                LEFT JOIN dg_cash_registers reg ON reg.id = r.register_id
                LEFT JOIN dg_cash_locations l ON l.id = r.location_id';
        $params = [];
        if ($registerId !== null && $registerId > 0) {
            $sql .= ' WHERE r.register_id = :rid';
            $params['rid'] = $registerId;
        }
        $sql .= ' ORDER BY r.id DESC LIMIT ' . $limit;
        $stmt = $params === []
            ? Database::pdo()->query($sql)
            : Database::pdo()->prepare($sql);
        if ($stmt === false) {
            return [];
        }
        if ($params !== []) {
            $stmt->execute($params);
        }
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

    /** @return array<string, mixed>|null */
    public static function findLineById(int $lineId): ?array
    {
        return CashRegisterTable::findById(self::LINES, $lineId);
    }

    public static function nextLineNo(int $receiptId): int
    {
        if ($receiptId <= 0 || !CashRegisterTable::tableExists(self::LINES)) {
            return 1;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT COALESCE(MAX(line_no), 0) FROM dg_cash_receipt_lines WHERE receipt_id = :rid'
        );
        $stmt->execute(['rid' => $receiptId]);

        return ((int) $stmt->fetchColumn()) + 1;
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

    /** @param array<string, mixed> $data */
    public static function updateLine(int $lineId, array $data): void
    {
        $fields = [];
        foreach (['article_id', 'article_number', 'title', 'unit', 'quantity', 'unit_price_gross', 'gross_amount', 'tax_rate'] as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $fields[$key] = $data[$key];
        }
        if (isset($fields['article_number'])) {
            $fields['article_number'] = trim((string) $fields['article_number']);
        }
        if (isset($fields['title'])) {
            $fields['title'] = trim((string) $fields['title']);
        }
        if (isset($fields['unit'])) {
            $fields['unit'] = trim((string) $fields['unit']) ?: 'Stück';
        }
        if (isset($fields['quantity'])) {
            $fields['quantity'] = round((float) $fields['quantity'], 3);
        }
        if (isset($fields['unit_price_gross'])) {
            $fields['unit_price_gross'] = round((float) $fields['unit_price_gross'], 2);
        }
        if (isset($fields['gross_amount'])) {
            $fields['gross_amount'] = round((float) $fields['gross_amount'], 2);
        }
        if (isset($fields['tax_rate'])) {
            $fields['tax_rate'] = (int) $fields['tax_rate'];
        }
        if (isset($fields['article_id'])) {
            $fields['article_id'] = (int) $fields['article_id'];
        }
        CashRegisterTable::update(self::LINES, $lineId, $fields);
    }

    public static function deleteLine(int $lineId): void
    {
        if ($lineId <= 0 || !CashRegisterTable::tableExists(self::LINES)) {
            return;
        }
        Database::pdo()->prepare('DELETE FROM dg_cash_receipt_lines WHERE id = :id')
            ->execute(['id' => $lineId]);
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
