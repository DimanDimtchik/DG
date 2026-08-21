<?php
declare(strict_types=1);

/**
 * Selbsttest für das Buchungsjournal / Jahresabschluss.
 * Führt Migration + Backfill aus und prüft Bilanz-Invarianten (Soll = Haben).
 */
require dirname(__DIR__) . '/bootstrap.php';

function line(string $s = ''): void { echo $s . "\n"; }

if (!Database::isConfigured()) {
    line('FEHLER: Datenbank nicht konfiguriert.');
    exit(1);
}

$pdo = Database::pdo();

line('== Migrationen ==');
$applied = MigrationRunner::runPending();
line("Neu angewandt: {$applied}");

foreach (['dg_ledger_postings', 'dg_fiscal_years'] as $t) {
    $exists = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($t))->fetchColumn() !== false;
    line("Tabelle {$t}: " . ($exists ? 'OK' : 'FEHLT'));
}

line('');
line('== Backfill Journal ==');
$count = LedgerRepository::backfillAll();
line("Belege verarbeitet: {$count}");

$totalPostings = (int) $pdo->query("SELECT COUNT(*) FROM dg_ledger_postings WHERE source='voucher'")->fetchColumn();
line("Journalzeilen (Belege): {$totalPostings}");

line('');
line('== Invariante: Soll = Haben je Beleg ==');
$rows = $pdo->query(
    "SELECT voucher_id,
            ROUND(SUM(CASE WHEN side='debit' THEN amount ELSE 0 END), 2) AS d,
            ROUND(SUM(CASE WHEN side='credit' THEN amount ELSE 0 END), 2) AS c
     FROM dg_ledger_postings
     WHERE source='voucher' AND voucher_id IS NOT NULL
     GROUP BY voucher_id"
)->fetchAll(PDO::FETCH_ASSOC);
$imbalanced = 0;
$gd = 0.0; $gc = 0.0;
foreach ($rows as $r) {
    $gd += (float) $r['d'];
    $gc += (float) $r['c'];
    if (abs((float) $r['d'] - (float) $r['c']) > 0.005) {
        $imbalanced++;
        if ($imbalanced <= 10) {
            line("  Unbalanciert: Beleg #{$r['voucher_id']} Soll={$r['d']} Haben={$r['c']}");
        }
    }
}
line('Belege gesamt: ' . count($rows) . ', unbalanciert: ' . $imbalanced);
line('Summe Soll=' . number_format($gd, 2) . ' / Haben=' . number_format($gc, 2) . ' / Diff=' . number_format($gd - $gc, 2));

line('');
line('== Kontenübersicht je Jahr ==');
foreach (LedgerRepository::availableYears() as $year) {
    $ov = LedgerRepository::accountOverview($year, ['show_empty' => false]);
    line(sprintf(
        'Jahr %d: %d Konten, Umsatz Soll=%s, Haben=%s, Status=%s',
        $year,
        count($ov['accounts']),
        number_format($ov['totals']['debit'], 2),
        number_format($ov['totals']['credit'], 2),
        FiscalYearService::status($year)
    ));
    $pl = FiscalYearService::profitLossPreview($year);
    line(sprintf('        GuV: Ertrag=%s, Aufwand=%s, Ergebnis=%s',
        number_format($pl['income'], 2), number_format($pl['expense'], 2), number_format($pl['result'], 2)));
}

line('');
line('== Beispiel-Kontoauszug (Konto mit meisten Buchungen) ==');
$topAccount = $pdo->query(
    "SELECT account_number, COUNT(*) c FROM dg_ledger_postings WHERE source='voucher' GROUP BY account_number ORDER BY c DESC LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
if ($topAccount) {
    $acc = (string) $topAccount['account_number'];
    $year = (int) $pdo->query("SELECT fiscal_year FROM dg_ledger_postings WHERE account_number=" . $pdo->quote($acc) . " ORDER BY fiscal_year DESC LIMIT 1")->fetchColumn();
    $st = LedgerRepository::accountStatement($acc, $year);
    line("Konto {$acc} ({$st['account']['name']}) / {$year}: Vortrag=" . number_format($st['opening'], 2)
        . ', Buchungen=' . count($st['rows']) . ', Soll=' . number_format($st['debit'], 2)
        . ', Haben=' . number_format($st['credit'], 2) . ', Schlusssaldo=' . number_format($st['closing'], 2));
    foreach (array_slice($st['rows'], 0, 5) as $r) {
        line(sprintf('  %s  GK %-6s  S=%8s H=%8s  Saldo=%9s  %s',
            $r['date'], $r['contra_account'], number_format($r['debit'], 2), number_format($r['credit'], 2),
            number_format($r['balance'], 2), mb_substr($r['description'], 0, 40)));
    }
} else {
    line('Keine Buchungen vorhanden.');
}

line('');
line('== ARAP (Rechnungsabgrenzung) — Zweijahres-Split ==');
$arapVouchers = $pdo->query(
    "SELECT id, voucher_date, arap_current_year_percent, arap_next_year_percent
     FROM dg_vouchers WHERE arap_enabled = 1 ORDER BY id"
)->fetchAll(PDO::FETCH_ASSOC);
if (!$arapVouchers) {
    line('Keine ARAP-Belege vorhanden (Split wird bei Bedarf automatisch gebucht).');
} else {
    foreach ($arapVouchers as $v) {
        $vid = (int) $v['id'];
        $y = (int) substr((string) $v['voucher_date'], 0, 4);
        line(sprintf('Beleg #%d (%s, %d%%/%d%%):', $vid, $v['voucher_date'], (int) $v['arap_current_year_percent'], (int) $v['arap_next_year_percent']));
        $rows = $pdo->query(
            "SELECT fiscal_year, account_number, contra_account, side, amount, source, description
             FROM dg_ledger_postings WHERE voucher_id = {$vid} ORDER BY fiscal_year, id"
        )->fetchAll(PDO::FETCH_ASSOC);
        $byYear = [];
        foreach ($rows as $r) {
            $byYear[(int) $r['fiscal_year']] = true;
            line(sprintf('   %d  %-6s GK %-6s %-6s %10s  %-16s %s',
                (int) $r['fiscal_year'], $r['account_number'], $r['contra_account'], $r['side'],
                number_format((float) $r['amount'], 2), $r['source'], mb_substr((string) $r['description'], 0, 40)));
        }
        line('   Jahre mit Buchungen: ' . implode(', ', array_keys($byYear)));
        // Balance je Jahr prüfen
        foreach (array_keys($byYear) as $fy) {
            $d = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM dg_ledger_postings WHERE voucher_id={$vid} AND fiscal_year={$fy} AND side='debit'")->fetchColumn();
            $c = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM dg_ledger_postings WHERE voucher_id={$vid} AND fiscal_year={$fy} AND side='credit'")->fetchColumn();
            line(sprintf('   Jahr %d: Soll=%s Haben=%s %s', $fy, number_format($d, 2), number_format($c, 2), abs($d - $c) < 0.005 ? 'OK' : 'UNBALANCIERT!'));
        }
    }
}

line('');
line('== Erweiterte Buchhaltung ==');
foreach (['dg_bank_transactions', 'dg_manual_journal_batches'] as $t) {
    $exists = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($t))->fetchColumn() !== false;
    line("Tabelle {$t}: " . ($exists ? 'OK' : 'FEHLT'));
}
$manualCnt = (int) $pdo->query("SELECT COUNT(*) FROM dg_ledger_postings WHERE source='manual'")->fetchColumn();
$closingCnt = (int) $pdo->query("SELECT COUNT(*) FROM dg_ledger_postings WHERE source='closing'")->fetchColumn();
line('Manuelle Buchungen: ' . $manualCnt . ' · GuV-Abschluss: ' . $closingCnt);

line('');
line('== DATEV Journal-Qualität ==');
$sammel = (int) $pdo->query("SELECT COUNT(*) FROM dg_ledger_postings WHERE source='voucher' AND contra_account='Sammel'")->fetchColumn();
line('Zeilen mit Gegenkonto „Sammel“: ' . $sammel . ($sammel === 0 ? ' (OK)' : ' (sollte 0 sein nach Backfill)'));
$withTaxKey = (int) $pdo->query("SELECT COUNT(*) FROM dg_ledger_postings WHERE source='voucher' AND tax_key <> ''")->fetchColumn();
line('Zeilen mit BU-Schlüssel: ' . $withTaxKey);

line('');
line('Fertig.');
