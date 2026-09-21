<?php
declare(strict_types=1);

/**
 * Kalkulations-Snapshots (GoBD: gebuchte/ gespeicherte Stände nicht rückwirkend ändern).
 */
final class RecipeSnapshotRepository
{
    public const KIND_SAVE = 'save';
    public const KIND_RUN = 'run';

    public static function ensureReady(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
    }

    /**
     * @param array<string, mixed> $inputs
     * @param array<string, mixed> $result
     */
    public static function create(int $recipeId, string $kind, array $inputs, array $result, ?int $userId): int
    {
        self::ensureReady();
        if ($recipeId <= 0 || !Database::isConfigured()) {
            throw new InvalidArgumentException('Ungültiges Rezept für Snapshot.');
        }
        if ($kind !== self::KIND_SAVE && $kind !== self::KIND_RUN) {
            $kind = self::KIND_SAVE;
        }
        $inputsJson = json_encode($inputs, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $resultJson = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_recipe_run_snapshots (recipe_id, kind, inputs_json, result_json, created_by)
             VALUES (:recipe_id, :kind, :inputs_json, :result_json, :created_by)'
        );
        $stmt->execute([
            'recipe_id' => $recipeId,
            'kind' => $kind,
            'inputs_json' => $inputsJson,
            'result_json' => $resultJson,
            'created_by' => $userId,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listForRecipe(int $recipeId, int $limit = 20): array
    {
        self::ensureReady();
        if ($recipeId <= 0 || !Database::isConfigured()) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $stmt = Database::pdo()->prepare(
            'SELECT id, recipe_id, kind, created_by, created_at, result_json
             FROM dg_recipe_run_snapshots
             WHERE recipe_id = :rid
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $limit
        );
        $stmt->execute(['rid' => $recipeId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $decoded = json_decode((string) ($row['result_json'] ?? ''), true);
            $row['result'] = is_array($decoded) ? $decoded : [];
            unset($row['result_json']);
        }
        unset($row);

        return $rows;
    }
}
