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
    $previewId = WebsiteMenuIcons::canonicalId($suggested);
}
$stroke = match ($iconStyle['stroke']) {
    'light' => '1.35',
    'bold' => '2.1',
    default => '1.75',
};
?>
<div class="dg-menu-icon-field<?= $compact ? ' dg-menu-icon-field--compact' : '' ?>" data-menu-icon-field
     data-suggested="<?= View::escape($suggested) ?>" data-stroke="<?= View::escape($stroke) ?>">
  <input type="hidden" name="<?= View::escape($name) ?>" value="<?= View::escape($value) ?>" data-menu-icon-input id="<?= View::escape($fieldId) ?>-input">
  <button type="button" class="dg-menu-icon-field__trigger" data-menu-icon-open aria-expanded="false" aria-controls="<?= View::escape($fieldId) ?>-panel"<?= $readOnly ? ' disabled' : '' ?>>
    <span class="dg-menu-icon-field__preview" data-menu-icon-preview aria-hidden="true">
      <?= $previewId !== '' ? WebsiteMenuIcons::svg($previewId, 'dg-menu-icon-field__svg', $iconStyle) : '<span class="dg-menu-icon-field__empty">—</span>' ?>
    </span>
    <span class="dg-menu-icon-field__label" data-menu-icon-label><?= View::escape($options[$value] ?? $options[WebsiteMenuIcons::canonicalId($value)] ?? 'Icon wählen') ?></span>
  </button>
  <?php if (!$readOnly) : ?>
    <div class="dg-menu-icon-field__panel" id="<?= View::escape($fieldId) ?>-panel" hidden data-menu-icon-panel>
      <label class="dg-menu-icon-field__search-wrap">
        <span class="dg-sr-only">Icons durchsuchen</span>
        <input type="search" class="dg-menu-icon-field__search" data-menu-icon-search placeholder="Icon suchen (z. B. mail, kalender, shop)…" autocomplete="off">
      </label>
      <div class="dg-menu-icon-grid" role="listbox" aria-label="Icon auswählen" data-menu-icon-grid></div>
      <p class="dg-field-hint dg-menu-icon-field__hint" data-menu-icon-hint hidden></p>
      <?php if (!$compact && $suggested !== '' && $value === 'auto') : ?>
        <p class="dg-field-hint" style="margin:8px 0 0;" data-menu-icon-suggest-hint>
          Vorschlag: <strong><?= View::escape($options[$suggested] ?? $options[WebsiteMenuIcons::canonicalId($suggested)] ?? $suggested) ?></strong>
          · <button type="button" class="dg-button dg-button--small" data-menu-icon-pick data-value="<?= View::escape($suggested) ?>" data-label="<?= View::escape($options[$suggested] ?? $options[WebsiteMenuIcons::canonicalId($suggested)] ?? $suggested) ?>">Fest übernehmen</button>
        </p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
