<?php
/**
 * @var array<string, mixed> $linkedinPublishForm
 * @var bool $dbConnected
 */
$form = is_array($linkedinPublishForm ?? null) ? $linkedinPublishForm : LinkedInPublishSettings::forForm();
$dbConnected = (bool) ($dbConnected ?? Database::isConfigured());
?>
<form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('linkedin-publish')) ?>">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
  <input type="hidden" name="linkedin_publish_save" value="1">

  <div class="dg-panel dg-panel--notice">
    <p>
      LinkedIn <strong>Community Management</strong> für Video-Posts, Kommentare und Insights auf der Unternehmensseite.
      Getrennt vom Stellenexport (Job Posting). App muss unter einer
      <strong>juristischen Person</strong> des Seitenbetreibers laufen; Produktion braucht später Standard Tier + Screencast.
      Nach Scope-Erweiterung bitte erneut verbinden.
    </p>
    <p class="dg-muted">
      Doku:
      <a href="https://learn.microsoft.com/en-us/linkedin/marketing/community-management-app-review?view=li-lms-2026-06" target="_blank" rel="noopener">App Review</a>
      ·
      <a href="https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/videos-api?view=li-lms-2026-10" target="_blank" rel="noopener">Videos API</a>
    </p>
    <p><strong>Status:</strong> <?= View::escape((string) ($form['status_label'] ?? '')) ?></p>
  </div>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Datenbank nicht verbunden — Einstellungen können trotzdem gespeichert werden.</div>
  <?php endif; ?>

  <section class="dg-form-section">
    <h2 class="dg-subsection-title">Schalter</h2>
    <div class="dg-form-grid">
      <label class="dg-field dg-field--checkbox dg-field--wide">
        <span class="dg-checkbox">
          <input type="checkbox" name="linkedin_publish_enabled" value="1"<?= !empty($form['enabled']) ? ' checked' : '' ?>>
          <span>LinkedIn-Video-Publish aktivieren</span>
        </span>
      </label>
      <label class="dg-field">
        <span>Review-Stufe</span>
        <select name="linkedin_publish_review_tier">
          <option value="none"<?= ($form['review_tier'] ?? '') === 'none' ? ' selected' : '' ?>>Noch keine Freigabe</option>
          <option value="development"<?= ($form['review_tier'] ?? '') === 'development' ? ' selected' : '' ?>>Development Tier</option>
          <option value="standard"<?= ($form['review_tier'] ?? '') === 'standard' ? ' selected' : '' ?>>Standard Tier</option>
        </select>
      </label>
      <label class="dg-field dg-field--wide">
        <span>Notiz zur App-Freigabe (intern)</span>
        <textarea name="linkedin_publish_app_review_note" rows="3"><?= View::escape((string) ($form['app_review_note'] ?? '')) ?></textarea>
        <span class="dg-field-hint">z. B. Antragsdatum, Screencast-Status — nicht öffentlich.</span>
      </label>
    </div>
  </section>

  <section class="dg-form-section">
    <h2 class="dg-subsection-title">Unternehmensseite</h2>
    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Organization ID</span>
        <input type="text" name="linkedin_publish_organization_id" value="<?= View::escape((string) ($form['organization_id'] ?? '')) ?>" maxlength="32" inputmode="numeric" autocomplete="off">
        <?php $orgUrn = trim((string) ($form['organization_urn'] ?? '')); ?>
        <span class="dg-field-hint">Numerische ID. URN: <?= View::escape($orgUrn !== '' ? $orgUrn : '—') ?></span>
      </label>
    </div>
  </section>

  <section class="dg-form-section">
    <h2 class="dg-subsection-title">OAuth-Client (Web)</h2>
    <div class="dg-form-grid">
      <label class="dg-field dg-field--wide">
        <span>Client-ID</span>
        <input type="text" name="linkedin_publish_client_id" value="<?= View::escape((string) ($form['client_id'] ?? '')) ?>" autocomplete="off">
      </label>
      <label class="dg-field dg-field--wide">
        <span>Client-Secret<?= !empty($form['client_secret_set']) ? ' (gesetzt — leer lassen zum Behalten)' : '' ?></span>
        <input type="password" name="linkedin_publish_client_secret" value="" autocomplete="new-password">
      </label>
      <label class="dg-field dg-field--wide">
        <span>Redirect-URI (in LinkedIn App eintragen)</span>
        <input type="text" readonly value="<?= View::escape((string) ($form['redirect_uri'] ?? '')) ?>">
      </label>
      <p class="dg-muted dg-field--wide">
        Scopes: <?= View::escape((string) ($form['scopes'] ?? '')) ?>
        · API-Version (später): <?= View::escape((string) ($form['api_version'] ?? '')) ?>
      </p>
    </div>
  </section>

  <div class="dg-form-actions">
    <button type="submit" class="dg-button dg-button--primary">Speichern</button>
  </div>
</form>

<?php if (!empty($form['credentials_ready'])) : ?>
  <section class="dg-panel dg-mt-4">
    <h2 class="dg-subsection-title">Page-Admin verbinden</h2>
    <?php if (!empty($form['connected'])) : ?>
      <p class="dg-muted">
        Verbunden: <?= View::escape((string) ($form['account_label'] ?? '')) ?>
        (<?= View::escape((string) ($form['account_id'] ?? '')) ?>)
        <?php if (!empty($form['connected_at'])) : ?>
          · seit <?= View::escape((string) $form['connected_at']) ?>
        <?php endif; ?>
      </p>
      <div class="dg-btn-row">
        <form method="post" action="<?= View::escape(SettingsRegistry::tabUrl('linkedin-publish')) ?>" class="dg-form">
          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
          <input type="hidden" name="linkedin_oauth_disconnect" value="1">
          <button type="submit" class="dg-button dg-button--danger">Verbindung trennen</button>
        </form>
        <a class="dg-button" href="/oauth/linkedin/start">Erneut verbinden</a>
      </div>
    <?php else : ?>
      <div class="dg-btn-row">
        <a class="dg-button dg-button--primary" href="/oauth/linkedin/start">Mit LinkedIn verbinden</a>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>
