<?php
declare(strict_types=1);

/**
 * Maschinenwissen / Störungen (E2b): Handbuch, Akademie-Modul, externe URL.
 */
final class MachineKnowledgeRepository
{
    public const CONTENT_MANUAL_PDF = 'manual_pdf';
    public const CONTENT_ACADEMY_MODULE = 'academy_module';
    public const CONTENT_EXTERNAL_URL = 'external_url';

    /** @var list<string> */
    private const CONTENT_TYPES = [
        self::CONTENT_MANUAL_PDF,
        self::CONTENT_ACADEMY_MODULE,
        self::CONTENT_EXTERNAL_URL,
    ];

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
        self::ensureReady();
        try {
            $stmt = Database::pdo()->query("SHOW TABLES LIKE 'dg_machine_knowledge_links'");

            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function list(?int $workCenterId = null, bool $activeOnly = true): array
    {
        self::ensureReady();
        if (!self::tableReady()) {
            return [];
        }
        $where = [];
        $params = [];
        if ($workCenterId !== null && $workCenterId > 0) {
            $where[] = 'work_center_id = :work_center_id';
            $params['work_center_id'] = $workCenterId;
        }
        if ($activeOnly) {
            $where[] = 'is_active = 1';
        }
        $sql = 'SELECT * FROM dg_machine_knowledge_links';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY is_active DESC, title ASC, id ASC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([self::class, 'normalize'], $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        self::ensureReady();
        if (!self::tableReady()) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_machine_knowledge_links WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::normalize($row) : null;
    }

    /**
     * Suche nach Fehlercode (exakt, case-insensitive) in error_codes_json.
     *
     * @return list<array<string, mixed>>
     */
    public static function searchByErrorCode(string $errorCode, ?int $workCenterId = null, bool $activeOnly = true): array
    {
        $needle = self::normalizeErrorCode($errorCode);
        if ($needle === '') {
            return [];
        }
        $rows = self::list($workCenterId, $activeOnly);
        $out = [];
        foreach ($rows as $row) {
            $codes = $row['error_codes'] ?? [];
            if (!is_array($codes)) {
                continue;
            }
            foreach ($codes as $code) {
                if (self::normalizeErrorCode((string) $code) === $needle) {
                    $out[] = $row;
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function save(array $data): int
    {
        self::ensureReady();
        if (!self::tableReady()) {
            throw new RuntimeException('Tabelle für Maschinenwissen ist nicht bereit.');
        }
        $id = (int) ($data['id'] ?? 0);
        $title = mb_substr(trim((string) ($data['title'] ?? '')), 0, 191);
        if ($title === '') {
            throw new InvalidArgumentException('Titel ist erforderlich.');
        }
        $contentType = trim((string) ($data['content_type'] ?? ''));
        if (!in_array($contentType, self::CONTENT_TYPES, true)) {
            throw new InvalidArgumentException('Ungültiger Inhaltstyp.');
        }
        $storagePath = mb_substr(trim((string) ($data['storage_path'] ?? '')), 0, 500);
        $academyModuleId = self::nullablePositiveInt($data['academy_module_id'] ?? null);
        $workCenterId = self::nullablePositiveInt($data['work_center_id'] ?? null);
        $recipeId = self::nullablePositiveInt($data['recipe_id'] ?? null);
        $duration = isset($data['video_duration_seconds']) && $data['video_duration_seconds'] !== ''
            ? max(0, (int) $data['video_duration_seconds'])
            : null;
        if ($duration === 0) {
            $duration = null;
        }
        $errorCodes = self::normalizeErrorCodeList($data['error_codes'] ?? ($data['error_codes_json'] ?? []));
        $errorJson = $errorCodes === [] ? null : json_encode(array_values($errorCodes), JSON_UNESCAPED_UNICODE);
        if ($errorJson === false) {
            throw new InvalidArgumentException('Fehlercodes konnten nicht gespeichert werden.');
        }
        $isActive = !array_key_exists('is_active', $data) || !empty($data['is_active']) ? 1 : 0;

        if ($contentType === self::CONTENT_ACADEMY_MODULE && ($academyModuleId === null || $academyModuleId < 1)) {
            throw new InvalidArgumentException('Akademie-Modul ist erforderlich.');
        }
        if ($contentType === self::CONTENT_MANUAL_PDF && $storagePath === '') {
            throw new InvalidArgumentException('Pfad zur PDF-Datei ist erforderlich.');
        }
        if ($contentType === self::CONTENT_EXTERNAL_URL && $storagePath === '') {
            throw new InvalidArgumentException('URL ist erforderlich.');
        }
        if ($contentType === self::CONTENT_MANUAL_PDF && !str_starts_with($storagePath, 'storage/')) {
            throw new InvalidArgumentException('PDF-Pfad muss unter storage/ liegen.');
        }

        $params = [
            'work_center_id' => $workCenterId,
            'recipe_id' => $recipeId,
            'title' => $title,
            'content_type' => $contentType,
            'storage_path' => $storagePath,
            'academy_module_id' => $academyModuleId,
            'video_duration_seconds' => $duration,
            'error_codes_json' => $errorJson,
            'is_active' => $isActive,
        ];

        $pdo = Database::pdo();
        if ($id > 0) {
            $existing = self::find($id);
            if ($existing === null) {
                throw new InvalidArgumentException('Eintrag nicht gefunden.');
            }
            $stmt = $pdo->prepare(
                'UPDATE dg_machine_knowledge_links SET
                    work_center_id = :work_center_id,
                    recipe_id = :recipe_id,
                    title = :title,
                    content_type = :content_type,
                    storage_path = :storage_path,
                    academy_module_id = :academy_module_id,
                    video_duration_seconds = :video_duration_seconds,
                    error_codes_json = :error_codes_json,
                    is_active = :is_active
                 WHERE id = :id'
            );
            $params['id'] = $id;
            $stmt->execute($params);

            return $id;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO dg_machine_knowledge_links
                (work_center_id, recipe_id, title, content_type, storage_path,
                 academy_module_id, video_duration_seconds, error_codes_json, is_active)
             VALUES
                (:work_center_id, :recipe_id, :title, :content_type, :storage_path,
                 :academy_module_id, :video_duration_seconds, :error_codes_json, :is_active)'
        );
        $stmt->execute($params);

        return (int) $pdo->lastInsertId();
    }

    public static function deactivate(int $id): void
    {
        if ($id < 1) {
            return;
        }
        self::ensureReady();
        if (!self::tableReady()) {
            return;
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE dg_machine_knowledge_links SET is_active = 0 WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function normalize(array $row): array
    {
        $rawJson = $row['error_codes_json'] ?? null;
        $codes = [];
        if (is_string($rawJson) && $rawJson !== '') {
            $decoded = json_decode($rawJson, true);
            if (is_array($decoded)) {
                $codes = self::normalizeErrorCodeList($decoded);
            }
        } elseif (is_array($rawJson)) {
            $codes = self::normalizeErrorCodeList($rawJson);
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'work_center_id' => isset($row['work_center_id']) && $row['work_center_id'] !== null
                ? (int) $row['work_center_id'] : null,
            'recipe_id' => isset($row['recipe_id']) && $row['recipe_id'] !== null
                ? (int) $row['recipe_id'] : null,
            'title' => (string) ($row['title'] ?? ''),
            'content_type' => (string) ($row['content_type'] ?? ''),
            'storage_path' => (string) ($row['storage_path'] ?? ''),
            'academy_module_id' => isset($row['academy_module_id']) && $row['academy_module_id'] !== null
                ? (int) $row['academy_module_id'] : null,
            'video_duration_seconds' => isset($row['video_duration_seconds']) && $row['video_duration_seconds'] !== null
                ? (int) $row['video_duration_seconds'] : null,
            'error_codes' => $codes,
            'error_codes_json' => $codes === [] ? null : json_encode(array_values($codes), JSON_UNESCAPED_UNICODE),
            'is_active' => !empty($row['is_active']),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    /** @return list<string> */
    public static function contentTypes(): array
    {
        return self::CONTENT_TYPES;
    }

    private static function normalizeErrorCode(string $code): string
    {
        return strtoupper(trim($code));
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    private static function normalizeErrorCodeList(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = preg_split('/[\s,;]+/', $raw) ?: [];
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            $code = self::normalizeErrorCode((string) $item);
            if ($code === '' || in_array($code, $out, true)) {
                continue;
            }
            if (mb_strlen($code) > 64) {
                $code = mb_substr($code, 0, 64);
            }
            $out[] = $code;
        }

        return $out;
    }

    private static function nullablePositiveInt(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        $n = (int) $v;

        return $n > 0 ? $n : null;
    }
}
