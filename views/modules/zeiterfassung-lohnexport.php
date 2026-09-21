<?php
/**
 * Z6b/Z6c Lohn-Export CSV + DATEV-Übergabe + Protokoll.
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
?>
<div class="dg-wrap dg-zeiterfassung-lohnexport">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Lohn-Export</h1>
      <p class="dg-lead">Z6c — CSV-Standard + DATEV Lohn-Zeitenübergabe (keine Netto-Berechnung)</p>
    </div>
    <div class="dg-toolbar">
      <a class="dg-button" href="/app?page=zeiterfassung-monat">Monatsblatt</a>
      <a class="dg-button" href="/app?page=zeiterfassung-team">Team heute</a>
      <a class="dg-button" href="/app?page=zeiterfassung-rueckstellung">Rückstellungen</a>
      <a class="dg-button" href="<?= View::escape(SettingsRegistry::tabUrl('kontenrahmen')) ?>">DATEV-Einstellungen</a>
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
    </form>
    <p class="dg-field-hint">
      Berater-Nr.: <?= View::escape((string) ($datevCfg['consultant_number'] ?? '')) ?: '—' ?>
      · Mandanten-Nr.: <?= View::escape((string) ($datevCfg['client_number'] ?? '')) ?: '—' ?>
      <?php if (!$datevOk) : ?>
        — bitte unter <a href="<?= View::escape(SettingsRegistry::tabUrl('kontenrahmen')) ?>">Einstellungen → Kontenrahmen</a> pflegen.
      <?php endif; ?>
      DATEV-Datei = CSV-Übergabe (kein LODAS-Binärformat); mit Steuerberater abstimmen.
    </p>
  </section>

  <section class="dg-panel">
    <h2 class="dg-subsection-title">Vorschau <?= View::escape($ym) ?></h2>
    <?php if ($rows === []) : ?>
      <p class="dg-muted">Keine Mitarbeiter-Kontakte.</p>
    <?php else : ?>
      <div class="dg-table-wrap">
        <table class="dg-table">
          <thead>
            <tr>
              <th>Personalnr.</th>
              <th>Name</th>
              <th class="dg-table__num">Soll</th>
              <th class="dg-table__num">Ist</th>
              <th class="dg-table__num">Pause</th>
              <th class="dg-table__num">ÜStd</th>
              <th class="dg-table__num">Urlaub</th>
              <th class="dg-table__num">Krank</th>
              <th class="dg-table__num">Korrektur</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row) : ?>
              <tr>
                <td><?= View::escape((string) ($row['personal_number'] ?? '')) ?></td>
                <td><?= View::escape((string) ($row['name'] ?? '')) ?></td>
                <td class="dg-table__num"><?= (int) ($row['soll_minutes'] ?? 0) ?></td>
                <td class="dg-table__num"><?= (int) ($row['ist_minutes'] ?? 0) ?></td>
                <td class="dg-table__num"><?= (int) ($row['pause_minutes'] ?? 0) ?></td>
                <td class="dg-table__num"><?= (int) ($row['ueberstunden_minutes'] ?? 0) ?></td>
                <td class="dg-table__num"><?= View::escape(number_format((float) ($row['urlaub_tage'] ?? 0), 1, ',', '')) ?></td>
                <td class="dg-table__num"><?= View::escape(number_format((float) ($row['krank_tage'] ?? 0), 1, ',', '')) ?></td>
                <td class="dg-table__num"><?= (int) ($row['korrektur_minutes'] ?? 0) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <th colspan="2">Summe</th>
              <th class="dg-table__num"><?= (int) ($totals['soll_minutes'] ?? 0) ?></th>
              <th class="dg-table__num"><?= (int) ($totals['ist_minutes'] ?? 0) ?></th>
              <th class="dg-table__num"><?= (int) ($totals['pause_minutes'] ?? 0) ?></th>
              <th class="dg-table__num"><?= (int) ($totals['ueberstunden_minutes'] ?? 0) ?></th>
              <th class="dg-table__num"><?= View::escape(number_format((float) ($totals['urlaub_tage'] ?? 0), 1, ',', '')) ?></th>
              <th class="dg-table__num"><?= View::escape(number_format((float) ($totals['krank_tage'] ?? 0), 1, ',', '')) ?></th>
              <th class="dg-table__num"><?= (int) ($totals['korrektur_minutes'] ?? 0) ?></th>
            </tr>
          </tfoot>
        </table>
      </div>
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
