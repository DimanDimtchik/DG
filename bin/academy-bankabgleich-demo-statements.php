<?php
declare(strict_types=1);

/**
 * Erzeugt Demo-Kontoauszüge (CAMT.053 + MT940) für Q1/Q2 2026
 * und importiert die CAMT-Dateien in die Instanz-DB (bleiben erhalten).
 *
 * php bin/academy-bankabgleich-demo-statements.php [--write-only] [--import-only]
 */

if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}

require_once DG_ROOT . '/src/autoload.php';

$writeOnly = in_array('--write-only', $argv, true);
$importOnly = in_array('--import-only', $argv, true);

$outDirs = [
    DG_ROOT . '/storage/media/training/bankabgleich/demo',
    DG_ROOT . '/docs/demo/bankabgleich',
];

/** @var list<array{slug: string, title: string, period: string, opening: float, closing: float, entries: list<array<string, mixed>>}> */
$statements = [
    [
        'slug' => 'q1-2026',
        'title' => 'Kontoauszug Q1 2026',
        'period' => '01.01.2026–31.03.2026',
        'stmt_id' => 'DG-DEMO-Q1-2026',
        'opening' => 12500.00,
        'closing' => 13842.55,
        'entries' => [
            ['date' => '2026-01-08', 'cdt' => true, 'amount' => 1190.00, 'name' => 'Demo Kunde Nord GmbH', 'iban' => 'DE02120300000000202051', 'eref' => 'DG-E2E-Q1-001', 'ref' => 'RE-2026-0001 Zahlung Januar'],
            ['date' => '2026-01-15', 'cdt' => false, 'amount' => 450.00, 'name' => 'Demo Büromiete AG', 'iban' => 'DE89370400440532013000', 'eref' => 'DG-E2E-Q1-002', 'ref' => 'Miete Januar 2026'],
            ['date' => '2026-02-03', 'cdt' => true, 'amount' => 714.00, 'name' => 'Demo Handel West OHG', 'iban' => 'DE12500105170648489890', 'eref' => 'DG-E2E-Q1-003', 'ref' => 'RE-2026-0012 Skonto'],
            ['date' => '2026-02-20', 'cdt' => false, 'amount' => 89.90, 'name' => 'Demo Software SaaS', 'iban' => 'DE44500105175407324931', 'eref' => 'DG-E2E-Q1-004', 'ref' => 'Abo CRM Hosting Feb'],
            ['date' => '2026-03-10', 'cdt' => true, 'amount' => 2380.00, 'name' => 'Demo Kunde Süd AG', 'iban' => 'DE02120300000000202051', 'eref' => 'DG-E2E-Q1-005', 'ref' => 'RE-2026-0028 Zahlung März'],
            ['date' => '2026-03-28', 'cdt' => false, 'amount' => 401.55, 'name' => 'Demo Versicherungen', 'iban' => 'DE89370400440532013000', 'eref' => 'DG-E2E-Q1-006', 'ref' => 'Haftpflicht Q1 2026'],
        ],
    ],
    [
        'slug' => 'q2a-2026',
        'title' => 'Kontoauszug Q2/1 2026 (Apr–Mai)',
        'period' => '01.04.2026–31.05.2026',
        'stmt_id' => 'DG-DEMO-Q2A-2026',
        'opening' => 13842.55,
        'closing' => 15210.05,
        'entries' => [
            ['date' => '2026-04-05', 'cdt' => true, 'amount' => 952.00, 'name' => 'Demo Kunde Nord GmbH', 'iban' => 'DE02120300000000202051', 'eref' => 'DG-E2E-Q2A-001', 'ref' => 'RE-2026-0040 Zahlung April'],
            ['date' => '2026-04-12', 'cdt' => false, 'amount' => 450.00, 'name' => 'Demo Büromiete AG', 'iban' => 'DE89370400440532013000', 'eref' => 'DG-E2E-Q2A-002', 'ref' => 'Miete April 2026'],
            ['date' => '2026-04-22', 'cdt' => true, 'amount' => 1785.00, 'name' => 'Demo Klinik Partner', 'iban' => 'DE12500105170648489890', 'eref' => 'DG-E2E-Q2A-003', 'ref' => 'RE-2026-0045 Lieferung'],
            ['date' => '2026-05-07', 'cdt' => false, 'amount' => 129.50, 'name' => 'Demo Bürobedarf', 'iban' => 'DE44500105175407324931', 'eref' => 'DG-E2E-Q2A-004', 'ref' => 'Rechnung Material Mai'],
            ['date' => '2026-05-18', 'cdt' => true, 'amount' => 595.00, 'name' => 'Demo Handel West OHG', 'iban' => 'DE12500105170648489890', 'eref' => 'DG-E2E-Q2A-005', 'ref' => 'RE-2026-0051 Restzahlung'],
            ['date' => '2026-05-29', 'cdt' => false, 'amount' => 385.00, 'name' => 'Demo Steuerberatung', 'iban' => 'DE89370400440532013000', 'eref' => 'DG-E2E-Q2A-006', 'ref' => 'Honorar Mai 2026'],
        ],
    ],
    [
        'slug' => 'q2b-2026',
        'title' => 'Kontoauszug Q2/2 2026 (Juni)',
        'period' => '01.06.2026–30.06.2026',
        'stmt_id' => 'DG-DEMO-Q2B-2026',
        'opening' => 15210.05,
        'closing' => 16103.55,
        'entries' => [
            ['date' => '2026-06-04', 'cdt' => true, 'amount' => 1428.00, 'name' => 'Demo Kunde Süd AG', 'iban' => 'DE02120300000000202051', 'eref' => 'DG-E2E-Q2B-001', 'ref' => 'RE-2026-0060 Zahlung Juni'],
            ['date' => '2026-06-11', 'cdt' => false, 'amount' => 450.00, 'name' => 'Demo Büromiete AG', 'iban' => 'DE89370400440532013000', 'eref' => 'DG-E2E-Q2B-002', 'ref' => 'Miete Juni 2026'],
            ['date' => '2026-06-18', 'cdt' => true, 'amount' => 833.00, 'name' => 'Demo Praxis Mitte', 'iban' => 'DE44500105175407324931', 'eref' => 'DG-E2E-Q2B-003', 'ref' => 'RE-2026-0066 Teilzahlung'],
            ['date' => '2026-06-25', 'cdt' => false, 'amount' => 89.90, 'name' => 'Demo Software SaaS', 'iban' => 'DE44500105175407324931', 'eref' => 'DG-E2E-Q2B-004', 'ref' => 'Abo CRM Hosting Jun'],
            ['date' => '2026-06-28', 'cdt' => true, 'amount' => 172.40, 'name' => 'Demo Kunde Nord GmbH', 'iban' => 'DE02120300000000202051', 'eref' => 'DG-E2E-Q2B-005', 'ref' => 'RE-2026-0070 Nachzahlung'],
        ],
    ],
];

function fmtMoney(float $n): string
{
    return number_format($n, 2, ',', '');
}

function buildCamt(array $stmt): string
{
    $lines = [];
    $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
    $lines[] = '<Document>';
    $lines[] = '  <BkToCstmrStmt>';
    $lines[] = '    <GrpHdr><MsgId>' . htmlspecialchars((string) $stmt['stmt_id'], ENT_XML1) . '</MsgId><CreDtTm>2026-07-01T10:00:00</CreDtTm></GrpHdr>';
    $lines[] = '    <Stmt>';
    $lines[] = '      <Id>' . htmlspecialchars((string) $stmt['stmt_id'], ENT_XML1) . '</Id>';
    $lines[] = '      <ElctrncSeqNb>1</ElctrncSeqNb>';
    $lines[] = '      <LglSeqNb>1</LglSeqNb>';
    $lines[] = '      <Acct><Id><IBAN>DE12500105170648489890</IBAN></Id><Ccy>EUR</Ccy><Ownr><Nm>Demo Firma CRM Schulung</Nm></Ownr></Acct>';
    $lines[] = '      <Bal><Tp><CdOrPrtry><Cd>OPBD</Cd></CdOrPrtry></Tp><Amt Ccy="EUR">' . number_format((float) $stmt['opening'], 2, '.', '') . '</Amt><CdtDbtInd>CRDT</CdtDbtInd></Bal>';
    $lines[] = '      <Bal><Tp><CdOrPrtry><Cd>CLBD</Cd></CdOrPrtry></Tp><Amt Ccy="EUR">' . number_format((float) $stmt['closing'], 2, '.', '') . '</Amt><CdtDbtInd>CRDT</CdtDbtInd></Bal>';

    foreach ($stmt['entries'] as $e) {
        $ind = !empty($e['cdt']) ? 'CRDT' : 'DBIT';
        $party = !empty($e['cdt']) ? 'Dbtr' : 'Cdtr';
        $acct = !empty($e['cdt']) ? 'DbtrAcct' : 'CdtrAcct';
        $amt = number_format((float) $e['amount'], 2, '.', '');
        $date = (string) $e['date'];
        $lines[] = '      <Ntry>';
        $lines[] = '        <Amt Ccy="EUR">' . $amt . '</Amt>';
        $lines[] = '        <CdtDbtInd>' . $ind . '</CdtDbtInd>';
        $lines[] = '        <Status>BOOK</Status>';
        $lines[] = '        <BookgDt><Dt>' . $date . '</Dt></BookgDt>';
        $lines[] = '        <ValDt><Dt>' . $date . '</Dt></ValDt>';
        $lines[] = '        <AcctSvcrRef>' . htmlspecialchars((string) $e['eref'], ENT_XML1) . '</AcctSvcrRef>';
        $lines[] = '        <NtryDtls><TxDtls>';
        $lines[] = '          <Refs><EndToEndId>' . htmlspecialchars((string) $e['eref'], ENT_XML1) . '</EndToEndId></Refs>';
        $lines[] = '          <RmtInf><Ustrd>' . htmlspecialchars((string) $e['ref'], ENT_XML1) . '</Ustrd></RmtInf>';
        $lines[] = '          <RltdPties>';
        $lines[] = '            <' . $party . '><Nm>' . htmlspecialchars((string) $e['name'], ENT_XML1) . '</Nm></' . $party . '>';
        $lines[] = '            <' . $acct . '><Id><IBAN>' . htmlspecialchars((string) $e['iban'], ENT_XML1) . '</IBAN></Id></' . $acct . '>';
        $lines[] = '          </RltdPties>';
        $lines[] = '        </TxDtls></NtryDtls>';
        $lines[] = '        <AddtlNtryInf>' . htmlspecialchars((string) $e['ref'], ENT_XML1) . '</AddtlNtryInf>';
        $lines[] = '      </Ntry>';
    }

    $lines[] = '    </Stmt>';
    $lines[] = '  </BkToCstmrStmt>';
    $lines[] = '</Document>';

    return implode("\n", $lines) . "\n";
}

function buildMt940(array $stmt): string
{
    $first = $stmt['entries'][0]['date'] ?? '2026-01-01';
    $last = $stmt['entries'][count($stmt['entries']) - 1]['date'] ?? $first;
    $openDate = substr(str_replace('-', '', (string) $first), 2); // YYMMDD
    $closeDate = substr(str_replace('-', '', (string) $last), 2);
    $openSign = ((float) $stmt['opening']) >= 0 ? 'C' : 'D';
    $closeSign = ((float) $stmt['closing']) >= 0 ? 'C' : 'D';

    $lines = [];
    $lines[] = ':20:' . substr((string) $stmt['stmt_id'], 0, 16);
    $lines[] = ':25:DE12500105170648489890';
    $lines[] = ':28C:00001/001';
    $lines[] = ':60F:' . $openSign . $openDate . 'EUR' . fmtMoney(abs((float) $stmt['opening']));

    foreach ($stmt['entries'] as $e) {
        $d = substr(str_replace('-', '', (string) $e['date']), 2);
        $cd = !empty($e['cdt']) ? 'C' : 'D';
        $lines[] = ':61:' . $d . $d . $cd . 'R' . fmtMoney((float) $e['amount']) . 'NTRFNONREF//' . (string) $e['eref'];
        // ?20 reference chunks of 27 chars for classic MT940 readability
        $ref = (string) $e['ref'];
        $chunk = mb_substr($ref, 0, 27);
        $lines[] = ':86:EREF+' . (string) $e['eref'] . '?00Gutschrift?31' . (string) $e['iban'] . '?32' . (string) $e['name'] . '?20' . $chunk;
    }

    $lines[] = ':62F:' . $closeSign . $closeDate . 'EUR' . fmtMoney(abs((float) $stmt['closing']));
    $lines[] = '-';

    return implode("\n", $lines) . "\n";
}

if (!$importOnly) {
    foreach ($outDirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    $index = [
        'generated_at' => date('c'),
        'note' => 'Demo-Kontoauszüge für Akademie Bankabgleich. Nur fiktive Namen/IBANs.',
        'statements' => [],
    ];

    foreach ($statements as $stmt) {
        $camt = buildCamt($stmt);
        $mt940 = buildMt940($stmt);
        foreach ($outDirs as $dir) {
            file_put_contents($dir . '/' . $stmt['slug'] . '.camt053.xml', $camt);
            file_put_contents($dir . '/' . $stmt['slug'] . '.mt940.sta', $mt940);
        }
        $index['statements'][] = [
            'slug' => $stmt['slug'],
            'title' => $stmt['title'],
            'period' => $stmt['period'],
            'entries' => count($stmt['entries']),
            'camt' => $stmt['slug'] . '.camt053.xml',
            'mt940' => $stmt['slug'] . '.mt940.sta',
        ];
        echo "Geschrieben: {$stmt['slug']} (" . count($stmt['entries']) . " Umsätze)\n";
    }

    foreach ($outDirs as $dir) {
        file_put_contents($dir . '/README.md', "# Demo-Kontoauszüge Bankabgleich\n\n"
            . "- **q1-2026** — 1. Quartal 2026\n"
            . "- **q2a-2026** — 2. Quartal 2026, Teil 1 (Apr–Mai)\n"
            . "- **q2b-2026** — 2. Quartal 2026, Teil 2 (Juni)\n\n"
            . "Formate: CAMT.053 (`.camt053.xml`) und MT940 (`.mt940.sta`).\n"
            . "Import in die DB: `php bin/academy-bankabgleich-demo-statements.php` (ohne `--write-only`).\n"
            . "CAMT wird importiert (Umsätze bleiben). MT940-Dateien dienen als alternatives Format — gleicher Inhalt, bei Zweitimport als Duplikat erkannt.\n");
        file_put_contents($dir . '/index.json', json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    }
}

if ($writeOnly) {
    echo "Nur Dateien geschrieben, kein Import.\n";
    exit(0);
}

if (!Database::isConfigured()) {
    fwrite(STDERR, "Keine DB — Dateien ok, Import übersprungen.\n");
    exit(0);
}

MigrationRunner::runPending();

$demoDir = DG_ROOT . '/storage/media/training/bankabgleich/demo';
$totalImported = 0;
$totalDup = 0;
foreach ($statements as $stmt) {
    $path = $demoDir . '/' . $stmt['slug'] . '.camt053.xml';
    if (!is_file($path)) {
        fwrite(STDERR, "Fehlt: {$path}\n");
        continue;
    }
    $xml = (string) file_get_contents($path);
    $res = Camt053Importer::import($xml);
    $totalImported += (int) $res['imported'];
    $totalDup += (int) $res['duplicates'];
    echo "Import CAMT {$stmt['slug']}: imported={$res['imported']} skipped={$res['skipped']} duplicates={$res['duplicates']}\n";
}

echo "Fertig. Summe imported={$totalImported} duplicates={$totalDup}\n";
echo "Hinweis: MT940-Dateien liegen bereit; nicht parallel importiert (gleiche Fingerprints → Duplikate).\n";
