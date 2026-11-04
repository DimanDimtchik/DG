<?php
declare(strict_types=1);

final class PublishJobRepository
{
    public const STATE_PENDING = 'pending';
    public const STATE_UPLOADING = 'uploading';
    public const STATE_PUBLISHED = 'published';
    public const STATE_FAILED = 'failed';

    public const PLATFORM_YOUTUBE = 'youtube';
    public const PLATFORM_LINKEDIN = 'linkedin';

    public static function ensureReady(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
    }

    public static function tableReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            Database::pdo()->query('SELECT 1 FROM dg_publish_jobs LIMIT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        self::ensureReady();
        if (!self::tableReady() || $id < 1) {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT j.*, a.stored_name, a.original_name, a.mime_type, a.size_bytes
             FROM dg_publish_jobs j
             INNER JOIN dg_video_assets a ON a.id = j.asset_id
             WHERE j.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listForAsset(int $assetId): array
    {
        self::ensureReady();
        if (!self::tableReady() || $assetId < 1) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_publish_jobs WHERE asset_id = :a ORDER BY id DESC'
        );
        $stmt->execute(['a' => $assetId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listActive(int $limit = 20): array
    {
        self::ensureReady();
        if (!self::tableReady()) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $stmt = Database::pdo()->query(
            "SELECT j.*, a.stored_name, a.original_name, a.mime_type, a.size_bytes
             FROM dg_publish_jobs j
             INNER JOIN dg_video_assets a ON a.id = j.asset_id
             WHERE j.state IN ('pending','uploading')
             ORDER BY j.id ASC
             LIMIT " . $limit
        );
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function create(int $assetId, string $platform, array $payload, ?int $userId): int
    {
        self::ensureReady();
        if (!self::tableReady()) {
            throw new RuntimeException('Publish-Jobs-Tabelle fehlt — Migration 138 ausführen.');
        }
        if (VideoAssetRepository::find($assetId) === null) {
            throw new InvalidArgumentException('Video nicht gefunden.');
        }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_publish_jobs (
                asset_id, platform, state, attempts, last_error, payload_json, created_by
             ) VALUES (
                :asset_id, :platform, :state, 0, \'\', :payload_json, :created_by
             )'
        );
        $stmt->execute([
            'asset_id' => $assetId,
            'platform' => $platform,
            'state' => self::STATE_PENDING,
            'payload_json' => is_string($json) ? $json : '{}',
            'created_by' => ($userId !== null && $userId > 0) ? $userId : null,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $fields
     */
    public static function update(int $id, array $fields): void
    {
        $row = self::find($id);
        if ($row === null) {
            throw new InvalidArgumentException('Job nicht gefunden.');
        }
        $allowed = [
            'state', 'attempts', 'last_error', 'payload_json', 'upload_session_url',
            'upload_offset', 'external_id',
        ];
        $sets = [];
        $params = ['id' => $id];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $fields)) {
                continue;
            }
            $sets[] = $key . ' = :' . $key;
            $params[$key] = $fields[$key];
        }
        if ($sets === []) {
            return;
        }
        Database::pdo()->prepare(
            'UPDATE dg_publish_jobs SET ' . implode(', ', $sets) . ' WHERE id = :id'
        )->execute($params);
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload(array $job): array
    {
        $raw = (string) ($job['payload_json'] ?? '');
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, string>
     */
    public static function stateOptions(): array
    {
        return [
            self::STATE_PENDING => 'Wartend',
            self::STATE_UPLOADING => 'Wird hochgeladen',
            self::STATE_PUBLISHED => 'Veröffentlicht',
            self::STATE_FAILED => 'Fehlgeschlagen',
        ];
    }
}
