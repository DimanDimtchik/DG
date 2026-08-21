<?php
/** @var array<string, mixed>|null $supportGrant */
/** @var array{type: string, message: string}|null $flash */
$grant = $supportGrant ?? null;
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>
  <header class="dg-page-header">
    <h1 class="dg-page-title">Support-Zuschauen</h1>
    <p class="dg-lead">Live-Bildschirm des Kunden (nur Anzeige). Der Kunde muss „Bildschirm teilen“ starten.</p>
  </header>

  <?php if ($grant === null) : ?>
    <div class="dg-flash dg-flash--warning">Keine aktive Support-Freigabe.</div>
  <?php elseif (empty($grant['screen_share_enabled'])) : ?>
    <div class="dg-flash dg-flash--warning">Bildschirmfreigabe ist für diese Session nicht freigeschaltet.</div>
  <?php else : ?>
    <div class="dg-panel">
      <p class="dg-muted">Freigabe bis <?= View::escape(date('d.m.Y H:i', strtotime((string) $grant['expires_at']))) ?> Uhr</p>
      <video id="dg-support-remote-video" class="dg-support-video dg-support-video--remote" playsinline autoplay></video>
      <p class="dg-muted" id="dg-support-share-status">Warte auf Bildschirmfreigabe durch den Kunden…</p>
    </div>
    <script>
    window.DG_SUPPORT_SHARE = {
      csrf: <?= json_encode(Csrf::token(), JSON_UNESCAPED_UNICODE) ?>,
      role: 'support',
      signalUrl: '/api/support/signal'
    };
    </script>
    <script src="<?= View::escape(Asset::url('/assets/js/support-screen-share.js')) ?>"></script>
  <?php endif; ?>
</div>
