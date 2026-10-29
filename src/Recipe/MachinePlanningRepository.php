<?php
declare(strict_types=1);

/**
 * Maschinenblock M0b: Kapazitaet, Zusatzmaterial und Wartung ohne UI/Buchungswirkung.
 */
final class MachinePlanningRepository
{
    private const AUX_ROLES = ['packaging', 'additive', 'consumable', 'other'];
    private const INTERVAL_TYPES = ['days', 'runtime_hours', 'cycles'];
    private const BOOKING_TYPES = ['production', 'maintenance', 'blocked', 'other'];
    private const BOOKING_STATUSES = ['planned', 'done', 'cancelled'];

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

    /** @param array<string, mixed> $data */
    public static function createBookingWindow(array $data): int
    {
        self::ensureReady();
        self::requireDatabase();
        $row = self::normalizeBookingWindowPayload($data, false);
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_machine_booking_windows
                (work_center_id, booking_type, recipe_id, maintenance_plan_id, maintenance_log_id,
                 title, starts_at, ends_at, planned_output_qty, unit, booking_status, notes)
             VALUES
                (:work_center_id, :booking_type, :recipe_id, :maintenance_plan_id, :maintenance_log_id,
                 :title, :starts_at, :ends_at, :planned_output_qty, :unit, :booking_status, :notes)'
        );
        $stmt->execute($row);

        return (int) Database::pdo()->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public static function findBookingWindow(int $id): ?array
    {
        self::ensureReady();
        if ($id <= 0 || !Database::isConfigured()) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_machine_booking_windows WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Aktualisiert ein Belegungsfenster (Felder + Status geplant/erledigt).
     * Stornierte Einträge werden nicht wieder geöffnet — dafür neu anlegen.
     *
     * @param array<string, mixed> $data
     */
    public static function updateBookingWindow(int $id, array $data): void
    {
        self::ensureReady();
        self::requireDatabase();
        $existing = self::findBookingWindow($id);
        if ($existing === null) {
            throw new InvalidArgumentException('Belegungsfenster nicht gefunden.');
        }
        if ((string) ($existing['booking_status'] ?? '') === 'cancelled') {
            throw new InvalidArgumentException('Stornierte Belegungsfenster können nicht bearbeitet werden.');
        }
        $row = self::normalizeBookingWindowPayload($data, true);
        if ($row['booking_status'] === 'cancelled') {
            throw new InvalidArgumentException('Zum Stornieren bitte „Stornieren“ nutzen.');
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE dg_machine_booking_windows SET
                work_center_id = :work_center_id,
                booking_type = :booking_type,
                recipe_id = :recipe_id,
                maintenance_plan_id = :maintenance_plan_id,
                maintenance_log_id = :maintenance_log_id,
                title = :title,
                starts_at = :starts_at,
                ends_at = :ends_at,
                planned_output_qty = :planned_output_qty,
                unit = :unit,
                booking_status = :booking_status,
                notes = :notes
             WHERE id = :id AND booking_status <> \'cancelled\''
        );
        $row['id'] = $id;
        $stmt->execute($row);
    }

    /** Schneller Statuswechsel planned ↔ done (kein Storno). */
    public static function setBookingWindowStatus(int $id, string $status): void
    {
        self::ensureReady();
        self::requireDatabase();
        $normalized = trim($status);
        if (!in_array($normalized, ['planned', 'done'], true)) {
            throw new InvalidArgumentException('Ungültiger Status.');
        }
        $existing = self::findBookingWindow($id);
        if ($existing === null) {
            throw new InvalidArgumentException('Belegungsfenster nicht gefunden.');
        }
        if ((string) ($existing['booking_status'] ?? '') === 'cancelled') {
            throw new InvalidArgumentException('Stornierte Belegungsfenster können nicht bearbeitet werden.');
        }
        $stmt = Database::pdo()->prepare(
            "UPDATE dg_machine_booking_windows
             SET booking_status = :booking_status
             WHERE id = :id AND booking_status <> 'cancelled'"
        );
        $stmt->execute([
            'id' => $id,
            'booking_status' => $normalized,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public static function listBookingWindows(?int $workCenterId = null, ?string $startsBefore = null, ?string $endsAfter = null, bool $excludeCancelled = false): array
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
        if ($startsBefore !== null && trim($startsBefore) !== '') {
            $where[] = 'starts_at < :starts_before';
            $params['starts_before'] = self::dateTime($startsBefore);
        }
        if ($endsAfter !== null && trim($endsAfter) !== '') {
            $where[] = 'ends_at > :ends_after';
            $params['ends_after'] = self::dateTime($endsAfter);
        }
        if ($excludeCancelled) {
            $where[] = "booking_status <> 'cancelled'";
        }
        $sql = 'SELECT * FROM dg_machine_booking_windows';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY starts_at ASC, id ASC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function cancelBookingWindow(int $id): void
    {
        self::ensureReady();
        if ($id <= 0 || !Database::isConfigured()) {
            return;
        }
        $stmt = Database::pdo()->prepare(
            "UPDATE dg_machine_booking_windows SET booking_status = 'cancelled' WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
    }

    /** @return list<array<string, mixed>> */
    public static function listBookingConflicts(int $workCenterId, string $startsAt, string $endsAt, ?int $ignoreId = null): array
    {
        $starts = self::dateTime($startsAt);
        $ends = self::dateTime($endsAt);
        if (strtotime($ends) <= strtotime($starts)) {
            throw new InvalidArgumentException('Belegungsfenster braucht ein Ende nach dem Start.');
        }
        $rows = self::listBookingWindows($workCenterId, $ends, $starts, true);
        if ($ignoreId !== null && $ignoreId > 0) {
            $rows = array_values(array_filter($rows, static fn (array $row): bool => (int) ($row['id'] ?? 0) !== $ignoreId));
        }

        return $rows;
    }

    /**
     * Verfügbare Maschinenstunden im Betrachtungszeitraum (Kalenderstunden × Maschinenanzahl).
     * Mit Filter Von/Bis: ganzer Filterzeitraum. Sonst: Spanne der gelisteten (nicht stornierten) Fenster.
     *
     * @param list<array<string, mixed>> $windows
     */
    public static function bookingAvailableHours(
        array $windows,
        string $filterFromYmd,
        string $filterToYmd,
        int $machineCount
    ): ?float {
        $machineCount = max(0, $machineCount);
        if ($machineCount <= 0) {
            return null;
        }
        $period = self::bookingPeriodBounds($windows, $filterFromYmd, $filterToYmd);
        if ($period === null) {
            return null;
        }
        $seconds = $period['end'] - $period['start'];
        if ($seconds <= 0) {
            return null;
        }

        return ($seconds / 3600) * $machineCount;
    }

    /**
     * Auslastung in Prozent (belegte Stunden / verfügbare Stunden). Überbuchung > 100 möglich.
     */
    public static function bookingUtilizationPercent(float $bookedHours, ?float $availableHours): ?float
    {
        if ($availableHours === null || $availableHours <= 0.0) {
            return null;
        }

        return round(($bookedHours / $availableHours) * 100, 1);
    }

    /**
     * Baut ein einfaches Wochen- oder Monatsgitter fuer die Belegungsansicht (ohne Drag-and-drop).
     *
     * @param list<array<string, mixed>> $windows
     * @return array{
     *   mode: 'month'|'week',
     *   ym: string,
     *   week_start: string,
     *   week_end: string,
     *   prev_ym: string,
     *   next_ym: string,
     *   prev_week: string,
     *   next_week: string,
     *   title: string,
     *   weekday_labels: list<string>,
     *   days: list<array{date: string, weekday: int, in_month: bool, is_today: bool, items: list<array<string, mixed>>}>
     * }
     */
    public static function buildBookingCalendarGrid(
        array $windows,
        string $mode,
        string $ym,
        string $weekAnchorYmd
    ): array {
        $mode = $mode === 'week' ? 'week' : 'month';
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
            $ym = date('Y-m');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekAnchorYmd)) {
            $weekAnchorYmd = date('Y-m-d');
        }
        $weekdayLabels = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
        $today = date('Y-m-d');
        $byDate = [];
        foreach ($windows as $booking) {
            if (!is_array($booking) || ($booking['booking_status'] ?? '') === 'cancelled') {
                continue;
            }
            $startTs = strtotime((string) ($booking['starts_at'] ?? ''));
            $endTs = strtotime((string) ($booking['ends_at'] ?? ''));
            if ($startTs === false || $endTs === false || $endTs <= $startTs) {
                continue;
            }
            $cursor = strtotime(date('Y-m-d 00:00:00', $startTs));
            $lastDay = strtotime(date('Y-m-d 00:00:00', $endTs));
            // Endet genau um Mitternacht: letzter Kalendertag ist der Vortag.
            if (date('H:i:s', $endTs) === '00:00:00') {
                $lastDay = strtotime('-1 day', $lastDay);
            }
            if ($lastDay < $cursor) {
                $lastDay = $cursor;
            }
            while ($cursor !== false && $cursor <= $lastDay) {
                $dateKey = date('Y-m-d', $cursor);
                $byDate[$dateKey][] = $booking;
                $cursor = strtotime('+1 day', $cursor);
            }
        }

        if ($mode === 'week') {
            $anchorTs = strtotime($weekAnchorYmd . ' 12:00:00') ?: time();
            $dow = (int) date('N', $anchorTs);
            $weekStartTs = strtotime('-' . ($dow - 1) . ' days', strtotime(date('Y-m-d', $anchorTs) . ' 12:00:00'));
            $days = [];
            for ($i = 0; $i < 7; $i++) {
                $ts = strtotime('+' . $i . ' days', $weekStartTs);
                $dateKey = date('Y-m-d', $ts);
                $days[] = [
                    'date' => $dateKey,
                    'weekday' => (int) date('N', $ts),
                    'in_month' => true,
                    'is_today' => $dateKey === $today,
                    'items' => $byDate[$dateKey] ?? [],
                ];
            }
            $weekStart = date('Y-m-d', $weekStartTs);
            $weekEnd = date('Y-m-d', strtotime('+6 days', $weekStartTs));
            $prevWeek = date('Y-m-d', strtotime('-7 days', $weekStartTs));
            $nextWeek = date('Y-m-d', strtotime('+7 days', $weekStartTs));

            return [
                'mode' => 'week',
                'ym' => date('Y-m', $weekStartTs),
                'week_start' => $weekStart,
                'week_end' => $weekEnd,
                'prev_ym' => date('Y-m', strtotime('-1 month', strtotime(date('Y-m', $weekStartTs) . '-01'))),
                'next_ym' => date('Y-m', strtotime('+1 month', strtotime(date('Y-m', $weekStartTs) . '-01'))),
                'prev_week' => $prevWeek,
                'next_week' => $nextWeek,
                'title' => 'Woche ' . date('d.m.', $weekStartTs) . '–' . date('d.m.Y', strtotime($weekEnd)),
                'weekday_labels' => $weekdayLabels,
                'days' => $days,
            ];
        }

        $monthStartTs = strtotime($ym . '-01 12:00:00') ?: time();
        $ym = date('Y-m', $monthStartTs);
        $firstDow = (int) date('N', $monthStartTs);
        $gridStartTs = strtotime('-' . ($firstDow - 1) . ' days', strtotime($ym . '-01 12:00:00'));
        $days = [];
        $weekStartTs = $gridStartTs;
        for ($week = 0; $week < 6; $week++) {
            $weekDays = [];
            $weekHasMonthDay = false;
            for ($i = 0; $i < 7; $i++) {
                $ts = strtotime('+' . $i . ' days', $weekStartTs);
                $dateKey = date('Y-m-d', $ts);
                $inMonth = date('Y-m', $ts) === $ym;
                if ($inMonth) {
                    $weekHasMonthDay = true;
                }
                $weekDays[] = [
                    'date' => $dateKey,
                    'weekday' => (int) date('N', $ts),
                    'in_month' => $inMonth,
                    'is_today' => $dateKey === $today,
                    'items' => $inMonth ? ($byDate[$dateKey] ?? []) : [],
                ];
            }
            if (!$weekHasMonthDay && $days !== []) {
                break;
            }
            foreach ($weekDays as $day) {
                $days[] = $day;
            }
            $weekStartTs = strtotime('+7 days', $weekStartTs);
        }

        return [
            'mode' => 'month',
            'ym' => $ym,
            'week_start' => date('Y-m-d', $gridStartTs),
            'week_end' => date('Y-m-t', $monthStartTs),
            'prev_ym' => date('Y-m', strtotime('-1 month', $monthStartTs)),
            'next_ym' => date('Y-m', strtotime('+1 month', $monthStartTs)),
            'prev_week' => date('Y-m-d', strtotime('-7 days', $monthStartTs)),
            'next_week' => date('Y-m-d', strtotime('+7 days', $monthStartTs)),
            'title' => date('m/Y', $monthStartTs),
            'weekday_labels' => $weekdayLabels,
            'days' => $days,
        ];
    }

    /**
     * @param list<array<string, mixed>> $windows
     * @return array{start: int, end: int, source: 'filter'|'bookings'}|null
     */
    public static function bookingPeriodBounds(array $windows, string $filterFromYmd, string $filterToYmd): ?array
    {
        $from = trim($filterFromYmd);
        $to = trim($filterToYmd);
        if ($from !== '' && $to !== '') {
            $start = strtotime($from . ' 00:00:00');
            $end = strtotime($to . ' 23:59:59');
            if ($start === false || $end === false || $end <= $start) {
                return null;
            }

            return ['start' => $start, 'end' => $end, 'source' => 'filter'];
        }

        $minStart = null;
        $maxEnd = null;
        foreach ($windows as $booking) {
            if (!is_array($booking) || ($booking['booking_status'] ?? '') === 'cancelled') {
                continue;
            }
            $bookingStart = strtotime((string) ($booking['starts_at'] ?? ''));
            $bookingEnd = strtotime((string) ($booking['ends_at'] ?? ''));
            if ($bookingStart === false || $bookingEnd === false || $bookingEnd <= $bookingStart) {
                continue;
            }
            $minStart = $minStart === null ? $bookingStart : min($minStart, $bookingStart);
            $maxEnd = $maxEnd === null ? $bookingEnd : max($maxEnd, $bookingEnd);
        }
        if ($minStart === null || $maxEnd === null || $maxEnd <= $minStart) {
            return null;
        }

        return ['start' => $minStart, 'end' => $maxEnd, 'source' => 'bookings'];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{
     *   work_center_id: int,
     *   booking_type: string,
     *   recipe_id: ?int,
     *   maintenance_plan_id: ?int,
     *   maintenance_log_id: ?int,
     *   title: string,
     *   starts_at: string,
     *   ends_at: string,
     *   planned_output_qty: float,
     *   unit: string,
     *   booking_status: string,
     *   notes: string
     * }
     */
    private static function normalizeBookingWindowPayload(array $data, bool $forUpdate): array
    {
        $startsAt = self::dateTime((string) ($data['starts_at'] ?? ''));
        $endsAt = self::dateTime((string) ($data['ends_at'] ?? ''));
        if (strtotime($endsAt) <= strtotime($startsAt)) {
            throw new InvalidArgumentException('Belegungsfenster braucht ein Ende nach dem Start.');
        }
        $title = self::trimmed($data['title'] ?? '', 191);
        if ($title === '') {
            throw new InvalidArgumentException('Titel fehlt.');
        }
        $statusDefault = $forUpdate ? 'planned' : 'planned';

        return [
            'work_center_id' => self::positiveInt($data['work_center_id'] ?? 0, 'Maschine'),
            'booking_type' => self::enum((string) ($data['booking_type'] ?? 'production'), self::BOOKING_TYPES, 'production'),
            'recipe_id' => self::nullablePositiveInt($data['recipe_id'] ?? null),
            'maintenance_plan_id' => self::nullablePositiveInt($data['maintenance_plan_id'] ?? null),
            'maintenance_log_id' => self::nullablePositiveInt($data['maintenance_log_id'] ?? null),
            'title' => $title,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'planned_output_qty' => self::decimal($data['planned_output_qty'] ?? 0, 0.0),
            'unit' => self::trimmed($data['unit'] ?? '', 50),
            'booking_status' => self::enum(
                (string) ($data['booking_status'] ?? $statusDefault),
                self::BOOKING_STATUSES,
                $statusDefault
            ),
            'notes' => self::trimmed($data['notes'] ?? '', 1000),
        ];
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
