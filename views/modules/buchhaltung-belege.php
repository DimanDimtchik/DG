<?php
/**
 * @var array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int} $voucherList
 * @var string $voucherSearch
 * @var int $voucherYear
 * @var string $voucherTypeFilter
 * @var string $voucherDocumentKindFilter
 * @var string $voucherDocumentStatusFilter
 * @var string $voucherDraftFilter
 * @var int $voucherDraftCount
 * @var array<string, mixed> $voucherImportPending
 * @var list<int> $voucherYears
 * @var bool $dbConnected
 * @var bool $canEdit
 * @var array{type: string, message: string}|null $flash
 */
$list = $voucherList ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 25, 'total_pages' => 1];
$search = $voucherSearch ?? '';
$year = (int) ($voucherYear ?? (int) date('Y'));
$period = $voucherPeriod ?? AccountingPeriodFilter::fromRequest(['year' => $year]);
$typeFilter = $voucherTypeFilter ?? '';
$documentKindFilter = $voucherDocumentKindFilter ?? '';
$documentStatusFilter = $voucherDocumentStatusFilter ?? '';
$draftFilter = $voucherDraftFilter ?? '';
$draftCount = (int) ($voucherDraftCount ?? 0);
$importPending = is_array($voucherImportPending ?? null) ? $voucherImportPending : [];
$years = $voucherYears ?? [(int) date('Y')];
$typeOptions = VoucherRepository::voucherTypeOptions();
$documentKindOptions = VoucherDocumentKind::options();
$documentStatusOptions = VoucherDocumentStatus::options();
$showDocumentListFilters = $typeFilter === '' || VoucherDocumentKind::voucherTypeSupportsDocumentKind($typeFilter);
$baseUrl = '/app?page=buchhaltung-belege';
$hasActiveFilters = $search !== '' || $typeFilter !== '' || $documentKindFilter !== '' || $documentStatusFilter !== '' || $draftFilter !== '';
?>
<div class="dg-wrap dg-buchhaltung-belege">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Belege</h1>
      <p class="dg-lead">Belegerfassung mit Steuerfeldern — <?= (int) $list['total'] ?> Einträge · <?= View::escape($period->label) ?></p>
    </div>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Datenbank nicht verbunden. Belege können erst nach Konfiguration unter
      <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Einstellungen → Datenbank</a> erfasst werden.
    </div>
  <?php endif; ?>

  <?php if ($draftCount > 0) : ?>
    <div class="dg-flash dg-flash--info">
      <?= (int) $draftCount ?> Beleg-Entwurf<?= $draftCount === 1 ? '' : 'e' ?>
      <?php if (($importPending['status'] ?? '') !== '' && ($importPending['status'] ?? '') !== 'todo') : ?>
        aus dem Installationsimport
      <?php endif; ?>
      — bitte Kontakt, Betrag und Konto ergänzen.
      <a href="<?= View::escape($baseUrl . '&year=' . $year . '&draft=1') ?>">Nur Entwürfe anzeigen</a>
    </div>
  <?php endif; ?>

  <form class="dg-buchhaltung-belege__filters dg-panel" method="get" action="/app">
    <input type="hidden" name="page" value="buchhaltung-belege">
    <div class="dg-form-grid dg-form-grid--compact">
      <?php View::render('partials/accounting-period-filter', [
          'period' => $period,
          'pageSlug' => 'buchhaltung-belege',
          'years' => $years,
      ]); ?>
      <label class="dg-field">
        <span>Belegart (EÜR)</span>
        <select name="type" id="dg-voucher-type-filter">
          <option value="">Alle Arten</option>
          <?php foreach ($typeOptions as $value => $label) : ?>
            <option value="<?= View::escape($value) ?>"<?= $typeFilter === $value ? ' selected' : '' ?>><?= View::escape($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field" id="dg-voucher-doc-kind-filter-field"<?= $showDocumentListFilters ? '' : ' hidden' ?>>
        <span>Dokument</span>
        <select name="doc_kind" id="dg-voucher-doc-kind-filter">
          <option value="">Alle Dokumente</option>
          <?php foreach ($documentKindOptions as $value => $label) : ?>
            <option value="<?= View::escape($value) ?>"<?= $documentKindFilter === $value ? ' selected' : '' ?>><?= View::escape($label) ?></option>
          <?php endforeach; ?>
        </select>
        <small class="dg-field-hint">Angebot, Rechnung usw. — nur bei Belegart Einnahmen.</small>
      </label>
      <label class="dg-field" id="dg-voucher-doc-status-filter-field"<?= $showDocumentListFilters ? '' : ' hidden' ?>>
        <span>Dokumentstatus</span>
        <select name="doc_status" id="dg-voucher-doc-status-filter">
          <option value="">Alle Status</option>
          <?php foreach ($documentStatusOptions as $value => $label) : ?>
            <option value="<?= View::escape($value) ?>"<?= $documentStatusFilter === $value ? ' selected' : '' ?>><?= View::escape($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field">
        <span>Vollständigkeit</span>
        <select name="draft" id="dg-voucher-draft-filter">
          <option value=""<?= $draftFilter === '' ? ' selected' : '' ?>>Alle</option>
          <option value="1"<?= $draftFilter === '1' ? ' selected' : '' ?>>Nur unvollständige Entwürfe</option>
          <option value="0"<?= $draftFilter === '0' ? ' selected' : '' ?>>Ohne Entwürfe</option>
        </select>
      </label>
      <label class="dg-field dg-field--wide">
        <span>Suche</span>
        <input type="search" name="s" value="<?= View::escape($search) ?>" placeholder="Lieferant, Rechnungsnr., Buchungstext, Konto …">
      </label>
      <div class="dg-field dg-field--actions">
        <button type="submit" class="dg-button dg-button--primary">Filtern</button>
        <?php if ($hasActiveFilters) : ?>
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
          <th>Dokument</th>
          <th>Status</th>
          <th>Lieferant / Kontakt</th>
          <th>Nummer</th>
          <th>Buchungstext</th>
          <th class="dg-table__num">Brutto</th>
          <th class="dg-table__num">MwSt.</th>
          <th>Konto</th>
          <th>Zahlung</th>
          <th class="dg-buchhaltung-belege__file-col" title="Belegdatei"><span class="dg-visually-hidden">Datei</span></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($list['items'] === []) : ?>
          <tr><td colspan="12" class="dg-table__empty">Keine Belege gefunden.</td></tr>
        <?php else : ?>
          <?php foreach ($list['items'] as $voucher) : ?>
            <tr<?= !empty($voucher['is_draft']) ? ' class="dg-buchhaltung-belege__row--draft"' : '' ?>>
              <td><?= View::escape(date('d.m.Y', strtotime((string) $voucher['voucher_date']))) ?></td>
              <td>
                <?= View::escape((string) ($voucher['type_label'] ?? '')) ?>
                <?php if (!empty($voucher['is_draft'])) : ?>
                  <span class="dg-badge dg-badge--muted">unvollständig</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ((string) ($voucher['document_status_label'] ?? '') !== '') : ?>
                  <span class="dg-badge <?= View::escape((string) ($voucher['document_status_badge_class'] ?? 'dg-badge--muted')) ?>">
                    <?= View::escape((string) $voucher['document_status_label']) ?>
                  </span>
                <?php else : ?>
                  <span class="dg-muted">—</span>
                <?php endif; ?>
              </td>
              <td><?= View::escape((string) ($voucher['supplier_display'] ?? '—')) ?></td>
              <td><?= View::escape((string) ($voucher['invoice_number'] ?? '') ?: '—') ?></td>
              <td><?= View::escape((string) ($voucher['description'] ?? '') ?: '—') ?></td>
              <td class="dg-table__num"><?= View::escape(VoucherRepository::formatMoney((float) ($voucher['gross_amount'] ?? 0))) ?> €</td>
              <td class="dg-table__num dg-table__tax-breakdown">
                <?php
                $taxLines = $voucher['tax_display_lines'] ?? [];
                if ($taxLines === []) :
                ?>
                  —
                <?php else : ?>
                  <?php foreach ($taxLines as $taxLine) : ?>
                    <span class="dg-table__tax-breakdown-line"><?= View::escape($taxLine) ?></span>
                  <?php endforeach; ?>
                <?php endif; ?>
              </td>
              <td>
                <span class="dg-buchhaltung-belege__account"><?= View::escape((string) ($voucher['account_number'] ?? '')) ?></span>
                <?php if ((string) ($voucher['account_name'] ?? '') !== '') : ?>
                  <br><small class="dg-muted"><?= View::escape((string) $voucher['account_name']) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <span class="dg-badge <?= View::escape((string) ($voucher['payment_badge_class'] ?? 'dg-badge--muted')) ?>">
                  <?= View::escape((string) ($voucher['payment_label'] ?? '')) ?>
                </span>
              </td>
              <td class="dg-buchhaltung-belege__file-col">
                <?php $fileCount = (int) (($voucherFileCounts ?? [])[(int) ($voucher['id'] ?? 0)] ?? 0); ?>
                <?php if ($fileCount > 0) : ?>
                  <a class="dg-file-badge" href="/app?page=buchhaltung-beleg-form&amp;action=edit&amp;id=<?= (int) $voucher['id'] ?>" title="<?= $fileCount ?> Datei(en) angehängt">
                    <?php View::render('partials/icon', ['name' => 'paperclip']); ?>
                    <?php if ($fileCount > 1) : ?><span class="dg-file-badge__count"><?= $fileCount ?></span><?php endif; ?>
                  </a>
                <?php else : ?>
                  <span class="dg-muted">—</span>
                <?php endif; ?>
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
            . ($documentKindFilter !== '' ? '&doc_kind=' . rawurlencode($documentKindFilter) : '')
            . ($documentStatusFilter !== '' ? '&doc_status=' . rawurlencode($documentStatusFilter) : '')
            . ($draftFilter !== '' ? '&draft=' . rawurlencode($draftFilter) : '')
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
