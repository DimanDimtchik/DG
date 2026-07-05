<?php
/** @var array<string, mixed> $calendarEmbedConfig */
/** @var bool $dbConnected */
$calendarEmbedConfig = $calendarEmbedConfig ?? CalendarEmbedSettings::forForm();
$publicUrl = (string) ($calendarEmbedConfig['public_url'] ?? CalendarEmbedSettings::publicBookingUrl());
$isEnabled = !empty($calendarEmbedConfig['online_booking_enabled']);
?>
<form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('kalender-einbindung')) ?>">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Zum Speichern ist eine funktionierende <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Datenbankverbindung</a> erforderlich.
    </div>
  <?php endif; ?>

  <p class="dg-lead">
    Öffentliche Buchungsseite für Kunden — unabhängig vom CRM-Login. Design und Farben kommen aus
    <a href="<?= View::escape(SettingsRegistry::tabUrl('kalender-darstellung')) ?>">Kalender Design</a>.
  </p>

  <div class="dg-form-grid">
    <label class="dg-field dg-field--wide">
      <span>
        <input type="checkbox" name="online_booking_enabled" value="1"<?= $isEnabled ? ' checked' : '' ?><?= !$dbConnected ? ' disabled' : '' ?>>
        Terminvereinbarung online stellen
      </span>
      <small class="dg-field-hint">Wenn aktiv, ist die öffentliche Seite erreichbar und Kunden können Termine buchen.</small>
    </label>
    <label class="dg-field dg-field--wide">
      <span>Seitentitel</span>
      <input type="text" name="page_title" value="<?= View::escape((string) ($calendarEmbedConfig['page_title'] ?? '')) ?>" placeholder="Termin online vereinbaren"<?= !$dbConnected ? ' disabled' : '' ?>>
    </label>
    <label class="dg-field dg-field--wide">
      <span>Einleitungstext</span>
      <textarea name="intro_text" rows="3"<?= !$dbConnected ? ' disabled' : '' ?>><?= View::escape((string) ($calendarEmbedConfig['intro_text'] ?? '')) ?></textarea>
    </label>
    <label class="dg-field dg-field--wide">
      <span>Bestätigungstext nach Buchung</span>
      <textarea name="success_message" rows="2"<?= !$dbConnected ? ' disabled' : '' ?>><?= View::escape((string) ($calendarEmbedConfig['success_message'] ?? '')) ?></textarea>
    </label>
  </div>

  <div class="dg-panel dg-mail-signature-rules" style="margin-top:8px">
    <h4 class="dg-subsection-title">Öffentliche Adresse</h4>
    <p class="dg-field-hint">Diese URL können Sie auf Ihrer Webseite verlinken oder als QR-Code verwenden.</p>
    <label class="dg-field dg-field--wide">
      <span>Link zur Buchungsseite</span>
      <input type="text" class="dg-input-copy" readonly value="<?= View::escape($publicUrl) ?>" onclick="this.select()">
    </label>
    <p class="dg-form-actions" style="margin-top:0">
      <a class="dg-button" href="<?= View::escape($publicUrl) ?>" target="_blank" rel="noopener">Seite öffnen</a>
      <?php if ($isEnabled) : ?>
        <span class="dg-badge dg-badge--ok">Online-Buchung aktiv</span>
      <?php else : ?>
        <span class="dg-badge">Deaktiviert — Seite zeigt Hinweis</span>
      <?php endif; ?>
    </p>
  </div>

  <div class="dg-form-actions">
    <button type="submit" name="calendar_embed_save" value="1" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>>Speichern</button>
  </div>
</form>
