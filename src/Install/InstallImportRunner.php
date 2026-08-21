<?php
declare(strict_types=1);

/** Führt Import-Jobs der Installation schrittweise aus. */
final class InstallImportRunner
{
    /**
     * @return array{ok: bool, done: bool, state: array<string, mixed>, message?: string}
     */
    public static function processNext(): array
    {
        require_once DG_ROOT . '/src/autoload.php';
        require_once DG_ROOT . '/src/App.php';
        App::reloadConfig();

        $state = InstallImportQueue::loadState();
        if ($state === null || empty($state['jobs'])) {
            return ['ok' => true, 'done' => true, 'state' => ['jobs' => [], 'current_index' => 0, 'phase' => 'done']];
        }

        $state['phase'] = 'running';
        $index = (int) ($state['current_index'] ?? 0);
        $jobs = $state['jobs'];

        if ($index >= count($jobs)) {
            $state['phase'] = 'done';
            InstallImportQueue::saveState($state);

            return ['ok' => true, 'done' => true, 'state' => $state];
        }

        $job = $jobs[$index];
        if (($job['status'] ?? '') === 'done') {
            $state['current_index'] = $index + 1;
            InstallImportQueue::saveState($state);

            return self::processNext();
        }

        $path = InstallImportQueue::storageDir() . '/' . ($job['file'] ?? '');
        if (!is_readable($path)) {
            $job['status'] = 'error';
            $job['message'] = 'Importdatei nicht gefunden.';
            $job['errors'][] = 'Datei fehlt: ' . ($job['file'] ?? '');
            $jobs[$index] = $job;
            $state['jobs'] = $jobs;
            $state['phase'] = 'error';
            InstallImportQueue::saveState($state);

            return ['ok' => false, 'done' => false, 'state' => $state, 'message' => $job['message']];
        }

        try {
            $result = self::runJobBatch($job, $path);
        } catch (Throwable $e) {
            $job['status'] = 'error';
            $job['message'] = $e->getMessage();
            $job['errors'][] = $e->getMessage();
            $jobs[$index] = $job;
            $state['jobs'] = $jobs;
            $state['phase'] = 'error';
            InstallImportQueue::saveState($state);

            return ['ok' => false, 'done' => false, 'state' => $state, 'message' => $e->getMessage()];
        }

        $job['progress'] = (int) ($result['progress'] ?? 0);
        $job['imported'] = (int) (($job['imported'] ?? 0) + ($result['imported'] ?? 0));
        $job['skipped'] = (int) (($job['skipped'] ?? 0) + ($result['skipped'] ?? 0));
        $job['message'] = (string) ($result['message'] ?? '');
        $job['offset'] = (int) ($result['next_offset'] ?? ($job['offset'] ?? -1));
        if (!empty($result['errors'])) {
            $job['errors'] = array_merge($job['errors'] ?? [], $result['errors']);
        }

        if (!empty($result['done'])) {
            $job['status'] = 'done';
            $job['progress'] = 100;
            $state['current_index'] = $index + 1;
        } else {
            $job['status'] = 'running';
        }

        $jobs[$index] = $job;
        $state['jobs'] = $jobs;

        $allDone = $state['current_index'] >= count($jobs);
        $state['phase'] = $allDone ? 'done' : 'running';
        InstallImportQueue::saveState($state);

        return [
            'ok' => true,
            'done' => $allDone,
            'state' => $state,
            'message' => $job['message'],
        ];
    }

    /**
     * @param array<string, mixed> $job
     * @return array<string, mixed>
     */
    private static function runJobBatch(array $job, string $path): array
    {
        $type = (string) ($job['type'] ?? '');
        $offset = (int) ($job['offset'] ?? -1);
        $source = (string) ($job['source'] ?? 'other');

        return match ($type) {
            InstallImportQueue::TYPE_CONTACTS => InstallContactImporter::importBatch($path, $offset, $source),
            InstallImportQueue::TYPE_EMPLOYEES => InstallEmployeeImporter::importBatch($path, $offset, $source),
            InstallImportQueue::TYPE_BOOKINGS => InstallBookingImporter::importBatch($path, $offset, $source),
            InstallImportQueue::TYPE_ARTICLES => self::importArticles($path),
            InstallImportQueue::TYPE_VOUCHERS => InstallVoucherImporter::importBatch($path),
            default => throw new InvalidArgumentException('Unbekannter Importtyp: ' . $type),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function importArticles(string $path): array
    {
        $areaId = self::defaultArticleAreaId();
        $result = CalendarArticleImporter::importFromPath($path, $areaId);

        return [
            'done' => true,
            'progress' => 100,
            'imported' => (int) ($result['imported'] ?? 0) + (int) ($result['updated'] ?? 0),
            'skipped' => count($result['errors'] ?? []),
            'errors' => $result['errors'] ?? [],
            'message' => (string) ($result['message'] ?? 'Artikel importiert.'),
        ];
    }

    private static function defaultArticleAreaId(): int
    {
        $areas = CalendarStaffRepository::getAreas(true);
        if ($areas !== []) {
            return (int) $areas[0]['id'];
        }

        CalendarStaffRepository::saveArea(['name' => 'Standard', 'sort_order' => 0, 'is_active' => 1]);
        $areas = CalendarStaffRepository::getAreas(true);

        return $areas !== [] ? (int) $areas[0]['id'] : 0;
    }

    public static function finalizeInstallation(array $wizard): void
    {
        $company = $wizard['company'] ?? ['name' => 'CRM'];
        file_put_contents(DG_ROOT . '/storage/.installed', json_encode([
            'installed_at' => date('Y-m-d H:i:s'),
            'version' => is_readable(DG_ROOT . '/config/version.php') ? (string) require DG_ROOT . '/config/version.php' : '?',
            'company' => $company['name'] ?? '',
            'import_completed_at' => date('Y-m-d H:i:s'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
