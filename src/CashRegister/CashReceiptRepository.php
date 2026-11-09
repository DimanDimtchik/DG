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

    /** K14d — Spalte tse_deferred_pending nach Migration 155. */
    public static function hasDeferredPendingColumn(): bool
    {
        if (!self::tableExists() || !Database::isConfigured()) {
            return false;
        }
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $stmt = Database::pdo()->query("SHOW COLUMNS FROM dg_cash_receipts LIKE 'tse_deferred_pending'");
            $cached = $stmt !== false && $stmt->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (Throwable) {
            $cached = false;
        }

        return $cached;
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
     * Vorhandener Storno-Bon zum Original (K13a/c) — egal welcher Status außer cancelled.
     *
     * @return array<string, mixed>|null
     */
    public static function findStornoOf(int $originalReceiptId): ?array
    {
        if ($originalReceiptId <= 0 || !self::tableExists()) {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM dg_cash_receipts
             WHERE storno_of_receipt_id = :oid
               AND receipt_type = 'storno'
               AND status <> 'cancelled'
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->execute(['oid' => $originalReceiptId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** Offene Bons einer Sitzung (draft/paying) — Guard für K11c. */
    public static function countUnfinishedBySession(int $sessionId): int
    {
        if ($sessionId <= 0 || !self::tableExists()) {
            return 0;
        }
        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM dg_cash_receipts
             WHERE session_id = :sid AND status IN ('draft', 'paying')"
        );
        $stmt->execute(['sid' => $sessionId]);

        return (int) $stmt->fetchColumn();
    }

    /** Offene Bons (draft/paying) für Kasse + Geschäftstag (über Sitzung). */
    public static function countUnfinishedByRegisterDate(int $registerId, string $businessDate): int
    {
        if ($registerId <= 0 || $businessDate === '' || !self::tableExists() || !CashSessionRepository::tableExists()) {
            return 0;
        }
        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM dg_cash_receipts r
             INNER JOIN dg_cash_sessions s ON s.id = r.session_id
             WHERE r.register_id = :rid
               AND s.business_date = :d
               AND r.status IN ('draft', 'paying')"
        );
        $stmt->execute(['rid' => $registerId, 'd' => $businessDate]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Aggregat finalized Bons + Zahlarten für Tagesabschluss (K12a).
     *
     * @return array{
     *   receipt_count: int,
     *   total_gross: float,
     *   total_cash: float,
     *   total_ec: float,
     *   total_card: float,
     *   total_voucher: float
     * }
     */
    public static function dayTotalsByRegisterDate(int $registerId, string $businessDate): array
    {
        $empty = [
            'receipt_count' => 0,
            'total_gross' => 0.0,
            'total_cash' => 0.0,
            'total_ec' => 0.0,
            'total_card' => 0.0,
            'total_voucher' => 0.0,
        ];
        if ($registerId <= 0 || $businessDate === '' || !self::tableExists() || !CashSessionRepository::tableExists()) {
            return $empty;
        }

        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(r.total_gross), 0) AS gross
             FROM dg_cash_receipts r
             INNER JOIN dg_cash_sessions s ON s.id = r.session_id
             WHERE r.register_id = :rid
               AND s.business_date = :d
               AND r.status = 'finalized'"
        );
        $stmt->execute(['rid' => $registerId, 'd' => $businessDate]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $empty['receipt_count'] = (int) ($row['cnt'] ?? 0);
        $empty['total_gross'] = round((float) ($row['gross'] ?? 0), 2);

        if (!CashPaymentRepository::tableExists()) {
            return $empty;
        }

        $pay = Database::pdo()->prepare(
            "SELECT p.payment_method AS method, COALESCE(SUM(p.amount), 0) AS total
             FROM dg_cash_payments p
             INNER JOIN dg_cash_receipts r ON r.id = p.receipt_id
             INNER JOIN dg_cash_sessions s ON s.id = r.session_id
             WHERE r.register_id = :rid
               AND s.business_date = :d
               AND r.status = 'finalized'
             GROUP BY p.payment_method"
        );
        $pay->execute(['rid' => $registerId, 'd' => $businessDate]);
        while ($pRow = $pay->fetch(PDO::FETCH_ASSOC)) {
            $method = strtolower(trim((string) ($pRow['method'] ?? '')));
            $amount = round((float) ($pRow['total'] ?? 0), 2);
            if ($method === 'cash') {
                $empty['total_cash'] = $amount;
            } elseif ($method === 'ec') {
                $empty['total_ec'] = $amount;
            } elseif ($method === 'card') {
                $empty['total_card'] = $amount;
            } elseif ($method === 'voucher') {
                $empty['total_voucher'] = $amount;
            }
        }

        return $empty;
    }

    /**
     * Neueste Bons (K7a Liste).
     *
     * @return list<array<string, mixed>>
     */
    public static function listRecent(int $limit = 50, ?int $registerId = null): array
    {
        return self::paginateRecent(1, 'all', $registerId, max(1, min(200, $limit)))['items'];
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int, per_page_key: string}
     */
    public static function paginateRecent(
        int $page = 1,
        string $perPageKey = ListPagination::DEFAULT,
        ?int $registerId = null,
        int $hardCap = 10000
    ): array {
        $perPageKey = ListPagination::parsePerPageKey($perPageKey);
        if (!self::tableExists()) {
            return ListPagination::emptyResult($perPageKey);
        }
        $params = [];
        $where = '';
        if ($registerId !== null && $registerId > 0) {
            $where = ' WHERE r.register_id = :rid';
            $params['rid'] = $registerId;
        }
        $countSql = 'SELECT COUNT(*) FROM dg_cash_receipts r' . $where;
        $countStmt = $params === []
            ? Database::pdo()->query($countSql)
            : Database::pdo()->prepare($countSql);
        if ($countStmt === false) {
            return ListPagination::emptyResult($perPageKey);
        }
        if ($params !== []) {
            $countStmt->execute($params);
        }
        $total = min((int) $countStmt->fetchColumn(), max(1, $hardCap));
        $meta = ListPagination::sliceMeta($total, $page, $perPageKey);
        $sql = 'SELECT r.*,
                       reg.code AS register_code, reg.name AS register_name,
                       l.code AS location_code, l.name AS location_name
                FROM dg_cash_receipts r
                LEFT JOIN dg_cash_registers reg ON reg.id = r.register_id
                LEFT JOIN dg_cash_locations l ON l.id = r.location_id'
            . $where
            . ' ORDER BY r.id DESC LIMIT ' . ($meta['show_all'] ? max(1, $hardCap) : $meta['limit'])
            . ($meta['show_all'] ? '' : (' OFFSET ' . $meta['offset']));
        $stmt = $params === []
            ? Database::pdo()->query($sql)
            : Database::pdo()->prepare($sql);
        if ($stmt === false) {
            return ListPagination::emptyResult($perPageKey);
        }
        if ($params !== []) {
            $stmt->execute($params);
        }
        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $row;
        }

        return ListPagination::result($rows, $total, $meta);
    }

    /**
     * Unveränderbares Bon-Journal (K16a): nur festgeschriebene Bons, Filter Kasse/Geschäftstag.
     *
     * @return list<array<string, mixed>>
     */
    public static function listJournal(
        ?int $registerId = null,
        ?string $businessDate = null,
        int $limit = 200
    ): array {
        return self::paginateJournal(1, 'all', $registerId, $businessDate, max(1, min(500, $limit)))['items'];
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int, per_page_key: string}
     */
    public static function paginateJournal(
        int $page = 1,
        string $perPageKey = ListPagination::DEFAULT,
        ?int $registerId = null,
        ?string $businessDate = null,
        int $hardCap = 10000
    ): array {
        $perPageKey = ListPagination::parsePerPageKey($perPageKey);
        if (!self::tableExists()) {
            return ListPagination::emptyResult($perPageKey);
        }
        $where = ' WHERE r.status = \'finalized\'';
        $params = [];
        if ($registerId !== null && $registerId > 0) {
            $where .= ' AND r.register_id = :rid';
            $params['rid'] = $registerId;
        }
        $date = trim((string) $businessDate);
        if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            $where .= ' AND s.business_date = :bd';
            $params['bd'] = $date;
        }
        $countSql = 'SELECT COUNT(*) FROM dg_cash_receipts r
                LEFT JOIN dg_cash_sessions s ON s.id = r.session_id' . $where;
        $countStmt = $params === []
            ? Database::pdo()->query($countSql)
            : Database::pdo()->prepare($countSql);
        if ($countStmt === false) {
            return ListPagination::emptyResult($perPageKey);
        }
        if ($params !== []) {
            $countStmt->execute($params);
        }
        $total = min((int) $countStmt->fetchColumn(), max(1, $hardCap));
        $meta = ListPagination::sliceMeta($total, $page, $perPageKey);
        $sql = 'SELECT r.*,
                       reg.code AS register_code, reg.name AS register_name,
                       l.code AS location_code, l.name AS location_name,
                       s.business_date AS business_date
                FROM dg_cash_receipts r
                LEFT JOIN dg_cash_registers reg ON reg.id = r.register_id
                LEFT JOIN dg_cash_locations l ON l.id = r.location_id
                LEFT JOIN dg_cash_sessions s ON s.id = r.session_id'
            . $where
            . ' ORDER BY s.business_date DESC, r.finished_at DESC, r.id DESC LIMIT '
            . ($meta['show_all'] ? max(1, $hardCap) : $meta['limit'])
            . ($meta['show_all'] ? '' : (' OFFSET ' . $meta['offset']));
        $stmt = $params === []
            ? Database::pdo()->query($sql)
            : Database::pdo()->prepare($sql);
        if ($stmt === false) {
            return ListPagination::emptyResult($perPageKey);
        }
        if ($params !== []) {
            $stmt->execute($params);
        }
        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $row;
        }

        return ListPagination::result($rows, $total, $meta);
    }

    /**
     * Festgeschriebene Bons im Zeitraum (K16b CSV).
     *
     * @return list<array<string, mixed>>
     */
    public static function listJournalRange(
        ?int $registerId,
        string $dateFrom,
        string $dateTo,
        int $limit = 5000
    ): array {
        if (!self::tableExists()) {
            return [];
        }
        $from = trim($dateFrom);
        $to = trim($dateTo);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) !== 1 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) !== 1) {
            throw new InvalidArgumentException('Zeitraum ungültig — bitte Von/Bis als Datum angeben.');
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $limit = max(1, min(10000, $limit));
        $sql = 'SELECT r.*,
                       reg.code AS register_code, reg.name AS register_name,
                       l.code AS location_code, l.name AS location_name,
                       s.business_date AS business_date
                FROM dg_cash_receipts r
                LEFT JOIN dg_cash_registers reg ON reg.id = r.register_id
                LEFT JOIN dg_cash_locations l ON l.id = r.location_id
                LEFT JOIN dg_cash_sessions s ON s.id = r.session_id
                WHERE r.status = \'finalized\'
                  AND s.business_date BETWEEN :df AND :dt';
        $params = ['df' => $from, 'dt' => $to];
        if ($registerId !== null && $registerId > 0) {
            $sql .= ' AND r.register_id = :rid';
            $params['rid'] = $registerId;
        }
        $sql .= ' ORDER BY s.business_date ASC, r.finished_at ASC, r.id ASC LIMIT ' . $limit;
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
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
            'tse_outage' => !empty($data['tse_outage']) ? 1 : 0,
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
