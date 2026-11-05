<?php
declare(strict_types=1);

/**
 * V0 Vertragsvorlagen: Katalog-Render ↔ Clauses-API; optional DB-Seed.
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

function assertV0(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

$fixture = [
    'employer_name' => 'Muster GmbH',
    'employer_address' => "Musterweg 1\n12345 Musterstadt",
    'employer_email' => 'hr@example.test',
    'employer_phone' => '0123',
    'employee_name' => 'Erika Mustermann',
    'employee_address' => "Beispielstr. 2\n12345 Musterstadt",
    'employee_birth_date' => '01.01.1990',
    'legal_guardian_name' => '',
    'legal_guardian_address' => '',
    'contract_start' => '01.11.2026',
    'contract_end' => '',
    'probation_period' => '3 Monate',
    'job_type' => 'Bürokauffrau',
    'employment_relationship' => 'Vollzeit',
    'work_location' => 'Musterstadt',
    'employer_site' => 'Zentrale',
    'working_hours' => '40 Stunden / Woche',
    'daily_work_minutes' => '480',
    'salary' => '3.000 EUR brutto / Monat',
    'vacation_days' => '30 Tage',
    'training_video_url' => 'https://example.test/video',
    'contract_type' => EmploymentContractContext::TYPE_FULLTIME,
    'contract_type_label' => 'Vollzeit',
    'min_wage' => '13,90 EUR/St.',
    'geringfuegig_month' => '603 EUR/Monat',
    'rates_from' => '01.01.2026',
    'today' => '05.11.2026',
];

$opts = [
    'contract_type' => EmploymentContractContext::TYPE_FULLTIME,
    'is_minor' => false,
    'annex_training' => true,
    'annex_pension' => false,
    'annex_ifsg' => false,
    'annex_video' => true,
];

$code = ContractTemplateCatalog::codeForContractType(EmploymentContractContext::TYPE_FULLTIME);
$catalogBlocks = ContractTemplateCatalog::blocksForCode($code);
assertV0($catalogBlocks !== [], 'Katalog Vollzeit hat Bausteine');
assertV0(count(ContractTemplateCatalog::templates()) === 3, '3 Vorlagen-Codes');

$fromCatalog = ContractTemplateRenderer::render($catalogBlocks, $fixture, $opts);
$fromClauses = EmploymentContractClauses::bodyHtml($fixture, $opts);
assertV0($fromCatalog === $fromClauses, 'Clauses-API = Katalog-Renderer (Fallback)');

assertV0(str_contains($fromClauses, 'Erika Mustermann'), 'Name im HTML');
assertV0(str_contains($fromClauses, '§ 1 Beginn'), '§1 vorhanden');
assertV0(str_contains($fromClauses, 'Kurzschulung'), 'Anlage Schulung');
assertV0(str_contains($fromClauses, 'https://example.test/video'), 'Video-Link in Anlage');
assertV0(!str_contains($fromClauses, '{{'), 'keine offenen Platzhalter');

$miniOpts = $opts;
$miniOpts['contract_type'] = EmploymentContractContext::TYPE_MINIJOB;
$miniOpts['annex_pension'] = true;
$miniFixture = $fixture;
$miniFixture['contract_type'] = EmploymentContractContext::TYPE_MINIJOB;
$miniFixture['contract_type_label'] = 'Minijob (geringfügig)';
$miniCode = ContractTemplateCatalog::codeForContractType(EmploymentContractContext::TYPE_MINIJOB);
$miniHtml = ContractTemplateRenderer::render(
    ContractTemplateCatalog::blocksForCode($miniCode),
    $miniFixture,
    $miniOpts
);
assertV0(str_contains($miniHtml, 'geringfügige Beschäftigung'), 'Minijob-SGB-Klausel');
assertV0(str_contains($miniHtml, 'Rentenversicherungsbefreiung'), 'Minijob-Anlage RV');

assertV0(ContractTemplateRepository::bumpVersion('1.0') === '1.1', 'bumpVersion 1.0→1.1');
assertV0(ContractTemplateRepository::bumpVersion('2.9') === '2.10', 'bumpVersion 2.9→2.10');
assertV0(ContractTemplateRepository::bumpVersion('3') === '4.0', 'bumpVersion 3→4.0');
assertV0(ContractTemplateRepository::bumpVersion('x') === '1.1', 'bumpVersion Fallback');

if (ContractTemplateRepository::tableReady()) {
    $seed = ContractTemplateRepository::ensureSeeded();
    assertV0(($seed['created'] + $seed['skipped']) === 3, 'Seed deckt 3 Codes ab');
    $pub = ContractTemplateRepository::publishedWithBlocks($code);
    assertV0($pub !== null, 'Published Fulltime aus DB');
    $fromDb = ContractTemplateRenderer::render($pub['blocks'], $fixture, $opts);
    assertV0($fromDb === $fromCatalog, 'DB-Seed-Render = Katalog (Abbruch-Tor V0)');
} else {
    echo "SKIP: DB/Tabellen nicht bereit — nur Katalog-Tests.\n";
}

echo "V0/V2 Selftest bestanden.\n";
exit(0);
