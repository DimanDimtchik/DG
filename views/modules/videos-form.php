<?php
/**
 * @var array<string, mixed> $videoAsset
 * @var list<array<string, mixed>> $publishJobs
 * @var array<string, mixed>|null $latestPost
 * @var array<string, mixed>|null $latestYoutubePost
 * @var array<string, mixed>|null $latestLinkedinPost
 * @var array<string, mixed>|null $latestInstagramPost
 * @var array<string, mixed>|null $latestTiktokPost
 * @var bool $youtubeConnected
 * @var bool $linkedinConnected
 * @var bool $instagramConnected
 * @var bool $tiktokConnected
 * @var bool $instagramCanDelete
 * @var string $tiktokPostingMode
 * @var array<string, mixed> $tiktokCreatorInfo
 * @var array<string, mixed> $linkedinEngagement
 * @var array<string, mixed> $instagramEngagement
 * @var array<string, mixed> $tiktokEngagement
 * @var string|null $formError
 * @var array{type: string, message: string}|null $flash
 */
$asset = is_array($videoAsset ?? null) ? $videoAsset : [];
$publishJobs = is_array($publishJobs ?? null) ? $publishJobs : [];
$latestYoutubePost = is_array($latestYoutubePost ?? null)
    ? $latestYoutubePost
    : (is_array($latestPost ?? null) ? $latestPost : null);
$latestLinkedinPost = is_array($latestLinkedinPost ?? null) ? $latestLinkedinPost : null;
$latestInstagramPost = is_array($latestInstagramPost ?? null) ? $latestInstagramPost : null;
$latestTiktokPost = is_array($latestTiktokPost ?? null) ? $latestTiktokPost : null;
$youtubeConnected = !empty($youtubeConnected);
$linkedinConnected = !empty($linkedinConnected);
$instagramConnected = !empty($instagramConnected);
$tiktokConnected = !empty($tiktokConnected);
$instagramCanDelete = !empty($instagramCanDelete);
$tiktokPostingMode = (string) ($tiktokPostingMode ?? TikTokPublishSettings::MODE_INBOX);
$tiktokCreatorInfo = is_array($tiktokCreatorInfo ?? null) ? $tiktokCreatorInfo : [
    'privacy_level_options' => [],
    'max_video_post_duration_sec' => 0,
    'creator_username' => '',
    'error' => '',
];
$ttPrivacyLabels = [
    'PUBLIC_TO_EVERYONE' => 'Öffentlich',
    'MUTUAL_FOLLOW_FRIENDS' => 'Freunde',
    'FOLLOWER_OF_CREATOR' => 'Follower',
    'SELF_ONLY' => 'Nur ich',
];
$linkedinEngagement = is_array($linkedinEngagement ?? null) ? $linkedinEngagement : [
    'post_urn' => '',
    'comments' => [],
    'summary' => ['likes' => 0, 'comments' => 0],
    'insights' => [
        'video_views' => 0,
        'viewers' => 0,
        'time_watched_ms' => 0,
        'time_watched_label' => '—',
    ],
    'error' => '',
];
$instagramEngagement = is_array($instagramEngagement ?? null) ? $instagramEngagement : [
    'media_id' => '',
    'comments' => [],
    'insights' => [
        'plays' => 0,
        'reach' => 0,
        'likes' => 0,
        'comments' => 0,
        'shares' => 0,
        'saved' => 0,
        'total_interactions' => 0,
    ],
    'error' => '',
];
$tiktokEngagement = is_array($tiktokEngagement ?? null) ? $tiktokEngagement : [
    'video_id' => '',
    'share_url' => '',
    'title' => '',
    'insights' => [
        'views' => 0,
        'likes' => 0,
        'comments' => 0,
        'shares' => 0,
        'duration' => 0,
    ],
    'account' => [
        'follower_count' => 0,
        'following_count' => 0,
        'likes_count' => 0,
        'video_count' => 0,
        'display_name' => '',
    ],
    'comments_available' => false,
    'comments_note' => '',
    'error' => '',
];
$assetId = (int) ($asset['id'] ?? 0);
$stateLabels = PublishJobRepository::stateOptions();
$publishedYoutubeJob = null;
$publishedLinkedinJob = null;
$publishedInstagramJob = null;
$publishedTiktokJob = null;
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
    if ($plat === PublishJobRepository::PLATFORM_INSTAGRAM && $publishedInstagramJob === null) {
        $publishedInstagramJob = $j;
    }
    if ($plat === PublishJobRepository::PLATFORM_TIKTOK && $publishedTiktokJob === null) {
        $publishedTiktokJob = $j;
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
          'label' => 'Zurück zu Social-Video',
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

  <section class="dg-panel dg-mb-4">
    <h2 class="dg-subsection-title">Auf Instagram veröffentlichen</h2>
    <p class="dg-muted">
      Reels (MP4/MOV). Caption nach Publish nicht mehr änderbar.
      Facebook-Login: Resume-Upload; Instagram-Login: kurzlebige öffentliche Datei-URL.
    </p>
    <?php if (!$instagramConnected) : ?>
      <p class="dg-muted">Zuerst Instagram in den Einstellungen verbinden.</p>
      <div class="dg-btn-row">
        <a class="dg-button" href="<?= View::escape(SettingsRegistry::tabUrl('instagram-publish')) ?>">Instagram-Einstellungen</a>
      </div>
    <?php else : ?>
      <form method="post" action="/app?page=videos-form&amp;id=<?= $assetId ?>" class="dg-form">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <input type="hidden" name="video_instagram_publish" value="1">
        <div class="dg-form-grid">
          <label class="dg-field dg-field--wide">
            <span>Interner Titel</span>
            <input type="text" name="ig_title" maxlength="200" value="<?= View::escape((string) ($_POST['ig_title'] ?? $defaultTitle)) ?>">
          </label>
          <label class="dg-field dg-field--wide">
            <span>Caption</span>
            <textarea name="ig_caption" rows="5" maxlength="2200"><?= View::escape((string) ($_POST['ig_caption'] ?? '')) ?></textarea>
            <span class="dg-field-hint">Max. ca. 2200 Zeichen. Nach dem Posten nicht mehr editierbar.</span>
          </label>
          <label class="dg-field dg-field--checkbox">
            <span class="dg-checkbox">
              <input type="checkbox" name="ig_share_to_feed" value="1"<?= !isset($_POST['ig_share_to_feed']) || !empty($_POST['ig_share_to_feed']) ? ' checked' : '' ?>>
              <span>Auch im Feed zeigen</span>
            </span>
          </label>
          <label class="dg-field">
            <span>Geplant (optional)</span>
            <input type="datetime-local" name="ig_scheduled_at" value="<?= View::escape((string) ($_POST['ig_scheduled_at'] ?? '')) ?>">
            <span class="dg-field-hint">CRM startet Container/Publish zum Termin.</span>
          </label>
        </div>
        <div class="dg-form-actions">
          <button type="submit" class="dg-button dg-button--primary">Als Reel veröffentlichen</button>
        </div>
      </form>
    <?php endif; ?>
  </section>

  <section class="dg-panel dg-mb-4">
    <h2 class="dg-subsection-title">Auf TikTok veröffentlichen</h2>
    <?php
      $ttIsDirect = $tiktokPostingMode === TikTokPublishSettings::MODE_DIRECT;
      $ttPrivacyOpts = is_array($tiktokCreatorInfo['privacy_level_options'] ?? null)
          ? $tiktokCreatorInfo['privacy_level_options']
          : [];
      $ttMaxSec = (int) ($tiktokCreatorInfo['max_video_post_duration_sec'] ?? 0);
    ?>
    <p class="dg-muted">
      <?php if ($ttIsDirect) : ?>
        Direct Post (nach Audit). Privacy manuell wählen — keine Vorbelegung.
        Caption nach Publish nicht mehr änderbar; Löschen über die API nicht vorgesehen.
      <?php else : ?>
        Inbox / Draft: Video landet in der TikTok-Inbox; der Creator beendet den Post in der App
        (kein echter One-Click).
      <?php endif; ?>
      <?php if ($ttMaxSec > 0) : ?>
        Max. Videolänge laut Konto: <?= $ttMaxSec ?> s.
      <?php endif; ?>
    </p>
    <?php if (!$tiktokConnected) : ?>
      <p class="dg-muted">Zuerst TikTok in den Einstellungen verbinden.</p>
      <div class="dg-btn-row">
        <a class="dg-button" href="<?= View::escape(SettingsRegistry::tabUrl('tiktok-publish')) ?>">TikTok-Einstellungen</a>
      </div>
    <?php elseif (($tiktokCreatorInfo['error'] ?? '') !== '') : ?>
      <div class="dg-flash dg-flash--warning"><?= View::escape((string) $tiktokCreatorInfo['error']) ?></div>
      <p class="dg-muted">Creator-Info konnte nicht geladen werden — erneut verbinden oder später versuchen.</p>
    <?php else : ?>
      <form method="post" action="/app?page=videos-form&amp;id=<?= $assetId ?>" class="dg-form">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <input type="hidden" name="video_tiktok_publish" value="1">
        <div class="dg-form-grid">
          <label class="dg-field dg-field--wide">
            <span>Titel / Caption</span>
            <textarea name="tt_caption" rows="4" maxlength="2200"><?= View::escape((string) ($_POST['tt_caption'] ?? $defaultTitle)) ?></textarea>
            <span class="dg-field-hint">Max. 2200 Zeichen (UTF-16). Bei Inbox oft in der App nachbearbeitet.</span>
          </label>
          <input type="hidden" name="tt_title" value="<?= View::escape((string) ($_POST['tt_title'] ?? $defaultTitle)) ?>">
          <?php if ($ttIsDirect) : ?>
            <label class="dg-field">
              <span>Sichtbarkeit</span>
              <select name="tt_privacy_level" required>
                <option value="">— bitte wählen —</option>
                <?php foreach ($ttPrivacyOpts as $opt) : ?>
                  <?php
                    $opt = (string) $opt;
                    $lab = $ttPrivacyLabels[$opt] ?? $opt;
                  ?>
                  <option value="<?= View::escape($opt) ?>"<?= (string) ($_POST['tt_privacy_level'] ?? '') === $opt ? ' selected' : '' ?>>
                    <?= View::escape($lab) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <span class="dg-field-hint">Nur Stufen, die TikTok für dieses Konto erlaubt.</span>
            </label>
            <label class="dg-field dg-field--checkbox">
              <span class="dg-checkbox">
                <input type="checkbox" name="tt_brand_content" value="1"<?= !empty($_POST['tt_brand_content']) ? ' checked' : '' ?>>
                <span>Bezahlte Partnerschaft (Drittanbieter)</span>
              </span>
            </label>
            <label class="dg-field dg-field--checkbox">
              <span class="dg-checkbox">
                <input type="checkbox" name="tt_brand_organic" value="1"<?= !empty($_POST['tt_brand_organic']) ? ' checked' : '' ?>>
                <span>Eigenes Business bewerben</span>
              </span>
            </label>
            <label class="dg-field dg-field--checkbox">
              <span class="dg-checkbox">
                <input type="checkbox" name="tt_is_aigc" value="1"<?= !empty($_POST['tt_is_aigc']) ? ' checked' : '' ?>>
                <span>KI-generierter Inhalt</span>
              </span>
            </label>
            <label class="dg-field dg-field--checkbox">
              <span class="dg-checkbox">
                <input type="checkbox" name="tt_disable_comment" value="1"<?= !empty($_POST['tt_disable_comment']) ? ' checked' : '' ?>>
                <span>Kommentare aus</span>
              </span>
            </label>
            <label class="dg-field dg-field--checkbox">
              <span class="dg-checkbox">
                <input type="checkbox" name="tt_disable_duet" value="1"<?= !empty($_POST['tt_disable_duet']) ? ' checked' : '' ?>>
                <span>Duet aus</span>
              </span>
            </label>
            <label class="dg-field dg-field--checkbox">
              <span class="dg-checkbox">
                <input type="checkbox" name="tt_disable_stitch" value="1"<?= !empty($_POST['tt_disable_stitch']) ? ' checked' : '' ?>>
                <span>Stitch aus</span>
              </span>
            </label>
          <?php endif; ?>
          <label class="dg-field">
            <span>Geplant (optional)</span>
            <input type="datetime-local" name="tt_scheduled_at" value="<?= View::escape((string) ($_POST['tt_scheduled_at'] ?? '')) ?>">
            <span class="dg-field-hint">CRM startet Upload zum Termin (TikTok plant nicht).</span>
          </label>
        </div>
        <div class="dg-form-actions">
          <button type="submit" class="dg-button dg-button--primary">
            <?= $ttIsDirect ? 'Auf TikTok posten' : 'Als Draft an TikTok senden' ?>
          </button>
        </div>
      </form>
    <?php endif; ?>
  </section>

  <?php if ($publishedTiktokJob !== null) : ?>
    <section class="dg-panel dg-mb-4">
      <h2 class="dg-subsection-title">TikTok</h2>
      <p class="dg-muted">
        ID: <?= View::escape((string) ($publishedTiktokJob['external_id'] ?? '')) ?>
        · Nach Publish keine Caption-Änderung / kein Löschen über die Posting-API.
      </p>
    </section>

    <section class="dg-panel dg-mb-4" id="tiktok-engagement">
      <h2 class="dg-subsection-title">TikTok: Insights</h2>
      <?php if (($tiktokEngagement['error'] ?? '') !== '') : ?>
        <div class="dg-flash dg-flash--warning"><?= View::escape((string) $tiktokEngagement['error']) ?></div>
        <p class="dg-muted">Falls neu: TikTok erneut verbinden (Video-Listen-Scope). Inbox-Drafts ohne Item-ID haben noch keine Insights.</p>
      <?php else : ?>
        <?php
          $ttIns = is_array($tiktokEngagement['insights'] ?? null) ? $tiktokEngagement['insights'] : [];
          $ttAcc = is_array($tiktokEngagement['account'] ?? null) ? $tiktokEngagement['account'] : [];
        ?>
        <?php if (($tiktokEngagement['title'] ?? '') !== '' || ($tiktokEngagement['share_url'] ?? '') !== '') : ?>
          <p class="dg-muted dg-mb-3">
            <?= View::escape((string) ($tiktokEngagement['title'] ?? '')) ?>
            <?php if (($tiktokEngagement['share_url'] ?? '') !== '') : ?>
              · <a href="<?= View::escape((string) $tiktokEngagement['share_url']) ?>" target="_blank" rel="noopener">Auf TikTok öffnen</a>
            <?php endif; ?>
          </p>
        <?php endif; ?>
        <div class="dg-table-wrap dg-mb-3">
          <table class="dg-table dg-table--compact">
            <thead>
              <tr>
                <th>Views</th>
                <th>Likes</th>
                <th>Kommentare (Anzahl)</th>
                <th>Shares</th>
                <th>Dauer (s)</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><?= (int) ($ttIns['views'] ?? 0) ?></td>
                <td><?= (int) ($ttIns['likes'] ?? 0) ?></td>
                <td><?= (int) ($ttIns['comments'] ?? 0) ?></td>
                <td><?= (int) ($ttIns['shares'] ?? 0) ?></td>
                <td><?= (int) ($ttIns['duration'] ?? 0) ?></td>
              </tr>
            </tbody>
          </table>
        </div>
        <?php if ((int) ($ttAcc['follower_count'] ?? 0) > 0 || (string) ($ttAcc['display_name'] ?? '') !== '') : ?>
          <p class="dg-muted dg-mb-3">
            Konto<?= ($ttAcc['display_name'] ?? '') !== '' ? ': ' . View::escape((string) $ttAcc['display_name']) : '' ?>
            · Follower <?= (int) ($ttAcc['follower_count'] ?? 0) ?>
            · Videos <?= (int) ($ttAcc['video_count'] ?? 0) ?>
            · Likes gesamt <?= (int) ($ttAcc['likes_count'] ?? 0) ?>
          </p>
        <?php endif; ?>
        <p class="dg-muted"><?= View::escape((string) ($tiktokEngagement['comments_note'] ?? '')) ?></p>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($publishedInstagramJob !== null) : ?>
    <section class="dg-panel dg-mb-4">
      <h2 class="dg-subsection-title">Instagram</h2>
      <p class="dg-muted">
        Media-ID: <?= View::escape((string) ($publishedInstagramJob['external_id'] ?? '')) ?>
        · Caption ist nach Publish fest (API).
      </p>
      <?php if ($instagramCanDelete) : ?>
        <form method="post" action="/app?page=videos-form&amp;id=<?= $assetId ?>" class="dg-form" onsubmit="return confirm('Beitrag wirklich auf Instagram löschen?');">
          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
          <input type="hidden" name="video_instagram_delete" value="1">
          <input type="hidden" name="job_id" value="<?= (int) ($publishedInstagramJob['id'] ?? 0) ?>">
          <div class="dg-form-actions">
            <button type="submit" class="dg-button dg-button--danger">Auf Instagram löschen</button>
          </div>
        </form>
      <?php else : ?>
        <p class="dg-muted">Löschen aus dem CRM nur mit Facebook-Login-Pfad.</p>
      <?php endif; ?>
    </section>

    <section class="dg-panel dg-mb-4" id="instagram-engagement">
      <h2 class="dg-subsection-title">Instagram: Insights &amp; Kommentare</h2>
      <?php if (($instagramEngagement['error'] ?? '') !== '') : ?>
        <div class="dg-flash dg-flash--warning"><?= View::escape((string) $instagramEngagement['error']) ?></div>
        <p class="dg-muted">Falls neu: Instagram erneut verbinden (Kommentar-/Insights-Scopes).</p>
      <?php else : ?>
        <?php
          $igIns = is_array($instagramEngagement['insights'] ?? null) ? $instagramEngagement['insights'] : [];
          $igComments = is_array($instagramEngagement['comments'] ?? null) ? $instagramEngagement['comments'] : [];
        ?>
        <div class="dg-table-wrap dg-mb-3">
          <table class="dg-table dg-table--compact">
            <thead>
              <tr>
                <th>Plays</th>
                <th>Reichweite</th>
                <th>Likes</th>
                <th>Kommentare</th>
                <th>Shares</th>
                <th>Gespeichert</th>
                <th>Interaktionen</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><?= (int) ($igIns['plays'] ?? 0) ?></td>
                <td><?= (int) ($igIns['reach'] ?? 0) ?></td>
                <td><?= (int) ($igIns['likes'] ?? 0) ?></td>
                <td><?= (int) ($igIns['comments'] ?? 0) ?></td>
                <td><?= (int) ($igIns['shares'] ?? 0) ?></td>
                <td><?= (int) ($igIns['saved'] ?? 0) ?></td>
                <td><?= (int) ($igIns['total_interactions'] ?? 0) ?></td>
              </tr>
            </tbody>
          </table>
        </div>
        <p class="dg-muted dg-mb-3">Metriken hängen von Meta-Freigabe und Medientyp ab; manche Werte können 0 bleiben.</p>

        <form method="post" action="/app?page=videos-form&amp;id=<?= $assetId ?>#instagram-engagement" class="dg-form dg-mb-4">
          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
          <input type="hidden" name="video_instagram_comment_reply" value="1">
          <input type="hidden" name="job_id" value="<?= (int) ($publishedInstagramJob['id'] ?? 0) ?>">
          <div class="dg-form-grid">
            <label class="dg-field dg-field--wide">
              <span>Antwort als Instagram-Konto</span>
              <textarea name="comment_text" rows="3" required></textarea>
            </label>
          </div>
          <div class="dg-form-actions">
            <button type="submit" class="dg-button dg-button--primary">Kommentar senden</button>
          </div>
        </form>

        <div class="dg-table-wrap">
          <table class="dg-table">
            <thead>
              <tr>
                <th>Zeit</th>
                <th>Autor</th>
                <th>Text</th>
                <th class="dg-table__num">Likes</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php if ($igComments === []) : ?>
                <tr><td colspan="5" class="dg-table__empty">Noch keine Kommentare.</td></tr>
              <?php else : ?>
                <?php foreach ($igComments as $c) : ?>
                  <?php if (!is_array($c)) {
                      continue;
                  } ?>
                  <tr>
                    <td><?= View::escape((string) ($c['created_label'] ?? '—')) ?></td>
                    <td><?= View::escape((string) ($c['username'] ?? '')) ?></td>
                    <td><?= View::escape((string) ($c['text'] ?? '')) ?></td>
                    <td class="dg-table__num"><?= (int) ($c['likes'] ?? 0) ?></td>
                    <td class="dg-table__actions">
                      <div class="dg-table__actions-group">
                        <form method="post" action="/app?page=videos-form&amp;id=<?= $assetId ?>#instagram-engagement" class="dg-form">
                          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                          <input type="hidden" name="video_instagram_comment_hide" value="1">
                          <input type="hidden" name="job_id" value="<?= (int) ($publishedInstagramJob['id'] ?? 0) ?>">
                          <input type="hidden" name="comment_id" value="<?= View::escape((string) ($c['id'] ?? '')) ?>">
                          <button type="submit" class="dg-button dg-button--small">Ausblenden</button>
                        </form>
                        <form method="post" action="/app?page=videos-form&amp;id=<?= $assetId ?>#instagram-engagement" class="dg-form" onsubmit="return confirm('Kommentar löschen?');">
                          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                          <input type="hidden" name="video_instagram_comment_delete" value="1">
                          <input type="hidden" name="job_id" value="<?= (int) ($publishedInstagramJob['id'] ?? 0) ?>">
                          <input type="hidden" name="comment_id" value="<?= View::escape((string) ($c['id'] ?? '')) ?>">
                          <button type="submit" class="dg-button dg-button--small dg-button--danger">Löschen</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

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

    <section class="dg-panel dg-mb-4" id="linkedin-engagement">
      <h2 class="dg-subsection-title">LinkedIn: Insights &amp; Kommentare</h2>
      <?php if (($linkedinEngagement['error'] ?? '') !== '') : ?>
        <div class="dg-flash dg-flash--warning"><?= View::escape((string) $linkedinEngagement['error']) ?></div>
        <p class="dg-muted">Falls neu: LinkedIn erneut verbinden (zusätzliche Feed-Scopes für Kommentare).</p>
      <?php else : ?>
        <?php
          $sum = is_array($linkedinEngagement['summary'] ?? null) ? $linkedinEngagement['summary'] : [];
          $ins = is_array($linkedinEngagement['insights'] ?? null) ? $linkedinEngagement['insights'] : [];
          $comments = is_array($linkedinEngagement['comments'] ?? null) ? $linkedinEngagement['comments'] : [];
        ?>
        <div class="dg-table-wrap dg-mb-3">
          <table class="dg-table dg-table--compact">
            <thead>
              <tr>
                <th>Likes</th>
                <th>Kommentare</th>
                <th>Video-Views</th>
                <th>Zuschauer</th>
                <th>Watch-Time</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><?= (int) ($sum['likes'] ?? 0) ?></td>
                <td><?= (int) ($sum['comments'] ?? 0) ?></td>
                <td><?= (int) ($ins['video_views'] ?? 0) ?></td>
                <td><?= (int) ($ins['viewers'] ?? 0) ?></td>
                <td><?= View::escape((string) ($ins['time_watched_label'] ?? '—')) ?></td>
              </tr>
            </tbody>
          </table>
        </div>
        <p class="dg-muted dg-mb-3">Video-Views nur bei ugcPost-URN und Daten &lt; 6 Monate. Likes/Kommentare über Social Actions.</p>

        <form method="post" action="/app?page=videos-form&amp;id=<?= $assetId ?>#linkedin-engagement" class="dg-form dg-mb-4">
          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
          <input type="hidden" name="video_linkedin_comment_reply" value="1">
          <input type="hidden" name="job_id" value="<?= (int) ($publishedLinkedinJob['id'] ?? 0) ?>">
          <div class="dg-form-grid">
            <label class="dg-field dg-field--wide">
              <span>Antwort als Unternehmensseite</span>
              <textarea name="comment_text" rows="3" required></textarea>
            </label>
          </div>
          <div class="dg-form-actions">
            <button type="submit" class="dg-button dg-button--primary">Kommentar senden</button>
          </div>
        </form>

        <div class="dg-table-wrap">
          <table class="dg-table">
            <thead>
              <tr>
                <th>Zeit</th>
                <th>Autor</th>
                <th>Text</th>
                <th class="dg-table__num">Likes</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php if ($comments === []) : ?>
                <tr><td colspan="5" class="dg-table__empty">Noch keine Kommentare.</td></tr>
              <?php else : ?>
                <?php foreach ($comments as $c) : ?>
                  <?php if (!is_array($c)) {
                      continue;
                  } ?>
                  <tr>
                    <td><?= View::escape((string) ($c['created_label'] ?? '—')) ?></td>
                    <td><?= View::escape((string) ($c['actor'] ?? '')) ?></td>
                    <td><?= View::escape((string) ($c['text'] ?? '')) ?></td>
                    <td class="dg-table__num"><?= (int) ($c['likes'] ?? 0) ?></td>
                    <td class="dg-table__actions">
                      <div class="dg-table__actions-group">
                        <form method="post" action="/app?page=videos-form&amp;id=<?= $assetId ?>#linkedin-engagement" class="dg-form" onsubmit="return confirm('Kommentar löschen?');">
                          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                          <input type="hidden" name="video_linkedin_comment_delete" value="1">
                          <input type="hidden" name="job_id" value="<?= (int) ($publishedLinkedinJob['id'] ?? 0) ?>">
                          <input type="hidden" name="comment_id" value="<?= View::escape((string) ($c['id'] ?? '')) ?>">
                          <button type="submit" class="dg-button dg-button--small dg-button--danger">Löschen</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
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
