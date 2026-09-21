<?php
declare(strict_types=1);

/**
 * Exportiert Media-Seiten (früher „Bilder“) für Academy-Screenshots.
 *
 * php bin/academy-export-media-html.php [--base=https://ganz-soft.de] [--user-id=9]
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
if ($user === null || !RoleResolver::isAdmin($user)) {
    fwrite(STDERR, "Kein Administrator für Media-Export.\n");
    exit(1);
}

MediaRepository::ensureTables();

$outDir = DG_ROOT . '/storage/media/training/media';
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
    ];
}

/**
 * @param array<string, mixed> $layoutData
 * @param array<string, mixed> $extra
 */
function exportHtml(
    string $baseUrl,
    string $path,
    array $layoutData,
    string $contentTemplate,
    string $title,
    string $currentPage,
    array $extra = []
): string {
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

    $html = preg_replace('/<style>\s*\.dg-cc-overlay[\s\S]*?<\/style>/i', '', $html) ?? $html;
    $html = preg_replace('/<div[^>]*id="dg-cookie-consent"[^>]*>[\s\S]*?<\/div>\s*<script>window\.dgCookieConsent[\s\S]*?<\/script>/i', '', $html) ?? $html;
    $html = preg_replace('/<div class="dg-cc-details"[\s\S]*?<div class="dg-cc-actions">[\s\S]*?<\/div>\s*<\/div>\s*<\/div>\s*<script>window\.dgCookieConsent[\s\S]*?<\/script>/i', '', $html) ?? $html;
    $html = preg_replace('/<script>window\.dgCookieConsent[\s\S]*?<\/script>/i', '', $html) ?? $html;

    file_put_contents($path, $html);
    echo "HTML: {$path}\n";

    return $html;
}

$layout = layoutVars($user);
$mediaList = MediaRepository::listWithUsage();

exportHtml(
    $baseUrl,
    $outDir . '/media-list.html',
    $layout,
    'modules/bilder',
    'Media',
    'bilder',
    [
        'mediaList' => $mediaList,
        'dbConnected' => true,
    ]
);

$editItem = $mediaList[0] ?? null;
if ($editItem === null) {
    // Minimaler Demo-Eintrag nur für Screenshot-Struktur (kein DB-Insert)
    $editItem = [
        'media_id' => 'demo000000000000000000000001',
        'title' => 'Demo-Logo',
        'original_name' => 'demo-logo.png',
        'alt_text' => 'Demo Firmenlogo',
        'mime_type' => 'image/png',
        'extension' => 'png',
        'width' => 320,
        'height' => 80,
        'size_bytes' => 12000,
        'uploaded_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'usages' => [],
        'source_note' => 'Demo',
    ];
}

exportHtml(
    $baseUrl,
    $outDir . '/media-edit.html',
    $layout,
    'modules/bilder-edit',
    'Bild bearbeiten',
    'bilder',
    [
        'mediaItem' => $editItem,
        'mediaIsNew' => false,
        'dbConnected' => true,
    ]
);

file_put_contents($outDir . '/media-export.meta.json', json_encode([
    'base_url' => $baseUrl,
    'user_id' => $user->id,
    'list_count' => count($mediaList),
    'edit_media_id' => (string) ($editItem['media_id'] ?? ''),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Media-Export fertig.\n";
