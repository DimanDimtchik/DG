<?php
/** @var string $namePrefix e.g. items[0][children][1][icon_style] */
/** @var array<string, string> $style */
/** @var bool $readOnly */
$namePrefix = $namePrefix ?? '';
$style = WebsiteMenuIcons::normalizeSubmenuIconStyle(is_array($style ?? null) ? $style : []);
$readOnly = (bool) ($readOnly ?? false);
$customFieldId = 'dg-submenu-icon-color-' . md5($namePrefix);
?>
<div class="dg-submenu-icon-style">
  <p class="dg-field-hint" style="margin:0 0 8px;">Icon-Darstellung (Untermenü)</p>
  <div class="dg-form-grid dg-form-grid--compact">
    <label class="dg-field">
      <span>Größe</span>
      <select name="<?= View::escape($namePrefix) ?>[size]"<?= $readOnly ? ' disabled' : '' ?>>
        <?php foreach (WebsiteMenuIcons::iconSizeOptions() as $sizeValue => $sizeLabel) : ?>
          <option value="<?= View::escape($sizeValue) ?>"<?= $style['size'] === $sizeValue ? ' selected' : '' ?>><?= View::escape($sizeLabel) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="dg-field">
      <span>Farbe</span>
      <select name="<?= View::escape($namePrefix) ?>[color]" class="dg-submenu-icon-color" data-custom-target="<?= View::escape($customFieldId) ?>"<?= $readOnly ? ' disabled' : '' ?>>
        <?php foreach (WebsiteMenuIcons::submenuIconColorOptions() as $colorValue => $colorLabel) : ?>
          <option value="<?= View::escape($colorValue) ?>"<?= $style['color'] === $colorValue ? ' selected' : '' ?>><?= View::escape($colorLabel) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="dg-field" id="<?= View::escape($customFieldId) ?>"<?= $style['color'] !== 'custom' ? ' hidden' : '' ?>>
      <span>Eigene Farbe</span>
      <input type="color" name="<?= View::escape($namePrefix) ?>[color_custom]" value="<?= View::escape($style['color_custom'] !== '' ? $style['color_custom'] : '#6e6258') ?>"<?= $readOnly ? ' readonly' : '' ?>>
    </label>
    <label class="dg-field">
      <span>Position</span>
      <select name="<?= View::escape($namePrefix) ?>[position]"<?= $readOnly ? ' disabled' : '' ?>>
        <?php foreach (WebsiteMenuIcons::iconPositionOptions() as $posValue => $posLabel) : ?>
          <option value="<?= View::escape($posValue) ?>"<?= $style['position'] === $posValue ? ' selected' : '' ?>><?= View::escape($posLabel) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
</div>
