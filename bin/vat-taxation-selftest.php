<?php
declare(strict_types=1);

/**
 * Selbsttest Ist-/Soll-Versteuerung ohne DB-Schreibzugriff auf Produktivdaten.
 */
require dirname(__DIR__) . '/bootstrap.php';

function vatOk(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

vatOk(LedgerAccounts::istClearingAccount('skr03') === '1390', 'SKR03 Ist-Verrechnungskonto 1390');
vatOk(LedgerAccounts::istClearingAccount('skr04') === '1490', 'SKR04 Ist-Verrechnungskonto 1490');

$sollTax = LedgerAccounts::taxAccount('skr04', 'income', 19);
vatOk($sollTax === '3806', 'Soll-USt-Konto SKR04 19 % = 3806');

$summary = VatTaxationSettings::summaryForPeriod((int) date('Y'), (int) date('n'));
vatOk(isset($summary['method'], $summary['label']), 'UStVA-Regime-Summary liefert method/label');
vatOk(
    in_array($summary['method'], [VatTaxationSettings::METHOD_SOLL, VatTaxationSettings::METHOD_IST], true),
    'Regime-Methode ist soll oder ist'
);

$immutableIncome = [
    'voucher_type' => 'income',
    'document_kind' => VoucherDocumentKind::INVOICE,
    'document_status' => VoucherDocumentStatus::SENT,
    'is_draft' => false,
];
vatOk(VoucherDocumentRevisionService::isImmutable($immutableIncome), 'Versendete Rechnung gesperrt');

echo "Fertig.\n";
