<?php
declare(strict_types=1);

/**
 * Database access for media items and usage tracking (dg_media / dg_media_usage).
 */
final class MediaRepository
{
    /**
     * Ensure media tables exist (idempotent CREATE TABLE IF NOT EXISTS).
     */
    public static function ensureTables(): void
    {
        if (!Database::isConfigured()) {
            return;
        }

        MigrationRunner::runPending();

        $pdo = Database::pdo();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS dg_media (
                media_id VARCHAR(32) NOT NULL,
                original_name VARCHAR(255) NOT NULL DEFAULT \'\',
                stored_name VARCHAR(255) NOT NULL,
                mime_type VARCHAR(64) NOT NULL,
                extension VARCHAR(16) NOT NULL,
                width INT UNSIGNED NULL,
                height INT UNSIGNED NULL,
                size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
                source_note VARCHAR(500) NOT NULL DEFAULT \'\',
                title VARCHAR(255) NOT NULL DEFAULT \'\',
                alt_text VARCHAR(255) NOT NULL DEFAULT \'\',
                uploaded_by INT UNSIGNED NULL,
                uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                status VARCHAR(16) NOT NULL DEFAULT \'active\',
                PRIMARY KEY (media_id),
                KEY idx_media_status_uploaded (status, uploaded_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS dg_media_usage (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                media_id VARCHAR(32) NOT NULL,
                context_key VARCHAR(191) NOT NULL,
                context_label VARCHAR(255) NOT NULL,
                used_from DATETIME NOT NULL,
                used_until DATETIME NULL,
                last_seen_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_media_context (media_id, context_key),
                KEY idx_media_usage_media (media_id),
                KEY idx_media_usage_active (media_id, used_until)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * Active media rows with nested usage lists and public URLs.
     *
     * @return list<array<string, mixed>>
     */
    public static function listWithUsage(): array
    {
        self::ensureTables();
        $pdo = Database::pdo();

        $rows = $pdo->query(
            'SELECT m.*,
                (SELECT COUNT(*) FROM dg_media_usage u WHERE u.media_id = m.media_id AND u.used_until IS NULL) AS active_usage_count,
                (SELECT MIN(u.used_from) FROM dg_media_usage u WHERE u.media_id = m.media_id) AS usage_from,
                (SELECT MAX(COALESCE(u.used_until, u.last_seen_at)) FROM dg_media_usage u WHERE u.media_id = m.media_id) AS usage_until
             FROM dg_media m
             WHERE m.status = \'active\'
             ORDER BY m.uploaded_at DESC'
        )->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $row) {
            $mediaId = (string) $row['media_id'];
            $row['usages'] = self::usageForMedia($mediaId);
            $row['url'] = MediaStorage::publicUrl($mediaId);
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Usage rows for one media id (active and historical).
     *
     * @return list<array<string, mixed>>
     */
    public static function usageForMedia(string $mediaId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT context_key, context_label, used_from, used_until, last_seen_at
             FROM dg_media_usage
             WHERE media_id = :id
             ORDER BY used_from ASC, context_label ASC'
        );
        $stmt->execute(['id' => $mediaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>|null Media row or null
     */
    public static function find(string $mediaId): ?array
    {
        if (!MediaId::isValid($mediaId)) {
            return null;
        }

        self::ensureTables();
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_media WHERE media_id = :id AND status = \'active\' LIMIT 1');
        $stmt->execute(['id' => $mediaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['url'] = MediaStorage::publicUrl($mediaId);
        $row['usages'] = self::usageForMedia($mediaId);

        return $row;
    }

    /**
     * Insert a new active media row.
     *
     * @param array<string, mixed> $data File + meta fields from MediaStorage::storeUpload / storeBinary
     */
    public static function insert(string $mediaId, array $data, ?int $uploadedBy): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_media (
                media_id, original_name, stored_name, mime_type, extension,
                width, height, size_bytes, source_note, title, alt_text, uploaded_by
             ) VALUES (
                :media_id, :original_name, :stored_name, :mime_type, :extension,
                :width, :height, :size_bytes, :source_note, :title, :alt_text, :uploaded_by
             )'
        );

        $stmt->execute([
            'media_id' => $mediaId,
            'original_name' => (string) ($data['original_name'] ?? ''),
            'stored_name' => (string) ($data['stored_name'] ?? ''),
            'mime_type' => (string) ($data['mime_type'] ?? ''),
            'extension' => (string) ($data['extension'] ?? ''),
            'width' => $data['width'] ?? null,
            'height' => $data['height'] ?? null,
            'size_bytes' => (int) ($data['size_bytes'] ?? 0),
            'source_note' => (string) ($data['source_note'] ?? ''),
            'title' => (string) ($data['title'] ?? ''),
            'alt_text' => (string) ($data['alt_text'] ?? ''),
            'uploaded_by' => $uploadedBy,
        ]);
    }

    /**
     * Update stored file metadata after rewrite / transform.
     *
     * @param array<string, mixed> $data stored_name, mime_type, extension, width, height, size_bytes
     */
    public static function updateFileMeta(string $mediaId, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE dg_media SET
                stored_name = :stored_name,
                mime_type = :mime_type,
                extension = :extension,
                width = :width,
                height = :height,
                size_bytes = :size_bytes,
                updated_at = NOW()
             WHERE media_id = :media_id'
        );

        $stmt->execute([
            'media_id' => $mediaId,
            'stored_name' => (string) ($data['stored_name'] ?? ''),
            'mime_type' => (string) ($data['mime_type'] ?? ''),
            'extension' => (string) ($data['extension'] ?? ''),
            'width' => $data['width'] ?? null,
            'height' => $data['height'] ?? null,
            'size_bytes' => (int) ($data['size_bytes'] ?? 0),
        ]);
    }

    /**
     * Update editorial metadata (title, alt, source note).
     */
    public static function updateMetadata(string $mediaId, string $sourceNote, string $title, string $altText): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE dg_media SET source_note = :source_note, title = :title, alt_text = :alt_text, updated_at = NOW()
             WHERE media_id = :media_id'
        );
        $stmt->execute([
            'media_id' => $mediaId,
            'source_note' => $sourceNote,
            'title' => $title,
            'alt_text' => $altText,
        ]);
    }

    /**
     * Count active (used_until IS NULL) usage rows.
     */
    public static function activeUsageCount(string $mediaId): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM dg_media_usage WHERE media_id = :id AND used_until IS NULL'
        );
        $stmt->execute(['id' => $mediaId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Whether any usage row exists (active or closed).
     */
    public static function hasAnyUsage(string $mediaId): bool
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM dg_media_usage WHERE media_id = :id');
        $stmt->execute(['id' => $mediaId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Delete usage + media row and remove files from disk.
     */
    public static function delete(string $mediaId): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM dg_media_usage WHERE media_id = :id')->execute(['id' => $mediaId]);
        $pdo->prepare('DELETE FROM dg_media WHERE media_id = :id')->execute(['id' => $mediaId]);
        MediaStorage::deleteMediaFiles($mediaId);
    }

    /**
     * Upsert an active usage reference for a context key.
     */
    public static function syncUsage(string $mediaId, string $contextKey, string $contextLabel): void
    {
        $now = date('Y-m-d H:i:s');
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_media_usage (media_id, context_key, context_label, used_from, used_until, last_seen_at)
             VALUES (:media_id, :context_key, :context_label, :used_from, NULL, :last_seen_at)
             ON DUPLICATE KEY UPDATE
                context_label = VALUES(context_label),
                used_until = NULL,
                last_seen_at = VALUES(last_seen_at)'
        );
        $stmt->execute([
            'media_id' => $mediaId,
            'context_key' => $contextKey,
            'context_label' => $contextLabel,
            'used_from' => $now,
            'last_seen_at' => $now,
        ]);
    }

    /**
     * Mark a previously active usage as ended (used_until = now).
     */
    public static function closeStaleUsage(string $mediaId, string $contextKey): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE dg_media_usage SET used_until = NOW()
             WHERE media_id = :media_id AND context_key = :context_key AND used_until IS NULL'
        );
        $stmt->execute(['media_id' => $mediaId, 'context_key' => $contextKey]);
    }
}
