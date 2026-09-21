<?php
declare(strict_types=1);

/**
 * Importiert Demo-Bilder (Becher) und exportiert HTML für Media-Clip Zuschneiden/Freistellen.
 *
 * php bin/academy-export-media-crop-html.php [--base=https://dg.ganz-om.de/] [--import-only]
 */

if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

MigrationRunner::runPending();

$baseUrl = 'https://dg.ganz-om.de/';
$importOnly = in_array('--import-only', $argv, true);
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--base=')) {
        $baseUrl = rtrim(substr($arg, 7), '/') . '/';
    }
}

if (!Database::isConfigured()) {
    fwrite(STDERR, "Keine DB.\n");
    exit(1);
}

$userId = (int) (Database::pdo()->query(
    "SELECT id FROM dg_users WHERE role IN ('administrator','admin') ORDER BY id LIMIT 1"
)->fetchColumn() ?: 0);
$user = UserRepository::findById($userId);
if ($user === null || !RoleResolver::isAdmin($user)) {
    fwrite(STDERR, "Kein Admin.\n");
    exit(1);
}

MediaRepository::ensureTables();

$demoDir = DG_ROOT . '/storage/media/training/media/demo';
$variants = [
    'orig' => [
        'file' => 'media-demo-becher.png',
        'title' => 'Demo Becher (Original)',
        'alt' => 'Produktfoto Becher auf Grün — Schulungsdemo',
        'note' => 'Akademie-Demo: Original vor Zuschnitt/Freistellen.',
        'id_suffix' => 'a1b2c3',
    ],
    'crop' => [
        'file' => 'media-demo-becher-crop.png',
        'title' => 'Demo Becher (Zuschnitt)',
        'alt' => 'Zugeschittener Becher — Schulungsdemo',
        'note' => 'Akademie-Demo: Abgeleitet beim Zuschneiden.',
        'id_suffix' => 'd4e5f6',
    ],
    'frei' => [
        'file' => 'media-demo-becher-frei.png',
        'title' => 'Demo Becher (Freigestellt)',
        'alt' => 'Freigestellter Becher ohne Hintergrund — Schulungsdemo',
        'note' => 'Akademie-Demo: Abgeleitet beim Freistellen.',
        'id_suffix' => '789abc',
    ],
];

/** @return array<string, mixed> */
function upsertDemoMedia(string $fixedId, string $absFile, string $title, string $alt, string $note, int $userId): array
{
    if (!is_file($absFile)) {
        throw new RuntimeException('Fehlt: ' . $absFile);
    }
    $info = @getimagesize($absFile);
    $w = is_array($info) ? (int) ($info[0] ?? 0) : 0;
    $h = is_array($info) ? (int) ($info[1] ?? 0) : 0;
    $size = (int) filesize($absFile);
    $stored = 'original.png';

    $dir = MediaStorage::baseDir() . '/' . $fixedId;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $dest = $dir . '/' . $stored;
    if (!copy($absFile, $dest)) {
        throw new RuntimeException('Kopieren fehlgeschlagen: ' . $dest);
    }
    MediaStorage::applyReadablePermissions($dest);

    $existing = MediaRepository::find($fixedId);
    $meta = [
        'original_name' => basename($absFile),
        'stored_name' => $stored,
        'mime_type' => 'image/png',
        'extension' => 'png',
        'width' => $w > 0 ? $w : null,
        'height' => $h > 0 ? $h : null,
        'size_bytes' => $size,
        'source_note' => $note,
        'title' => $title,
        'alt_text' => $alt,
    ];
    if ($existing === null) {
        MediaRepository::insert($fixedId, $meta, $userId);
        echo "Media angelegt: {$fixedId} ({$title})\n";
    } else {
        MediaRepository::updateFileMeta($fixedId, $meta);
        $pdo = Database::pdo();
        $pdo->prepare(
            'UPDATE dg_media SET title = :t, alt_text = :a, source_note = :n, status = \'active\' WHERE media_id = :id'
        )->execute(['t' => $title, 'a' => $alt, 'n' => $note, 'id' => $fixedId]);
        echo "Media aktualisiert: {$fixedId} ({$title})\n";
    }

    $row = MediaRepository::find($fixedId);
    if ($row === null) {
        throw new RuntimeException('Media nicht lesbar: ' . $fixedId);
    }
    $row['usages'] = MediaRepository::usageForMedia($fixedId);

    return $row;
}

$stamp = '20260917190000_';
$ids = [];
foreach ($variants as $key => $cfg) {
    $id = $stamp . $cfg['id_suffix'];
    $ids[$key] = upsertDemoMedia(
        $id,
        $demoDir . '/' . $cfg['file'],
        $cfg['title'],
        $cfg['alt'],
        $cfg['note'],
        (int) $user->id
    );
}

if ($importOnly) {
    echo "Import fertig.\n";
    exit(0);
}

$outDir = DG_ROOT . '/storage/media/training/media';

$layout = [
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
    'canEdit' => true,
    'dbConfig' => DatabaseSettings::forForm(),
    'dbConnected' => true,
    'settingsNav' => null,
    'settingsSelection' => null,
    'area' => null,
    'dept' => null,
];

/**
 * @param array<string, mixed> $layoutData
 * @param array<string, mixed> $extra
 */
function exportMediaHtml(
    string $baseUrl,
    string $path,
    array $layoutData,
    string $contentTemplate,
    string $title,
    array $extra = []
): void {
    $layoutData['contentTemplate'] = $contentTemplate;
    $layoutData['title'] = $title;
    $layoutData['currentPage'] = 'bilder';
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
    $html = preg_replace('/<style>\s*\.dg-cc-overlay[\s\S]*?<\/style>/i', '', $html) ?? $html;
    $html = preg_replace('/<div[^>]*id="dg-cookie-consent"[^>]*>[\s\S]*?<\/div>\s*<script>window\.dgCookieConsent[\s\S]*?<\/script>/i', '', $html) ?? $html;
    $html = preg_replace('/<script>window\.dgCookieConsent[\s\S]*?<\/script>/i', '', $html) ?? $html;
    file_put_contents($path, $html);
    echo "HTML: {$path}\n";
}

$list = MediaRepository::listWithUsage();
exportMediaHtml($baseUrl, $outDir . '/media-list-demo.html', $layout, 'modules/bilder', 'Media', [
    'mediaList' => $list,
]);

exportMediaHtml($baseUrl, $outDir . '/media-edit-demo.html', $layout, 'modules/bilder-edit', 'Bild bearbeiten', [
    'mediaItem' => $ids['orig'],
    'mediaIsNew' => false,
]);
exportMediaHtml($baseUrl, $outDir . '/media-edit-crop.html', $layout, 'modules/bilder-edit', 'Bild bearbeiten', [
    'mediaItem' => $ids['crop'],
    'mediaIsNew' => false,
]);
exportMediaHtml($baseUrl, $outDir . '/media-edit-frei.html', $layout, 'modules/bilder-edit', 'Bild bearbeiten', [
    'mediaItem' => $ids['frei'],
    'mediaIsNew' => false,
]);
// Crop-Modal: gleiche Seite, Capture öffnet Modal + setzt Preview lokal
copy($outDir . '/media-edit-demo.html', $outDir . '/media-crop-modal.html');

file_put_contents($outDir . '/media-crop-export.meta.json', json_encode([
    'base' => $baseUrl,
    'ids' => [
        'orig' => $ids['orig']['media_id'] ?? '',
        'crop' => $ids['crop']['media_id'] ?? '',
        'frei' => $ids['frei']['media_id'] ?? '',
    ],
    'exported_at' => date('c'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

echo "Export fertig.\n";
