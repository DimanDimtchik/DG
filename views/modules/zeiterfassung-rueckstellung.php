<?php
/**
 * Z5b Rückstellungs-Preview (keine Buchung).
 *
 * @var array<string, mixed> $timeProvisionPreview
 * @var array<string, mixed> $timeProvisionConfig
 * @var int $timeProvisionYear
 * @var array{type: string, message: string}|null $flash
 */
$preview = is_array($timeProvisionPreview ?? null) ? $timeProvisionPreview : [];
$year = (int) ($timeProvisionYear ?? ($preview['year'] ?? date('Y')));
$rows = is_array($preview['rows'] ?? null) ? $preview['rows'] : [];
$totals = is_array($preview['totals'] ?? null) ? $preview['totals'] : [];
$cfg = is_array($preview['config'] ?? null) ? $preview['config'] : [];
$warnings = is_array($preview['warnings'] ?? null) ? $preview['warnings'] : [];
$otOn = !empty($cfg['ot_enabled']);
?>
<div class="dg-wrap dg-zeiterfassung-rueckstellung">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Rückstellungen</h1>
      <p class="dg-lead">Z5b — Preview Urlaub<?= $otOn ? '/Überstunden' : '' ?> · keine Buchung</p>
    </div>
    <div class="dg-toolbar">
      <a class="dg-button" href="<?= View::escape(SettingsRegistry::tabUrl('zeiterfassung')) ?>">Einstellungen</a>
      <a class="dg-button" href="/app?page=zeiterfassung-urlaub">Urlaub</a>
      <a class="dg-button" href="/app?page=buchhaltung-manuelle-buchung">Manuelle Buchung</a>
    </div>
  </header>

  <section class="dg-panel">
    <form method="get" action="/app" class="dg-form dg-form--inline">
      <input type="hidden" name="page" value="zeiterfassung-rueckstellung">
      <label class="dg-field">
        <span class="dg-field-label">Jahr (Stichtag 31.12.)</span>
        <input type="number" name="year" value="<?= (int) $year ?>" min="2000" max="2100" required>
      </label>
      <button type="submit" class="dg-button">Berechnen</button>
      <a class="dg-button dg-button--primary" href="/app?page=zeiterfassung-rueckstellung&amp;year=<?= (int) $year ?>&amp;download=csv">CSV Prüfnachweis</a>
    </form>
    <p class="dg-field-hint">
      Formel: Resttage × Tageskostensatz × Sozialfaktor.
      Methode: <?= View::escape((string) ($cfg['cost_method'] ?? '')) ?>
      (Divisor <?= View::escape((string) ($cfg['divisor'] ?? '')) ?>).
      Konten Urlaub: <?= View::escape((string) ($cfg['account_vacation_expense'] ?? '')) ?>
      / <?= View::escape((string) ($cfg['account_vacation_liability'] ?? '')) ?>
      — <strong>Vorschlag, mit Steuerberater prüfen.</strong>
      Buchung erst Z5c.
    </p>
    <?php if (($cfg['booking_text'] ?? '') !== '') : ?>
      <p class="dg-muted">Buchungstext-Vorschlag: <?= View::escape((string) $cfg['booking_text']) ?></p>
    <?php endif; ?>
  </section>

  <?php foreach ($warnings as $w) : ?>
    <div class="dg-flash dg-flash--warning"><?= View::escape((string) $w) ?></div>
  <?php endforeach; ?>

  <section class="dg-panel">
    <h2 class="dg-subsection-title">Preview Stichtag <?= View::escape((string) ($preview['stichtag'] ?? '')) ?></h2>
    <?php if ($rows === []) : ?>
      <p class="dg-muted">Keine Mitarbeiter-Kontakte.</p>
    <?php else : ?>
      <div class="dg-table-wrap">
        <table class="dg-table">
          <thead>
            <tr>
              <th>Mitarbeiter</th>
              <th class="dg-table__num">Rest Tage</th>
              <th class="dg-table__num">Tageskosten</th>
              <th class="dg-table__num">Urlaub €</th>
              <?php if ($otOn) : ?>
                <th class="dg-table__num">ÜStd h</th>
                <th class="dg-table__num">ÜStd €</th>
              <?php endif; ?>
              <th class="dg-table__num">Summe €</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row) : ?>
              <tr>
                <td><?= View::escape((string) ($row['label'] ?? '')) ?></td>
                <td class="dg-table__num"><?= View::escape(number_format((float) ($row['rest_days'] ?? 0), 1, ',', '')) ?></td>
                <td class="dg-table__num"><?= View::escape(number_format((float) ($row['daily_cost'] ?? 0), 2, ',', '')) ?></td>
                <td class="dg-table__num"><?= View::escape(number_format((float) ($row['vacation_amount'] ?? 0), 2, ',', '')) ?></td>
                <?php if ($otOn) : ?>
                  <td class="dg-table__num"><?= View::escape(number_format((float) ($row['ot_hours'] ?? 0), 2, ',', '')) ?></td>
                  <td class="dg-table__num"><?= View::escape(number_format((float) ($row['ot_amount'] ?? 0), 2, ',', '')) ?></td>
                <?php endif; ?>
                <td class="dg-table__num"><?= View::escape(number_format((float) ($row['row_total'] ?? 0), 2, ',', '')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <th colspan="3">Summe</th>
              <th class="dg-table__num"><?= View::escape(number_format((float) ($totals['vacation'] ?? 0), 2, ',', '')) ?></th>
              <?php if ($otOn) : ?>
                <th></th>
                <th class="dg-table__num"><?= View::escape(number_format((float) ($totals['overtime'] ?? 0), 2, ',', '')) ?></th>
              <?php endif; ?>
              <th class="dg-table__num"><?= View::escape(number_format((float) ($totals['total'] ?? 0), 2, ',', '')) ?></th>
            </tr>
          </tfoot>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>
