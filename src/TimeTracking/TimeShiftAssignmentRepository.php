<?php
declare(strict_types=1);

/** Zeiterfassung Z3c: Schicht-Zuordnung Kontakt × Datum × Vorlage. */
final class TimeShiftAssignmentRepository
{
    public static function tableReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            $r = Database::pdo()->query("SHOW TABLES LIKE 'dg_time_shift_assignments'");

            return $r !== false && $r->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findForContactDate(int $contactId, string $workDate): ?array
    {
        if (!self::tableReady() || $contactId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate)) {
            return null;
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            'SELECT a.*, t.name AS template_name, t.start_time, t.end_time, t.active AS template_active
             FROM dg_time_shift_assignments a
             INNER JOIN dg_time_shift_templates t ON t.id = a.template_id
             WHERE a.contact_id = :cid AND a.work_date = :d
             LIMIT 1'
        );
        $stmt->execute(['cid' => $contactId, 'd' => $workDate]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::mapRow($row) : null;
    }

    /**
     * Alle Zuordnungen im Datumsbereich, Schlüssel "contactId|date".
     *
     * @return array<string, array<string, mixed>>
     */
    public static function mapForRange(string $from, string $to): array
    {
        if (!self::tableReady()
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            return [];
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            'SELECT a.*, t.name AS template_name, t.start_time, t.end_time, t.active AS template_active
             FROM dg_time_shift_assignments a
             INNER JOIN dg_time_shift_templates t ON t.id = a.template_id
             WHERE a.work_date >= :from AND a.work_date <= :to
             ORDER BY a.contact_id ASC, a.work_date ASC'
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $mapped = self::mapRow($row);
            $key = (int) $mapped['contact_id'] . '|' . (string) $mapped['work_date'];
            $out[$key] = $mapped;
        }

        return $out;
    }

    /**
     * Setzt oder löscht Zuordnung (templateId 0 = löschen).
     */
    public static function upsert(int $contactId, string $workDate, int $templateId): void
    {
        if ($contactId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate)) {
            throw new InvalidArgumentException('Kontakt und Datum erforderlich.');
        }
        MigrationRunner::runPending();
        if (!self::tableReady()) {
            throw new RuntimeException('Zuordnungs-Tabelle fehlt — Migration 092 ausführen.');
        }

        if ($templateId < 1) {
            $del = Database::pdo()->prepare(
                'DELETE FROM dg_time_shift_assignments WHERE contact_id = :cid AND work_date = :d'
            );
            $del->execute(['cid' => $contactId, 'd' => $workDate]);

            return;
        }

        $tpl = TimeShiftTemplateRepository::findById($templateId);
        if ($tpl === null || empty($tpl['active'])) {
            throw new InvalidArgumentException('Schicht-Vorlage ungültig oder inaktiv.');
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_time_shift_assignments (contact_id, work_date, template_id)
             VALUES (:cid, :d, :tid)
             ON DUPLICATE KEY UPDATE template_id = VALUES(template_id), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'cid' => $contactId,
            'd' => $workDate,
            'tid' => $templateId,
        ]);
    }

    /**
     * Speichert Wochenraster: assignments[contactId][YYYY-MM-DD] = templateId|"".
     *
     * @param array<string|int, array<string, mixed>> $grid
     * @return int Anzahl gesetzter/gelöschter Zellen
     */
    public static function saveWeekGrid(array $grid): int
    {
        $count = 0;
        foreach ($grid as $contactIdRaw => $days) {
            $contactId = (int) $contactIdRaw;
            if ($contactId < 1 || !is_array($days)) {
                continue;
            }
            foreach ($days as $date => $templateRaw) {
                $date = (string) $date;
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    continue;
                }
                $templateId = (int) $templateRaw;
                self::upsert($contactId, $date, $templateId);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Montag der Kalenderwoche zu einem Datum.
     */
    public static function mondayOfWeek(string $dateYmd): string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd)) {
            $dateYmd = date('Y-m-d');
        }
        $ref = new DateTimeImmutable($dateYmd);
        $n = (int) $ref->format('N');

        return $ref->modify('-' . ($n - 1) . ' days')->format('Y-m-d');
    }

    /**
     * @return list<string> 7 Daten Mo–So
     */
    public static function weekDates(string $monday): array
    {
        $monday = self::mondayOfWeek($monday);
        $out = [];
        $dt = new DateTimeImmutable($monday);
        for ($i = 0; $i < 7; $i++) {
            $out[] = $dt->modify('+' . $i . ' days')->format('Y-m-d');
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function mapRow(array $row): array
    {
        $start = (string) ($row['start_time'] ?? '');
        $end = (string) ($row['end_time'] ?? '');

        return [
            'id' => (int) ($row['id'] ?? 0),
            'contact_id' => (int) ($row['contact_id'] ?? 0),
            'work_date' => (string) ($row['work_date'] ?? ''),
            'template_id' => (int) ($row['template_id'] ?? 0),
            'template_name' => (string) ($row['template_name'] ?? ''),
            'start_time' => $start,
            'end_time' => $end,
            'duration_minutes' => TimeShiftTemplateRepository::durationMinutes($start, $end),
            'template_active' => !empty($row['template_active']),
        ];
    }
}
