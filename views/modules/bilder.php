<?php
/** @var list<array<string, mixed>> $mediaList */
/** @var array{type: string, message: string}|null $flash */
?>
<div class="dg-wrap dg-media-library">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-media-library__header">
    <div>
      <h1 class="dg-page-title">Bilder</h1>
      <p class="dg-lead">Zentrale Medienbibliothek — nur Bilder. HR-Dokumente unter Kontakte sind ausgeschlossen.</p>
    </div>
    <div class="dg-media-library__actions">
      <a class="dg-button dg-button--primary" href="/app?page=bilder&amp;action=new">Hochladen</a>
      <button type="button" class="dg-button" id="dg-media-scan">Verwendung scannen</button>
    </div>
  </header>

  <?php if ($mediaList === []) : ?>
    <div class="dg-panel dg-panel--notice">
      <p>Noch keine Bilder. Klicken Sie auf <strong>Hochladen</strong>, um das erste Bild anzulegen.</p>
    </div>
  <?php else : ?>
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
          <?php foreach ($mediaList as $item) : ?>
            <?php
              $usages = is_array($item['usages'] ?? null) ? $item['usages'] : [];
              $sourcePlain = trim(strip_tags((string) ($item['source_note'] ?? '')));
            ?>
            <tr>
              <td class="dg-media-table__thumb">
                <a href="/app?page=bilder&amp;action=edit&amp;id=<?= View::escape((string) $item['media_id']) ?>">
                  <img src="<?= View::escape(MediaStorage::adminPreviewUrl((string) $item['media_id'])) ?>" alt="" loading="lazy" width="56" height="56">
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
                <a class="dg-button dg-button--small" href="/app?page=bilder&amp;action=edit&amp;id=<?= View::escape((string) $item['media_id']) ?>">Bearbeiten</a>
                <a class="dg-button dg-button--small" href="/api/media?action=download&amp;id=<?= View::escape((string) $item['media_id']) ?>">Download</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
