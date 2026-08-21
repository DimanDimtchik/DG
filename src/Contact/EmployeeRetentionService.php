<?php
declare(strict_types=1);

/**
 * Entfernt abgelaufene Mitarbeiterdaten (10 Jahre nach Austritt).
 * Stamm-/Kontaktdaten bleiben erhalten; Rolle wird auf Kunde gesetzt.
 */
final class EmployeeRetentionService
{
    public const RETENTION_YEARS = 10;

        /**
     * Erste zwei Kalenderwochen im Januar (1.–14.).
     * @return bool
     */
    public static function isPurgeWindow(): bool
    {
        $now = new DateTimeImmutable('now');
        $month = (int) $now->format('n');
        $day = (int) $now->format('j');

        return $month === 1 && $day >= 1 && $day <= 14;
    }

    /**
     * purgeWindowLabel
     * @return string
     */
    public static function purgeWindowLabel(): string
    {
        return '1.–14. Januar';
    }

    /**
     * applyIfExpired
     * @param int $contactId Kontakt-ID
     * @return bool
     */
    public static function applyIfExpired(int $contactId): bool
    {
        if ($contactId <= 0 || !self::isPurgeWindow()) {
            return false;
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT id, contact_role, employee_data, employee_files FROM dg_contacts WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $contactId]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }

        $role = CrmRole::normalize((string) ($row['contact_role'] ?? ''));
        if (!CrmRole::hasEmployeeProfile($role)) {
            return false;
        }

        $employeeData = self::decodeEmployeeData($row['employee_data'] ?? null);
        if (EmployeeData::retentionStatus($employeeData)['status'] !== 'expired') {
            return false;
        }

        self::purge($contactId, $row['employee_files'] ?? null);

        return true;
    }

        /**
     * Batch-Lauf (Cron, Wartung oder CRM-Aufruf).
     * @return int
     */
    public static function purgeAllExpired(): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query(
            "SELECT id FROM dg_contacts
             WHERE contact_role IN ('dg_eigenmitarbeiter', 'administrator')
             AND employee_data IS NOT NULL"
        );
        $count = 0;
        while ($row = $stmt->fetch()) {
            if (self::applyIfExpired((int) $row['id'])) {
                $count++;
            }
        }

        if ($count > 0) {
            self::logPurge($count, 'crm-batch');
        }

        return $count;
    }

        /**
     * Beim CRM-Aufruf durch Admin/Mitarbeiter — max. einmal pro Sitzung, nur 1.–14. Januar.
     * @return int
     */
    public static function runOnCrmAccess(): int
    {
        if (!self::isPurgeWindow()) {
            return 0;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            return 0;
        }
        if (!empty($_SESSION['dg_retention_purge_done'])) {
            return 0;
        }
        $_SESSION['dg_retention_purge_done'] = true;

        if (!Database::isConfigured()) {
            return 0;
        }

        try {
            return self::purgeAllExpired();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * logPurge
     * @param int $count
     * @param string $source
     * @return void
     */
    private static function logPurge(int $count, string $source): void
    {
        $line = date('c') . " employee-retention ({$source}) bereinigt: {$count}\n";
        $logDir = DG_ROOT . '/storage/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }
        file_put_contents($logDir . '/cron-purge.log', $line, FILE_APPEND | LOCK_EX);
    }

        /**
     * purge
     * @param int $contactId Kontakt-ID
     * @param mixed $employeeFilesRaw
     * @return void
     */
    private static function purge(int $contactId, mixed $employeeFilesRaw): void
    {
        $decoded = is_string($employeeFilesRaw) ? json_decode($employeeFilesRaw, true) : $employeeFilesRaw;
        ContactFileStorage::deleteAll(ContactFileStorage::normalizeFiles($decoded));

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'UPDATE dg_contacts
             SET contact_role = :role, employee_data = NULL, employee_files = NULL
             WHERE id = :id'
        );
        $stmt->execute([
            'role' => 'dg_kunde',
            'id' => $contactId,
        ]);
    }

        /**
     * decodeEmployeeData
     * @param mixed $raw Rohdaten
     * @return array<string, string>
     */
    private static function decodeEmployeeData(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return EmployeeData::empty();
        }
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($decoded) ? EmployeeData::sanitize($decoded) : EmployeeData::empty();
    }
}
