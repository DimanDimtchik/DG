<?php
declare(strict_types=1);

/**
 * K14d — Nachsignatur-Queue nur wenn Provider supportsDeferredSignature() === true.
 * Mock: true · fiskaly: false bis Freigabe.
 */
final class CashTseDeferredSignatureService
{
    public const EVENT_ENQUEUE = 'tse.deferred.enqueue';
    public const EVENT_DONE = 'tse.deferred.done';

    /**
     * Bon in Nachsignatur-Warteschlange (bleibt zahlend, Ausfall-Flag gesetzt).
     */
    public static function enqueue(int $receiptId, string $reason = ''): void
    {
        if ($receiptId <= 0 || !CashReceiptRepository::tableExists()) {
            return;
        }
        if (!CashReceiptRepository::hasDeferredPendingColumn()) {
            throw new RuntimeException('Nachsignatur-Spalte fehlt — Migration 155 ausführen.');
        }
        $receipt = CashReceiptRepository::findById($receiptId);
        if ($receipt === null) {
            return;
        }
        if (($receipt['status'] ?? '') === CashReceiptService::STATUS_FINALIZED
            && trim((string) ($receipt['tse_tx_id'] ?? '')) !== ''
        ) {
            return;
        }

        CashReceiptRepository::updateReceipt($receiptId, [
            'tse_deferred_pending' => 1,
            'tse_outage' => 1,
            'status' => CashReceiptService::STATUS_PAYING,
            'finished_at' => null,
        ]);

        if (CashAuditLogRepository::tableExists()) {
            CashAuditLogRepository::insert([
                'location_id' => (int) ($receipt['location_id'] ?? 0) ?: null,
                'register_id' => (int) ($receipt['register_id'] ?? 0) ?: null,
                'session_id' => (int) ($receipt['session_id'] ?? 0) ?: null,
                'receipt_id' => $receiptId,
                'event_type' => self::EVENT_ENQUEUE,
                'severity' => 'warning',
                'message' => mb_substr(
                    $reason !== '' ? $reason : 'Nachsignatur ausstehend (TSE-Ausfall).',
                    0,
                    500
                ),
                'details_json' => json_encode(['tse_deferred_pending' => true], JSON_UNESCAPED_UNICODE) ?: null,
            ]);
        }
    }

    /**
     * Offene Nachsignaturen (für Job/UI).
     *
     * @return list<array<string, mixed>>
     */
    public static function listPending(?int $registerId = null): array
    {
        if (!CashReceiptRepository::tableExists() || !CashReceiptRepository::hasDeferredPendingColumn()) {
            return [];
        }
        $sql = "SELECT * FROM dg_cash_receipts
                WHERE tse_deferred_pending = 1 AND status = 'paying'";
        $params = [];
        if ($registerId !== null && $registerId > 0) {
            $sql .= ' AND register_id = :rid';
            $params['rid'] = $registerId;
        }
        $sql .= ' ORDER BY id ASC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Eine ausstehende Nachsignatur nachziehen (K14d).
     *
     * @return array<string, mixed> Finish-Ergebnis
     */
    public static function processOne(int $receiptId, ?TseProviderInterface $provider = null): array
    {
        if ($receiptId <= 0) {
            throw new RuntimeException('Bon fehlt.');
        }
        $receipt = CashReceiptRepository::findById($receiptId);
        if ($receipt === null) {
            throw new RuntimeException('Bon nicht gefunden.');
        }
        if (empty($receipt['tse_deferred_pending'])) {
            throw new RuntimeException('Bon ist nicht für Nachsignatur vorgemerkt.');
        }

        $registerId = (int) ($receipt['register_id'] ?? 0);
        $assignment = $registerId > 0
            ? CashTseAssignmentRepository::findByRegisterId($registerId)
            : null;
        $provider ??= TseProviderFactory::fromAssignment(is_array($assignment) ? $assignment : null);
        if (!$provider->supportsDeferredSignature()) {
            throw new RuntimeException(
                'Aktiver TSE-Anbieter erlaubt keine Nachsignatur — Bon manuell laut Betriebsanweisung klären.'
            );
        }

        $fin = CashReceiptService::finalizeWithTse($receiptId, $provider);

        if (CashAuditLogRepository::tableExists()) {
            CashAuditLogRepository::insert([
                'location_id' => (int) ($receipt['location_id'] ?? 0) ?: null,
                'register_id' => $registerId > 0 ? $registerId : null,
                'receipt_id' => $receiptId,
                'event_type' => self::EVENT_DONE,
                'severity' => 'info',
                'message' => 'Nachsignatur abgeschlossen.',
                'details_json' => json_encode([
                    'tse_tx_id' => $fin['tse_tx_id'] ?? null,
                ], JSON_UNESCAPED_UNICODE) ?: null,
            ]);
        }

        if ($registerId > 0 && class_exists('CashTseOutageService')
            && CashTseOutageService::isOutageActive($registerId)
            && self::listPending($registerId) === []
        ) {
            try {
                CashTseOutageService::clearOutage($registerId, null, 'Nachsignatur erledigt — Ausfall beendet.');
            } catch (Throwable) {
                // optional
            }
        }

        return $fin;
    }

    /**
     * Alle offenen Nachsignaturen einer Kasse (oder alle) verarbeiten.
     *
     * @return array{done: int, failed: int, errors: list<string>}
     */
    public static function processPending(?int $registerId = null): array
    {
        $done = 0;
        $failed = 0;
        $errors = [];
        foreach (self::listPending($registerId) as $row) {
            $id = (int) ($row['id'] ?? 0);
            try {
                self::processOne($id);
                $done++;
            } catch (Throwable $e) {
                $failed++;
                $errors[] = '#' . $id . ': ' . $e->getMessage();
            }
        }

        return ['done' => $done, 'failed' => $failed, 'errors' => $errors];
    }
}
