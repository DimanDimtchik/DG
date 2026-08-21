<?php
/** @var array<string, string> $websiteDesignForm */
/** @var bool $canEdit */
/** @var bool $dbConnected */
/** @var array{type: string, message: string}|null $flash */
$form = $websiteDesignForm ?? WebsiteSettings::designDefaults();
$readOnly = !($canEdit ?? false);
$primary = (string) ($form['primary'] ?? '#6e6258');
$background = (string) ($form['background'] ?? '#ffffff');
$text = (string) ($form['text'] ?? '#1d2327');
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header">
    <h1 class="dg-page-title">Design</h1>
    <p class="dg-lead">Farben der öffentlichen Website. Die CRM-Oberfläche bleibt unverändert (Einstellungen → Software Design).</p>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Zum Speichern ist eine Datenbankverbindung erforderlich.</div>
  <?php endif; ?>

  <div class="dg-cal-appearance-layout">
    <form class="dg-form dg-panel" method="post" action="/app?page=website-design" id="dg-website-design-form">
      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
      <input type="hidden" name="website_design_save" value="1">

      <h2>Farben</h2>
      <div class="dg-form-grid dg-cal-appearance-fields">
        <label class="dg-field">
          <span>Hauptfarbe</span>
          <input type="color" name="primary" id="dg-website-color-primary" value="<?= View::escape($primary) ?>"<?= $readOnly ? ' disabled' : '' ?>>
          <small class="dg-field-hint">Buttons, Links und Kopfzeile</small>
        </label>
        <label class="dg-field">
          <span>Hintergrund</span>
          <input type="color" name="background" id="dg-website-color-background" value="<?= View::escape($background) ?>"<?= $readOnly ? ' disabled' : '' ?>>
          <small class="dg-field-hint">Seitenfläche hinter dem Text</small>
        </label>
        <label class="dg-field">
          <span>Text</span>
          <input type="color" name="text" id="dg-website-color-text" value="<?= View::escape($text) ?>"<?= $readOnly ? ' disabled' : '' ?>>
          <small class="dg-field-hint">Überschriften und Fließtext</small>
        </label>
      </div>

      <?php if (!$readOnly) : ?>
        <div class="dg-form-actions">
          <button type="submit" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>>Design speichern</button>
        </div>
      <?php endif; ?>
    </form>

    <aside class="dg-cal-appearance-preview-wrap" aria-label="Website-Vorschau">
      <h3 class="dg-subsection-title">Vorschau</h3>
      <div
        class="dg-website-design-preview"
        id="dg-website-design-preview"
        style="--website-preview-primary: <?= View::escape($primary) ?>; --website-preview-bg: <?= View::escape($background) ?>; --website-preview-text: <?= View::escape($text) ?>;"
      >
        <header class="dg-website-design-preview__bar">
          <strong>Firmenname</strong>
          <span>Start · Kontakt</span>
        </header>
        <div class="dg-website-design-preview__body">
          <h4>Überschrift</h4>
          <p>Beispieltext auf der öffentlichen Seite. Die Farben gelten nach dem Speichern für Kopf, Text und Buttons.</p>
          <span class="dg-website-design-preview__btn">Button</span>
        </div>
        <footer class="dg-website-design-preview__foot">© Firma · Impressum</footer>
      </div>
    </aside>
  </div>
</div>
<script>
(function () {
  var preview = document.getElementById('dg-website-design-preview');
  var form = document.getElementById('dg-website-design-form');
  if (!preview || !form) return;
  function apply() {
    var primary = form.querySelector('[name="primary"]');
    var background = form.querySelector('[name="background"]');
    var text = form.querySelector('[name="text"]');
    if (primary) preview.style.setProperty('--website-preview-primary', primary.value);
    if (background) preview.style.setProperty('--website-preview-bg', background.value);
    if (text) preview.style.setProperty('--website-preview-text', text.value);
  }
  form.addEventListener('input', apply);
})();
</script>
