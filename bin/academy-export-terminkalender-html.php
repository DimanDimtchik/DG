#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Exportiert Terminkalender-Seiten (Liste, Neuer Termin) als HTML für Academy-Screenshots.
 *
 * php bin/academy-export-terminkalender-html.php [--base=https://ganz-soft.de] [--user-id=9]
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
if ($user === null || !MenuRegistry::canAccess($user, 'terminkalender')) {
    fwrite(STDERR, "Kein Admin mit Terminkalender-Zugriff.\n");
    exit(1);
}

$outDir = DG_ROOT . '/storage/media/training/terminkalender';
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
        'settingsNav' => null,
        'settingsSelection' => null,
        'area' => null,
        'dept' => null,
        'bookingArticleOptions' => CalendarArticleRepository::bookingOptions(),
        'bookingEmployeeOptions' => CalendarStaffRepository::bookingEmployeeOptions(),
    ];
}

function exportHtml(string $baseUrl, string $path, array $layoutData, string $contentTemplate, string $title, string $currentPage, array $extra = []): void
{
    $layoutData['contentTemplate'] = $contentTemplate;
    $layoutData['title'] = $title;
    $layoutData['currentPage'] = $currentPage;
    $layoutData = array_merge($layoutData, $extra);

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

    file_put_contents($path, $html);
    echo "HTML: {$path}\n";
}

$layout = layoutVars($user);

exportHtml(
    $baseUrl,
    $outDir . '/terminkalender-list.html',
    $layout,
    'modules/terminkalender',
    'Terminkalender',
    'terminkalender',
    [
        'bookingList' => BookingRepository::paginate('', 1),
        'bookingSearch' => '',
    ]
);

$form = BookingRepository::emptyForm();
$form['slot_date'] = date('Y-m-d', strtotime('+3 days'));
$form['slot_time'] = '10:00';
$form['slot_datetime'] = $form['slot_date'] . 'T' . $form['slot_time'];
$form['status'] = 'gebucht';

exportHtml(
    $baseUrl,
    $outDir . '/terminkalender-new.html',
    $layout,
    'modules/terminkalender-form',
    'Neuer Termin',
    'terminkalender',
    [
        'bookingId' => null,
        'form' => $form,
        'formError' => null,
    ]
);

$meta = [
    'exported_at' => date('c'),
    'base_url' => $baseUrl,
    'user_id' => $user->id,
];
file_put_contents($outDir . '/terminkalender-export.meta.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Meta: {$outDir}/terminkalender-export.meta.json\n";
