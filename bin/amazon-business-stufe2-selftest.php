<?php
declare(strict_types=1);

/**
 * Statischer Guard für Amazon Business Stufe-2 Rest (A1–A5a):
 * Migration, Service, POST-Rechte-Gate, UI-Button, ASIN-/Amazon-Hinweise.
 *
 * Usage: php bin/amazon-business-stufe2-selftest.php
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}

function assertAb(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

$migration = DG_ROOT . '/database/migrations/142_purchase_list_amazon.sql';
assertAb(is_readable($migration), 'Migration 142_purchase_list_amazon.sql vorhanden');
$migBody = (string) file_get_contents($migration);
assertAb(str_contains($migBody, 'external_order_id'), 'Migration enthält external_order_id');
assertAb(str_contains($migBody, 'external_order_channel'), 'Migration enthält external_order_channel');

$service = DG_ROOT . '/src/Inventory/AmazonBusinessOrderService.php';
assertAb(is_readable($service), 'AmazonBusinessOrderService vorhanden');
$svcBody = (string) file_get_contents($service);
assertAb(str_contains($svcBody, 'function orderPurchaseListItem'), 'orderPurchaseListItem vorhanden');
assertAb(str_contains($svcBody, 'function canOrderItem'), 'canOrderItem vorhanden');
assertAb(str_contains($svcBody, 'asinFromPreferredSource'), 'asinFromPreferredSource vorhanden');

$autoload = (string) file_get_contents(DG_ROOT . '/src/autoload.php');
assertAb(str_contains($autoload, 'AmazonBusinessOrderService.php'), 'Autoload registriert AmazonBusinessOrderService');

$pls = (string) file_get_contents(DG_ROOT . '/src/Inventory/PurchaseListService.php');
assertAb(str_contains($pls, 'function markAmazonOrdered'), 'PurchaseListService::markAmazonOrdered vorhanden');

$repo = (string) file_get_contents(DG_ROOT . '/src/Inventory/PurchaseListRepository.php');
assertAb(str_contains($repo, 'CHANNEL_AMAZON_BUSINESS'), 'PurchaseListRepository CHANNEL_AMAZON_BUSINESS');
assertAb(str_contains($repo, 'external_order_id'), 'Repository kennt external_order_id');

$index = (string) file_get_contents(DG_ROOT . '/index.php');
assertAb(str_contains($index, "isset(\$_POST['purchase_list_amazon_order'])"), 'POST purchase_list_amazon_order in index.php');
assertAb(
    (bool) preg_match(
        '/DepartmentAccess::userCanManageArticleCatalog\(\$user\)[\s\S]{0,800}?purchase_list_amazon_order/',
        $index
    ),
    'Amazon-POST im gleichen Gate wie Purchase-List (userCanManageArticleCatalog)'
);
assertAb(
    (bool) preg_match(
        '/purchase_list_ordered[\s\S]{0,400}?purchase_list_amazon_order/',
        $index
    ),
    'Amazon-POST neben purchase_list_ordered im POST-Block'
);
assertAb(str_contains($index, 'AmazonBusinessOrderService::orderPurchaseListItem'), 'POST ruft Order-Service auf');

$view = (string) file_get_contents(DG_ROOT . '/views/settings/tab-calendar-articles.php');
assertAb(str_contains($view, 'Bei Amazon Business bestellen'), 'Einkaufsliste-Button-Text vorhanden');
assertAb(str_contains($view, 'AmazonBusinessOrderService::canOrderItem'), 'Button an canOrderItem gekoppelt');
assertAb(str_contains($view, 'external_order_id'), 'Nachbestellt zeigt external_order_id');
assertAb(str_contains($view, 'Bei Amazon Business die ASIN'), 'ASIN-Hinweis im Quellen-Leadtext');

$js = (string) file_get_contents(DG_ROOT . '/assets/js/settings-calendar-articles.js');
assertAb(str_contains($js, 'Bei Amazon Business: ASIN hier eintragen'), 'JS-Feldhinweis ASIN');
assertAb(str_contains($js, 'wireAmazonSupplierHint'), 'JS Amazon-Lieferant-Erkennung');
assertAb(str_contains($js, 'Amazon erkannt'), 'JS Soft-Hinweis bei Amazon-Lieferant');

echo "Amazon-Business-Stufe-2 Selftest bestanden.\n";
