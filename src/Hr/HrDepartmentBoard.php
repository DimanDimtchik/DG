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
        $contactLabels = self::employeeContactOptions();
        $ownership = CompanyExtendedSettings::resolveExecutiveOwnership();
        $chefUserId = (int) ($ownership['leader_user_id'] ?? 0);
        $chefContactId = 0;
        if ($chefUserId > 0) {
            $chefUser = UserRepository::findById($chefUserId);
            if ($chefUser !== null) {
                $chefContactId = DepartmentRepository::resolveContactIdForUser($chefUser);
            }
        }

        $out = [];
        foreach ($departments as $dept) {
            $deptId = (string) ($dept['id'] ?? '');
            $isExecutive = DepartmentRepository::isExecutiveDepartment($deptId, (string) ($dept['name'] ?? ''));
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
            $leadersOut = [];
            $regularOut = [];
            $withoutTeam = [];
            foreach ($dept['members'] as $member) {
                $contactId = (int) ($member['contact_id'] ?? 0);
                $userId = (int) ($member['user_id'] ?? 0);
                $role = (string) ($member['role'] ?? 'member');
                $isChefLeader = $isExecutive && $contactId > 0 && $chefContactId > 0 && $contactId === $chefContactId;
                $row = self::memberRow(
                    $contactId,
                    $userId,
                    $role,
                    $isChefLeader,
                    $contactLabels,
                    $inTeamLookup
                );
                $membersOut[] = $row;
                if ($row['role'] === 'leader') {
                    $leadersOut[] = $row;
                } else {
                    $regularOut[] = $row;
                }
                if (!$isChefLeader && !$row['in_team']) {
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
                'leaders' => $leadersOut,
                'regular_members' => $regularOut,
                'members_without_team' => $withoutTeam,
                'teams' => $teamDetails,
            ];
        }

        return $out;
    }

    /**
     * @param array<int, string> $contactLabels
     * @param array<int, true> $inTeamLookup
     * @return array<string, mixed>
     */
    private static function memberRow(
        int $contactId,
        int $userId,
        string $role,
        bool $isChefLeader,
        array $contactLabels,
        array $inTeamLookup
    ): array {
        $label = $contactLabels[$contactId] ?? ('Kontakt #' . $contactId);
        $hasCrm = $userId > 0;

        if ($isChefLeader) {
            $roleLabel = 'Chef / Abteilungsleiter';
        } elseif ($role === 'leader') {
            $roleLabel = 'Abteilungsleiter / Planner';
        } else {
            $roleLabel = 'Mitglied';
        }
        if (!$hasCrm) {
            $roleLabel .= ' · ohne CRM';
        }

        return [
            'contact_id' => $contactId,
            'user_id' => $userId,
            'role' => $isChefLeader ? 'leader' : $role,
            'role_label' => $roleLabel,
            'label' => $label,
            'in_team' => $contactId > 0 && isset($inTeamLookup[$contactId]),
            'auto_chef' => $isChefLeader,
            'has_crm_user' => $hasCrm,
        ];
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
}
