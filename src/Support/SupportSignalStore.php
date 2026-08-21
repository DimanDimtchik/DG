<?php
declare(strict_types=1);

/**
 * WebRTC-Signaling-Nachrichten für Support-Bildschirmfreigabe.
 */
final class SupportSignalStore
{
    public static function ensureTables(): void
    {
        SupportAccessService::ensureTables();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function push(int $accessId, string $direction, array $payload): void
    {
        self::ensureTables();
        $direction = $direction === 'support_to_customer' ? 'support_to_customer' : 'customer_to_support';
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new InvalidArgumentException('Ungültige Signaling-Daten.');
        }
        Database::pdo()->prepare(
            'INSERT INTO dg_support_signals (access_id, direction, payload_json)
             VALUES (:aid, :dir, :payload)'
        )->execute([
            'aid' => $accessId,
            'dir' => $direction,
            'payload' => $json,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function pull(int $accessId, string $direction, int $afterId = 0): array
    {
        self::ensureTables();
        $direction = $direction === 'support_to_customer' ? 'support_to_customer' : 'customer_to_support';
        $stmt = Database::pdo()->prepare(
            'SELECT id, payload_json, created_at FROM dg_support_signals
             WHERE access_id = :aid AND direction = :dir AND id > :after AND consumed_at IS NULL
             ORDER BY id ASC LIMIT 50'
        );
        $stmt->execute([
            'aid' => $accessId,
            'dir' => $direction,
            'after' => $afterId,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int) $row['id'];
            $payload = json_decode((string) $row['payload_json'], true);
            $out[] = [
                'id' => (int) $row['id'],
                'payload' => is_array($payload) ? $payload : [],
                'created_at' => $row['created_at'],
            ];
        }
        if ($ids !== []) {
            $in = implode(',', $ids);
            Database::pdo()->exec(
                "UPDATE dg_support_signals SET consumed_at = NOW() WHERE id IN ($in)"
            );
        }

        return $out;
    }

    public static function clearForAccess(int $accessId): void
    {
        self::ensureTables();
        Database::pdo()->prepare('DELETE FROM dg_support_signals WHERE access_id = :id')->execute(['id' => $accessId]);
    }
}
