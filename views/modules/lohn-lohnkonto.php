<?php
/**
 * Lohn L6c — Lohnkonto UI.
 *
 * @var int $lohnkontoYear
 * @var int $lohnkontoContactId
 * @var string $lohnkontoContactLabel
 * @var bool $lohnkontoIncludeDrafts
 * @var list<array<string, mixed>> $lohnkontoRows
 * @var array{gross_total: float, lst: float, sv_an: float, net_pay: float, count: int} $lohnkontoTotals
 * @var list<array{id: int, label: string}> $lohnkontoStaffOptions
 * @var array{type: string, message: string}|null $flash
 */
$year = (int) ($lohnkontoYear ?? (int) date('Y'));
$contactId = (int) ($lohnkontoContactId ?? 0);
$contactLabel = (string) ($lohnkontoContactLabel ?? '');
$includeDrafts = !empty($lohnkontoIncludeDrafts);
$rows = is_array($lohnkontoRows ?? null) ? $lohnkontoRows : [];
$totals = is_array($lohnkontoTotals ?? null) ? $lohnkontoTotals : [
    'gross_total' => 0.0, 'lst' => 0.0, 'sv_an' => 0.0, 'net_pay' => 0.0, 'count' => 0,
];
$staffOptions = is_array($lohnkontoStaffOptions ?? null) ? $lohnkontoStaffOptions : [];
$money = static fn (float $v): string => number_format($v, 2, ',', '.');
$baseQs = 'page=lohn-lohnkonto&year=' . $year
    . ($includeDrafts ? '&drafts=1' : '')
    . ($contactId > 0 ? '&contact_id=' . $contactId : '');
?>
<div class="dg-wrap dg-lohn-lohnkonto">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Lohnkonto</h1>
      <p class="dg-lead">
        Chronologische Abrechnungsübersicht je Mitarbeiter und Kalenderjahr.
        Final-PDF aus gesperrten Läufen; fremde <code>payroll_slip</code>-Uploads bleiben am Kontakt.
        Kein SEPA und keine FiBu.
      </p>
    </div>
    <div class="dg-toolbar">
      <a class="dg-button" href="/app?page=lohn-monatslauf">Monatslauf</a>
      <?php if ($contactId > 0) : ?>
        <a class="dg-button" href="/app?page=kontakte&amp;action=view&amp;id=<?= $contactId ?>">Kontakt</a>
        <a class="dg-button dg-button--primary" href="/app?<?= View::escape($baseQs) ?>&amp;download=csv">Kontroll-CSV</a>
      <?php endif; ?>
    </div>
  </header>

  <section class="dg-panel">
    <form method="get" action="/app" class="dg-form dg-btn-row">
      <input type="hidden" name="page" value="lohn-lohnkonto">
      <label class="dg-field">
        <span>Mitarbeiter</span>
        <select name="contact_id" required>
          <option value="">— wählen —</option>
          <?php foreach ($staffOptions as $opt) : ?>
            <option value="<?= (int) $opt['id'] ?>"<?= $contactId === (int) $opt['id'] ? ' selected' : '' ?>>
              <?= View::escape((string) $opt['label']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field">
        <span>Jahr</span>
        <input type="number" name="year" min="2000" max="2100" value="<?= $year ?>" required>
      </label>
      <label class="dg-field dg-field--checkbox">
        <span>
          <input type="checkbox" name="drafts" value="1"<?= $includeDrafts ? ' checked' : '' ?>>
          Entwürfe (calculated) anzeigen
        </span>
      </label>
      <button type="submit" class="dg-button dg-button--primary">Anzeigen</button>
    </form>
  </section>

  <?php if ($contactId <= 0) : ?>
    <p class="dg-lead">Bitte Mitarbeiter und Jahr wählen.</p>
  <?php else : ?>
    <section class="dg-panel dg-panel--wide">
      <h2><?= View::escape($contactLabel !== '' ? $contactLabel : ('Mitarbeiter #' . $contactId)) ?> — <?= $year ?></h2>
      <?php if ($rows === []) : ?>
        <p class="dg-lead">
          Keine Einträge<?= $includeDrafts ? '' : ' (verbindlich approved/locked)' ?>.
          <?php if (!$includeDrafts) : ?>
            Optional Entwürfe aktivieren oder zuerst im <a href="/app?page=lohn-monatslauf">Monatslauf</a> freigeben.
          <?php endif; ?>
        </p>
      <?php else : ?>
        <div class="dg-table-wrap">
          <table class="dg-table dg-table--compact">
            <thead>
              <tr>
                <th>Monat</th>
                <th>Run</th>
                <th>Art</th>
                <th>Status</th>
                <th class="dg-table__num">Brutto</th>
                <th class="dg-table__num">LSt</th>
                <th class="dg-table__num">SV-AN</th>
                <th class="dg-table__num">Netto</th>
                <th>PDF</th>
                <th>Hinweis</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $row) : ?>
                <?php
                  $rid = (int) ($row['run_id'] ?? 0);
                  $month = (int) ($row['period_month'] ?? 0);
                  $docId = (int) ($row['document_id'] ?? 0);
                  $flags = [];
                  if (!empty($row['is_superseded'])) {
                      $flags[] = 'ersetzt';
                  }
                  if (!empty($row['is_draft_run'])) {
                      $flags[] = 'Entwurf';
                  }
                  $monthHref = sprintf(
                      '/app?page=lohn-monatslauf&month=%04d-%02d&run=%d',
                      (int) ($row['period_year'] ?? $year),
                      $month,
                      $rid
                  );
                ?>
                <tr<?= !empty($row['is_superseded']) ? ' class="is-muted"' : '' ?>>
                  <td><?= sprintf('%02d/%d', $month, $year) ?></td>
                  <td><a href="<?= View::escape($monthHref) ?>">#<?= $rid ?></a></td>
                  <td><?= View::escape((string) ($row['kind_label'] ?? '')) ?></td>
                  <td><?= View::escape((string) ($row['run_status'] ?? '') . ' / ' . (string) ($row['emp_status'] ?? '')) ?></td>
                  <td class="dg-table__num"><?= View::escape($money((float) ($row['gross_total'] ?? 0))) ?></td>
                  <td class="dg-table__num"><?= View::escape($money((float) ($row['lst'] ?? 0))) ?></td>
                  <td class="dg-table__num"><?= View::escape($money((float) ($row['sv_an'] ?? 0))) ?></td>
                  <td class="dg-table__num"><?= View::escape($money((float) ($row['net_pay'] ?? 0))) ?></td>
                  <td>
                    <?php if ($docId > 0) : ?>
                      <a href="/app?page=lohn-lohnkonto&amp;download=pdf&amp;doc=<?= $docId ?>">PDF</a>
                    <?php else : ?>
                      —
                    <?php endif; ?>
                  </td>
                  <td><?= View::escape(implode(', ', $flags)) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <th colspan="4">Jahres-Summe (ohne ersetzt, <?= (int) ($totals['count'] ?? 0) ?> Läufe)</th>
                <th class="dg-table__num"><?= View::escape($money((float) ($totals['gross_total'] ?? 0))) ?></th>
                <th class="dg-table__num"><?= View::escape($money((float) ($totals['lst'] ?? 0))) ?></th>
                <th class="dg-table__num"><?= View::escape($money((float) ($totals['sv_an'] ?? 0))) ?></th>
                <th class="dg-table__num"><?= View::escape($money((float) ($totals['net_pay'] ?? 0))) ?></th>
                <th colspan="2"></th>
              </tr>
            </tfoot>
          </table>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>
