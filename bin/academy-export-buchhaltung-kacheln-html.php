<?php
declare(strict_types=1);

/**
 * Exportiert Kassenbuch, OPOS, GuV, Steuerberater-Export und Statistik für Academy-Screenshots.
 *
 * php bin/academy-export-buchhaltung-kacheln-html.php [--base=https://ganz-soft.de/] [--user-id=9]
 */

if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}

require_once DG_ROOT . '/src/autoload.php';

MigrationRunner::runPending();

$baseUrl = 'https://ganz-soft.de/';
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
    fwrite(STDERR, "Kein Admin-Benutzer gefunden.\n");
    exit(1);
}

$layoutBase = [
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

/**
 * @param array<string, mixed> $data
 */
function academyWriteHtml(string $path, array $data, string $baseUrl): void
{
    ob_start();
    View::render('layout/app', $data);
    $html = (string) ob_get_clean();

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

    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents($path, $html);
    echo "HTML: {$path}\n";
}

$year = (int) date('Y');
$years = LedgerRepository::availableYears();
if ($years === []) {
    $years = [$year];
}

// --- Kassenbuch ---
$cashPeriod = AccountingPeriodFilter::fromRequest(['year' => (string) $year], $year);
$cashEntries = CashJournalRepository::listForPeriod($cashPeriod);
$cashTotals = CashJournalRepository::totalsForPeriod($cashPeriod);
if ($cashEntries === []) {
    $cashEntries = [
        [
            'id' => 9001,
            'entry_date' => sprintf('%d-03-10', $year),
            'side_label' => 'Einzahlung',
            'account_number' => '1000',
            'amount' => 500.00,
            'amount_display' => '500,00 €',
            'description' => 'Demo: Bareinnahme Anzahlung',
            'voucher_id' => 0,
        ],
        [
            'id' => 9002,
            'entry_date' => sprintf('%d-03-12', $year),
            'side_label' => 'Auszahlung',
            'account_number' => '1000',
            'amount' => 45.50,
            'amount_display' => '45,50 €',
            'description' => 'Demo: Büromaterial bar',
            'voucher_id' => 0,
        ],
    ];
    $cashTotals = ['in' => 500.00, 'out' => 45.50, 'balance' => 454.50];
}
academyWriteHtml(
    DG_ROOT . '/storage/media/training/kassenbuch/kassenbuch-page.html',
    array_merge($layoutBase, [
        'contentTemplate' => 'modules/buchhaltung-kassenbuch',
        'title' => 'Kassenbuch',
        'currentPage' => 'buchhaltung-kassenbuch',
        'cashPeriod' => $cashPeriod,
        'cashYear' => $year,
        'cashYears' => $years,
        'cashEntries' => $cashEntries,
        'cashTotals' => $cashTotals,
        'cashClosings' => CashDayCloseService::listClosings($year),
        'cashCloseDate' => date('Y-m-d'),
        'cashDaySummary' => CashDayCloseService::daySummary(date('Y-m-d')),
    ]),
    $baseUrl
);

// --- Offene Posten ---
$oposData = OpenItemsRepository::list(['direction' => '', 'search' => '']);
if (($oposData['items'] ?? []) === []) {
    $oposData = [
        'items' => [
            [
                'voucher_id' => 901,
                'voucher_date' => sprintf('%d-02-01', $year),
                'payment_due_date' => sprintf('%d-02-15', $year),
                'days_overdue' => 0,
                'document_kind_label' => 'Rechnung',
                'direction' => 'receivable',
                'is_advance' => false,
                'invoice_number' => 'RE-DEMO-1',
                'contact_label' => 'Demo Kunde Nord GmbH',
                'person_account' => '10001',
                'paid_amount' => 0.0,
                'open_amount' => 427.60,
                'dunning_level' => 0,
            ],
            [
                'voucher_id' => 902,
                'voucher_date' => sprintf('%d-01-20', $year),
                'payment_due_date' => sprintf('%d-02-05', $year),
                'days_overdue' => 0,
                'document_kind_label' => 'Eingangsrechnung',
                'direction' => 'payable',
                'is_advance' => false,
                'invoice_number' => 'ER-DEMO-3',
                'contact_label' => 'Demo Lieferant West OHG',
                'person_account' => '70001',
                'paid_amount' => 100.0,
                'open_amount' => 89.25,
                'dunning_level' => 0,
            ],
        ],
        'totals' => ['receivable' => 427.60, 'payable' => 89.25],
    ];
} else {
    foreach ($oposData['items'] as $i => $row) {
        if (!is_array($row)) {
            continue;
        }
        $dir = (string) ($row['direction'] ?? '');
        $oposData['items'][$i]['contact_label'] = $dir === 'payable'
            ? 'Demo Lieferant ' . chr(65 + ($i % 5))
            : 'Demo Kunde ' . chr(65 + ($i % 5));
    }
}
academyWriteHtml(
    DG_ROOT . '/storage/media/training/opos/opos-page.html',
    array_merge($layoutBase, [
        'contentTemplate' => 'modules/buchhaltung-opos',
        'title' => 'Offene Posten',
        'currentPage' => 'buchhaltung-opos',
        'oposDirection' => '',
        'oposSearch' => '',
        'oposData' => $oposData,
    ]),
    $baseUrl
);

// --- GuV ---
$reportPeriod = AccountingPeriodFilter::fromRequest(['year' => (string) $year], $year);
$profitLoss = FinancialReportsService::profitLoss($year);
$balanceSheet = FinancialReportsService::balanceSheet($year);
if (($profitLoss['income'] ?? []) === [] && ($profitLoss['expense'] ?? []) === []) {
    $profitLoss = [
        'income' => [
            ['account_number' => '8400', 'name' => 'Erlöse 19 %', 'pl_amount' => 12500.00],
            ['account_number' => '8300', 'name' => 'Erlöse 7 %', 'pl_amount' => 820.00],
        ],
        'expense' => [
            ['account_number' => '3400', 'name' => 'Wareneingang', 'pl_amount' => 4100.00],
            ['account_number' => '4120', 'name' => 'Gehälter', 'pl_amount' => 6200.00],
        ],
        'totals' => ['income' => 13320.00, 'expense' => 10300.00, 'result' => 3020.00],
    ];
}
academyWriteHtml(
    DG_ROOT . '/storage/media/training/guv/guv-page.html',
    array_merge($layoutBase, [
        'contentTemplate' => 'modules/buchhaltung-auswertungen',
        'title' => 'Bilanz & GuV',
        'currentPage' => 'buchhaltung-auswertungen',
        'reportPeriod' => $reportPeriod,
        'reportYear' => $year,
        'reportYears' => $years,
        'reportType' => 'guv',
        'balanceSheet' => $balanceSheet,
        'profitLoss' => $profitLoss,
    ]),
    $baseUrl
);

// --- Steuerberater-Export ---
academyWriteHtml(
    DG_ROOT . '/storage/media/training/steuerberater-export/steuerberater-export-page.html',
    array_merge($layoutBase, [
        'contentTemplate' => 'modules/buchhaltung-steuerberater-export',
        'title' => 'Steuerberater-Export',
        'currentPage' => 'buchhaltung-steuerberater-export',
        'datevExportYear' => $year,
        'datevExportYears' => $years,
        'datevExportSettings' => [
            'consultant_number' => '12345',
            'client_number' => '67890',
        ],
    ]),
    $baseUrl
);

// --- Statistik ---
$websiteStatsDays = 30;
$websiteStatsSummary = ['total' => 0, 'today' => 0, 'days7' => 0, 'days30' => 0];
$websiteStatsByDay = [];
$websiteStatsTopPaths = [];
$websiteStatsTopReferrers = [];
$websiteAnalyticsLinks = [];
if (class_exists('WebsitePageviewRepository')) {
    try {
        WebsitePageviewRepository::ensureTables();
        $websiteStatsSummary = WebsitePageviewRepository::summary();
        $websiteStatsByDay = WebsitePageviewRepository::viewsByDay($websiteStatsDays);
        $websiteStatsTopPaths = WebsitePageviewRepository::topPaths($websiteStatsDays);
        $websiteStatsTopReferrers = WebsitePageviewRepository::topReferrers($websiteStatsDays);
    } catch (Throwable) {
    }
}
if (class_exists('WebsitePageviewTracker')) {
    try {
        $websiteAnalyticsLinks = WebsitePageviewTracker::externalDashboardLinks();
    } catch (Throwable) {
    }
}
if ((int) ($websiteStatsSummary['total'] ?? 0) < 1 && $websiteStatsByDay === []) {
    $websiteStatsSummary = ['total' => 1840, 'today' => 23, 'days7' => 156, 'days30' => 612];
    for ($i = 29; $i >= 0; $i--) {
        $websiteStatsByDay[] = [
            'day' => date('Y-m-d', strtotime('-' . $i . ' days')),
            'views' => 10 + (($i * 3) % 40),
        ];
    }
    $websiteStatsTopPaths = [
        ['path' => '/', 'views' => 420, 'page_id' => null],
        ['path' => '/leistungen', 'views' => 180, 'page_id' => null],
        ['path' => '/kontakt', 'views' => 95, 'page_id' => null],
    ];
    $websiteStatsTopReferrers = [
        ['host' => 'www.google.com', 'views' => 210],
        ['host' => '(direkt)', 'views' => 150],
    ];
}
academyWriteHtml(
    DG_ROOT . '/storage/media/training/statistik/statistik-page.html',
    array_merge($layoutBase, [
        'contentTemplate' => 'modules/website-statistik',
        'title' => 'Statistik',
        'currentPage' => 'website-statistik',
        'websiteStatsDays' => $websiteStatsDays,
        'websiteStatsSummary' => $websiteStatsSummary,
        'websiteStatsByDay' => $websiteStatsByDay,
        'websiteStatsTopPaths' => $websiteStatsTopPaths,
        'websiteStatsTopReferrers' => $websiteStatsTopReferrers,
        'websiteAnalyticsLinks' => $websiteAnalyticsLinks,
    ]),
    $baseUrl
);

file_put_contents(
    DG_ROOT . '/storage/media/training/kassenbuch/buchhaltung-kacheln-export.meta.json',
    json_encode([
        'base' => $baseUrl,
        'year' => $year,
        'exported_at' => date('c'),
        'pages' => ['kassenbuch', 'opos', 'guv', 'steuerberater-export', 'statistik'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
);

echo "Fertig.\n";
