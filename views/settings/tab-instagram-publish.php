<?php
/**
 * @var array<string, mixed> $instagramPublishForm
 * @var bool $dbConnected
 */
$form = is_array($instagramPublishForm ?? null) ? $instagramPublishForm : InstagramPublishSettings::forForm();
$dbConnected = (bool) ($dbConnected ?? Database::isConfigured());
?>
<form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('instagram-publish')) ?>">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
  <input type="hidden" name="instagram_publish_save" value="1">

  <div class="dg-panel dg-panel--notice">
    <p>
      Instagram <strong>Professional</strong>-Konto (Business/Creator) für Reels über die Meta Graph API.
      Meta-App unter dem Business Portfolio des Seitenbetreibers; für fremde Konten meist
      <strong>Business Verification</strong> und <strong>Advanced Access</strong> für
      Publish-, Kommentar- und Insights-Rechte. Nach Scope-Erweiterung bitte erneut verbinden.
    </p>
    <p class="dg-muted">
      Doku:
      <a href="https://developers.facebook.com/docs/instagram-platform/instagram-graph-api/content-publishing/" target="_blank" rel="noopener">Content Publishing</a>
      ·
      <a href="https://developers.facebook.com/docs/instagram-platform/app-review/" target="_blank" rel="noopener">App Review</a>
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
          <input type="checkbox" name="instagram_publish_enabled" value="1"<?= !empty($form['enabled']) ? ' checked' : '' ?>>
          <span>Instagram-Publish aktivieren</span>
        </span>
      </label>
      <label class="dg-field">
        <span>Login-Pfad</span>
        <select name="instagram_publish_login_path">
          <option value="facebook"<?= ($form['login_path'] ?? '') === 'facebook' ? ' selected' : '' ?>>Facebook Login (Page, empfohlen)</option>
          <option value="instagram"<?= ($form['login_path'] ?? '') === 'instagram' ? ' selected' : '' ?>>Instagram Login</option>
        </select>
        <span class="dg-field-hint">Facebook-Pfad braucht verknüpfte Page; besser für späteres Löschen.</span>
      </label>
      <label class="dg-field">
        <span>Access-Stufe</span>
        <select name="instagram_publish_access_level">
          <option value="standard"<?= ($form['access_level'] ?? '') === 'standard' ? ' selected' : '' ?>>Standard (App-Rollen / eigene Tests)</option>
          <option value="advanced"<?= ($form['access_level'] ?? '') === 'advanced' ? ' selected' : '' ?>>Advanced Access (fremde Tenants)</option>
        </select>
      </label>
      <label class="dg-field dg-field--wide">
        <span>Notiz zu Verification / Review (intern)</span>
        <textarea name="instagram_publish_app_review_note" rows="3"><?= View::escape((string) ($form['app_review_note'] ?? '')) ?></textarea>
        <span class="dg-field-hint">z. B. BV-Status, Advanced-Antrag, Screencast — nicht öffentlich.</span>
      </label>
    </div>
  </section>

  <section class="dg-form-section">
    <h2 class="dg-subsection-title">Konto (optional pinnen)</h2>
    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Facebook Page-ID</span>
        <input type="text" name="instagram_publish_facebook_page_id" value="<?= View::escape((string) ($form['facebook_page_id'] ?? '')) ?>" maxlength="32" inputmode="numeric" autocomplete="off">
        <span class="dg-field-hint">Nur Facebook-Login-Pfad. Leer = erste Page mit IG Professional.</span>
      </label>
      <label class="dg-field">
        <span>Instagram User-ID</span>
        <input type="text" name="instagram_publish_ig_user_id" value="<?= View::escape((string) ($form['ig_user_id'] ?? '')) ?>" maxlength="32" inputmode="numeric" autocomplete="off">
        <span class="dg-field-hint">Optional — Connect prüft Übereinstimmung.</span>
      </label>
    </div>
  </section>

  <section class="dg-form-section">
    <h2 class="dg-subsection-title">Meta App</h2>
    <div class="dg-form-grid">
      <label class="dg-field dg-field--wide">
        <span>App-ID</span>
        <input type="text" name="instagram_publish_app_id" value="<?= View::escape((string) ($form['app_id'] ?? '')) ?>" autocomplete="off">
      </label>
      <label class="dg-field dg-field--wide">
        <span>App-Secret<?= !empty($form['app_secret_set']) ? ' (gesetzt — leer lassen zum Behalten)' : '' ?></span>
        <input type="password" name="instagram_publish_app_secret" value="" autocomplete="new-password">
      </label>
      <label class="dg-field dg-field--wide">
        <span>Redirect-URI (in Meta App eintragen)</span>
        <input type="text" readonly value="<?= View::escape((string) ($form['redirect_uri'] ?? '')) ?>">
      </label>
      <p class="dg-muted dg-field--wide">
        Scopes: <?= View::escape((string) ($form['scopes'] ?? '')) ?>
        · Graph: <?= View::escape((string) ($form['graph_version'] ?? '')) ?>
      </p>
    </div>
  </section>

  <div class="dg-form-actions">
    <button type="submit" class="dg-button dg-button--primary">Speichern</button>
  </div>
</form>

<?php if (!empty($form['credentials_ready'])) : ?>
  <section class="dg-panel dg-mt-4">
    <h2 class="dg-subsection-title">Professional-Konto verbinden</h2>
    <?php if (!empty($form['connected'])) : ?>
      <p class="dg-muted">
        Verbunden: <?= View::escape((string) ($form['account_label'] ?? '')) ?>
        (<?= View::escape((string) ($form['account_id'] ?? '')) ?>)
        <?php if (!empty($form['connected_at'])) : ?>
          · seit <?= View::escape((string) $form['connected_at']) ?>
        <?php endif; ?>
      </p>
      <div class="dg-btn-row">
        <form method="post" action="<?= View::escape(SettingsRegistry::tabUrl('instagram-publish')) ?>" class="dg-form">
          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
          <input type="hidden" name="instagram_oauth_disconnect" value="1">
          <button type="submit" class="dg-button dg-button--danger">Verbindung trennen</button>
        </form>
        <a class="dg-button" href="/oauth/instagram/start">Erneut verbinden</a>
      </div>
    <?php else : ?>
      <div class="dg-btn-row">
        <a class="dg-button dg-button--primary" href="/oauth/instagram/start">Mit Instagram verbinden</a>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>
