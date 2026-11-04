<?php
/**
 * @var array<string, mixed> $tiktokPublishForm
 * @var bool $dbConnected
 */
$form = is_array($tiktokPublishForm ?? null) ? $tiktokPublishForm : TikTokPublishSettings::forForm();
$dbConnected = (bool) ($dbConnected ?? Database::isConfigured());
?>
<form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('tiktok-publish')) ?>">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
  <input type="hidden" name="tiktok_publish_save" value="1">

  <div class="dg-panel dg-panel--notice">
    <p>
      TikTok Content Posting über Login Kit. Ohne Client-Audit nur
      <strong>Inbox / Draft</strong> (<code>video.upload</code>) —
      der Creator beendet den Post in der TikTok-App. Direct Post
      (<code>video.publish</code>) erst nach bestandenem Audit und
      klarer Produkt-Position als öffentliches Creator-/Marketing-CRM.
    </p>
    <p class="dg-muted">
      Doku:
      <a href="https://developers.tiktok.com/products/content-posting-api" target="_blank" rel="noopener">Content Posting</a>
      ·
      <a href="https://developers.tiktok.com/doc/content-sharing-guidelines" target="_blank" rel="noopener">Sharing Guidelines</a>
      ·
      <a href="https://developers.tiktok.com/doc/oauth-user-access-token-management" target="_blank" rel="noopener">OAuth</a>
    </p>
    <p><strong>Status:</strong> <?= View::escape((string) ($form['status_label'] ?? '')) ?></p>
    <?php if (!empty($form['direct_blocked'])) : ?>
      <p class="dg-muted">Direct gewählt, aber Audit-Häkchen fehlt — wirksam bleibt Inbox.</p>
    <?php endif; ?>
  </div>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Datenbank nicht verbunden — Einstellungen können trotzdem gespeichert werden.</div>
  <?php endif; ?>

  <section class="dg-form-section">
    <h2 class="dg-subsection-title">Schalter</h2>
    <div class="dg-form-grid">
      <label class="dg-field dg-field--checkbox dg-field--wide">
        <span class="dg-checkbox">
          <input type="checkbox" name="tiktok_publish_enabled" value="1"<?= !empty($form['enabled']) ? ' checked' : '' ?>>
          <span>TikTok-Publish aktivieren</span>
        </span>
      </label>
      <label class="dg-field">
        <span>Posting-Modus</span>
        <select name="tiktok_publish_posting_mode">
          <option value="inbox"<?= ($form['posting_mode'] ?? '') === 'inbox' ? ' selected' : '' ?>>Inbox / Draft (ohne Audit)</option>
          <option value="direct"<?= ($form['posting_mode'] ?? '') === 'direct' ? ' selected' : '' ?>>Direct Post (nur nach Audit)</option>
        </select>
        <span class="dg-field-hint">Wirksam: <?= View::escape((string) ($form['effective_mode'] ?? 'inbox')) ?></span>
      </label>
      <label class="dg-field dg-field--checkbox">
        <span class="dg-checkbox">
          <input type="checkbox" name="tiktok_publish_audit_ok" value="1"<?= !empty($form['audit_ok']) ? ' checked' : '' ?>>
          <span>Client-Audit bestanden</span>
        </span>
        <span class="dg-field-hint">Nur setzen, wenn TikTok Direct Post freigegeben hat.</span>
      </label>
      <label class="dg-field dg-field--wide">
        <span>Notiz zu App / Audit (intern)</span>
        <textarea name="tiktok_publish_app_review_note" rows="3"><?= View::escape((string) ($form['app_review_note'] ?? '')) ?></textarea>
        <span class="dg-field-hint">z. B. TT4D-App-Status, Audit-Termin — nicht öffentlich.</span>
      </label>
    </div>
  </section>

  <section class="dg-form-section">
    <h2 class="dg-subsection-title">TikTok for Developers App</h2>
    <div class="dg-form-grid">
      <label class="dg-field dg-field--wide">
        <span>Client Key</span>
        <input type="text" name="tiktok_publish_client_key" value="<?= View::escape((string) ($form['client_key'] ?? '')) ?>" autocomplete="off">
      </label>
      <label class="dg-field dg-field--wide">
        <span>Client Secret<?= !empty($form['client_secret_set']) ? ' (gesetzt — leer lassen zum Behalten)' : '' ?></span>
        <input type="password" name="tiktok_publish_client_secret" value="" autocomplete="new-password">
      </label>
      <label class="dg-field dg-field--wide">
        <span>Redirect-URI (in TT4D App eintragen)</span>
        <input type="text" readonly value="<?= View::escape((string) ($form['redirect_uri'] ?? '')) ?>">
      </label>
      <p class="dg-muted dg-field--wide">
        Scopes: <?= View::escape((string) ($form['scopes'] ?? '')) ?>
      </p>
    </div>
  </section>

  <div class="dg-form-actions">
    <button type="submit" class="dg-button dg-button--primary">Speichern</button>
  </div>
</form>

<?php if (!empty($form['credentials_ready'])) : ?>
  <section class="dg-panel dg-mt-4">
    <h2 class="dg-subsection-title">Creator-Konto verbinden</h2>
    <?php if (!empty($form['connected'])) : ?>
      <p class="dg-muted">
        Verbunden: <?= View::escape((string) ($form['account_label'] ?? '')) ?>
        (<?= View::escape((string) ($form['account_id'] ?? '')) ?>)
        <?php if (!empty($form['connected_at'])) : ?>
          · seit <?= View::escape((string) $form['connected_at']) ?>
        <?php endif; ?>
      </p>
      <div class="dg-btn-row">
        <form method="post" action="<?= View::escape(SettingsRegistry::tabUrl('tiktok-publish')) ?>" class="dg-form">
          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
          <input type="hidden" name="tiktok_oauth_disconnect" value="1">
          <button type="submit" class="dg-button dg-button--danger">Verbindung trennen</button>
        </form>
        <a class="dg-button" href="/oauth/tiktok/start">Erneut verbinden</a>
      </div>
    <?php else : ?>
      <div class="dg-btn-row">
        <a class="dg-button dg-button--primary" href="/oauth/tiktok/start">Mit TikTok verbinden</a>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>
