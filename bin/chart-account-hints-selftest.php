<?php
declare(strict_types=1);

/**
 * Selbsttest fuer fachliche SKR-Kontohinweise.
 */
require dirname(__DIR__) . '/bootstrap.php';

function assertHint(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

function containsNeedle(array $values, string $needle): bool
{
    foreach ($values as $value) {
        if (str_contains((string) $value, $needle)) {
            return true;
        }
    }

    return false;
}

$generated = ChartAccountHintBuilder::build('skr03', '4800', 'Bewirtungskosten', 'aufwand');
$generatedEdges = is_array($generated['edge_cases'] ?? null) ? $generated['edge_cases'] : [];
$generatedTax = is_array($generated['tax_effects'] ?? null) ? $generated['tax_effects'] : [];

assertHint(!containsNeedle($generatedEdges, '70 % Vorsteuerabzug'), 'generierte Hinweise enthalten keinen 70-%-Vorsteuerabzug');
assertHint(containsNeedle($generatedEdges, '70 % als Betriebsausgabe'), 'generierte Hinweise nennen 70-%-Betriebsausgabenabzug');
assertHint(($generatedTax['ust'] ?? '') === 'vorsteuer', 'generierte Bewirtung weist USt als Vorsteuer-Thema aus');

$seedAccount = null;
foreach (ChartAccountSeedData::accountsForSkr('skr03') as $account) {
    if (($account['account_number'] ?? '') === '4800') {
        $seedAccount = $account;
        break;
    }
}
assertHint(is_array($seedAccount), 'manuelles SKR03-Bewirtungskonto 4800 vorhanden');
$seedHints = is_array($seedAccount['hints'] ?? null) ? $seedAccount['hints'] : [];
$seedEdges = is_array($seedHints['edge_cases'] ?? null) ? $seedHints['edge_cases'] : [];
$seedTax = is_array($seedHints['tax_effects'] ?? null) ? $seedHints['tax_effects'] : [];

assertHint(!containsNeedle($seedEdges, '70 % Vorsteuerabzug'), 'manuelle Hinweise enthalten keinen 70-%-Vorsteuerabzug');
assertHint(containsNeedle($seedEdges, '70 % als Betriebsausgabe'), 'manuelle Hinweise nennen 70-%-Betriebsausgabenabzug');
assertHint(($seedTax['ust'] ?? '') === 'vorsteuer', 'manuelle Bewirtung weist USt als Vorsteuer-Thema aus');

echo "Fertig.\n";
