<?php
/**
 * @var array<string, string> $calendarAppearanceConfig
 * @var bool $dbConnected
 */
$presets = CalendarColorPresets::presets();
$fields = CalendarAppearanceSettings::fieldDefinitions();
$presetColors = [];
foreach ($presets as $presetId => $preset) {
    $presetColors[$presetId] = $preset['colors'];
}
?>
<div class="dg-form">
  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Zum Speichern ist eine funktionierende <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Datenbankverbindung</a> erforderlich.
    </div>
  <?php endif; ?>

  <p class="dg-lead">
    Kalender Design für den öffentlichen Buchungskalender (Shortcode, Einbindung). Vorlagen oder eigene Farben —
    die Vorschau zeigt Buttons und Termin-Slots wie auf der Webseite.
    Stylesheet für Einbindungen: <code>/api/calendar-theme.css</code>
  </p>

  <section class="dg-cal-color-presets">
    <h3 class="dg-subsection-title">Farbvorlagen</h3>
    <p class="dg-field-hint">Vorlage wählen, Felder werden ausgefüllt. Anschließend „Kalender Design speichern“ klicken.</p>
    <div class="dg-cal-color-presets__grid">
      <?php foreach ($presets as $presetId => $preset) : ?>
        <button
          type="button"
          class="dg-cal-color-preset"
          data-preset-id="<?= View::escape($presetId) ?>"
        >
          <span class="dg-cal-color-preset__swatch" style="background-color: <?= View::escape($preset['colors']['primary_color']) ?>;"></span>
          <span class="dg-cal-color-preset__name"><?= View::escape($preset['name']) ?></span>
          <span class="dg-cal-color-preset__desc"><?= View::escape($preset['desc']) ?></span>
        </button>
      <?php endforeach; ?>
    </div>
    <p id="dg-cal-preset-notice" class="dg-cal-preset-notice" hidden aria-live="polite"></p>
  </section>

  <div class="dg-cal-appearance-layout">
    <form
      class="dg-form dg-cal-appearance-form"
      method="post"
      action="<?= View::escape(SettingsRegistry::tabUrl('kalender-darstellung')) ?>"
      id="dg-calendar-appearance-form"
    >
      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

      <h3 class="dg-subsection-title">Einzelne Farben</h3>
      <div class="dg-form-grid dg-cal-appearance-fields">
        <?php foreach ($fields as $key => $field) : ?>
          <label class="dg-field">
            <span><?= View::escape($field['label']) ?></span>
            <input
              type="color"
              name="<?= View::escape($key) ?>"
              id="dg_cal_color_<?= View::escape($key) ?>"
              value="<?= View::escape($calendarAppearanceConfig[$key] ?? '') ?>"
              data-color-key="<?= View::escape($key) ?>"
              <?= !$dbConnected ? ' disabled' : '' ?>
            >
            <small class="dg-field-hint"><?= View::escape($field['hint']) ?></small>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="dg-form-actions">
        <button type="submit" name="calendar_appearance_save" value="1" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>>Kalender Design speichern</button>
      </div>
    </form>

    <aside class="dg-cal-appearance-preview-wrap" aria-label="Live-Vorschau">
      <h3 class="dg-subsection-title">Vorschau</h3>
      <div class="dg-cal-appearance-preview" id="dg-cal-appearance-preview"<?= CalendarFrontendTheme::wrapperStyleAttribute() ?>>
        <button type="button" class="dg-cal-appearance-preview__btn">Termin buchen</button>
        <div class="dg-cal-appearance-preview__slots">
          <span class="dg-cal-appearance-preview__slot">09:00</span>
          <span class="dg-cal-appearance-preview__slot dg-cal-appearance-preview__slot--hover">09:15</span>
          <span class="dg-cal-appearance-preview__slot dg-cal-appearance-preview__slot--selected">09:30</span>
          <span class="dg-cal-appearance-preview__slot dg-cal-appearance-preview__slot--booked">09:45</span>
        </div>
      </div>
    </aside>
  </div>
</div>

<script>
  window.dgCalendarAppearance = {
    presets: <?= json_encode($presetColors, JSON_THROW_ON_ERROR) ?>
  };
</script>
