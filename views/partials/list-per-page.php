<?php
/**
 * Dropdown „Anzeigen“ 10/50/100/Alle — nur ab total >= 10.
 *
 * @var int $total
 * @var string $perPageKey
 * @var string $listPage CRM-Modulslug (GET page=…)
 * @var array<string, scalar|null>|null $extraQuery weitere GET-Filter (ohne page/per_page/paged)
 */
$total = (int) ($total ?? 0);
if (!ListPagination::showControls($total)) {
    return;
}
$perPageKey = ListPagination::parsePerPageKey($perPageKey ?? null);
$listPage = trim((string) ($listPage ?? ''));
if ($listPage === '') {
    return;
}
$extraQuery = is_array($extraQuery ?? null) ? $extraQuery : [];
$sizeOptions = [
    '10' => '10',
    '50' => '50',
    '100' => '100',
    'all' => 'Alle',
];
?>
<form class="dg-form dg-btn-row" method="get" action="/app" aria-label="Einträge pro Seite">
  <input type="hidden" name="page" value="<?= View::escape($listPage) ?>">
  <?php foreach ($extraQuery as $key => $value) : ?>
    <?php
      if ($value === null || $value === '') {
          continue;
      }
      $key = (string) $key;
      if (in_array($key, ['page', 'per_page', 'paged'], true)) {
          continue;
      }
    ?>
    <input type="hidden" name="<?= View::escape($key) ?>" value="<?= View::escape((string) $value) ?>">
  <?php endforeach; ?>
  <label class="dg-field">
    <span>Anzeigen</span>
    <select name="per_page" onchange="this.form.submit()">
      <?php foreach ($sizeOptions as $sizeKey => $sizeLabel) : ?>
        <option value="<?= View::escape($sizeKey) ?>"<?= $perPageKey === $sizeKey ? ' selected' : '' ?>><?= View::escape($sizeLabel) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
</form>
