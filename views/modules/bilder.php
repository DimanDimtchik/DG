<?php
/**
 * @var array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int, per_page_key: string}|list<array<string, mixed>> $mediaList
 * @var array{type: string, message: string}|null $flash
 */
if (isset($mediaList['items']) && is_array($mediaList['items'])) {
    $list = $mediaList;
} else {
    $flat = is_array($mediaList ?? null) ? $mediaList : [];
    $list = [
        'items' => $flat,
        'total' => count($flat),
        'page' => 1,
        'per_page' => max(1, count($flat)),
        'total_pages' => 1,
        'per_page_key' => 'all',
    ];
}
$items = $list['items'];
$perPageKey = (string) ($list['per_page_key'] ?? MediaRepository::DEFAULT_PER_PAGE);
$baseUrl = '/app?page=bilder';
$sizeChips = [
    '10' => '10',
    '50' => '50',
    '100' => '100',
    'all' => 'Alle',
];
$sizeUrl = static function (string $key) use ($baseUrl): string {
    return $baseUrl . '&per_page=' . rawurlencode($key);
};
$pageQuery = $baseUrl . '&per_page=' . rawurlencode($perPageKey);
?>
<div class="dg-wrap dg-media-library">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-media-library__header">
    <div>
      <h1 class="dg-page-title">Media</h1>
      <p class="dg-lead">Zentrale Media-Bibliothek — nur Bilddateien. HR-Dokumente unter Kontakte sind ausgeschlossen. — <?= (int) $list['total'] ?> Einträge</p>
    </div>
    <div class="dg-media-library__actions">
      <a class="dg-button dg-button--primary" href="/app?page=bilder&amp;action=new">Hochladen</a>
      <button type="button" class="dg-button" id="dg-media-scan">Verwendung scannen</button>
    </div>
  </header>

  <?php if ((int) $list['total'] === 0) : ?>
    <div class="dg-panel dg-panel--notice">
      <p>Noch keine Dateien. Klicken Sie auf <strong>Hochladen</strong>, um das erste Bild anzulegen.</p>
    </div>
  <?php else : ?>
    <nav class="dg-subtabs" aria-label="Einträge pro Seite">
      <span class="dg-subtabs__group-label">Anzeigen</span>
      <?php foreach ($sizeChips as $sizeKey => $sizeLabel) : ?>
        <?php $isActive = $perPageKey === $sizeKey; ?>
        <a
          href="<?= View::escape($sizeUrl($sizeKey)) ?>"
          class="dg-subtabs__link<?= $isActive ? ' is-active' : '' ?>"
          <?= $isActive ? 'aria-current="true"' : '' ?>
        ><?= View::escape($sizeLabel) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="dg-table-wrap">
      <table class="dg-table dg-table--compact dg-media-table">
        <thead>
          <tr>
            <th>Vorschau</th>
            <th>ID / Name</th>
            <th>Größe</th>
            <th>Format</th>
            <th>Quelle</th>
            <th>Benutzung</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item) : ?>
            <?php
              $usages = is_array($item['usages'] ?? null) ? $item['usages'] : [];
              $sourcePlain = trim(strip_tags((string) ($item['source_note'] ?? '')));
              $isSvg = ((string) ($item['mime_type'] ?? '') === 'image/svg+xml')
                  || strtolower((string) ($item['extension'] ?? '')) === 'svg';
            ?>
            <tr>
              <td class="dg-media-table__thumb">
                <a href="/app?page=bilder&amp;action=edit&amp;id=<?= View::escape((string) $item['media_id']) ?>">
                  <img
                    class="<?= $isSvg ? 'dg-media-thumb--svg' : '' ?>"
                    src="<?= View::escape(MediaStorage::adminPreviewUrl((string) $item['media_id'])) ?>"
                    alt=""
                    loading="lazy"
                    width="56"
                    height="56"
                  >
                </a>
              </td>
              <td>
                <code class="dg-media-id"><?= View::escape((string) $item['media_id']) ?></code>
                <div><?= View::escape((string) ($item['title'] !== '' ? $item['title'] : $item['original_name'])) ?></div>
              </td>
              <td>
                <?php if (!empty($item['width']) && !empty($item['height'])) : ?>
                  <?= (int) $item['width'] ?>×<?= (int) $item['height'] ?> px<br>
                <?php endif; ?>
                <?= View::escape(number_format(((int) $item['size_bytes']) / 1024, 1, ',', '.') . ' KB') ?>
              </td>
              <td><?= View::escape(strtoupper((string) $item['extension'])) ?></td>
              <td><?= View::escape($sourcePlain !== '' ? $sourcePlain : '—') ?></td>
              <td class="dg-media-table__usage">
                <?php if ($usages === []) : ?>
                  <span class="dg-badge">Nicht referenziert</span>
                <?php else : ?>
                  <?php foreach ($usages as $usage) : ?>
                    <div class="dg-media-usage-row<?= empty($usage['used_until']) ? ' is-active' : '' ?>">
                      <strong><?= View::escape((string) $usage['context_label']) ?></strong>
                      <span>von <?= View::escape((string) $usage['used_from']) ?></span>
                      <?php if (!empty($usage['used_until'])) : ?>
                        <span>bis <?= View::escape((string) $usage['used_until']) ?></span>
                      <?php else : ?>
                        <span class="dg-badge dg-badge--ok">aktiv</span>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </td>
              <td class="dg-media-table__ops">
                <div class="dg-table__actions-group">
                  <a class="dg-button dg-button--small" href="/app?page=bilder&amp;action=edit&amp;id=<?= View::escape((string) $item['media_id']) ?>">Bearbeiten</a>
                  <a class="dg-button dg-button--small" href="/api/media?action=download&amp;id=<?= View::escape((string) $item['media_id']) ?>">Download</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ((int) $list['total_pages'] > 1) : ?>
      <nav class="dg-pagination" aria-label="Seiten">
        <?php if ((int) $list['page'] > 1) : ?>
          <a href="<?= View::escape($pageQuery . '&paged=' . ((int) $list['page'] - 1)) ?>">&laquo; Zurück</a>
        <?php endif; ?>
        <span>Seite <?= (int) $list['page'] ?> von <?= (int) $list['total_pages'] ?></span>
        <?php if ((int) $list['page'] < (int) $list['total_pages']) : ?>
          <a href="<?= View::escape($pageQuery . '&paged=' . ((int) $list['page'] + 1)) ?>">Weiter &raquo;</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>
