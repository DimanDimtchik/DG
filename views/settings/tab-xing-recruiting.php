<?php
/**
 * @var array<string, mixed> $xingRecruitingForm
 * @var bool $dbConnected
 */
$form = is_array($xingRecruitingForm ?? null) ? $xingRecruitingForm : XingRecruitingSettings::forForm();
$dbConnected = (bool) ($dbConnected ?? Database::isConfigured());
?>
<form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('xing-recruiting')) ?>">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
  <input type="hidden" name="xing_recruiting_save" value="1">

  <div class="dg-panel dg-panel--notice">
    <p>
      Xing E-Recruiting veröffentlicht Stellenanzeigen auf Xing Jobs.
      <strong>Voraussetzung:</strong> gültiger Kunden-Jobvertrag (organization_id und order_id von Xing)
      sowie eine freigegebene Developer-App mit Recht <code>vendor_jobs</code>.
    </p>
    <p class="dg-muted">
      Kontakt Xing: e-recruiting-api@xing.com · Doku:
      <a href="https://dev.xing.com/partners/job_integration/api_docs" target="_blank" rel="noopener">dev.xing.com</a>
    </p>
    <p><strong>Status:</strong> <?= View::escape((string) ($form['status_label'] ?? '')) ?></p>
  </div>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Datenbank nicht verbunden — Einstellungen können trotzdem gespeichert werden.</div>
  <?php endif; ?>

  <section class="dg-form-section">
    <h2 class="dg-subsection-title">Schalter</h2>
    <div class="dg-form-grid">
      <label class="dg-field dg-field--checkbox">
        <span class="dg-checkbox">
          <input type="checkbox" name="xing_enabled" value="1"<?= !empty($form['enabled']) ? ' checked' : '' ?>>
          <span>Xing-Export aktivieren</span>
        </span>
      </label>
      <label class="dg-field dg-field--checkbox">
        <span class="dg-checkbox">
          <input type="checkbox" name="xing_use_test_contract" value="1"<?= !empty($form['use_test_contract']) ? ' checked' : '' ?>>
          <span>Xing-Test-IDs nutzen (nur Entwicklung, Postings max. 1 Tag)</span>
        </span>
      </label>
      <label class="dg-field dg-field--checkbox">
        <span class="dg-checkbox">
          <input type="checkbox" name="xing_auto_activate" value="1"<?= !empty($form['auto_activate']) ? ' checked' : '' ?>>
          <span>Nach Anlegen sofort auf Xing aktivieren</span>
        </span>
      </label>
      <label class="dg-field dg-field--checkbox">
        <span class="dg-checkbox">
          <input type="checkbox" name="xing_auto_publish_queued" value="1"<?= !empty($form['auto_publish_queued']) ? ' checked' : '' ?>>
          <span>Vorgemerkte Stellen beim Speichern automatisch an Xing senden</span>
        </span>
      </label>
    </div>
  </section>

  <section class="dg-form-section">
    <h2 class="dg-subsection-title">Jobvertrag</h2>
    <p class="dg-muted">Ohne Vertrag blockiert das CRM den API-Publish. Test-IDs nur mit gesetztem Haken oben.</p>
    <div class="dg-form-grid">
      <label class="dg-field">
        <span>organization_id</span>
        <input type="text" name="xing_organization_id" value="<?= View::escape((string) ($form['organization_id'] ?? '')) ?>" maxlength="32" inputmode="numeric"<?= !empty($form['use_test_contract']) ? ' disabled' : '' ?>>
      </label>
      <label class="dg-field">
        <span>order_id</span>
        <input type="text" name="xing_order_id" value="<?= View::escape((string) ($form['order_id'] ?? '')) ?>" maxlength="32" inputmode="numeric"<?= !empty($form['use_test_contract']) ? ' disabled' : '' ?>>
      </label>
      <div class="dg-field dg-field--wide">
        <span>Wirksam</span>
        <p class="dg-muted">
          Org <?= View::escape((string) ($form['effective_organization_id'] ?? '—')) ?>
          · Order <?= View::escape((string) ($form['effective_order_id'] ?? '—')) ?>
        </p>
      </div>
    </div>
  </section>

  <section class="dg-form-section">
    <h2 class="dg-subsection-title">OAuth (App + Benutzer)</h2>
    <p class="dg-muted">Consumer Key/Secret aus dem Xing Developer Portal; Access Token nach Benutzer-Freigabe (OAuth 1.0a).</p>
    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Consumer Key</span>
        <input type="text" name="xing_consumer_key" value="<?= View::escape((string) ($form['consumer_key'] ?? '')) ?>" maxlength="255" autocomplete="off">
      </label>
      <label class="dg-field">
        <span>Consumer Secret<?= !empty($form['consumer_secret_set']) ? ' (gesetzt — leer lassen zum Behalten)' : '' ?></span>
        <input type="password" name="xing_consumer_secret" value="" maxlength="255" autocomplete="new-password">
      </label>
      <label class="dg-field">
        <span>Access Token<?= !empty($form['access_token_set']) ? ' (gesetzt — leer lassen zum Behalten)' : '' ?></span>
        <input type="password" name="xing_access_token" value="" maxlength="255" autocomplete="new-password">
      </label>
      <label class="dg-field">
        <span>Access Token Secret<?= !empty($form['access_token_secret_set']) ? ' (gesetzt — leer lassen zum Behalten)' : '' ?></span>
        <input type="password" name="xing_access_token_secret" value="" maxlength="255" autocomplete="new-password">
      </label>
    </div>
  </section>

  <section class="dg-form-section">
    <h2 class="dg-subsection-title">Anzeige auf Xing</h2>
    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Firmenname (optional)</span>
        <input type="text" name="xing_company_name" value="<?= View::escape((string) ($form['company_name'] ?? '')) ?>" maxlength="255" placeholder="sonst Firmendaten aus Einstellungen">
      </label>
      <label class="dg-field dg-field--wide">
        <span>Xing-Unternehmensprofil-URL (optional)</span>
        <input type="url" name="xing_company_profile_url" value="<?= View::escape((string) ($form['company_profile_url'] ?? '')) ?>" maxlength="500" placeholder="https://www.xing.com/pages/…">
      </label>
    </div>
  </section>

  <div class="dg-form-actions">
    <button type="submit" class="dg-button dg-button--primary">Speichern</button>
  </div>
</form>
