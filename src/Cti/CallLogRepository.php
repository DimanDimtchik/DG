<?php
declare(strict_types=1);

final class CallLogRepository
{
    public static function ensureReady(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
    }

    /** @return list<array<string, mixed>> */
    public static function recent(int $limit = 50): array
    {
        self::ensureReady();
        if (!Database::isConfigured()) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $stmt = Database::pdo()->prepare(
            'SELECT c.*, ct.display_name AS contact_name
             FROM dg_call_log c
             LEFT JOIN dg_contacts ct ON ct.id = c.contact_id
             ORDER BY c.started_at DESC, c.id DESC
             LIMIT ' . $limit
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public static function activeRinging(int $maxAgeSec = 120): array
    {
        self::ensureReady();
        if (!Database::isConfigured()) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT c.*, ct.display_name AS contact_name
             FROM dg_call_log c
             LEFT JOIN dg_contacts ct ON ct.id = c.contact_id
             WHERE c.direction = \'in\' AND c.status = \'ringing\'
               AND c.started_at >= (NOW() - INTERVAL ? SECOND)
             ORDER BY c.started_at DESC
             LIMIT 5'
        );
        $stmt->execute([max(30, min(600, $maxAgeSec))]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function findOpenByExternal(string $externalId): ?array
    {
        self::ensureReady();
        $externalId = trim($externalId);
        if ($externalId === '' || !Database::isConfigured()) {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_call_log
             WHERE external_id = ?
               AND status IN (\'ringing\', \'dialing\', \'answered\')
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$externalId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array{direction: string, status?: string, phone_raw: string, phone_e164?: string, contact_id?: ?int, user_id?: ?int, external_id?: string} $data
     */
    public static function insert(array $data): int
    {
        self::ensureReady();
        $phoneRaw = trim((string) ($data['phone_raw'] ?? ''));
        $e164 = trim((string) ($data['phone_e164'] ?? ''));
        if ($e164 === '' && $phoneRaw !== '') {
            $e164 = PhoneNormalizer::toE164($phoneRaw) ?? PhoneNormalizer::normalize($phoneRaw);
        }
        $contactId = isset($data['contact_id']) ? (int) $data['contact_id'] : CtiPhoneLookup::contactIdForPhone($phoneRaw !== '' ? $phoneRaw : $e164);
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_call_log
             (direction, status, phone_e164, phone_raw, contact_id, user_id, external_id, started_at)
             VALUES (:direction, :status, :phone_e164, :phone_raw, :contact_id, :user_id, :external_id, NOW())'
        );
        $stmt->execute([
            'direction' => ($data['direction'] ?? 'in') === 'out' ? 'out' : 'in',
            'status' => (string) ($data['status'] ?? 'ringing'),
            'phone_e164' => $e164,
            'phone_raw' => $phoneRaw !== '' ? $phoneRaw : $e164,
            'contact_id' => $contactId > 0 ? $contactId : null,
            'user_id' => isset($data['user_id']) && (int) $data['user_id'] > 0 ? (int) $data['user_id'] : null,
            'external_id' => trim((string) ($data['external_id'] ?? '')),
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public static function markAnswered(int $id): void
    {
        if ($id <= 0 || !Database::isConfigured()) {
            return;
        }
        Database::pdo()->prepare(
            'UPDATE dg_call_log SET status = \'answered\', answered_at = COALESCE(answered_at, NOW()) WHERE id = ?'
        )->execute([$id]);
    }

    public static function markEnded(int $id, int $durationSec = 0, string $fallbackStatus = 'ended'): void
    {
        if ($id <= 0 || !Database::isConfigured()) {
            return;
        }
        $row = self::findById($id);
        $status = $fallbackStatus;
        if ($row && ($row['status'] ?? '') === 'ringing' && ($row['direction'] ?? '') === 'in') {
            $status = 'missed';
        }
        Database::pdo()->prepare(
            'UPDATE dg_call_log
             SET status = ?, ended_at = NOW(), duration_sec = ?
             WHERE id = ?'
        )->execute([$status, max(0, $durationSec), $id]);
    }

    public static function findById(int $id): ?array
    {
        if ($id <= 0 || !Database::isConfigured()) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_call_log WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
