<?php
/**
 * @var AccountingPeriodFilter $cashPeriod
 * @var int $cashYear
 * @var list<int> $cashYears
 * @var list<array<string, mixed>> $cashEntries
 * @var array{in: float, out: float, balance: float} $cashTotals
 * @var list<array<string, mixed>> $cashClosings
 * @var string $cashCloseDate
 * @var array{opening: float, expected: float, entries_in: float, entries_out: float} $cashDaySummary
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$period = $cashPeriod ?? AccountingPeriodFilter::fromRequest([]);
$year = (int) ($cashYear ?? $period->year);
$years = $cashYears ?? [(int) date('Y')];
$entries = $cashEntries ?? [];
$totals = $cashTotals ?? ['in' => 0.0, 'out' => 0.0, 'balance' => 0.0];
$closings = $cashClosings ?? [];
$closeDate = (string) ($cashCloseDate ?? date('Y-m-d'));
$daySummary = $cashDaySummary ?? ['opening' => 0.0, 'expected' => 0.0, 'entries_in' => 0.0, 'entries_out' => 0.0];
$fmt = static fn (float $v): string => number_format($v, 2, ',', '.');
$printUrl = $period->appendToUrl('/app?page=buchhaltung-kassenbuch&download=print');
$csrf = Csrf::token();
?>
<div class="dg-wrap dg-buchhaltung-kassenbuch">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Kassenbuch</h1>
      <p class="dg-lead">Bar-Ein- und Ausgänge · <?= View::escape($period->label) ?></p>
    </div>
    <div class="dg-page-header__actions">
      <a class="dg-button" href="<?= View::escape($printUrl) ?>" target="_blank" rel="noopener">Drucken / PDF</a>
    </div>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Datenbank nicht verbunden.</div>
  <?php endif; ?>

  <form class="dg-panel dg-ledger-filters" method="get" action="/app">
    <input type="hidden" name="page" value="buchhaltung-kassenbuch">
    <?php View::render('partials/accounting-period-filter', [
        'period' => $period,
        'pageSlug' => 'buchhaltung-kassenbuch',
        'years' => $years,
    ]); ?>
    <div class="dg-field dg-field--actions">
      <button type="submit" class="dg-button dg-button--primary">Anzeigen</button>
    </div>
  </form>

  <div class="dg-panel dg-opos-summary">
    <p>
      <strong>Ein:</strong> <?= $fmt((float) ($totals['in'] ?? 0)) ?> €
      &middot;
      <strong>Aus:</strong> <?= $fmt((float) ($totals['out'] ?? 0)) ?> €
      &middot;
      <strong>Saldo (Zeitraum):</strong> <?= $fmt((float) ($totals['balance'] ?? 0)) ?> €
    </p>
  </div>

  <?php if ($canEdit && $dbConnected) : ?>
  <section class="dg-panel">
    <h2 class="dg-subsection-title">Tagesabschluss</h2>
    <form method="post" action="/app?page=buchhaltung-kassenbuch" class="dg-form-grid dg-form-grid--compact">
      <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
      <input type="hidden" name="cash_day_close" value="1">
      <input type="hidden" name="year" value="<?= (int) $year ?>">
      <label class="dg-field">
        <span>Datum</span>
        <input type="date" name="closing_date" value="<?= View::escape($closeDate) ?>" required>
      </label>
      <label class="dg-field">
        <span>Soll-Bestand</span>
        <input type="text" value="<?= View::escape($fmt((float) ($daySummary['expected'] ?? 0))) ?> €" readonly>
      </label>
      <label class="dg-field">
        <span>Gezählter Bestand</span>
        <input type="number" name="counted_balance" step="0.01" min="0" required>
      </label>
      <label class="dg-field dg-field--wide">
        <span>Notiz</span>
        <input type="text" name="closing_note" maxlength="500" placeholder="Optional">
      </label>
      <div class="dg-field dg-field--actions">
        <button type="submit" class="dg-button dg-button--primary">Tag abschließen</button>
      </div>
    </form>
    <?php if ($closings !== []) : ?>
      <table class="dg-table" style="margin-top:1rem;">
        <thead><tr><th>Datum</th><th>Soll</th><th>Ist</th><th>Differenz</th><th>Notiz</th></tr></thead>
        <tbody>
          <?php foreach ($closings as $closing) : ?>
            <tr>
              <td><?= View::escape((string) ($closing['closing_date'] ?? '')) ?></td>
              <td class="dg-table__num"><?= $fmt((float) ($closing['expected_balance'] ?? 0)) ?> €</td>
              <td class="dg-table__num"><?= $fmt((float) ($closing['counted_balance'] ?? 0)) ?> €</td>
              <td class="dg-table__num"><?= $fmt((float) ($closing['difference'] ?? 0)) ?> €</td>
              <td><?= View::escape((string) ($closing['note'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <section class="dg-panel">
    <?php if ($entries === []) : ?>
      <p class="dg-muted">Keine Kassenbuch-Einträge für <?= View::escape($period->label) ?>.</p>
    <?php else : ?>
      <div class="dg-table-wrap">
        <table class="dg-table">
          <thead>
            <tr>
              <th>Datum</th>
              <th>Art</th>
              <th>Konto</th>
              <th>Text</th>
              <th class="dg-table__num">Betrag</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($entries as $entry) : ?>
              <tr>
                <td><?= View::escape((string) ($entry['entry_date'] ?? '')) ?></td>
                <td><?= View::escape((string) ($entry['side_label'] ?? '')) ?></td>
                <td><?= View::escape((string) ($entry['account_number'] ?? '')) ?></td>
                <td><?= View::escape((string) ($entry['description'] ?? '')) ?></td>
                <td class="dg-table__num"><?= View::escape((string) ($entry['amount_display'] ?? '')) ?></td>
                <td>
                  <?php if (!empty($entry['voucher_id'])) : ?>
                    <a href="/app?page=buchhaltung-beleg-form&action=edit&id=<?= (int) $entry['voucher_id'] ?>">Beleg</a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>
