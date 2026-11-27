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

    /**
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int, per_page_key: string}
     */
    public static function paginate(int $page = 1, string $perPageKey = ListPagination::DEFAULT, int $hardCap = 10000): array
    {
        $perPageKey = ListPagination::parsePerPageKey($perPageKey);
        if (!Database::isConfigured()) {
            return ListPagination::emptyResult($perPageKey);
        }
        MigrationRunner::runPending();
        try {
            $total = min((int) Database::pdo()->query('SELECT COUNT(*) FROM dg_kichel_log')->fetchColumn(), max(1, $hardCap));
            $meta = ListPagination::sliceMeta($total, $page, $perPageKey);
            $stmt = Database::pdo()->prepare(
                'SELECT k.*, u.username, u.display_name
                 FROM dg_kichel_log k
                 LEFT JOIN dg_users u ON u.id = k.user_id
                 ORDER BY k.created_at DESC
                 LIMIT :lim OFFSET :off'
            );
            $stmt->bindValue('lim', $meta['show_all'] ? max(1, $hardCap) : $meta['limit'], PDO::PARAM_INT);
            $stmt->bindValue('off', $meta['show_all'] ? 0 : $meta['offset'], PDO::PARAM_INT);
            $stmt->execute();

            return ListPagination::result($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], $total, $meta);
        } catch (Throwable) {
            return ListPagination::emptyResult($perPageKey);
        }
    }

    /**
     * KR6b — Meta aus response_json (ohne Extra-Migration).
     *
     * @return array{rag_used: bool, source_refs: list<string>, sources: list<array{label: string, ref: string, href?: string|null}>}
     */
    public static function metaFromResponseJson(?string $json): array
    {
        $empty = [
            'rag_used' => false,
            'source_refs' => [],
            'sources' => [],
        ];
        if ($json === null || trim($json) === '') {
            return $empty;
        }
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return $empty;
        }
        if (!is_array($data)) {
            return $empty;
        }

        $ragUsed = !empty($data['rag_used']);
        $sources = [];
        if (isset($data['sources']) && is_array($data['sources'])) {
            foreach ($data['sources'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $label = trim((string) ($row['label'] ?? ''));
                $ref = trim((string) ($row['ref'] ?? ''));
                if ($label === '' && $ref === '') {
                    continue;
                }
                $sources[] = [
                    'label' => $label !== '' ? $label : $ref,
                    'ref' => $ref,
                    'href' => isset($row['href']) && is_string($row['href']) ? $row['href'] : null,
                ];
            }
        }
        if ($sources === [] && isset($data['rag_facts']) && is_array($data['rag_facts'])) {
            $sources = KichelRagFact::sourcesForUi($data['rag_facts']);
            $ragUsed = $ragUsed || $sources !== [];
        }

        $refs = [];
        foreach ($sources as $source) {
            $ref = trim((string) ($source['ref'] ?? ''));
            if ($ref !== '') {
                $refs[] = $ref;
            }
        }

        return [
            'rag_used' => $ragUsed,
            'source_refs' => array_values(array_unique($refs)),
            'sources' => $sources,
        ];
    }
}
