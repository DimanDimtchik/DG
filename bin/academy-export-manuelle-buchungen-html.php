<?php
declare(strict_types=1);

/**
 * Exportiert die Seite „Manuelle Buchungen“ für Academy-Screenshots (Demo-Daten).
 *
 * php bin/academy-export-manuelle-buchungen-html.php [--base=https://ganz-soft.de] [--user-id=9]
 */

if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}

require_once DG_ROOT . '/src/autoload.php';

MigrationRunner::runPending();

$baseUrl = 'https://ganz-soft.de';
$userId = 0;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--base=')) {
        $baseUrl = rtrim(substr($arg, 7), '/') . '/';
    } elseif (str_starts_with($arg, '--user-id=')) {
        $userId = (int) substr($arg, 10);
    }
}

if (!Database::isConfigured()) {
    fwrite(STDERR, "Keine DB-Konfiguration.\n");
    exit(1);
}

$user = $userId > 0 ? UserRepository::findById($userId) : null;
if ($user === null) {
    $foundId = (int) (Database::pdo()->query(
        "SELECT id FROM dg_users WHERE role IN ('administrator', 'admin') ORDER BY id LIMIT 1"
    )->fetchColumn() ?: 0);
    $user = $foundId > 0 ? UserRepository::findById($foundId) : null;
}
if ($user === null || !MenuRegistry::canAccess($user, 'buchhaltung-manuelle-buchung')) {
    fwrite(STDERR, "Kein Benutzer mit Zugriff auf Manuelle Buchungen.\n");
    exit(1);
}

$outDir = DG_ROOT . '/storage/media/training/manuelle-buchungen';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}

$year = (int) date('Y');
$years = LedgerRepository::availableYears();
if ($years === []) {
    $years = [$year];
}
if (!in_array($year, $years, true)) {
    $years[] = $year;
    rsort($years);
}

/** Demo-Buchungen — keine echten Kundendaten. */
$manualBatches = [
    [
        'id' => 9001,
        'batch_date' => sprintf('%d-03-15', $year),
        'description' => 'Demo: Umbuchung Privatanteil',
        'line_count' => 2,
        'total_debit' => 120.00,
    ],
    [
        'id' => 9002,
        'batch_date' => sprintf('%d-06-01', $year),
        'description' => 'Demo: Korrektur Kontierung',
        'line_count' => 2,
        'total_debit' => 49.90,
    ],
];

$layoutData = [
    'user' => $user,
    'navMode' => RoleResolver::navMode($user),
    'departments' => RoleResolver::departmentsFor($user),
    'menuItems' => MenuRegistry::modules($user),
    'settingsItem' => MenuRegistry::settingsItem($user),
    'buchhaltungSection' => MenuRegistry::buchhaltungSection($user),
    'websiteSection' => MenuRegistry::websiteSection($user),
    'kdvSection' => MenuRegistry::kdvSection($user),
    'sidebarItems' => MenuRegistry::sidebarItems($user),
    'flash' => null,
    'canEdit' => RoleResolver::canEdit($user),
    'dbConfig' => DatabaseSettings::forForm(),
    'dbConnected' => true,
    'settingsNav' => null,
    'settingsSelection' => null,
    'area' => null,
    'dept' => null,
    'contentTemplate' => 'modules/buchhaltung-manuelle-buchung',
    'title' => 'Manuelle Buchungen',
    'currentPage' => 'buchhaltung-manuelle-buchung',
    'manualYear' => $year,
    'manualYears' => $years,
    'manualBatches' => $manualBatches,
];

ob_start();
View::render('layout/app', $layoutData);
$html = ob_get_clean();

if (!str_contains($html, '<base ')) {
    $html = preg_replace(
        '/<head>/i',
        '<head>' . "\n" . '  <base href="' . htmlspecialchars($baseUrl, ENT_QUOTES) . '">',
        $html,
        1
    ) ?? $html;
}

$html = preg_replace('/<style>\s*\.dg-cc-overlay[\s\S]*?<\/style>/i', '', $html) ?? $html;
$html = preg_replace('/<div[^>]*id="dg-cookie-consent"[^>]*>[\s\S]*?<\/div>\s*<script>window\.dgCookieConsent[\s\S]*?<\/script>/i', '', $html) ?? $html;
$html = preg_replace('/<div class="dg-cc-details"[\s\S]*?<div class="dg-cc-actions">[\s\S]*?<\/div>\s*<\/div>\s*<\/div>\s*<script>window\.dgCookieConsent[\s\S]*?<\/script>/i', '', $html) ?? $html;
$html = preg_replace('/<script>window\.dgCookieConsent[\s\S]*?<\/script>/i', '', $html) ?? $html;

$path = $outDir . '/manuelle-buchungen-page.html';
file_put_contents($path, $html);
file_put_contents($outDir . '/manuelle-buchungen-export.meta.json', json_encode([
    'base' => $baseUrl,
    'year' => $year,
    'exported_at' => date('c'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

echo "HTML: {$path}\n";
