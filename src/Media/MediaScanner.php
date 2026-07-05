<?php
declare(strict_types=1);

final class MediaScanner
{
    /** @var array<string, string> */
    private const CONTEXT_LABELS = [
        'views/login.php' => 'Login-Seite',
        'views/register.php' => 'Registrierung',
        'views/partials/head.php' => 'HTML-Kopf (Favicon)',
        'views/layout/app.php' => 'CRM-Kopfzeile',
        'views/offline.php' => 'Startseite (offline)',
        'settings:company' => 'Einstellungen → Firmendaten',
        'settings:appearance' => 'Einstellungen → Schriften & Logo',
        'settings:mail' => 'Einstellungen → E-Mail',
    ];

  /**
   * @return array{contexts: int, references: int, closed: int}
   */
    public static function scanAll(): array
    {
        MediaRepository::ensureTables();

        /** @var array<string, array<string, true>> $found context_key => [media_id => true] */
        $found = [];

        self::scanDirectory(DG_ROOT . '/views', 'views', $found);
        self::scanDirectory(DG_ROOT . '/src', 'src', $found);
        self::scanSettings($found);

        $references = 0;
        $contexts = 0;

        foreach ($found as $contextKey => $mediaIds) {
            $contexts++;
            $label = self::contextLabel($contextKey);
            foreach (array_keys($mediaIds) as $mediaId) {
                if (!MediaRepository::find($mediaId)) {
                    continue;
                }
                MediaRepository::syncUsage($mediaId, $contextKey, $label);
                $references++;
            }
        }

        $closed = self::closeMissingReferences($found);

        return [
            'contexts' => $contexts,
            'references' => $references,
            'closed' => $closed,
        ];
    }

    /** @param array<string, array<string, true>> $found */
    private static function scanDirectory(string $dir, string $prefix, array &$found): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $dir = rtrim(str_replace('\\', '/', $dir), '/');

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            if (!str_ends_with(strtolower($path), '.php')) {
                continue;
            }

            $relative = $prefix . '/' . substr($path, strlen($dir) + 1);
            $content = (string) @file_get_contents($path);
            if ($content === '') {
                continue;
            }

            self::extractFromContent($content, $relative, $found);
        }
    }

    /** @param array<string, array<string, true>> $found */
    private static function scanSettings(array &$found): void
    {
        if (!Database::isConfigured()) {
            return;
        }

        $logoId = AppearanceSettings::logoMediaId();
        if ($logoId !== '' && MediaId::isValid($logoId)) {
            if (!isset($found['settings:appearance'])) {
                $found['settings:appearance'] = [];
            }
            $found['settings:appearance'][$logoId] = true;
        }

        $faviconId = AppearanceSettings::faviconMediaId();
        if ($faviconId !== '' && MediaId::isValid($faviconId)) {
            if (!isset($found['settings:appearance'])) {
                $found['settings:appearance'] = [];
            }
            $found['settings:appearance'][$faviconId] = true;
            $found['views/partials/head.php'][$faviconId] = true;
        }

        $stmt = Database::pdo()->query('SELECT setting_key, value_json FROM dg_settings');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = 'settings:' . (string) $row['setting_key'];
            $content = (string) $row['value_json'];
            self::extractFromContent($content, $key, $found);
        }
    }

    /** @param array<string, array<string, true>> $found */
    private static function extractFromContent(string $content, string $contextKey, array &$found): void
    {
        if (!isset($found[$contextKey])) {
            $found[$contextKey] = [];
        }

        if (preg_match_all(MediaId::PATTERN, $content, $matches)) {
            foreach ($matches[0] as $mediaId) {
                $found[$contextKey][$mediaId] = true;
            }
        }

        if (preg_match_all('#/app/media\?id=([0-9]{14}_[a-f0-9]{6})#', $content, $urlMatches)) {
            foreach ($urlMatches[1] as $mediaId) {
                $found[$contextKey][$mediaId] = true;
            }
        }

        if (preg_match_all('#data-dg-media=["\']([0-9]{14}_[a-f0-9]{6})["\']#', $content, $dataMatches)) {
            foreach ($dataMatches[1] as $mediaId) {
                $found[$contextKey][$mediaId] = true;
            }
        }
    }

    private static function contextLabel(string $contextKey): string
    {
        if (isset(self::CONTEXT_LABELS[$contextKey])) {
            return self::CONTEXT_LABELS[$contextKey];
        }

        if (str_starts_with($contextKey, 'settings:')) {
            return 'Einstellungen → ' . substr($contextKey, 9);
        }

        if (str_starts_with($contextKey, 'views/')) {
            return 'Ansicht: ' . substr($contextKey, 6);
        }

        return $contextKey;
    }

    /** @param array<string, array<string, true>> $found */
    private static function closeMissingReferences(array $found): int
    {
        $pdo = Database::pdo();
        $active = $pdo->query(
            'SELECT media_id, context_key FROM dg_media_usage WHERE used_until IS NULL'
        )->fetchAll(PDO::FETCH_ASSOC);

        $closed = 0;
        foreach ($active as $row) {
            $mediaId = (string) $row['media_id'];
            $contextKey = (string) $row['context_key'];
            if (!isset($found[$contextKey][$mediaId])) {
                MediaRepository::closeStaleUsage($mediaId, $contextKey);
                $closed++;
            }
        }

        return $closed;
    }
}
