<?php
declare(strict_types=1);

/**
 * Automatic update system.
 *
 * - Checks the central update server on the first business day of each month.
 * - Critical/forced updates are checked on every request.
 * - Downloads a ZIP, extracts code directories, runs migrations, clears OPcache.
 * - Never overwrites config/database.local.php, config/app.local.php, or storage/.
 */
final class UpdateChecker
{
    private const UPDATE_SERVER = 'https://dg.ganz-om.de/update';
    private const STATE_FILE    = '/storage/update-state.json';

    /** Directories/files that are NEVER overwritten by an update. */
    private const PROTECTED_PATHS = [
        'config/database.local.php',
        'config/app.local.php',
        'config/users.php',
        'config/cron.local.php',
        'storage',
        'tmp-upload',
    ];

    /** Code directories that ARE replaced during an update. */
    private const UPDATE_DIRS = ['src', 'views', 'assets', 'database', 'bin'];
    private const UPDATE_FILES = ['index.php', 'bootstrap.php', '.htaccess', 'config/version.php', 'config/app.php', 'config/database.php'];

    /**
     * Prüft monatlich bzw. bei Force-Update und wendet Updates an.
     */
    public static function runIfDue(): void    {
        if (!Database::isConfigured()) {
            return;
        }

        $state = self::loadState();

        if (self::isForcedUpdatePending($state)) {
            self::performUpdate($state);
            return;
        }

        if (self::isMonthlyCheckDue($state)) {
            self::checkForUpdate($state);
        }
    }

    /**
     * Called by the central admin panel to flag all instances for immediate update.
     * Sets a marker that bypasses the monthly schedule.
     */
    public static function flagForceUpdate(): void
    {
        $state = self::loadState();
        $state['force_pending'] = true;
        self::saveState($state);
    }

    /**
     * @return array<string, mixed> Persistierter Update-Status.
     */
    public static function getState(): array    {
        return self::loadState();
    }

    /**
     * @return array{last_check: string, current_version: string, available_version: string|null, last_update: string|null, last_error: string|null}|null
     */
    public static function lastCheckInfo(): ?array    {
        $state = self::loadState();
        if (empty($state['last_check'])) {
            return null;
        }
        return [
            'last_check'        => $state['last_check'],
            'current_version'   => App::version(),
            'available_version' => $state['available_version'] ?? null,
            'last_update'       => $state['last_update'] ?? null,
            'last_error'        => $state['last_error'] ?? null,
        ];
    }

    // ── Monthly schedule ────────────────────────────────────────────

    /** @param array<string, mixed> $state */
    private static function isMonthlyCheckDue(array $state): bool
    {
        $today = new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin'));

        $lastCheck = $state['last_check'] ?? null;
        if ($lastCheck !== null) {
            $lastMonth = (int) (new DateTimeImmutable($lastCheck))->format('Ym');
            $thisMonth = (int) $today->format('Ym');
            if ($lastMonth >= $thisMonth) {
                return false;
            }
        }

        return self::isFirstBusinessDay($today);
    }

    /** Erster Werktag des Monats (DE-Feiertage) oder Fallback bis Tag 7. */
    private static function isFirstBusinessDay(DateTimeImmutable $today): bool
    {
        $year  = (int) $today->format('Y');
        $month = (int) $today->format('n');
        $day   = (int) $today->format('j');

        $firstBusinessDay = self::findFirstBusinessDay($year, $month);

        if ($day === $firstBusinessDay) {
            return true;
        }

        // If nobody opened the CRM on the first business day,
        // allow the check on any subsequent business day within the first 7 days.
        if ($day > $firstBusinessDay && $day <= 7 && !self::isWeekendOrHoliday($today)) {
            return true;
        }

        return false;
    }

    /** @return int Tag im Monat (1–7) des ersten Werktags. */
    private static function findFirstBusinessDay(int $year, int $month): int
    {
        for ($d = 1; $d <= 7; $d++) {
            $date = new DateTimeImmutable("$year-$month-$d", new DateTimeZone('Europe/Berlin'));
            if (!self::isWeekendOrHoliday($date)) {
                return $d;
            }
        }
        return 1;
    }

    /** Wochenende oder deutscher Feiertag. */
    private static function isWeekendOrHoliday(DateTimeImmutable $date): bool
    {
        $dow = (int) $date->format('N'); // 6=Sat, 7=Sun
        if ($dow >= 6) {
            return true;
        }

        return in_array($date->format('m-d'), self::fixedHolidays(), true)
            || in_array($date->format('Y-m-d'), self::easterBasedHolidays((int) $date->format('Y')), true);
    }

    /** @return list<string> month-day strings */
    private static function fixedHolidays(): array
    {
        return ['01-01', '05-01', '10-03', '12-25', '12-26'];
    }

    /** @return list<string> Y-m-d Easter-based holidays */
    private static function easterBasedHolidays(int $year): array
    {
        $easter = new DateTimeImmutable(date('Y-m-d', easter_date($year)));
        return [
            $easter->modify('-2 days')->format('Y-m-d'),  // Karfreitag
            $easter->modify('+1 day')->format('Y-m-d'),   // Ostermontag
            $easter->modify('+39 days')->format('Y-m-d'), // Himmelfahrt
            $easter->modify('+50 days')->format('Y-m-d'), // Pfingstmontag
            $easter->modify('+60 days')->format('Y-m-d'), // Fronleichnam
        ];
    }

    // ── Force update ────────────────────────────────────────────────

    /** @param array<string, mixed> $state */
    private static function isForcedUpdatePending(array $state): bool
    {
        return !empty($state['force_pending']);
    }

    // ── Update logic ────────────────────────────────────────────────

    /** @param array<string, mixed> $state */
    private static function checkForUpdate(array &$state): void
    {
        $info = self::fetchVersionInfo();
        if ($info === null) {
            $state['last_check'] = date('Y-m-d H:i:s');
            $state['last_error'] = 'Could not reach update server';
            self::saveState($state);
            return;
        }

        $state['last_check']        = date('Y-m-d H:i:s');
        $state['available_version'] = $info['version'];
        $state['last_error']        = null;

        if (version_compare($info['version'], App::version(), '>')) {
            self::performUpdate($state, $info);
        } else {
            self::saveState($state);
        }
    }

    /**
     * @param array<string, mixed> $state
     * @param array{version?: string, url?: string}|null $info
     */
    private static function performUpdate(array &$state, ?array $info = null): void
    {
        if ($info === null) {
            $info = self::fetchVersionInfo();
        }
        if ($info === null || empty($info['url'])) {
            $state['last_error'] = 'Update info unavailable';
            self::saveState($state);
            return;
        }

        if (!version_compare($info['version'], App::version(), '>')) {
            $state['force_pending'] = false;
            self::saveState($state);
            return;
        }

        $tmpZip = DG_ROOT . '/tmp-upload/update-' . $info['version'] . '.zip';
        $tmpDir = DG_ROOT . '/tmp-upload/update-extract';

        try {
            self::downloadFile($info['url'], $tmpZip);
            self::extractAndApply($tmpZip, $tmpDir);

            MigrationRunner::runPending();

            if (function_exists('opcache_reset')) {
                opcache_reset();
            }

            $state['last_update']       = date('Y-m-d H:i:s');
            $state['installed_version'] = $info['version'];
            $state['force_pending']     = false;
            $state['last_error']        = null;
        } catch (Throwable $e) {
            $state['last_error'] = $e->getMessage();
        } finally {
            @unlink($tmpZip);
            if (is_dir($tmpDir)) {
                self::removeDir($tmpDir);
            }
            self::saveState($state);
        }
    }

    // ── HTTP / file operations ──────────────────────────────────────

    /** @return array{version: string, url?: string}|null */
    private static function fetchVersionInfo(): ?array
    {
        $url = self::UPDATE_SERVER . '/version.json';

        $ctx = stream_context_create([
            'http' => ['timeout' => 10, 'ignore_errors' => true],
            'ssl'  => ['verify_peer' => true],
        ]);

        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            return null;
        }

        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['version'])) {
            return null;
        }

        return $data;
    }

    /**
     * @throws RuntimeException Bei Download-Fehler.
     */
    private static function downloadFile(string $url, string $dest): void
    {
        $dir = dirname($dest);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $ctx = stream_context_create([
            'http' => ['timeout' => 120],
            'ssl'  => ['verify_peer' => true],
        ]);

        $data = file_get_contents($url, false, $ctx);
        if ($data === false) {
            throw new RuntimeException('Download failed: ' . $url);
        }

        file_put_contents($dest, $data);
    }

    /**
     * @throws RuntimeException Wenn ZIP nicht geöffnet werden kann.
     */
    private static function extractAndApply(string $zipPath, string $extractDir): void
    {
        if (is_dir($extractDir)) {
            self::removeDir($extractDir);
        }
        mkdir($extractDir, 0755, true);

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Cannot open update ZIP');
        }
        $zip->extractTo($extractDir);
        $zip->close();

        // Windows ZIPs may contain backslash paths; normalize into real directories.
        self::normalizeExtractedPaths($extractDir);

        // The ZIP may contain a single root folder – detect it.
        $entries = array_diff(scandir($extractDir) ?: [], ['.', '..']);
        $root = $extractDir;
        if (count($entries) === 1) {
            $single = $extractDir . '/' . reset($entries);
            if (is_dir($single)) {
                $root = $single;
            }
        }

        foreach (self::UPDATE_DIRS as $dir) {
            $src = $root . '/' . $dir;
            if (!is_dir($src)) {
                continue;
            }
            $dst = DG_ROOT . '/' . $dir;
            if (is_dir($dst)) {
                self::removeDir($dst);
            }
            rename($src, $dst);
        }

        foreach (self::UPDATE_FILES as $file) {
            $src = $root . '/' . $file;
            if (!is_file($src)) {
                continue;
            }
            $dst = DG_ROOT . '/' . $file;
            $dstDir = dirname($dst);
            if (!is_dir($dstDir)) {
                mkdir($dstDir, 0755, true);
            }
            copy($src, $dst);
        }
    }

    /** Normalisiert Backslash-Pfade aus Windows-ZIPs. */
    private static function normalizeExtractedPaths(string $extractDir): void
    {
        $items = scandir($extractDir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (!str_contains($item, '\\')) {
                continue;
            }
            $src = $extractDir . '/' . $item;
            if (!is_file($src)) {
                continue;
            }
            $normalized = str_replace('\\', '/', $item);
            $dst = $extractDir . '/' . $normalized;
            $dstDir = dirname($dst);
            if (!is_dir($dstDir)) {
                mkdir($dstDir, 0755, true);
            }
            rename($src, $dst);
        }
    }

    // ── State persistence ───────────────────────────────────────────

    /** Absoluter Pfad zur Update-State-Datei unter storage/. */
    private static function stateFile(): string
    {
        return DG_ROOT . self::STATE_FILE;
    }

    /** @return array<string, mixed> */
    private static function loadState(): array
    {
        $file = self::stateFile();
        if (!is_readable($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    /** @param array<string, mixed> $state */
    private static function saveState(array $state): void
    {
        $file = self::stateFile();
        $dir  = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($file, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    // ── Utility ─────────────────────────────────────────────────────

    /** Rekursives Löschen eines Verzeichnisses. */
    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
