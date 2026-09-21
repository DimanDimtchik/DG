<?php
/**
 * Z5b/Z5c Rückstellungs-Preview + Buchungsbestätigung.
 *
 * @var array<string, mixed> $timeProvisionPreview
 * @var array<string, mixed>|null $timeProvisionDraft
 * @var int|null $timeProvisionExistingBatchId
 * @var bool $timeProvisionCanBook
 * @var int $timeProvisionYear
 * @var array{type: string, message: string}|null $flash
 */
$preview = is_array($timeProvisionPreview ?? null) ? $timeProvisionPreview : [];
$year = (int) ($timeProvisionYear ?? ($preview['year'] ?? date('Y')));
$rows = is_array($preview['rows'] ?? null) ? $preview['rows'] : [];
$totals = is_array($preview['totals'] ?? null) ? $preview['totals'] : [];
$cfg = is_array($preview['config'] ?? null) ? $preview['config'] : [];
$warnings = is_array($preview['warnings'] ?? null) ? $preview['warnings'] : [];
$draft = is_array($timeProvisionDraft ?? null) ? $timeProvisionDraft : null;
$existingBatch = isset($timeProvisionExistingBatchId) ? (int) $timeProvisionExistingBatchId : 0;
$canBook = !empty($timeProvisionCanBook);
$otOn = !empty($cfg['ot_enabled']);
?>
<div class="dg-wrap dg-zeiterfassung-rueckstellung">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Rückstellungen</h1>
      <p class="dg-lead">Z5c — Preview Urlaub<?= $otOn ? '/Überstunden' : '' ?> · Buchung nur nach Bestätigung</p>
    </div>
    <div class="dg-toolbar">
      <a class="dg-button" href="<?= View::escape(SettingsRegistry::tabUrl('zeiterfassung')) ?>">Einstellungen</a>
      <a class="dg-button" href="/app?page=zeiterfassung-urlaub">Urlaub</a>
      <a class="dg-button" href="/app?page=buchhaltung-manuelle-buchung&amp;year=<?= (int) $year ?>">Manuelle Buchung</a>
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
      <a class="dg-button" href="/app?page=zeiterfassung-rueckstellung&amp;year=<?= (int) $year ?>&amp;download=csv">CSV Prüfnachweis</a>
    </form>
    <p class="dg-field-hint">
      Formel: Resttage × Tageskostensatz × Sozialfaktor.
      Methode: <?= View::escape((string) ($cfg['cost_method'] ?? '')) ?>
      (Divisor <?= View::escape((string) ($cfg['divisor'] ?? '')) ?>).
      Konten Urlaub: <?= View::escape((string) ($cfg['account_vacation_expense'] ?? '')) ?>
      / <?= View::escape((string) ($cfg['account_vacation_liability'] ?? '')) ?>
      — <strong>Vorschlag, mit Steuerberater prüfen.</strong>
    </p>
  </section>

  <?php foreach ($warnings as $w) : ?>
    <div class="dg-flash dg-flash--warning"><?= View::escape((string) $w) ?></div>
  <?php endforeach; ?>

  <?php if ($existingBatch > 0) : ?>
    <div class="dg-flash dg-flash--success">
      Für <?= (int) $year ?> bereits gebucht: Batch #<?= $existingBatch ?>.
      <a href="/app?page=buchhaltung-manuelle-buchung&amp;year=<?= (int) $year ?>">Zur manuellen Buchung</a>
    </div>
  <?php endif; ?>

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

  <?php if ($draft !== null && $existingBatch < 1) : ?>
    <section class="dg-panel">
      <h2 class="dg-subsection-title">Buchungsentwurf</h2>
      <p>
        Stichtag <?= View::escape((string) ($draft['batch_date'] ?? '')) ?> ·
        Urlaub <?= View::escape(number_format((float) ($draft['vacation_amount'] ?? 0), 2, ',', '')) ?> €
        <?php if ((float) ($draft['overtime_amount'] ?? 0) > 0) : ?>
          · Überstunden <?= View::escape(number_format((float) ($draft['overtime_amount'] ?? 0), 2, ',', '')) ?> €
        <?php endif; ?>
        · <strong>Gesamt <?= View::escape(number_format((float) ($draft['total'] ?? 0), 2, ',', '')) ?> €</strong>
      </p>
      <div class="dg-table-wrap">
        <table class="dg-table">
          <thead>
            <tr>
              <th>Konto</th>
              <th>Seite</th>
              <th class="dg-table__num">Betrag</th>
              <th>Gegenkonto</th>
              <th>Text</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (($draft['lines'] ?? []) as $line) : ?>
              <?php if (!is_array($line)) {
                  continue;
              } ?>
              <tr>
                <td><?= View::escape((string) ($line['account_number'] ?? '')) ?></td>
                <td><?= View::escape((string) ($line['side'] ?? '') === 'credit' ? 'Haben' : 'Soll') ?></td>
                <td class="dg-table__num"><?= View::escape(number_format((float) ($line['amount'] ?? 0), 2, ',', '')) ?></td>
                <td><?= View::escape((string) ($line['contra_account'] ?? '')) ?></td>
                <td><?= View::escape((string) ($line['description'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($canBook) : ?>
        <form method="post" action="/app?page=zeiterfassung-rueckstellung&amp;year=<?= (int) $year ?>" class="dg-form" style="margin-top:1rem"
              onsubmit="return confirm('Rückstellung wirklich in das Journal buchen?');">
          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
          <input type="hidden" name="provision_action" value="book">
          <input type="hidden" name="year" value="<?= (int) $year ?>">
          <label class="dg-field dg-field--checkbox">
            <span>
              <input type="checkbox" name="confirm_book" value="1" required>
              Ich bestätige Betrag und Konten (Abstimmung mit Steuerberater). Keine stille Cron-Buchung.
            </span>
          </label>
          <button type="submit" class="dg-button dg-button--primary">Buchung bestätigen</button>
        </form>
      <?php else : ?>
        <p class="dg-muted">Buchung erfordert Recht wie bei manuellen Journalbuchungen.</p>
      <?php endif; ?>
    </section>
  <?php elseif ($draft === null && $existingBatch < 1 && (float) ($totals['total'] ?? 0) <= 0) : ?>
    <div class="dg-flash dg-flash--warning">Kein Buchungsentwurf — Summe 0 oder Konten unvollständig.</div>
  <?php endif; ?>
</div>
