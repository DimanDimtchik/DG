<?php
declare(strict_types=1);

/**
 * Exportiert Bankabgleich-Seite für Academy-Screenshots (Live-Daten nach Demo-Import).
 *
 * php bin/academy-export-bankabgleich-html.php [--base=https://dg.ganz-om.de/]
 */

if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}

require_once DG_ROOT . '/src/autoload.php';

MigrationRunner::runPending();

$baseUrl = 'https://dg.ganz-om.de/';
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
if ($user === null || !MenuRegistry::canAccess($user, 'buchhaltung-bankabgleich')) {
    fwrite(STDERR, "Kein Benutzer mit Zugriff auf Bankabgleich.\n");
    exit(1);
}

$outDir = DG_ROOT . '/storage/media/training/bankabgleich';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}

BankTransactionRepository::backfillFingerprints();
$classified = BankGhostDetectionService::classifyOpenTransactions();
$matched = BankTransactionRepository::list('matched');

// Für Schulungsclip: Beispiel-Geisterumsatz, falls die Live-DB gerade keine hat.
if ($classified['ghosts'] === [] && $classified['open'] !== []) {
    $sample = $classified['open'][0];
    $classified['ghosts'] = [[
        'id' => (int) ($sample['id'] ?? 0),
        'transaction_date' => (string) ($sample['transaction_date'] ?? '2026-03-10'),
        'reference_text' => (string) ($sample['reference_text'] ?? 'Demo: doppelter Import'),
        'counterparty_name' => (string) ($sample['counterparty_name'] ?? 'Demo Kunde Süd AG'),
        'amount_display' => (string) ($sample['amount_display'] ?? '2.380,00 €'),
        'ghost_label' => 'Möglicher Doppelimport',
        'ghost_detail' => 'Schulungsbeispiel: gleicher Umsatz schon vorhanden — prüfen, dann Verknüpfen oder Ausblenden.',
        'ghost_voucher_id' => 0,
        'ghost_payment_id' => 0,
    ]];
}

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
    'flash' => [
        'type' => 'success',
        'message' => 'CAMT importiert: 17 Umsätze (0 übersprungen). Automatischer Abgleich durchgeführt.',
    ],
    'canEdit' => RoleResolver::canEdit($user),
    'dbConfig' => DatabaseSettings::forForm(),
    'dbConnected' => true,
    'settingsNav' => null,
    'settingsSelection' => null,
    'area' => null,
    'dept' => null,
    'contentTemplate' => 'modules/buchhaltung-bankabgleich',
    'title' => 'Bankabgleich',
    'currentPage' => 'buchhaltung-bankabgleich',
    'bankTransactionsOpen' => $classified['open'],
    'bankTransactionsGhosts' => $classified['ghosts'],
    'bankTransactionsMatched' => $matched,
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

$path = $outDir . '/bankabgleich-page.html';
file_put_contents($path, $html);
file_put_contents($outDir . '/bankabgleich-export.meta.json', json_encode([
    'base' => $baseUrl,
    'open' => count($classified['open']),
    'ghosts' => count($classified['ghosts']),
    'matched' => count($matched),
    'exported_at' => date('c'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

echo "HTML: {$path}\n";
echo "open=" . count($classified['open']) . " ghosts=" . count($classified['ghosts']) . " matched=" . count($matched) . "\n";
