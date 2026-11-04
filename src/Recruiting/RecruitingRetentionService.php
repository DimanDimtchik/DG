<?php
declare(strict_types=1);

/**
 * Löscht abgesagte/beendete Bewerbungen nach Speicherdauer.
 * Primär: runIfDue() beim Seitenaufruf (ohne KAS-Cron); optional cron.php.
 */
final class RecruitingRetentionService
{
    /** Status, die als „Verfahren beendet“ gelten und nach Frist gelöscht werden dürfen. */
    public const PURGEABLE_STATUSES = [
        JobApplicationRepository::STATUS_REJECTED,
        JobApplicationRepository::STATUS_WITHDRAWN,
    ];

    /**
     * @return array{deleted_applications: int, deleted_applicants: int, skipped: int, errors: list<string>}
     */
    public static function runAutomatic(?string $today = null): array
    {
        $today = $today ?? date('Y-m-d');
        $result = [
            'deleted_applications' => 0,
            'deleted_applicants' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        if (!Database::isConfigured()) {
            $result['errors'][] = 'Datenbank nicht konfiguriert.';

            return $result;
        }

        try {
            MigrationRunner::runPending();
            if (!JobApplicationRepository::tableReady()) {
                $result['skipped']++;

                return $result;
            }

            $due = self::listDueApplications($today);
            if ($due === []) {
                self::log("{$today}: nichts fällig");

                return $result;
            }

            foreach ($due as $row) {
                $appId = (int) ($row['id'] ?? 0);
                $applicantId = (int) ($row['applicant_id'] ?? 0);
                try {
                    self::purgeApplication($appId);
                    $result['deleted_applications']++;
                    if ($applicantId > 0 && self::purgeApplicantIfOrphan($applicantId)) {
                        $result['deleted_applicants']++;
                    }
                } catch (Throwable $e) {
                    $result['errors'][] = "Bewerbung #{$appId}: " . $e->getMessage();
                    self::log("error app={$appId}: " . $e->getMessage());
                }
            }

            self::log(
                "{$today}: applications={$result['deleted_applications']}, "
                . "applicants={$result['deleted_applicants']}, errors=" . count($result['errors'])
            );
        } catch (Throwable $e) {
            $result['errors'][] = $e->getMessage();
            self::log('error: ' . $e->getMessage());
        }

        return $result;
    }

    public static function runIfDue(): void
    {
        if (!Database::isConfigured()) {
            return;
        }

        $today = date('Y-m-d');
        $stateFile = DG_ROOT . '/storage/recruiting/retention-auto-state.json';
        $state = [];
        if (is_file($stateFile)) {
            $raw = json_decode((string) file_get_contents($stateFile), true);
            $state = is_array($raw) ? $raw : [];
        }
        if (($state['last_run'] ?? '') === $today) {
            return;
        }

        $result = self::runAutomatic($today);
        $dir = dirname($stateFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        @file_put_contents($stateFile, json_encode([
            'last_run' => $today,
            'deleted_applications' => (int) ($result['deleted_applications'] ?? 0),
            'deleted_applicants' => (int) ($result['deleted_applicants'] ?? 0),
            'last_error' => ($result['errors'] ?? []) !== []
                ? implode('; ', $result['errors'])
                : null,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listDueApplications(string $today): array
    {
        $placeholders = implode(',', array_fill(0, count(self::PURGEABLE_STATUSES), '?'));
        $sql = "SELECT id, applicant_id, status, retention_until
                FROM dg_job_applications
                WHERE status IN ({$placeholders})
                  AND retention_until IS NOT NULL
                  AND retention_until <> ''
                  AND retention_until <= ?
                ORDER BY retention_until ASC, id ASC
                LIMIT 200";
        $params = array_values(self::PURGEABLE_STATUSES);
        $params[] = $today;
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    public static function purgeApplication(int $applicationId): void
    {
        if ($applicationId < 1) {
            return;
        }
        $row = JobApplicationRepository::find($applicationId);
        if ($row === null) {
            return;
        }
        $status = (string) ($row['status'] ?? '');
        if (!in_array($status, self::PURGEABLE_STATUSES, true)) {
            throw new RuntimeException('Laufendes Verfahren wird nicht gelöscht.');
        }

        RecruitingFileStorage::deleteAllForApplication($applicationId);
        Database::pdo()->prepare(
            'DELETE FROM dg_job_application_events WHERE application_id = :id'
        )->execute(['id' => $applicationId]);
        Database::pdo()->prepare('DELETE FROM dg_job_applications WHERE id = :id')->execute(['id' => $applicationId]);
    }

    public static function purgeApplicantIfOrphan(int $applicantId): bool
    {
        if ($applicantId < 1 || !ApplicantRepository::tableReady()) {
            return false;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM dg_job_applications WHERE applicant_id = :id'
        );
        $stmt->execute(['id' => $applicantId]);
        if ((int) $stmt->fetchColumn() > 0) {
            return false;
        }
        Database::pdo()->prepare('DELETE FROM dg_applicants WHERE id = :id')->execute(['id' => $applicantId]);

        return true;
    }

    private static function log(string $line): void
    {
        $dir = DG_ROOT . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        @file_put_contents(
            $dir . '/recruiting-retention.log',
            date('c') . ' ' . $line . "\n",
            FILE_APPEND
        );
    }
}
