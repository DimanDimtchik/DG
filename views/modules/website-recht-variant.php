<?php
/** @var string $legalSlug */
/** @var string $legalProductKey */
/** @var string $legalProductLabel */
/** @var string $legalPageTitle */
/** @var string $legalHtml */
/** @var string $legalStatus */
/** @var string|null $formError */
/** @var bool $canEdit */
$statusOptions = LegalProductSettings::statusOptions();
$readOnly = !($canEdit ?? false);
?>
<div class="dg-wrap">
  <?php View::partial('partials/back-nav', [
      'href' => SettingsRegistry::tabUrl('agb'),
      'label' => 'Zurück zu Rechtliches',
  ]); ?>

  <header class="dg-page-header">
    <h1 class="dg-page-title"><?= View::escape($legalPageTitle) ?></h1>
    <p class="dg-lead">Produkt/Gruppe: <strong><?= View::escape($legalProductLabel) ?></strong> · URL: <code>/<?= View::escape($legalSlug) ?><?= $legalProductKey !== LegalProductSettings::DEFAULT_PRODUCT_KEY ? '?produkt=' . View::escape($legalProductKey) : '' ?></code></p>
  </header>

  <?php if (!empty($formError)) : ?>
    <div class="dg-flash dg-flash--error"><?= View::escape($formError) ?></div>
  <?php endif; ?>

  <form class="dg-form dg-panel" method="post" action="/app?page=website-recht">
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
    <input type="hidden" name="slug" value="<?= View::escape($legalSlug) ?>">
    <input type="hidden" name="product" value="<?= View::escape($legalProductKey) ?>">

    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Status (Tab)</span>
        <select name="status"<?= $readOnly ? ' disabled' : '' ?>>
          <?php foreach ($statusOptions as $value => $label) : ?>
            <option value="<?= View::escape($value) ?>"<?= $legalStatus === $value ? ' selected' : '' ?>><?= View::escape($label) ?></option>
          <?php endforeach; ?>
        </select>
        <small class="dg-field-hint">Online = öffentlich sichtbar · Entwurf/Offline = Tab ausgeblendet (Vorschau mit Login).</small>
      </label>
    </div>

    <label class="dg-field dg-field--wide">
      <span>Inhalt (HTML)</span>
      <textarea name="html" rows="24" style="font-family:monospace"<?= $readOnly ? ' readonly' : '' ?>><?= View::escape($legalHtml) ?></textarea>
    </label>

    <p class="dg-form-actions">
      <?php if (!$readOnly) : ?>
        <button type="submit" name="legal_variant_save" value="1" class="dg-button dg-button--primary">Speichern</button>
      <?php endif; ?>
      <a class="dg-button" href="<?= View::escape('/vorschau/' . $legalSlug . ($legalProductKey !== LegalProductSettings::DEFAULT_PRODUCT_KEY ? '?produkt=' . rawurlencode($legalProductKey) : '')) ?>" target="_blank" rel="noopener">Vorschau</a>
    </p>
  </form>
</div>
