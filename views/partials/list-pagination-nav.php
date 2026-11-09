<?php
/**
 * Seiten-Navigation unter Ops-Listen — nur ab total >= 10 und total_pages > 1.
 *
 * @var int $total
 * @var int $page
 * @var int $totalPages
 * @var string $perPageKey
 * @var string $listPage
 * @var array<string, scalar|null>|null $extraQuery
 */
$total = (int) ($total ?? 0);
$pageNum = max(1, (int) ($page ?? 1));
$totalPages = max(1, (int) ($totalPages ?? 1));
if (!ListPagination::showControls($total) || $totalPages <= 1) {
    return;
}
$perPageKey = ListPagination::parsePerPageKey($perPageKey ?? null);
$listPage = trim((string) ($listPage ?? ''));
if ($listPage === '') {
    return;
}
$extraQuery = is_array($extraQuery ?? null) ? $extraQuery : [];

$buildUrl = static function (int $paged) use ($listPage, $perPageKey, $extraQuery): string {
    $params = ['page' => $listPage, 'per_page' => $perPageKey];
    foreach ($extraQuery as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $key = (string) $key;
        if (in_array($key, ['page', 'per_page', 'paged'], true)) {
            continue;
        }
        $params[$key] = $value;
    }
    if ($paged > 1) {
        $params['paged'] = $paged;
    }

    return '/app?' . http_build_query($params);
};
?>
<nav class="dg-pagination" aria-label="Seiten">
  <?php if ($pageNum > 1) : ?>
    <a href="<?= View::escape($buildUrl($pageNum - 1)) ?>">&laquo; Zurück</a>
  <?php endif; ?>
  <span>Seite <?= $pageNum ?> von <?= $totalPages ?></span>
  <?php if ($pageNum < $totalPages) : ?>
    <a href="<?= View::escape($buildUrl($pageNum + 1)) ?>">Weiter &raquo;</a>
  <?php endif; ?>
</nav>
