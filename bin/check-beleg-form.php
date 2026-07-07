<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $form = VoucherRepository::emptyForm();
    $voucherId = null;
    $formError = null;
    $canEdit = true;
    $chartOfAccountsConfig = ChartOfAccountsSettings::forForm();
    $flash = null;
    ob_start();
    include DG_ROOT . '/views/modules/buchhaltung-beleg-form.php';
    $html = ob_get_clean();
    echo 'OK len=' . strlen($html) . "\n";
} catch (Throwable $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    echo 'ERROR: ' . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
}
