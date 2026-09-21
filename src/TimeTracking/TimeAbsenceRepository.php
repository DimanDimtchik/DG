<?php
declare(strict_types=1);

/**
 * Zeiterfassung Z4b: Abwesenheiten (Urlaub/Krankheit/Sonstiges).
 * Werktage = Mo–Fr, ohne Feiertagslogik (Z4 Default).
 */
final class TimeAbsenceRepository
{
    public const TYPES = ['vacation', 'sick', 'other'];
    public const STATUSES = ['requested', 'approved', 'rejected', 'cancelled'];

    public static function tableReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            $r = Database::pdo()->query("SHOW TABLES LIKE 'dg_time_absences'");

            return $r !== false && $r->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Werktage Mo–Fr inklusiv (ohne Feiertage). Halbe Tage: manuell via days_count.
     */
    public static function countWorkingDays(string $dateFrom, string $dateTo): float
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            return 0.0;
        }
        $from = new DateTimeImmutable($dateFrom);
        $to = new DateTimeImmutable($dateTo);
        if ($to < $from) {
            return 0.0;
        }
        $count = 0;
        for ($d = $from; $d <= $to; $d = $d->modify('+1 day')) {
            $n = (int) $d->format('N');
            if ($n >= 1 && $n <= 5) {
                $count++;
            }
        }

        return (float) $count;
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
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_time_absences WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::mapRow($row) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listForContact(int $contactId, ?int $year = null, int $limit = 100): array
    {
        if (!self::tableReady() || $contactId < 1) {
            return [];
        }
        MigrationRunner::runPending();
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT * FROM dg_time_absences WHERE contact_id = :cid';
        $params = ['cid' => $contactId];
        if ($year !== null && $year >= 2000 && $year <= 2100) {
            $sql .= ' AND date_from <= :ye AND date_to >= :ys';
            $params['ys'] = sprintf('%04d-01-01', $year);
            $params['ye'] = sprintf('%04d-12-31', $year);
        }
        $sql .= ' ORDER BY date_from DESC, id DESC LIMIT ' . $limit;
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if (is_array($row)) {
                $out[] = self::mapRow($row);
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listByStatus(string $status, int $limit = 100): array
    {
        if (!self::tableReady() || !in_array($status, self::STATUSES, true)) {
            return [];
        }
        MigrationRunner::runPending();
        $limit = max(1, min(500, $limit));
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_time_absences
             WHERE status = :st
             ORDER BY date_from ASC, id ASC
             LIMIT ' . $limit
        );
        $stmt->execute(['st' => $status]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if (is_array($row)) {
                $out[] = self::mapRow($row);
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listOverlappingRange(string $from, string $to, ?string $status = null, int $limit = 500): array
    {
        if (!self::tableReady()
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            return [];
        }
        MigrationRunner::runPending();
        $limit = max(1, min(1000, $limit));
        $sql = 'SELECT * FROM dg_time_absences
                WHERE date_from <= :to AND date_to >= :from';
        $params = ['from' => $from, 'to' => $to];
        if ($status !== null) {
            if (!in_array($status, self::STATUSES, true)) {
                return [];
            }
            $sql .= ' AND status = :st';
            $params['st'] = $status;
        }
        $sql .= ' ORDER BY date_from ASC, contact_id ASC, id ASC LIMIT ' . $limit;
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if (is_array($row)) {
                $out[] = self::mapRow($row);
            }
        }

        return $out;
    }

    /**
     * Genehmigte Abwesenheit an einem Kalendertag (Z4e-Vorbereitung).
     *
     * @return array<string, mixed>|null
     */
    public static function approvedOnDate(int $contactId, string $dateYmd): ?array
    {
        if (!self::tableReady()
            || $contactId < 1
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd)) {
            return null;
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_time_absences
             WHERE contact_id = :cid
               AND status = \'approved\'
               AND date_from <= :d AND date_to >= :d
             ORDER BY id ASC
             LIMIT 1'
        );
        $stmt->execute(['cid' => $contactId, 'd' => $dateYmd]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::mapRow($row) : null;
    }

    /**
     * Summe days_count genehmigter Urlaub vollständig im Kalenderjahr.
     */
    public static function approvedVacationDaysInYear(int $contactId, int $year): float
    {
        if (!self::tableReady() || $contactId < 1 || $year < 2000 || $year > 2100) {
            return 0.0;
        }
        MigrationRunner::runPending();
        $from = sprintf('%04d-01-01', $year);
        $to = sprintf('%04d-12-31', $year);
        $stmt = Database::pdo()->prepare(
            'SELECT COALESCE(SUM(days_count), 0)
             FROM dg_time_absences
             WHERE contact_id = :cid
               AND type = \'vacation\'
               AND status = \'approved\'
               AND date_from >= :from
               AND date_to <= :to'
        );
        $stmt->execute(['cid' => $contactId, 'from' => $from, 'to' => $to]);

        return round((float) $stmt->fetchColumn(), 1);
    }

    /**
     * Neue Abwesenheit anlegen.
     *
     * @param array{
     *   contact_id: int,
     *   type: string,
     *   date_from: string,
     *   date_to: string,
     *   days_count?: float|int|string|null,
     *   status?: string,
     *   reason?: string,
     *   document_ref?: string|null,
     *   created_by?: int|null
     * } $input
     */
    public static function create(array $input): int
    {
        MigrationRunner::runPending();
        if (!self::tableReady()) {
            throw new RuntimeException('Abwesenheits-Tabelle fehlt — Migration 093 ausführen.');
        }

        $contactId = (int) ($input['contact_id'] ?? 0);
        $type = (string) ($input['type'] ?? '');
        $from = (string) ($input['date_from'] ?? '');
        $to = (string) ($input['date_to'] ?? '');
        $status = (string) ($input['status'] ?? 'requested');
        $reason = trim((string) ($input['reason'] ?? ''));
        $createdBy = isset($input['created_by']) ? (int) $input['created_by'] : null;
        if ($createdBy !== null && $createdBy < 1) {
            $createdBy = null;
        }

        if ($contactId < 1) {
            throw new InvalidArgumentException('Kontakt erforderlich.');
        }
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Ungültiger Abwesenheitstyp.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            throw new InvalidArgumentException('Datum von/bis im Format YYYY-MM-DD erforderlich.');
        }
        if ($to < $from) {
            throw new InvalidArgumentException('Datum bis darf nicht vor Datum von liegen.');
        }
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Ungültiger Status.');
        }
        if ($reason === '') {
            throw new InvalidArgumentException('Begründung/Grund ist erforderlich.');
        }
        if (function_exists('mb_substr')) {
            $reason = mb_substr($reason, 0, 500);
        } else {
            $reason = substr($reason, 0, 500);
        }

        $days = isset($input['days_count']) && $input['days_count'] !== '' && $input['days_count'] !== null
            ? self::normalizeDays($input['days_count'])
            : self::countWorkingDays($from, $to);
        if ($days <= 0) {
            throw new InvalidArgumentException('Keine Werktage im Zeitraum (Mo–Fr).');
        }

        $doc = trim((string) ($input['document_ref'] ?? ''));
        if ($doc === '') {
            $doc = null;
        } elseif (function_exists('mb_substr')) {
            $doc = mb_substr($doc, 0, 255);
        } else {
            $doc = substr($doc, 0, 255);
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_time_absences
                (contact_id, type, date_from, date_to, days_count, status, reason, document_ref, created_by)
             VALUES
                (:cid, :type, :from, :to, :days, :status, :reason, :doc, :created_by)'
        );
        $stmt->execute([
            'cid' => $contactId,
            'type' => $type,
            'from' => $from,
            'to' => $to,
            'days' => $days,
            'status' => $status,
            'reason' => $reason,
            'doc' => $doc,
            'created_by' => $createdBy,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public static function setStatus(int $id, string $status, ?int $decidedBy, ?string $reason = null): void
    {
        if ($id < 1 || !in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Status ungültig.');
        }
        MigrationRunner::runPending();
        $row = self::findById($id);
        if ($row === null) {
            throw new InvalidArgumentException('Abwesenheit nicht gefunden.');
        }

        $params = [
            'id' => $id,
            'status' => $status,
            'decided_by' => ($decidedBy !== null && $decidedBy > 0) ? $decidedBy : null,
        ];

        $sql = 'UPDATE dg_time_absences SET status = :status, decided_by = :decided_by, decided_at = CURRENT_TIMESTAMP';
        if ($reason !== null) {
            $reason = trim($reason);
            if ($reason === '') {
                throw new InvalidArgumentException('Begründung bei Statuswechsel erforderlich.');
            }
            if (function_exists('mb_substr')) {
                $reason = mb_substr($reason, 0, 500);
            } else {
                $reason = substr($reason, 0, 500);
            }
            $sql .= ', reason = :reason';
            $params['reason'] = $reason;
        }
        $sql .= ', updated_at = CURRENT_TIMESTAMP WHERE id = :id';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
    }

    public static function cancel(int $id, int $byUserId): void
    {
        $row = self::findById($id);
        if ($row === null) {
            throw new InvalidArgumentException('Abwesenheit nicht gefunden.');
        }
        if ((string) ($row['status'] ?? '') !== 'requested') {
            throw new InvalidArgumentException('Nur Anträge im Status „beantragt“ können zurückgezogen werden.');
        }
        self::setStatus($id, 'cancelled', $byUserId > 0 ? $byUserId : null);
    }

    private static function normalizeDays(mixed $raw): float
    {
        if (is_string($raw)) {
            $raw = str_replace(',', '.', $raw);
        }
        $v = round((float) $raw, 1);
        if ($v < 0.5 || $v > 366) {
            throw new InvalidArgumentException('days_count muss zwischen 0,5 und 366 liegen.');
        }
        // Halbe Tage: .0 oder .5
        $tenth = (int) round($v * 10);
        if ($tenth % 5 !== 0) {
            throw new InvalidArgumentException('Tage nur in ganzen oder halben Tagen (x,0 / x,5).');
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
            'type' => (string) ($row['type'] ?? ''),
            'date_from' => (string) ($row['date_from'] ?? ''),
            'date_to' => (string) ($row['date_to'] ?? ''),
            'days_count' => round((float) ($row['days_count'] ?? 0), 1),
            'status' => (string) ($row['status'] ?? ''),
            'reason' => (string) ($row['reason'] ?? ''),
            'document_ref' => isset($row['document_ref']) && $row['document_ref'] !== null && $row['document_ref'] !== ''
                ? (string) $row['document_ref']
                : null,
            'decided_by' => isset($row['decided_by']) && $row['decided_by'] !== null
                ? (int) $row['decided_by']
                : null,
            'decided_at' => (string) ($row['decided_at'] ?? ''),
            'created_by' => isset($row['created_by']) && $row['created_by'] !== null
                ? (int) $row['created_by']
                : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
}
