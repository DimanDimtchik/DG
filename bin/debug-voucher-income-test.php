<?php
declare(strict_types=1);

/**
 * CLI: Prüft Einnahmen-Formular und Artikelsuche (ohne Browser-Login).
 */
require dirname(__DIR__) . '/bootstrap.php';

MigrationRunner::runPending();

$errors = [];

$js = (string) file_get_contents(DG_ROOT . '/assets/js/buchhaltung-belege.js');
if (str_contains($js, 'currentVoucherType')) {
    $errors[] = 'JS enthält noch currentVoucherType (defekt).';
}
if (!str_contains($js, 'getVoucherType()') || !str_contains($js, 'invoice-items-section')) {
    $errors[] = 'JS fehlt getVoucherType oder invoice-items-section.';
}

$formPhp = (string) file_get_contents(DG_ROOT . '/views/modules/buchhaltung-beleg-form.php');
if (!str_contains($formPhp, 'dg-voucher-invoice-items-section')) {
    $errors[] = 'Formular-Template ohne Rechnungspositionen-Sektion.';
}

$articles = VoucherIncomePositions::searchArticles('', 3);
if ($articles === []) {
    $errors[] = 'Artikelsuche liefert keine Katalog-Einträge.';
}

echo '=== Voucher income self-test ===' . PHP_EOL;
echo 'articles_empty_search=' . count($articles) . PHP_EOL;
foreach ($articles as $a) {
    echo '  - ' . ($a['article_number'] ?? '') . ' ' . ($a['title'] ?? '') . PHP_EOL;
}

if ($errors === []) {
    echo 'RESULT: OK' . PHP_EOL;
    exit(0);
}

echo 'RESULT: FAIL' . PHP_EOL;
foreach ($errors as $e) {
    echo '  ! ' . $e . PHP_EOL;
}
exit(1);
