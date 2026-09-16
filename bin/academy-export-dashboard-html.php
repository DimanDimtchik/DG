#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Exportiert das echte CRM-Dashboard als HTML (1:1-Rendering) für Screenshot/Video.
 * Assets werden über <base href> von der Live-Instanz geladen.
 *
 * Aufruf auf Master/Live: php bin/academy-export-dashboard-html.php [--base=https://ganz-soft.de] [--user-id=9]
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
    $stmt = Database::pdo()->query(
        "SELECT id FROM dg_users WHERE role IN ('administrator', 'admin') ORDER BY id LIMIT 1"
    );
    $foundId = (int) ($stmt->fetchColumn() ?: 0);
    $user = $foundId > 0 ? UserRepository::findById($foundId) : null;
}
if ($user === null) {
    fwrite(STDERR, "Kein Administrator für Dashboard-Export gefunden.\n");
    exit(1);
}

$navMode = RoleResolver::navMode($user);
$departments = RoleResolver::departmentsFor($user);
$menuItems = MenuRegistry::modules($user);
$settingsItem = MenuRegistry::settingsItem($user);
$buchhaltungSection = MenuRegistry::buchhaltungSection($user);
$websiteSection = MenuRegistry::websiteSection($user);
$kdvSection = MenuRegistry::kdvSection($user);
$sidebarItems = MenuRegistry::sidebarItems($user);
$contentTemplate = 'dashboard';
$title = 'Dashboard';
$currentPage = 'dashboard';
$area = null;
$dept = null;
$flash = null;
$canEdit = RoleResolver::canEdit($user);
$dbConfig = DatabaseSettings::forForm();
$dbConnected = true;
$settingsNav = null;
$settingsSelection = null;

ob_start();
View::render('layout/app', compact(
    'title',
    'user',
    'navMode',
    'departments',
    'contentTemplate',
    'area',
    'dept',
    'menuItems',
    'settingsItem',
    'buchhaltungSection',
    'websiteSection',
    'kdvSection',
    'currentPage',
    'settingsNav',
    'settingsSelection',
    'flash',
    'dbConfig',
    'dbConnected',
    'canEdit',
    'sidebarItems',
));
$html = ob_get_clean();

if (!str_contains($html, '<base ')) {
    $html = preg_replace(
        '/<head>/i',
        '<head>' . "\n" . '  <base href="' . htmlspecialchars($baseUrl, ENT_QUOTES) . '">',
        $html,
        1
    ) ?? $html;
}

$outDir = DG_ROOT . '/storage/media/training/allgemein';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}

$htmlPath = $outDir . '/dashboard-capture.html';
file_put_contents($htmlPath, $html);

$tiles = MenuRegistry::dashboardTiles($user);
$meta = [
    'base_url' => $baseUrl,
    'user_id' => $user->id,
    'display_name' => $user->displayName,
    'tiles' => $tiles,
];
$metaPath = $outDir . '/dashboard-capture.meta.json';
file_put_contents($metaPath, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "HTML: {$htmlPath}\n";
echo "Meta: {$metaPath}\n";
echo 'Kacheln: ' . count($tiles) . "\n";
