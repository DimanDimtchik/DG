<?php
/**
 * Einstellungen → Registerkasse FiBu: Zahlart → Konto (K15a).
 *
 * @var array{
 *   skr_type: string,
 *   accounts: array<string, string>,
 *   labels: array<string, string>,
 *   hints: array<string, string>
 * } $cashRegisterFiBuConfig
 * @var bool $dbConnected
 */
$cfg = $cashRegisterFiBuConfig ?? CashRegisterFiBuSettings::forForm();
$skr = (string) ($cfg['skr_type'] ?? 'skr03');
$accounts = is_array($cfg['accounts'] ?? null) ? $cfg['accounts'] : [];
$labels = is_array($cfg['labels'] ?? null) ? $cfg['labels'] : CashPaymentRepository::methodLabels();
$hints = is_array($cfg['hints'] ?? null) ? $cfg['hints'] : CashRegisterFiBuSettings::methodHints();
$skrLabel = ChartOfAccountsSettings::skrTypeOptions()[$skr] ?? $skr;
?>
<form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('registerkasse-fibu')) ?>">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

  <p class="dg-lead">
    Welche Konten die Registerkasse bei Bon-Abschluss je Zahlart belasten soll.
    Erlöse und Umsatzsteuer folgen später aus den Bon-Positionen — hier nur die Zahlungsseite.
  </p>

  <div class="dg-panel dg-panel--notice">
    <p>
      Aktiver Kontenrahmen:
      <a href="<?= View::escape(SettingsRegistry::tabUrl('chart-of-accounts')) ?>"><?= View::escape($skrLabel) ?></a>.
      Leere Felder oder Speichern ohne Änderung nutzen die Standardkonten dieses Rahmens.
    </p>
    <p>
      Das ist die Anbindung an die FiBu-Buchung — nicht das FiBu-Kassenbuch und nicht der
      Registerkassen-Tagesabschluss.
    </p>
  </div>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Zum Speichern ist eine funktionierende
      <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Datenbankverbindung</a> erforderlich.
    </div>
  <?php endif; ?>

  <fieldset class="dg-fieldset dg-form-grid"<?= !$dbConnected ? ' disabled' : '' ?>>
    <legend>Konten je Zahlart</legend>
    <?php foreach (CashRegisterFiBuSettings::PAYMENT_METHODS as $method) : ?>
      <label class="dg-field">
        <span><?= View::escape((string) ($labels[$method] ?? $method)) ?></span>
        <input
          type="text"
          name="accounts[<?= View::escape($method) ?>]"
          value="<?= View::escape((string) ($accounts[$method] ?? '')) ?>"
          maxlength="8"
          inputmode="numeric"
          pattern="\d*"
          autocomplete="off"
          placeholder="<?= View::escape((string) (CashRegisterFiBuSettings::defaultsForSkr($skr)[$method] ?? '')) ?>"
        >
        <small class="dg-field-hint"><?= View::escape((string) ($hints[$method] ?? '')) ?></small>
      </label>
    <?php endforeach; ?>
  </fieldset>

  <p class="dg-form-actions">
    <button type="submit" name="cash_register_fibu_save" value="1" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>>
      Konten speichern
    </button>
  </p>
</form>
