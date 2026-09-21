<?php
declare(strict_types=1);

/**
 * Soll/Ist-Protokoll zu Lauf-Snapshots (R6) — ohne Buchungswirkung.
 */
final class RecipeActualRepository
{
    public static function ensureReady(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
    }

    /**
     * Summen aus Rezept/Routing für geplante Soll-Werte.
     *
     * @param array<string, mixed> $recipe
     * @param list<array<string, mixed>> $routingLines
     * @param array<string, mixed> $calcResult
     * @return array{
     *   planned_qty: float,
     *   planned_setup_min: float,
     *   planned_run_min: float,
     *   planned_self_cost: float
     * }
     */
    public static function plannedFromRecipe(array $recipe, array $routingLines, array $calcResult): array
    {
        $setup = 0.0;
        $run = 0.0;
        foreach ($routingLines as $step) {
            if (!is_array($step)) {
                continue;
            }
            $setup += self::f($step['setup_min'] ?? 0);
            $run += self::f($step['run_min'] ?? 0);
        }
        $run += self::f($recipe['labor_minutes'] ?? 0);

        return [
            'planned_qty' => self::f($recipe['target_qty'] ?? 0),
            'planned_setup_min' => $setup,
            'planned_run_min' => $run,
            'planned_self_cost' => self::f($calcResult['self_cost'] ?? 0),
        ];
    }

    /**
     * @param array{
     *   planned_qty: float|int|string,
     *   planned_setup_min: float|int|string,
     *   planned_run_min: float|int|string,
     *   planned_self_cost: float|int|string,
     *   actual_qty?: float|int|string|null,
     *   actual_setup_min?: float|int|string|null,
     *   actual_run_min?: float|int|string|null,
     *   actual_self_cost?: float|int|string|null,
     *   note?: string
     * } $data
     */
    public static function createForSnapshot(int $snapshotId, array $data, ?int $userId): int
    {
        self::ensureReady();
        if ($snapshotId <= 0 || !Database::isConfigured()) {
            throw new InvalidArgumentException('Ungültiger Snapshot für Soll/Ist.');
        }

        $plannedQty = self::f($data['planned_qty'] ?? 0);
        $plannedSetup = self::f($data['planned_setup_min'] ?? 0);
        $plannedRun = self::f($data['planned_run_min'] ?? 0);
        $plannedSelf = self::f($data['planned_self_cost'] ?? 0);

        $actualQty = array_key_exists('actual_qty', $data) && $data['actual_qty'] !== null && (string) $data['actual_qty'] !== ''
            ? self::f($data['actual_qty'])
            : $plannedQty;
        $actualSetup = array_key_exists('actual_setup_min', $data) && $data['actual_setup_min'] !== null && (string) $data['actual_setup_min'] !== ''
            ? self::f($data['actual_setup_min'])
            : $plannedSetup;
        $actualRun = array_key_exists('actual_run_min', $data) && $data['actual_run_min'] !== null && (string) $data['actual_run_min'] !== ''
            ? self::f($data['actual_run_min'])
            : $plannedRun;
        $actualSelf = null;
        if (array_key_exists('actual_self_cost', $data) && $data['actual_self_cost'] !== null && (string) $data['actual_self_cost'] !== '') {
            $actualSelf = self::f($data['actual_self_cost']);
        }
        $note = mb_substr(trim((string) ($data['note'] ?? '')), 0, 1000);

        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_recipe_run_actuals
                (snapshot_id, planned_qty, actual_qty, planned_setup_min, actual_setup_min,
                 planned_run_min, actual_run_min, planned_self_cost, actual_self_cost, note, created_by)
             VALUES
                (:snapshot_id, :planned_qty, :actual_qty, :planned_setup_min, :actual_setup_min,
                 :planned_run_min, :actual_run_min, :planned_self_cost, :actual_self_cost, :note, :created_by)'
        );
        $stmt->execute([
            'snapshot_id' => $snapshotId,
            'planned_qty' => $plannedQty,
            'actual_qty' => $actualQty,
            'planned_setup_min' => $plannedSetup,
            'actual_setup_min' => $actualSetup,
            'planned_run_min' => $plannedRun,
            'actual_run_min' => $actualRun,
            'planned_self_cost' => $plannedSelf,
            'actual_self_cost' => $actualSelf,
            'note' => $note,
            'created_by' => $userId,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findBySnapshot(int $snapshotId): ?array
    {
        self::ensureReady();
        if ($snapshotId <= 0 || !Database::isConfigured()) {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_recipe_run_actuals WHERE snapshot_id = :sid LIMIT 1'
        );
        $stmt->execute(['sid' => $snapshotId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Actuals zu Snapshots einer Rezeptur (Key = snapshot_id).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function mapForRecipe(int $recipeId): array
    {
        self::ensureReady();
        if ($recipeId <= 0 || !Database::isConfigured()) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT a.*
             FROM dg_recipe_run_actuals a
             INNER JOIN dg_recipe_run_snapshots s ON s.id = a.snapshot_id
             WHERE s.recipe_id = :rid'
        );
        $stmt->execute(['rid' => $recipeId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $map = [];
        foreach ($rows as $row) {
            $map[(int) ($row['snapshot_id'] ?? 0)] = $row;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $post
     */
    public static function updateFromPost(int $actualId, array $post): void
    {
        self::ensureReady();
        if ($actualId <= 0 || !Database::isConfigured()) {
            throw new InvalidArgumentException('Ungültiger Soll/Ist-Eintrag.');
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE dg_recipe_run_actuals SET
                actual_qty = :actual_qty,
                actual_setup_min = :actual_setup_min,
                actual_run_min = :actual_run_min,
                actual_self_cost = :actual_self_cost,
                note = :note
             WHERE id = :id'
        );
        $actualSelfRaw = trim(str_replace([' ', ','], ['', '.'], (string) ($post['actual_self_cost'] ?? '')));
        $stmt->execute([
            'id' => $actualId,
            'actual_qty' => self::f($post['actual_qty'] ?? 0),
            'actual_setup_min' => self::f($post['actual_setup_min'] ?? 0),
            'actual_run_min' => self::f($post['actual_run_min'] ?? 0),
            'actual_self_cost' => ($actualSelfRaw !== '' && is_numeric($actualSelfRaw)) ? (float) $actualSelfRaw : null,
            'note' => mb_substr(trim((string) ($post['note'] ?? '')), 0, 1000),
        ]);
    }

    private static function f(mixed $v): float
    {
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        $s = trim(str_replace([' ', ','], ['', '.'], (string) $v));

        return $s !== '' && is_numeric($s) ? (float) $s : 0.0;
    }
}
