<?php
/**
 * @var array<string, mixed> $youtubePublishForm
 * @var bool $dbConnected
 */
$form = is_array($youtubePublishForm ?? null) ? $youtubePublishForm : YouTubePublishSettings::forForm();
$dbConnected = (bool) ($dbConnected ?? Database::isConfigured());
?>
<form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('youtube-publish')) ?>">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
  <input type="hidden" name="youtube_publish_save" value="1">

  <div class="dg-panel dg-panel--notice">
    <p>
      YouTube Data API v3: GCP-Projekt anlegen, YouTube Data API aktivieren, OAuth-Client (Web)
      mit Redirect-URI unten eintragen. Consent zuerst im Testmodus — Production braucht Google-Verification.
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
          <input type="checkbox" name="youtube_enabled" value="1"<?= !empty($form['enabled']) ? ' checked' : '' ?>>
          <span>YouTube-Publish aktivieren</span>
        </span>
      </label>
      <label class="dg-field">
        <span>OAuth-Modus</span>
        <select name="youtube_oauth_mode">
          <option value="testing"<?= ($form['oauth_mode'] ?? '') === 'testing' ? ' selected' : '' ?>>Testing (Google Consent)</option>
          <option value="production"<?= ($form['oauth_mode'] ?? '') === 'production' ? ' selected' : '' ?>>Production (Verification nötig)</option>
        </select>
      </label>
    </div>
  </section>

  <section class="dg-form-section">
    <h2 class="dg-subsection-title">GCP OAuth-Client</h2>
    <div class="dg-form-grid">
      <label class="dg-field dg-field--wide">
        <span>Client-ID</span>
        <input type="text" name="youtube_client_id" value="<?= View::escape((string) ($form['client_id'] ?? '')) ?>" autocomplete="off">
      </label>
      <label class="dg-field dg-field--wide">
        <span>Client-Secret<?= !empty($form['client_secret_set']) ? ' (gesetzt — leer lassen zum Behalten)' : '' ?></span>
        <input type="password" name="youtube_client_secret" value="" autocomplete="new-password">
      </label>
      <label class="dg-field dg-field--wide">
        <span>Redirect-URI (in GCP eintragen)</span>
        <input type="text" readonly value="<?= View::escape((string) ($form['redirect_uri'] ?? '')) ?>">
      </label>
      <p class="dg-muted dg-field--wide">Scopes: <?= View::escape((string) ($form['scopes'] ?? '')) ?></p>
    </div>
  </section>

  <div class="dg-form-actions">
    <button type="submit" class="dg-button dg-button--primary">Speichern</button>
  </div>
</form>

<?php if (!empty($form['credentials_ready'])) : ?>
  <section class="dg-panel dg-mt-4">
    <h2 class="dg-subsection-title">Kanal verbinden</h2>
    <?php if (!empty($form['connected'])) : ?>
      <p class="dg-muted">
        Verbunden: <?= View::escape((string) ($form['channel_label'] ?? '')) ?>
        (<?= View::escape((string) ($form['channel_id'] ?? '')) ?>)
        <?php if (!empty($form['connected_at'])) : ?>
          · seit <?= View::escape((string) $form['connected_at']) ?>
        <?php endif; ?>
      </p>
      <div class="dg-btn-row">
        <form method="post" action="<?= View::escape(SettingsRegistry::tabUrl('youtube-publish')) ?>" class="dg-form">
          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
          <input type="hidden" name="youtube_oauth_disconnect" value="1">
          <button type="submit" class="dg-button dg-button--danger">Verbindung trennen</button>
        </form>
        <a class="dg-button" href="/oauth/youtube/start">Erneut verbinden</a>
      </div>
    <?php else : ?>
      <div class="dg-btn-row">
        <a class="dg-button dg-button--primary" href="/oauth/youtube/start">Mit YouTube verbinden</a>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>
