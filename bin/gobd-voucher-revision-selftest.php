<?php
declare(strict_types=1);

/**
 * Kleiner GoBD-Selbsttest ohne Datenbank: prueft Sperrstatus und erlaubte Statusaktionen.
 */
require dirname(__DIR__) . '/bootstrap.php';

function ok(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$base = [
    'voucher_type' => 'income',
    'document_kind' => VoucherDocumentKind::INVOICE,
    'document_status' => VoucherDocumentStatus::DRAFT,
    'gross_amount' => '100,00',
    'voucher_date' => '2026-10-27',
    'contact_id' => 1,
];

foreach ([
    VoucherDocumentStatus::SENT,
    VoucherDocumentStatus::ACCEPTED,
    VoucherDocumentStatus::BILLED,
    VoucherDocumentStatus::CANCELLED,
    VoucherDocumentStatus::EXPIRED,
] as $status) {
    $voucher = $base;
    $voucher['document_status'] = $status;
    ok(VoucherDocumentRevisionService::isImmutable($voucher), "Status {$status} sperrt Ausgangsbeleg");
}

$draft = $base;
ok(!VoucherDocumentRevisionService::isImmutable($draft), 'Entwurf bleibt normal bearbeitbar');

$expense = $base;
$expense['voucher_type'] = 'expense';
$expense['document_status'] = VoucherDocumentStatus::SENT;
ok(!VoucherDocumentRevisionService::isImmutable($expense), 'Eingangsbeleg wird nicht durch Ausgangsbeleg-Sperre blockiert');

$existing = $base;
$existing['document_status'] = VoucherDocumentStatus::SENT;
ok(
    VoucherDocumentRevisionService::isStatusOnlyChange($existing, [
        'document_status' => VoucherDocumentStatus::BILLED,
        'gross_amount' => '100,00',
        'voucher_date' => '2026-10-27',
        'contact_id' => 1,
    ]),
    'Reiner Workflow-Statuswechsel bleibt erlaubt'
);

ok(
    !VoucherDocumentRevisionService::isStatusOnlyChange($existing, [
        'document_status' => VoucherDocumentStatus::BILLED,
        'gross_amount' => '100,00',
        'voucher_date' => '2026-10-27',
        'contact_id' => 1,
        'items' => [['title' => 'Geaendert']],
    ]),
    'Statuswechsel mit Positionen gilt als inhaltliche Aenderung'
);

ok(
    !VoucherDocumentRevisionService::isStatusOnlyChange($existing, [
        'document_status' => VoucherDocumentStatus::BILLED,
        'gross_amount' => '101,00',
        'voucher_date' => '2026-10-27',
        'contact_id' => 1,
    ]),
    'Statuswechsel mit anderem Betrag wird nicht durchgelassen'
);

echo "Fertig.\n";
