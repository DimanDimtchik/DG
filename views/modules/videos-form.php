<?php
/**
 * @var array<string, mixed> $videoAsset
 * @var list<array<string, mixed>> $publishJobs
 * @var array<string, mixed>|null $latestPost
 * @var array<string, mixed>|null $latestYoutubePost
 * @var array<string, mixed>|null $latestLinkedinPost
 * @var bool $youtubeConnected
 * @var bool $linkedinConnected
 * @var string|null $formError
 * @var array{type: string, message: string}|null $flash
 */
$asset = is_array($videoAsset ?? null) ? $videoAsset : [];
$publishJobs = is_array($publishJobs ?? null) ? $publishJobs : [];
$latestYoutubePost = is_array($latestYoutubePost ?? null)
    ? $latestYoutubePost
    : (is_array($latestPost ?? null) ? $latestPost : null);
$latestLinkedinPost = is_array($latestLinkedinPost ?? null) ? $latestLinkedinPost : null;
$youtubeConnected = !empty($youtubeConnected);
$linkedinConnected = !empty($linkedinConnected);
$assetId = (int) ($asset['id'] ?? 0);
$stateLabels = PublishJobRepository::stateOptions();
$publishedYoutubeJob = null;
$publishedLinkedinJob = null;
foreach ($publishJobs as $j) {
    if ((string) ($j['state'] ?? '') !== PublishJobRepository::STATE_PUBLISHED || (string) ($j['external_id'] ?? '') === '') {
        continue;
    }
    $plat = (string) ($j['platform'] ?? '');
    if ($plat === PublishJobRepository::PLATFORM_YOUTUBE && $publishedYoutubeJob === null) {
        $publishedYoutubeJob = $j;
    }
    if ($plat === PublishJobRepository::PLATFORM_LINKEDIN && $publishedLinkedinJob === null) {
        $publishedLinkedinJob = $j;
    }
}
$defaultTitle = pathinfo((string) ($asset['original_name'] ?? 'Video'), PATHINFO_FILENAME);
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header">
    <?php
      View::partial('partials/back-nav', [
          'href' => '/app?page=videos',
          'label' => 'Zurück zu Videos',
      ]);
    ?>
    <h1 class="dg-page-title"><?= View::escape((string) ($asset['original_name'] ?? 'Video')) ?></h1>
    <p class="dg-lead">
      <?= View::escape((string) ($asset['mime_type'] ?? '')) ?>
      · <?= number_format((int) ($asset['size_bytes'] ?? 0) / 1048576, 2, ',', '.') ?> MB
    </p>
  </header>

  <?php if (!empty($formError)) : ?>
    <div class="dg-flash dg-flash--error"><?= View::escape((string) $formError) ?></div>
  <?php endif; ?>

  <section class="dg-panel dg-mb-4">
    <h2 class="dg-subsection-title">Auf YouTube veröffentlichen</h2>
    <?php if (!$youtubeConnected) : ?>
      <p class="dg-muted">Zuerst YouTube in den Einstellungen verbinden.</p>
      <div class="dg-btn-row">
        <a class="dg-button" href="<?= View::escape(SettingsRegistry::tabUrl('youtube-publish')) ?>">YouTube-Einstellungen</a>
      </div>
    <?php else : ?>
      <form method="post" action="/app?page=videos-form&amp;id=<?= $assetId ?>" class="dg-form">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <input type="hidden" name="video_youtube_publish" value="1">
        <div class="dg-form-grid">
          <label class="dg-field dg-field--wide">
            <span>Titel *</span>
            <input type="text" name="title" required maxlength="100" value="<?= View::escape((string) ($_POST['title'] ?? $defaultTitle)) ?>">
          </label>
          <label class="dg-field dg-field--wide">
            <span>Beschreibung</span>
            <textarea name="description" rows="5"><?= View::escape((string) ($_POST['description'] ?? '')) ?></textarea>
          </label>
          <label class="dg-field">
            <span>Sichtbarkeit</span>
            <select name="privacy">
              <?php foreach (['private' => 'Privat', 'unlisted' => 'Nicht gelistet', 'public' => 'Öffentlich'] as $val => $label) : ?>
                <option value="<?= $val ?>"<?= (string) ($_POST['privacy'] ?? 'private') === $val ? ' selected' : '' ?>><?= View::escape($label) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="dg-field">
            <span>Geplant (optional, nur privat)</span>
            <input type="datetime-local" name="publishAt" value="<?= View::escape((string) ($_POST['publishAt'] ?? '')) ?>">
            <span class="dg-field-hint">YouTube: nur vor Erstveröffentlichung, Video bleibt bis dahin privat.</span>
          </label>
          <label class="dg-field dg-field--wide">
            <span>Tags (Komma-getrennt, optional)</span>
            <input type="text" name="tags" value="<?= View::escape((string) ($_POST['tags'] ?? '')) ?>">
          </label>
        </div>
        <div class="dg-form-actions">
          <button type="submit" class="dg-button dg-button--primary">Auf YouTube veröffentlichen</button>
        </div>
      </form>
    <?php endif; ?>
  </section>

  <?php if ($publishedYoutubeJob !== null) : ?>
    <section class="dg-panel dg-mb-4">
      <h2 class="dg-subsection-title">YouTube bearbeiten</h2>
      <p class="dg-muted">YouTube-ID: <?= View::escape((string) ($publishedYoutubeJob['external_id'] ?? '')) ?></p>
      <form method="post" action="/app?page=videos-form&amp;id=<?= $assetId ?>" class="dg-form">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <input type="hidden" name="video_youtube_update" value="1">
        <input type="hidden" name="job_id" value="<?= (int) ($publishedYoutubeJob['id'] ?? 0) ?>">
        <div class="dg-form-grid">
          <label class="dg-field dg-field--wide">
            <span>Titel</span>
            <input type="text" name="title" maxlength="100" value="<?= View::escape((string) ($latestYoutubePost['title'] ?? PublishJobRepository::payload($publishedYoutubeJob)['title'] ?? '')) ?>">
          </label>
          <label class="dg-field dg-field--wide">
            <span>Beschreibung</span>
            <textarea name="description" rows="4"><?= View::escape((string) ($latestYoutubePost['description'] ?? PublishJobRepository::payload($publishedYoutubeJob)['description'] ?? '')) ?></textarea>
          </label>
          <label class="dg-field">
            <span>Sichtbarkeit</span>
            <?php $curPrivacy = (string) ($latestYoutubePost['privacy'] ?? PublishJobRepository::payload($publishedYoutubeJob)['privacy'] ?? 'private'); ?>
            <select name="privacy">
              <?php foreach (['private' => 'Privat', 'unlisted' => 'Nicht gelistet', 'public' => 'Öffentlich'] as $val => $label) : ?>
                <option value="<?= $val ?>"<?= $curPrivacy === $val ? ' selected' : '' ?>><?= View::escape($label) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <div class="dg-form-actions">
          <button type="submit" class="dg-button dg-button--primary">Auf YouTube speichern</button>
        </div>
      </form>
      <form method="post" action="/app?page=videos-form&amp;id=<?= $assetId ?>" class="dg-form dg-mt-3" onsubmit="return confirm('Video wirklich auf YouTube löschen?');">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <input type="hidden" name="video_youtube_delete" value="1">
        <input type="hidden" name="job_id" value="<?= (int) ($publishedYoutubeJob['id'] ?? 0) ?>">
        <div class="dg-form-actions">
          <button type="submit" class="dg-button dg-button--danger">Auf YouTube löschen</button>
        </div>
      </form>
    </section>
  <?php endif; ?>

  <section class="dg-panel dg-mb-4">
    <h2 class="dg-subsection-title">Auf LinkedIn veröffentlichen</h2>
    <p class="dg-muted">Unternehmensseite · nur MP4 · min. 75 KB. Planung erfolgt im CRM (kein natives LinkedIn-Schedule).</p>
    <?php if (!$linkedinConnected) : ?>
      <p class="dg-muted">Zuerst LinkedIn in den Einstellungen verbinden.</p>
      <div class="dg-btn-row">
        <a class="dg-button" href="<?= View::escape(SettingsRegistry::tabUrl('linkedin-publish')) ?>">LinkedIn-Einstellungen</a>
      </div>
    <?php else : ?>
      <form method="post" action="/app?page=videos-form&amp;id=<?= $assetId ?>" class="dg-form">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <input type="hidden" name="video_linkedin_publish" value="1">
        <div class="dg-form-grid">
          <label class="dg-field dg-field--wide">
            <span>Videotitel *</span>
            <input type="text" name="li_title" required maxlength="200" value="<?= View::escape((string) ($_POST['li_title'] ?? $defaultTitle)) ?>">
          </label>
          <label class="dg-field dg-field--wide">
            <span>Beitragstext</span>
            <textarea name="li_commentary" rows="5"><?= View::escape((string) ($_POST['li_commentary'] ?? '')) ?></textarea>
          </label>
          <label class="dg-field">
            <span>Geplant (optional)</span>
            <input type="datetime-local" name="scheduled_at" value="<?= View::escape((string) ($_POST['scheduled_at'] ?? '')) ?>">
            <span class="dg-field-hint">CRM startet Upload/Post zum Termin.</span>
          </label>
        </div>
        <div class="dg-form-actions">
          <button type="submit" class="dg-button dg-button--primary">Auf LinkedIn veröffentlichen</button>
        </div>
      </form>
    <?php endif; ?>
  </section>

  <?php if ($publishedLinkedinJob !== null) : ?>
    <section class="dg-panel dg-mb-4">
      <h2 class="dg-subsection-title">LinkedIn bearbeiten</h2>
      <p class="dg-muted">Post: <?= View::escape((string) ($publishedLinkedinJob['external_id'] ?? '')) ?></p>
      <form method="post" action="/app?page=videos-form&amp;id=<?= $assetId ?>" class="dg-form">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <input type="hidden" name="video_linkedin_update" value="1">
        <input type="hidden" name="job_id" value="<?= (int) ($publishedLinkedinJob['id'] ?? 0) ?>">
        <div class="dg-form-grid">
          <label class="dg-field dg-field--wide">
            <span>Beitragstext</span>
            <textarea name="commentary" rows="4"><?= View::escape((string) ($latestLinkedinPost['description'] ?? PublishJobRepository::payload($publishedLinkedinJob)['commentary'] ?? '')) ?></textarea>
          </label>
        </div>
        <div class="dg-form-actions">
          <button type="submit" class="dg-button dg-button--primary">Auf LinkedIn speichern</button>
        </div>
      </form>
      <form method="post" action="/app?page=videos-form&amp;id=<?= $assetId ?>" class="dg-form dg-mt-3" onsubmit="return confirm('Beitrag wirklich auf LinkedIn löschen?');">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <input type="hidden" name="video_linkedin_delete" value="1">
        <input type="hidden" name="job_id" value="<?= (int) ($publishedLinkedinJob['id'] ?? 0) ?>">
        <div class="dg-form-actions">
          <button type="submit" class="dg-button dg-button--danger">Auf LinkedIn löschen</button>
        </div>
      </form>
    </section>
  <?php endif; ?>

  <section class="dg-panel">
    <h2 class="dg-subsection-title">Publish-Jobs</h2>
    <div class="dg-table-wrap">
      <table class="dg-table dg-table--compact">
        <thead>
          <tr>
            <th>ID</th>
            <th>Plattform</th>
            <th>Status</th>
            <th>Externe ID</th>
            <th>Fortschritt</th>
            <th>Fehler</th>
            <th>Aktualisiert</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($publishJobs === []) : ?>
            <tr><td colspan="7" class="dg-table__empty">Noch keine Jobs.</td></tr>
          <?php else : ?>
            <?php foreach ($publishJobs as $job) : ?>
              <?php
                $st = (string) ($job['state'] ?? '');
                $plat = (string) ($job['platform'] ?? '');
                $size = (int) ($asset['size_bytes'] ?? 0);
                $off = (int) ($job['upload_offset'] ?? 0);
                $prog = $size > 0 ? min(100, round(100 * $off / $size)) : 0;
                if ($plat === PublishJobRepository::PLATFORM_LINKEDIN && $st === PublishJobRepository::STATE_UPLOADING) {
                    $payload = PublishJobRepository::payload($job);
                    $parts = is_array($payload['upload_instructions'] ?? null) ? count($payload['upload_instructions']) : 0;
                    if ($parts > 0) {
                        $prog = min(100, round(100 * $off / $parts));
                    }
                }
              ?>
              <tr>
                <td><?= (int) ($job['id'] ?? 0) ?></td>
                <td><?= View::escape($plat !== '' ? $plat : '—') ?></td>
                <td><?= View::escape($stateLabels[$st] ?? $st) ?></td>
                <td><?php
                  $extId = trim((string) ($job['external_id'] ?? ''));
                  echo View::escape($extId !== '' ? $extId : '—');
                ?></td>
                <td><?= (int) $prog ?> %</td>
                <td><?= View::escape((string) ($job['last_error'] ?? '') !== '' ? (string) $job['last_error'] : '—') ?></td>
                <td><?= View::escape((string) ($job['updated_at'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <form method="post" action="/app?page=videos-form&amp;id=<?= $assetId ?>" class="dg-form dg-mt-4" onsubmit="return confirm('Video lokal löschen?');">
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
    <input type="hidden" name="video_asset_delete" value="1">
    <div class="dg-form-actions">
      <button type="submit" class="dg-button dg-button--danger">Lokal löschen</button>
      <a class="dg-button" href="/app?page=videos">Abbrechen</a>
    </div>
  </form>
</div>
