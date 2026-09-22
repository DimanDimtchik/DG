<?php
/**
 * Z6b–Z6e Lohn-Export CSV + DATEV/Lexoffice + Überstunden-Auszahlung + Protokoll.
 *
 * @var string $timePayrollYearMonth
 * @var array{year_month: string, rows: list<array<string, mixed>>, totals: array<string, mixed>} $timePayrollDataset
 * @var list<array<string, mixed>> $timePayrollExports
 * @var array{consultant_number: string, client_number: string} $timePayrollDatevSettings
 * @var bool $timePayrollDatevConfigured
 * @var array{type: string, message: string}|null $flash
 */
$ym = (string) ($timePayrollYearMonth ?? date('Y-m'));
$dataset = is_array($timePayrollDataset ?? null) ? $timePayrollDataset : ['rows' => [], 'totals' => []];
$rows = is_array($dataset['rows'] ?? null) ? $dataset['rows'] : [];
$totals = is_array($dataset['totals'] ?? null) ? $dataset['totals'] : [];
$exports = is_array($timePayrollExports ?? null) ? $timePayrollExports : [];
$datevCfg = is_array($timePayrollDatevSettings ?? null) ? $timePayrollDatevSettings : [];
$datevOk = !empty($timePayrollDatevConfigured);
$prev = (new DateTimeImmutable($ym . '-01'))->modify('-1 month')->format('Y-m');
$next = (new DateTimeImmutable($ym . '-01'))->modify('+1 month')->format('Y-m');
$hasAppliedPayout = false;
$hasEditablePayout = false;
foreach ($rows as $r) {
    if (!empty($r['auszahlung_applied'])) {
        $hasAppliedPayout = true;
    } else {
        $hasEditablePayout = true;
    }
}
?>
<div class="dg-wrap dg-zeiterfassung-lohnexport">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Lohn-Export</h1>
      <p class="dg-lead">Z6e — CSV / DATEV / Lexoffice Zeitenübergabe · Überstunden-Auszahlung vom Konto</p>
    </div>
    <div class="dg-toolbar">
      <a class="dg-button" href="/app?page=zeiterfassung-monat">Monatsblatt</a>
      <a class="dg-button" href="/app?page=zeiterfassung-team">Team heute</a>
      <a class="dg-button" href="/app?page=zeiterfassung-stundenimport">Stunden-Import</a>
      <a class="dg-button" href="/app?page=zeiterfassung-konto">Zeitkonto</a>
      <a class="dg-button" href="/app?page=zeiterfassung-rueckstellung">Rückstellungen</a>
      <a class="dg-button" href="<?= View::escape(SettingsRegistry::tabUrl('kontenrahmen')) ?>">DATEV-Einstellungen</a>
      <a class="dg-button" href="/app?page=kontakte">Kontaktakte (PDF)</a>
    </div>
  </header>

  <section class="dg-panel">
    <form method="get" action="/app" class="dg-form dg-form--inline">
      <input type="hidden" name="page" value="zeiterfassung-lohnexport">
      <label class="dg-field">
        <span class="dg-field-label">Monat</span>
        <input type="month" name="month" value="<?= View::escape($ym) ?>" required>
      </label>
      <button type="submit" class="dg-button">Anzeigen</button>
      <a class="dg-button" href="/app?page=zeiterfassung-lohnexport&amp;month=<?= View::escape($prev) ?>">←</a>
      <a class="dg-button" href="/app?page=zeiterfassung-lohnexport&amp;month=<?= View::escape($next) ?>">→</a>
      <a class="dg-button" href="/app?page=zeiterfassung-lohnexport&amp;month=<?= View::escape($ym) ?>&amp;download=csv">CSV herunterladen</a>
      <?php if ($datevOk) : ?>
        <a class="dg-button dg-button--primary" href="/app?page=zeiterfassung-lohnexport&amp;month=<?= View::escape($ym) ?>&amp;download=datev">DATEV Lohn-Zeiten</a>
      <?php else : ?>
        <span class="dg-button dg-button--primary" aria-disabled="true" title="Berater-/Mandantennummer setzen">DATEV Lohn-Zeiten</span>
      <?php endif; ?>
      <a class="dg-button" href="/app?page=zeiterfassung-lohnexport&amp;month=<?= View::escape($ym) ?>&amp;download=lexoffice">Lexoffice Lohn-Zeiten</a>
    </form>
    <p class="dg-field-hint">
      Berater-Nr.: <?= View::escape((string) ($datevCfg['consultant_number'] ?? '')) ?: '—' ?>
      · Mandanten-Nr.: <?= View::escape((string) ($datevCfg['client_number'] ?? '')) ?: '—' ?>
      <?php if (!$datevOk) : ?>
        — bitte unter <a href="<?= View::escape(SettingsRegistry::tabUrl('kontenrahmen')) ?>">Einstellungen → Kontenrahmen</a> pflegen.
      <?php endif; ?>
      DATEV/Lexoffice = CSV-Übergabe (kein Binärformat); mit Steuerberater abstimmen.
      Lohnabrechnungs-PDF vom Berater unter Kontakt → Mitarbeiterdaten als <code>payroll_slip</code> ablegen (kein Generieren im CRM).
    </p>
    <p class="dg-field-hint">
      <strong>Überstunden-Auszahlung:</strong> unten Minuten eintragen und speichern (auch teilweise).
      Beim ersten Export des Monats mit Auszahlung werden diese Minuten FIFO vom Überstundenkonto abgebucht und erscheinen in der Export-Datei.
      Spalte „ÜStd“ = Monatsdifferenz Ist−Soll; „Konto“ = aktueller Überstunden-Saldo.
    </p>
  </section>

  <section class="dg-panel">
    <h2 class="dg-subsection-title">Vorschau <?= View::escape($ym) ?></h2>
    <p class="dg-muted">Soll/Ist/Pause/ÜStd/Korrektur/Konto als Stunden:Minuten (intern und DATEV-CSV: Minuten). Lexoffice-CSV: Dezimalstunden. Auszahlung: Minuten.</p>
    <?php if ($rows === []) : ?>
      <p class="dg-muted">Keine Mitarbeiter-Kontakte.</p>
    <?php else : ?>
      <form method="post" action="/app?page=zeiterfassung-lohnexport&amp;month=<?= View::escape($ym) ?>" class="dg-form">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <input type="hidden" name="payroll_ot_payout_action" value="save">
        <input type="hidden" name="month" value="<?= View::escape($ym) ?>">
        <div class="dg-table-wrap">
          <table class="dg-table">
            <thead>
              <tr>
                <th>Personalnr.</th>
                <th>Name</th>
                <th class="dg-table__num" title="Geplante Arbeitszeit laut Schicht/Vertrag">Soll</th>
                <th class="dg-table__num" title="Tatsächliche Netto-Arbeitszeit">Ist</th>
                <th class="dg-table__num" title="Pausen (gestempelt + Zwangspause)">Pause</th>
                <th class="dg-table__num" title="Überstunden Monat (Ist − Soll, wenn positiv)">ÜStd</th>
                <th class="dg-table__num">Urlaub</th>
                <th class="dg-table__num">Krank</th>
                <th class="dg-table__num" title="Korrekturbuchungen">Korrektur</th>
                <th class="dg-table__num" title="Aktueller Überstunden-Saldo (Zeitkonto)">Konto</th>
                <th class="dg-table__num" title="Zur Auszahlung vorgesehene Minuten (Teilbetrag möglich)">Auszahlung (Min.)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $row) :
                  $cid = (int) ($row['contact_id'] ?? 0);
                  $konto = (int) ($row['konto_saldo_minutes'] ?? 0);
                  $payout = (int) ($row['auszahlung_minutes'] ?? 0);
                  $applied = !empty($row['auszahlung_applied']);
                  $appliedMins = $row['auszahlung_applied_minutes'] !== null
                      ? (int) $row['auszahlung_applied_minutes']
                      : null;
                  ?>
                <tr>
                  <td><?= View::escape((string) ($row['personal_number'] ?? '')) ?></td>
                  <td><?= View::escape((string) ($row['name'] ?? '')) ?></td>
                  <td class="dg-table__num"><?= View::escape(TimeClockService::formatMinutes((int) ($row['soll_minutes'] ?? 0))) ?></td>
                  <td class="dg-table__num"><?= View::escape(TimeClockService::formatMinutes((int) ($row['ist_minutes'] ?? 0))) ?></td>
                  <td class="dg-table__num"><?= View::escape(TimeClockService::formatMinutes((int) ($row['pause_minutes'] ?? 0))) ?></td>
                  <td class="dg-table__num"><?= View::escape(TimeClockService::formatMinutes((int) ($row['ueberstunden_minutes'] ?? 0))) ?></td>
                  <td class="dg-table__num"><?= View::escape(number_format((float) ($row['urlaub_tage'] ?? 0), 1, ',', '')) ?></td>
                  <td class="dg-table__num"><?= View::escape(number_format((float) ($row['krank_tage'] ?? 0), 1, ',', '')) ?></td>
                  <td class="dg-table__num"><?= View::escape(TimeClockService::formatSignedCorrection((int) ($row['korrektur_minutes'] ?? 0))) ?></td>
                  <td class="dg-table__num"><?= View::escape(TimeClockService::formatMinutes($konto)) ?></td>
                  <td class="dg-table__num">
                    <?php if ($applied) : ?>
                      <?= View::escape(TimeClockService::formatMinutes($appliedMins ?? $payout)) ?>
                      <span class="dg-muted" title="Bereits beim Export vom Konto abgebucht">✓</span>
                    <?php elseif ($konto < 1) : ?>
                      <input type="hidden" name="auszahlung[<?= $cid ?>]" value="0">
                      <span class="dg-muted">0</span>
                    <?php else : ?>
                      <input
                        type="number"
                        name="auszahlung[<?= $cid ?>]"
                        value="<?= $payout > 0 ? $payout : '' ?>"
                        min="0"
                        max="<?= $konto ?>"
                        step="1"
                        class="dg-input dg-input--narrow"
                        placeholder="0"
                        title="Max. <?= View::escape(TimeClockService::formatMinutes($konto)) ?> (Konto)"
                      >
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <th colspan="2">Summe</th>
                <th class="dg-table__num"><?= View::escape(TimeClockService::formatMinutes((int) ($totals['soll_minutes'] ?? 0))) ?></th>
                <th class="dg-table__num"><?= View::escape(TimeClockService::formatMinutes((int) ($totals['ist_minutes'] ?? 0))) ?></th>
                <th class="dg-table__num"><?= View::escape(TimeClockService::formatMinutes((int) ($totals['pause_minutes'] ?? 0))) ?></th>
                <th class="dg-table__num"><?= View::escape(TimeClockService::formatMinutes((int) ($totals['ueberstunden_minutes'] ?? 0))) ?></th>
                <th class="dg-table__num"><?= View::escape(number_format((float) ($totals['urlaub_tage'] ?? 0), 1, ',', '')) ?></th>
                <th class="dg-table__num"><?= View::escape(number_format((float) ($totals['krank_tage'] ?? 0), 1, ',', '')) ?></th>
                <th class="dg-table__num"><?= View::escape(TimeClockService::formatSignedCorrection((int) ($totals['korrektur_minutes'] ?? 0))) ?></th>
                <th class="dg-table__num"><?= View::escape(TimeClockService::formatMinutes((int) ($totals['konto_saldo_minutes'] ?? 0))) ?></th>
                <th class="dg-table__num"><?= View::escape(TimeClockService::formatMinutes((int) ($totals['auszahlung_minutes'] ?? 0))) ?></th>
              </tr>
            </tfoot>
          </table>
        </div>
        <?php if ($hasEditablePayout) : ?>
          <p class="dg-toolbar" style="margin-top:1rem">
            <button type="submit" class="dg-button dg-button--primary">Auszahlung speichern</button>
            <span class="dg-muted">Danach Export starten — Abbuchung vom Konto erst beim Download.</span>
          </p>
        <?php endif; ?>
        <?php if ($hasAppliedPayout) : ?>
          <p class="dg-flash dg-flash--info" style="margin-top:1rem">
            Mindestens eine Auszahlung für diesen Monat wurde bereits beim Export vom Überstundenkonto abgebucht und ist gesperrt.
          </p>
        <?php endif; ?>
      </form>
    <?php endif; ?>
  </section>

  <section class="dg-panel">
    <h2 class="dg-subsection-title">Export-Protokoll</h2>
    <?php if ($exports === []) : ?>
      <p class="dg-muted">Noch keine Exporte protokolliert.</p>
    <?php else : ?>
      <div class="dg-table-wrap">
        <table class="dg-table">
          <thead>
            <tr>
              <th>Zeit</th>
              <th>Monat</th>
              <th>Format</th>
              <th>Datei</th>
              <th class="dg-table__num">Zeilen</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($exports as $ex) : ?>
              <tr>
                <td><?= View::escape((string) ($ex['created_at'] ?? '')) ?></td>
                <td><?= View::escape((string) ($ex['year_month'] ?? '')) ?></td>
                <td><?= View::escape((string) ($ex['format'] ?? '')) ?></td>
                <td><?= View::escape((string) ($ex['filename'] ?? '')) ?></td>
                <td class="dg-table__num"><?= (int) ($ex['row_count'] ?? 0) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>
