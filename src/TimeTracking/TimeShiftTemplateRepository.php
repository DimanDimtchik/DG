<?php
declare(strict_types=1);

/** Zeiterfassung Z3b: Schicht-Vorlagen. */
final class TimeShiftTemplateRepository
{
    public static function tableReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            $r = Database::pdo()->query("SHOW TABLES LIKE 'dg_time_shift_templates'");

            return $r !== false && $r->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function all(bool $activeOnly = false): array
    {
        if (!self::tableReady()) {
            return [];
        }
        MigrationRunner::runPending();
        $sql = 'SELECT * FROM dg_time_shift_templates';
        if ($activeOnly) {
            $sql .= ' WHERE active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, name ASC, id ASC';
        $rows = Database::pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = self::mapRow($row);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findById(int $id): ?array
    {
        if (!self::tableReady() || $id < 1) {
            return null;
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_time_shift_templates WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::mapRow($row) : null;
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function save(array $input, ?int $id = null): int
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht konfiguriert.');
        }
        MigrationRunner::runPending();
        if (!self::tableReady()) {
            throw new RuntimeException('Schicht-Vorlagen-Tabelle fehlt — Migration 091 ausführen.');
        }

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Name der Schicht ist erforderlich.');
        }
        if (function_exists('mb_substr')) {
            $name = mb_substr($name, 0, 80);
        } else {
            $name = substr($name, 0, 80);
        }

        $start = self::normalizeTime((string) ($input['start_time'] ?? ''));
        $end = self::normalizeTime((string) ($input['end_time'] ?? ''));
        if ($start === null || $end === null) {
            throw new InvalidArgumentException('Start- und Endzeit im Format HH:MM erforderlich.');
        }

        $sort = (int) ($input['sort_order'] ?? 0);
        $active = !empty($input['active']) ? 1 : 0;
        $duration = self::durationMinutes($start, $end);
        if ($duration < 1 || $duration > 960) {
            throw new InvalidArgumentException('Schichtdauer muss zwischen 1 und 960 Minuten liegen.');
        }

        $pdo = Database::pdo();
        if ($id !== null && $id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE dg_time_shift_templates
                 SET name = :name, start_time = :start_time, end_time = :end_time,
                     sort_order = :sort_order, active = :active
                 WHERE id = :id'
            );
            $stmt->execute([
                'name' => $name,
                'start_time' => $start,
                'end_time' => $end,
                'sort_order' => $sort,
                'active' => $active,
                'id' => $id,
            ]);

            return $id;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO dg_time_shift_templates (name, start_time, end_time, sort_order, active)
             VALUES (:name, :start_time, :end_time, :sort_order, :active)'
        );
        $stmt->execute([
            'name' => $name,
            'start_time' => $start,
            'end_time' => $end,
            'sort_order' => $sort,
            'active' => $active,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function setActive(int $id, bool $active): void
    {
        if ($id < 1 || !self::tableReady()) {
            throw new InvalidArgumentException('Vorlage nicht gefunden.');
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            'UPDATE dg_time_shift_templates SET active = :a WHERE id = :id'
        );
        $stmt->execute(['a' => $active ? 1 : 0, 'id' => $id]);
        if ($stmt->rowCount() < 1 && self::findById($id) === null) {
            throw new InvalidArgumentException('Vorlage nicht gefunden.');
        }
    }

    /**
     * Löschen nur wenn keine Zuordnung existiert (Z3c-Tabelle optional).
     */
    public static function delete(int $id): void
    {
        if ($id < 1 || !self::tableReady()) {
            throw new InvalidArgumentException('Vorlage nicht gefunden.');
        }
        MigrationRunner::runPending();
        if (self::assignmentCount($id) > 0) {
            throw new InvalidArgumentException('Vorlage ist noch zugewiesen — bitte deaktivieren statt löschen.');
        }
        $stmt = Database::pdo()->prepare('DELETE FROM dg_time_shift_templates WHERE id = :id');
        $stmt->execute(['id' => $id]);
        if ($stmt->rowCount() < 1) {
            throw new InvalidArgumentException('Vorlage nicht gefunden.');
        }
    }

    public static function assignmentCount(int $templateId): int
    {
        if ($templateId < 1 || !Database::isConfigured()) {
            return 0;
        }
        try {
            $chk = Database::pdo()->query("SHOW TABLES LIKE 'dg_time_shift_assignments'");
            if ($chk === false || $chk->fetchColumn() === false) {
                return 0;
            }
        } catch (Throwable) {
            return 0;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM dg_time_shift_assignments WHERE template_id = :id'
        );
        $stmt->execute(['id' => $templateId]);

        return max(0, (int) $stmt->fetchColumn());
    }

    /**
     * Dauer in Minuten; end ≤ start = über Mitternacht.
     */
    public static function durationMinutes(string $startTime, string $endTime): int
    {
        $start = self::timeToMinutes($startTime);
        $end = self::timeToMinutes($endTime);
        if ($start === null || $end === null) {
            return 0;
        }
        if ($end > $start) {
            return $end - $start;
        }
        // Nacht: Rest des Tages + Ende
        return (24 * 60 - $start) + $end;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function mapRow(array $row): array
    {
        $start = self::formatTimeHm((string) ($row['start_time'] ?? ''));
        $end = self::formatTimeHm((string) ($row['end_time'] ?? ''));
        $duration = self::durationMinutes(
            (string) ($row['start_time'] ?? ''),
            (string) ($row['end_time'] ?? '')
        );

        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'start_time' => (string) ($row['start_time'] ?? ''),
            'end_time' => (string) ($row['end_time'] ?? ''),
            'start_time_hm' => $start,
            'end_time_hm' => $end,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'active' => !empty($row['active']),
            'duration_minutes' => $duration,
            'duration_display' => TimeClockService::formatMinutes($duration),
            'overnight' => self::timeToMinutes((string) ($row['end_time'] ?? ''))
                <= self::timeToMinutes((string) ($row['start_time'] ?? '')),
        ];
    }

    private static function normalizeTime(string $raw): ?string
    {
        $raw = trim($raw);
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $raw, $m)) {
            $h = (int) $m[1];
            $i = (int) $m[2];
            $s = isset($m[3]) ? (int) $m[3] : 0;
            if ($h > 23 || $i > 59 || $s > 59) {
                return null;
            }

            return sprintf('%02d:%02d:%02d', $h, $i, $s);
        }

        return null;
    }

    private static function timeToMinutes(string $time): ?int
    {
        $norm = self::normalizeTime($time);
        if ($norm === null) {
            return null;
        }
        if (!preg_match('/^(\d{2}):(\d{2}):(\d{2})$/', $norm, $m)) {
            return null;
        }

        return ((int) $m[1]) * 60 + (int) $m[2];
    }

    private static function formatTimeHm(string $time): string
    {
        $norm = self::normalizeTime($time);
        if ($norm === null) {
            return substr($time, 0, 5);
        }

        return substr($norm, 0, 5);
    }
}
