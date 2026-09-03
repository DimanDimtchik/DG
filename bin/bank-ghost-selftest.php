<?php
declare(strict_types=1);

/**
 * Selbsttest für Geisterumsatz-Erkennung (Fingerabdruck + Klassifikation).
 */
require dirname(__DIR__) . '/bootstrap.php';

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$tx1 = [
    'transaction_date' => '2026-01-15',
    'amount' => 119.00,
    'counterparty_iban' => 'DE89370400440532013000',
    'reference_text' => 'RE-2026-0042 Muster GmbH',
    'end_to_end_id' => '',
];
$tx2 = $tx1;
$tx3 = $tx1;
$tx3['reference_text'] = 'Andere Referenz';

$fp1 = BankTransactionRepository::fingerprintFor($tx1);
$fp2 = BankTransactionRepository::fingerprintFor($tx2);
$fp3 = BankTransactionRepository::fingerprintFor($tx3);

assertTrue($fp1 === $fp2, 'Gleiche Umsätze erzeugen gleichen Fingerabdruck');
assertTrue($fp1 !== $fp3, 'Unterschiedliche Referenz erzeugt anderen Fingerabdruck');
assertTrue(strlen($fp1) === 64, 'Fingerabdruck ist SHA-256 (64 Zeichen)');

$e2eTx = $tx1;
$e2eTx['end_to_end_id'] = 'E2E-UNIQUE-12345';
$e2eTx2 = $e2eTx;
$e2eTx2['amount'] = 999.99;
$fpE2e1 = BankTransactionRepository::fingerprintFor($e2eTx);
$fpE2e2 = BankTransactionRepository::fingerprintFor($e2eTx2);
assertTrue($fpE2e1 === $fpE2e2, 'End-to-End-ID dominiert Fingerabdruck (Betrag ignoriert)');

$meaningless = $tx1;
$meaningless['end_to_end_id'] = 'NOTPROVIDED';
$fpMeaningless = BankTransactionRepository::fingerprintFor($meaningless);
assertTrue($fpMeaningless === $fp1, 'NOTPROVIDED wird ignoriert, Fallback auf Betrag/Datum/IBAN/Text');

if (Database::isConfigured()) {
    echo "\n== Datenbank-Test ==\n";
    MigrationRunner::runPending();
    $backfilled = BankTransactionRepository::backfillFingerprints();
    echo "Fingerabdrücke nachgezogen: {$backfilled}\n";

    $classified = BankGhostDetectionService::classifyOpenTransactions();
    echo sprintf(
        "Offene Umsätze: %d · Geisterumsätze: %d\n",
        count($classified['open']),
        count($classified['ghosts'])
    );
} else {
    echo "\nHinweis: Keine DB konfiguriert — nur Fingerabdruck-Tests ausgeführt.\n";
}

echo "\nFertig.\n";
