<?php
/** @var array<string, mixed>|null $supportGrant */
/** @var bool $canEdit */
/** @var bool $dbConnected */
/** @var array{type: string, message: string}|null $flash */
/** @var string|null $supportTokenOnce */
$grant = $supportGrant ?? null;
$active = is_array($grant);
$tokenOnce = $supportTokenOnce ?? null;
$readOnly = !($canEdit ?? false);
$hoursDefault = SupportAccessService::DEFAULT_HOURS;
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header">
    <h1 class="dg-page-title">Support-Freigabe</h1>
    <p class="dg-lead">
      Geben Sie Ganz Soft zeitlich begrenzt Zugang zu diesem CRM und optional Ihren Bildschirm
      (nur Zuschauen). Sie können die Freigabe jederzeit beenden.
    </p>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Datenbankverbindung erforderlich.</div>
  <?php endif; ?>

  <?php if ($tokenOnce) : ?>
    <div class="dg-flash dg-flash--success">
      Freigabe gestartet. Der Support kann die Session jetzt über den KDV-Hub öffnen.
    </div>
  <?php endif; ?>

  <?php if ($active) : ?>
    <section class="dg-panel">
      <h2>Freigabe aktiv</h2>
      <p>
        Läuft noch ca. <strong><?= View::escape(SupportAccessService::remainingLabel($grant)) ?></strong>
        (bis <?= View::escape(date('d.m.Y H:i', strtotime((string) $grant['expires_at']))) ?> Uhr).
      </p>
      <p class="dg-muted">Bildschirmfreigabe: <?= !empty($grant['screen_share_enabled']) ? 'erlaubt' : 'aus' ?></p>

      <?php if (!empty($grant['screen_share_enabled']) && !$readOnly) : ?>
        <div class="dg-panel dg-panel--nested" id="dg-support-share">
          <h3>Bildschirm für Support freigeben</h3>
          <p class="dg-field-hint">
            Der Support sieht Ihren Bildschirm und Mauszeiger. Die Steuerung bleibt bei Ihnen.
            Der Browser fragt Sie um Erlaubnis.
          </p>
          <video id="dg-support-local-preview" class="dg-support-video" playsinline muted autoplay hidden></video>
          <div class="dg-form-actions">
            <button type="button" class="dg-button dg-button--primary" id="dg-support-share-start">Bildschirm teilen</button>
            <button type="button" class="dg-button" id="dg-support-share-stop" hidden>Teilen beenden</button>
          </div>
          <p class="dg-muted" id="dg-support-share-status"></p>
        </div>
      <?php endif; ?>

      <?php if (!$readOnly) : ?>
        <form method="post" action="/app?page=support-freigabe" class="dg-form-actions" style="margin-top:1rem">
          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
          <input type="hidden" name="support_access_stop" value="1">
          <button type="submit" class="dg-button dg-button--danger">Freigabe jetzt beenden</button>
        </form>
      <?php endif; ?>
    </section>
  <?php else : ?>
    <section class="dg-panel">
      <h2>Neue Freigabe starten</h2>
      <form class="dg-form" method="post" action="/app?page=support-freigabe">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <input type="hidden" name="support_access_start" value="1">

        <label class="dg-field">
          <span>Dauer</span>
          <select name="duration_hours"<?= $readOnly ? ' disabled' : '' ?>>
            <?php foreach (SupportAccessService::DURATIONS as $h => $label) : ?>
              <option value="<?= (int) $h ?>"<?= (int) $h === $hoursDefault ? ' selected' : '' ?>>
                <?= View::escape($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="dg-field dg-field--checkbox">
          <span>
            <input type="checkbox" name="screen_share" value="1" checked<?= $readOnly ? ' disabled' : '' ?>>
            Bildschirm-Zuschauen erlauben (Support sieht Bildschirm + Maus, steuert nicht)
          </span>
        </label>

        <div class="dg-flash dg-flash--warning" style="margin:1rem 0">
          Mit der Freigabe erlauben Sie Ganz Soft den Zugang zu diesem CRM für die gewählte Dauer.
          Sensible Daten können eingesehen werden. Beenden Sie die Freigabe, sobald der Support fertig ist.
        </div>

        <?php if (!$readOnly) : ?>
          <div class="dg-form-actions">
            <button type="submit" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>>
              Support-Freigabe starten
            </button>
          </div>
        <?php endif; ?>
      </form>
    </section>
  <?php endif; ?>
</div>
<?php if ($active && !empty($grant['screen_share_enabled']) && !$readOnly) : ?>
<script>
window.DG_SUPPORT_SHARE = {
  csrf: <?= json_encode(Csrf::token(), JSON_UNESCAPED_UNICODE) ?>,
  role: 'customer',
  signalUrl: '/api/support/signal'
};
</script>
<script src="<?= View::escape(Asset::url('/assets/js/support-screen-share.js')) ?>"></script>
<?php endif; ?>
