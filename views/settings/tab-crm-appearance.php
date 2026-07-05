<?php
/**
 * @var array<string, string> $crmThemeConfig
 * @var bool $dbConnected
 */
$presets = CrmThemePresets::presets();
$fields = CrmThemeSettings::fieldDefinitions();
$groupLabels = CrmThemeSettings::groupLabels();
$presetColors = [];
foreach ($presets as $presetId => $preset) {
    $presetColors[$presetId] = $preset['colors'];
}
$fieldsByGroup = [];
foreach ($fields as $key => $field) {
    $fieldsByGroup[$field['group']][$key] = $field;
}
?>
<div class="dg-form">
  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Zum Speichern ist eine funktionierende <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Datenbankverbindung</a> erforderlich.
    </div>
  <?php endif; ?>

  <p class="dg-lead">
    Software Design der CRM-Oberfläche: oberes Menü, Seitenleiste, Hintergrund, Text und Buttons.
    Die Vorschau rechts zeigt die Wirkung sofort — nach dem Speichern gilt das Design für alle Benutzer.
  </p>

  <section class="dg-cal-color-presets dg-crm-theme-presets">
    <h3 class="dg-subsection-title">Farbvorlagen</h3>
    <p class="dg-field-hint">Vorlage wählen, Felder werden ausgefüllt. Anschließend „Software Design speichern“ klicken.</p>
    <div class="dg-cal-color-presets__grid">
      <?php foreach ($presets as $presetId => $preset) : ?>
        <button
          type="button"
          class="dg-cal-color-preset dg-crm-theme-preset"
          data-preset-id="<?= View::escape($presetId) ?>"
        >
          <span class="dg-crm-theme-preset__swatches">
            <span class="dg-crm-theme-preset__swatch" style="background-color: <?= View::escape($preset['colors']['menu_bg']) ?>;"></span>
            <span class="dg-crm-theme-preset__swatch" style="background-color: <?= View::escape($preset['colors']['brand']) ?>;"></span>
            <span class="dg-crm-theme-preset__swatch" style="background-color: <?= View::escape($preset['colors']['body_bg']) ?>;"></span>
          </span>
          <span class="dg-cal-color-preset__name"><?= View::escape($preset['name']) ?></span>
          <span class="dg-cal-color-preset__desc"><?= View::escape($preset['desc']) ?></span>
        </button>
      <?php endforeach; ?>
    </div>
    <p id="dg-crm-theme-preset-notice" class="dg-cal-preset-notice" hidden aria-live="polite"></p>
  </section>

  <div class="dg-cal-appearance-layout dg-crm-theme-layout">
    <form
      class="dg-form dg-crm-theme-form"
      method="post"
      action="<?= View::escape(SettingsRegistry::tabUrl('crm-darstellung')) ?>"
      id="dg-crm-theme-form"
    >
      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

      <?php foreach ($groupLabels as $groupId => $groupLabel) : ?>
        <?php if (empty($fieldsByGroup[$groupId])) { continue; } ?>
        <details class="dg-collapsible-form dg-crm-theme-group" open>
          <summary class="dg-subsection-title dg-collapsible-form__summary"><?= View::escape($groupLabel) ?></summary>
          <div class="dg-collapsible-form__body">
            <div class="dg-form-grid dg-cal-appearance-fields">
              <?php foreach ($fieldsByGroup[$groupId] as $key => $field) : ?>
                <label class="dg-field">
                  <span><?= View::escape($field['label']) ?></span>
                  <input
                    type="color"
                    name="<?= View::escape($key) ?>"
                    id="dg_crm_theme_<?= View::escape($key) ?>"
                    value="<?= View::escape($crmThemeConfig[$key] ?? '') ?>"
                    data-color-key="<?= View::escape($key) ?>"
                    <?= !$dbConnected ? ' disabled' : '' ?>
                  >
                  <small class="dg-field-hint"><?= View::escape($field['hint']) ?></small>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        </details>
      <?php endforeach; ?>

      <div class="dg-form-actions">
        <button type="submit" name="crm_theme_save" value="1" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>>Software Design speichern</button>
      </div>
    </form>

    <aside class="dg-cal-appearance-preview-wrap" aria-label="CRM-Vorschau">
      <h3 class="dg-subsection-title">Vorschau</h3>
      <div class="dg-crm-theme-preview" id="dg-crm-theme-preview"<?= CrmFrontendTheme::wrapperStyleAttribute() ?>>
        <div class="dg-crm-theme-preview__bar">
          <span class="dg-crm-theme-preview__brand">DG CRM</span>
          <span class="dg-crm-theme-preview__menu">Menü</span>
        </div>
        <div class="dg-crm-theme-preview__shell">
          <nav class="dg-crm-theme-preview__sidebar" aria-hidden="true">
            <span class="is-active">Dashboard</span>
            <span>Kontakte</span>
            <span>Termine</span>
          </nav>
          <div class="dg-crm-theme-preview__main">
            <div class="dg-crm-theme-preview__panel">
              <strong>Beispiel-Inhalt</strong>
              <p>Tabellen, Formulare und Karten im Inhaltsbereich.</p>
              <button type="button" class="dg-crm-theme-preview__btn">Speichern</button>
            </div>
          </div>
        </div>
      </div>
    </aside>
  </div>
</div>

<script>
  window.dgCrmTheme = {
    presets: <?= json_encode($presetColors, JSON_THROW_ON_ERROR) ?>,
    derivedKeys: ['menu_bg_hover', 'menu_bg_active', 'menu_border', 'menu_text_muted', 'brand_dark', 'focus_ring']
  };
</script>
