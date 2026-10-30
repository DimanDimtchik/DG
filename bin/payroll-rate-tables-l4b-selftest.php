<?php
declare(strict_types=1);

/**
 * Lohn L4b Selftest — Parse/Allowlist/Fixture ohne DB.
 */
require_once dirname(__DIR__) . '/src/autoload.php';

function assertL4b(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

$keys = PayrollRateTableRepository::allowedKeys();
assertL4b(in_array('lst_fixture_factor', $keys, true), 'Allowlist enthält lst_fixture_factor');
assertL4b(in_array('midi_an_factor', $keys, true), 'Allowlist enthält midi_an_factor');
assertL4b(!in_array('secret_elster_key', $keys, true), 'Keine Geheim-Keys');

$fix = PayrollRateTableRepository::fixtureParams();
assertL4b(isset($fix['minijob_month_limit_eur']) && (float) $fix['minijob_month_limit_eur'] === 560.0, 'Fixture Mini-Grenze');
assertL4b((float) $fix['lst_fixture_factor'] === 0.14, 'Fixture LSt-Faktor');
assertL4b((float) $fix['midi_an_factor'] === 0.7, 'Fixture midi_an_factor');

assertL4b(PayrollRateTableRepository::normalizeParamValue('1,5') === '1.50000000', 'Komma-Dezimal');
assertL4b(PayrollRateTableRepository::normalizeParamValue('abc') === null, 'Ungültiger Wert');

$csv = PayrollRateTableRepository::exampleImportCsv();
$parsed = PayrollRateTableRepository::parseImportText($csv);
assertL4b($parsed['meta']['label'] === 'EXAMPLE-IMPORT-2026', 'Beispiel-Label');
assertL4b($parsed['meta']['valid_from'] === '2026-01-01', 'Beispiel valid_from');
assertL4b(count($parsed['params']) === count($fix), 'Alle Fixture-Params im Beispiel');

$threw = false;
try {
    PayrollRateTableRepository::parseImportText("valid_from,2026-01-01\nlabel,X\nparam,not_allowed,1\n");
} catch (InvalidArgumentException) {
    $threw = true;
}
assertL4b($threw, 'Unbekannter Key abgelehnt');

$threw2 = false;
try {
    PayrollRateTableRepository::parseImportText("valid_from,2026-01-01\nlabel,X\n");
} catch (InvalidArgumentException) {
    $threw2 = true;
}
assertL4b($threw2, 'Import ohne Params abgelehnt');

echo "L4b Selftest bestanden.\n";
exit(0);
