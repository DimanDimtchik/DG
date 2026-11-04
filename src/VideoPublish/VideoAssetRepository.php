<?php
declare(strict_types=1);

final class VideoAssetRepository
{
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
            Database::pdo()->query('SELECT 1 FROM dg_video_assets LIMIT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listAll(int $limit = 100): array
    {
        self::ensureReady();
        if (!self::tableReady()) {
            return [];
        }
        $limit = max(1, min(500, $limit));
        $stmt = Database::pdo()->query(
            'SELECT * FROM dg_video_assets ORDER BY id DESC LIMIT ' . $limit
        );
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
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
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_video_assets WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array{stored_name: string, original_name: string, mime_type: string, size_bytes: int, sha256: string} $meta
     */
    public static function create(array $meta, ?int $userId): int
    {
        self::ensureReady();
        if (!self::tableReady()) {
            throw new RuntimeException('Video-Tabelle fehlt — Migration 138 ausführen.');
        }
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_video_assets (stored_name, original_name, mime_type, size_bytes, sha256, created_by)
             VALUES (:stored_name, :original_name, :mime_type, :size_bytes, :sha256, :created_by)'
        );
        $stmt->execute([
            'stored_name' => (string) $meta['stored_name'],
            'original_name' => (string) $meta['original_name'],
            'mime_type' => (string) $meta['mime_type'],
            'size_bytes' => (int) $meta['size_bytes'],
            'sha256' => (string) $meta['sha256'],
            'created_by' => ($userId !== null && $userId > 0) ? $userId : null,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public static function delete(int $id): void
    {
        $row = self::find($id);
        if ($row === null) {
            return;
        }
        VideoAssetStorage::deleteFile((string) ($row['stored_name'] ?? ''));
        Database::pdo()->prepare('DELETE FROM dg_video_assets WHERE id = :id')->execute(['id' => $id]);
    }
}
