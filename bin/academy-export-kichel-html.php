#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Exportiert Dashboard mit geöffnetem Kichel-Panel für Academy-Screenshots.
 *
 * php bin/academy-export-kichel-html.php [--base=https://ganz-soft.de] [--user-id=9]
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
if ($user === null) {
    fwrite(STDERR, "Kein Administrator für Kichel-Export gefunden.\n");
    exit(1);
}

$outDir = DG_ROOT . '/storage/media/training/kichel';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}

$navMode = RoleResolver::navMode($user);
$contentTemplate = 'dashboard';
$title = 'Dashboard';
$currentPage = 'dashboard';

ob_start();
View::render('layout/app', [
    'user' => $user,
    'navMode' => $navMode,
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
    'contentTemplate' => $contentTemplate,
    'title' => $title,
    'currentPage' => $currentPage,
]);
$html = ob_get_clean();

if (!str_contains($html, '<base ')) {
    $html = preg_replace(
        '/<head>/i',
        '<head>' . "\n" . '  <base href="' . htmlspecialchars($baseUrl, ENT_QUOTES) . '">',
        $html,
        1
    ) ?? $html;
}

$kichelScript = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function () {
  var fab = document.querySelector('[data-kichel-fab]');
  var panel = document.getElementById('dg-kichel-panel');
  var messages = document.querySelector('[data-kichel-messages]');
  if (fab) fab.setAttribute('aria-expanded', 'true');
  if (panel) panel.hidden = false;
  if (messages) {
    messages.innerHTML =
      '<div class="dg-kichel-msg dg-kichel-msg--bot">Hallo! Ich helfe dir, im CRM schnell den richtigen Weg zu finden — stell einfach deine Frage oder wähle ein Stichwort.</div>' +
      '<div class="dg-kichel-msg dg-kichel-msg--user">Wo trage ich die Umsatzsteuer-Identifikationsnummer ein?</div>' +
      '<div class="dg-kichel-msg dg-kichel-msg--bot">Die Umsatzsteuer-Identifikationsnummer trägst du beim Kontakt ein: Kontakte öffnen, Kunde bearbeiten, Abschnitt Kunde und Lieferant — Feld USt-IdNr. Für deine eigene Firma: Einstellungen, Firma.</div>';
  }
});
</script>
HTML;

$htmlClosed = str_replace('</body>', $kichelScript . "\n</body>", $html);
file_put_contents($outDir . '/kichel-dashboard-closed.html', $html);
echo "HTML: {$outDir}/kichel-dashboard-closed.html\n";

file_put_contents($outDir . '/kichel-dashboard-open.html', $htmlClosed);
echo "HTML: {$outDir}/kichel-dashboard-open.html\n";

file_put_contents($outDir . '/kichel-export.meta.json', json_encode([
    'base_url' => $baseUrl,
    'user_id' => $user->id,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Kichel-Export fertig.\n";
