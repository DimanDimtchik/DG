<?php
declare(strict_types=1);

/** HR-Teams: Stammdaten, Mitglieder (Kontakte), Inventar. */
final class TeamRepository
{
    public const ASSET_KINDS = [
        'vehicle' => 'Fahrzeug',
        'device' => 'Gerät',
        'other' => 'Sonstiges',
    ];

    public static function tableReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            MigrationRunner::runPending();
            $stmt = Database::pdo()->query("SHOW TABLES LIKE 'dg_teams'");

            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function list(?string $departmentId = null, bool $activeOnly = false): array
    {
        if (!self::tableReady()) {
            return [];
        }
        MigrationRunner::runPending();

        $sql = 'SELECT t.*, d.name AS department_name
                FROM dg_teams t
                LEFT JOIN dg_departments d ON d.id = t.department_id';
        $where = [];
        $params = [];
        if ($departmentId !== null && $departmentId !== '') {
            $where[] = 't.department_id = :department_id';
            $params['department_id'] = $departmentId;
        }
        if ($activeOnly) {
            $where[] = 't.is_active = 1';
        }
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY d.sort_order ASC, d.name ASC, t.sort_order ASC, t.name ASC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = [];
        while ($row = $stmt->fetch()) {
            $rows[] = self::hydrateListRow($row);
        }

        return $rows;
    }

    public static function findById(int $id): ?array
    {
        if ($id < 1 || !self::tableReady()) {
            return null;
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'SELECT t.*, d.name AS department_name
             FROM dg_teams t
             LEFT JOIN dg_departments d ON d.id = t.department_id
             WHERE t.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        $team = self::hydrateListRow($row);
        $team['members'] = self::membersForTeam($id);
        $team['assets'] = self::assetsForTeam($id);

        return $team;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function membersForTeam(int $teamId): array
    {
        if ($teamId < 1 || !self::tableReady()) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT tm.team_id, tm.contact_id, tm.member_role,
                    c.display_name, c.company_name, c.first_name, c.last_name, c.contact_role
             FROM dg_team_members tm
             INNER JOIN dg_contacts c ON c.id = tm.contact_id
             WHERE tm.team_id = :team_id
             ORDER BY tm.member_role DESC, c.display_name ASC, c.id ASC'
        );
        $stmt->execute(['team_id' => $teamId]);
        $out = [];
        while ($row = $stmt->fetch()) {
            $out[] = [
                'team_id' => (int) $row['team_id'],
                'contact_id' => (int) $row['contact_id'],
                'member_role' => (string) $row['member_role'] === 'lead' ? 'lead' : 'member',
                'label' => self::contactLabel($row),
                'contact_role' => (string) ($row['contact_role'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function assetsForTeam(int $teamId): array
    {
        if ($teamId < 1 || !self::tableReady()) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_team_assets WHERE team_id = :team_id ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['team_id' => $teamId]);
        $out = [];
        while ($row = $stmt->fetch()) {
            $kind = self::sanitizeAssetKind((string) ($row['kind'] ?? 'other'));
            $out[] = [
                'id' => (int) $row['id'],
                'team_id' => (int) $row['team_id'],
                'parent_asset_id' => isset($row['parent_asset_id']) && $row['parent_asset_id'] !== null
                    ? (int) $row['parent_asset_id'] : null,
                'kind' => $kind,
                'kind_label' => self::ASSET_KINDS[$kind] ?? $kind,
                'name' => (string) ($row['name'] ?? ''),
                'inventory_no' => (string) ($row['inventory_no'] ?? ''),
                'notes' => (string) ($row['notes'] ?? ''),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Teams eines Kontakts (für Kontaktformular).
     *
     * @return list<array<string, mixed>>
     */
    public static function teamsForContact(int $contactId): array
    {
        if ($contactId < 1 || !self::tableReady()) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT t.id, t.name, t.department_id, d.name AS department_name, tm.member_role
             FROM dg_team_members tm
             INNER JOIN dg_teams t ON t.id = tm.team_id
             LEFT JOIN dg_departments d ON d.id = t.department_id
             WHERE tm.contact_id = :cid
             ORDER BY d.name ASC, t.name ASC'
        );
        $stmt->execute(['cid' => $contactId]);
        $out = [];
        while ($row = $stmt->fetch()) {
            $out[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'department_id' => (string) ($row['department_id'] ?? ''),
                'department_name' => (string) ($row['department_name'] ?? ''),
                'member_role' => (string) $row['member_role'] === 'lead' ? 'lead' : 'member',
                'member_role_label' => (string) $row['member_role'] === 'lead' ? 'Teamleitung' : 'Mitglied',
            ];
        }

        return $out;
    }

    /**
     * Kontakt-IDs, die in mindestens einem Team der Abteilung sind.
     *
     * @return list<int>
     */
    public static function contactIdsInDepartmentTeams(string $departmentId): array
    {
        if ($departmentId === '' || !self::tableReady()) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT DISTINCT tm.contact_id
             FROM dg_team_members tm
             INNER JOIN dg_teams t ON t.id = tm.team_id
             WHERE t.department_id = :department_id'
        );
        $stmt->execute(['department_id' => $departmentId]);
        $ids = [];
        while ($row = $stmt->fetch()) {
            $ids[] = (int) $row['contact_id'];
        }

        return $ids;
    }

    /**
     * @return array{id: int}
     */
    public static function save(array $data, ?int $id, ?int $userId): array
    {
        if (!self::tableReady()) {
            throw new RuntimeException('Team-Tabellen nicht verfügbar.');
        }
        MigrationRunner::runPending();

        $departmentId = trim((string) ($data['department_id'] ?? ''));
        if ($departmentId === '' || !DepartmentRepository::exists($departmentId)) {
            throw new InvalidArgumentException('Bitte eine gültige Abteilung wählen.');
        }
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Teamname ist erforderlich.');
        }
        $description = trim((string) ($data['description'] ?? ''));
        $isActive = !empty($data['is_active']) ? 1 : 0;
        $sortOrder = (int) ($data['sort_order'] ?? 0);

        $pdo = Database::pdo();
        if ($id !== null && $id > 0) {
            $existing = self::findById($id);
            if ($existing === null) {
                throw new InvalidArgumentException('Team nicht gefunden.');
            }
            $pdo->prepare(
                'UPDATE dg_teams
                 SET department_id = :department_id, name = :name, description = :description,
                     is_active = :is_active, sort_order = :sort_order
                 WHERE id = :id'
            )->execute([
                'department_id' => $departmentId,
                'name' => mb_substr($name, 0, 191),
                'description' => $description !== '' ? $description : null,
                'is_active' => $isActive,
                'sort_order' => $sortOrder,
                'id' => $id,
            ]);
            $teamId = $id;
        } else {
            $pdo->prepare(
                'INSERT INTO dg_teams (department_id, name, description, is_active, sort_order, created_by)
                 VALUES (:department_id, :name, :description, :is_active, :sort_order, :created_by)'
            )->execute([
                'department_id' => $departmentId,
                'name' => mb_substr($name, 0, 191),
                'description' => $description !== '' ? $description : null,
                'is_active' => $isActive,
                'sort_order' => $sortOrder,
                'created_by' => $userId !== null && $userId > 0 ? $userId : null,
            ]);
            $teamId = (int) $pdo->lastInsertId();
        }

        self::replaceMembers($teamId, is_array($data['members'] ?? null) ? $data['members'] : []);
        self::replaceAssets($teamId, is_array($data['assets'] ?? null) ? $data['assets'] : []);

        return ['id' => $teamId];
    }

    public static function delete(int $id): void
    {
        if ($id < 1 || !self::tableReady()) {
            return;
        }
        Database::pdo()->prepare('DELETE FROM dg_teams WHERE id = :id')->execute(['id' => $id]);
    }

    /**
     * @param list<array<string, mixed>>|array<string, mixed> $members
     */
    private static function replaceMembers(int $teamId, array $members): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM dg_team_members WHERE team_id = :team_id')->execute(['team_id' => $teamId]);
        $ins = $pdo->prepare(
            'INSERT INTO dg_team_members (team_id, contact_id, member_role)
             VALUES (:team_id, :contact_id, :member_role)'
        );
        $seen = [];
        foreach ($members as $row) {
            if (!is_array($row)) {
                continue;
            }
            $contactId = (int) ($row['contact_id'] ?? 0);
            if ($contactId < 1 || isset($seen[$contactId])) {
                continue;
            }
            if (ContactRepository::findById($contactId) === null) {
                continue;
            }
            $role = (string) ($row['member_role'] ?? 'member') === 'lead' ? 'lead' : 'member';
            $ins->execute([
                'team_id' => $teamId,
                'contact_id' => $contactId,
                'member_role' => $role,
            ]);
            $seen[$contactId] = true;
        }
    }

    /**
     * @param list<array<string, mixed>>|array<string, mixed> $assets
     */
    private static function replaceAssets(int $teamId, array $assets): void
    {
        $pdo = Database::pdo();
        // Kinder zuerst lösen, dann alles löschen (FK parent)
        $pdo->prepare('UPDATE dg_team_assets SET parent_asset_id = NULL WHERE team_id = :team_id')
            ->execute(['team_id' => $teamId]);
        $pdo->prepare('DELETE FROM dg_team_assets WHERE team_id = :team_id')->execute(['team_id' => $teamId]);

        $ins = $pdo->prepare(
            'INSERT INTO dg_team_assets (team_id, parent_asset_id, kind, name, inventory_no, notes, sort_order)
             VALUES (:team_id, :parent_asset_id, :kind, :name, :inventory_no, :notes, :sort_order)'
        );

        // Zwei Durchläufe: zuerst Wurzeln (Fahrzeuge), dann Geräte mit temp-Index → echte IDs
        $indexToId = [];
        $pendingChildren = [];

        foreach (array_values($assets) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $kind = self::sanitizeAssetKind((string) ($row['kind'] ?? 'other'));
            $parentTemp = trim((string) ($row['parent_temp'] ?? ''));
            $parentIdRaw = (int) ($row['parent_asset_id'] ?? 0);
            $inventoryNo = mb_substr(trim((string) ($row['inventory_no'] ?? '')), 0, 64);
            $notes = trim((string) ($row['notes'] ?? ''));
            $sortOrder = (int) ($row['sort_order'] ?? $index);

            if ($parentTemp !== '' || $parentIdRaw > 0) {
                $pendingChildren[] = [
                    'temp_index' => $index,
                    'parent_temp' => $parentTemp,
                    'parent_asset_id' => $parentIdRaw,
                    'kind' => $kind,
                    'name' => $name,
                    'inventory_no' => $inventoryNo,
                    'notes' => $notes,
                    'sort_order' => $sortOrder,
                ];
                continue;
            }

            $ins->execute([
                'team_id' => $teamId,
                'parent_asset_id' => null,
                'kind' => $kind,
                'name' => mb_substr($name, 0, 191),
                'inventory_no' => $inventoryNo,
                'notes' => $notes !== '' ? $notes : null,
                'sort_order' => $sortOrder,
            ]);
            $indexToId[(string) $index] = (int) $pdo->lastInsertId();
            $tempKey = trim((string) ($row['temp_key'] ?? ''));
            if ($tempKey !== '') {
                $indexToId[$tempKey] = $indexToId[(string) $index];
            }
        }

        foreach ($pendingChildren as $child) {
            $parentId = null;
            if ($child['parent_temp'] !== '' && isset($indexToId[$child['parent_temp']])) {
                $parentId = $indexToId[$child['parent_temp']];
            } elseif ($child['parent_asset_id'] > 0 && in_array($child['parent_asset_id'], $indexToId, true)) {
                $parentId = $child['parent_asset_id'];
            } elseif ($child['parent_temp'] !== '' && isset($indexToId[$child['parent_temp']])) {
                $parentId = $indexToId[$child['parent_temp']];
            }
            // Falls Parent-Temp auf Index zeigt
            if ($parentId === null && $child['parent_temp'] !== '' && ctype_digit($child['parent_temp'])) {
                $parentId = $indexToId[$child['parent_temp']] ?? null;
            }

            $ins->execute([
                'team_id' => $teamId,
                'parent_asset_id' => $parentId,
                'kind' => $child['kind'],
                'name' => mb_substr($child['name'], 0, 191),
                'inventory_no' => $child['inventory_no'],
                'notes' => $child['notes'] !== '' ? $child['notes'] : null,
                'sort_order' => $child['sort_order'],
            ]);
        }
    }

    public static function sanitizeAssetKind(string $kind): string
    {
        $kind = strtolower(trim($kind));

        return isset(self::ASSET_KINDS[$kind]) ? $kind : 'other';
    }

    public static function emptyForm(): array
    {
        return [
            'department_id' => '',
            'name' => '',
            'description' => '',
            'is_active' => '1',
            'sort_order' => '0',
            'members' => [],
            'assets' => [],
        ];
    }

    public static function toForm(array $team): array
    {
        $assets = is_array($team['assets'] ?? null) ? $team['assets'] : [];
        $idToTemp = [];
        foreach ($assets as $i => $asset) {
            $temp = 'a' . $i;
            $idToTemp[(int) ($asset['id'] ?? 0)] = $temp;
            $assets[$i]['temp_key'] = $temp;
        }
        foreach ($assets as $i => $asset) {
            $parentId = (int) ($asset['parent_asset_id'] ?? 0);
            $assets[$i]['parent_temp'] = $parentId > 0 ? ($idToTemp[$parentId] ?? '') : '';
        }

        return [
            'department_id' => (string) ($team['department_id'] ?? ''),
            'name' => (string) ($team['name'] ?? ''),
            'description' => (string) ($team['description'] ?? ''),
            'is_active' => !empty($team['is_active']) ? '1' : '0',
            'sort_order' => (string) (int) ($team['sort_order'] ?? 0),
            'members' => is_array($team['members'] ?? null) ? $team['members'] : [],
            'assets' => $assets,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function hydrateListRow(array $row): array
    {
        $id = (int) ($row['id'] ?? 0);
        $memberCount = 0;
        $assetCount = 0;
        if ($id > 0 && self::tableReady()) {
            $c = Database::pdo()->prepare('SELECT COUNT(*) FROM dg_team_members WHERE team_id = :id');
            $c->execute(['id' => $id]);
            $memberCount = (int) $c->fetchColumn();
            $a = Database::pdo()->prepare('SELECT COUNT(*) FROM dg_team_assets WHERE team_id = :id');
            $a->execute(['id' => $id]);
            $assetCount = (int) $a->fetchColumn();
        }

        return [
            'id' => $id,
            'department_id' => (string) ($row['department_id'] ?? ''),
            'department_name' => (string) ($row['department_name'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'is_active' => !empty($row['is_active']),
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'member_count' => $memberCount,
            'asset_count' => $assetCount,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function contactLabel(array $row): string
    {
        $label = trim((string) ($row['display_name'] ?? ''));
        if ($label === '') {
            $label = trim((string) ($row['company_name'] ?? ''));
        }
        if ($label === '') {
            $label = trim(trim((string) ($row['first_name'] ?? '')) . ' ' . trim((string) ($row['last_name'] ?? '')));
        }

        return $label !== '' ? $label : ('Kontakt #' . (int) ($row['contact_id'] ?? 0));
    }
}
