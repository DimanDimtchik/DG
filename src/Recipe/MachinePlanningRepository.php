<?php
declare(strict_types=1);

/**
 * Maschinenblock M0b: Kapazitaet, Zusatzmaterial und Wartung ohne UI/Buchungswirkung.
 */
final class MachinePlanningRepository
{
    private const AUX_ROLES = ['packaging', 'additive', 'consumable', 'other'];
    private const INTERVAL_TYPES = ['days', 'runtime_hours', 'cycles'];

    public static function ensureReady(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
    }

    /** @param array<string, mixed> $data */
    public static function createCapacityProfile(array $data): int
    {
        self::ensureReady();
        self::requireDatabase();
        $workCenterId = self::positiveInt($data['work_center_id'] ?? 0, 'Maschine');
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_machine_capacity_profiles
                (work_center_id, recipe_id, input_material_label, input_article_id, input_qty_per_hour,
                 output_product_label, output_article_id, output_qty_per_hour, unit, yield_percent,
                 valid_from, is_active, notes)
             VALUES
                (:work_center_id, :recipe_id, :input_material_label, :input_article_id, :input_qty_per_hour,
                 :output_product_label, :output_article_id, :output_qty_per_hour, :unit, :yield_percent,
                 :valid_from, :is_active, :notes)'
        );
        $stmt->execute([
            'work_center_id' => $workCenterId,
            'recipe_id' => self::nullablePositiveInt($data['recipe_id'] ?? null),
            'input_material_label' => self::trimmed($data['input_material_label'] ?? '', 191),
            'input_article_id' => self::nullablePositiveInt($data['input_article_id'] ?? null),
            'input_qty_per_hour' => self::decimal($data['input_qty_per_hour'] ?? 0, 0.0),
            'output_product_label' => self::trimmed($data['output_product_label'] ?? '', 191),
            'output_article_id' => self::nullablePositiveInt($data['output_article_id'] ?? null),
            'output_qty_per_hour' => self::decimal($data['output_qty_per_hour'] ?? 0, 0.0),
            'unit' => self::trimmed($data['unit'] ?? '', 50),
            'yield_percent' => self::decimal($data['yield_percent'] ?? 100, 0.0),
            'valid_from' => self::dateOrNull($data['valid_from'] ?? null),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'notes' => self::trimmed($data['notes'] ?? '', 1000),
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public static function listCapacityProfiles(?int $workCenterId = null, bool $activeOnly = false): array
    {
        self::ensureReady();
        if (!Database::isConfigured()) {
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
        $sql = 'SELECT * FROM dg_machine_capacity_profiles';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY is_active DESC, valid_from DESC, id DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function deactivateCapacityProfile(int $id): void
    {
        self::deactivate('dg_machine_capacity_profiles', $id);
    }

    /** @param array<string, mixed> $data */
    public static function createAuxiliaryMaterial(array $data): int
    {
        self::ensureReady();
        self::requireDatabase();
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_recipe_auxiliary_materials
                (recipe_id, article_id, material_label, role, qty_per_output_unit, unit, cost_per_unit, is_active)
             VALUES
                (:recipe_id, :article_id, :material_label, :role, :qty_per_output_unit, :unit, :cost_per_unit, :is_active)'
        );
        $stmt->execute([
            'recipe_id' => self::positiveInt($data['recipe_id'] ?? 0, 'Rezeptur'),
            'article_id' => self::nullablePositiveInt($data['article_id'] ?? null),
            'material_label' => self::trimmed($data['material_label'] ?? '', 191),
            'role' => self::enum((string) ($data['role'] ?? 'other'), self::AUX_ROLES, 'other'),
            'qty_per_output_unit' => self::decimal($data['qty_per_output_unit'] ?? 0, 0.0),
            'unit' => self::trimmed($data['unit'] ?? '', 50),
            'cost_per_unit' => self::decimal($data['cost_per_unit'] ?? 0, 0.0),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public static function listAuxiliaryMaterials(int $recipeId, bool $activeOnly = false): array
    {
        self::ensureReady();
        if ($recipeId <= 0 || !Database::isConfigured()) {
            return [];
        }
        $sql = 'SELECT * FROM dg_recipe_auxiliary_materials WHERE recipe_id = :recipe_id';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY role ASC, id ASC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['recipe_id' => $recipeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function deactivateAuxiliaryMaterial(int $id): void
    {
        self::deactivate('dg_recipe_auxiliary_materials', $id);
    }

    /** @param array<string, mixed> $data */
    public static function createMaintenancePlan(array $data): int
    {
        self::ensureReady();
        self::requireDatabase();
        $title = self::trimmed($data['title'] ?? '', 191);
        if ($title === '') {
            throw new InvalidArgumentException('Wartungsplan braucht einen Titel.');
        }
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_machine_maintenance_plans
                (work_center_id, title, interval_type, interval_value, planned_downtime_minutes,
                 helper_materials_json, estimated_material_cost, estimated_service_cost, next_due_date, is_active)
             VALUES
                (:work_center_id, :title, :interval_type, :interval_value, :planned_downtime_minutes,
                 :helper_materials_json, :estimated_material_cost, :estimated_service_cost, :next_due_date, :is_active)'
        );
        $stmt->execute([
            'work_center_id' => self::positiveInt($data['work_center_id'] ?? 0, 'Maschine'),
            'title' => $title,
            'interval_type' => self::enum((string) ($data['interval_type'] ?? ''), self::INTERVAL_TYPES, 'days'),
            'interval_value' => self::decimal($data['interval_value'] ?? 0, 0.01),
            'planned_downtime_minutes' => max(0, (int) ($data['planned_downtime_minutes'] ?? 0)),
            'helper_materials_json' => self::jsonOrNull($data['helper_materials_json'] ?? null),
            'estimated_material_cost' => self::decimal($data['estimated_material_cost'] ?? 0, 0.0),
            'estimated_service_cost' => self::decimal($data['estimated_service_cost'] ?? 0, 0.0),
            'next_due_date' => self::dateOrNull($data['next_due_date'] ?? null),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public static function listMaintenancePlans(?int $workCenterId = null, bool $activeOnly = false): array
    {
        self::ensureReady();
        if (!Database::isConfigured()) {
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
        $sql = 'SELECT * FROM dg_machine_maintenance_plans';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY is_active DESC, next_due_date IS NULL ASC, next_due_date ASC, id DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function deactivateMaintenancePlan(int $id): void
    {
        self::deactivate('dg_machine_maintenance_plans', $id);
    }

    /** @param array<string, mixed> $data */
    public static function createMaintenanceLog(array $data): int
    {
        self::ensureReady();
        self::requireDatabase();
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_machine_maintenance_logs
                (maintenance_plan_id, work_center_id, performed_at, runtime_hours_at_service,
                 actual_material_cost, actual_service_cost, notes)
             VALUES
                (:maintenance_plan_id, :work_center_id, :performed_at, :runtime_hours_at_service,
                 :actual_material_cost, :actual_service_cost, :notes)'
        );
        $stmt->execute([
            'maintenance_plan_id' => self::nullablePositiveInt($data['maintenance_plan_id'] ?? null),
            'work_center_id' => self::positiveInt($data['work_center_id'] ?? 0, 'Maschine'),
            'performed_at' => self::dateTime((string) ($data['performed_at'] ?? '')),
            'runtime_hours_at_service' => self::nullableDecimal($data['runtime_hours_at_service'] ?? null, 0.0),
            'actual_material_cost' => self::decimal($data['actual_material_cost'] ?? 0, 0.0),
            'actual_service_cost' => self::decimal($data['actual_service_cost'] ?? 0, 0.0),
            'notes' => self::trimmed($data['notes'] ?? '', 1000),
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public static function listMaintenanceLogs(?int $workCenterId = null): array
    {
        self::ensureReady();
        if (!Database::isConfigured()) {
            return [];
        }
        $sql = 'SELECT * FROM dg_machine_maintenance_logs';
        $params = [];
        if ($workCenterId !== null && $workCenterId > 0) {
            $sql .= ' WHERE work_center_id = :work_center_id';
            $params['work_center_id'] = $workCenterId;
        }
        $sql .= ' ORDER BY performed_at DESC, id DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private static function requireDatabase(): void
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }
    }

    private static function deactivate(string $table, int $id): void
    {
        self::ensureReady();
        if ($id <= 0 || !Database::isConfigured()) {
            return;
        }
        $allowed = [
            'dg_machine_capacity_profiles',
            'dg_recipe_auxiliary_materials',
            'dg_machine_maintenance_plans',
        ];
        if (!in_array($table, $allowed, true)) {
            throw new InvalidArgumentException('Unbekannte Tabelle.');
        }
        $stmt = Database::pdo()->prepare("UPDATE {$table} SET is_active = 0 WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        $n = (int) $value;
        if ($n <= 0) {
            throw new InvalidArgumentException($label . ' fehlt.');
        }
        return $n;
    }

    private static function nullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || (int) $value <= 0) {
            return null;
        }
        return (int) $value;
    }

    private static function decimal(mixed $value, float $min): float
    {
        $raw = trim(str_replace([' ', ','], ['', '.'], (string) $value));
        $n = $raw !== '' && is_numeric($raw) ? (float) $raw : 0.0;
        if ($n < $min) {
            throw new InvalidArgumentException('Zahl darf nicht kleiner als ' . $min . ' sein.');
        }
        return $n;
    }

    private static function nullableDecimal(mixed $value, float $min): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        return self::decimal($value, $min);
    }

    private static function trimmed(mixed $value, int $max): string
    {
        return mb_substr(trim((string) $value), 0, $max);
    }

    /** @param list<string> $allowed */
    private static function enum(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private static function dateOrNull(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d', $ts);
    }

    private static function dateTime(string $value): string
    {
        $ts = strtotime(trim($value));
        if ($ts === false) {
            $ts = time();
        }
        return date('Y-m-d H:i:s', $ts);
    }

    private static function jsonOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $value : json_encode([$value], JSON_UNESCAPED_UNICODE);
        }
        $json = json_encode($value, JSON_UNESCAPED_UNICODE);
        return $json === false ? null : $json;
    }
}
