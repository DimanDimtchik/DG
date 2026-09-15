#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Exportiert Konten- und Kontenübersicht-Seiten als HTML für Academy-Screenshots.
 *
 * php bin/academy-export-konten-html.php [--base=https://ganz-soft.de] [--user-id=9]
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

/** @param array<string, mixed> $account */
function academyPublicAccount(array $account): array
{
    $hints = is_array($account['hints'] ?? null) ? $account['hints'] : [];
    $searchTerms = ChartAccountHintTerms::normalizeList($hints['search_terms'] ?? []);

    return [
        'account_number' => (string) ($account['account_number'] ?? ''),
        'name' => (string) ($account['name'] ?? ''),
        'section' => (string) ($account['section'] ?? ''),
        'section_label' => (string) ($account['section_label'] ?? ''),
        'skr_type' => (string) ($account['skr_type'] ?? ''),
        'hints' => $hints,
        'search_terms' => $searchTerms,
        'digit_breakdown' => is_array($account['digit_breakdown'] ?? null) ? $account['digit_breakdown'] : [],
    ];
}

function injectKontenHint(string $html, array $account): string
{
    $json = json_encode($account, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    if ($json === false) {
        return $html;
    }
    $snippet = <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {
  var account = {$json};
  var numInput = document.getElementById('dg-account-number');
  if (numInput) numInput.value = account.account_number || '';
  var empty = document.getElementById('dg-account-empty');
  var panel = document.getElementById('dg-account-hint-panel');
  if (empty) empty.hidden = true;
  if (panel) panel.hidden = false;
  var hints = account.hints || {};
  var set = function (id, text) {
    var el = document.getElementById(id);
    if (el) el.textContent = text || '';
  };
  set('dg-account-hint-number', account.account_number);
  set('dg-account-hint-name', account.name);
  set('dg-account-hint-meta', (account.section_label || account.section || '') + (account.skr_type ? ' · ' + String(account.skr_type).toUpperCase() : ''));
  set('dg-account-hint-summary', hints.summary || '');
  var tags = document.getElementById('dg-account-hint-search-tags');
  if (tags && Array.isArray(account.search_terms)) {
    tags.innerHTML = account.search_terms.map(function (t) {
      return '<span class="dg-account-hint__tag">' + t + '</span>';
    }).join('');
  }
  var digits = document.getElementById('dg-account-hint-digits');
  if (digits && Array.isArray(account.digit_breakdown)) {
    digits.innerHTML = account.digit_breakdown.map(function (item) {
      return '<div class="dg-account-hint__digit"><span class="dg-account-hint__digit-value">' + item.value + '</span><div class="dg-account-hint__digit-text">' + (item.label || '') + '</div></div>';
    }).join('');
  }
  var examples = document.getElementById('dg-account-hint-examples');
  var exWrap = document.getElementById('dg-account-hint-examples-wrap');
  if (examples && Array.isArray(hints.examples) && hints.examples.length) {
    examples.innerHTML = hints.examples.map(function (e) { return '<li>' + e + '</li>'; }).join('');
    if (exWrap) exWrap.hidden = false;
  }
});
</script>
HTML;
    return str_replace('</body>', $snippet . "\n</body>", $html);
}

$outDir = DG_ROOT . '/storage/media/training/konten';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}

$layout = layoutVars($user);
$skrType = ChartOfAccountsSettings::activeSkrType();
ChartAccountRepository::ensureSeeded($skrType);
$chartOfAccountsConfig = ChartOfAccountsSettings::forForm();
$chartAccountCount = ChartAccountRepository::countForSkr();
$chartCatalogCount = ChartAccountCatalog::catalogCount($skrType);
$chartHintCount = ChartAccountRepository::countWithDetailedHints($skrType);

$kontenHtml = exportHtml(
    $baseUrl,
    $outDir . '/konten-search.html',
    $layout,
    'modules/buchhaltung-konten',
    'Konten',
    'buchhaltung-konten',
    [
        'chartOfAccountsConfig' => $chartOfAccountsConfig,
        'chartAccountCount' => $chartAccountCount,
        'chartCatalogCount' => $chartCatalogCount,
        'chartHintCount' => $chartHintCount,
    ]
);

$hintAccount = ChartAccountRepository::findByNumber('8400', $skrType)
    ?? ChartAccountRepository::findByNumber('4930', $skrType);
if ($hintAccount !== null) {
    $public = academyPublicAccount($hintAccount);
    $hintHtml = injectKontenHint($kontenHtml, $public);
    file_put_contents($outDir . '/konten-hint-8400.html', $hintHtml);
    echo "HTML: {$outDir}/konten-hint-8400.html (Konto {$public['account_number']})\n";
} else {
    fwrite(STDERR, "Warnung: Kein Demo-Konto für Hinweise gefunden.\n");
    copy($outDir . '/konten-search.html', $outDir . '/konten-hint-8400.html');
}

$ledgerYears = LedgerRepository::availableYears();
$ledgerPeriod = AccountingPeriodFilter::fromRequest(['year' => (int) date('Y')]);
$ledgerYear = $ledgerPeriod->year;
$ledgerOverview = LedgerRepository::accountOverview($ledgerYear, [
    'search' => '',
    'show_empty' => false,
    'date_from' => $ledgerPeriod->dateFrom,
    'date_to' => $ledgerPeriod->dateTo,
]);

exportHtml(
    $baseUrl,
    $outDir . '/kontenuebersicht.html',
    $layout,
    'modules/buchhaltung-kontenuebersicht',
    'Kontenübersicht',
    'buchhaltung-kontenuebersicht',
    [
        'ledgerYear' => $ledgerYear,
        'ledgerYears' => $ledgerYears,
        'ledgerSearch' => '',
        'ledgerShowEmpty' => false,
        'ledgerOverview' => $ledgerOverview,
        'ledgerAccount' => '',
        'ledgerYearStatus' => FiscalYearService::status($ledgerYear),
        'ledgerPeriod' => $ledgerPeriod,
    ]
);

file_put_contents($outDir . '/konten-export.meta.json', json_encode([
    'base_url' => $baseUrl,
    'skr_type' => $skrType,
    'chart_accounts' => $chartAccountCount,
    'hint_account' => $hintAccount['account_number'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Konten-Export fertig.\n";
