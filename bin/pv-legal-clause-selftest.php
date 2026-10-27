<?php
declare(strict_types=1);

/**
 * Selbsttest fuer PV-0-%-Klausel-Trigger im Belegdruck.
 */
require dirname(__DIR__) . '/bootstrap.php';

function assertCase(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$pvItem = [[
    'title' => 'PV-Anlage',
    'gross_amount' => '1.000,00',
    'tax_rate' => '0',
]];
$normalItem = [[
    'title' => 'Service',
    'gross_amount' => '119,00',
    'tax_rate' => '19',
]];
$zeroAmountItem = [[
    'title' => 'PV-Anlage',
    'gross_amount' => '0,00',
    'tax_rate' => '0',
]];

assertCase(
    VoucherDocumentPrintService::hasPhotovoltaikZeroTaxLine($pvItem, ['reverse_charge_type' => '']) === true,
    'positive 0-%-Position triggert PV-Hinweis'
);
assertCase(
    VoucherDocumentPrintService::hasPhotovoltaikZeroTaxLine($normalItem, ['reverse_charge_type' => '']) === false,
    '19-%-Position triggert keinen PV-Hinweis'
);
assertCase(
    VoucherDocumentPrintService::hasPhotovoltaikZeroTaxLine($zeroAmountItem, ['reverse_charge_type' => '']) === false,
    '0-%-Position ohne Betrag triggert keinen PV-Hinweis'
);
assertCase(
    VoucherDocumentPrintService::hasPhotovoltaikZeroTaxLine($pvItem, ['reverse_charge_type' => VoucherReverseCharge::TYPE_CONSTRUCTION]) === false,
    'Reverse-Charge-Beleg triggert keinen PV-Hinweis'
);
assertCase(
    VoucherDocumentPrintService::hasPhotovoltaikZeroTaxLine([], [
        'reverse_charge_type' => '',
        'gross_amount' => '500,00',
        'net_amount' => '500,00',
        'tax_amount' => '0,00',
        'tax_rate' => '0',
    ]) === true,
    'Header-Fallback mit 0-%-Steuersatz triggert PV-Hinweis'
);

echo "Fertig.\n";
