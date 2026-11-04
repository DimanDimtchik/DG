<?php
/**
 * @var array<string, mixed> $recruitingPrivacyForm
 * @var bool $dbConnected
 */
$form = is_array($recruitingPrivacyForm ?? null) ? $recruitingPrivacyForm : RecruitingPrivacySettings::forForm();
$dbConnected = (bool) ($dbConnected ?? Database::isConfigured());
?>
<form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('recruiting-privacy')) ?>">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
  <input type="hidden" name="recruiting_privacy_save" value="1">

  <div class="dg-panel dg-panel--notice">
    <p>
      Speicherdauer nach Absage und Texte auf der öffentlichen Bewerbungsseite.
      Checkliste für DSB/Anwalt: Dokumentation im Projektordner unter Recruiting-DSGVO.
    </p>
    <p><strong>Status:</strong> <?= View::escape((string) ($form['status_label'] ?? '')) ?>
      <?php if (!empty($form['dsb_signed_off_at'])) : ?>
        (<?= View::escape((string) $form['dsb_signed_off_at']) ?>)
      <?php endif; ?>
    </p>
  </div>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Datenbank nicht verbunden — Einstellungen können trotzdem gespeichert werden.</div>
  <?php endif; ?>

  <section class="dg-form-section">
    <h2 class="dg-subsection-title">Löschfrist nach Absage</h2>
    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Monate bis zur automatischen Löschung</span>
        <input type="number" name="recruiting_retention_months" min="1" max="24" required
               value="<?= (int) ($form['retention_months'] ?? 6) ?>">
        <span class="dg-field-hint">Standard 6 · erlaubt 1–24. Gilt für neue Absagen/Zurückziehungen, wenn kein eigenes Datum gesetzt ist.</span>
      </label>
    </div>
  </section>

  <section class="dg-form-section">
    <h2 class="dg-subsection-title">Öffentliche Bewerbungsseite</h2>
    <div class="dg-form-grid">
      <label class="dg-field dg-field--wide">
        <span>Hinweis Speicherdauer (leer = Standardtext)</span>
        <textarea name="recruiting_public_retention_hint" rows="4" maxlength="2000"><?= View::escape((string) ($form['public_retention_hint'] ?? '')) ?></textarea>
        <span class="dg-field-hint">Standard: <?= View::escape((string) ($form['default_retention_hint'] ?? '')) ?></span>
      </label>
      <label class="dg-field dg-field--wide">
        <span>Text der Pflicht-Checkbox (leer = Standard)</span>
        <textarea name="recruiting_public_consent_label" rows="3" maxlength="500"><?= View::escape((string) ($form['public_consent_label'] ?? '')) ?></textarea>
        <span class="dg-field-hint">Standard: <?= View::escape((string) ($form['default_consent_label'] ?? '')) ?></span>
      </label>
    </div>
  </section>

  <section class="dg-form-section">
    <h2 class="dg-subsection-title">Freigabe DSB / Anwalt</h2>
    <div class="dg-form-grid">
      <label class="dg-field dg-field--checkbox dg-field--wide">
        <span class="dg-checkbox">
          <input type="checkbox" name="recruiting_dsb_signed_off" value="1"<?= !empty($form['dsb_signed_off']) ? ' checked' : '' ?>>
          <span>Fristen und Texte wurden mit DSB bzw. Anwalt abgestimmt</span>
        </span>
      </label>
      <label class="dg-field dg-field--wide">
        <span>Interne Notiz (optional)</span>
        <textarea name="recruiting_dsb_note" rows="3" maxlength="2000"><?= View::escape((string) ($form['dsb_note'] ?? '')) ?></textarea>
      </label>
    </div>
  </section>

  <div class="dg-form-actions">
    <button type="submit" class="dg-button dg-button--primary">Speichern</button>
  </div>
</form>
