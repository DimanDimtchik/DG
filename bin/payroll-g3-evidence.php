<?php
declare(strict_types=1);

/**
 * Gate G3 — Zwei-Monats-Nachweis aus Instanz-DB lesen (kein Unlock, kein Deploy).
 *
 * Usage:
 *   php bin/payroll-g3-evidence.php [--domain=dg.ganz-om.de] [--markdown]
 *   php bin/payroll-g3-evidence.php --selftest
 *
 * Exit: 0 immer bei erfolgreicher Auswertung.
 * Ausgabe enthält GATE_STATUS=open|partial|ready (ready = 2 komplette Monate — Gate manuell abnehmen).
 */

$argvList = $argv ?? [];
$selftest = in_array('--selftest', $argvList, true);
$markdown = in_array('--markdown', $argvList, true);
$domain = 'local';
foreach ($argvList as $arg) {
    if (str_starts_with($arg, '--domain=')) {
        $domain = substr($arg, 9);
    }
}

if ($selftest && !defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}

/**
 * @return list<string>
 */
function g3RequiredTables(): array
{
    return [
        'dg_payroll_runs',
        'dg_payroll_run_employees',
        'dg_payroll_documents',
        'dg_payroll_fibu_batches',
        'dg_payroll_sepa_exports',
        'dg_payroll_sv_exports',
    ];
}

function g3TableExists(PDO $pdo, string $table): bool
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $st = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
        $st->execute([$table]);
        return (bool) $st->fetchColumn();
    }
    $st = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));
    return $st !== false && $st->fetchColumn() !== false;
}

/**
 * @return array{missing: list<string>, ok: bool}
 */
function g3SchemaCheck(PDO $pdo): array
{
    $missing = [];
    foreach (g3RequiredTables() as $t) {
        if (!g3TableExists($pdo, $t)) {
            $missing[] = $t;
        }
    }
    return ['missing' => $missing, 'ok' => $missing === []];
}

/**
 * @return list<array<string, mixed>>
 */
function g3CollectLockedPeriods(PDO $pdo): array
{
    $sql = <<<'SQL'
SELECT
    r.id AS run_id,
    r.period_year,
    r.period_month,
    r.kind,
    r.status,
    r.is_superseded,
    r.locked_at,
    r.notes,
    (SELECT COUNT(*) FROM dg_payroll_run_employees e WHERE e.run_id = r.id AND e.status = 'ok') AS ma_ok,
    (SELECT COUNT(*) FROM dg_payroll_documents d WHERE d.run_id = r.id AND d.is_final = 1) AS pdf_final,
    (SELECT GROUP_CONCAT(fb.id) FROM dg_payroll_fibu_batches fb WHERE fb.run_id = r.id) AS fibu_ids,
    (SELECT GROUP_CONCAT(CONCAT(sx.id, IF(sx.is_probe = 1, ':PROBE', ':LIVE')))
        FROM dg_payroll_sepa_exports sx WHERE sx.run_id = r.id) AS sepa_ids,
    (SELECT GROUP_CONCAT(CONCAT(sv.id, ':', DATE(sv.created_at)))
        FROM dg_payroll_sv_exports sv WHERE sv.run_id = r.id) AS sv_ids
FROM dg_payroll_runs r
WHERE r.status = 'locked' AND r.is_superseded = 0
ORDER BY r.period_year ASC, r.period_month ASC, r.id ASC
SQL;

    // SQLite: IF() → CASE; GROUP_CONCAT ok
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $sql = str_replace(
            "CONCAT(sx.id, IF(sx.is_probe = 1, ':PROBE', ':LIVE'))",
            "(CAST(sx.id AS TEXT) || CASE WHEN sx.is_probe = 1 THEN ':PROBE' ELSE ':LIVE' END)",
            $sql
        );
        $sql = str_replace(
            "CONCAT(sv.id, ':', DATE(sv.created_at))",
            "(CAST(sv.id AS TEXT) || ':' || date(sv.created_at))",
            $sql
        );
        $sql = str_replace('GROUP_CONCAT(', 'GROUP_CONCAT(', $sql);
    }

    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function g3EvaluateMonths(array $rows, string $domain): array
{
    /** @var array<string, array<string, mixed>> $byPeriod */
    $byPeriod = [];
    foreach ($rows as $row) {
        $y = (int) $row['period_year'];
        $m = (int) $row['period_month'];
        $key = sprintf('%04d-%02d', $y, $m);
        if (!isset($byPeriod[$key])) {
            $byPeriod[$key] = [
                'period' => $key,
                'domain' => $domain,
                'run_ids' => [],
                'ma_ok' => 0,
                'pdf_final' => 0,
                'fibu' => [],
                'sepa' => [],
                'sv' => [],
                'notes' => [],
                'kinds' => [],
            ];
        }
        $byPeriod[$key]['run_ids'][] = (int) $row['run_id'];
        $byPeriod[$key]['ma_ok'] = max($byPeriod[$key]['ma_ok'], (int) $row['ma_ok']);
        $byPeriod[$key]['pdf_final'] += (int) $row['pdf_final'];
        if ($row['fibu_ids']) {
            $byPeriod[$key]['fibu'][] = (string) $row['fibu_ids'];
        }
        if ($row['sepa_ids']) {
            $byPeriod[$key]['sepa'][] = (string) $row['sepa_ids'];
        }
        if ($row['sv_ids']) {
            $byPeriod[$key]['sv'][] = (string) $row['sv_ids'];
        }
        if (trim((string) ($row['notes'] ?? '')) !== '') {
            $byPeriod[$key]['notes'][] = trim((string) $row['notes']);
        }
        $byPeriod[$key]['kinds'][] = (string) $row['kind'];
    }

    $out = [];
    foreach ($byPeriod as $period) {
        $fibuTxt = $period['fibu'] !== []
            ? implode(',', $period['fibu'])
            : g3ExceptionHint($period['notes'], 'FiBu');
        $sepaTxt = $period['sepa'] !== []
            ? implode(',', $period['sepa'])
            : g3ExceptionHint($period['notes'], 'SEPA');
        $svTxt = $period['sv'] !== [] ? implode(',', $period['sv']) : '—';

        $checks = [
            'locked_run' => $period['run_ids'] !== [],
            'ma_ok' => $period['ma_ok'] > 0,
            'pdf' => $period['pdf_final'] > 0,
            'fibu_or_exception' => $fibuTxt !== '—',
            'sepa_or_exception' => $sepaTxt !== '—',
            'sv' => $svTxt !== '—',
        ];
        $complete = !in_array(false, $checks, true);

        $out[] = [
            'period' => $period['period'],
            'domain' => $period['domain'],
            'run_ids' => implode(',', $period['run_ids']),
            'ma_ok' => (string) $period['ma_ok'],
            'fibu' => $fibuTxt,
            'sepa' => $sepaTxt,
            'sv' => $svTxt,
            'lexware' => '(manuell)',
            'who' => '(manuell)',
            'complete' => $complete,
            'checks' => $checks,
        ];
    }

    return $out;
}

/**
 * @param list<string> $notes
 */
function g3ExceptionHint(array $notes, string $topic): string
{
    foreach ($notes as $n) {
        if (stripos($n, $topic) !== false || stripos($n, 'ausnahme') !== false || stripos($n, 'probe') !== false) {
            return 'Ausnahme/Notiz: ' . mb_substr($n, 0, 80);
        }
    }
    return '—';
}

/**
 * @param list<array<string, mixed>> $months
 * @return array{status: string, complete_count: int, complete_periods: list<string>}
 */
function g3GateStatus(array $months): array
{
    $complete = array_values(array_filter($months, static fn (array $m): bool => (bool) $m['complete']));
    $periods = array_map(static fn (array $m): string => (string) $m['period'], $complete);
    $n = count($complete);
    if ($n >= 2) {
        return ['status' => 'ready', 'complete_count' => $n, 'complete_periods' => $periods];
    }
    if ($n === 1) {
        return ['status' => 'partial', 'complete_count' => $n, 'complete_periods' => $periods];
    }
    return ['status' => 'open', 'complete_count' => 0, 'complete_periods' => []];
}

/**
 * @param list<array<string, mixed>> $months
 */
function g3PrintMarkdown(array $months, string $domain, array $gate): void
{
    echo "## G3 Zwei-Monats-Nachweis (automatisch gelesen)\n\n";
    echo "| Feld | Monat A | Monat B |\n";
    echo "|------|---------|---------|\n";
    $a = $months[0] ?? null;
    $b = $months[1] ?? null;
    $rows = [
        'Instanz (Domain)' => 'domain',
        'Periode (YYYY-MM)' => 'period',
        'Run-ID(s) locked' => 'run_ids',
        'MA-Anzahl ok' => 'ma_ok',
        'FiBu Batch / Ausnahme' => 'fibu',
        'SEPA Export-ID / PROBE' => 'sepa',
        'SV-CSV Datum' => 'sv',
        'Lexware-Delta / OK' => 'lexware',
        'Wer / Datum' => 'who',
    ];
    foreach ($rows as $label => $key) {
        $va = $a[$key] ?? '';
        $vb = $b[$key] ?? '';
        if ($key === 'domain') {
            $va = $a ? $domain : '';
            $vb = $b ? $domain : '';
        }
        echo '| ' . $label . ' | ' . $va . ' | ' . $vb . " |\n";
    }
    echo "\nVollständige Monate (L5–L9 Pflicht): **" . $gate['complete_count'] . "** → `GATE_STATUS=" . $gate['status'] . "`\n";
    if ($gate['complete_periods'] !== []) {
        echo 'Perioden komplett: ' . implode(', ', $gate['complete_periods']) . "\n";
    }
    echo "\n> Gate G3 gilt erst nach Nutzer-/Berater-Abnahme der ausgefüllten Tabelle. Dieses Skript schaltet keine Flags.\n";
}

/**
 * Offline-Selftest ohne PDO/SQLite (Cloud-PHP oft ohne sqlite-Treiber).
 */
function g3Selftest(): void
{
    $required = g3RequiredTables();
    if (count($required) !== 6 || !in_array('dg_payroll_runs', $required, true)) {
        fwrite(STDERR, "SELFTEST FAIL required tables\n");
        exit(1);
    }

    $rowA = [
        'run_id' => 1,
        'period_year' => 2026,
        'period_month' => 1,
        'kind' => 'regular',
        'status' => 'locked',
        'is_superseded' => 0,
        'locked_at' => '2026-02-01',
        'notes' => '',
        'ma_ok' => 2,
        'pdf_final' => 2,
        'fibu_ids' => '5',
        'sepa_ids' => '8:PROBE',
        'sv_ids' => '3:2026-02-02',
    ];
    $rowBIncomplete = [
        'run_id' => 2,
        'period_year' => 2026,
        'period_month' => 2,
        'kind' => 'regular',
        'status' => 'locked',
        'is_superseded' => 0,
        'locked_at' => '2026-03-01',
        'notes' => '',
        'ma_ok' => 1,
        'pdf_final' => 1,
        'fibu_ids' => '6',
        'sepa_ids' => '9:PROBE',
        'sv_ids' => null,
    ];

    $months = g3EvaluateMonths([$rowA, $rowBIncomplete], 'selftest.local');
    $gate = g3GateStatus($months);
    if ($gate['status'] !== 'partial' || $gate['complete_count'] !== 1) {
        fwrite(STDERR, "SELFTEST FAIL expected partial/1 got {$gate['status']}/{$gate['complete_count']}\n");
        exit(1);
    }

    $rowBComplete = $rowBIncomplete;
    $rowBComplete['sv_ids'] = '4:2026-03-02';
    $months2 = g3EvaluateMonths([$rowA, $rowBComplete], 'selftest.local');
    $gate2 = g3GateStatus($months2);
    if ($gate2['status'] !== 'ready' || $gate2['complete_count'] !== 2) {
        fwrite(STDERR, "SELFTEST FAIL expected ready/2\n");
        exit(1);
    }

    $empty = g3GateStatus([]);
    if ($empty['status'] !== 'open') {
        fwrite(STDERR, "SELFTEST FAIL empty→open\n");
        exit(1);
    }

    // Ausnahme-Notiz statt FiBu-Meta
    $rowExc = $rowA;
    $rowExc['fibu_ids'] = null;
    $rowExc['notes'] = 'FiBu-Ausnahme: Flag aus, nur Probe';
    $mExc = g3EvaluateMonths([$rowExc], 'selftest.local');
    if (!$mExc[0]['complete'] || !str_contains((string) $mExc[0]['fibu'], 'Ausnahme')) {
        fwrite(STDERR, "SELFTEST FAIL fibu exception note\n");
        exit(1);
    }

    echo "OK: required tables list\n";
    echo "OK: partial with 1 complete month\n";
    echo "OK: ready with 2 complete months\n";
    echo "OK: empty → open\n";
    echo "OK: FiBu Ausnahme aus Notiz\n";
    echo "SELFTEST_OK\n";
}

if ($selftest) {
    g3Selftest();
    exit(0);
}

require dirname(__DIR__) . '/bootstrap.php';

if (!Database::isConfigured()) {
    echo "GATE_STATUS=open\n";
    echo "REASON=database_not_configured\n";
    echo "HINT=Auf Instanz (nach Merge/Deploy + Migrationen) ausführen: php bin/payroll-g3-evidence.php --domain=… --markdown\n";
    echo "PROBE_NOTE=Master dg.ganz-om.de 2026-10-30: Payroll-Schema fehlt (Code noch nicht auf Server), daher kein Pilot-Nachweis möglich.\n";
    exit(0);
}

$pdo = Database::pdo();
$schema = g3SchemaCheck($pdo);
if (!$schema['ok']) {
    echo "GATE_STATUS=open\n";
    echo "REASON=payroll_schema_missing\n";
    echo 'MISSING=' . implode(',', $schema['missing']) . "\n";
    echo "HINT=PR mergen, deployen, Migrationen laufen lassen, dann 2 Testmonate L5–L9 fahren.\n";
    exit(0);
}

$months = g3EvaluateMonths(g3CollectLockedPeriods($pdo), $domain);
$gate = g3GateStatus($months);

echo 'GATE_STATUS=' . $gate['status'] . "\n";
echo 'COMPLETE_MONTHS=' . $gate['complete_count'] . "\n";
echo 'DOMAIN=' . $domain . "\n";

if ($markdown || true) {
    echo "\n";
    g3PrintMarkdown($months, $domain, $gate);
    if ($months !== []) {
        echo "\n### Detailchecks\n";
        foreach ($months as $m) {
            $bits = [];
            foreach ($m['checks'] as $k => $v) {
                $bits[] = $k . '=' . ($v ? 'ok' : 'FAIL');
            }
            echo '- ' . $m['period'] . ' complete=' . ($m['complete'] ? 'yes' : 'no') . ' · ' . implode(', ', $bits) . "\n";
        }
    }
}

if ($gate['status'] !== 'ready') {
    echo "\nNEXT=Zwei verschiedene Kalendermonate mit lock + PDF + FiBu/Ausnahme + SEPA/Ausnahme + SV-CSV auf derselben Instanz.\n";
}
