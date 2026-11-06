<?php
/**
 * @var array<string, mixed> $tseConfig
 * @var bool $dbConnected
 */
$cfg = $tseConfig ?? TseSettings::forForm();
$fromEnv = is_array($cfg['from_env'] ?? null) ? $cfg['from_env'] : [];
$enabled = !empty($cfg['enabled']);
$env = (string) ($cfg['environment'] ?? TseSettings::ENV_TEST);
?>
<form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('tse')) ?>">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

  <div class="dg-panel dg-panel--notice">
    <p>
      Barzahlungen auf Ausgangsrechnungen werden mit der technischen Sicherungseinrichtung (TSE) signiert.
      Der Prüf-QR-Code erscheint auf dem Belegdruck. Ohne eingeschaltete TSE bleibt die Kasse unverändert.
    </p>
  </div>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Datenbank nicht verbunden.</div>
  <?php endif; ?>

  <?php if (!empty($cfg['ready'])) : ?>
    <div class="dg-flash dg-flash--success">Zugangsdaten vollständig — Verbindung kann geprüft werden.</div>
  <?php elseif ($enabled) : ?>
    <div class="dg-flash dg-flash--warning">TSE ist eingeschaltet, aber Zugangsdaten fehlen.</div>
  <?php endif; ?>

  <fieldset class="dg-fieldset dg-form-grid"<?= !$dbConnected ? ' disabled' : '' ?>>
    <legend>Betrieb</legend>
    <label class="dg-field">
      <span>TSE für Barzahlungen</span>
      <select name="tse_enabled">
        <option value="0"<?= !$enabled ? ' selected' : '' ?>>Aus — keine Signatur</option>
        <option value="1"<?= $enabled ? ' selected' : '' ?>>An — Barzahlung nur mit Signatur</option>
      </select>
    </label>
    <label class="dg-field">
      <span>Umgebung</span>
      <select name="tse_environment">
        <option value="test"<?= $env === TseSettings::ENV_TEST ? ' selected' : '' ?>>Test</option>
        <option value="live"<?= $env === TseSettings::ENV_LIVE ? ' selected' : '' ?>>Live</option>
      </select>
      <small class="dg-field-hint">Live nur nach abgeschlossener TSE-Einrichtung und Freigabe.</small>
    </label>
  </fieldset>

  <fieldset class="dg-fieldset dg-form-grid"<?= !$dbConnected ? ' disabled' : '' ?>>
    <legend>Zugang</legend>
    <label class="dg-field dg-field--wide">
      <span>API-Schlüssel</span>
      <input type="password" name="tse_api_key" value="" autocomplete="new-password" spellcheck="false" placeholder="<?= !empty($cfg['api_key_set']) ? '•••••••• (unverändert lassen)' : '' ?>">
      <?php if (!empty($fromEnv['api_key'])) : ?>
        <small class="dg-field-hint">Aktuell aus der Server-Umgebung übernommen.</small>
      <?php endif; ?>
    </label>
    <label class="dg-field dg-field--wide">
      <span>API-Geheimnis</span>
      <input type="password" name="tse_api_secret" value="" autocomplete="new-password" placeholder="<?= !empty($cfg['api_secret_set']) ? '•••••••• (unverändert lassen)' : '' ?>">
      <?php if (!empty($fromEnv['api_secret'])) : ?>
        <small class="dg-field-hint">Aktuell aus der Server-Umgebung übernommen.</small>
      <?php endif; ?>
    </label>
    <label class="dg-field">
      <span>TSE-ID</span>
      <input type="text" name="tse_tss_id" value="<?= View::escape((string) ($cfg['tss_id'] ?? '')) ?>" spellcheck="false" placeholder="UUID">
      <?php if (!empty($fromEnv['tss_id'])) : ?>
        <small class="dg-field-hint">Aktuell aus der Server-Umgebung übernommen.</small>
      <?php endif; ?>
    </label>
    <label class="dg-field">
      <span>Kassen-ID</span>
      <input type="text" name="tse_client_id" value="<?= View::escape((string) ($cfg['client_id'] ?? '')) ?>" spellcheck="false" placeholder="UUID">
      <?php if (!empty($fromEnv['client_id'])) : ?>
        <small class="dg-field-hint">Aktuell aus der Server-Umgebung übernommen.</small>
      <?php endif; ?>
    </label>
  </fieldset>

  <p class="dg-form-actions">
    <button type="submit" name="tse_save" value="1" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>>Speichern</button>
    <button type="submit" name="tse_ping" value="1" class="dg-button"<?= !$dbConnected ? ' disabled' : '' ?>>Verbindung prüfen</button>
  </p>
</form>
