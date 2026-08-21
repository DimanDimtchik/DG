<?php
/**
 * @var array<string, mixed> $form
 * @var int|null $voucherId
 * @var string|null $formError
 * @var bool $canEdit
 * @var array{skr_type: string, account_digits: int} $chartOfAccountsConfig
 * @var array{type: string, message: string}|null $flash
 */
$isEdit = ($voucherId ?? 0) > 0;
$readOnly = !($canEdit ?? false);
$typeOptions = VoucherRepository::voucherTypeOptions();
$paymentOptions = VoucherRepository::paymentStatusOptions();
$taxRates = VoucherTaxKeys::allowedTaxRates();
$reverseChargeType = VoucherReverseCharge::sanitizeType((string) ($form['reverse_charge_type'] ?? ''));
$reverseCharge = VoucherReverseCharge::isActive($reverseChargeType);
$reverseChargeOptions = VoucherReverseCharge::typeOptions();
/** @var list<array<string, mixed>> $ustvaPositions */
$ustvaPositions = is_array($form['ustva_positions'] ?? null) ? $form['ustva_positions'] : [];
/** @var list<array<string, mixed>> $systemLines */
$systemLines = is_array($form['system_lines'] ?? null) ? $form['system_lines'] : [];
$skrLabel = ChartOfAccountsSettings::skrTypeOptions()[$chartOfAccountsConfig['skr_type'] ?? 'skr03'] ?? 'SKR03';
$selectedType = VoucherRepository::normalizeVoucherType((string) ($form['voucher_type'] ?? 'expense'));
$typeHint = VoucherRepository::voucherTypeHint($selectedType);
/** @var list<array<string, mixed>> $lineRows */
$lineRows = is_array($form['lines'] ?? null) ? $form['lines'] : [];
/** @var list<array<string, mixed>> $itemRows */
$itemRows = is_array($form['items'] ?? null) ? $form['items'] : [];
$showInvoiceItems = VoucherIncomePositions::usesInvoiceItems($selectedType);
if ($lineRows === [] && (string) ($form['account_number'] ?? '') !== '') {
    $lineRows[] = [
        'account_number' => (string) ($form['account_number'] ?? ''),
        'account_name' => (string) ($form['account_name'] ?? ''),
        'gross_amount' => (string) ($form['gross_amount'] ?? ''),
        'tax_rate' => (string) ($form['tax_rate'] ?? '19'),
    ];
}
if ($lineRows === []) {
    $lineRows[] = [
        'account_number' => '',
        'account_name' => '',
        'gross_amount' => '',
        'tax_rate' => (string) ($form['tax_rate'] ?? '19'),
    ];
}
if ($itemRows === []) {
    $itemRows[] = [
        'article_id' => '',
        'catalog_kind' => '',
        'article_number' => '',
        'title' => '',
        'area_id' => '',
        'area_name' => '',
        'unit' => 'Stück',
        'quantity' => '1',
        'unit_price_gross' => '',
        'gross_amount' => '',
        'tax_rate' => '19',
        'tax_type' => 'ust19',
    ];
}
/** @var list<array<string, mixed>> $calendarAreas */
$calendarAreas = Database::isConfigured() ? CalendarStaffRepository::getAreas(true) : [];
$taxBreakdown = VoucherTaxKeys::taxBreakdownFromLines($lineRows, $reverseCharge);
$hasBookingAmounts = array_sum($taxBreakdown) > 0
    || (string) ($form['gross_amount'] ?? '') !== ''
    || array_reduce($lineRows, static fn (bool $carry, array $line): bool => $carry || (float) str_replace(',', '.', (string) ($line['gross_amount'] ?? '0')) > 0, false);
$paymentStatus = VoucherPaymentStatus::sanitize((string) ($form['payment_status'] ?? VoucherPaymentStatus::OPEN));
$paymentStatusHint = VoucherPaymentStatus::hint($paymentStatus);
$invoiceNumberRangeType = VoucherRepository::numberRangeTypeForVoucher($selectedType);
$autoInvoiceNumber = $invoiceNumberRangeType !== null;
$invoiceNumberRangeLabel = $autoInvoiceNumber
    ? (NumberRangeSettings::documentTypes()[$invoiceNumberRangeType] ?? 'Rechnung')
    : '';
$invoiceNumberValue = (string) ($form['invoice_number'] ?? '');
if ($autoInvoiceNumber && !$isEdit) {
    try {
        $invoiceNumberValue = VoucherRepository::peekInvoiceNumber($selectedType);
    } catch (Throwable) {
        $invoiceNumberValue = '';
    }
}
$invoiceNumberRequired = VoucherRepository::isExpenseType($selectedType) && !$autoInvoiceNumber && !$readOnly;
$newContactUrl = '/app?page=kontakte&action=new&return_to=' . rawurlencode('/app?page=buchhaltung-beleg-form&action=new');
$arapEnabled = !empty($form['arap_enabled']);
$arapCurrentPercent = max(0, min(100, (int) ($form['arap_current_year_percent'] ?? 100)));
$arapNextPercent = max(0, min(100, (int) ($form['arap_next_year_percent'] ?? (100 - $arapCurrentPercent))));
if ($arapEnabled && $arapCurrentPercent + $arapNextPercent !== 100) {
    $arapNextPercent = max(0, 100 - $arapCurrentPercent);
}
$fiscalYear = (int) date('Y', strtotime((string) ($form['voucher_date'] ?? date('Y-m-d'))));
$nextFiscalYear = $fiscalYear + 1;
$arapTypeLabel = VoucherAccrual::labelForType($selectedType);
$arapTypeHint = VoucherAccrual::hintForType($selectedType);
$showArapSection = VoucherAccrual::showAccrualUi($selectedType, $arapEnabled, $readOnly);
$transferSupported = $isEdit
    && $paymentStatus === VoucherPaymentStatus::OPEN
    && in_array($selectedType, ['expense', 'expense_reduction'], true);
$existingTransfer = ($isEdit && Database::isConfigured()) ? BankTransferRepository::findByVoucher((int) $voucherId) : null;
/** @var list<array<string, mixed>> $ledgerPostings */
$ledgerPostings = is_array($ledgerPostings ?? null) ? $ledgerPostings : [];
?>
<div class="dg-wrap dg-buchhaltung-beleg-form">
  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <?php
        $backHref = '/app?page=buchhaltung-belege';
        View::partial('partials/back-nav', [
            'href' => $backHref,
            'label' => 'Zurück zur Belegliste',
        ]);
      ?>
      <h1 class="dg-page-title"><?= $isEdit ? ($readOnly ? 'Beleg anzeigen' : 'Beleg bearbeiten') : 'Neuer Beleg' ?></h1>
      <p class="dg-lead">Belegerfassung — Kontenrahmen <?= View::escape($skrLabel) ?></p>
    </div>
    <?php if ($transferSupported && !$readOnly) : ?>
      <div class="dg-page-header__actions">
        <?php if ($existingTransfer !== null) : ?>
          <a class="dg-button" href="/app?page=buchhaltung-ueberweisungen&open=<?= (int) $existingTransfer['id'] ?>#transfer-<?= (int) $existingTransfer['id'] ?>">Überweisung ansehen</a>
        <?php else : ?>
          <form method="post" action="/app?page=buchhaltung-beleg-form" class="dg-inline-form">
            <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
            <input type="hidden" name="id" value="<?= (int) $voucherId ?>">
            <button type="submit" name="voucher_transfer_prepare" value="1" class="dg-button dg-button--primary">Überweisung vorbereiten</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </header>

  <?php View::render('partials/flash', compact('flash')); ?>

  <?php if ($formError ?? '') : ?>
    <div class="dg-flash dg-flash--error"><?= View::escape($formError) ?></div>
  <?php endif; ?>

  <form class="dg-form dg-panel dg-buchhaltung-beleg-form__form" method="post" action="/app?page=buchhaltung-beleg-form" id="dg-voucher-form" enctype="multipart/form-data"<?= $readOnly ? ' data-readonly="1"' : '' ?>>
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
    <input type="hidden" name="voucher_save" value="1">
    <input type="hidden" name="contact_id" id="dg-voucher-contact-id" value="<?= View::escape($form['contact_id'] ?? '') ?>">
    <input type="hidden" name="draft_voucher_id" id="dg-voucher-draft-id" value="<?= (int) ($voucherId ?? 0) ?>">
    <?php if ($isEdit) : ?><input type="hidden" name="id" value="<?= (int) $voucherId ?>"><?php endif; ?>

    <?php if (!$readOnly) : ?>
    <section class="dg-form-section dg-voucher-files">
      <h2 class="dg-subsection-title">Belegdatei (PDF / Bild / E-Rechnung)</h2>
      <div class="dg-voucher-files__dropzone" id="dg-voucher-file-dropzone">
        <input type="file" name="voucher_files[]" id="dg-voucher-file-input"
               accept="<?= View::escape(VoucherFileStorage::acceptAttribute()) ?>" multiple>
        <p class="dg-field-hint">
          PDF, JPG, PNG, WEBP oder E-Rechnung (ZUGFeRD/XRechnung). Die Datei wird beim Hochladen sofort am Beleg gespeichert.
          Bei E-Rechnungen und digitalen PDFs werden die Felder automatisch vorgeschlagen.
        </p>
      </div>
      <div class="dg-voucher-attachments" id="dg-voucher-attachments-live" hidden></div>
      <div class="dg-voucher-extract" id="dg-voucher-extract" hidden>
        <div class="dg-voucher-extract__status" id="dg-voucher-extract-status"></div>
        <div class="dg-voucher-extract__layout">
          <div class="dg-voucher-extract__preview-wrap">
            <div class="dg-voucher-extract__preview" id="dg-voucher-extract-preview" hidden></div>
          </div>
          <div class="dg-voucher-extract__data">
            <div class="dg-voucher-extract__fields" id="dg-voucher-extract-fields"></div>
            <div class="dg-voucher-extract__actions" id="dg-voucher-extract-actions" hidden>
              <label class="dg-voucher-extract__contact-sync" id="dg-voucher-extract-contact-sync-wrap" hidden>
                <input type="checkbox" id="dg-voucher-extract-contact-sync" checked>
                Erkannte Stammdaten auch beim Kontakt speichern (nur leere Felder)
              </label>
              <button type="button" class="dg-button dg-button--primary" id="dg-voucher-extract-apply">Vorschläge übernehmen</button>
              <button type="button" class="dg-button dg-button--ghost" id="dg-voucher-extract-dismiss">Verwerfen</button>
            </div>
          </div>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <section class="dg-form-section">
      <h2 class="dg-subsection-title">Beleg</h2>
      <div class="dg-form-grid">
        <label class="dg-field dg-field--wide">
          <span>Belegart *</span>
          <select name="voucher_type" id="dg-voucher-type" required<?= $readOnly ? ' disabled' : '' ?>>
            <?php foreach ($typeOptions as $value => $label) : ?>
              <option value="<?= View::escape($value) ?>"<?= $selectedType === $value ? ' selected' : '' ?>><?= View::escape($label) ?></option>
            <?php endforeach; ?>
          </select>
          <small class="dg-field-hint" id="dg-voucher-type-hint"><?= View::escape($typeHint) ?></small>
        </label>
        <label class="dg-field">
          <span>Belegdatum *</span>
          <input type="date" name="voucher_date" id="dg-voucher-date" value="<?= View::escape($form['voucher_date'] ?? '') ?>" required<?= $readOnly ? ' readonly' : '' ?>>
        </label>
        <label class="dg-field">
          <span>Lieferdatum</span>
          <input type="date" name="delivery_date" id="dg-voucher-delivery-date" value="<?= View::escape($form['delivery_date'] ?? '') ?>"<?= $readOnly ? ' readonly' : '' ?>>
          <small class="dg-field-hint">Leistungs- oder Lieferdatum — z. B. auf der Ausgangsrechnung.</small>
        </label>
        <label class="dg-field" id="dg-voucher-invoice-field">
          <span id="dg-voucher-invoice-label">Rechnungsnummer<?= $invoiceNumberRequired ? ' *' : '' ?></span>
          <input
            type="text"
            <?= $autoInvoiceNumber ? '' : 'name="invoice_number"' ?>
            id="dg-voucher-invoice-number"
            value="<?= View::escape($invoiceNumberValue) ?>"
            maxlength="100"
            <?= $invoiceNumberRequired ? ' required' : '' ?>
            <?= ($readOnly || $autoInvoiceNumber) ? ' readonly' : '' ?>
            class="<?= $autoInvoiceNumber ? 'dg-input--computed' : '' ?>"
            <?= $isEdit && $autoInvoiceNumber ? ' data-saved-invoice="1"' : '' ?>
          >
          <?php if (!$readOnly) : ?>
            <small class="dg-field-hint" id="dg-voucher-invoice-hint"<?= $autoInvoiceNumber ? '' : ' hidden' ?>>
              <?php if ($autoInvoiceNumber) : ?>
                <?= $isEdit
                    ? 'Aus dem Nummernkreis „' . View::escape($invoiceNumberRangeLabel) . '“, nachträglich nicht änderbar.'
                    : 'Wird beim Speichern automatisch aus dem Nummernkreis „' . View::escape($invoiceNumberRangeLabel) . '“ vergeben (Vorschau).' ?>
                <a href="<?= View::escape(SettingsRegistry::tabUrl('nummernkreise') . '&ntype=' . rawurlencode((string) $invoiceNumberRangeType)) ?>">Nummernkreis einrichten</a>
              <?php endif; ?>
            </small>
          <?php endif; ?>
        </label>
        <label class="dg-field dg-field--wide">
          <span>Zahlungsstatus</span>
          <select name="payment_status" id="dg-voucher-payment-status"<?= $readOnly ? ' disabled' : '' ?>>
            <?php foreach ($paymentOptions as $value => $label) : ?>
              <option value="<?= View::escape($value) ?>"<?= $paymentStatus === $value ? ' selected' : '' ?>><?= View::escape($label) ?></option>
            <?php endforeach; ?>
          </select>
          <small class="dg-field-hint" id="dg-voucher-payment-status-hint"><?= View::escape($paymentStatusHint) ?></small>
        </label>
      </div>
      <div class="dg-form-grid" id="dg-voucher-settlement-fields">
        <label class="dg-field">
          <span>Skonto %</span>
          <input type="number" name="discount_percent" min="0" max="100" step="1"
                 value="<?= View::escape((string) ($form['discount_percent'] ?? '0')) ?>"<?= $readOnly ? ' readonly' : '' ?>>
        </label>
        <label class="dg-field">
          <span>Skontobetrag</span>
          <input type="text" name="discount_amount" inputmode="decimal"
                 value="<?= View::escape((string) ($form['discount_amount'] ?? '')) ?>" placeholder="0,00"<?= $readOnly ? ' readonly' : '' ?>>
        </label>
        <label class="dg-field">
          <span>Gezahlt (Brutto − Skonto)</span>
          <input type="text" name="paid_amount" inputmode="decimal"
                 value="<?= View::escape((string) ($form['paid_amount'] ?? '')) ?>" placeholder="0,00"<?= $readOnly ? ' readonly' : '' ?>>
        </label>
        <label class="dg-field">
          <span>Zahlungsdatum</span>
          <input type="date" name="paid_at" value="<?= View::escape((string) ($form['paid_at'] ?? '')) ?>"<?= $readOnly ? ' readonly' : '' ?>>
        </label>
      </div>
    </section>

    <section class="dg-form-section">
      <div class="dg-form-section__head">
        <h2 class="dg-subsection-title">Lieferant / Kontakt</h2>
        <?php if (!$readOnly && MenuRegistry::canAccess($user ?? null, 'kontakte')) : ?>
          <a class="dg-button dg-button--small" id="dg-voucher-new-contact-link" href="<?= View::escape($newContactUrl) ?>">Neuen Kunden / Lieferanten anlegen</a>
        <?php endif; ?>
      </div>
      <div class="dg-form-grid">
        <label class="dg-field dg-field--wide">
          <span>Kontakt suchen</span>
          <input
            type="search"
            id="dg-voucher-contact-search"
            value="<?= View::escape($form['contact_label'] ?? '') ?>"
            placeholder="Name, Firma oder E-Mail …"
            autocomplete="off"
            <?= $readOnly ? ' disabled' : '' ?>
          >
          <small class="dg-field-hint" id="dg-voucher-contact-hint">Pflicht: einen gespeicherten Kontakt aus der Liste wählen.</small>
          <div id="dg-voucher-contact-results" class="dg-voucher-contact-results" hidden></div>
        </label>
        <label class="dg-field dg-field--wide">
          <span>Lieferantenname (Freitext)</span>
          <input type="text" name="supplier_name" id="dg-voucher-supplier-name" value="<?= View::escape($form['supplier_name'] ?? '') ?>" maxlength="191"<?= $readOnly ? ' readonly' : '' ?>>
          <small class="dg-field-hint">Wird vom gewählten Kontakt übernommen, kann bei Bedarf angepasst werden.</small>
        </label>
      </div>
    </section>

    <section class="dg-form-section" id="dg-voucher-invoice-items-section"<?= $showInvoiceItems ? '' : ' hidden' ?>>
      <div class="dg-form-section__head">
        <h2 class="dg-subsection-title">Rechnungspositionen</h2>
      </div>
      <p class="dg-field-hint" id="dg-voucher-invoice-items-hint">
        Artikel und Leistungen aus dem Katalog — Buchungszeilen werden daraus automatisch erzeugt.
        <span class="dg-muted">Leistungen mit <strong>IMP-*</strong> haben oft keinen Festpreis (0,00 €) — Betrag bitte manuell eintragen.</span>
      </p>
      <div class="dg-table-wrap">
        <table class="dg-table dg-voucher-items__table">
          <thead>
            <tr>
              <th>Artikel / Leistung</th>
              <th>Leistungsbereich</th>
              <th>Menge</th>
              <th>Einheit</th>
              <th>Einzelpreis brutto</th>
              <th>USt %</th>
              <th>Summe</th>
              <?php if (!$readOnly) : ?><th></th><?php endif; ?>
            </tr>
          </thead>
          <tbody id="dg-voucher-invoice-items-body">
            <?php foreach ($itemRows as $index => $item) : ?>
              <?php
              $itemTitle = (string) ($item['title'] ?? '');
              $itemNumber = (string) ($item['article_number'] ?? '');
              $itemQuery = $itemTitle !== ''
                  ? ($itemNumber !== '' ? $itemNumber . ' — ' . $itemTitle : $itemTitle)
                  : '';
              $itemAreaId = (int) ($item['area_id'] ?? 0);
              ?>
              <tr class="dg-voucher-items__row" data-item-index="<?= (int) $index ?>">
                <td class="dg-voucher-items-article-cell">
                  <?php if ($readOnly) : ?>
                    <?= View::escape($itemQuery !== '' ? $itemQuery : '—') ?>
                  <?php else : ?>
                    <div class="dg-voucher-items-article-wrap">
                      <input
                        type="search"
                        class="dg-voucher-items-article-query"
                        placeholder="Artikel oder Leistung suchen …"
                        autocomplete="off"
                        value="<?= View::escape($itemQuery) ?>"
                      >
                      <input type="hidden" class="dg-voucher-items-article-id" name="items[<?= (int) $index ?>][article_id]" value="<?= View::escape((string) ($item['article_id'] ?? '')) ?>">
                      <input type="hidden" class="dg-voucher-items-catalog-kind" name="items[<?= (int) $index ?>][catalog_kind]" value="<?= View::escape((string) ($item['catalog_kind'] ?? '')) ?>">
                      <input type="hidden" class="dg-voucher-items-article-number" name="items[<?= (int) $index ?>][article_number]" value="<?= View::escape($itemNumber) ?>">
                      <input type="hidden" class="dg-voucher-items-title" name="items[<?= (int) $index ?>][title]" value="<?= View::escape($itemTitle) ?>">
                      <input type="hidden" class="dg-voucher-items-tax-type" name="items[<?= (int) $index ?>][tax_type]" value="<?= View::escape((string) ($item['tax_type'] ?? 'ust19')) ?>">
                      <div class="dg-article-search-results dg-voucher-items-search-results" hidden></div>
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($readOnly) : ?>
                    <?= View::escape((string) ($item['area_name'] ?? '—')) ?>
                  <?php else : ?>
                    <select class="dg-voucher-items-area" name="items[<?= (int) $index ?>][area_id]">
                      <option value="">—</option>
                      <?php foreach ($calendarAreas as $area) : ?>
                        <option
                          value="<?= (int) ($area['id'] ?? 0) ?>"
                          data-area-name="<?= View::escape((string) ($area['name'] ?? '')) ?>"
                          <?= $itemAreaId === (int) ($area['id'] ?? 0) ? ' selected' : '' ?>
                        ><?= View::escape((string) ($area['name'] ?? '')) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <input type="hidden" class="dg-voucher-items-area-name" name="items[<?= (int) $index ?>][area_name]" value="<?= View::escape((string) ($item['area_name'] ?? '')) ?>">
                  <?php endif; ?>
                </td>
                <td>
                  <input
                    type="text"
                    class="dg-voucher-items-quantity"
                    name="items[<?= (int) $index ?>][quantity]"
                    inputmode="decimal"
                    value="<?= View::escape((string) ($item['quantity'] ?? '1')) ?>"
                    <?= $readOnly ? ' readonly' : '' ?>
                  >
                </td>
                <td>
                  <input
                    type="text"
                    class="dg-voucher-items-unit"
                    name="items[<?= (int) $index ?>][unit]"
                    value="<?= View::escape((string) ($item['unit'] ?? 'Stück')) ?>"
                    maxlength="64"
                    <?= $readOnly ? ' readonly' : '' ?>
                  >
                </td>
                <td>
                  <input
                    type="text"
                    class="dg-voucher-items-unit-price"
                    name="items[<?= (int) $index ?>][unit_price_gross]"
                    inputmode="decimal"
                    value="<?= View::escape((string) ($item['unit_price_gross'] ?? '')) ?>"
                    <?= $readOnly ? ' readonly' : '' ?>
                  >
                </td>
                <td>
                  <select class="dg-voucher-items-tax" name="items[<?= (int) $index ?>][tax_rate]"<?= $readOnly ? ' disabled' : '' ?>>
                    <?php foreach ($taxRates as $rate) : ?>
                      <option value="<?= (int) $rate ?>"<?= (int) ($item['tax_rate'] ?? 19) === $rate ? ' selected' : '' ?>><?= (int) $rate ?> %</option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td>
                  <input
                    type="text"
                    class="dg-voucher-items-gross dg-input--computed"
                    name="items[<?= (int) $index ?>][gross_amount]"
                    value="<?= View::escape((string) ($item['gross_amount'] ?? '')) ?>"
                    readonly
                    tabindex="-1"
                  >
                </td>
                <?php if (!$readOnly) : ?>
                  <td>
                    <button type="button" class="dg-button dg-button--ghost dg-voucher-items-remove" aria-label="Position entfernen">×</button>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="dg-field-hint">Summe Positionen: <strong id="dg-voucher-invoice-items-sum">0,00</strong> €</p>
    </section>

    <section class="dg-form-section" id="dg-voucher-booking-section">
      <h2 class="dg-subsection-title">Buchung</h2>
      <div class="dg-form-grid">
        <label class="dg-field dg-field--wide">
          <span>Buchungstext</span>
          <input type="text" name="description" value="<?= View::escape($form['description'] ?? '') ?>" maxlength="500" placeholder="z. B. Büromaterial, Tankbeleg …"<?= $readOnly ? ' readonly' : '' ?>>
          <small class="dg-field-hint">Freier Buchungstext für den konkreten Vorgang auf dem Beleg, nicht die Kontenbezeichnung. Beispiel: „Tankbeleg 03/2026“ oder „Büromaterial Müller“.</small>
        </label>
      </div>

      <div id="dg-voucher-booking-panel" class="dg-voucher-booking<?= $showInvoiceItems ? ' dg-voucher-booking--derived' : '' ?>">
        <?php if ($showInvoiceItems) : ?>
          <p class="dg-field-hint dg-voucher-booking-derived-hint">Wird aus den Rechnungspositionen berechnet (Erlöskonten je Steuersatz).</p>
        <?php endif; ?>
        <div class="dg-table-wrap">
          <table class="dg-table dg-voucher-split__table">
            <thead>
              <tr>
                <th>Konto</th>
                <th id="dg-voucher-booking-amount-header">Brutto</th>
                <th>USt %</th>
                <?php if (!$readOnly) : ?><th></th><?php endif; ?>
              </tr>
            </thead>
            <tbody id="dg-voucher-booking-body">
              <?php foreach ($lineRows as $index => $line) : ?>
                <?php
                $accountNumber = (string) ($line['account_number'] ?? '');
                $accountName = (string) ($line['account_name'] ?? '');
                $accountDisplay = $accountNumber !== ''
                    ? ($accountName !== '' ? $accountNumber . ' — ' . $accountName : $accountNumber)
                    : '';
                ?>
                <tr class="dg-voucher-split__row" data-line-index="<?= (int) $index ?>">
                  <td class="dg-voucher-split-account-cell">
                    <?php if ($readOnly) : ?>
                      <?= View::escape($accountDisplay !== '' ? $accountDisplay : '—') ?>
                    <?php else : ?>
                      <div class="dg-voucher-split-account-wrap">
                        <input
                          type="search"
                          class="dg-voucher-split-account-query"
                          placeholder="Konto suchen oder Nummer …"
                          autocomplete="off"
                          value="<?= View::escape($accountDisplay) ?>"
                        >
                        <input
                          type="hidden"
                          class="dg-voucher-split-account"
                          name="lines[<?= (int) $index ?>][account_number]"
                          value="<?= View::escape($accountNumber) ?>"
                        >
                        <div class="dg-account-search-results dg-voucher-split-search-results" hidden></div>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <input
                      type="text"
                      name="lines[<?= (int) $index ?>][gross_amount]"
                      class="dg-voucher-split-gross"
                      inputmode="decimal"
                      value="<?= View::escape((string) ($line['gross_amount'] ?? '')) ?>"
                      <?= $readOnly ? ' readonly' : '' ?>
                    >
                  </td>
                  <td>
                    <select name="lines[<?= (int) $index ?>][tax_rate]"<?= $readOnly ? ' disabled' : '' ?>>
                      <?php foreach ($taxRates as $rate) : ?>
                        <option value="<?= (int) $rate ?>"<?= (int) ($line['tax_rate'] ?? $form['tax_rate'] ?? 19) === $rate ? ' selected' : '' ?>><?= (int) $rate ?> %</option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <?php if (!$readOnly) : ?>
                    <td>
                      <button type="button" class="dg-button dg-button--ghost dg-voucher-split-remove" aria-label="Zeile entfernen">×</button>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="dg-field-hint" id="dg-voucher-booking-sum-hint">Summe Buchungszeilen: <strong id="dg-voucher-booking-sum">0,00</strong> €</p>
      </div>
    </section>

    <section class="dg-form-section" id="dg-voucher-arap-section"<?= $showArapSection ? '' : ' hidden' ?>>
      <h2 class="dg-subsection-title" id="dg-voucher-arap-section-title">Rechnungsabgrenzung<?= VoucherAccrual::isIncomeType($selectedType) ? '' : ' (ARAP)' ?></h2>
      <?php if ($readOnly) : ?>
        <p class="dg-field-static">
          <?= $arapEnabled
              ? View::escape($arapTypeLabel) . ' — ' . (int) $arapCurrentPercent . ' % ' . $fiscalYear . ', ' . (int) $arapNextPercent . ' % ' . $nextFiscalYear
              : 'Keine Rechnungsabgrenzung' ?>
        </p>
        <?php if ($arapEnabled) : ?>
          <?php
          $accrualDisplayLines = array_values(array_filter(
              $systemLines,
              static fn (array $line): bool => (string) ($line['line_kind'] ?? '') === VoucherAccrual::LINE_ACCRUAL
          ));
          ?>
          <?php if ($accrualDisplayLines !== []) : ?>
            <div class="dg-table-wrap">
              <table class="dg-table dg-voucher-arap__table">
                <thead>
                  <tr>
                    <th>Konto</th>
                    <th>Bezeichnung</th>
                    <th>Betrag (brutto)</th>
                    <th>USt %</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($accrualDisplayLines as $posting) : ?>
                    <tr>
                      <td><?= View::escape((string) ($posting['account_number'] ?? '')) ?></td>
                      <td><?= View::escape((string) ($posting['account_name'] ?? $posting['description'] ?? '')) ?></td>
                      <td class="dg-table__num"><?= View::escape((string) ($posting['gross_amount'] ?? '')) ?> €</td>
                      <td><?= View::escape((string) ($posting['tax_rate'] ?? '')) ?> %</td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      <?php else : ?>
        <label class="dg-field dg-field--wide dg-voucher-arap-toggle">
          <span class="dg-checkbox">
            <input type="checkbox" name="arap_enabled" id="dg-voucher-arap-enabled" value="1"<?= $arapEnabled ? ' checked' : '' ?>>
            <span><?= View::escape($arapTypeLabel) ?> aktivieren</span>
          </span>
          <small class="dg-field-hint" id="dg-voucher-arap-hint"><?= View::escape($arapTypeHint) ?></small>
        </label>
        <div id="dg-voucher-arap-fields" class="dg-voucher-arap-fields"<?= $arapEnabled ? '' : ' hidden' ?>>
          <div class="dg-form-grid">
            <label class="dg-field">
              <span id="dg-voucher-arap-current-label"><?= View::escape((string) $fiscalYear) ?> *</span>
              <input
                type="number"
                name="arap_current_year_percent"
                id="dg-voucher-arap-current-percent"
                min="0"
                max="100"
                step="1"
                value="<?= (int) $arapCurrentPercent ?>"
              >
              <small class="dg-field-hint">Anteil in % für das Geschäftsjahr des Belegdatums.</small>
            </label>
            <label class="dg-field">
              <span id="dg-voucher-arap-next-label"><?= View::escape((string) $nextFiscalYear) ?></span>
              <input
                type="number"
                name="arap_next_year_percent"
                id="dg-voucher-arap-next-percent"
                min="0"
                max="100"
                step="1"
                value="<?= (int) $arapNextPercent ?>"
                readonly
                class="dg-input--computed"
                tabindex="-1"
              >
              <small class="dg-field-hint">Wird automatisch als Rest auf 100 % berechnet.</small>
            </label>
          </div>
          <div class="dg-voucher-arap-panel">
            <h3 class="dg-voucher-arap-panel__title">Vorschau Verteilung</h3>
            <div class="dg-table-wrap">
              <table class="dg-table dg-voucher-arap__table">
                <thead>
                  <tr>
                    <th>Anteil</th>
                    <th>Konto</th>
                    <th>Bezeichnung</th>
                    <th>Betrag (brutto)</th>
                  </tr>
                </thead>
                <tbody id="dg-voucher-arap-preview-body">
                  <tr><td colspan="4" class="dg-muted">Buchungszeilen erfassen, dann erscheint die Verteilung.</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </section>

    <section class="dg-form-section">
      <h2 class="dg-subsection-title">Steuer &amp; Beträge</h2>
      <div class="dg-form-grid">
        <label class="dg-field dg-field--wide">
          <span>Reverse Charge (§13b UStG)</span>
          <?php if ($readOnly) : ?>
            <p class="dg-field-static">
              <?= $reverseCharge
                  ? View::escape($reverseChargeOptions[$reverseChargeType] ?? $reverseChargeType) . ' — DATEV-Schlüssel 94'
                  : 'Kein Reverse Charge' ?>
            </p>
          <?php else : ?>
            <select name="reverse_charge_type" id="dg-voucher-reverse-charge-type">
              <option value="">Kein Reverse Charge (normale USt.)</option>
              <?php foreach ($reverseChargeOptions as $rcValue => $rcLabel) : ?>
                <option value="<?= View::escape($rcValue) ?>"<?= $reverseChargeType === $rcValue ? ' selected' : '' ?>><?= View::escape($rcLabel) ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
          <input type="hidden" name="tax_key" id="dg-voucher-tax-key" value="<?= View::escape($reverseCharge ? VoucherTaxKeys::KEY_REVERSE_CHARGE : '') ?>">
          <small class="dg-field-hint" id="dg-voucher-reverse-charge-hint">
            <?= View::escape($reverseCharge ? VoucherReverseCharge::typeHint($reverseChargeType) : 'Bei EU-/Drittlandsleistungen oder Bauleistungen mit Steuerschuldnerschaft des Leistungsempfängers wählen.') ?>
          </small>
        </label>
        <label class="dg-field">
          <span>Bruttobetrag *</span>
          <input type="text" name="gross_amount" id="dg-voucher-gross" inputmode="decimal" value="<?= View::escape($form['gross_amount'] ?? '') ?>" required placeholder="0,00" readonly tabindex="-1" class="dg-input--computed"<?= $readOnly ? ' readonly' : '' ?>>
          <small class="dg-field-hint" id="dg-voucher-gross-hint">Wird aus den Buchungszeilen berechnet.</small>
        </label>
        <label class="dg-field">
          <span>Nettobetrag</span>
          <input type="text" name="net_amount" id="dg-voucher-net" inputmode="decimal" value="<?= View::escape($form['net_amount'] ?? '') ?>" readonly tabindex="-1" class="dg-input--computed">
          <small class="dg-field-hint" id="dg-voucher-net-hint">Wird aus den Buchungszeilen berechnet.</small>
        </label>
        <div class="dg-field dg-field--wide dg-voucher-tax-breakdown">
          <span>MwSt.-Aufschlüsselung</span>
          <div class="dg-voucher-tax-breakdown__grid" role="list">
            <?php foreach ($taxRates as $rate) : ?>
              <?php
                $rateTax = (float) ($taxBreakdown[$rate] ?? 0);
                $rateDisplay = !$hasBookingAmounts
                    ? '—'
                    : VoucherRepository::formatMoney($rateTax) . ' €';
              ?>
              <div class="dg-voucher-tax-breakdown__item" role="listitem">
                <span class="dg-voucher-tax-breakdown__rate"><?= (int) $rate ?> %</span>
                <span class="dg-voucher-tax-breakdown__amount dg-input--computed" id="dg-voucher-tax-rate-<?= (int) $rate ?>"><?= View::escape($rateDisplay) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
          <p class="dg-voucher-tax-breakdown__total">
            Summe MwSt.:
            <strong id="dg-voucher-tax-total"><?= $hasBookingAmounts ? View::escape(VoucherRepository::formatMoney((float) ($form['tax_amount'] ?? 0))) . ' €' : '—' ?></strong>
          </p>
          <small class="dg-field-hint" id="dg-voucher-tax-hint"><?= $reverseCharge ? 'Bei §13b: geschuldete Umsatzsteuer je Satz (Vorsteuer verrechnet sich).' : 'Enthaltene Umsatzsteuer je Steuersatz aus den Buchungszeilen.' ?></small>
        </div>
      </div>

      <div id="dg-voucher-rc-panels" class="dg-voucher-rc-panels"<?= $reverseCharge ? '' : ' hidden' ?>>
        <div class="dg-voucher-rc-panel">
          <h3 class="dg-voucher-rc-panel__title">Automatische Nebenbuchungen (§13b)</h3>
          <p class="dg-field-hint">Wie in Lexoffice: Vorsteuer und Umsatzsteuer werden zusätzlich auf die Steuerkonten gebucht.</p>
          <div class="dg-table-wrap">
            <table class="dg-table dg-voucher-rc-postings__table">
              <thead>
                <tr>
                  <th>S/H</th>
                  <th>Konto</th>
                  <th>Bezeichnung</th>
                  <th>Betrag</th>
                  <th>UStVA</th>
                </tr>
              </thead>
              <tbody id="dg-voucher-rc-postings-body">
                <?php if ($readOnly && $systemLines !== []) : ?>
                  <?php foreach ($systemLines as $posting) : ?>
                    <tr>
                      <td><?= ($posting['posting_side'] ?? '') === 'credit' ? 'H' : 'S' ?></td>
                      <td><?= View::escape((string) ($posting['account_number'] ?? '')) ?></td>
                      <td><?= View::escape((string) ($posting['account_name'] ?? $posting['description'] ?? '')) ?></td>
                      <td class="dg-table__num"><?= View::escape((string) ($posting['gross_amount'] ?? '')) ?> €</td>
                      <td><?= View::escape((string) ($posting['ustva_kz'] ?? '')) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="dg-voucher-rc-panel">
          <h3 class="dg-voucher-rc-panel__title">USt-Voranmeldung (Kennziffern)</h3>
          <div class="dg-table-wrap">
            <table class="dg-table dg-voucher-rc-ustva__table">
              <thead>
                <tr>
                  <th>Kz</th>
                  <th>Bemessungsgrundlage</th>
                  <th>Steuer</th>
                </tr>
              </thead>
              <tbody id="dg-voucher-rc-ustva-body">
                <?php if ($readOnly && $ustvaPositions !== []) : ?>
                  <?php foreach ($ustvaPositions as $pos) : ?>
                    <tr>
                      <td><?= View::escape((string) ($pos['kz'] ?? '')) ?></td>
                      <td class="dg-table__num"><?= (float) ($pos['net'] ?? 0) > 0 ? View::escape(VoucherRepository::formatMoney((float) $pos['net'])) . ' €' : '—' ?></td>
                      <td class="dg-table__num"><?= (float) ($pos['tax'] ?? 0) > 0 ? View::escape(VoucherRepository::formatMoney((float) $pos['tax'])) . ' €' : '—' ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </section>

    <?php if ($isEdit && empty($isDraftVoucher)) : ?>
    <section class="dg-form-section dg-voucher-ledger-postings">
      <h2 class="dg-subsection-title">Buchungssätze (Journal)</h2>
      <p class="dg-field-hint">Automatisch erzeugt beim Speichern — Soll = Haben, mit DATEV-Steuerschlüssel und Belegfeldern.</p>
      <?php if ($ledgerPostings === []) : ?>
        <p class="dg-muted">Noch keine Buchungen — Beleg speichern oder Beträge prüfen.</p>
      <?php else : ?>
        <div class="dg-table-wrap">
          <table class="dg-table">
            <thead>
              <tr>
                <th>S/H</th>
                <th>Konto</th>
                <th>Gegenkonto</th>
                <th>BU</th>
                <th>Belegfeld 1</th>
                <th>Belegfeld 2</th>
                <th class="dg-table__num">Betrag</th>
                <th>Text</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($ledgerPostings as $posting) : ?>
                <tr>
                  <td><?= View::escape((string) ($posting['side_label'] ?? '')) ?></td>
                  <td>
                    <?= View::escape((string) ($posting['account_number'] ?? '')) ?>
                    <?php if (($posting['account_name'] ?? '') !== '') : ?>
                      <span class="dg-muted"><?= View::escape((string) $posting['account_name']) ?></span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?= View::escape((string) ($posting['contra_account'] ?? '')) ?>
                    <?php if (($posting['contra_name'] ?? '') !== '') : ?>
                      <span class="dg-muted"><?= View::escape((string) $posting['contra_name']) ?></span>
                    <?php endif; ?>
                  </td>
                  <td><?= View::escape((string) ($posting['tax_key'] ?? '')) ?></td>
                  <td><?= View::escape((string) ($posting['document_field1'] ?? '')) ?></td>
                  <td><?= View::escape((string) ($posting['document_field2'] ?? '')) ?></td>
                  <td class="dg-table__num"><?= View::escape(VoucherRepository::formatMoney((float) ($posting['amount'] ?? 0))) ?> €</td>
                  <td><?= View::escape((string) ($posting['description'] ?? '')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="dg-form-section">
      <h2 class="dg-subsection-title">Notizen</h2>
      <label class="dg-field dg-field--wide">
        <span>Interne Notizen</span>
        <textarea name="notes" rows="3"<?= $readOnly ? ' readonly' : '' ?>><?= View::escape($form['notes'] ?? '') ?></textarea>
      </label>
    </section>

    <?php if (!$readOnly) : ?>
      <div class="dg-form-actions">
        <button type="submit" class="dg-button dg-button--primary" id="dg-voucher-save-btn"<?= !$readOnly && (int) ($form['contact_id'] ?? 0) < 1 ? ' disabled' : '' ?>>Beleg speichern</button>
        <a class="dg-button" href="<?= View::escape($backHref) ?>">Abbrechen</a>
      </div>
    <?php endif; ?>
  </form>

  <?php
  /** @var list<array<string, mixed>> $voucherFiles */
  $voucherFiles = is_array($form['files'] ?? null) ? $form['files'] : [];
  ?>
  <?php if ($isEdit && empty($isDraftVoucher) && $voucherFiles !== []) : ?>
    <section class="dg-panel dg-voucher-attachments">
      <h2 class="dg-subsection-title">Angehängte Dateien</h2>
      <ul class="dg-voucher-attachments__list">
        <?php foreach ($voucherFiles as $file) : ?>
          <li class="dg-voucher-attachments__item">
            <?php if (!empty($file['is_image'])) : ?>
              <a href="<?= View::escape((string) $file['view_url']) ?>" target="_blank" rel="noopener" class="dg-voucher-attachments__thumb">
                <img src="<?= View::escape((string) $file['view_url']) ?>" alt="<?= View::escape((string) $file['original_name']) ?>" loading="lazy">
              </a>
            <?php else : ?>
              <a href="<?= View::escape((string) $file['view_url']) ?>" target="_blank" rel="noopener" class="dg-voucher-attachments__thumb dg-voucher-attachments__thumb--file">
                <?php View::render('partials/icon', ['name' => 'document']); ?>
              </a>
            <?php endif; ?>
            <div class="dg-voucher-attachments__meta">
              <a href="<?= View::escape((string) $file['view_url']) ?>" target="_blank" rel="noopener" class="dg-voucher-attachments__name"><?= View::escape((string) $file['original_name']) ?></a>
              <span class="dg-muted"><?= View::escape((string) ($file['size_label'] ?? '')) ?></span>
              <span class="dg-voucher-attachments__links">
                <a href="<?= View::escape((string) $file['download_url']) ?>">Herunterladen</a>
                <?php if (!$readOnly) : ?>
                  &middot;
                  <button type="submit" form="dg-voucher-file-delete-<?= (int) $file['id'] ?>" class="dg-linklike dg-linklike--danger" onclick="return confirm('Diese Datei wirklich löschen?');">Löschen</button>
                <?php endif; ?>
              </span>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php if (!$readOnly) : ?>
      <?php foreach ($voucherFiles as $file) : ?>
        <form method="post" action="/app?page=buchhaltung-beleg-form" id="dg-voucher-file-delete-<?= (int) $file['id'] ?>" class="dg-hidden-form">
          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
          <input type="hidden" name="voucher_file_delete" value="1">
          <input type="hidden" name="file_id" value="<?= (int) $file['id'] ?>">
          <input type="hidden" name="id" value="<?= (int) $voucherId ?>">
        </form>
      <?php endforeach; ?>
    <?php endif; ?>
  <?php endif; ?>

  <template id="dg-voucher-invoice-item-row-template">
    <tr class="dg-voucher-items__row">
      <td class="dg-voucher-items-article-cell">
        <div class="dg-voucher-items-article-wrap">
          <input type="search" class="dg-voucher-items-article-query" placeholder="Artikel oder Leistung suchen …" autocomplete="off">
          <input type="hidden" class="dg-voucher-items-article-id">
          <input type="hidden" class="dg-voucher-items-catalog-kind">
          <input type="hidden" class="dg-voucher-items-article-number">
          <input type="hidden" class="dg-voucher-items-title">
          <input type="hidden" class="dg-voucher-items-tax-type">
          <div class="dg-article-search-results dg-voucher-items-search-results" hidden></div>
        </div>
      </td>
      <td>
        <select class="dg-voucher-items-area">
          <option value="">—</option>
          <?php foreach ($calendarAreas as $area) : ?>
            <option value="<?= (int) ($area['id'] ?? 0) ?>" data-area-name="<?= View::escape((string) ($area['name'] ?? '')) ?>"><?= View::escape((string) ($area['name'] ?? '')) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="hidden" class="dg-voucher-items-area-name">
      </td>
      <td><input type="text" class="dg-voucher-items-quantity" inputmode="decimal" value="1"></td>
      <td><input type="text" class="dg-voucher-items-unit" value="Stück" maxlength="64"></td>
      <td><input type="text" class="dg-voucher-items-unit-price" inputmode="decimal" placeholder="0,00"></td>
      <td>
        <select class="dg-voucher-items-tax">
          <?php foreach ($taxRates as $rate) : ?>
            <option value="<?= (int) $rate ?>"><?= (int) $rate ?> %</option>
          <?php endforeach; ?>
        </select>
      </td>
      <td><input type="text" class="dg-voucher-items-gross dg-input--computed" readonly tabindex="-1"></td>
      <td><button type="button" class="dg-button dg-button--ghost dg-voucher-items-remove" aria-label="Position entfernen">×</button></td>
    </tr>
  </template>

  <template id="dg-voucher-booking-row-template">
    <tr class="dg-voucher-split__row">
      <td class="dg-voucher-split-account-cell">
        <div class="dg-voucher-split-account-wrap">
          <input type="search" class="dg-voucher-split-account-query" placeholder="Konto suchen oder Nummer …" autocomplete="off">
          <input type="hidden" class="dg-voucher-split-account">
          <div class="dg-account-search-results dg-voucher-split-search-results" hidden></div>
        </div>
      </td>
      <td><input type="text" class="dg-voucher-split-gross" inputmode="decimal" placeholder="0,00"></td>
      <td>
        <select class="dg-voucher-split-tax">
          <?php foreach ($taxRates as $rate) : ?>
            <option value="<?= (int) $rate ?>"><?= (int) $rate ?> %</option>
          <?php endforeach; ?>
        </select>
      </td>
      <td><button type="button" class="dg-button dg-button--ghost dg-voucher-split-remove" aria-label="Zeile entfernen">×</button></td>
    </tr>
  </template>
</div>
