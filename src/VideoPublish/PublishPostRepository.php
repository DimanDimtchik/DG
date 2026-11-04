<?php
declare(strict_types=1);

final class PublishPostRepository
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
            Database::pdo()->query('SELECT 1 FROM dg_publish_posts LIMIT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByJob(int $jobId): ?array
    {
        self::ensureReady();
        if (!self::tableReady() || $jobId < 1) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_publish_posts WHERE job_id = :id LIMIT 1');
        $stmt->execute(['id' => $jobId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByExternal(string $platform, string $externalId): ?array
    {
        self::ensureReady();
        if (!self::tableReady() || $externalId === '') {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_publish_posts WHERE platform = :p AND external_id = :e LIMIT 1'
        );
        $stmt->execute(['p' => $platform, 'e' => $externalId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function upsertFromJob(int $jobId, array $data): int
    {
        self::ensureReady();
        if (!self::tableReady()) {
            throw new RuntimeException('Publish-Posts-Tabelle fehlt — Migration 138 ausführen.');
        }
        $existing = self::findByJob($jobId);
        $params = [
            'job_id' => $jobId,
            'platform' => (string) ($data['platform'] ?? 'youtube'),
            'external_id' => mb_substr((string) ($data['external_id'] ?? ''), 0, 191),
            'title' => mb_substr((string) ($data['title'] ?? ''), 0, 255),
            'description' => (string) ($data['description'] ?? ''),
            'privacy' => mb_substr((string) ($data['privacy'] ?? 'private'), 0, 32),
            'published_at' => $data['published_at'] ?? date('Y-m-d H:i:s'),
            'editable_flags' => mb_substr(
                (string) ($data['editable_flags'] ?? 'title,description,privacy,delete'),
                0,
                191
            ),
        ];
        if ($existing === null) {
            $stmt = Database::pdo()->prepare(
                'INSERT INTO dg_publish_posts (
                    job_id, platform, external_id, title, description, privacy, published_at, editable_flags
                 ) VALUES (
                    :job_id, :platform, :external_id, :title, :description, :privacy, :published_at, :editable_flags
                 )'
            );
            $stmt->execute($params);

            return (int) Database::pdo()->lastInsertId();
        }
        $params['id'] = (int) $existing['id'];
        unset($params['job_id']);
        Database::pdo()->prepare(
            'UPDATE dg_publish_posts SET
                platform = :platform,
                external_id = :external_id,
                title = :title,
                description = :description,
                privacy = :privacy,
                published_at = :published_at,
                editable_flags = :editable_flags
             WHERE id = :id'
        )->execute($params);

        return (int) $existing['id'];
    }

    /**
     * @param array<string, mixed> $fields
     */
    public static function update(int $id, array $fields): void
    {
        $allowed = ['title', 'description', 'privacy', 'external_id', 'editable_flags'];
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
            'UPDATE dg_publish_posts SET ' . implode(', ', $sets) . ' WHERE id = :id'
        )->execute($params);
    }

    public static function delete(int $id): void
    {
        if ($id < 1 || !self::tableReady()) {
            return;
        }
        Database::pdo()->prepare('DELETE FROM dg_publish_posts WHERE id = :id')->execute(['id' => $id]);
    }
}
