<?php
/**
 * @var array<string, mixed> $voucherBoard
 * @var array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int, gross_sum?: float} $voucherList
 * @var string $voucherSearch
 * @var int $voucherYear
 * @var AccountingPeriodFilter $voucherPeriod
 * @var int $voucherDraftCount
 * @var array<string, mixed> $voucherImportPending
 * @var list<int> $voucherYears
 * @var array<int, int> $voucherFileCounts
 * @var bool $dbConnected
 * @var bool $canEdit
 * @var array{type: string, message: string}|null $flash
 */
$board = is_array($voucherBoard ?? null) ? $voucherBoard : [];
$list = $voucherList ?? ($board['list'] ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 25, 'total_pages' => 1, 'gross_sum' => 0.0]);
$search = (string) ($board['search'] ?? ($voucherSearch ?? ''));
$year = (int) ($voucherYear ?? (int) date('Y'));
$period = $voucherPeriod ?? AccountingPeriodFilter::fromRequest(['year' => $year]);
$draftCount = (int) ($voucherDraftCount ?? 0);
$importPending = is_array($voucherImportPending ?? null) ? $voucherImportPending : [];
$years = $voucherYears ?? [(int) date('Y')];
$sections = is_array($board['sections'] ?? null) ? $board['sections'] : [];
$chips = is_array($board['chips'] ?? null) ? $board['chips'] : [];
$invoiceKindChips = is_array($board['invoice_kind_chips'] ?? null) ? $board['invoice_kind_chips'] : [];
$payChips = is_array($board['pay_chips'] ?? null) ? $board['pay_chips'] : [];
$contacts = is_array($board['contacts'] ?? null) ? $board['contacts'] : [];
$section = (string) ($board['section'] ?? VoucherBelegeBoard::SECTION_ACTION);
$contactId = (int) ($board['contact_id'] ?? 0);
$amountMin = (string) ($board['amount_min'] ?? '');
$amountMax = (string) ($board['amount_max'] ?? '');
$actionableOnly = !empty($board['actionable_only']);
$actionTotal = (int) ($board['action_total'] ?? 0);
$baseUrl = '/app?page=buchhaltung-belege';
$hasActiveFilters = $search !== '' || $contactId > 0 || $amountMin !== '' || $amountMax !== '' || $actionableOnly
    || !$period->isFullYear() || $period->month !== null;
$canEdit = (bool) ($canEdit ?? false);
$fileCounts = is_array($voucherFileCounts ?? null) ? $voucherFileCounts : [];

$boardUrl = static function (array $overrides = []) use ($board, $period): string {
    $base = [
        'date_from' => $period->dateFrom,
        'date_to' => $period->dateTo,
        'year' => $period->year,
        'month' => $period->month,
        'date_from_raw' => (!$period->isFullYear() || $period->month !== null) ? $period->dateFrom : '',
        'date_to_raw' => (!$period->isFullYear() || $period->month !== null) ? $period->dateTo : '',
        'search' => (string) ($board['search'] ?? ''),
        'contact_id' => (int) ($board['contact_id'] ?? 0),
        'amount_min' => ($board['amount_min'] ?? '') !== '' ? $board['amount_min'] : null,
        'amount_max' => ($board['amount_max'] ?? '') !== '' ? $board['amount_max'] : null,
        '_year' => $period->year,
        '_month' => $period->month,
        '_date_from_raw' => (!$period->isFullYear() || $period->month !== null) ? $period->dateFrom : '',
        '_date_to_raw' => (!$period->isFullYear() || $period->month !== null) ? $period->dateTo : '',
    ];

    return VoucherBelegeBoard::url($base, array_merge([
        'section' => (string) ($board['section'] ?? VoucherBelegeBoard::SECTION_ACTION),
        'status' => (string) ($board['status'] ?? ''),
        'invoice_kind' => (string) ($board['invoice_kind'] ?? ''),
        'pay' => (string) ($board['pay'] ?? ''),
        'actionable_only' => !empty($board['actionable_only']) ? '1' : '',
    ], $overrides));
};
?>
<div class="dg-wrap dg-buchhaltung-belege">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Belege</h1>
      <p class="dg-lead">
        Belegkette nach Dokumentart —
        <?= (int) ($list['total'] ?? 0) ?> in dieser Ansicht · <?= View::escape($period->label) ?>
        <?php if ($actionTotal > 0) : ?>
          · <a href="<?= View::escape($boardUrl(['section' => VoucherBelegeBoard::SECTION_ACTION, 'status' => '', 'page' => 1])) ?>"><?= (int) $actionTotal ?> handlungsbedürftig</a>
        <?php endif; ?>
      </p>
    </div>
    <?php if ($canEdit && $dbConnected) : ?>
      <a class="dg-button dg-button--primary" href="/app?page=buchhaltung-beleg-form&amp;action=new">Neuer Beleg</a>
    <?php endif; ?>
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
      <a href="<?= View::escape($boardUrl(['section' => VoucherBelegeBoard::SECTION_ACTION, 'status' => 'drafts', 'page' => 1])) ?>">Entwürfe im Handlungsbedarf</a>
    </div>
  <?php endif; ?>

  <form class="dg-buchhaltung-belege__filters dg-panel" method="get" action="/app" id="dg-belege-filter-form">
    <input type="hidden" name="page" value="buchhaltung-belege">
    <input type="hidden" name="section" value="<?= View::escape($section) ?>">
    <?php if ((string) ($board['status'] ?? '') !== '') : ?>
      <input type="hidden" name="status" value="<?= View::escape((string) $board['status']) ?>">
    <?php endif; ?>
    <?php if ((string) ($board['invoice_kind'] ?? '') !== '') : ?>
      <input type="hidden" name="invoice_kind" value="<?= View::escape((string) $board['invoice_kind']) ?>">
    <?php endif; ?>
    <?php if ((string) ($board['pay'] ?? '') !== '') : ?>
      <input type="hidden" name="pay" value="<?= View::escape((string) $board['pay']) ?>">
    <?php endif; ?>
    <div class="dg-form-grid dg-form-grid--compact">
      <?php View::render('partials/accounting-period-filter', [
          'period' => $period,
          'pageSlug' => 'buchhaltung-belege',
          'years' => $years,
      ]); ?>
      <label class="dg-field">
        <span>Kunde / Kontakt</span>
        <select name="contact_id" id="dg-voucher-contact-filter">
          <option value="0">Alle Kontakte</option>
          <?php foreach ($contacts as $c) : ?>
            <option value="<?= (int) $c['id'] ?>"<?= $contactId === (int) $c['id'] ? ' selected' : '' ?>><?= View::escape((string) $c['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field">
        <span>Betrag von</span>
        <input type="number" name="amount_min" step="0.01" min="0" value="<?= View::escape($amountMin) ?>" placeholder="0,00">
      </label>
      <label class="dg-field">
        <span>Betrag bis</span>
        <input type="number" name="amount_max" step="0.01" min="0" value="<?= View::escape($amountMax) ?>" placeholder="…">
      </label>
      <label class="dg-field dg-field--wide">
        <span>Suche</span>
        <input type="search" name="s" value="<?= View::escape($search) ?>" placeholder="Lieferant, Rechnungsnr., Buchungstext, Konto …">
      </label>
      <label class="dg-field dg-field--check">
        <span class="dg-visually-hidden">Nur Handlungsbedarf</span>
        <label class="dg-checkbox">
          <input type="checkbox" name="actionable" value="1"<?= $actionableOnly ? ' checked' : '' ?>>
          Nur handlungsbedürftig
        </label>
      </label>
      <div class="dg-field dg-field--actions">
        <button type="submit" class="dg-button dg-button--primary">Filtern</button>
        <?php if ($hasActiveFilters) : ?>
          <a class="dg-button" href="<?= View::escape($baseUrl . '&year=' . $year . '&section=' . rawurlencode($section)) ?>">Zurücksetzen</a>
        <?php endif; ?>
      </div>
    </div>
  </form>

  <div class="dg-belege-board dg-dept-accordion" id="dg-belege-board">
    <?php foreach ($sections as $sec) : ?>
      <?php
        $isOpen = !empty($sec['is_open']);
        $secId = (string) ($sec['id'] ?? '');
        $secCount = (int) ($sec['count'] ?? 0);
        $secGross = (float) ($sec['gross_sum'] ?? 0);
      ?>
      <section class="dg-dept-card dg-belege-board__section<?= $isOpen ? ' is-open' : '' ?>" data-belege-section="<?= View::escape($secId) ?>">
        <header class="dg-dept-accordion__header">
          <a class="dg-dept-accordion__trigger" href="<?= View::escape((string) ($sec['url'] ?? '#')) ?>" aria-expanded="<?= $isOpen ? 'true' : 'false' ?>">
            <span class="dg-dept-accordion__icon" aria-hidden="true"></span>
            <span class="dg-dept-accordion__label">
              <strong class="dg-dept-accordion__title">
                <?= View::escape((string) ($sec['label'] ?? '')) ?>
                <span class="dg-belege-board__count<?= $secId === VoucherBelegeBoard::SECTION_ACTION && $secCount > 0 ? ' dg-belege-board__count--alert' : '' ?>"><?= $secCount ?></span>
              </strong>
              <span class="dg-dept-accordion__meta">
                <?= View::escape((string) ($sec['meta'] ?? '')) ?>
                <?php if ($secCount > 0) : ?>
                  · Summe <?= View::escape(VoucherRepository::formatMoney($secGross)) ?> €
                <?php endif; ?>
              </span>
            </span>
          </a>
        </header>

        <?php if ($isOpen) : ?>
          <div class="dg-dept-accordion__panel dg-belege-board__panel">
            <?php if ($chips !== []) : ?>
              <div class="dg-belege-board__chips" role="tablist" aria-label="Statusfilter">
                <?php foreach ($chips as $chip) : ?>
                  <a class="dg-belege-chip<?= !empty($chip['active']) ? ' is-active' : '' ?>"
                     href="<?= View::escape((string) ($chip['url'] ?? '#')) ?>"
                     role="tab"
                     aria-selected="<?= !empty($chip['active']) ? 'true' : 'false' ?>">
                    <?= View::escape((string) ($chip['label'] ?? '')) ?>
                    <span class="dg-belege-chip__count"><?= (int) ($chip['count'] ?? 0) ?></span>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if ($invoiceKindChips !== []) : ?>
              <div class="dg-belege-board__chips dg-belege-board__chips--secondary" role="tablist" aria-label="Rechnungsart">
                <?php foreach ($invoiceKindChips as $chip) : ?>
                  <a class="dg-belege-chip dg-belege-chip--soft<?= !empty($chip['active']) ? ' is-active' : '' ?>"
                     href="<?= View::escape((string) ($chip['url'] ?? '#')) ?>">
                    <?= View::escape((string) ($chip['label'] ?? '')) ?>
                    <span class="dg-belege-chip__count"><?= (int) ($chip['count'] ?? 0) ?></span>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if ($payChips !== []) : ?>
              <div class="dg-belege-board__chips dg-belege-board__chips--secondary" role="tablist" aria-label="Zahlungsstatus">
                <?php foreach ($payChips as $chip) : ?>
                  <a class="dg-belege-chip dg-belege-chip--soft<?= !empty($chip['active']) ? ' is-active' : '' ?>"
                     href="<?= View::escape((string) ($chip['url'] ?? '#')) ?>">
                    <?= View::escape((string) ($chip['label'] ?? '')) ?>
                    <span class="dg-belege-chip__count"><?= (int) ($chip['count'] ?? 0) ?></span>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <div class="dg-belege-board__sum">
              <?= (int) ($list['total'] ?? 0) ?> Beleg<?= (int) ($list['total'] ?? 0) === 1 ? '' : 'e' ?>
              · Brutto <?= View::escape(VoucherRepository::formatMoney((float) ($list['gross_sum'] ?? 0))) ?> €
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
                  <?php if (($list['items'] ?? []) === []) : ?>
                    <tr><td colspan="12" class="dg-table__empty">Keine Belege in diesem Abschnitt.</td></tr>
                  <?php else : ?>
                    <?php foreach ($list['items'] as $voucher) : ?>
                      <?php
                        $voucherId = (int) ($voucher['id'] ?? 0);
                        $parentId = (int) ($voucher['parent_voucher_id'] ?? 0);
                        $hasChain = $parentId > 0 || VoucherDocumentKind::sanitize((string) ($voucher['document_kind'] ?? '')) !== '';
                      ?>
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
                          <?php $fileCount = (int) ($fileCounts[$voucherId] ?? 0); ?>
                          <?php if ($fileCount > 0) : ?>
                            <a class="dg-file-badge" href="/app?page=buchhaltung-beleg-form&amp;action=edit&amp;id=<?= $voucherId ?>" title="<?= $fileCount ?> Datei(en) angehängt">
                              <?php View::render('partials/icon', ['name' => 'paperclip']); ?>
                              <?php if ($fileCount > 1) : ?><span class="dg-file-badge__count"><?= $fileCount ?></span><?php endif; ?>
                            </a>
                          <?php else : ?>
                            <span class="dg-muted">—</span>
                          <?php endif; ?>
                        </td>
                        <td class="dg-table__actions">
                          <a href="/app?page=buchhaltung-beleg-form&amp;action=edit&amp;id=<?= $voucherId ?>"><?= $canEdit ? 'Bearbeiten' : 'Anzeigen' ?></a>
                          <?php if ($hasChain && $voucherId > 0) : ?>
                            <a class="dg-belege-board__chain-link" href="/app?page=buchhaltung-beleg-form&amp;action=edit&amp;id=<?= $voucherId ?>#dg-voucher-chain" title="Belegkette">Kette</a>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

            <?php if (($list['total_pages'] ?? 1) > 1) : ?>
              <nav class="dg-pagination" aria-label="Seiten">
                <?php if (($list['page'] ?? 1) > 1) : ?>
                  <a href="<?= View::escape($boardUrl(['page' => (int) $list['page'] - 1])) ?>">&laquo; Zurück</a>
                <?php endif; ?>
                <span>Seite <?= (int) ($list['page'] ?? 1) ?> von <?= (int) ($list['total_pages'] ?? 1) ?></span>
                <?php if (($list['page'] ?? 1) < ($list['total_pages'] ?? 1)) : ?>
                  <a href="<?= View::escape($boardUrl(['page' => (int) $list['page'] + 1])) ?>">Weiter &raquo;</a>
                <?php endif; ?>
              </nav>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  </div>
</div>
