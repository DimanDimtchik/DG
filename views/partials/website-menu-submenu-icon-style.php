<?php
/** @var string $namePrefix e.g. items[0][children][1][icon_style] */
/** @var array<string, mixed> $style */
/** @var bool $readOnly */
$namePrefix = $namePrefix ?? '';
$style = WebsiteMenuIcons::normalizeSubmenuIconStyle(is_array($style ?? null) ? $style : []);
$readOnly = (bool) ($readOnly ?? false);
$customFieldId = 'dg-submenu-icon-color-' . md5($namePrefix);
$hoverCustomFieldId = 'dg-submenu-icon-hover-color-' . md5($namePrefix);
?>
<div class="dg-submenu-icon-style">
  <p class="dg-field-hint" style="margin:0 0 8px;">Icon-Darstellung (Untermenü)</p>
  <div class="dg-form-grid dg-form-grid--compact">
    <label class="dg-field">
      <span>Sichtbarkeit</span>
      <select name="<?= View::escape($namePrefix) ?>[visibility]"<?= $readOnly ? ' disabled' : '' ?>>
        <?php foreach (WebsiteMenuIcons::submenuIconVisibilityOptions() as $visValue => $visLabel) : ?>
          <option value="<?= View::escape($visValue) ?>"<?= $style['visibility'] === $visValue ? ' selected' : '' ?>><?= View::escape($visLabel) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="dg-field">
      <span>Badge (optional)</span>
      <input type="text" name="<?= View::escape($namePrefix) ?>[badge]" maxlength="24"
             value="<?= View::escape((string) $style['badge']) ?>" placeholder="z. B. Neu"
             <?= $readOnly ? ' readonly' : '' ?>>
    </label>
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
      <span>Hover-Farbe</span>
      <select name="<?= View::escape($namePrefix) ?>[hover]" class="dg-submenu-icon-hover" data-custom-target="<?= View::escape($hoverCustomFieldId) ?>"<?= $readOnly ? ' disabled' : '' ?>>
        <?php foreach (WebsiteMenuIcons::submenuIconHoverOptions() as $hoverValue => $hoverLabel) : ?>
          <option value="<?= View::escape($hoverValue) ?>"<?= $style['hover'] === $hoverValue ? ' selected' : '' ?>><?= View::escape($hoverLabel) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="dg-field" id="<?= View::escape($hoverCustomFieldId) ?>"<?= $style['hover'] !== 'custom' ? ' hidden' : '' ?>>
      <span>Eigene Hover-Farbe</span>
      <input type="color" name="<?= View::escape($namePrefix) ?>[hover_color_custom]"
             value="<?= View::escape($style['hover_color_custom'] !== '' ? $style['hover_color_custom'] : '#6e6258') ?>"<?= $readOnly ? ' readonly' : '' ?>>
    </label>
    <label class="dg-field">
      <span>Position</span>
      <select name="<?= View::escape($namePrefix) ?>[position]"<?= $readOnly ? ' disabled' : '' ?>>
        <?php foreach (WebsiteMenuIcons::iconPositionOptions() as $posValue => $posLabel) : ?>
          <option value="<?= View::escape($posValue) ?>"<?= $style['position'] === $posValue ? ' selected' : '' ?>><?= View::escape($posLabel) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="dg-field">
      <span>Abstand Icon–Text</span>
      <select name="<?= View::escape($namePrefix) ?>[gap]"<?= $readOnly ? ' disabled' : '' ?>>
        <?php foreach (WebsiteMenuIcons::iconGapOptions() as $gapValue => $gapLabel) : ?>
          <option value="<?= View::escape($gapValue) ?>"<?= $style['gap'] === $gapValue ? ' selected' : '' ?>><?= View::escape($gapLabel) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="dg-field">
      <span>Strichstärke</span>
      <select name="<?= View::escape($namePrefix) ?>[stroke]"<?= $readOnly ? ' disabled' : '' ?>>
        <?php foreach (WebsiteMenuIcons::iconStrokeOptions() as $strokeValue => $strokeLabel) : ?>
          <option value="<?= View::escape($strokeValue) ?>"<?= $style['stroke'] === $strokeValue ? ' selected' : '' ?>><?= View::escape($strokeLabel) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="dg-field dg-field--checkbox">
      <span>
        <input type="hidden" name="<?= View::escape($namePrefix) ?>[hide_mobile]" value="0">
        <input type="checkbox" name="<?= View::escape($namePrefix) ?>[hide_mobile]" value="1"<?= !empty($style['hide_mobile']) ? ' checked' : '' ?><?= $readOnly ? ' disabled' : '' ?>>
        Icon im mobilen Menü ausblenden
      </span>
    </label>
  </div>
</div>
