<?php
/**
 * Akkordeon-Bereich der CRM-Seitenleiste (WordPress-Stil).
 *
 * @var string $sectionId kurze ID, z. B. "hr"
 * @var string $sectionLabel sichtbarer Bereichstitel
 * @var list<array{slug: string, label: string, icon: string, href: string, is_active?: bool}> $sectionItems
 * @var bool $sectionOpen ob der Bereich aufgeklappt starten soll
 * @var string $sectionModifier optionaler Extra-Klassen-Suffix, z. B. "buchhaltung"
 * @var bool $sectionCollapsible Akkordeon (Standard true); false = immer offen
 */
$sectionId = (string) ($sectionId ?? '');
$sectionLabel = (string) ($sectionLabel ?? '');
$sectionItems = is_array($sectionItems ?? null) ? $sectionItems : [];
$sectionOpen = !empty($sectionOpen);
$sectionModifier = trim((string) ($sectionModifier ?? ''));
$sectionCollapsible = ($sectionCollapsible ?? true) !== false;
$headingId = 'dg-sidebar-' . $sectionId;
$listId = 'dg-sidebar-list-' . $sectionId;
$sectionClass = 'dg-sidebar__section';
if ($sectionModifier !== '') {
    $sectionClass .= ' dg-sidebar__section--' . $sectionModifier;
}
if ($sectionCollapsible) {
    $sectionClass .= ' dg-sidebar__section--collapsible';
    if ($sectionOpen) {
        $sectionClass .= ' is-open';
    }
} else {
    $sectionClass .= ' is-open';
}
?>
<section class="<?= View::escape($sectionClass) ?>" aria-labelledby="<?= View::escape($headingId) ?>"<?php if ($sectionCollapsible) : ?> data-sidebar-section<?= $sectionOpen ? ' data-sidebar-default-open' : '' ?><?php endif; ?>>
  <h2 id="<?= View::escape($headingId) ?>" class="dg-sidebar__heading">
    <?php if ($sectionCollapsible) : ?>
      <button
        type="button"
        class="dg-sidebar__toggle"
        aria-expanded="<?= $sectionOpen ? 'true' : 'false' ?>"
        aria-controls="<?= View::escape($listId) ?>"
        data-sidebar-toggle
      >
        <span class="dg-sidebar__toggle-label"><?= View::escape($sectionLabel) ?></span>
        <span class="dg-sidebar__chevron" aria-hidden="true"></span>
      </button>
    <?php else : ?>
      <?= View::escape($sectionLabel) ?>
    <?php endif; ?>
  </h2>
  <ul id="<?= View::escape($listId) ?>" class="dg-sidebar__list">
    <?php foreach ($sectionItems as $item) : ?>
      <li>
        <a href="<?= View::escape((string) $item['href']) ?>" class="dg-sidebar__link<?= !empty($item['is_active']) ? ' is-active' : '' ?>">
          <?php View::render('partials/icon', ['name' => (string) $item['icon']]); ?>
          <span><?= View::escape((string) $item['label']) ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
