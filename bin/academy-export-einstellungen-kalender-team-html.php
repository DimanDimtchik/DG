#!/usr/bin/env php
<?php
declare(strict_types=1);

/** Export: Einstellungen → Bereiche und Mitglieder (Tabs Bereiche + Mitarbeiter). */

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

$shared = [
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
    'settingsSelection' => SettingsRegistry::resolve('kalender-team'),
    'area' => null,
    'dept' => null,
    'calendarAreas' => CalendarStaffRepository::getAreas(),
    'calendarEmployees' => CalendarStaffRepository::getEmployees(),
    'calendarAbsences' => CalendarStaffRepository::getAllAbsences(),
    'calendarLinkUsers' => CalendarStaffRepository::linkableUsers(),
    'calendarDepartmentOptions' => DepartmentRepository::optionsForSelect(),
    'calendarLinkContacts' => CalendarStaffRepository::linkableContacts(),
    'calendarDepartmentSuggestions' => CalendarStaffRepository::departmentMemberSuggestions(),
];

function exportTeamHtml(string $baseUrl, string $path, array $data, string $ctab): void
{
    $data['calendarTeamTab'] = $ctab;
    $data['contentTemplate'] = 'modules/einstellungen';
    $data['title'] = 'Einstellungen — Bereiche und Mitglieder';
    $data['currentPage'] = 'einstellungen';
    ob_start();
    View::render('layout/app', $data);
    $html = ob_get_clean();
    if (!str_contains($html, '<base ')) {
        $html = preg_replace(
            '/<head>/i',
            '<head>' . "\n" . '  <base href="' . htmlspecialchars($baseUrl, ENT_QUOTES) . '">',
            $html,
            1
        ) ?? $html;
    }
    file_put_contents($path, $html);
    echo "HTML: {$path}\n";
}

exportTeamHtml($baseUrl, $outDir . '/einstellungen-kalender-bereiche.html', $shared, 'bereiche');
exportTeamHtml($baseUrl, $outDir . '/einstellungen-kalender-mitglieder.html', $shared, 'mitarbeiter');

file_put_contents(
    $outDir . '/einstellungen-kalender-team-export.meta.json',
    json_encode([
        'exported_at' => date('c'),
        'base_url' => $baseUrl,
        'user_id' => $user->id,
        'kind' => 'einstellungen-kalender-team',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);
echo "Meta: {$outDir}/einstellungen-kalender-team-export.meta.json\n";
