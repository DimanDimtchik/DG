<?php
declare(strict_types=1);

/**
 * Smoke-Assertions für AcquisitionCompareService (ohne DB-Pflicht für Kernrechnung).
 */
require dirname(__DIR__) . '/bootstrap.php';

function line(string $s = ''): void
{
    echo $s . "\n";
}

function assertTrue(bool $ok, string $msg): void
{
    if (!$ok) {
        line('FAIL: ' . $msg);
        exit(1);
    }
    line('OK: ' . $msg);
}

$baseCompany = [
    'company_type' => 'gmbh',
    'tax_rates' => [
        'gewst_hebesatz' => 400.0,
        'kst_satz' => 15.0,
        'solz_satz' => 5.5,
        'est_marginal' => 42.0,
    ],
    '_vat_deductible' => true,
];

// 1) Barkauf PC 1500 € / 3 J. AfA — Aufwand Jahr 1 = 500
$r1 = AcquisitionCompareService::compare([
    'net' => 1500,
    'vat_rate' => 19,
    'useful_life_years' => 3,
    'term_months' => 36,
], $baseCompany);
$barkauf = $r1['models']['barkauf'];
assertTrue(!$r1['meta']['is_gwg'], 'PC 1500 € ist kein GWG');
assertTrue(abs((float) $barkauf['years'][0]['expense'] - 500.0) < 0.02, 'Barkauf AfA Jahr 1 = 500');
assertTrue(abs((float) $barkauf['years'][0]['cash'] + 1785.0) < 0.02, 'Barkauf Cash Jahr 1 = −1785 Brutto');
assertTrue((float) $barkauf['years'][0]['vat_cash'] > 0, 'Vorsteuer Jahr 1 > 0');

// 2) GWG 500 € Sofortabschreibung
$r2 = AcquisitionCompareService::compare([
    'net' => 500,
    'vat_rate' => 19,
    'useful_life_years' => 3,
    'term_months' => 12,
], $baseCompany);
assertTrue($r2['meta']['is_gwg'] === true, '500 € Netto ist GWG');
assertTrue(abs((float) $r2['models']['barkauf']['years'][0]['expense'] - 500.0) < 0.02, 'GWG Sofortaufwand 500');
$y2exp = (float) ($r2['models']['barkauf']['years'][1]['expense'] ?? 0);
assertTrue($y2exp < 0.01, 'GWG kein Aufwand in Jahr 2');

// 3) KapG vs. Einzelunternehmen — unterschiedliche income_tax bei gleichem Aufwand
$euCompany = $baseCompany;
$euCompany['company_type'] = 'einzelunternehmen';
$rKap = AcquisitionCompareService::compare(['net' => 1500, 'vat_rate' => 19, 'useful_life_years' => 3], $baseCompany);
$rEu = AcquisitionCompareService::compare(['net' => 1500, 'vat_rate' => 19, 'useful_life_years' => 3], $euCompany);
assertTrue($rKap['meta']['is_kapg'] === true, 'GmbH ist KapG');
assertTrue($rEu['meta']['is_kapg'] === false, 'Einzelunternehmen ist kein KapG');
$taxKap = (float) $rKap['models']['barkauf']['totals']['tax_income'];
$taxEu = (float) $rEu['models']['barkauf']['totals']['tax_income'];
assertTrue(abs($taxKap - $taxEu) > 1.0, 'KSt-Pfad und ESt-Pfad liefern unterschiedliche Ertragsteuer-Entlastung');

// Hebesatz wirkt auf GewSt-Anteil
$highHeb = $baseCompany;
$highHeb['tax_rates']['gewst_hebesatz'] = 500.0;
$rHeb = AcquisitionCompareService::compare(['net' => 1500, 'vat_rate' => 19, 'useful_life_years' => 3], $highHeb);
assertTrue(
    (float) $rHeb['models']['barkauf']['totals']['tax_gewst']
    > (float) $rKap['models']['barkauf']['totals']['tax_gewst'],
    'Höherer Hebesatz → höhere GewSt-Entlastung'
);

line('');
line('DONE — acquisition-compare-selftest OK');
exit(0);
