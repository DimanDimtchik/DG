<?php
declare(strict_types=1);

/**
 * PA5c: XRechnung Reverse Charge (§13b) — Kategorie AE + BT-120/BT-121.
 */
require dirname(__DIR__) . '/bootstrap.php';

function assertXr(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

$base = [
    'id' => 0,
    'document_kind' => VoucherDocumentKind::INVOICE,
    'voucher_type' => 'income',
    'is_draft' => 0,
    'invoice_number' => 'TEST-13B-1',
    'voucher_date' => '2026-11-08',
    'payment_due_date' => '2026-11-22',
    'gross_amount' => 1000,
    'net_amount' => 1000,
    'tax_amount' => 0,
    'tax_rate' => 19,
    'contact_id' => 0,
    'supplier_name' => 'Testkunde GmbH',
];

$rc = $base;
$rc['reverse_charge_type'] = VoucherReverseCharge::TYPE_CONSTRUCTION;
$xmlRc = VoucherEInvoiceXmlService::renderXml($rc);

assertXr(str_contains($xmlRc, '<cbc:ID>AE</cbc:ID>'), 'Reverse Charge → TaxCategory AE');
assertXr(str_contains($xmlRc, '<cbc:TaxExemptionReasonCode>VATEX-EU-AE</cbc:TaxExemptionReasonCode>'), 'BT-121 VATEX-EU-AE');
assertXr(str_contains($xmlRc, 'Steuerschuldnerschaft des Leistungsempfängers'), 'BT-120 Text vorhanden');
assertXr(str_contains($xmlRc, '<cbc:Percent>0.00</cbc:Percent>'), 'AE-Satz 0 %');
assertXr(
    preg_match('/<cac:TaxTotal>.*?<cbc:TaxAmount[^>]*>0\.00<\/cbc:TaxAmount>/s', $xmlRc) === 1,
    'TaxTotal Steuerbetrag 0'
);
assertXr(!preg_match('/<cac:TaxCategory>.*?<cbc:ID>S<\/cbc:ID>/s', $xmlRc), 'kein Standard-S bei Reverse Charge');
assertXr(str_contains($xmlRc, 'ClassifiedTaxCategory><cbc:ID>AE</cbc:ID>'), 'Position Kategorie AE');

$normal = $base;
$normal['reverse_charge_type'] = '';
$normal['tax_amount'] = 190;
$normal['net_amount'] = 1000;
$normal['gross_amount'] = 1190;
$xmlNormal = VoucherEInvoiceXmlService::renderXml($normal);
assertXr(str_contains($xmlNormal, '<cbc:ID>S</cbc:ID>'), 'Normalrechnung → Kategorie S');
assertXr(!str_contains($xmlNormal, 'VATEX-EU-AE'), 'Normalrechnung ohne VATEX-EU-AE');

echo "Fertig.\n";
