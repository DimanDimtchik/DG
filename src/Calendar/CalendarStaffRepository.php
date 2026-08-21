<?php
declare(strict_types=1);

/**
 * Datenzugriff für Kalender-Mitarbeiter, Bereiche und Einsatzplanung.
 */
final class CalendarStaffRepository
{
    private const SLOT_STEP_MINUTES = 15;

    /**
     * Stellt Standarddaten in der Datenbank sicher.
     * @return void
     */
    public static function ensureSeeded(): void
    {
        if (!self::useDatabase()) {
            return;
        }

        $count = (int) Database::pdo()->query('SELECT COUNT(*) FROM dg_calendar_areas')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_calendar_areas (name, sort_order, is_active) VALUES (:name, :sort_order, 1)'
        );
        foreach ([['Nägel', 10], ['Massage', 20], ['Frisör', 30]] as [$name, $sort]) {
            $stmt->execute(['name' => $name, 'sort_order' => $sort]);
        }
    }

    /**
     * Liefert Kalender-Bereiche.
     * @param bool $activeOnly
     * @return list<array<string, mixed>>
     */
    public static function getAreas(bool $activeOnly = false): array
    {
        self::ensureSeeded();
        if (!self::useDatabase()) {
            return [];
        }

        $sql = 'SELECT * FROM dg_calendar_areas';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, name ASC';

        $rows = Database::pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$area) {
            $area['department_name'] = DepartmentRepository::departmentName((string) ($area['department_id'] ?? ''));
        }
        unset($area);

        return $rows;
    }

    /**
     * Liefert Kalender-Mitarbeiter.
     * @param bool $activeOnly
     * @return list<array<string, mixed>>
     */
    public static function getEmployees(bool $activeOnly = false): array
    {
        if (!self::useDatabase()) {
            return [];
        }

        $sql = 'SELECT * FROM dg_calendar_employees';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, name ASC';

        $employees = Database::pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($employees as &$employee) {
            $employee['area_ids'] = self::getEmployeeAreaIds((int) $employee['id']);
            $employee['contact_label'] = self::contactLabel((int) ($employee['contact_id'] ?? 0));
        }
        unset($employee);

        return $employees;
    }

    /**
     * Liefert all absences.
     * @return array<string, mixed>
     */
    public static function getAllAbsences(): array
    {
        if (!self::useDatabase()) {
            return [];
        }

        $stmt = Database::pdo()->query(
            'SELECT a.*, e.name AS employee_name
             FROM dg_calendar_employee_absences a
             INNER JOIN dg_calendar_employees e ON e.id = a.employee_id
             ORDER BY a.start_date DESC, a.end_date DESC, e.name ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Methode linkable users.
     * @return array<string, mixed>
     */
    public static function linkableUsers(): array
    {
        return UserRepository::all();
    }

    /**
     * Methode linkable contacts.
     * @param int $forEmployeeId
     * @return array<string, mixed>
     */
    public static function linkableContacts(int $forEmployeeId = 0): array
    {
        return ContactRepository::calendarLinkableContacts($forEmployeeId);
    }

    /**
     * Methode department member suggestions.
     * @return array<string, mixed>
     */
    public static function departmentMemberSuggestions(): array
    {
        if (!self::useDatabase()) {
            return [];
        }

        $existingByUser = [];
        $existingByContact = [];
        foreach (self::getEmployees() as $employee) {
            $employeeId = (int) $employee['id'];
            $userId = (int) ($employee['user_id'] ?? 0);
            $contactId = (int) ($employee['contact_id'] ?? 0);
            if ($userId > 0) {
                $existingByUser[$userId] = $employeeId;
            }
            if ($contactId > 0) {
                $existingByContact[$contactId] = $employeeId;
            }
        }

        $areasByDepartment = [];
        foreach (self::getAreas() as $area) {
            $departmentId = trim((string) ($area['department_id'] ?? ''));
            if ($departmentId === '' || empty($area['is_active'])) {
                continue;
            }
            $areaId = (int) $area['id'];
            $areasByDepartment[$departmentId]['ids'][] = $areaId;
            $areasByDepartment[$departmentId]['names'][] = (string) $area['name'];
        }

        $suggestions = [];
        foreach (DepartmentRepository::allWithMembers() as $department) {
            $departmentId = (string) $department['id'];
            $areaIds = $areasByDepartment[$departmentId]['ids'] ?? [];
            $areaNames = $areasByDepartment[$departmentId]['names'] ?? [];
            if ($areaIds === []) {
                continue;
            }

            foreach ($department['members'] as $member) {
                $userId = (int) ($member['user_id'] ?? 0);
                if ($userId < 1) {
                    continue;
                }

                $crmUser = UserRepository::findById($userId);
                if (!$crmUser) {
                    continue;
                }

                $contactId = ContactRepository::findStaffContactIdForUser($crmUser) ?? 0;
                $contactLabel = '';
                if ($contactId > 0) {
                    $contact = ContactRepository::findById($contactId);
                    $contactLabel = $contact ? $contact->listLabel() : '';
                }

                $calendarEmployeeId = $existingByUser[$userId]
                    ?? ($contactId > 0 ? ($existingByContact[$contactId] ?? 0) : 0);
                $alreadyEmployee = $calendarEmployeeId > 0;
                $hasContact = $contactId > 0;
                $canAdd = $hasContact && !$alreadyEmployee;

                $hint = '';
                if ($alreadyEmployee) {
                    $hint = 'Bereits als Kalender-Mitarbeiter angelegt.';
                } elseif (!$hasContact) {
                    $hint = 'Kein passender Mitarbeiter-Kontakt (gleiche E-Mail oder Login in Kontakte).';
                }

                $userLabel = trim($crmUser->displayName) !== '' ? $crmUser->displayName : $crmUser->username;

                $suggestions[] = [
                    'department_id' => $departmentId,
                    'department_name' => (string) $department['name'],
                    'member_role' => (string) ($member['role'] ?? ''),
                    'user_id' => $userId,
                    'user_label' => $userLabel,
                    'contact_id' => $contactId,
                    'contact_label' => $contactLabel,
                    'area_ids' => $areaIds,
                    'area_names' => $areaNames,
                    'has_contact' => $hasContact,
                    'already_employee' => $alreadyEmployee,
                    'calendar_employee_id' => $calendarEmployeeId,
                    'can_add' => $canAdd,
                    'hint' => $hint,
                ];
            }
        }

        usort(
            $suggestions,
            static function (array $left, array $right): int {
                if ($left['can_add'] !== $right['can_add']) {
                    return $left['can_add'] ? -1 : 1;
                }

                $departmentCompare = strcasecmp($left['department_name'], $right['department_name']);
                if ($departmentCompare !== 0) {
                    return $departmentCompare;
                }

                return strcasecmp($left['user_label'], $right['user_label']);
            }
        );

        return $suggestions;
    }

    /**
     * Methode area department map.
     * @return array<string, mixed>
     */
    public static function areaDepartmentMap(): array
    {
        $map = [];
        foreach (self::getAreas() as $area) {
            $departmentId = trim((string) ($area['department_id'] ?? ''));
            if ($departmentId !== '') {
                $map[(string) (int) $area['id']] = $departmentId;
            }
        }

        return $map;
    }

    /**
     * Speichert einen Kalender-Bereich.
     * @param array $input
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public static function saveArea(array $input): array
    {
        $id = (int) ($input['area_id'] ?? 0);
        $name = trim((string) ($input['name'] ?? ''));
        $sortOrder = max(0, (int) ($input['sort_order'] ?? 0));
        $isActive = empty($input['is_active']) ? 0 : 1;
        $departmentId = trim((string) ($input['department_id'] ?? ''));

        if ($name === '') {
            throw new InvalidArgumentException('Bitte geben Sie einen Bereichsnamen ein.');
        }
        if ($departmentId !== '' && !DepartmentRepository::exists($departmentId)) {
            throw new InvalidArgumentException('Die gewählte Abteilung existiert nicht.');
        }

        $pdo = Database::pdo();
        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE dg_calendar_areas
                 SET name = :name, sort_order = :sort_order, is_active = :is_active, department_id = :department_id
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => $id,
                'name' => $name,
                'sort_order' => $sortOrder,
                'is_active' => $isActive,
                'department_id' => $departmentId,
            ]);
            if ($stmt->rowCount() === 0 && self::getArea($id) === null) {
                throw new RuntimeException('Bereich nicht gefunden.');
            }

            return ['message' => 'Bereich aktualisiert.'];
        }

        $stmt = $pdo->prepare(
            'INSERT INTO dg_calendar_areas (name, department_id, sort_order, is_active)
             VALUES (:name, :department_id, :sort_order, :is_active)'
        );
        $stmt->execute([
            'name' => $name,
            'department_id' => $departmentId,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
        ]);

        return ['message' => 'Bereich gespeichert.'];
    }

    /**
     * Löscht einen Kalender-Bereich.
     * @param int $id
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    public static function deleteArea(int $id): array
    {
        if ($id < 1) {
            throw new InvalidArgumentException('ID erforderlich.');
        }

        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM dg_calendar_employee_areas WHERE area_id = :id')->execute(['id' => $id]);
        $pdo->prepare('DELETE FROM dg_calendar_areas WHERE id = :id')->execute(['id' => $id]);

        return ['message' => 'Bereich gelöscht.'];
    }

    /**
     * Speichert einen Kalender-Mitarbeiter.
     * @param array $input
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public static function saveEmployee(array $input): array
    {
        $id = (int) ($input['employee_id'] ?? 0);
        $name = trim((string) ($input['name'] ?? ''));
        $sortOrder = max(0, (int) ($input['sort_order'] ?? 0));
        $isActive = empty($input['is_active']) ? 0 : 1;
        $userId = max(0, (int) ($input['user_id'] ?? 0));
        $contactId = max(0, (int) ($input['contact_id'] ?? 0));
        $supervisorId = max(0, (int) ($input['supervisor_id'] ?? 0));
        $areaIds = array_values(array_filter(array_map('intval', (array) ($input['area_ids'] ?? []))));

        if ($contactId > 0) {
            $contact = ContactRepository::findById($contactId);
            if ($contact === null || !ContactRepository::isStaffContactRole($contact->contactRole)) {
                throw new InvalidArgumentException('Der gewählte Kontakt ist kein Mitarbeiter-Kontakt.');
            }
            if ($name === '') {
                $name = $contact->listLabel();
            }
        }

        if ($name === '') {
            throw new InvalidArgumentException('Bitte geben Sie einen Namen ein oder wählen Sie einen Kontakt.');
        }
        if ($areaIds === []) {
            throw new InvalidArgumentException('Bitte wählen Sie mindestens einen Bereich aus.');
        }
        if ($userId > 0 && UserRepository::findById($userId) === null) {
            throw new InvalidArgumentException('Der gewählte CRM-Benutzer existiert nicht.');
        }
        if ($supervisorId > 0) {
            if ($supervisorId === $id) {
                throw new InvalidArgumentException('Ein Mitarbeiter kann nicht sein eigener Vorgesetzter sein.');
            }
            if (self::getEmployee($supervisorId) === null) {
                throw new InvalidArgumentException('Der gewählte Vorgesetzte existiert nicht.');
            }
        }

        $pdo = Database::pdo();
        if ($contactId > 0) {
            $conflictSql = 'SELECT id FROM dg_calendar_employees WHERE contact_id = :contact_id';
            $conflictParams = ['contact_id' => $contactId];
            if ($id > 0) {
                $conflictSql .= ' AND id != :id';
                $conflictParams['id'] = $id;
            }
            $stmt = $pdo->prepare($conflictSql);
            $stmt->execute($conflictParams);
            if ($stmt->fetchColumn()) {
                throw new InvalidArgumentException('Dieser Kontakt ist bereits einem anderen Kalender-Mitarbeiter zugeordnet.');
            }
        }
        if ($userId > 0) {
            $conflictSql = 'SELECT id FROM dg_calendar_employees WHERE user_id = :user_id';
            $params = ['user_id' => $userId];
            if ($id > 0) {
                $conflictSql .= ' AND id != :id';
                $params['id'] = $id;
            }
            $stmt = $pdo->prepare($conflictSql);
            $stmt->execute($params);
            if ($stmt->fetchColumn()) {
                throw new InvalidArgumentException('Dieser CRM-Benutzer ist bereits einem anderen Kalender-Mitarbeiter zugeordnet.');
            }
        }

        if ($id > 0) {
            if (self::getEmployee($id) === null) {
                throw new RuntimeException('Mitarbeiter nicht gefunden.');
            }
            $stmt = $pdo->prepare(
                'UPDATE dg_calendar_employees
                 SET name = :name, contact_id = :contact_id, sort_order = :sort_order, is_active = :is_active,
                     user_id = :user_id, supervisor_id = :supervisor_id
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => $id,
                'name' => $name,
                'contact_id' => $contactId,
                'sort_order' => $sortOrder,
                'is_active' => $isActive,
                'user_id' => $userId,
                'supervisor_id' => $supervisorId,
            ]);
            self::saveEmployeeAreas($id, $areaIds);

            return ['message' => 'Mitarbeiter aktualisiert.'];
        }

        $stmt = $pdo->prepare(
            'INSERT INTO dg_calendar_employees (name, contact_id, sort_order, is_active, user_id, supervisor_id)
             VALUES (:name, :contact_id, :sort_order, :is_active, :user_id, :supervisor_id)'
        );
        $stmt->execute([
            'name' => $name,
            'contact_id' => $contactId,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
            'user_id' => $userId,
            'supervisor_id' => $supervisorId,
        ]);
        $newId = (int) $pdo->lastInsertId();
        self::saveEmployeeAreas($newId, $areaIds);

        return ['message' => 'Mitarbeiter gespeichert.'];
    }

    /**
     * Löscht einen Kalender-Mitarbeiter.
     * @param int $id
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public static function deleteEmployee(int $id): array
    {
        if ($id < 1) {
            throw new InvalidArgumentException('ID erforderlich.');
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM dg_bookings
             WHERE employee_id = :id AND LOWER(status) NOT IN ('storniert', 'cancelled', 'canceled', 'cancel')"
        );
        $stmt->execute(['id' => $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException('Mitarbeiter hat noch aktive Buchungen. Bitte deaktivieren statt löschen.');
        }

        $pdo->prepare('DELETE FROM dg_calendar_employee_areas WHERE employee_id = :id')->execute(['id' => $id]);
        $pdo->prepare('DELETE FROM dg_calendar_employee_hours WHERE employee_id = :id')->execute(['id' => $id]);
        $pdo->prepare('DELETE FROM dg_calendar_employee_absences WHERE employee_id = :id')->execute(['id' => $id]);
        $pdo->prepare('DELETE FROM dg_calendar_employees WHERE id = :id')->execute(['id' => $id]);

        return ['message' => 'Mitarbeiter gelöscht.'];
    }

    /**
     * Speichert absence.
     * @param array $input
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    public static function saveAbsence(array $input): array
    {
        $employeeId = (int) ($input['employee_id'] ?? 0);
        $type = (string) ($input['absence_type'] ?? 'vacation');
        $startDate = trim((string) ($input['start_date'] ?? ''));
        $endDate = trim((string) ($input['end_date'] ?? ''));
        $note = trim((string) ($input['note'] ?? ''));

        if ($employeeId < 1 || self::getEmployee($employeeId) === null) {
            throw new InvalidArgumentException('Mitarbeiter nicht gefunden.');
        }
        if (!in_array($type, ['vacation', 'sick', 'other'], true)) {
            $type = 'vacation';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            throw new InvalidArgumentException('Gültiges Von- und Bis-Datum erforderlich.');
        }
        if ($endDate < $startDate) {
            throw new InvalidArgumentException('Das Enddatum darf nicht vor dem Startdatum liegen.');
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_calendar_employee_absences (employee_id, absence_type, start_date, end_date, note)
             VALUES (:employee_id, :absence_type, :start_date, :end_date, :note)'
        );
        $stmt->execute([
            'employee_id' => $employeeId,
            'absence_type' => $type,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'note' => $note,
        ]);

        return ['message' => 'Abwesenheit gespeichert.'];
    }

    /**
     * Führt aus: delete absence.
     * @param int $id
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    public static function deleteAbsence(int $id): array
    {
        if ($id < 1) {
            throw new InvalidArgumentException('ID erforderlich.');
        }
        Database::pdo()->prepare('DELETE FROM dg_calendar_employee_absences WHERE id = :id')->execute(['id' => $id]);

        return ['message' => 'Abwesenheit gelöscht.'];
    }

    /**
     * Speichert Arbeitszeiten eines Mitarbeiters.
     * @param int $employeeId Mitarbeiter-/Benutzer-ID
     * @param array $windows Zeitfenster
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    public static function saveEmployeeHours(int $employeeId, array $windows): array
    {
        if ($employeeId < 1 || self::getEmployee($employeeId) === null) {
            throw new InvalidArgumentException('Mitarbeiter nicht gefunden.');
        }

        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM dg_calendar_employee_hours WHERE employee_id = :id')->execute(['id' => $employeeId]);

        $stmt = $pdo->prepare(
            'INSERT INTO dg_calendar_employee_hours (employee_id, weekday, start_time, end_time)
             VALUES (:employee_id, :weekday, :start_time, :end_time)'
        );

        $saved = 0;
        foreach ($windows as $window) {
            $weekday = (int) ($window['weekday'] ?? 0);
            if ($weekday < 1 || $weekday > 7) {
                continue;
            }
            $start = self::normalizeTime((string) ($window['start_time'] ?? ''));
            $end = self::normalizeTime((string) ($window['end_time'] ?? ''));
            if ($start === '' || $end === '' || $start >= $end) {
                continue;
            }
            $stmt->execute([
                'employee_id' => $employeeId,
                'weekday' => $weekday,
                'start_time' => $start,
                'end_time' => $end,
            ]);
            $saved++;
        }

        return ['message' => 'Arbeitszeiten gespeichert.', 'count' => $saved];
    }

    /**
     * Führt aus: parse hours windows.
     * @param array $input Eingabedaten
     * @return list<array{weekday: int, start_time: string, end_time: string}>
     */
    public static function parseHoursWindows(array $input): array
    {
        $raw = [];
        if (!empty($input['windows_json'])) {
            $decoded = json_decode((string) $input['windows_json'], true);
            if (is_array($decoded)) {
                $raw = $decoded;
            }
        }

        $windows = [];
        foreach ($raw as $window) {
            if (!is_array($window)) {
                continue;
            }
            $windows[] = [
                'weekday' => (int) ($window['weekday'] ?? 0),
                'start_time' => trim((string) ($window['start_time'] ?? '')),
                'end_time' => trim((string) ($window['end_time'] ?? '')),
            ];
        }

        return $windows;
    }

    /**
     * Führt aus: render hours editor html.
     * @param int $employeeId
     * @return string
     * @throws InvalidArgumentException
     */
    public static function renderHoursEditorHtml(int $employeeId): string
    {
        $employee = self::getEmployee($employeeId);
        if ($employee === null) {
            throw new InvalidArgumentException('Mitarbeiter nicht gefunden.');
        }

        $labels = self::weekdayLabels();
        $hours = self::getEmployeeHours($employeeId);
        $byDay = [];
        foreach (array_keys($labels) as $wd) {
            $byDay[$wd] = [];
        }
        foreach ($hours as $row) {
            $wd = (int) $row['weekday'];
            if (isset($byDay[$wd])) {
                $byDay[$wd][] = $row;
            }
        }

        ob_start();
        ?>
        <div class="dg-cal-schedule-panel" data-employee-id="<?= (int) $employeeId ?>">
          <p class="dg-lead">Wöchentliche Arbeitszeiten für <strong><?= View::escape((string) $employee['name']) ?></strong> (Raster: <?= self::SLOT_STEP_MINUTES ?> Minuten). Ohne Zeitfenster ist der Mitarbeiter nicht buchbar.</p>
          <div class="dg-cal-schedule-editor">
            <?php foreach ($labels as $weekday => $label) : ?>
              <div class="dg-cal-schedule-day" data-weekday="<?= (int) $weekday ?>">
                <div class="dg-cal-schedule-day__label"><?= View::escape($label) ?></div>
                <div class="dg-cal-schedule-day__windows">
                  <?php
                    $dayWindows = $byDay[$weekday] !== [] ? $byDay[$weekday] : [['start_time' => '', 'end_time' => '']];
                    foreach ($dayWindows as $window) :
                        $start = self::formatTimeHm((string) ($window['start_time'] ?? ''));
                        $end = self::formatTimeHm((string) ($window['end_time'] ?? ''));
                  ?>
                    <div class="dg-cal-schedule-window">
                      <?= self::timeSelectHtml('schedule-start', $start) ?>
                      <span class="dg-cal-schedule-sep">–</span>
                      <?= self::timeSelectHtml('schedule-end', $end) ?>
                      <button type="button" class="dg-button dg-button--small dg-cal-remove-window" title="Fenster entfernen">&times;</button>
                    </div>
                  <?php endforeach; ?>
                </div>
                <button type="button" class="dg-button dg-button--small dg-cal-add-window">+ Fenster</button>
              </div>
            <?php endforeach; ?>
            <p>
              <button type="button" class="dg-button dg-button--primary dg-cal-save-hours" data-employee-id="<?= (int) $employeeId ?>">Arbeitszeiten speichern</button>
            </p>
          </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * Methode employee has hours.
     * @param int $employeeId
     * @return bool
     */
    public static function employeeHasHours(int $employeeId): bool
    {
        return self::getEmployeeHours($employeeId) !== [];
    }

    /**
     * Methode hours summary.
     * @param int $employeeId
     * @return string
     */
    public static function hoursSummary(int $employeeId): string
    {
        $hours = self::getEmployeeHours($employeeId);
        if ($hours === []) {
            return '';
        }

        $labels = self::weekdayLabels();
        $byDay = [];
        foreach ($hours as $row) {
            $wd = (int) $row['weekday'];
            $byDay[$wd][] = self::formatTimeHm((string) $row['start_time']) . '–' . self::formatTimeHm((string) $row['end_time']);
        }

        $parts = [];
        foreach ($labels as $wd => $label) {
            if (!empty($byDay[$wd])) {
                $parts[] = $label . ' ' . implode(', ', $byDay[$wd]);
            }
        }

        return implode('; ', $parts);
    }

    /**
     * Methode absence type label.
     * @param string $type
     * @return string
     */
    public static function absenceTypeLabel(string $type): string
    {
        return match ($type) {
            'vacation' => 'Urlaub',
            'sick' => 'Krank',
            default => 'Sonstiges',
        };
    }

    /**
     * Liefert employee by id.
     * @param int $id
     * @return array|null
     */
    public static function getEmployeeById(int $id): ?array
    {
        return self::getEmployee($id);
    }

    /**
     * Prüft: is employee absent on date.
     * @param int $employeeId
     * @param string $dateYmd
     * @return bool
     */
    public static function isEmployeeAbsentOnDate(int $employeeId, string $dateYmd): bool
    {
        if ($employeeId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd) || !self::useDatabase()) {
            return false;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM dg_calendar_employee_absences
             WHERE employee_id = :employee_id AND start_date <= :date AND end_date >= :date'
        );
        $stmt->execute(['employee_id' => $employeeId, 'date' => $dateYmd]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Liefert hours for weekday.
     * @param int $employeeId
     * @param int $weekday
     * @return array<string, mixed>
     */
    public static function getHoursForWeekday(int $employeeId, int $weekday): array
    {
        $windows = [];
        foreach (self::getEmployeeHours($employeeId) as $row) {
            if ((int) ($row['weekday'] ?? 0) !== $weekday) {
                continue;
            }
            $windows[] = [
                'start_time' => (string) ($row['start_time'] ?? ''),
                'end_time' => (string) ($row['end_time'] ?? ''),
            ];
        }

        return $windows;
    }

    /**
     * Methode window bounds on day.
     * @param DateTimeImmutable $day
     * @param string $startTime
     * @param string $endTime
     * @return array|null
     */
    public static function windowBoundsOnDay(DateTimeImmutable $day, string $startTime, string $endTime): ?array
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})/', trim($startTime), $startMatch)) {
            return null;
        }
        if (!preg_match('/^(\d{1,2}):(\d{2})/', trim($endTime), $endMatch)) {
            return null;
        }

        $windowStart = $day->setTime((int) $startMatch[1], (int) $startMatch[2], 0);
        $windowEnd = $day->setTime((int) $endMatch[1], (int) $endMatch[2], 0);

        if ($windowEnd <= $windowStart) {
            return null;
        }

        return [$windowStart, $windowEnd];
    }

    /**
     * Prüft: is employee working at.
     * @param int $employeeId
     * @param DateTimeImmutable $slotStart
     * @param DateTimeImmutable $slotEnd
     * @return bool
     */
    public static function isEmployeeWorkingAt(
        int $employeeId,
        DateTimeImmutable $slotStart,
        DateTimeImmutable $slotEnd,
    ): bool {
        if ($employeeId < 1) {
            return false;
        }

        $weekday = (int) $slotStart->format('N');
        $windows = self::getHoursForWeekday($employeeId, $weekday);
        if ($windows === []) {
            return false;
        }

        foreach ($windows as $window) {
            $bounds = self::windowBoundsOnDay($slotStart, $window['start_time'], $window['end_time']);
            if ($bounds === null) {
                continue;
            }

            [$windowStart, $windowEnd] = $bounds;
            if ($slotStart >= $windowStart && $slotEnd <= $windowEnd) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prüft: is employee schedulable at.
     * @param int $employeeId
     * @param string $slotDatetime
     * @param int $durationMinutes
     * @return bool
     */
    public static function isEmployeeSchedulableAt(int $employeeId, string $slotDatetime, int $durationMinutes): bool
    {
        if ($employeeId < 1 || $durationMinutes < 1) {
            return false;
        }

        $normalized = BookingSlotService::normalizeSlotDatetime($slotDatetime);
        $slotStart = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $normalized, new DateTimeZone((string) App::config('timezone', 'Europe/Berlin')));
        if (!$slotStart) {
            return false;
        }

        if (self::isEmployeeAbsentOnDate($employeeId, $slotStart->format('Y-m-d'))) {
            return false;
        }

        $slotEnd = $slotStart->modify('+' . $durationMinutes . ' minutes');

        return self::isEmployeeWorkingAt($employeeId, $slotStart, $slotEnd);
    }

    /**
     * Liefert merged day windows for employees.
     * @param list<int> $employeeIds
     * @param DateTimeImmutable $day
     * @return list<array{0: DateTimeImmutable, 1: DateTimeImmutable}>
     */
    public static function getMergedDayWindowsForEmployees(array $employeeIds, DateTimeImmutable $day): array
    {
        $weekday = (int) $day->format('N');
        $ranges = [];

        foreach ($employeeIds as $employeeId) {
            $employeeId = (int) $employeeId;
            if ($employeeId < 1) {
                continue;
            }
            if (self::isEmployeeAbsentOnDate($employeeId, $day->format('Y-m-d'))) {
                continue;
            }

            foreach (self::getHoursForWeekday($employeeId, $weekday) as $window) {
                $bounds = self::windowBoundsOnDay($day, $window['start_time'], $window['end_time']);
                if ($bounds !== null) {
                    $ranges[] = $bounds;
                }
            }
        }

        if ($ranges === []) {
            return [];
        }

        usort($ranges, static fn(array $a, array $b): int => $a[0] <=> $b[0]);

        $merged = [];
        foreach ($ranges as $range) {
            if ($merged === []) {
                $merged[] = $range;
                continue;
            }

            $lastIndex = count($merged) - 1;
            if ($range[0] <= $merged[$lastIndex][1]) {
                if ($range[1] > $merged[$lastIndex][1]) {
                    $merged[$lastIndex][1] = $range[1];
                }
            } else {
                $merged[] = $range;
            }
        }

        return $merged;
    }

    /**
     * Liefert employee day windows.
     * @param int $employeeId
     * @param DateTimeImmutable $day
     * @return array<string, mixed>
     */
    public static function getEmployeeDayWindows(int $employeeId, DateTimeImmutable $day): array
    {
        return self::getMergedDayWindowsForEmployees([$employeeId], $day);
    }

    /**
     * Prüft: has active employees.
     * @return bool
     */
    public static function hasActiveEmployees(): bool
    {
        if (!self::useDatabase()) {
            return false;
        }

        $count = (int) Database::pdo()->query('SELECT COUNT(*) FROM dg_calendar_employees WHERE is_active = 1')->fetchColumn();

        return $count > 0;
    }

    /**
     * Liefert employee ids for area.
     * @param int $areaId
     * @return array<string, mixed>
     */
    public static function getEmployeeIdsForArea(int $areaId): array
    {
        if ($areaId < 1 || !self::useDatabase()) {
            return [];
        }

        $area = self::getArea($areaId);
        if ($area === null) {
            return [];
        }

        $stmt = Database::pdo()->prepare(
            'SELECT DISTINCT e.id
             FROM dg_calendar_employees e
             INNER JOIN dg_calendar_employee_areas ea ON ea.employee_id = e.id
             WHERE ea.area_id = :area_id AND e.is_active = 1
             ORDER BY e.sort_order ASC, e.name ASC'
        );
        $stmt->execute(['area_id' => $areaId]);
        $employeeIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

        $departmentId = trim((string) ($area['department_id'] ?? ''));
        if ($departmentId === '') {
            return $employeeIds;
        }

        $deptUserIds = DepartmentRepository::userIdsForDepartment($departmentId);

        return array_values(array_filter(
            $employeeIds,
            static function (int $employeeId) use ($deptUserIds): bool {
                $employee = self::getEmployeeById($employeeId);
                if ($employee === null) {
                    return false;
                }

                $userId = (int) ($employee['user_id'] ?? 0);
                if ($userId > 0) {
                    return in_array($userId, $deptUserIds, true);
                }

                return (int) ($employee['contact_id'] ?? 0) > 0;
            }
        ));
    }

    /**
     * Uses Employee Scheduling For Article.
     * @param int $articleId
     * @return bool
     */
    public static function usesEmployeeSchedulingForArticle(int $articleId): bool
    {
        if ($articleId < 1 || !self::hasActiveEmployees()) {
            return false;
        }

        $areaId = CalendarArticleRepository::getAreaId($articleId);

        return $areaId > 0 && self::getEmployeeIdsForArea($areaId) !== [];
    }

    /**
     * Liefert qualified employee ids for article.
     * @param int $articleId
     * @return array<string, mixed>
     */
    public static function getQualifiedEmployeeIdsForArticle(int $articleId): array
    {
        return self::getEmployeeIdsForArea(CalendarArticleRepository::getAreaId($articleId));
    }

    /**
     * Liefert merged day windows for article.
     * @param int $articleId
     * @param DateTimeImmutable $day
     * @return array<string, mixed>
     */
    public static function getMergedDayWindowsForArticle(int $articleId, DateTimeImmutable $day): array
    {
        return self::getMergedDayWindowsForEmployees(self::getQualifiedEmployeeIdsForArticle($articleId), $day);
    }

    /**
     * Methode booking employee options.
     * @return array<string, mixed>
     */
    public static function bookingEmployeeOptions(): array
    {
        $options = [];
        foreach (self::getEmployees(true) as $employee) {
            $id = (int) ($employee['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $options[] = [
                'id' => $id,
                'name' => (string) ($employee['name'] ?? ''),
                'area_ids' => array_map('intval', $employee['area_ids'] ?? []),
            ];
        }

        return $options;
    }

    /**
     * Methode employee name.
     * @param int $employeeId
     * @return string
     */
    public static function employeeName(int $employeeId): string
    {
        $employee = self::getEmployeeById($employeeId);

        return $employee ? (string) ($employee['name'] ?? '') : '';
    }

    /**
     * Liefert Uhrzeit-Optionen für Select-Felder.
     * @return list<string>
     */
    public static function timeOptions(): array
    {
        $options = [];
        for ($hour = 0; $hour < 24; $hour++) {
            for ($minute = 0; $minute < 60; $minute += self::SLOT_STEP_MINUTES) {
                $options[] = sprintf('%02d:%02d', $hour, $minute);
            }
        }

        return $options;
    }

    /**
     * Liefert Wochentags-Bezeichnungen.
     * @return array<int, string>
     */
    public static function weekdayLabels(): array
    {
        return [
            1 => 'Montag',
            2 => 'Dienstag',
            3 => 'Mittwoch',
            4 => 'Donnerstag',
            5 => 'Freitag',
            6 => 'Samstag',
            7 => 'Sonntag',
        ];
    }

    /**
     * Liefert einen Kalender-Bereich.
     * @param int $id
     * @return array|null
     */
    private static function getArea(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_calendar_areas WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Liefert einen Kalender-Mitarbeiter.
     * @param int $id
     * @return array|null
     */
    private static function getEmployee(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_calendar_employees WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['area_ids'] = self::getEmployeeAreaIds($id);

        return $row;
    }

    /**
     * Liefert employee area ids.
     * @param int $employeeId
     * @return array<string, mixed>
     */
    private static function getEmployeeAreaIds(int $employeeId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT area_id FROM dg_calendar_employee_areas WHERE employee_id = :employee_id'
        );
        $stmt->execute(['employee_id' => $employeeId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * Speichert employee areas.
     * @param int $employeeId
     * @param array $areaIds
     * @return void
     */
    private static function saveEmployeeAreas(int $employeeId, array $areaIds): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM dg_calendar_employee_areas WHERE employee_id = :id')->execute(['id' => $employeeId]);
        $stmt = $pdo->prepare(
            'INSERT INTO dg_calendar_employee_areas (employee_id, area_id) VALUES (:employee_id, :area_id)'
        );
        foreach ($areaIds as $areaId) {
            if ($areaId > 0) {
                $stmt->execute(['employee_id' => $employeeId, 'area_id' => $areaId]);
            }
        }
    }

    /**
     * Liefert Arbeitszeiten eines Mitarbeiters.
     * @param int $employeeId
     * @return list<array<string, mixed>>
     */
    private static function getEmployeeHours(int $employeeId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_calendar_employee_hours WHERE employee_id = :employee_id ORDER BY weekday ASC, start_time ASC'
        );
        $stmt->execute(['employee_id' => $employeeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Erzeugt HTML für eine Uhrzeit-Auswahl.
     * @param string $class
     * @param string $selected
     * @return string
     */
    private static function timeSelectHtml(string $class, string $selected): string
    {
        $html = '<select class="' . View::escape($class) . '"><option value="">—</option>';
        foreach (self::timeOptions() as $option) {
            $sel = $option === $selected ? ' selected' : '';
            $html .= '<option value="' . View::escape($option) . '"' . $sel . '>' . View::escape($option) . '</option>';
        }
        $html .= '</select>';

        return $html;
    }

    /**
     * Führt aus: normalize time.
     * @param string $value
     * @return string
     */
    private static function normalizeTime(string $value): string
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})/', trim($value), $m)) {
            return '';
        }
        $total = ((int) $m[1] * 60) + (int) $m[2];
        $snapped = (int) round($total / self::SLOT_STEP_MINUTES) * self::SLOT_STEP_MINUTES;
        if ($snapped >= 24 * 60) {
            $snapped = 24 * 60 - self::SLOT_STEP_MINUTES;
        }

        return sprintf('%02d:%02d:00', intdiv($snapped, 60), $snapped % 60);
    }

    /**
     * Methode format time hm.
     * @param string $time
     * @return string
     */
    private static function formatTimeHm(string $time): string
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})/', $time, $m)) {
            return '';
        }

        return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
    }

    /**
     * Prüft, ob die Datenbanktabelle verfügbar ist.
     * @return bool
     */
    private static function useDatabase(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            Database::pdo()->query('SELECT 1 FROM dg_calendar_areas LIMIT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Methode contact label.
     * @param int $contactId
     * @return string
     */
    private static function contactLabel(int $contactId): string
    {
        if ($contactId < 1) {
            return '';
        }

        $contact = ContactRepository::findById($contactId);

        return $contact ? $contact->listLabel() : '';
    }
}
