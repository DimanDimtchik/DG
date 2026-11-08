<?php
/**
 * @var array<string, mixed> $recruitingMailForm
 * @var bool $dbConnected
 */
$form = is_array($recruitingMailForm ?? null) ? $recruitingMailForm : RecruitingMailSettings::forForm();
$dbConnected = (bool) ($dbConnected ?? Database::isConfigured());
$effective = trim((string) ($form['effective_hr_email'] ?? ''));
$mailReady = !empty($form['mail_ready']);
?>
<form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('recruiting-mail')) ?>">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
  <input type="hidden" name="recruiting_mail_save" value="1">

  <div class="dg-panel dg-panel--notice">
    <p>
      Bei einer Bewerbung über die öffentliche Seite <code>/rekrut</code> kann das CRM die Personalstelle
      und den Bewerber per E-Mail informieren. Statuswechsel (Gespräch, Angebot, Zusage, Absage) können
      dem Bewerber ebenfalls mitgeteilt werden.
    </p>
    <?php if (!$mailReady) : ?>
      <p class="dg-muted">
        Versand ist erst möglich, wenn unter Kommunikation → E-Mail ein SMTP-Postfach eingerichtet ist.
      </p>
    <?php endif; ?>
  </div>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Datenbank nicht verbunden — Einstellungen können trotzdem gespeichert werden.</div>
  <?php endif; ?>

  <section class="dg-form-section">
    <h2 class="dg-subsection-title">HR / Personalstelle</h2>
    <div class="dg-form-grid">
      <label class="dg-field dg-field--checkbox dg-field--wide">
        <span>
          <input type="checkbox" name="recruiting_send_hr_on_apply" value="1"<?= !empty($form['send_hr_on_apply']) ? ' checked' : '' ?>>
          Bei neuer Bewerbung über die Website die Personalstelle benachrichtigen
        </span>
      </label>
      <label class="dg-field dg-field--wide">
        <span>Empfänger (leer = Firmen-E-Mail)</span>
        <input type="email" name="recruiting_notify_hr_email" value="<?= View::escape((string) ($form['notify_hr_email'] ?? '')) ?>" placeholder="personal@beispiel.de" autocomplete="off">
        <?php if ($effective !== '') : ?>
          <span class="dg-field-hint">Aktuell genutzt: <?= View::escape($effective) ?></span>
        <?php else : ?>
          <span class="dg-field-hint">Noch keine Firmen-E-Mail unter Grundlagen → Firmendaten hinterlegt.</span>
        <?php endif; ?>
      </label>
    </div>
  </section>

  <section class="dg-form-section">
    <h2 class="dg-subsection-title">Bewerber</h2>
    <div class="dg-form-grid">
      <label class="dg-field dg-field--checkbox dg-field--wide">
        <span>
          <input type="checkbox" name="recruiting_send_applicant_confirmation" value="1"<?= !empty($form['send_applicant_confirmation']) ? ' checked' : '' ?>>
          Eingangsbestätigung nach Bewerbung über die Website
        </span>
      </label>
      <label class="dg-field dg-field--checkbox dg-field--wide">
        <span>
          <input type="checkbox" name="recruiting_send_applicant_status" value="1"<?= !empty($form['send_applicant_status']) ? ' checked' : '' ?>>
          Bei Statuswechsel Gespräch, Angebot, Zusage oder Absage informieren
        </span>
      </label>
    </div>
    <p class="dg-muted">
      Interne Schritte wie „In Prüfung“ und „Zurückgezogen“ lösen keine Bewerber-Mail aus.
      Der Versand erscheint im Ereignisprotokoll der Bewerbung.
    </p>
  </section>

  <div class="dg-form-actions">
    <button type="submit" class="dg-button dg-button--primary">Speichern</button>
  </div>
</form>
