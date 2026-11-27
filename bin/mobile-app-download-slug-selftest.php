<?php
declare(strict_types=1);

/**
 * MA0d: CRM-App-Download-Slug ist `app-download`, nicht `apps`.
 *
 * Usage: php bin/mobile-app-download-slug-selftest.php
 */

function assertMa0d(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$svc = $root . '/src/Mobile/MobileAppDownloadService.php';
$boot = $root . '/src/Website/WebsiteBootstrapService.php';
$mig = $root . '/database/migrations/162_mobile_app_download_slug.sql';
$runner = $root . '/src/Database/MigrationRunner.php';
$index = $root . '/index.php';
$check = $root . '/bin/check-apps-page.php';

assertMa0d(is_file($svc), 'MobileAppDownloadService vorhanden');
assertMa0d(is_file($mig), 'Migration 162 vorhanden');

require_once $root . '/bootstrap.php';

assertMa0d(MobileAppDownloadService::PAGE_SLUG === 'app-download', 'PAGE_SLUG = app-download');
assertMa0d(MobileAppDownloadService::LEGACY_PAGE_SLUG === 'apps', 'LEGACY_PAGE_SLUG = apps');
assertMa0d(MobileAppDownloadService::pagePath() === '/app-download', 'pagePath /app-download');
assertMa0d(MobileAppDownloadService::MENU_LABEL === 'App-Download', 'Menü-Label App-Download');
assertMa0d(
    (string) (MobileAppDownloadService::pageLayout()['page_kind'] ?? '') === 'mobile_app_download',
    'page_kind mobile_app_download'
);

$svcSrc = (string) file_get_contents($svc);
$bootSrc = (string) file_get_contents($boot);
$runnerSrc = (string) file_get_contents($runner);
$indexSrc = (string) file_get_contents($index);
$checkSrc = (string) file_get_contents($check);

assertMa0d(str_contains($svcSrc, 'migrateLegacyAppsSlug'), 'Legacy-Migration Methode');
assertMa0d(str_contains($svcSrc, 'rewriteMenuUrls'), 'Menü-Rewrite');
assertMa0d(
    !preg_match("/public const PAGE_SLUG\s*=\s*'apps'/", $svcSrc),
    'PAGE_SLUG nicht mehr apps'
);
assertMa0d(
    preg_match("/public const PAGE_SLUG\s*=\s*'app-download'/", $svcSrc) === 1,
    'PAGE_SLUG Quelltext app-download'
);
assertMa0d(str_contains($bootSrc, 'MobileAppDownloadService::pagePath()'), 'Bootstrap nutzt pagePath');
assertMa0d(!str_contains($bootSrc, "url' => '/apps'"), 'Bootstrap-Default ohne /apps');
assertMa0d(!str_contains($bootSrc, 'ensureAppsMenuItem'), 'alte ensureAppsMenuItem entfernt');
assertMa0d(str_contains($runnerSrc, '162_mobile_app_download_slug.sql'), 'MigrationRunner kennt 162');
assertMa0d(str_contains($indexSrc, 'MobileAppDownloadService::PAGE_SLUG'), 'APK-Fehlerseite linkt PAGE_SLUG');
assertMa0d(str_contains($checkSrc, 'PAGE_SLUG'), 'check-apps-page nutzt PAGE_SLUG');
assertMa0d(
    !preg_match("#href=\"/apps\"#", $indexSrc),
    'index.php kein hartes /apps mehr für Download-Zurück'
);

echo "MA0d Selftest bestanden.\n";
exit(0);
