<?php
/**
 * @var AccountingPeriodFilter $susaPeriod
 * @var list<int> $susaYears
 * @var array<string, mixed> $susaReport
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$period = $susaPeriod ?? AccountingPeriodFilter::fromRequest([]);
$years = $susaYears ?? [(int) date('Y')];
$report = $susaReport ?? ['accounts' => [], 'totals' => [], 'period_label' => ''];
$fmt = static fn (float $v): string => number_format($v, 2, ',', '.');
$printUrl = $period->appendToUrl('/app?page=buchhaltung-susa&download=print');
?>
<div class="dg-wrap dg-buchhaltung-susa">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">SuSa</h1>
      <p class="dg-lead">Summen- und Saldenliste — <?= View::escape((string) ($report['period_label'] ?? '')) ?></p>
    </div>
    <div class="dg-page-header__actions">
      <a class="dg-button" href="<?= View::escape($printUrl) ?>" target="_blank" rel="noopener">Drucken / PDF</a>
    </div>
  </header>

  <form class="dg-panel dg-ledger-filters" method="get" action="/app">
    <input type="hidden" name="page" value="buchhaltung-susa">
    <?php View::render('partials/accounting-period-filter', [
        'period' => $period,
        'pageSlug' => 'buchhaltung-susa',
        'years' => $years,
    ]); ?>
    <div class="dg-field dg-field--actions">
      <button type="submit" class="dg-button dg-button--primary">Anzeigen</button>
    </div>
  </form>

  <section class="dg-panel">
    <div class="dg-table-wrap">
      <table class="dg-table">
        <thead>
          <tr>
            <th>Konto</th>
            <th>Bezeichnung</th>
            <th class="dg-table__num">Anfang</th>
            <th class="dg-table__num">Soll</th>
            <th class="dg-table__num">Haben</th>
            <th class="dg-table__num">Saldo</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($report['accounts'] ?? [] as $row) : ?>
            <tr>
              <td><?= View::escape((string) ($row['account_number'] ?? '')) ?></td>
              <td><?= View::escape((string) ($row['name'] ?? '')) ?></td>
              <td class="dg-table__num"><?= $fmt((float) ($row['opening'] ?? 0)) ?> €</td>
              <td class="dg-table__num"><?= $fmt((float) ($row['debit'] ?? 0)) ?> €</td>
              <td class="dg-table__num"><?= $fmt((float) ($row['credit'] ?? 0)) ?> €</td>
              <td class="dg-table__num"><?= $fmt((float) ($row['balance'] ?? 0)) ?> €</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
