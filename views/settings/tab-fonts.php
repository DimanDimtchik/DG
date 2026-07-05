<?php
/** @var array<string, string> $appearanceConfig */
$fontOptions = AppearanceSettings::fontOptions();
?>
<form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('schriften')) ?>">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

  <p class="dg-lead">
    Schriftarten für die <strong>CRM-Oberfläche</strong> und für <strong>HTML-E-Mails</strong>.
    Google Fonts werden nur geladen, wenn Sie eine Web-Schrift wählen.
  </p>

  <h3 class="dg-subsection-title">CRM-Oberfläche</h3>
  <div class="dg-form-grid">
    <label class="dg-field dg-field--wide">
      <span>Schriftart</span>
      <select name="ui_font" data-font-select="ui">
        <?php foreach ($fontOptions as $value => $label) : ?>
          <option value="<?= View::escape($value) ?>"<?= ($appearanceConfig['ui_font'] ?? '') === $value ? ' selected' : '' ?>><?= View::escape($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="dg-field dg-field--wide" data-font-custom="ui"<?= ($appearanceConfig['ui_font'] ?? '') === 'custom' ? '' : ' hidden' ?>>
      <span>Eigene Schrift (CSS font-family)</span>
      <input type="text" name="custom_ui_font" value="<?= View::escape($appearanceConfig['custom_ui_font'] ?? '') ?>" placeholder='"IBM Plex Sans", system-ui, sans-serif'>
    </label>
  </div>

  <div class="dg-font-preview dg-font-preview--ui">
    <p class="dg-font-preview__label">Vorschau CRM</p>
    <p class="dg-font-preview__sample" data-font-preview="ui">Ganz OM — Kontakte, Termine und Einstellungen</p>
  </div>

  <h3 class="dg-subsection-title">E-Mails (HTML)</h3>
  <div class="dg-form-grid">
    <label class="dg-field dg-field--wide">
      <span>Schriftart</span>
      <select name="email_font" data-font-select="email">
        <?php foreach ($fontOptions as $value => $label) : ?>
          <option value="<?= View::escape($value) ?>"<?= ($appearanceConfig['email_font'] ?? '') === $value ? ' selected' : '' ?>><?= View::escape($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="dg-field" data-font-custom="email"<?= ($appearanceConfig['email_font'] ?? '') === 'custom' ? '' : ' hidden' ?>>
      <span>Eigene Schrift (CSS font-family)</span>
      <input type="text" name="custom_email_font" value="<?= View::escape($appearanceConfig['custom_email_font'] ?? '') ?>">
    </label>
    <label class="dg-field">
      <span>Schriftgröße (px)</span>
      <input type="number" name="email_font_size" value="<?= (int) ($appearanceConfig['email_font_size'] ?? 16) ?>" min="12" max="24">
    </label>
  </div>

  <div class="dg-font-preview dg-font-preview--email">
    <p class="dg-font-preview__label">Vorschau E-Mail</p>
    <div class="dg-font-preview__sample" data-font-preview="email">
      <p><strong>Terminbestätigung</strong></p>
      <p>Ihr Termin am Freitag, 10:00 Uhr ist eingetragen.</p>
    </div>
  </div>

  <div class="dg-form-actions">
    <button type="submit" name="appearance_save" value="1" class="dg-button dg-button--primary">Speichern</button>
  </div>
</form>
