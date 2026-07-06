<?php
/**
 * @var array<string, string> $form
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
$taxKeyOptions = VoucherTaxKeys::options();
$taxRates = VoucherTaxKeys::allowedTaxRates();
$skrLabel = ChartOfAccountsSettings::skrTypeOptions()[$chartOfAccountsConfig['skr_type'] ?? 'skr03'] ?? 'SKR03';
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
  </header>

  <?php View::render('partials/flash', compact('flash')); ?>

  <?php if ($formError ?? '') : ?>
    <div class="dg-flash dg-flash--error"><?= View::escape($formError) ?></div>
  <?php endif; ?>

  <form class="dg-form dg-panel dg-buchhaltung-beleg-form__form" method="post" action="/app?page=buchhaltung-beleg-form" id="dg-voucher-form"<?= $readOnly ? ' data-readonly="1"' : '' ?>>
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
    <input type="hidden" name="voucher_save" value="1">
    <input type="hidden" name="contact_id" id="dg-voucher-contact-id" value="<?= View::escape($form['contact_id'] ?? '') ?>">
    <?php if ($isEdit) : ?><input type="hidden" name="id" value="<?= (int) $voucherId ?>"><?php endif; ?>

    <section class="dg-form-section">
      <h2 class="dg-subsection-title">Beleg</h2>
      <div class="dg-form-grid">
        <label class="dg-field">
          <span>Belegart *</span>
          <select name="voucher_type" id="dg-voucher-type" required<?= $readOnly ? ' disabled' : '' ?>>
            <?php foreach ($typeOptions as $value => $label) : ?>
              <option value="<?= View::escape($value) ?>"<?= ($form['voucher_type'] ?? '') === $value ? ' selected' : '' ?>><?= View::escape($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dg-field">
          <span>Belegdatum *</span>
          <input type="date" name="voucher_date" value="<?= View::escape($form['voucher_date'] ?? '') ?>" required<?= $readOnly ? ' readonly' : '' ?>>
        </label>
        <label class="dg-field">
          <span>Rechnungsnummer</span>
          <input type="text" name="invoice_number" value="<?= View::escape($form['invoice_number'] ?? '') ?>" maxlength="100"<?= $readOnly ? ' readonly' : '' ?>>
        </label>
        <label class="dg-field">
          <span>Zahlungsstatus</span>
          <select name="payment_status"<?= $readOnly ? ' disabled' : '' ?>>
            <?php foreach ($paymentOptions as $value => $label) : ?>
              <option value="<?= View::escape($value) ?>"<?= ($form['payment_status'] ?? '') === $value ? ' selected' : '' ?>><?= View::escape($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
    </section>

    <section class="dg-form-section">
      <h2 class="dg-subsection-title">Lieferant / Kontakt</h2>
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
          <small class="dg-field-hint">Optional — verknüpft den Beleg mit einem Kontakt aus dem CRM.</small>
          <div id="dg-voucher-contact-results" class="dg-voucher-contact-results" hidden></div>
        </label>
        <label class="dg-field dg-field--wide">
          <span>Lieferantenname (Freitext)</span>
          <input type="text" name="supplier_name" id="dg-voucher-supplier-name" value="<?= View::escape($form['supplier_name'] ?? '') ?>" maxlength="191"<?= $readOnly ? ' readonly' : '' ?>>
          <small class="dg-field-hint">Pflicht, wenn kein Kontakt gewählt ist.</small>
        </label>
      </div>
    </section>

    <section class="dg-form-section">
      <h2 class="dg-subsection-title">Buchung</h2>
      <div class="dg-form-grid">
        <label class="dg-field dg-field--wide">
          <span>Buchungstext</span>
          <input type="text" name="description" value="<?= View::escape($form['description'] ?? '') ?>" maxlength="500" placeholder="z. B. Büromaterial, Tankbeleg …"<?= $readOnly ? ' readonly' : '' ?>>
        </label>
        <label class="dg-field">
          <span>Kontonummer *</span>
          <input
            type="text"
            name="account_number"
            id="dg-voucher-account-number"
            inputmode="numeric"
            pattern="[0-9]*"
            maxlength="8"
            value="<?= View::escape($form['account_number'] ?? '') ?>"
            required
            <?= $readOnly ? ' readonly' : '' ?>
          >
          <small class="dg-field-hint" id="dg-voucher-account-hint">
            <?= (int) ($chartOfAccountsConfig['account_digits'] ?? 4) ?>-stellig · <?= View::escape($skrLabel) ?>
            <?php if ((string) ($form['account_name'] ?? '') !== '') : ?>
              — <?= View::escape($form['account_name']) ?>
            <?php endif; ?>
          </small>
        </label>
      </div>
    </section>

    <section class="dg-form-section">
      <h2 class="dg-subsection-title">Steuer &amp; Beträge</h2>
      <div class="dg-form-grid">
        <label class="dg-field">
          <span>Steuersatz</span>
          <select name="tax_rate" id="dg-voucher-tax-rate"<?= $readOnly ? ' disabled' : '' ?>>
            <?php foreach ($taxRates as $rate) : ?>
              <option value="<?= (int) $rate ?>"<?= (int) ($form['tax_rate'] ?? 19) === $rate ? ' selected' : '' ?>><?= (int) $rate ?> %</option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dg-field">
          <span>Steuerschlüssel (DATEV)</span>
          <select name="tax_key" id="dg-voucher-tax-key"<?= $readOnly ? ' disabled' : '' ?>>
            <?php foreach ($taxKeyOptions as $value => $label) : ?>
              <option value="<?= View::escape($value) ?>"<?= ($form['tax_key'] ?? '') === $value ? ' selected' : '' ?>><?= View::escape($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dg-field">
          <span>Bruttobetrag *</span>
          <input type="text" name="gross_amount" id="dg-voucher-gross" inputmode="decimal" value="<?= View::escape($form['gross_amount'] ?? '') ?>" required placeholder="0,00"<?= $readOnly ? ' readonly' : '' ?>>
        </label>
        <label class="dg-field">
          <span>Nettobetrag</span>
          <input type="text" name="net_amount" id="dg-voucher-net" inputmode="decimal" value="<?= View::escape($form['net_amount'] ?? '') ?>" readonly tabindex="-1" class="dg-input--computed">
          <small class="dg-field-hint">Wird aus Brutto und Steuersatz berechnet.</small>
        </label>
        <label class="dg-field">
          <span>MwSt.-Betrag</span>
          <input type="text" id="dg-voucher-tax-amount" value="<?= View::escape($form['tax_amount'] ?? '') ?>" readonly tabindex="-1" class="dg-input--computed">
        </label>
      </div>
    </section>

    <section class="dg-form-section">
      <h2 class="dg-subsection-title">Notizen</h2>
      <label class="dg-field dg-field--wide">
        <span>Interne Notizen</span>
        <textarea name="notes" rows="3"<?= $readOnly ? ' readonly' : '' ?>><?= View::escape($form['notes'] ?? '') ?></textarea>
      </label>
    </section>

    <?php if (!$readOnly) : ?>
      <div class="dg-form-actions">
        <button type="submit" class="dg-button dg-button--primary">Beleg speichern</button>
        <a class="dg-button" href="<?= View::escape($backHref) ?>">Abbrechen</a>
      </div>
    <?php endif; ?>
  </form>
</div>
