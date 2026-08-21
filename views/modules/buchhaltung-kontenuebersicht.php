<?php
/**
 * Kontenübersicht (Salden je Konto) und Kontoauszug (Einzelbuchungen).
 *
 * @var int $ledgerYear
 * @var list<int> $ledgerYears
 * @var string $ledgerSearch
 * @var bool $ledgerShowEmpty
 * @var array{accounts: list<array<string, mixed>>, totals: array<string, float>} $ledgerOverview
 * @var string $ledgerAccount
 * @var array<string, mixed> $ledgerStatement
 * @var string $ledgerYearStatus
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$year = (int) ($ledgerYear ?? (int) date('Y'));
$period = $ledgerPeriod ?? AccountingPeriodFilter::fromRequest(['year' => $year]);
$years = $ledgerYears ?? [(int) date('Y')];
$search = (string) ($ledgerSearch ?? '');
$showEmpty = (bool) ($ledgerShowEmpty ?? false);
$selectedAccount = (string) ($ledgerAccount ?? '');
$yearStatus = (string) ($ledgerYearStatus ?? 'open');
$baseUrl = '/app?page=buchhaltung-kontenuebersicht';

$fmt = static fn (float $v): string => number_format($v, 2, ',', '.');
$saldo = static function (float $signed) use ($fmt): string {
    if (abs($signed) < 0.005) {
        return '0,00';
    }
    return $fmt(abs($signed)) . ' ' . ($signed > 0 ? 'S' : 'H');
};
$accountLink = static fn (string $acc): string => $baseUrl . '&year=' . $year . '&account=' . rawurlencode($acc);
?>
<div class="dg-wrap dg-buchhaltung-konten-uebersicht">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Kontenübersicht</h1>
      <p class="dg-lead">Salden und Buchungen je Konto — <?= View::escape($period->label) ?>
        <?php if ($yearStatus === 'closed') : ?>
          <span class="dg-badge dg-badge--muted">abgeschlossen</span>
        <?php endif; ?>
      </p>
    </div>
    <div class="dg-page-header__actions">
      <a class="dg-button" href="/app?page=buchhaltung-jahresabschluss">Jahresabschluss</a>
    </div>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Datenbank nicht verbunden — Auswertung nicht verfügbar.</div>
  <?php endif; ?>

  <form class="dg-panel dg-ledger-filters" method="get" action="/app">
    <input type="hidden" name="page" value="buchhaltung-kontenuebersicht">
    <div class="dg-form-grid dg-form-grid--compact">
      <?php View::render('partials/accounting-period-filter', [
          'period' => $period,
          'pageSlug' => 'buchhaltung-kontenuebersicht',
          'years' => $years,
          'extraHidden' => $selectedAccount !== '' ? ['account' => $selectedAccount] : [],
      ]); ?>
      <?php if ($selectedAccount === '') : ?>
        <label class="dg-field dg-field--wide">
          <span>Suche</span>
          <input type="search" name="s" value="<?= View::escape($search) ?>" placeholder="Kontonummer oder Bezeichnung …">
        </label>
        <label class="dg-field dg-field--check">
          <span>Leere Konten</span>
          <input type="checkbox" name="empty" value="1"<?= $showEmpty ? ' checked' : '' ?>>
        </label>
      <?php endif; ?>
      <div class="dg-field dg-field--actions">
        <button type="submit" class="dg-button dg-button--primary">Anzeigen</button>
      </div>
    </div>
  </form>

  <?php if ($selectedAccount !== '') :
      $st = $ledgerStatement ?? ['account' => ['account_number' => $selectedAccount, 'name' => ''], 'opening' => 0.0, 'rows' => [], 'closing' => 0.0, 'debit' => 0.0, 'credit' => 0.0];
      $acc = $st['account'];
  ?>
    <!-- Kontoauszug -->
    <div class="dg-toolbar">
      <a class="dg-button" href="<?= View::escape($period->appendToUrl($baseUrl)) ?>">&laquo; Zur Übersicht</a>
      <a class="dg-button" href="<?= View::escape($period->appendToUrl($baseUrl . '&account=' . rawurlencode($selectedAccount) . '&download=print')) ?>" target="_blank" rel="noopener">Drucken / PDF</a>
    </div>
    <section class="dg-panel dg-ledger-statement">
      <h2 class="dg-subsection-title">
        Konto <?= View::escape((string) $acc['account_number']) ?>
        <?php if ((string) $acc['name'] !== '') : ?> — <?= View::escape((string) $acc['name']) ?><?php endif; ?>
      </h2>
      <div class="dg-table-wrap">
        <table class="dg-table dg-ledger-statement__table">
          <thead>
            <tr>
              <th>Datum</th>
              <th>Beleg</th>
              <th>Gegenkonto</th>
              <th>Buchungstext</th>
              <th class="dg-table__num">Soll</th>
              <th class="dg-table__num">Haben</th>
              <th class="dg-table__num">Saldo</th>
            </tr>
          </thead>
          <tbody>
            <tr class="dg-ledger-statement__carry">
              <td colspan="6">Saldenvortrag <?= (int) $year ?></td>
              <td class="dg-table__num"><?= View::escape($saldo((float) $st['opening'])) ?></td>
            </tr>
            <?php if ($st['rows'] === []) : ?>
              <tr><td colspan="7" class="dg-table__empty">Keine Buchungen in <?= (int) $year ?>.</td></tr>
            <?php else : ?>
              <?php foreach ($st['rows'] as $row) : ?>
                <tr>
                  <td><?= View::escape(date('d.m.Y', strtotime((string) $row['date']))) ?></td>
                  <td>
                    <?php if ($row['voucher_id'] !== null) : ?>
                      <a href="/app?page=buchhaltung-beleg-form&amp;action=edit&amp;id=<?= (int) $row['voucher_id'] ?>"><?= View::escape((string) ($row['invoice_number'] ?? '') ?: ('#' . (int) $row['voucher_id'])) ?></a>
                    <?php else : ?>
                      —
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ((string) $row['contra_account'] !== '') : ?>
                      <span class="dg-mono"><?= View::escape((string) $row['contra_account']) ?></span>
                      <?php if ((string) $row['contra_name'] !== '') : ?><br><small class="dg-muted"><?= View::escape((string) $row['contra_name']) ?></small><?php endif; ?>
                    <?php else : ?>—<?php endif; ?>
                  </td>
                  <td><?= View::escape((string) $row['description'] ?: '—') ?></td>
                  <td class="dg-table__num"><?= $row['debit'] > 0 ? View::escape($fmt((float) $row['debit'])) : '' ?></td>
                  <td class="dg-table__num"><?= $row['credit'] > 0 ? View::escape($fmt((float) $row['credit'])) : '' ?></td>
                  <td class="dg-table__num"><?= View::escape($saldo((float) $row['balance'])) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="4">Summen / Schlusssaldo</td>
              <td class="dg-table__num"><?= View::escape($fmt((float) $st['debit'])) ?></td>
              <td class="dg-table__num"><?= View::escape($fmt((float) $st['credit'])) ?></td>
              <td class="dg-table__num"><strong><?= View::escape($saldo((float) $st['closing'])) ?></strong></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </section>
  <?php else :
      $ov = $ledgerOverview ?? ['accounts' => [], 'totals' => ['debit' => 0.0, 'credit' => 0.0, 'opening' => 0.0, 'balance' => 0.0]];
  ?>
    <!-- Kontenübersicht -->
    <div class="dg-table-wrap">
      <table class="dg-table dg-ledger-overview__table">
        <thead>
          <tr>
            <th>Konto</th>
            <th>Bezeichnung</th>
            <th class="dg-table__num">Saldenvortrag</th>
            <th class="dg-table__num">Umsatz Soll</th>
            <th class="dg-table__num">Umsatz Haben</th>
            <th class="dg-table__num">Saldo</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($ov['accounts'] === []) : ?>
            <tr><td colspan="6" class="dg-table__empty">Keine Buchungen im Geschäftsjahr <?= (int) $year ?>.</td></tr>
          <?php else : ?>
            <?php foreach ($ov['accounts'] as $a) : ?>
              <tr>
                <td><a class="dg-mono" href="<?= View::escape($accountLink((string) $a['account_number'])) ?>"><?= View::escape((string) $a['account_number']) ?></a></td>
                <td><?= View::escape((string) $a['name'] ?: '—') ?></td>
                <td class="dg-table__num"><?= abs((float) $a['opening']) > 0.005 ? View::escape($saldo((float) $a['opening'])) : '' ?></td>
                <td class="dg-table__num"><?= (float) $a['debit'] > 0 ? View::escape($fmt((float) $a['debit'])) : '' ?></td>
                <td class="dg-table__num"><?= (float) $a['credit'] > 0 ? View::escape($fmt((float) $a['credit'])) : '' ?></td>
                <td class="dg-table__num"><?= View::escape($saldo((float) $a['balance'])) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="2">Summen (<?= count($ov['accounts']) ?> Konten)</td>
            <td class="dg-table__num"></td>
            <td class="dg-table__num"><?= View::escape($fmt((float) $ov['totals']['debit'])) ?></td>
            <td class="dg-table__num"><?= View::escape($fmt((float) $ov['totals']['credit'])) ?></td>
            <td class="dg-table__num"></td>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>
