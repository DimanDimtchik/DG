<?php
/**
 * @var array<string, mixed> $linkedinRecruitingForm
 * @var bool $dbConnected
 */
$form = is_array($linkedinRecruitingForm ?? null) ? $linkedinRecruitingForm : LinkedInRecruitingSettings::forForm();
$dbConnected = (bool) ($dbConnected ?? Database::isConfigured());
?>
<form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('linkedin-recruiting')) ?>">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
  <input type="hidden" name="linkedin_recruiting_save" value="1">

  <div class="dg-panel dg-panel--notice">
    <p>
      LinkedIn Job Posting veröffentlicht Stellen auf LinkedIn Jobs.
      <strong>Voraussetzung:</strong> freigegebene Developer-App (App-Review / Talent-Solutions-Partner).
      LinkedIn nimmt derzeit kaum neue Job-Posting-Partnerschaften an — Zugang oft über Apply Connect / Business Development.
    </p>
    <p class="dg-muted">
      Doku:
      <a href="https://learn.microsoft.com/en-us/linkedin/talent/job-postings/api/overview" target="_blank" rel="noopener">Job Posting API</a>
      · Community Management (Seiten) ist ein separates Produkt und hier nicht der Publish-Weg für Stellen.
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
          <input type="checkbox" name="linkedin_enabled" value="1"<?= !empty($form['enabled']) ? ' checked' : '' ?>>
          <span>LinkedIn-Export aktivieren</span>
        </span>
      </label>
      <label class="dg-field dg-field--checkbox dg-field--wide">
        <span class="dg-checkbox">
          <input type="checkbox" name="linkedin_app_review_approved" value="1"<?= !empty($form['app_review_approved']) ? ' checked' : '' ?>>
          <span>App-Review / Partner-Freigabe liegt vor (ohne Haken kein API-Publish)</span>
        </span>
      </label>
      <label class="dg-field dg-field--checkbox">
        <span class="dg-checkbox">
          <input type="checkbox" name="linkedin_auto_publish_queued" value="1"<?= !empty($form['auto_publish_queued']) ? ' checked' : '' ?>>
          <span>Vorgemerkte Stellen beim Speichern automatisch an LinkedIn senden</span>
        </span>
      </label>
      <label class="dg-field">
        <span>Anzeigentyp</span>
        <select name="linkedin_listing_type">
          <option value="BASIC"<?= ($form['listing_type'] ?? '') === 'BASIC' ? ' selected' : '' ?>>BASIC (aggregiert)</option>
          <option value="PREMIUM"<?= ($form['listing_type'] ?? '') === 'PREMIUM' ? ' selected' : '' ?>>PREMIUM (nur mit Slot-Freigabe)</option>
        </select>
      </label>
    </div>
  </section>

  <section class="dg-form-section">
    <h2 class="dg-subsection-title">Firmenseite</h2>
    <div class="dg-form-grid">
      <label class="dg-field">
        <span>LinkedIn Company ID *</span>
        <input type="text" name="linkedin_company_id" value="<?= View::escape((string) ($form['company_id'] ?? '')) ?>" maxlength="32" inputmode="numeric">
        <span class="dg-field-hint">Numerische ID der Unternehmensseite. URN: <?= View::escape((string) ($form['company_urn'] ?? '—')) ?></span>
      </label>
    </div>
  </section>

  <section class="dg-form-section">
    <h2 class="dg-subsection-title">OAuth2 (Client Credentials)</h2>
    <p class="dg-muted">Client ID/Secret der freigegebenen LinkedIn-App. Token wird bei Bedarf geholt und kurz zwischengespeichert.</p>
    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Client ID</span>
        <input type="text" name="linkedin_client_id" value="<?= View::escape((string) ($form['client_id'] ?? '')) ?>" maxlength="255" autocomplete="off">
      </label>
      <label class="dg-field">
        <span>Client Secret<?= !empty($form['client_secret_set']) ? ' (gesetzt — leer lassen zum Behalten)' : '' ?></span>
        <input type="password" name="linkedin_client_secret" value="" maxlength="255" autocomplete="new-password">
      </label>
      <div class="dg-field">
        <span>Token-Cache</span>
        <p class="dg-muted"><?= !empty($form['token_cached']) ? 'gültig' : 'leer / abgelaufen' ?></p>
      </div>
    </div>
  </section>

  <div class="dg-form-actions">
    <button type="submit" class="dg-button dg-button--primary">Speichern</button>
  </div>
</form>
