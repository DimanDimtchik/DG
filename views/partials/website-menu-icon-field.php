<?php
/** @var string $name */
/** @var string $value */
/** @var bool $readOnly */
/** @var bool $compact */
/** @var string $suggested */
/** @var string|null $fieldId */
$name = $name ?? '';
$value = (string) ($value ?? 'auto');
$readOnly = (bool) ($readOnly ?? false);
$compact = (bool) ($compact ?? false);
$suggested = (string) ($suggested ?? '');
$fieldId = $fieldId ?? ('dg-menu-icon-' . md5($name));
$iconStyle = WebsiteMenuIcons::normalizeIconStyle(is_array($websiteMenuIconStyle ?? null) ? $websiteMenuIconStyle : []);
$options = WebsiteMenuIcons::options();
$previewId = WebsiteMenuIcons::resolve(['icon' => $value, 'label' => '', 'url' => '', 'children' => []]);
if ($previewId === '' && $value === 'auto' && $suggested !== '') {
    $previewId = $suggested;
}
?>
<div class="dg-menu-icon-field<?= $compact ? ' dg-menu-icon-field--compact' : '' ?>" data-menu-icon-field>
  <input type="hidden" name="<?= View::escape($name) ?>" value="<?= View::escape($value) ?>" data-menu-icon-input id="<?= View::escape($fieldId) ?>-input">
  <button type="button" class="dg-menu-icon-field__trigger" data-menu-icon-open aria-expanded="false" aria-controls="<?= View::escape($fieldId) ?>-panel"<?= $readOnly ? ' disabled' : '' ?>>
    <span class="dg-menu-icon-field__preview" data-menu-icon-preview aria-hidden="true">
      <?= $previewId !== '' ? WebsiteMenuIcons::svg($previewId, 'dg-menu-icon-field__svg', $iconStyle) : '<span class="dg-menu-icon-field__empty">—</span>' ?>
    </span>
    <span class="dg-menu-icon-field__label" data-menu-icon-label><?= View::escape($options[$value] ?? 'Icon wählen') ?></span>
  </button>
  <?php if (!$readOnly) : ?>
    <div class="dg-menu-icon-field__panel" id="<?= View::escape($fieldId) ?>-panel" hidden data-menu-icon-panel>
      <div class="dg-menu-icon-grid" role="listbox" aria-label="Icon auswählen">
        <?php foreach ($options as $iconValue => $iconLabel) :
          $gridIcon = $iconValue;
          if ($iconValue === 'auto') {
              $gridIcon = $suggested !== '' ? $suggested : 'nav';
          }
          if ($iconValue === '') {
              $gridIcon = '';
          }
          ?>
          <button type="button" class="dg-menu-icon-grid__item<?= $value === (string) $iconValue ? ' is-selected' : '' ?>"
                  role="option" aria-selected="<?= $value === (string) $iconValue ? 'true' : 'false' ?>"
                  data-menu-icon-pick data-value="<?= View::escape((string) $iconValue) ?>"
                  data-label="<?= View::escape($iconLabel) ?>"
                  title="<?= View::escape($iconLabel) ?>">
            <?php if ($gridIcon !== '') : ?>
              <?= WebsiteMenuIcons::svg($gridIcon, 'dg-menu-icon-grid__svg', $iconStyle) ?>
            <?php else : ?>
              <span class="dg-menu-icon-grid__none">∅</span>
            <?php endif; ?>
            <span class="dg-menu-icon-grid__text"><?= View::escape($iconLabel) ?></span>
          </button>
        <?php endforeach; ?>
      </div>
      <?php if (!$compact && $suggested !== '' && $value === 'auto') : ?>
        <p class="dg-field-hint" style="margin:8px 0 0;">
          Vorschlag: <strong><?= View::escape($options[$suggested] ?? $suggested) ?></strong>
          · <button type="button" class="dg-button dg-button--small" data-menu-icon-pick data-value="<?= View::escape($suggested) ?>" data-label="<?= View::escape($options[$suggested] ?? $suggested) ?>">Fest übernehmen</button>
        </p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
