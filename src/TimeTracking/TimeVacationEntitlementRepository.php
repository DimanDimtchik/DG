<?php
declare(strict_types=1);

/** Zeiterfassung Z4b: Jahres-Urlaubsanspruch. */
final class TimeVacationEntitlementRepository
{
    public static function tableReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            $r = Database::pdo()->query("SHOW TABLES LIKE 'dg_time_vacation_entitlements'");

            return $r !== false && $r->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $contactId, int $year): ?array
    {
        if (!self::tableReady() || $contactId < 1 || $year < 2000 || $year > 2100) {
            return null;
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_time_vacation_entitlements
             WHERE contact_id = :cid AND year = :y
             LIMIT 1'
        );
        $stmt->execute(['cid' => $contactId, 'y' => $year]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::mapRow($row) : null;
    }

    /**
     * Upsert Anspruch für Kontakt + Jahr.
     *
     * @param array{days_entitled?: float|int|string, days_carried?: float|int|string, note?: string|null} $input
     */
    public static function save(int $contactId, int $year, array $input): int
    {
        if ($contactId < 1 || $year < 2000 || $year > 2100) {
            throw new InvalidArgumentException('Kontakt und Jahr erforderlich.');
        }
        MigrationRunner::runPending();
        if (!self::tableReady()) {
            throw new RuntimeException('Urlaubsanspruch-Tabelle fehlt — Migration 093 ausführen.');
        }

        $entitled = self::normalizeDays($input['days_entitled'] ?? 0);
        $carried = self::normalizeDays($input['days_carried'] ?? 0);
        $note = trim((string) ($input['note'] ?? ''));
        if ($note === '') {
            $note = null;
        } elseif (function_exists('mb_substr')) {
            $note = mb_substr($note, 0, 255);
        } else {
            $note = substr($note, 0, 255);
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_time_vacation_entitlements
                (contact_id, year, days_entitled, days_carried, note)
             VALUES
                (:cid, :y, :ent, :car, :note)
             ON DUPLICATE KEY UPDATE
                days_entitled = VALUES(days_entitled),
                days_carried = VALUES(days_carried),
                note = VALUES(note),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'cid' => $contactId,
            'y' => $year,
            'ent' => $entitled,
            'car' => $carried,
            'note' => $note,
        ]);

        $existing = self::find($contactId, $year);

        return (int) ($existing['id'] ?? 0);
    }

    /**
     * Resttage = Anspruch + Übertrag − genehmigte Urlaubstage im Jahr
     * (nur Absences vollständig innerhalb des Jahres; Mehrjahres-Anträge aufteilen).
     */
    public static function restDays(int $contactId, int $year): float
    {
        $row = self::find($contactId, $year);
        $entitled = $row !== null ? (float) $row['days_entitled'] : 0.0;
        $carried = $row !== null ? (float) $row['days_carried'] : 0.0;
        $used = TimeAbsenceRepository::approvedVacationDaysInYear($contactId, $year);

        return round($entitled + $carried - $used, 1);
    }

    /**
     * @return array{
     *   contact_id: int,
     *   year: int,
     *   days_entitled: float,
     *   days_carried: float,
     *   days_used: float,
     *   days_rest: float,
     *   note: string|null,
     *   id: int|null
     * }
     */
    public static function balance(int $contactId, int $year): array
    {
        $row = self::find($contactId, $year);
        $entitled = $row !== null ? (float) $row['days_entitled'] : 0.0;
        $carried = $row !== null ? (float) $row['days_carried'] : 0.0;
        $used = TimeAbsenceRepository::approvedVacationDaysInYear($contactId, $year);

        return [
            'id' => $row !== null ? (int) $row['id'] : null,
            'contact_id' => $contactId,
            'year' => $year,
            'days_entitled' => $entitled,
            'days_carried' => $carried,
            'days_used' => $used,
            'days_rest' => round($entitled + $carried - $used, 1),
            'note' => $row !== null ? ($row['note'] ?? null) : null,
        ];
    }

    private static function normalizeDays(mixed $raw): float
    {
        if (is_string($raw)) {
            $raw = str_replace(',', '.', $raw);
        }
        $v = round((float) $raw, 1);
        if ($v < 0 || $v > 366) {
            throw new InvalidArgumentException('Tage müssen zwischen 0 und 366 liegen.');
        }

        return $v;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function mapRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'contact_id' => (int) ($row['contact_id'] ?? 0),
            'year' => (int) ($row['year'] ?? 0),
            'days_entitled' => round((float) ($row['days_entitled'] ?? 0), 1),
            'days_carried' => round((float) ($row['days_carried'] ?? 0), 1),
            'note' => isset($row['note']) && $row['note'] !== null && $row['note'] !== ''
                ? (string) $row['note']
                : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
}
