<?php
/**
 * @var AccountingPeriodFilter $bwaPeriod
 * @var list<int> $bwaYears
 * @var array<string, mixed> $bwaReport
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$period = $bwaPeriod ?? AccountingPeriodFilter::fromRequest([]);
$years = $bwaYears ?? [(int) date('Y')];
$report = $bwaReport ?? ['lines' => [], 'totals' => ['revenue' => 0.0, 'costs' => 0.0, 'result' => 0.0], 'period_label' => ''];
$fmt = static fn (float $v): string => number_format($v, 2, ',', '.');
$printUrl = $period->appendToUrl('/app?page=buchhaltung-bwa&download=print');
?>
<div class="dg-wrap dg-buchhaltung-bwa">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">BWA</h1>
      <p class="dg-lead">Betriebswirtschaftliche Auswertung — <?= View::escape((string) ($report['period_label'] ?? '')) ?></p>
    </div>
    <div class="dg-page-header__actions">
      <a class="dg-button" href="<?= View::escape($printUrl) ?>" target="_blank" rel="noopener">Drucken / PDF</a>
    </div>
  </header>

  <form class="dg-panel dg-ledger-filters" method="get" action="/app">
    <input type="hidden" name="page" value="buchhaltung-bwa">
    <?php View::render('partials/accounting-period-filter', [
        'period' => $period,
        'pageSlug' => 'buchhaltung-bwa',
        'years' => $years,
    ]); ?>
    <div class="dg-field dg-field--actions">
      <button type="submit" class="dg-button dg-button--primary">Anzeigen</button>
    </div>
  </form>

  <section class="dg-panel">
    <table class="dg-table">
      <thead><tr><th>Position</th><th class="dg-table__num">Betrag</th></tr></thead>
      <tbody>
        <?php foreach ($report['lines'] ?? [] as $line) : ?>
          <tr<?= ($line['key'] ?? '') === 'result' ? ' class="dg-table__total"' : '' ?>>
            <td><?= str_repeat('· ', (int) ($line['level'] ?? 0)) . View::escape((string) ($line['label'] ?? '')) ?></td>
            <td class="dg-table__num"><?= $fmt((float) ($line['amount'] ?? 0)) ?> €</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</div>
