<?php
declare(strict_types=1);

/**
 * TSE-Ausfall: Flag setzen + Audit-Log (K14a).
 * UI-Warnung: K14b · Nachsignatur nur bei supportsDeferredSignature: K14c/d.
 */
final class CashTseOutageService
{
    public const EVENT_OUTAGE = 'tse.outage';
    public const EVENT_CLEARED = 'tse.outage.cleared';

    /**
     * Ausfall protokollieren: Kassen-Flag + optional Bon-Flag + Audit.
     *
     * @param array{
     *   register_id: int,
     *   message?: string,
     *   receipt_id?: int|null,
     *   session_id?: int|null,
     *   user_id?: int|null,
     *   details?: array<string, mixed>|null,
     *   ip?: string
     * } $input
     * @return int audit log id
     */
    public static function recordOutage(array $input): int
    {
        $registerId = (int) ($input['register_id'] ?? 0);
        if ($registerId <= 0) {
            throw new RuntimeException('Kasse für TSE-Ausfall fehlt.');
        }
        if (!CashAuditLogRepository::tableExists()) {
            throw new RuntimeException('Audit-Log fehlt — bitte Migrationen ausführen.');
        }

        $register = CashRegisterRepository::findById($registerId);
        if ($register === null) {
            throw new RuntimeException('Kasse nicht gefunden.');
        }
        $locationId = (int) ($register['location_id'] ?? 0);
        $receiptId = isset($input['receipt_id']) ? max(0, (int) $input['receipt_id']) : 0;
        $sessionId = isset($input['session_id']) ? max(0, (int) $input['session_id']) : 0;
        $userId = isset($input['user_id']) ? max(0, (int) $input['user_id']) : 0;
        $message = trim((string) ($input['message'] ?? ''));
        if ($message === '') {
            $message = 'TSE-Ausfall gemeldet.';
        }
        $message = mb_substr($message, 0, 500);

        $now = date('Y-m-d H:i:s');
        $assignment = CashTseAssignmentRepository::findByRegisterId($registerId);
        if ($assignment === null) {
            throw new RuntimeException('Keine TSE-Zuordnung — Ausfall-Flag nicht setzbar.');
        }
        CashTseAssignmentRepository::update((int) $assignment['id'], [
            'outage_active' => 1,
            'outage_since' => !empty($assignment['outage_active'])
                ? ($assignment['outage_since'] ?? $now)
                : $now,
            'last_error' => $message,
            'last_state' => 'OUTAGE',
            'last_checked_at' => $now,
        ]);

        if ($receiptId > 0 && CashReceiptRepository::tableExists()) {
            $receipt = CashReceiptRepository::findById($receiptId);
            if ($receipt === null || (int) ($receipt['register_id'] ?? 0) !== $registerId) {
                throw new RuntimeException('Bon gehört nicht zu dieser Kasse.');
            }
            CashReceiptRepository::updateReceipt($receiptId, ['tse_outage' => 1]);
            if ($sessionId <= 0) {
                $sessionId = (int) ($receipt['session_id'] ?? 0);
            }
            if ($locationId <= 0) {
                $locationId = (int) ($receipt['location_id'] ?? 0);
            }
        }

        $details = $input['details'] ?? null;
        if (!is_array($details)) {
            $details = [];
        }
        $details['outage_active'] = true;
        $details['provider'] = (string) ($assignment['provider'] ?? '');
        $details['supports_deferred_signature'] = TseProviderFactory::fromAssignment($assignment)
            ->supportsDeferredSignature();
        $detailsJson = json_encode($details, JSON_UNESCAPED_UNICODE);

        return CashAuditLogRepository::insert([
            'location_id' => $locationId > 0 ? $locationId : null,
            'register_id' => $registerId,
            'session_id' => $sessionId > 0 ? $sessionId : null,
            'receipt_id' => $receiptId > 0 ? $receiptId : null,
            'user_id' => $userId > 0 ? $userId : null,
            'event_type' => self::EVENT_OUTAGE,
            'severity' => 'error',
            'message' => $message,
            'details_json' => $detailsJson === false ? null : $detailsJson,
            'ip' => trim((string) ($input['ip'] ?? '')),
        ]);
    }

    /** Aktiver Ausfall an der Kasse (Zuordnung)? */
    public static function isOutageActive(int $registerId): bool
    {
        if ($registerId <= 0) {
            return false;
        }
        $assignment = CashTseAssignmentRepository::findByRegisterId($registerId);
        if ($assignment === null) {
            return false;
        }

        return !empty($assignment['outage_active']);
    }

    /**
     * Alle Kassen mit aktivem TSE-Ausfall (UI K14b).
     *
     * @return list<array<string, mixed>>
     */
    public static function listActiveOutages(): array
    {
        if (!CashTseAssignmentRepository::tableExists() || !CashRegisterRepository::tableExists()) {
            return [];
        }
        $stmt = Database::pdo()->query(
            'SELECT a.register_id, a.outage_since, a.last_error, a.provider, a.last_state,
                    r.code AS register_code, r.name AS register_name,
                    l.code AS location_code, l.name AS location_name
             FROM dg_cash_tse_assignments a
             INNER JOIN dg_cash_registers r ON r.id = a.register_id
             LEFT JOIN dg_cash_locations l ON l.id = r.location_id
             WHERE a.outage_active = 1
             ORDER BY a.outage_since ASC, r.id ASC'
        );
        if ($stmt === false) {
            return [];
        }
        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Ausfall-Flag an der Kasse zurücksetzen (TSE wieder erreichbar) + Audit.
     *
     * @return int audit log id
     */
    public static function clearOutage(int $registerId, ?int $userId = null, string $message = ''): int
    {
        if ($registerId <= 0) {
            throw new RuntimeException('Kasse fehlt.');
        }
        $register = CashRegisterRepository::findById($registerId);
        if ($register === null) {
            throw new RuntimeException('Kasse nicht gefunden.');
        }
        $assignment = CashTseAssignmentRepository::findByRegisterId($registerId);
        if ($assignment === null) {
            throw new RuntimeException('Keine TSE-Zuordnung für diese Kasse.');
        }

        CashTseAssignmentRepository::update((int) $assignment['id'], [
            'outage_active' => 0,
            'outage_since' => null,
            'last_state' => 'RECOVERED',
            'last_error' => '',
            'last_checked_at' => date('Y-m-d H:i:s'),
        ]);

        $msg = trim($message);
        if ($msg === '') {
            $msg = 'TSE-Ausfall beendet — Verbindung wieder da.';
        }

        return CashAuditLogRepository::insert([
            'location_id' => (int) ($register['location_id'] ?? 0) ?: null,
            'register_id' => $registerId,
            'user_id' => $userId !== null && $userId > 0 ? $userId : null,
            'event_type' => self::EVENT_CLEARED,
            'severity' => 'info',
            'message' => mb_substr($msg, 0, 500),
            'details_json' => json_encode(['outage_active' => false], JSON_UNESCAPED_UNICODE) ?: null,
        ]);
    }
}
