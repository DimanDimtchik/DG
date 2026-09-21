<?php
declare(strict_types=1);

/**
 * Rezeptur R1: Rezepte + Stückliste (Material + manuelle Fertigungszeit, keine Maschine).
 */
final class RecipeRepository
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Entwurf',
            self::STATUS_ACTIVE => 'Aktiv',
            self::STATUS_ARCHIVED => 'Archiviert',
        ];
    }

    public static function ensureReady(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
    }

    /**
     * @return array{
     *   title: string,
     *   target_qty: string,
     *   labor_minutes: string,
     *   margin_pct: string,
     *   status: string,
     *   version: int,
     *   notes: string,
     *   bom: list<array<string, mixed>>
     * }
     */
    public static function emptyForm(): array
    {
        return [
            'title' => '',
            'target_qty' => '1',
            'labor_minutes' => '0',
            'margin_pct' => '0',
            'status' => self::STATUS_DRAFT,
            'version' => 1,
            'notes' => '',
            'bom' => [self::emptyBomLine()],
            'routing' => [self::emptyRoutingLine()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function emptyBomLine(): array
    {
        return [
            'id' => 0,
            'material_label' => '',
            'article_id' => 0,
            'qty' => '1',
            'scrap_pct' => '0',
            'unit' => 'Stk',
            'unit_cost' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function emptyRoutingLine(): array
    {
        return [
            'id' => 0,
            'work_center_id' => 0,
            'setup_min' => '0',
            'run_min' => '0',
            'label' => '',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listAll(): array
    {
        self::ensureReady();
        if (!Database::isConfigured()) {
            return [];
        }
        $sql = 'SELECT r.*,
                       (SELECT COUNT(*) FROM dg_recipe_bom b WHERE b.recipe_id = r.id) AS bom_count,
                       (SELECT COUNT(*) FROM dg_recipe_routing rt WHERE rt.recipe_id = r.id) AS routing_count
                FROM dg_recipes r
                ORDER BY r.updated_at DESC, r.id DESC';
        $stmt = Database::pdo()->query($sql);

        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        self::ensureReady();
        if ($id <= 0 || !Database::isConfigured()) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_recipes WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function bomLines(int $recipeId): array
    {
        self::ensureReady();
        if ($recipeId <= 0 || !Database::isConfigured()) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT b.*, a.title AS article_title
             FROM dg_recipe_bom b
             LEFT JOIN dg_calendar_articles a ON a.id = b.article_id
             WHERE b.recipe_id = :rid
             ORDER BY b.sort_order ASC, b.id ASC'
        );
        $stmt->execute(['rid' => $recipeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function routingLines(int $recipeId): array
    {
        self::ensureReady();
        if ($recipeId <= 0 || !Database::isConfigured()) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT rt.*, w.name AS work_center_name
             FROM dg_recipe_routing rt
             LEFT JOIN dg_work_centers w ON w.id = rt.work_center_id
             WHERE rt.recipe_id = :rid
             ORDER BY rt.step_order ASC, rt.id ASC'
        );
        $stmt->execute(['rid' => $recipeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array{
     *   title: string,
     *   target_qty: string,
     *   labor_minutes: string,
     *   margin_pct: string,
     *   status: string,
     *   version: int,
     *   notes: string,
     *   bom: list<array<string, mixed>>,
     *   routing: list<array<string, mixed>>
     * }
     */
    public static function formForId(int $id): array
    {
        $row = self::find($id);
        if ($row === null) {
            return self::emptyForm();
        }
        $bom = self::bomLines($id);
        if ($bom === []) {
            $bom = [self::emptyBomLine()];
        }
        $normalizedBom = [];
        foreach ($bom as $line) {
            $unitCost = $line['unit_cost'] ?? null;
            $normalizedBom[] = [
                'id' => (int) ($line['id'] ?? 0),
                'material_label' => (string) ($line['material_label'] ?? ''),
                'article_id' => (int) ($line['article_id'] ?? 0),
                'qty' => self::formatDecimal((string) ($line['qty'] ?? '1')),
                'scrap_pct' => self::formatDecimal((string) ($line['scrap_pct'] ?? '0')),
                'unit' => (string) ($line['unit'] ?? 'Stk'),
                'unit_cost' => $unitCost !== null && $unitCost !== ''
                    ? self::formatDecimal((string) $unitCost)
                    : '',
                'article_title' => (string) ($line['article_title'] ?? ''),
            ];
        }

        $routing = self::routingLines($id);
        if ($routing === []) {
            $routing = [self::emptyRoutingLine()];
        }
        $normalizedRouting = [];
        foreach ($routing as $step) {
            $normalizedRouting[] = [
                'id' => (int) ($step['id'] ?? 0),
                'work_center_id' => (int) ($step['work_center_id'] ?? 0),
                'setup_min' => self::formatDecimal((string) ($step['setup_min'] ?? '0')),
                'run_min' => self::formatDecimal((string) ($step['run_min'] ?? '0')),
                'label' => (string) ($step['label'] ?? ''),
                'work_center_name' => (string) ($step['work_center_name'] ?? ''),
            ];
        }

        return [
            'title' => (string) ($row['title'] ?? ''),
            'target_qty' => self::formatDecimal((string) ($row['target_qty'] ?? '1')),
            'labor_minutes' => self::formatDecimal((string) ($row['labor_minutes'] ?? '0')),
            'margin_pct' => self::formatDecimal((string) ($row['margin_pct'] ?? '0')),
            'status' => self::normalizeStatus((string) ($row['status'] ?? self::STATUS_DRAFT)),
            'version' => max(1, (int) ($row['version'] ?? 1)),
            'notes' => (string) ($row['notes'] ?? ''),
            'bom' => $normalizedBom,
            'routing' => $normalizedRouting,
        ];
    }

    /**
     * @param array<string, mixed> $post
     */
    public static function save(array $post, ?int $id, ?int $userId): int
    {
        self::ensureReady();
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }

        $title = trim((string) ($post['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Bitte einen Rezept-Titel angeben.');
        }
        if (mb_strlen($title) > 191) {
            throw new InvalidArgumentException('Titel ist zu lang (max. 191 Zeichen).');
        }

        $targetQty = self::parseDecimal((string) ($post['target_qty'] ?? '1'), 0.001);
        $laborMinutes = self::parseDecimal((string) ($post['labor_minutes'] ?? '0'), 0.0);
        $marginPct = self::parseDecimal((string) ($post['margin_pct'] ?? '0'), 0.0);
        if ($marginPct < 0 || $marginPct > 999) {
            throw new InvalidArgumentException('Marge muss zwischen 0 und 999 % liegen.');
        }
        $status = self::normalizeStatus((string) ($post['status'] ?? self::STATUS_DRAFT));
        $version = max(1, (int) ($post['version'] ?? 1));
        $notes = trim((string) ($post['notes'] ?? ''));
        if (mb_strlen($notes) > 1000) {
            $notes = mb_substr($notes, 0, 1000);
        }
        $bomLines = self::parseBomFromPost($post);
        $routingLines = self::parseRoutingFromPost($post);

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if ($id !== null && $id > 0) {
                $existing = self::find($id);
                if ($existing === null) {
                    throw new InvalidArgumentException('Rezept nicht gefunden.');
                }
                $stmt = $pdo->prepare(
                    'UPDATE dg_recipes
                     SET title = :title,
                         target_qty = :target_qty,
                         labor_minutes = :labor_minutes,
                         margin_pct = :margin_pct,
                         status = :status,
                         version = :version,
                         notes = :notes
                     WHERE id = :id'
                );
                $stmt->execute([
                    'title' => $title,
                    'target_qty' => $targetQty,
                    'labor_minutes' => $laborMinutes,
                    'margin_pct' => $marginPct,
                    'status' => $status,
                    'version' => $version,
                    'notes' => $notes,
                    'id' => $id,
                ]);
                $recipeId = $id;
                $pdo->prepare('DELETE FROM dg_recipe_bom WHERE recipe_id = :rid')->execute(['rid' => $recipeId]);
                $pdo->prepare('DELETE FROM dg_recipe_routing WHERE recipe_id = :rid')->execute(['rid' => $recipeId]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO dg_recipes
                        (title, target_qty, labor_minutes, margin_pct, status, version, notes, created_by)
                     VALUES
                        (:title, :target_qty, :labor_minutes, :margin_pct, :status, :version, :notes, :created_by)'
                );
                $stmt->execute([
                    'title' => $title,
                    'target_qty' => $targetQty,
                    'labor_minutes' => $laborMinutes,
                    'margin_pct' => $marginPct,
                    'status' => $status,
                    'version' => $version,
                    'notes' => $notes,
                    'created_by' => $userId,
                ]);
                $recipeId = (int) $pdo->lastInsertId();
            }

            $ins = $pdo->prepare(
                'INSERT INTO dg_recipe_bom
                    (recipe_id, sort_order, material_label, article_id, qty, scrap_pct, unit, unit_cost)
                 VALUES
                    (:recipe_id, :sort_order, :material_label, :article_id, :qty, :scrap_pct, :unit, :unit_cost)'
            );
            foreach ($bomLines as $i => $line) {
                $ins->execute([
                    'recipe_id' => $recipeId,
                    'sort_order' => $i,
                    'material_label' => $line['material_label'],
                    'article_id' => $line['article_id'] > 0 ? $line['article_id'] : null,
                    'qty' => $line['qty'],
                    'scrap_pct' => $line['scrap_pct'],
                    'unit' => $line['unit'],
                    'unit_cost' => $line['unit_cost'],
                ]);
            }

            $insRt = $pdo->prepare(
                'INSERT INTO dg_recipe_routing
                    (recipe_id, step_order, work_center_id, setup_min, run_min, label)
                 VALUES
                    (:recipe_id, :step_order, :work_center_id, :setup_min, :run_min, :label)'
            );
            foreach ($routingLines as $i => $step) {
                $insRt->execute([
                    'recipe_id' => $recipeId,
                    'step_order' => $i,
                    'work_center_id' => $step['work_center_id'],
                    'setup_min' => $step['setup_min'],
                    'run_min' => $step['run_min'],
                    'label' => $step['label'],
                ]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        // Snapshot nach erfolgreichem Speichern (GoBD: Stand einfrieren)
        try {
            $formSnap = [
                'title' => $title,
                'target_qty' => $targetQty,
                'labor_minutes' => $laborMinutes,
                'margin_pct' => $marginPct,
                'status' => $status,
                'version' => $version,
            ];
            $calc = RecipeCostService::calculate($formSnap, $bomLines, $routingLines);
            $payload = RecipeCostService::snapshotPayload($formSnap, $bomLines, $routingLines, $calc);
            RecipeSnapshotRepository::create(
                $recipeId,
                RecipeSnapshotRepository::KIND_SAVE,
                $payload['inputs'],
                $payload['result'],
                $userId
            );
        } catch (Throwable) {
            // Snapshot-Fehler blockiert Speichern nicht
        }

        return $recipeId;
    }

    public static function delete(int $id): void
    {
        self::ensureReady();
        if ($id <= 0 || !Database::isConfigured()) {
            return;
        }
        $stmt = Database::pdo()->prepare('DELETE FROM dg_recipes WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * @param array<string, mixed> $post
     * @return list<array{material_label: string, article_id: int, qty: float, scrap_pct: float, unit: string}>
     */
    private static function parseBomFromPost(array $post): array
    {
        $raw = $post['bom'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = trim((string) ($row['material_label'] ?? ''));
            $articleId = (int) ($row['article_id'] ?? 0);
            if ($label === '' && $articleId <= 0) {
                continue;
            }
            if ($label === '' && $articleId > 0) {
                $article = CalendarArticleRepository::findById($articleId);
                $label = $article ? (string) ($article['title'] ?? '') : ('Artikel #' . $articleId);
            }
            if (mb_strlen($label) > 191) {
                $label = mb_substr($label, 0, 191);
            }
            $unit = trim((string) ($row['unit'] ?? 'Stk'));
            if ($unit === '') {
                $unit = 'Stk';
            }
            if (mb_strlen($unit) > 32) {
                $unit = mb_substr($unit, 0, 32);
            }
            $qty = self::parseDecimal((string) ($row['qty'] ?? '1'), 0.0001);
            $scrap = self::parseDecimal((string) ($row['scrap_pct'] ?? '0'), 0.0);
            if ($scrap < 0 || $scrap > 100) {
                throw new InvalidArgumentException('Verschnitt muss zwischen 0 und 100 % liegen.');
            }
            if ($articleId > 0) {
                $article = CalendarArticleRepository::findById($articleId);
                if ($article === null) {
                    throw new InvalidArgumentException('Artikel #' . $articleId . ' nicht gefunden.');
                }
            }
            $unitCostRaw = trim(str_replace([' ', ','], ['', '.'], (string) ($row['unit_cost'] ?? '')));
            $unitCost = null;
            if ($unitCostRaw !== '') {
                if (!is_numeric($unitCostRaw)) {
                    throw new InvalidArgumentException('EK/Einheit ungültig.');
                }
                $unitCost = (float) $unitCostRaw;
                if ($unitCost < 0) {
                    throw new InvalidArgumentException('EK/Einheit darf nicht negativ sein.');
                }
            }
            $out[] = [
                'material_label' => $label,
                'article_id' => $articleId,
                'qty' => $qty,
                'scrap_pct' => $scrap,
                'unit' => $unit,
                'unit_cost' => $unitCost,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $post
     * @return list<array{work_center_id: int, setup_min: float, run_min: float, label: string}>
     */
    private static function parseRoutingFromPost(array $post): array
    {
        $raw = $post['routing'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $wcId = (int) ($row['work_center_id'] ?? 0);
            if ($wcId <= 0) {
                continue;
            }
            if (WorkCenterRepository::find($wcId) === null) {
                throw new InvalidArgumentException('Arbeitsplatz #' . $wcId . ' nicht gefunden.');
            }
            $label = trim((string) ($row['label'] ?? ''));
            if (mb_strlen($label) > 191) {
                $label = mb_substr($label, 0, 191);
            }
            $out[] = [
                'work_center_id' => $wcId,
                'setup_min' => self::parseDecimal((string) ($row['setup_min'] ?? '0'), 0.0),
                'run_min' => self::parseDecimal((string) ($row['run_min'] ?? '0'), 0.0),
                'label' => $label,
            ];
        }

        return $out;
    }

    private static function normalizeStatus(string $status): string
    {
        $status = trim($status);
        $opts = self::statusOptions();

        return isset($opts[$status]) ? $status : self::STATUS_DRAFT;
    }

    private static function parseDecimal(string $raw, float $min): float
    {
        $raw = trim(str_replace([' ', ','], ['', '.'], $raw));
        if ($raw === '' || !is_numeric($raw)) {
            throw new InvalidArgumentException('Ungültige Zahl: ' . $raw);
        }
        $n = (float) $raw;
        if ($n < $min) {
            throw new InvalidArgumentException('Wert darf nicht kleiner als ' . $min . ' sein.');
        }

        return $n;
    }

    private static function formatDecimal(string $value): string
    {
        $value = trim(str_replace(',', '.', $value));
        if ($value === '' || !is_numeric($value)) {
            return '0';
        }
        $formatted = rtrim(rtrim(sprintf('%.4f', (float) $value), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
