<?php
/**
 * @var array<string, mixed> $ldapConfig
 * @var bool $dbConnected
 */
$cfg = $ldapConfig ?? LdapSettings::forForm();
$readiness = is_array($cfg['readiness'] ?? null) ? $cfg['readiness'] : LdapAuthenticator::readiness();
$supportsLdap = (bool) ($cfg['server_supports_ldap'] ?? false);
$mode = (string) ($cfg['mode'] ?? LdapSettings::MODE_LOCAL);
$roleOptions = CrmRole::options();
?>
<form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('ldap')) ?>">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

  <div class="dg-panel dg-panel--notice">
    <p>
      <strong>Aktuell:</strong> Anmeldung nur über lokale CRM-Benutzer (<code>dg_users</code>).
      LDAP-Client und WordPress-Plugin <code>dg-user</code> sind vorbereitet — Live-Betrieb erst nach
      Umzug auf Root-Server (php-ldap + erreichbarer LDAP-Host).
    </p>
    <p>
      Anleitung: <code>docs/LDAP-INTEGRATION.md</code> · Migration <code>065_user_auth_source.sql</code>
    </p>
  </div>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Datenbank nicht verbunden.</div>
  <?php endif; ?>

  <fieldset class="dg-form-grid"<?= !$dbConnected ? ' disabled' : '' ?>>
    <legend>Betriebsmodus (CRM)</legend>
    <label class="dg-field dg-field--wide">
      <span>Anmeldemodus</span>
      <select name="ldap_mode">
        <option value="local"<?= $mode === LdapSettings::MODE_LOCAL ? ' selected' : '' ?>>
          Lokal — nur CRM-Passwörter (Standard)
        </option>
        <option value="hybrid"<?= $mode === LdapSettings::MODE_HYBRID ? ' selected' : '' ?> <?= !$supportsLdap ? ' disabled' : '' ?>>
          Hybrid — LDAP zuerst, sonst lokales Passwort
        </option>
        <option value="ldap_only"<?= $mode === LdapSettings::MODE_LDAP_ONLY ? ' selected' : '' ?> <?= !$supportsLdap ? ' disabled' : '' ?>>
          Nur LDAP — lokale Passwörter deaktiviert
        </option>
      </select>
      <?php if (!$supportsLdap) : ?>
        <small class="dg-field-hint">
          LDAP ist auf diesem Server noch nicht verfügbar.
          <code>config/ldap.local.php</code> anlegen und <code>enabled = true</code> setzen (nach Umzug).
        </small>
      <?php endif; ?>
    </label>

    <label class="dg-field">
      <span>Neue LDAP-Benutzer anlegen (JIT)</span>
      <select name="ldap_jit_provision">
        <option value="1"<?= !empty($cfg['jit_provision']) ? ' selected' : '' ?>>Ja — bei erstem Login</option>
        <option value="0"<?= empty($cfg['jit_provision']) ? ' selected' : '' ?>>Nein — nur bestehende CRM-Konten</option>
      </select>
    </label>

    <label class="dg-field">
      <span>Standard-Rolle (JIT / ohne Gruppen-Mapping)</span>
      <select name="ldap_default_role">
        <?php foreach ($roleOptions as $slug => $label) : ?>
          <option value="<?= View::escape($slug) ?>"<?= ($cfg['default_role'] ?? '') === $slug ? ' selected' : '' ?>>
            <?= View::escape($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
  </fieldset>

  <section class="dg-panel">
    <h3 class="dg-subsection-title">Server-Konfiguration (nur lesen)</h3>
    <dl class="dg-dl">
      <div><dt>ldap.local.php</dt><dd><?= !empty($cfg['local_configured']) ? 'vorhanden' : 'fehlt (Beispiel: config/ldap.local.php.example)' ?></dd></div>
      <div><dt>LDAP aktiviert</dt><dd><?= !empty($cfg['local_enabled']) ? 'ja' : 'nein' ?></dd></div>
      <div><dt>Host</dt><dd><?= View::escape((string) ($cfg['host'] ?? '—')) ?></dd></div>
      <div><dt>WordPress dg-user (optional)</dt><dd><?= View::escape((string) ($cfg['wordpress_base_url'] ?? '—')) ?></dd></div>
    </dl>
    <p class="dg-muted">Bind-Passwort und API-Token nur in <code>config/ldap.local.php</code> — nicht in der Datenbank.</p>
  </section>

  <section class="dg-panel">
    <h3 class="dg-subsection-title">Readiness-Check</h3>
    <ul>
      <?php foreach ($readiness['items'] as $item) : ?>
        <li>
          <span class="dg-badge <?= !empty($item['ok']) ? 'dg-badge--ok' : 'dg-badge--pending' ?>">
            <?= !empty($item['ok']) ? 'OK' : 'Offen' ?>
          </span>
          <?= View::escape((string) ($item['label'] ?? '')) ?>
          — <?= View::escape((string) ($item['detail'] ?? '')) ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="dg-muted">CLI: <code>php bin/ldap-readiness.php</code></p>
  </section>

  <p class="dg-form-actions">
    <button type="submit" name="ldap_save" value="1" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>>Vorbereitung speichern</button>
  </p>
</form>

<section class="dg-panel">
  <h3 class="dg-subsection-title">All-Inkl vs. Ziel-Server</h3>
  <dl class="dg-dl">
    <div><dt>Kasserver (jetzt)</dt><dd>php-ldap meist fehlt · kein LDAP-Server · Modus bleibt lokal</dd></div>
    <div><dt>Hetzner / VPS (Ziel)</dt><dd>OpenLDAP oder FreeIPA · TLS · CRM als LDAP-Client</dd></div>
    <div><dt>WordPress</dt><dd>Plugin dg-user synchronisiert Benutzer — REST-URL in ldap.local.php</dd></div>
  </dl>
</section>
