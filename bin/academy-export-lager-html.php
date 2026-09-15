#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Exportiert Lager-Seiten als HTML für Academy-Screenshots.
 *
 * php bin/academy-export-lager-html.php [--base=https://ganz-soft.de] [--user-id=9]
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
if ($user === null || !MenuRegistry::canAccess($user, 'lager')) {
    fwrite(STDERR, "Kein Admin mit Lager-Zugriff.\n");
    exit(1);
}

$outDir = DG_ROOT . '/storage/media/training/lager';
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

function exportHtml(string $baseUrl, string $path, array $layoutData, string $contentTemplate, string $title, string $currentPage, array $extra = []): string
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
    return $html;
}

function injectBeforeBodyClose(string $html, string $snippet): string
{
    if (str_contains($html, '</body>')) {
        return str_replace('</body>', $snippet . "\n</body>", $html);
    }
    return $html . $snippet;
}

$layout = layoutVars($user);
$canEdit = RoleResolver::canEdit($user);
$stockItems = StockMovementService::stockOverview(false);
$stockMovements = StockMovementRepository::recent(50);
$openInventories = StockInventoryService::openInventories();
$activeInventory = $openInventories[0] ?? null;
$activeInventoryLines = $activeInventory !== null
    ? StockInventoryService::linesForInventory((int) $activeInventory['id'])
    : [];
$stockInventories = Database::pdo()->query(
    "SELECT * FROM dg_stock_inventories ORDER BY inventory_date DESC, id DESC LIMIT 20"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];
$stockPlaces = StockStructureRepository::allPlaces();
$stockLocationOptions = StockStructureRepository::locationOptions();
$stockHalls = StockStructureRepository::allHalls();
$stockShelves = StockStructureRepository::allShelves();
$stockOutboundVouchers = StockReceiptIssueService::outboundVoucherOptions();

$common = [
    'stockItems' => $stockItems,
    'stockMovements' => $stockMovements,
    'stockInventories' => $stockInventories,
    'activeInventory' => null,
    'activeInventoryLines' => [],
    'stockOutboundVouchers' => $stockOutboundVouchers,
    'stockPlaces' => $stockPlaces,
    'stockLocationOptions' => $stockLocationOptions,
    'stockHalls' => $stockHalls,
    'stockShelves' => $stockShelves,
    'canEdit' => $canEdit,
    'dbConnected' => true,
];

exportHtml(
    $baseUrl,
    $outDir . '/lager-overview.html',
    $layout,
    'modules/lager',
    'Lager',
    'lager',
    array_merge($common, ['lagerView' => 'overview'])
);

exportHtml(
    $baseUrl,
    $outDir . '/lager-platz-check.html',
    $layout,
    'modules/lager',
    'Lager — Platz-Check',
    'lager',
    array_merge($common, ['lagerView' => 'platz-check'])
);

$platzCheckHtml = file_get_contents($outDir . '/lager-platz-check.html');
$auditDemo = <<<'HTML'
<div id="dg-place-audit-panel" class="dg-panel dg-panel--nested">
  <h3 class="dg-subsection-title">Stellplatz A-01-03 · Palette</h3>
  <p class="dg-field-hint">Belegung: 2 Kartons · Reservierung: flexibel · Letzte Bewegung: Wareneingang, Demo-Artikel</p>
  <div class="dg-table-wrap">
    <table class="dg-table dg-table--compact">
      <thead><tr><th>Artikel</th><th>Menge</th><th>Karton</th></tr></thead>
      <tbody>
        <tr><td>DEMO-100 — Schulungsartikel</td><td>24 Stk</td><td>K-001</td></tr>
        <tr><td>DEMO-200 — Verpackung</td><td>6 Stk</td><td>K-002</td></tr>
      </tbody>
    </table>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var panel = document.getElementById('dg-place-audit-panel');
  if (panel) panel.hidden = false;
});
</script>
HTML;
$platzCheckHtml = injectBeforeBodyClose($platzCheckHtml, $auditDemo);
file_put_contents($outDir . '/lager-platz-check-demo.html', $platzCheckHtml);
echo "HTML: {$outDir}/lager-platz-check-demo.html\n";

exportHtml(
    $baseUrl,
    $outDir . '/lager-wareneingang.html',
    $layout,
    'modules/lager',
    'Lager — Wareneingang',
    'lager',
    array_merge($common, ['lagerView' => 'wareneingang'])
);

exportHtml(
    $baseUrl,
    $outDir . '/lager-warenausgang.html',
    $layout,
    'modules/lager',
    'Lager — Warenausgang',
    'lager',
    array_merge($common, ['lagerView' => 'warenausgang'])
);

exportHtml(
    $baseUrl,
    $outDir . '/lager-inventur.html',
    $layout,
    'modules/lager',
    'Lager — Inventur',
    'lager',
    array_merge($common, [
        'lagerView' => 'inventur',
        'activeInventory' => null,
        'activeInventoryLines' => [],
    ])
);

exportHtml(
    $baseUrl,
    $outDir . '/lager-bewegungen.html',
    $layout,
    'modules/lager',
    'Lager — Bewegungen',
    'lager',
    array_merge($common, ['lagerView' => 'bewegungen'])
);

file_put_contents($outDir . '/lager-export.meta.json', json_encode([
    'base_url' => $baseUrl,
    'stock_items' => count($stockItems),
    'movements' => count($stockMovements),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo 'Lager-Export fertig.' . "\n";
