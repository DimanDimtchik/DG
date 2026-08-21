<?php
declare(strict_types=1);

/**
 * Stores and lists website form submissions (inbox).
 */
final class WebsiteFormSubmissionRepository
{
    /**
     * @param array<string, mixed> $payload
     * @param list<array<string, mixed>> $files
     */
    public static function create(int $formId, array $payload, array $files, string $ip, string $userAgent): int
    {
        WebsiteFormRepository::ensureTables();
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO dg_website_form_submissions (form_id, payload_json, files_json, ip, user_agent, is_read)
             VALUES (:form_id, :payload_json, :files_json, :ip, :ua, 0)'
        );
        $stmt->execute([
            'form_id' => $formId,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'files_json' => json_encode($files, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'ip' => mb_substr($ip, 0, 64),
            'ua' => mb_substr($userAgent, 0, 500),
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listForForm(int $formId, int $limit = 100, int $offset = 0): array
    {
        WebsiteFormRepository::ensureTables();
        $stmt = Database::pdo()->prepare(
            'SELECT id, form_id, payload_json, files_json, ip, is_read, created_at
             FROM dg_website_form_submissions
             WHERE form_id = :fid
             ORDER BY created_at DESC
             LIMIT :lim OFFSET :off'
        );
        $stmt->bindValue('fid', $formId, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue('off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $out[] = self::map($row);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        WebsiteFormRepository::ensureTables();
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_website_form_submissions WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::map($row) : null;
    }

    public static function markRead(int $id, bool $read = true): void
    {
        WebsiteFormRepository::ensureTables();
        $stmt = Database::pdo()->prepare('UPDATE dg_website_form_submissions SET is_read = :r WHERE id = :id');
        $stmt->execute(['r' => $read ? 1 : 0, 'id' => $id]);
    }

    public static function delete(int $id): void
    {
        $sub = self::find($id);
        if ($sub === null) {
            return;
        }
        WebsiteFormFileStorage::deleteSubmissionDir((int) $sub['form_id'], $id);
        $stmt = Database::pdo()->prepare('DELETE FROM dg_website_form_submissions WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function unreadCount(?int $formId = null): int
    {
        if (!Database::isConfigured()) {
            return 0;
        }
        WebsiteFormRepository::ensureTables();
        if ($formId !== null && $formId > 0) {
            $stmt = Database::pdo()->prepare(
                'SELECT COUNT(*) FROM dg_website_form_submissions WHERE form_id = :fid AND is_read = 0'
            );
            $stmt->execute(['fid' => $formId]);
            return (int) $stmt->fetchColumn();
        }
        $val = Database::pdo()->query(
            'SELECT COUNT(*) FROM dg_website_form_submissions WHERE is_read = 0'
        )->fetchColumn();

        return (int) $val;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function map(array $row): array
    {
        $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
        $files = json_decode((string) ($row['files_json'] ?? ''), true);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'form_id' => (int) ($row['form_id'] ?? 0),
            'payload' => is_array($payload) ? $payload : [],
            'files' => is_array($files) ? $files : [],
            'ip' => (string) ($row['ip'] ?? ''),
            'user_agent' => (string) ($row['user_agent'] ?? ''),
            'is_read' => !empty($row['is_read']),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }
}
