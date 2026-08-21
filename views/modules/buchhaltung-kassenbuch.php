<?php
/**
 * @var int $cashYear
 * @var list<int> $cashYears
 * @var list<array<string, mixed>> $cashEntries
 * @var array{in: float, out: float, balance: float} $cashTotals
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$year = (int) ($cashYear ?? (int) date('Y'));
$years = $cashYears ?? [(int) date('Y')];
$entries = $cashEntries ?? [];
$totals = $cashTotals ?? ['in' => 0.0, 'out' => 0.0, 'balance' => 0.0];
$fmt = static fn (float $v): string => number_format($v, 2, ',', '.');
?>
<div class="dg-wrap dg-buchhaltung-kassenbuch">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Kassenbuch</h1>
      <p class="dg-lead">Bar-Ein- und Ausgänge aus Belegen mit Zahlungsstatus „Per Kasse bezahlt“.</p>
    </div>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Datenbank nicht verbunden.</div>
  <?php endif; ?>

  <form class="dg-panel dg-ledger-filters" method="get" action="/app">
    <input type="hidden" name="page" value="buchhaltung-kassenbuch">
    <div class="dg-form-grid dg-form-grid--compact">
      <label class="dg-field">
        <span>Jahr</span>
        <select name="year">
          <?php foreach ($years as $y) : ?>
            <option value="<?= (int) $y ?>"<?= $year === (int) $y ? ' selected' : '' ?>><?= (int) $y ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="dg-field dg-field--actions">
        <button type="submit" class="dg-button dg-button--primary">Anzeigen</button>
      </div>
    </div>
  </form>

  <div class="dg-panel dg-opos-summary">
    <p>
      <strong>Ein:</strong> <?= $fmt((float) ($totals['in'] ?? 0)) ?> €
      &middot;
      <strong>Aus:</strong> <?= $fmt((float) ($totals['out'] ?? 0)) ?> €
      &middot;
      <strong>Saldo:</strong> <?= $fmt((float) ($totals['balance'] ?? 0)) ?> €
    </p>
  </div>

  <section class="dg-panel">
    <?php if ($entries === []) : ?>
      <p class="dg-muted">Keine Kassenbuch-Einträge für <?= (int) $year ?>.</p>
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
