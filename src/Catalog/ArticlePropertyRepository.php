<?php
declare(strict_types=1);

/**
 * AP1: Dynamische Artikel-/Leistungseigenschaften.
 * Gruppen · Definitionen · typisierte Werte · Instanz-Aktivierung.
 */
final class ArticlePropertyRepository
{
    public const TYPE_TEXT = 'text';
    public const TYPE_INTEGER = 'integer';
    public const TYPE_DECIMAL = 'decimal';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_DATE = 'date';
    public const TYPE_SELECT = 'select';
    public const TYPE_MULTI_SELECT = 'multi_select';

    /** @var list<string> */
    public const DATA_TYPES = [
        self::TYPE_TEXT,
        self::TYPE_INTEGER,
        self::TYPE_DECIMAL,
        self::TYPE_BOOLEAN,
        self::TYPE_DATE,
        self::TYPE_SELECT,
        self::TYPE_MULTI_SELECT,
    ];

    public static function ensureReady(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
    }

    public static function tablesReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        self::ensureReady();
        try {
            $pdo = Database::pdo();
            foreach ([
                'dg_article_property_groups',
                'dg_article_properties',
                'dg_article_property_values',
                'dg_instance_property_groups',
            ] as $table) {
                $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));
                if ($stmt === false || $stmt->fetchColumn() === false) {
                    return false;
                }
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    // ——— Gruppen ———

    /**
     * @return list<array<string, mixed>>
     */
    public static function listGroups(bool $withActivation = true): array
    {
        self::ensureReady();
        if (!self::tablesReady()) {
            return [];
        }
        $sql = 'SELECT g.*';
        if ($withActivation) {
            $sql .= ', COALESCE(i.is_active, 0) AS instance_active,
                      i.sort_order AS instance_sort_order,
                      i.activated_at AS instance_activated_at';
        }
        $sql .= ' FROM dg_article_property_groups g';
        if ($withActivation) {
            $sql .= ' LEFT JOIN dg_instance_property_groups i ON i.group_id = g.id';
        }
        $sql .= ' ORDER BY COALESCE(i.sort_order, g.sort_order) ASC, g.sort_order ASC, g.id ASC';
        $stmt = Database::pdo()->query($sql);
        $rows = $stmt !== false ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

        return array_map([self::class, 'normalizeGroup'], $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findGroup(int $id): ?array
    {
        if ($id < 1 || !self::tablesReady()) {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT g.*, COALESCE(i.is_active, 0) AS instance_active,
                    i.sort_order AS instance_sort_order,
                    i.activated_at AS instance_activated_at
             FROM dg_article_property_groups g
             LEFT JOIN dg_instance_property_groups i ON i.group_id = g.id
             WHERE g.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::normalizeGroup($row) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findGroupByCode(string $code): ?array
    {
        $code = self::normalizeCode($code);
        if ($code === '' || !self::tablesReady()) {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT g.*, COALESCE(i.is_active, 0) AS instance_active,
                    i.sort_order AS instance_sort_order,
                    i.activated_at AS instance_activated_at
             FROM dg_article_property_groups g
             LEFT JOIN dg_instance_property_groups i ON i.group_id = g.id
             WHERE g.code = :code LIMIT 1'
        );
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::normalizeGroup($row) : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function saveGroup(array $data): int
    {
        self::ensureReady();
        if (!self::tablesReady()) {
            throw new RuntimeException('Artikeleigenschaften-Tabellen sind nicht bereit.');
        }
        $id = (int) ($data['id'] ?? 0);
        $code = self::normalizeCode((string) ($data['code'] ?? ''));
        $label = mb_substr(trim((string) ($data['label'] ?? '')), 0, 191);
        if ($code === '' || $label === '') {
            throw new InvalidArgumentException('Gruppen-Code und Bezeichnung sind erforderlich.');
        }
        $description = trim((string) ($data['description'] ?? ''));
        $description = $description === '' ? null : $description;
        $sortOrder = (int) ($data['sort_order'] ?? 0);
        $isSystem = !empty($data['is_system']) ? 1 : 0;
        $pdo = Database::pdo();

        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE dg_article_property_groups
                 SET code = :code, label = :label, description = :description,
                     sort_order = :sort_order, is_system = :is_system
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => $id,
                'code' => $code,
                'label' => $label,
                'description' => $description,
                'sort_order' => $sortOrder,
                'is_system' => $isSystem,
            ]);

            return $id;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO dg_article_property_groups
             (code, label, description, sort_order, is_system)
             VALUES (:code, :label, :description, :sort_order, :is_system)'
        );
        $stmt->execute([
            'code' => $code,
            'label' => $label,
            'description' => $description,
            'sort_order' => $sortOrder,
            'is_system' => $isSystem,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Löscht eine Gruppe. Blockiert, wenn Eigenschaftswerte an Artikeln hängen
     * oder die Gruppe in der Instanz aktiv ist (AP1-Schutz).
     *
     * @return array{ok: bool, error?: string}
     */
    public static function deleteGroup(int $id): array
    {
        if ($id < 1 || !self::tablesReady()) {
            return ['ok' => false, 'error' => 'Gruppe nicht gefunden.'];
        }
        $group = self::findGroup($id);
        if ($group === null) {
            return ['ok' => false, 'error' => 'Gruppe nicht gefunden.'];
        }
        if (!empty($group['instance_active'])) {
            return [
                'ok' => false,
                'error' => 'Aktive Gruppe kann nicht gelöscht werden. Bitte zuerst deaktivieren.',
            ];
        }
        $valueCount = self::countValuesInGroup($id);
        if ($valueCount > 0) {
            return [
                'ok' => false,
                'error' => 'Gruppe hat ' . $valueCount . ' gespeicherte Werte und kann nicht gelöscht werden.',
            ];
        }

        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM dg_instance_property_groups WHERE group_id = :id')->execute(['id' => $id]);
        $pdo->prepare('DELETE FROM dg_article_properties WHERE group_id = :id')->execute(['id' => $id]);
        $pdo->prepare('DELETE FROM dg_article_property_groups WHERE id = :id')->execute(['id' => $id]);

        return ['ok' => true];
    }

    public static function countValuesInGroup(int $groupId): int
    {
        if ($groupId < 1 || !self::tablesReady()) {
            return 0;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM dg_article_property_values v
             INNER JOIN dg_article_properties p ON p.id = v.property_id
             WHERE p.group_id = :gid'
        );
        $stmt->execute(['gid' => $groupId]);

        return (int) $stmt->fetchColumn();
    }

    public static function setGroupActive(int $groupId, bool $active, ?int $sortOrder = null): void
    {
        if ($groupId < 1 || !self::tablesReady()) {
            throw new InvalidArgumentException('Ungültige Gruppe.');
        }
        if (self::findGroup($groupId) === null) {
            throw new InvalidArgumentException('Gruppe nicht gefunden.');
        }
        $pdo = Database::pdo();
        $existing = $pdo->prepare('SELECT id, sort_order FROM dg_instance_property_groups WHERE group_id = :gid LIMIT 1');
        $existing->execute(['gid' => $groupId]);
        $row = $existing->fetch(PDO::FETCH_ASSOC);
        $order = $sortOrder !== null ? $sortOrder : (is_array($row) ? (int) $row['sort_order'] : 0);

        if (is_array($row)) {
            $stmt = $pdo->prepare(
                'UPDATE dg_instance_property_groups
                 SET is_active = :active,
                     sort_order = :sort_order,
                     activated_at = CASE
                         WHEN :active2 = 1 AND (activated_at IS NULL OR is_active = 0) THEN NOW()
                         WHEN :active3 = 0 THEN activated_at
                         ELSE activated_at
                     END
                 WHERE group_id = :gid'
            );
            $stmt->execute([
                'active' => $active ? 1 : 0,
                'active2' => $active ? 1 : 0,
                'active3' => $active ? 1 : 0,
                'sort_order' => $order,
                'gid' => $groupId,
            ]);

            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO dg_instance_property_groups (group_id, is_active, sort_order, activated_at)
             VALUES (:gid, :active, :sort_order, CASE WHEN :active2 = 1 THEN NOW() ELSE NULL END)'
        );
        $stmt->execute([
            'gid' => $groupId,
            'active' => $active ? 1 : 0,
            'active2' => $active ? 1 : 0,
            'sort_order' => $order,
        ]);
    }

    /**
     * @return list<int>
     */
    public static function activeGroupIds(): array
    {
        if (!self::tablesReady()) {
            return [];
        }
        $stmt = Database::pdo()->query(
            'SELECT group_id FROM dg_instance_property_groups WHERE is_active = 1 ORDER BY sort_order ASC, group_id ASC'
        );
        if ($stmt === false) {
            return [];
        }
        $ids = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $id) {
            $ids[] = (int) $id;
        }

        return $ids;
    }

    // ——— Eigenschaftsdefinitionen ———

    /**
     * @return list<array<string, mixed>>
     */
    public static function listProperties(?int $groupId = null, bool $onlyActiveGroups = false): array
    {
        self::ensureReady();
        if (!self::tablesReady()) {
            return [];
        }
        $sql = 'SELECT p.*, g.code AS group_code, g.label AS group_label,
                       COALESCE(i.is_active, 0) AS group_instance_active
                FROM dg_article_properties p
                INNER JOIN dg_article_property_groups g ON g.id = p.group_id
                LEFT JOIN dg_instance_property_groups i ON i.group_id = g.id';
        $where = [];
        $params = [];
        if ($groupId !== null && $groupId > 0) {
            $where[] = 'p.group_id = :gid';
            $params['gid'] = $groupId;
        }
        if ($onlyActiveGroups) {
            $where[] = 'COALESCE(i.is_active, 0) = 1';
        }
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY COALESCE(i.sort_order, g.sort_order) ASC, g.sort_order ASC,
                           p.sort_order ASC, p.id ASC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([self::class, 'normalizeProperty'], $rows);
    }

    /**
     * Definitionen nur aus in der Instanz aktivierten Gruppen (UI-Filter).
     *
     * @return list<array<string, mixed>>
     */
    public static function listActiveDefinitions(): array
    {
        return self::listProperties(null, true);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findProperty(int $id): ?array
    {
        if ($id < 1 || !self::tablesReady()) {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT p.*, g.code AS group_code, g.label AS group_label,
                    COALESCE(i.is_active, 0) AS group_instance_active
             FROM dg_article_properties p
             INNER JOIN dg_article_property_groups g ON g.id = p.group_id
             LEFT JOIN dg_instance_property_groups i ON i.group_id = g.id
             WHERE p.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::normalizeProperty($row) : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function saveProperty(array $data): int
    {
        self::ensureReady();
        if (!self::tablesReady()) {
            throw new RuntimeException('Artikeleigenschaften-Tabellen sind nicht bereit.');
        }
        $id = (int) ($data['id'] ?? 0);
        $groupId = (int) ($data['group_id'] ?? 0);
        if ($groupId < 1 || self::findGroup($groupId) === null) {
            throw new InvalidArgumentException('Ungültige Eigenschaftsgruppe.');
        }
        $code = self::normalizeCode((string) ($data['code'] ?? ''));
        $label = mb_substr(trim((string) ($data['label'] ?? '')), 0, 191);
        if ($code === '' || $label === '') {
            throw new InvalidArgumentException('Eigenschafts-Code und Bezeichnung sind erforderlich.');
        }
        $dataType = trim((string) ($data['data_type'] ?? self::TYPE_TEXT));
        if (!in_array($dataType, self::DATA_TYPES, true)) {
            throw new InvalidArgumentException('Ungültiger Datentyp.');
        }
        $unit = mb_substr(trim((string) ($data['unit'] ?? '')), 0, 32);
        $description = trim((string) ($data['description'] ?? ''));
        $description = $description === '' ? null : $description;
        $allowed = self::encodeAllowedValues($data['allowed_values'] ?? ($data['allowed_values_json'] ?? null));
        $minValue = self::nullableDecimal($data['min_value'] ?? null);
        $maxValue = self::nullableDecimal($data['max_value'] ?? null);
        $isRequired = !empty($data['is_required']) ? 1 : 0;
        $isFilterable = !empty($data['is_filterable']) ? 1 : 0;
        $isSearchable = !empty($data['is_searchable']) ? 1 : 0;
        $sortOrder = (int) ($data['sort_order'] ?? 0);
        $pdo = Database::pdo();

        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE dg_article_properties
                 SET group_id = :group_id, code = :code, label = :label, description = :description,
                     data_type = :data_type, unit = :unit, allowed_values_json = :allowed_values_json,
                     min_value = :min_value, max_value = :max_value,
                     is_required = :is_required, is_filterable = :is_filterable,
                     is_searchable = :is_searchable, sort_order = :sort_order
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => $id,
                'group_id' => $groupId,
                'code' => $code,
                'label' => $label,
                'description' => $description,
                'data_type' => $dataType,
                'unit' => $unit,
                'allowed_values_json' => $allowed,
                'min_value' => $minValue,
                'max_value' => $maxValue,
                'is_required' => $isRequired,
                'is_filterable' => $isFilterable,
                'is_searchable' => $isSearchable,
                'sort_order' => $sortOrder,
            ]);

            return $id;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO dg_article_properties
             (group_id, code, label, description, data_type, unit, allowed_values_json,
              min_value, max_value, is_required, is_filterable, is_searchable, sort_order)
             VALUES
             (:group_id, :code, :label, :description, :data_type, :unit, :allowed_values_json,
              :min_value, :max_value, :is_required, :is_filterable, :is_searchable, :sort_order)'
        );
        $stmt->execute([
            'group_id' => $groupId,
            'code' => $code,
            'label' => $label,
            'description' => $description,
            'data_type' => $dataType,
            'unit' => $unit,
            'allowed_values_json' => $allowed,
            'min_value' => $minValue,
            'max_value' => $maxValue,
            'is_required' => $isRequired,
            'is_filterable' => $isFilterable,
            'is_searchable' => $isSearchable,
            'sort_order' => $sortOrder,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public static function deleteProperty(int $id): array
    {
        if ($id < 1 || !self::tablesReady()) {
            return ['ok' => false, 'error' => 'Eigenschaft nicht gefunden.'];
        }
        $prop = self::findProperty($id);
        if ($prop === null) {
            return ['ok' => false, 'error' => 'Eigenschaft nicht gefunden.'];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM dg_article_property_values WHERE property_id = :id'
        );
        $stmt->execute(['id' => $id]);
        $count = (int) $stmt->fetchColumn();
        if ($count > 0) {
            return [
                'ok' => false,
                'error' => 'Eigenschaft hat ' . $count . ' gespeicherte Werte und kann nicht gelöscht werden.',
            ];
        }
        Database::pdo()->prepare('DELETE FROM dg_article_properties WHERE id = :id')->execute(['id' => $id]);

        return ['ok' => true];
    }

    // ——— Werte ———

    /**
     * @return list<array<string, mixed>>
     */
    public static function valuesForArticle(int $articleId, bool $onlyActiveGroups = true): array
    {
        if ($articleId < 1 || !self::tablesReady()) {
            return [];
        }
        $sql = 'SELECT v.*, p.code AS property_code, p.label AS property_label,
                       p.data_type, p.unit, p.group_id, g.code AS group_code,
                       COALESCE(i.is_active, 0) AS group_instance_active
                FROM dg_article_property_values v
                INNER JOIN dg_article_properties p ON p.id = v.property_id
                INNER JOIN dg_article_property_groups g ON g.id = p.group_id
                LEFT JOIN dg_instance_property_groups i ON i.group_id = g.id
                WHERE v.article_id = :aid';
        if ($onlyActiveGroups) {
            $sql .= ' AND COALESCE(i.is_active, 0) = 1';
        }
        $sql .= ' ORDER BY COALESCE(i.sort_order, g.sort_order) ASC, p.sort_order ASC, p.id ASC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['aid' => $articleId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([self::class, 'normalizeValue'], $rows);
    }

    /**
     * Speichert einen typisierten Wert. Leerer Wert löscht den Datensatz.
     *
     * @param mixed $raw
     */
    public static function setValue(int $articleId, int $propertyId, mixed $raw): void
    {
        if ($articleId < 1 || $propertyId < 1 || !self::tablesReady()) {
            throw new InvalidArgumentException('Artikel oder Eigenschaft ungültig.');
        }
        $prop = self::findProperty($propertyId);
        if ($prop === null) {
            throw new InvalidArgumentException('Eigenschaft nicht gefunden.');
        }
        $packed = self::packValue((string) $prop['data_type'], $raw);
        $pdo = Database::pdo();

        if ($packed['empty']) {
            $pdo->prepare(
                'DELETE FROM dg_article_property_values
                 WHERE article_id = :aid AND property_id = :pid'
            )->execute(['aid' => $articleId, 'pid' => $propertyId]);

            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO dg_article_property_values
             (article_id, property_id, value_text, value_integer, value_decimal,
              value_boolean, value_date, value_json)
             VALUES
             (:aid, :pid, :value_text, :value_integer, :value_decimal,
              :value_boolean, :value_date, :value_json)
             ON DUPLICATE KEY UPDATE
               value_text = VALUES(value_text),
               value_integer = VALUES(value_integer),
               value_decimal = VALUES(value_decimal),
               value_boolean = VALUES(value_boolean),
               value_date = VALUES(value_date),
               value_json = VALUES(value_json)'
        );
        $stmt->execute([
            'aid' => $articleId,
            'pid' => $propertyId,
            'value_text' => $packed['value_text'],
            'value_integer' => $packed['value_integer'],
            'value_decimal' => $packed['value_decimal'],
            'value_boolean' => $packed['value_boolean'],
            'value_date' => $packed['value_date'],
            'value_json' => $packed['value_json'],
        ]);
    }

    /**
     * Packt einen Rohwert in typisierte Spalten (ohne DB).
     *
     * @return array{
     *   empty: bool,
     *   value_text: ?string,
     *   value_integer: ?int,
     *   value_decimal: ?string,
     *   value_boolean: ?int,
     *   value_date: ?string,
     *   value_json: ?string
     * }
     */
    public static function packValue(string $dataType, mixed $raw): array
    {
        $empty = [
            'empty' => true,
            'value_text' => null,
            'value_integer' => null,
            'value_decimal' => null,
            'value_boolean' => null,
            'value_date' => null,
            'value_json' => null,
        ];
        if ($raw === null) {
            return $empty;
        }

        return match ($dataType) {
            self::TYPE_INTEGER => self::packInteger($raw, $empty),
            self::TYPE_DECIMAL => self::packDecimal($raw, $empty),
            self::TYPE_BOOLEAN => self::packBoolean($raw, $empty),
            self::TYPE_DATE => self::packDate($raw, $empty),
            self::TYPE_SELECT => self::packSelect($raw, $empty),
            self::TYPE_MULTI_SELECT => self::packMultiSelect($raw, $empty),
            default => self::packText($raw, $empty),
        };
    }

    /**
     * Liest den kanonischen PHP-Wert aus einer normalisierten Value-Zeile.
     *
     * @param array<string, mixed> $row
     */
    public static function unpackValue(array $row): mixed
    {
        $type = (string) ($row['data_type'] ?? self::TYPE_TEXT);

        return match ($type) {
            self::TYPE_INTEGER => $row['value_integer'] !== null ? (int) $row['value_integer'] : null,
            self::TYPE_DECIMAL => $row['value_decimal'] !== null ? (string) $row['value_decimal'] : null,
            self::TYPE_BOOLEAN => $row['value_boolean'] !== null ? ((int) $row['value_boolean'] === 1) : null,
            self::TYPE_DATE => $row['value_date'] !== null ? (string) $row['value_date'] : null,
            self::TYPE_SELECT => $row['value_text'] !== null ? (string) $row['value_text'] : null,
            self::TYPE_MULTI_SELECT => self::decodeJsonList($row['value_json'] ?? null),
            default => $row['value_text'] !== null ? (string) $row['value_text'] : null,
        };
    }

    // ——— Normalize ———

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function normalizeGroup(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'code' => (string) ($row['code'] ?? ''),
            'label' => (string) ($row['label'] ?? ''),
            'description' => $row['description'] !== null ? (string) $row['description'] : null,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'is_system' => !empty($row['is_system']),
            'instance_active' => !empty($row['instance_active']),
            'instance_sort_order' => isset($row['instance_sort_order']) ? (int) $row['instance_sort_order'] : null,
            'instance_activated_at' => isset($row['instance_activated_at']) && $row['instance_activated_at'] !== null
                ? (string) $row['instance_activated_at']
                : null,
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : null,
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function normalizeProperty(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'group_id' => (int) ($row['group_id'] ?? 0),
            'group_code' => (string) ($row['group_code'] ?? ''),
            'group_label' => (string) ($row['group_label'] ?? ''),
            'group_instance_active' => !empty($row['group_instance_active']),
            'code' => (string) ($row['code'] ?? ''),
            'label' => (string) ($row['label'] ?? ''),
            'description' => $row['description'] !== null ? (string) $row['description'] : null,
            'data_type' => (string) ($row['data_type'] ?? self::TYPE_TEXT),
            'unit' => (string) ($row['unit'] ?? ''),
            'allowed_values' => self::decodeJsonList($row['allowed_values_json'] ?? null),
            'min_value' => $row['min_value'] !== null ? (string) $row['min_value'] : null,
            'max_value' => $row['max_value'] !== null ? (string) $row['max_value'] : null,
            'is_required' => !empty($row['is_required']),
            'is_filterable' => !empty($row['is_filterable']),
            'is_searchable' => !empty($row['is_searchable']),
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : null,
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function normalizeValue(array $row): array
    {
        $normalized = [
            'id' => (int) ($row['id'] ?? 0),
            'article_id' => (int) ($row['article_id'] ?? 0),
            'property_id' => (int) ($row['property_id'] ?? 0),
            'property_code' => (string) ($row['property_code'] ?? ''),
            'property_label' => (string) ($row['property_label'] ?? ''),
            'data_type' => (string) ($row['data_type'] ?? self::TYPE_TEXT),
            'unit' => (string) ($row['unit'] ?? ''),
            'group_id' => (int) ($row['group_id'] ?? 0),
            'group_code' => (string) ($row['group_code'] ?? ''),
            'group_instance_active' => !empty($row['group_instance_active']),
            'value_text' => $row['value_text'] !== null ? (string) $row['value_text'] : null,
            'value_integer' => $row['value_integer'] !== null ? (int) $row['value_integer'] : null,
            'value_decimal' => $row['value_decimal'] !== null ? (string) $row['value_decimal'] : null,
            'value_boolean' => $row['value_boolean'] !== null ? (int) $row['value_boolean'] : null,
            'value_date' => $row['value_date'] !== null ? (string) $row['value_date'] : null,
            'value_json' => $row['value_json'] !== null ? (string) $row['value_json'] : null,
        ];
        $normalized['value'] = self::unpackValue($normalized);

        return $normalized;
    }

    public static function normalizeCode(string $code): string
    {
        $code = strtolower(trim($code));
        $code = preg_replace('/[^a-z0-9_]+/', '_', $code) ?? '';
        $code = trim($code, '_');

        return mb_substr($code, 0, 64);
    }

    // ——— private helpers ———

    /**
     * @param array{empty: bool, value_text: ?string, value_integer: ?int, value_decimal: ?string, value_boolean: ?int, value_date: ?string, value_json: ?string} $empty
     * @return array{empty: bool, value_text: ?string, value_integer: ?int, value_decimal: ?string, value_boolean: ?int, value_date: ?string, value_json: ?string}
     */
    private static function packText(mixed $raw, array $empty): array
    {
        $text = trim((string) $raw);
        if ($text === '') {
            return $empty;
        }
        $empty['empty'] = false;
        $empty['value_text'] = $text;

        return $empty;
    }

    /**
     * @param array{empty: bool, value_text: ?string, value_integer: ?int, value_decimal: ?string, value_boolean: ?int, value_date: ?string, value_json: ?string} $empty
     * @return array{empty: bool, value_text: ?string, value_integer: ?int, value_decimal: ?string, value_boolean: ?int, value_date: ?string, value_json: ?string}
     */
    private static function packInteger(mixed $raw, array $empty): array
    {
        if ($raw === '' || $raw === null) {
            return $empty;
        }
        if (!is_numeric($raw)) {
            throw new InvalidArgumentException('Ganzzahl erwartet.');
        }
        $empty['empty'] = false;
        $empty['value_integer'] = (int) $raw;

        return $empty;
    }

    /**
     * @param array{empty: bool, value_text: ?string, value_integer: ?int, value_decimal: ?string, value_boolean: ?int, value_date: ?string, value_json: ?string} $empty
     * @return array{empty: bool, value_text: ?string, value_integer: ?int, value_decimal: ?string, value_boolean: ?int, value_date: ?string, value_json: ?string}
     */
    private static function packDecimal(mixed $raw, array $empty): array
    {
        if ($raw === '' || $raw === null) {
            return $empty;
        }
        $normalized = str_replace(',', '.', trim((string) $raw));
        if (!is_numeric($normalized)) {
            throw new InvalidArgumentException('Dezimalzahl erwartet.');
        }
        $empty['empty'] = false;
        $empty['value_decimal'] = number_format((float) $normalized, 6, '.', '');

        return $empty;
    }

    /**
     * @param array{empty: bool, value_text: ?string, value_integer: ?int, value_decimal: ?string, value_boolean: ?int, value_date: ?string, value_json: ?string} $empty
     * @return array{empty: bool, value_text: ?string, value_integer: ?int, value_decimal: ?string, value_boolean: ?int, value_date: ?string, value_json: ?string}
     */
    private static function packBoolean(mixed $raw, array $empty): array
    {
        if ($raw === '' || $raw === null) {
            return $empty;
        }
        $truthy = [true, 1, '1', 'true', 'yes', 'ja', 'on'];
        $falsy = [false, 0, '0', 'false', 'no', 'nein', 'off'];
        if (is_string($raw)) {
            $rawLower = strtolower(trim($raw));
        } else {
            $rawLower = $raw;
        }
        if (in_array($rawLower, $truthy, true) || in_array($raw, $truthy, true)) {
            $empty['empty'] = false;
            $empty['value_boolean'] = 1;

            return $empty;
        }
        if (in_array($rawLower, $falsy, true) || in_array($raw, $falsy, true)) {
            $empty['empty'] = false;
            $empty['value_boolean'] = 0;

            return $empty;
        }
        throw new InvalidArgumentException('Ja/Nein-Wert erwartet.');
    }

    /**
     * @param array{empty: bool, value_text: ?string, value_integer: ?int, value_decimal: ?string, value_boolean: ?int, value_date: ?string, value_json: ?string} $empty
     * @return array{empty: bool, value_text: ?string, value_integer: ?int, value_decimal: ?string, value_boolean: ?int, value_date: ?string, value_json: ?string}
     */
    private static function packDate(mixed $raw, array $empty): array
    {
        $text = trim((string) $raw);
        if ($text === '') {
            return $empty;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
            throw new InvalidArgumentException('Datum im Format JJJJ-MM-TT erwartet.');
        }
        $empty['empty'] = false;
        $empty['value_date'] = $text;

        return $empty;
    }

    /**
     * @param array{empty: bool, value_text: ?string, value_integer: ?int, value_decimal: ?string, value_boolean: ?int, value_date: ?string, value_json: ?string} $empty
     * @return array{empty: bool, value_text: ?string, value_integer: ?int, value_decimal: ?string, value_boolean: ?int, value_date: ?string, value_json: ?string}
     */
    private static function packSelect(mixed $raw, array $empty): array
    {
        $text = trim((string) $raw);
        if ($text === '') {
            return $empty;
        }
        $empty['empty'] = false;
        $empty['value_text'] = mb_substr($text, 0, 500);

        return $empty;
    }

    /**
     * @param array{empty: bool, value_text: ?string, value_integer: ?int, value_decimal: ?string, value_boolean: ?int, value_date: ?string, value_json: ?string} $empty
     * @return array{empty: bool, value_text: ?string, value_integer: ?int, value_decimal: ?string, value_boolean: ?int, value_date: ?string, value_json: ?string}
     */
    private static function packMultiSelect(mixed $raw, array $empty): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $list = is_array($decoded) ? $decoded : (preg_split('/\s*,\s*/', $raw) ?: []);
        } elseif (is_array($raw)) {
            $list = $raw;
        } else {
            return $empty;
        }
        $clean = [];
        foreach ($list as $item) {
            $s = trim((string) $item);
            if ($s !== '') {
                $clean[] = $s;
            }
        }
        $clean = array_values(array_unique($clean));
        if ($clean === []) {
            return $empty;
        }
        $empty['empty'] = false;
        $empty['value_json'] = json_encode($clean, JSON_UNESCAPED_UNICODE);

        return $empty;
    }

    private static function encodeAllowedValues(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            } else {
                $raw = preg_split('/\s*,\s*/', $raw) ?: [];
            }
        }
        if (!is_array($raw)) {
            return null;
        }
        $clean = [];
        foreach ($raw as $item) {
            $s = trim((string) $item);
            if ($s !== '') {
                $clean[] = $s;
            }
        }
        if ($clean === []) {
            return null;
        }

        return json_encode(array_values(array_unique($clean)), JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return list<string>
     */
    private static function decodeJsonList(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_array($raw)) {
            $list = $raw;
        } else {
            $decoded = json_decode((string) $raw, true);
            $list = is_array($decoded) ? $decoded : [];
        }
        $out = [];
        foreach ($list as $item) {
            $s = trim((string) $item);
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return $out;
    }

    private static function nullableDecimal(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $normalized = str_replace(',', '.', trim((string) $raw));
        if (!is_numeric($normalized)) {
            return null;
        }

        return number_format((float) $normalized, 6, '.', '');
    }
}
