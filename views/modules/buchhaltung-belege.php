<?php
/**
 * @var array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int} $voucherList
 * @var string $voucherSearch
 * @var int $voucherYear
 * @var string $voucherTypeFilter
 * @var list<int> $voucherYears
 * @var bool $dbConnected
 * @var bool $canEdit
 * @var array{type: string, message: string}|null $flash
 */
$list = $voucherList ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 25, 'total_pages' => 1];
$search = $voucherSearch ?? '';
$year = (int) ($voucherYear ?? (int) date('Y'));
$typeFilter = $voucherTypeFilter ?? '';
$years = $voucherYears ?? [(int) date('Y')];
$typeOptions = VoucherRepository::voucherTypeOptions();
$baseUrl = '/app?page=buchhaltung-belege';
?>
<div class="dg-wrap dg-buchhaltung-belege">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Belege</h1>
      <p class="dg-lead">Belegerfassung mit Steuerfeldern — <?= (int) $list['total'] ?> Einträge<?= $year > 0 ? ' in ' . $year : '' ?></p>
    </div>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Datenbank nicht verbunden. Belege können erst nach Konfiguration unter
      <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Einstellungen → Datenbank</a> erfasst werden.
    </div>
  <?php endif; ?>

  <form class="dg-buchhaltung-belege__filters dg-panel" method="get" action="/app">
    <input type="hidden" name="page" value="buchhaltung-belege">
    <div class="dg-form-grid dg-form-grid--compact">
      <label class="dg-field">
        <span>Jahr</span>
        <select name="year" id="dg-voucher-year">
          <?php foreach ($years as $y) : ?>
            <option value="<?= (int) $y ?>"<?= $year === (int) $y ? ' selected' : '' ?>><?= (int) $y ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field">
        <span>Belegart</span>
        <select name="type" id="dg-voucher-type-filter">
          <option value="">Alle Arten</option>
          <?php foreach ($typeOptions as $value => $label) : ?>
            <option value="<?= View::escape($value) ?>"<?= $typeFilter === $value ? ' selected' : '' ?>><?= View::escape($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field dg-field--wide">
        <span>Suche</span>
        <input type="search" name="s" value="<?= View::escape($search) ?>" placeholder="Lieferant, Rechnungsnr., Buchungstext, Konto …">
      </label>
      <div class="dg-field dg-field--actions">
        <span class="dg-field__spacer" aria-hidden="true">&nbsp;</span>
        <button type="submit" class="dg-button dg-button--primary">Filtern</button>
        <?php if ($search !== '' || $typeFilter !== '') : ?>
          <a class="dg-button" href="<?= View::escape($baseUrl . '&year=' . $year) ?>">Zurücksetzen</a>
        <?php endif; ?>
      </div>
    </div>
  </form>

  <div class="dg-toolbar dg-buchhaltung-belege__toolbar">
    <?php if ($canEdit && $dbConnected) : ?>
      <a class="dg-button dg-button--primary" href="/app?page=buchhaltung-beleg-form&amp;action=new">Neuer Beleg</a>
    <?php endif; ?>
  </div>

  <div class="dg-table-wrap">
    <table class="dg-table dg-buchhaltung-belege__table">
      <thead>
        <tr>
          <th>Datum</th>
          <th>Art</th>
          <th>Lieferant / Kontakt</th>
          <th>Rechnungsnr.</th>
          <th>Buchungstext</th>
          <th class="dg-table__num">Brutto</th>
          <th class="dg-table__num">MwSt.</th>
          <th>Konto</th>
          <th>Zahlung</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($list['items'] === []) : ?>
          <tr><td colspan="10" class="dg-table__empty">Keine Belege gefunden.</td></tr>
        <?php else : ?>
          <?php foreach ($list['items'] as $voucher) : ?>
            <tr>
              <td><?= View::escape(date('d.m.Y', strtotime((string) $voucher['voucher_date']))) ?></td>
              <td><?= View::escape((string) ($voucher['type_label'] ?? '')) ?></td>
              <td><?= View::escape((string) ($voucher['supplier_display'] ?? '—')) ?></td>
              <td><?= View::escape((string) ($voucher['invoice_number'] ?? '') ?: '—') ?></td>
              <td><?= View::escape((string) ($voucher['description'] ?? '') ?: '—') ?></td>
              <td class="dg-table__num"><?= View::escape(VoucherRepository::formatMoney((float) ($voucher['gross_amount'] ?? 0))) ?> €</td>
              <td class="dg-table__num"><?= (int) ($voucher['tax_rate'] ?? 0) ?> % · <?= View::escape(VoucherRepository::formatMoney((float) ($voucher['tax_amount'] ?? 0))) ?> €</td>
              <td>
                <span class="dg-buchhaltung-belege__account"><?= View::escape((string) ($voucher['account_number'] ?? '')) ?></span>
                <?php if ((string) ($voucher['account_name'] ?? '') !== '') : ?>
                  <br><small class="dg-muted"><?= View::escape((string) $voucher['account_name']) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <span class="dg-badge<?= ($voucher['payment_status'] ?? '') === 'paid' ? ' dg-badge--ok' : ' dg-badge--muted' ?>">
                  <?= View::escape((string) ($voucher['payment_label'] ?? '')) ?>
                </span>
              </td>
              <td class="dg-table__actions">
                <?php if ($canEdit) : ?>
                  <a href="/app?page=buchhaltung-beleg-form&amp;action=edit&amp;id=<?= (int) $voucher['id'] ?>">Bearbeiten</a>
                <?php else : ?>
                  <a href="/app?page=buchhaltung-beleg-form&amp;action=edit&amp;id=<?= (int) $voucher['id'] ?>">Anzeigen</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($list['total_pages'] > 1) : ?>
    <nav class="dg-pagination" aria-label="Seiten">
      <?php
        $pageQuery = $baseUrl . '&year=' . $year
            . ($typeFilter !== '' ? '&type=' . rawurlencode($typeFilter) : '')
            . ($search !== '' ? '&s=' . rawurlencode($search) : '');
      ?>
      <?php if ($list['page'] > 1) : ?>
        <a href="<?= View::escape($pageQuery . '&paged=' . ($list['page'] - 1)) ?>">&laquo; Zurück</a>
      <?php endif; ?>
      <span>Seite <?= (int) $list['page'] ?> von <?= (int) $list['total_pages'] ?></span>
      <?php if ($list['page'] < $list['total_pages']) : ?>
        <a href="<?= View::escape($pageQuery . '&paged=' . ($list['page'] + 1)) ?>">Weiter &raquo;</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
</div>
