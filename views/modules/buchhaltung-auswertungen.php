<?php
/**
 * @var int $reportYear
 * @var list<int> $reportYears
 * @var string $reportType
 * @var array<string, mixed> $balanceSheet
 * @var array<string, mixed> $profitLoss
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$year = (int) ($reportYear ?? (int) date('Y'));
$period = $reportPeriod ?? AccountingPeriodFilter::fromRequest(['year' => $year]);
$years = $reportYears ?? [(int) date('Y')];
$type = (string) ($reportType ?? 'guv');
$bs = $balanceSheet ?? ['aktiva' => [], 'passiva' => [], 'totals' => ['aktiva' => 0.0, 'passiva' => 0.0], 'result' => 0.0];
$pl = $profitLoss ?? ['income' => [], 'expense' => [], 'totals' => ['income' => 0.0, 'expense' => 0.0, 'result' => 0.0]];
$fmt = static fn (float $v): string => number_format($v, 2, ',', '.');
?>
<div class="dg-wrap dg-buchhaltung-auswertungen">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Bilanz &amp; GuV</h1>
      <p class="dg-lead">Auswertungen aus dem Buchungsjournal — <?= View::escape($period->label) ?></p>
    </div>
    <div class="dg-page-header__actions">
      <a class="dg-button" href="<?= View::escape($period->appendToUrl('/app?page=buchhaltung-auswertungen&type=' . rawurlencode($type) . '&download=print')) ?>" target="_blank" rel="noopener">Drucken / PDF</a>
      <a class="dg-button" href="/app?page=buchhaltung-jahresabschluss&year=<?= (int) $year ?>">Jahresabschluss</a>
    </div>
  </header>

  <form class="dg-panel dg-ledger-filters" method="get" action="/app">
    <input type="hidden" name="page" value="buchhaltung-auswertungen">
    <div class="dg-form-grid dg-form-grid--compact">
      <?php View::render('partials/accounting-period-filter', [
          'period' => $period,
          'pageSlug' => 'buchhaltung-auswertungen',
          'years' => $years,
          'extraHidden' => ['type' => $type],
      ]); ?>
      <label class="dg-field">
        <span>Report</span>
        <select name="type">
          <option value="guv"<?= $type === 'guv' ? ' selected' : '' ?>>GuV</option>
          <option value="bilanz"<?= $type === 'bilanz' ? ' selected' : '' ?>>Bilanz</option>
        </select>
      </label>
      <div class="dg-field dg-field--actions">
        <button type="submit" class="dg-button dg-button--primary">Anzeigen</button>
      </div>
    </div>
  </form>

  <?php if ($type === 'bilanz') : ?>
    <section class="dg-panel">
      <h2 class="dg-subsection-title">Bilanz</h2>
      <div class="dg-form-grid dg-form-grid--2col">
        <div>
          <h3>Aktiva</h3>
          <table class="dg-table">
            <thead><tr><th>Konto</th><th>Bezeichnung</th><th class="dg-table__num">Saldo</th></tr></thead>
            <tbody>
              <?php foreach ($bs['aktiva'] as $row) : ?>
                <tr>
                  <td><?= View::escape((string) ($row['account_number'] ?? '')) ?></td>
                  <td><?= View::escape((string) ($row['name'] ?? '')) ?></td>
                  <td class="dg-table__num"><?= $fmt((float) ($row['balance'] ?? 0)) ?> €</td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <p><strong>Summe Aktiva:</strong> <?= $fmt((float) ($bs['totals']['aktiva'] ?? 0)) ?> €</p>
        </div>
        <div>
          <h3>Passiva</h3>
          <table class="dg-table">
            <thead><tr><th>Konto</th><th>Bezeichnung</th><th class="dg-table__num">Saldo</th></tr></thead>
            <tbody>
              <?php foreach ($bs['passiva'] as $row) : ?>
                <tr>
                  <td><?= View::escape((string) ($row['account_number'] ?? '')) ?></td>
                  <td><?= View::escape((string) ($row['name'] ?? '')) ?></td>
                  <td class="dg-table__num"><?= $fmt(-(float) ($row['balance'] ?? 0)) ?> €</td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <p><strong>Summe Passiva:</strong> <?= $fmt((float) ($bs['totals']['passiva'] ?? 0)) ?> €</p>
          <p class="dg-muted">Jahresüberschuss (GuV): <?= $fmt((float) ($bs['result'] ?? 0)) ?> €</p>
        </div>
      </div>
    </section>
  <?php else : ?>
    <section class="dg-panel">
      <h2 class="dg-subsection-title">Gewinn- und Verlustrechnung</h2>
      <table class="dg-table">
        <thead><tr><th>Konto</th><th>Bezeichnung</th><th class="dg-table__num">Betrag</th></tr></thead>
        <tbody>
          <tr><td colspan="3"><strong>Erträge</strong></td></tr>
          <?php foreach ($pl['income'] as $row) : ?>
            <tr>
              <td><?= View::escape((string) ($row['account_number'] ?? '')) ?></td>
              <td><?= View::escape((string) ($row['name'] ?? '')) ?></td>
              <td class="dg-table__num"><?= $fmt((float) ($row['pl_amount'] ?? 0)) ?> €</td>
            </tr>
          <?php endforeach; ?>
          <tr><td colspan="2"><strong>Summe Erträge</strong></td><td class="dg-table__num"><strong><?= $fmt((float) ($pl['totals']['income'] ?? 0)) ?> €</strong></td></tr>
          <tr><td colspan="3"><strong>Aufwendungen</strong></td></tr>
          <?php foreach ($pl['expense'] as $row) : ?>
            <tr>
              <td><?= View::escape((string) ($row['account_number'] ?? '')) ?></td>
              <td><?= View::escape((string) ($row['name'] ?? '')) ?></td>
              <td class="dg-table__num"><?= $fmt((float) ($row['pl_amount'] ?? 0)) ?> €</td>
            </tr>
          <?php endforeach; ?>
          <tr><td colspan="2"><strong>Summe Aufwendungen</strong></td><td class="dg-table__num"><strong><?= $fmt((float) ($pl['totals']['expense'] ?? 0)) ?> €</strong></td></tr>
          <tr><td colspan="2"><strong>Jahresüberschuss / -fehlbetrag</strong></td><td class="dg-table__num"><strong><?= $fmt((float) ($pl['totals']['result'] ?? 0)) ?> €</strong></td></tr>
        </tbody>
      </table>
    </section>
  <?php endif; ?>
</div>
