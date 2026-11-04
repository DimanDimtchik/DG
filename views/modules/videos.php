<?php
/**
 * @var list<array<string, mixed>> $videoAssets
 * @var bool $youtubeConnected
 * @var bool $linkedinConnected
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$videoAssets = is_array($videoAssets ?? null) ? $videoAssets : [];
$youtubeConnected = !empty($youtubeConnected);
$linkedinConnected = !empty($linkedinConnected);
$dbConnected = (bool) ($dbConnected ?? Database::isConfigured());
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Videos</h1>
      <p class="dg-lead">Videos speichern und auf YouTube oder LinkedIn veröffentlichen (max. 100 MB).</p>
    </div>
    <div class="dg-page-header__actions">
      <a class="dg-button" href="<?= View::escape(SettingsRegistry::tabUrl('youtube-publish')) ?>">YouTube</a>
      <a class="dg-button" href="<?= View::escape(SettingsRegistry::tabUrl('linkedin-publish')) ?>">LinkedIn</a>
    </div>
  </header>

  <?php if (!$youtubeConnected && !$linkedinConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Noch keine Plattform verbunden.
      <a class="dg-button dg-button--small" href="<?= View::escape(SettingsRegistry::tabUrl('youtube-publish')) ?>">YouTube</a>
      <a class="dg-button dg-button--small" href="<?= View::escape(SettingsRegistry::tabUrl('linkedin-publish')) ?>">LinkedIn</a>
    </div>
  <?php endif; ?>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Datenbank nicht verbunden.</div>
  <?php else : ?>
    <section class="dg-panel dg-mb-4">
      <h2 class="dg-subsection-title">Video hochladen</h2>
      <form method="post" action="/app?page=videos" class="dg-form" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <input type="hidden" name="video_asset_upload" value="1">
        <div class="dg-form-grid">
          <label class="dg-field dg-field--wide">
            <span>Datei (MP4, WebM, MOV)</span>
            <input type="file" name="video_file" accept="video/mp4,video/webm,video/quicktime,.mp4,.webm,.mov" required>
            <span class="dg-field-hint">Maximal 100 MB auf dem aktuellen Hosting.</span>
          </label>
        </div>
        <div class="dg-form-actions">
          <button type="submit" class="dg-button dg-button--primary">Hochladen</button>
        </div>
      </form>
    </section>

    <div class="dg-table-wrap">
      <table class="dg-table">
        <thead>
          <tr>
            <th>Datei</th>
            <th>Typ</th>
            <th class="dg-table__num">Größe</th>
            <th>Hochgeladen</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($videoAssets === []) : ?>
            <tr><td colspan="5" class="dg-table__empty">Noch keine Videos.</td></tr>
          <?php else : ?>
            <?php foreach ($videoAssets as $row) : ?>
              <?php $id = (int) ($row['id'] ?? 0); ?>
              <tr>
                <td><strong><?= View::escape((string) ($row['original_name'] ?? '')) ?></strong></td>
                <td><?= View::escape((string) ($row['mime_type'] ?? '')) ?></td>
                <td class="dg-table__num"><?= number_format((int) ($row['size_bytes'] ?? 0) / 1048576, 2, ',', '.') ?> MB</td>
                <td><?= View::escape((string) ($row['created_at'] ?? '')) ?></td>
                <td class="dg-table__actions">
                  <div class="dg-table__actions-group">
                    <a class="dg-button dg-button--small" href="/app?page=videos-form&amp;id=<?= $id ?>">Öffnen</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
