<?php
/**
 * @var array{payment_term_tiers: list<array{days: int, adjustment_percent: float, label: string}>, dunning: array<string, mixed>} $accountingPaymentSettings
 * @var bool $dbConnected
 */
$settings = $accountingPaymentSettings ?? AccountingPaymentSettings::forForm();
$tiers = is_array($settings['payment_term_tiers'] ?? null) ? $settings['payment_term_tiers'] : [];
$dunning = is_array($settings['dunning'] ?? null) ? $settings['dunning'] : AccountingPaymentSettings::defaults()['dunning'];
$dunningLevels = is_array($dunning['levels'] ?? null) ? $dunning['levels'] : [];
$fmt = static fn (float $v): string => number_format($v, 2, '.', '');
?>
<form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('payment-terms')) ?>" id="dg-payment-terms-settings-form">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Zum Speichern ist eine funktionierende <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Datenbankverbindung</a> erforderlich.
    </div>
  <?php endif; ?>

  <section class="dg-form-section">
    <h3 class="dg-subsection-title">Skonto-Stufen mit Zeitvorgaben</h3>
    <p class="dg-field-hint">
      Negative Prozentwerte = Skonto (Rabatt). Positive Werte = Verzugszinsen/Zuschlag bei späterer Zahlung.
      Standard: 7 Tage 3&nbsp;% Skonto, 30 Tage netto, 90 Tage 1,5&nbsp;% Verzug.
    </p>

    <div class="dg-table-wrap">
      <table class="dg-table" id="dg-payment-term-tiers-table">
        <thead>
          <tr>
            <th>Tage ab Rechnungsdatum</th>
            <th>Änderung %</th>
            <th>Bezeichnung</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="dg-payment-term-tiers-body">
          <?php foreach ($tiers as $index => $tier) : ?>
            <tr class="dg-payment-term-tier-row">
              <td>
                <input type="number" name="payment_term_tiers[<?= (int) $index ?>][days]" min="1" max="365" step="1"
                       value="<?= (int) ($tier['days'] ?? 1) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
              </td>
              <td>
                <input type="text" name="payment_term_tiers[<?= (int) $index ?>][adjustment_percent]" inputmode="decimal"
                       value="<?= View::escape($fmt((float) ($tier['adjustment_percent'] ?? 0))) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
              </td>
              <td>
                <input type="text" name="payment_term_tiers[<?= (int) $index ?>][label]" maxlength="80"
                       value="<?= View::escape((string) ($tier['label'] ?? '')) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
              </td>
              <td>
                <button type="button" class="dg-button dg-button--ghost dg-payment-term-tier-remove" aria-label="Stufe entfernen"<?= !$dbConnected ? ' disabled' : '' ?>>×</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="dg-form-actions">
      <button type="button" class="dg-button dg-button--small" id="dg-payment-term-tier-add"<?= !$dbConnected ? ' disabled' : '' ?>>Stufe hinzufügen</button>
    </p>
  </section>

  <hr class="dg-form-divider">

  <section class="dg-form-section">
    <h3 class="dg-subsection-title">Automatischer Mahnversand</h3>
    <p class="dg-field-hint">
      Wenn aktiviert: Mahnungen werden <strong>automatisch einmal täglich</strong> beim ersten Seitenaufruf versendet
      (CRM oder öffentliche Website — gleiches Prinzip wie Tages-Backup, kein KAS-Cron nötig).
      Optional zusätzlich per KAS-Cron: <code>cron.php?job=dunning-auto&amp;token=…</code>.
      Log: <code>storage/logs/dunning-auto.log</code>.
      Platzhalter: <code>{RECHNUNGSNR}</code>, <code>{BELEGDATUM}</code>, <code>{FAELLIG}</code>, <code>{OFFEN}</code>, <code>{MAHNGEBUEHR}</code>, <code>{FIRMA}</code>.
    </p>

    <label class="dg-checkbox">
      <input type="checkbox" name="dunning_auto_send" value="1"<?= !empty($dunning['auto_send']) ? ' checked' : '' ?><?= !$dbConnected ? ' disabled' : '' ?>>
      <span>Automatischen Mahnversand aktivieren</span>
    </label>

    <label class="dg-field" style="margin-top: 12px;">
      <span>Mahngebühren-Konto (SKR)</span>
      <input type="text" name="dunning_fee_account" maxlength="8" pattern="\d*"
             value="<?= View::escape((string) ($dunning['fee_account'] ?? '4970')) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
      <small class="dg-field-hint">z. B. 4970 Mahngebühren (SKR03)</small>
    </label>

    <div class="dg-table-wrap" style="margin-top: 16px;">
      <table class="dg-table" id="dg-dunning-levels-table">
        <thead>
          <tr>
            <th>Tage nach Fälligkeit</th>
            <th>Stufe</th>
            <th>Mahngebühr €</th>
            <th>E-Mail Betreff</th>
          </tr>
        </thead>
        <tbody id="dg-dunning-levels-body">
          <?php foreach ($dunningLevels as $index => $level) : ?>
            <tr class="dg-dunning-level-row">
              <td>
                <input type="number" name="dunning_levels[<?= (int) $index ?>][days_after_due]" min="0" max="365" step="1"
                       value="<?= (int) ($level['days_after_due'] ?? 0) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
              </td>
              <td>
                <input type="text" name="dunning_levels[<?= (int) $index ?>][label]" maxlength="120"
                       value="<?= View::escape((string) ($level['label'] ?? '')) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
              </td>
              <td>
                <input type="text" name="dunning_levels[<?= (int) $index ?>][fee_amount]" inputmode="decimal"
                       value="<?= View::escape($fmt((float) ($level['fee_amount'] ?? 0))) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
              </td>
              <td>
                <input type="text" name="dunning_levels[<?= (int) $index ?>][subject]" maxlength="255"
                       value="<?= View::escape((string) ($level['subject'] ?? '')) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
              </td>
            </tr>
            <tr class="dg-dunning-level-intro-row">
              <td colspan="4">
                <label class="dg-field dg-field--wide">
                  <span>E-Mail-Text</span>
                  <textarea name="dunning_levels[<?= (int) $index ?>][intro]" rows="4"<?= !$dbConnected ? ' disabled' : '' ?>><?= View::escape((string) ($level['intro'] ?? '')) ?></textarea>
                </label>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="dg-form-actions">
      <button type="button" class="dg-button dg-button--small" id="dg-dunning-level-add"<?= !$dbConnected ? ' disabled' : '' ?>>Mahnstufe hinzufügen</button>
    </p>
  </section>

  <p class="dg-form-actions">
    <button type="submit" name="accounting_payment_save" value="1" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>>
      Zahlungsbedingungen speichern
    </button>
  </p>
</form>

<template id="dg-payment-term-tier-template">
  <tr class="dg-payment-term-tier-row">
    <td><input type="number" name="payment_term_tiers[__INDEX__][days]" min="1" max="365" step="1" value="30"></td>
    <td><input type="text" name="payment_term_tiers[__INDEX__][adjustment_percent]" inputmode="decimal" value="0"></td>
    <td><input type="text" name="payment_term_tiers[__INDEX__][label]" maxlength="80" value="Netto"></td>
    <td><button type="button" class="dg-button dg-button--ghost dg-payment-term-tier-remove" aria-label="Stufe entfernen">×</button></td>
  </tr>
</template>

<template id="dg-dunning-level-template">
  <tr class="dg-dunning-level-row">
    <td><input type="number" name="dunning_levels[__INDEX__][days_after_due]" min="0" max="365" step="1" value="14"></td>
    <td><input type="text" name="dunning_levels[__INDEX__][label]" maxlength="120" value="Mahnung"></td>
    <td><input type="text" name="dunning_levels[__INDEX__][fee_amount]" inputmode="decimal" value="0.00"></td>
    <td><input type="text" name="dunning_levels[__INDEX__][subject]" maxlength="255" value=""></td>
  </tr>
  <tr class="dg-dunning-level-intro-row">
    <td colspan="4">
      <label class="dg-field dg-field--wide">
        <span>E-Mail-Text</span>
        <textarea name="dunning_levels[__INDEX__][intro]" rows="4"></textarea>
      </label>
    </td>
  </tr>
</template>

<script>
(function () {
  'use strict';
  var tierBody = document.getElementById('dg-payment-term-tiers-body');
  var tierTemplate = document.getElementById('dg-payment-term-tier-template');
  var tierAdd = document.getElementById('dg-payment-term-tier-add');
  var dunningBody = document.getElementById('dg-dunning-levels-body');
  var dunningTemplate = document.getElementById('dg-dunning-level-template');
  var dunningAdd = document.getElementById('dg-dunning-level-add');

  function reindexRows(body, rowClass) {
    if (!body) return;
    var index = 0;
    body.querySelectorAll('.' + rowClass).forEach(function (row) {
      row.querySelectorAll('[name]').forEach(function (field) {
        field.name = field.name.replace(/\[\d+\]/, '[' + index + ']');
      });
      if (rowClass === 'dg-dunning-level-row') {
        var intro = row.nextElementSibling;
        if (intro && intro.classList.contains('dg-dunning-level-intro-row')) {
          intro.querySelectorAll('[name]').forEach(function (field) {
            field.name = field.name.replace(/\[\d+\]/, '[' + index + ']');
          });
        }
      }
      index++;
    });
  }

  if (tierAdd && tierBody && tierTemplate) {
    tierAdd.addEventListener('click', function () {
      var index = tierBody.querySelectorAll('.dg-payment-term-tier-row').length;
      var html = tierTemplate.innerHTML.replace(/__INDEX__/g, String(index));
      tierBody.insertAdjacentHTML('beforeend', html);
    });
    tierBody.addEventListener('click', function (event) {
      var btn = event.target.closest('.dg-payment-term-tier-remove');
      if (!btn) return;
      var row = btn.closest('.dg-payment-term-tier-row');
      if (row) row.remove();
      reindexRows(tierBody, 'dg-payment-term-tier-row');
    });
  }

  if (dunningAdd && dunningBody && dunningTemplate) {
    dunningAdd.addEventListener('click', function () {
      var index = dunningBody.querySelectorAll('.dg-dunning-level-row').length;
      var html = dunningTemplate.innerHTML.replace(/__INDEX__/g, String(index));
      dunningBody.insertAdjacentHTML('beforeend', html);
    });
  }
})();
</script>
