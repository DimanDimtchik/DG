<?php
/**
 * @var string $oposDirection
 * @var string $oposSearch
 * @var array{items: list<array<string, mixed>>, totals: array{receivable: float, payable: float}} $oposData
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$direction = (string) ($oposDirection ?? '');
$search = (string) ($oposSearch ?? '');
$data = $oposData ?? ['items' => [], 'totals' => ['receivable' => 0.0, 'payable' => 0.0]];
$items = $data['items'] ?? [];
$totals = $data['totals'] ?? ['receivable' => 0.0, 'payable' => 0.0];
$fmt = static fn (float $v): string => number_format($v, 2, ',', '.');
?>
<div class="dg-wrap dg-buchhaltung-opos">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Offene Posten (OPOS)</h1>
      <p class="dg-lead">Forderungen und Verbindlichkeiten mit Personenkonten — DATEV-konform bei verknüpftem Kontakt.</p>
    </div>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Datenbank nicht verbunden.</div>
  <?php endif; ?>

  <div class="dg-panel dg-opos-summary">
    <p>
      <strong>Forderungen:</strong> <?= $fmt((float) ($totals['receivable'] ?? 0)) ?> €
      &middot;
      <strong>Verbindlichkeiten:</strong> <?= $fmt((float) ($totals['payable'] ?? 0)) ?> €
    </p>
  </div>

  <form class="dg-panel dg-ledger-filters" method="get" action="/app">
    <input type="hidden" name="page" value="buchhaltung-opos">
    <div class="dg-form-grid dg-form-grid--compact">
      <label class="dg-field">
        <span>Richtung</span>
        <select name="direction">
          <option value="">Alle</option>
          <option value="receivable"<?= $direction === 'receivable' ? ' selected' : '' ?>>Forderungen</option>
          <option value="payable"<?= $direction === 'payable' ? ' selected' : '' ?>>Verbindlichkeiten</option>
        </select>
      </label>
      <label class="dg-field dg-field--wide">
        <span>Suche</span>
        <input type="search" name="s" value="<?= View::escape($search) ?>" placeholder="Rechnungsnr., Kontakt, Personenkonto …">
      </label>
      <div class="dg-field dg-field--actions">
        <button type="submit" class="dg-button dg-button--primary">Filtern</button>
      </div>
    </div>
  </form>

  <section class="dg-panel">
    <?php if ($items === []) : ?>
      <p class="dg-muted">Keine offenen Posten gefunden.</p>
    <?php else : ?>
      <div class="dg-table-wrap">
        <table class="dg-table">
          <thead>
            <tr>
              <th>Datum</th>
              <th>Fällig</th>
              <th>Art</th>
              <th>Rechnung</th>
              <th>Kontakt</th>
              <th>Personenkonto</th>
              <th class="dg-table__num">Offen</th>
              <th>Mahnung</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item) : ?>
              <tr>
                <td><?= View::escape((string) ($item['voucher_date'] ?? '')) ?></td>
                <td>
                  <?php
                    $due = (string) ($item['payment_due_date'] ?? '');
                    $overdue = (int) ($item['days_overdue'] ?? 0);
                  ?>
                  <?= $due !== '' ? View::escape($due) : '—' ?>
                  <?php if ($overdue > 0) : ?>
                    <span class="dg-badge dg-badge--pending"><?= $overdue ?> T. überf.</span>
                  <?php endif; ?>
                </td>
                <td><?= ($item['direction'] ?? '') === 'receivable' ? 'Forderung' : 'Verbindlichkeit' ?></td>
                <td><?= View::escape((string) ($item['invoice_number'] ?? '—')) ?></td>
                <td><?= View::escape((string) ($item['contact_label'] ?? '')) ?></td>
                <td><?= View::escape((string) ($item['person_account'] ?? '')) ?: '—' ?></td>
                <td class="dg-table__num"><strong><?= $fmt((float) ($item['open_amount'] ?? 0)) ?> €</strong></td>
                <td><?= (int) ($item['dunning_level'] ?? 0) > 0 ? (int) $item['dunning_level'] : '—' ?></td>
                <td>
                  <a href="/app?page=buchhaltung-beleg-form&action=edit&id=<?= (int) ($item['voucher_id'] ?? 0) ?>">Beleg</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>
