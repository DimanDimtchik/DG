<?php
/**
 * @var string $href
 * @var string|null $label
 */
$label = $label ?? 'Zurück';
?>
<p class="dg-back-nav">
  <a class="dg-button dg-button--back" href="<?= View::escape($href) ?>">← <?= View::escape($label) ?></a>
</p>
