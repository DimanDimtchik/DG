<?php
declare(strict_types=1);

/** Read-only HR-Übersicht: Abteilungen mit Teams, Inventar-Hinweisen und MA ohne Team. */
final class HrDepartmentBoard
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function build(): array
    {
        $departments = DepartmentRepository::allWithMembers();
        $usersById = self::usersById();
        $contactByUserId = self::contactIdByUserId();
        $employeeContacts = self::employeeContactOptions();

        $out = [];
        foreach ($departments as $dept) {
            $deptId = (string) ($dept['id'] ?? '');
            $teams = TeamRepository::list($deptId, false);
            $teamDetails = [];
            foreach ($teams as $teamRow) {
                $full = TeamRepository::findById((int) $teamRow['id']);
                if ($full !== null) {
                    $teamDetails[] = $full;
                }
            }

            $inTeamContactIds = TeamRepository::contactIdsInDepartmentTeams($deptId);
            $inTeamLookup = array_fill_keys($inTeamContactIds, true);

            $membersOut = [];
            $withoutTeam = [];
            foreach ($dept['members'] as $member) {
                $userId = (int) ($member['user_id'] ?? 0);
                $user = $usersById[$userId] ?? null;
                $contactId = $contactByUserId[$userId] ?? 0;
                $label = $user !== null
                    ? (trim((string) ($user['display_name'] ?? '')) !== ''
                        ? (string) $user['display_name']
                        : (string) ($user['username'] ?? ('User #' . $userId)))
                    : ('User #' . $userId);
                if ($contactId > 0 && isset($employeeContacts[$contactId])) {
                    $label = $employeeContacts[$contactId];
                }
                $row = [
                    'user_id' => $userId,
                    'contact_id' => $contactId,
                    'role' => (string) ($member['role'] ?? 'member'),
                    'role_label' => (string) ($member['role'] ?? '') === 'leader' ? 'Abteilungsleiter / Planner' : 'Mitglied',
                    'label' => $label,
                    'in_team' => $contactId > 0 && isset($inTeamLookup[$contactId]),
                ];
                $membersOut[] = $row;
                if (!$row['in_team']) {
                    $withoutTeam[] = $row;
                }
            }

            $modules = is_array($dept['modules'] ?? null) ? $dept['modules'] : [];
            $moduleLabels = [];
            foreach ($modules as $key => $level) {
                if ((string) $level === 'none') {
                    continue;
                }
                $moduleLabels[] = (DepartmentAccess::MODULE_LABELS[$key] ?? $key) . ' (' . $level . ')';
            }

            $out[] = [
                'id' => $deptId,
                'name' => (string) ($dept['name'] ?? ''),
                'description' => (string) ($dept['description'] ?? ''),
                'is_hr' => !empty($dept['is_hr']),
                'allow_contact_delete' => !empty($dept['allow_contact_delete']),
                'allow_article_catalog' => !empty($dept['allow_article_catalog']),
                'sort_order' => (int) ($dept['sort_order'] ?? 0),
                'module_labels' => $moduleLabels,
                'members' => $membersOut,
                'members_without_team' => $withoutTeam,
                'teams' => $teamDetails,
            ];
        }

        return $out;
    }

    /**
     * @return array<int, string> contact_id => label
     */
    public static function employeeContactOptions(): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        $stmt = Database::pdo()->query(
            "SELECT id, display_name, company_name, first_name, last_name
             FROM dg_contacts
             WHERE contact_role IN ('dg_eigenmitarbeiter', 'administrator', 'mitarbeiter')
             ORDER BY display_name ASC, id ASC"
        );
        $out = [];
        while ($row = $stmt->fetch()) {
            $id = (int) $row['id'];
            $label = trim((string) ($row['display_name'] ?? ''));
            if ($label === '') {
                $label = trim((string) ($row['company_name'] ?? ''));
            }
            if ($label === '') {
                $label = trim(trim((string) ($row['first_name'] ?? '')) . ' ' . trim((string) ($row['last_name'] ?? '')));
            }
            $out[$id] = $label !== '' ? $label : ('Kontakt #' . $id);
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function usersById(): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        $stmt = Database::pdo()->query('SELECT id, username, display_name, email FROM dg_users');
        $out = [];
        while ($row = $stmt->fetch()) {
            $out[(int) $row['id']] = $row;
        }

        return $out;
    }

    /**
     * @return array<int, int> user_id => contact_id
     */
    private static function contactIdByUserId(): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        try {
            $check = Database::pdo()->query("SHOW TABLES LIKE 'dg_calendar_employees'");
            if ($check === false || $check->fetchColumn() === false) {
                return [];
            }
        } catch (Throwable) {
            return [];
        }
        $stmt = Database::pdo()->query(
            'SELECT user_id, contact_id FROM dg_calendar_employees
             WHERE user_id > 0 AND contact_id > 0'
        );
        $out = [];
        while ($row = $stmt->fetch()) {
            $out[(int) $row['user_id']] = (int) $row['contact_id'];
        }

        return $out;
    }
}
