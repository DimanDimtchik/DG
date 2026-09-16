<?php
/** @var int $i */
/** @var array<string, string> $account */
/** @var array<string, string> $bankTypes */
?>
<div class="dg-bank-card" data-bank-card>
  <div class="dg-bank-card__header">
    <p class="dg-bank-card__title" data-bank-title>Konto / Zahlungsdienst <?= (int) $i + 1 ?></p>
    <button type="button" class="dg-button dg-button--small" data-bank-remove>Entfernen</button>
  </div>
  <div class="dg-form-grid">
    <label class="dg-field">
      <span>Typ</span>
      <select name="bank_accounts[<?= (int) $i ?>][type]" data-bank-type>
        <?php foreach ($bankTypes as $typeKey => $typeLabel) : ?>
          <option value="<?= View::escape($typeKey) ?>"<?= ($account['type'] ?? 'giro') === $typeKey ? ' selected' : '' ?>><?= View::escape($typeLabel) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="dg-field"><span>Bezeichnung</span><input name="bank_accounts[<?= (int) $i ?>][label]" value="<?= View::escape((string) ($account['label'] ?? '')) ?>"></label>
    <label class="dg-field dg-field--check" data-bank-field="is_primary">
      <input type="checkbox" name="bank_accounts[<?= (int) $i ?>][is_primary]" value="1"<?= !empty($account['is_primary']) ? ' checked' : '' ?>>
      <span>Hauptkonto auf Dokumenten (Rechnung)</span>
    </label>
    <label class="dg-field" data-bank-field="account_holder"><span>Kontoinhaber</span><input name="bank_accounts[<?= (int) $i ?>][account_holder]" value="<?= View::escape((string) ($account['account_holder'] ?? '')) ?>"></label>
    <label class="dg-field dg-field--wide" data-bank-field="iban">
      <span>IBAN</span>
      <input class="dg-bank-iban" name="bank_accounts[<?= (int) $i ?>][iban]" value="<?= View::escape((string) ($account['iban'] ?? '')) ?>" autocomplete="off">
      <div class="dg-bank-suggest dg-bank-iban-suggest" hidden></div>
      <small class="dg-field-hint" data-bank-hint="card-iban">Viele Kreditkarten haben eine eigene IBAN für SEPA — Bank/BIC werden daraus vorgeschlagen. Für Rechnungen und Daueraufträge ist die IBAN maßgeblich.</small>
    </label>
    <label class="dg-field" data-bank-field="bic">
      <span>BIC</span>
      <input class="dg-bank-bic" name="bank_accounts[<?= (int) $i ?>][bic]" value="<?= View::escape((string) ($account['bic'] ?? '')) ?>" autocomplete="off">
      <div class="dg-bank-suggest dg-bank-bic-suggest" hidden></div>
    </label>
    <label class="dg-field dg-field--wide" data-bank-field="bank_name">
      <span>Bank / Institut</span>
      <input class="dg-bank-name" name="bank_accounts[<?= (int) $i ?>][bank_name]" value="<?= View::escape((string) ($account['bank_name'] ?? '')) ?>" autocomplete="off">
      <div class="dg-bank-suggest dg-bank-name-suggest" hidden></div>
    </label>

    <label class="dg-field dg-field--wide" data-bank-field="card_number_entry">
      <span>Kartennummer eingeben (nur zur Erkennung)</span>
      <input type="text" inputmode="numeric" autocomplete="off" data-card-entry name="bank_accounts[<?= (int) $i ?>][card_number_entry]" value="" placeholder="Nummer tippen oder einfügen — wird nicht gespeichert">
      <small class="dg-field-hint">Die volle Nummer wird <strong>nicht</strong> gespeichert (PCI). Nach Erkennung bleiben Maske, Kartenart und Institut. Für monatliche Einzüge: IBAN/SEPA oder Zahlungsdienst-Token (Stripe o. ä.).</small>
    </label>
    <label class="dg-field" data-bank-field="provider"><span>Kartennetz</span><input name="bank_accounts[<?= (int) $i ?>][provider]" value="<?= View::escape((string) ($account['provider'] ?? '')) ?>" placeholder="Visa, Mastercard …" data-card-provider></label>
    <label class="dg-field" data-bank-field="card_brand"><span>Kartenart (Code)</span><input name="bank_accounts[<?= (int) $i ?>][card_brand]" value="<?= View::escape((string) ($account['card_brand'] ?? '')) ?>" placeholder="visa / mastercard …" data-card-brand></label>
    <label class="dg-field" data-bank-field="card_issuer"><span>Karteninstitut</span><input name="bank_accounts[<?= (int) $i ?>][card_issuer]" value="<?= View::escape((string) ($account['card_issuer'] ?? '')) ?>" placeholder="z. B. DKB, N26 …" data-card-issuer></label>
    <label class="dg-field" data-bank-field="card_bin"><span>BIN (6–8 Ziffern)</span><input name="bank_accounts[<?= (int) $i ?>][card_bin]" value="<?= View::escape((string) ($account['card_bin'] ?? '')) ?>" maxlength="8" data-card-bin></label>
    <label class="dg-field" data-bank-field="card_number_masked"><span>Kartennummer (maskiert)</span><input name="bank_accounts[<?= (int) $i ?>][card_number_masked]" value="<?= View::escape((string) ($account['card_number_masked'] ?? '')) ?>" placeholder="**** **** **** 1234" data-card-masked readonly></label>
    <label class="dg-field" data-bank-field="card_last4"><span>Letzte 4 Ziffern</span><input name="bank_accounts[<?= (int) $i ?>][card_last4]" value="<?= View::escape((string) ($account['card_last4'] ?? '')) ?>" maxlength="4" data-card-last4></label>
    <label class="dg-field" data-bank-field="expiry"><span>Gültig bis</span><input name="bank_accounts[<?= (int) $i ?>][expiry]" value="<?= View::escape((string) ($account['expiry'] ?? '')) ?>" placeholder="MM/JJ"></label>

    <label class="dg-field" data-bank-field="email"><span>E-Mail (PayPal o. ä.)</span><input type="email" name="bank_accounts[<?= (int) $i ?>][email]" value="<?= View::escape((string) ($account['email'] ?? '')) ?>"></label>
    <label class="dg-field" data-bank-field="merchant_id"><span>Merchant-ID</span><input name="bank_accounts[<?= (int) $i ?>][merchant_id]" value="<?= View::escape((string) ($account['merchant_id'] ?? '')) ?>"></label>
    <label class="dg-field" data-bank-field="account_id"><span>Account-ID (Stripe)</span><input name="bank_accounts[<?= (int) $i ?>][account_id]" value="<?= View::escape((string) ($account['account_id'] ?? '')) ?>"></label>
    <label class="dg-field" data-bank-field="profile_id"><span>Profile-ID (Mollie)</span><input name="bank_accounts[<?= (int) $i ?>][profile_id]" value="<?= View::escape((string) ($account['profile_id'] ?? '')) ?>"></label>
    <label class="dg-field" data-bank-field="creditor_id"><span>Gläubiger-ID (SEPA)</span><input name="bank_accounts[<?= (int) $i ?>][creditor_id]" value="<?= View::escape((string) ($account['creditor_id'] ?? '')) ?>"></label>
  </div>
</div>
