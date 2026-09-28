#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Exportiert Academy-Screenshots: Einstellungen → Termine → Arbeitszeiten
 * (Liste + Formular „Neue Arbeitszeit hinzufügen“ geöffnet).
 *
 * php bin/academy-export-einstellungen-arbeitszeiten-html.php [--base=https://ganz-soft.de] [--user-id=9]
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
if ($user === null || !MenuRegistry::canAccess($user, 'einstellungen')) {
    fwrite(STDERR, "Kein Admin mit Einstellungen-Zugriff.\n");
    exit(1);
}

$outDir = DG_ROOT . '/storage/media/training/einstellungen';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}

/** @return array<string, mixed> */
function layoutVars(User $user): array
{
    return [
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
        'settingsNav' => SettingsRegistry::navigation(),
        'settingsSelection' => SettingsRegistry::resolve('arbeitszeiten'),
        'area' => null,
        'dept' => null,
        'calendarWorkingHours' => CalendarWorkingHoursRepository::all(),
    ];
}

function exportAppHtml(string $baseUrl, string $path, array $layoutData, bool $formOpen = false): void
{
    $layoutData['contentTemplate'] = 'modules/einstellungen';
    $layoutData['title'] = 'Einstellungen — Arbeitszeiten';
    $layoutData['currentPage'] = 'einstellungen';

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

    if ($formOpen) {
        $html = preg_replace(
            '/<details class="dg-collapsible-form">/',
            '<details class="dg-collapsible-form" open>',
            $html,
            1
        ) ?? $html;
    }

    file_put_contents($path, $html);
    echo "HTML: {$path}\n";
}

$layout = layoutVars($user);

exportAppHtml(
    $baseUrl,
    $outDir . '/einstellungen-arbeitszeiten.html',
    $layout,
    false
);

exportAppHtml(
    $baseUrl,
    $outDir . '/einstellungen-arbeitszeiten-neu.html',
    $layout,
    true
);

$meta = [
    'exported_at' => date('c'),
    'base_url' => $baseUrl,
    'user_id' => $user->id,
    'kind' => 'einstellungen-arbeitszeiten',
];
file_put_contents(
    $outDir . '/einstellungen-arbeitszeiten-export.meta.json',
    json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);
echo "Meta: {$outDir}/einstellungen-arbeitszeiten-export.meta.json\n";
