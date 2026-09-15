<?php
declare(strict_types=1);

/**
 * Protokoll aller Kichel-Anfragen (nur eingeloggte Mitarbeiter/Admins).
 */
final class KichelProtocolRepository
{
    public static function record(int $userId, string $query, string $answer, ?array $response = null): void
    {
        if (!Database::isConfigured()) {
            return;
        }

        MigrationRunner::runPending();

        try {
            $json = $response !== null
                ? json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                : null;
            $stmt = Database::pdo()->prepare(
                'INSERT INTO dg_kichel_log (user_id, query_text, answer_text, response_json, created_at)
                 VALUES (:uid, :query, :answer, :json, NOW())'
            );
            $stmt->execute([
                'uid' => $userId,
                'query' => mb_substr(trim($query), 0, 500),
                'answer' => mb_substr($answer, 0, 65000),
                'json' => $json,
            ]);
        } catch (Throwable $e) {
            error_log('KichelProtocolRepository: ' . $e->getMessage());
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function recent(int $limit = 100, int $offset = 0): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        MigrationRunner::runPending();

        try {
            $stmt = Database::pdo()->prepare(
                'SELECT k.*, u.username, u.display_name
                 FROM dg_kichel_log k
                 LEFT JOIN dg_users u ON u.id = k.user_id
                 ORDER BY k.created_at DESC
                 LIMIT :lim OFFSET :off'
            );
            $stmt->bindValue('lim', max(1, min(500, $limit)), PDO::PARAM_INT);
            $stmt->bindValue('off', max(0, $offset), PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }
}
