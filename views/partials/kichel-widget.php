<?php
/** Kichel — KI-Helfer (regelbasiert, ohne externe API). */
/** @var User $user */
$kichelIsAdmin = RoleResolver::isAdmin($user);
?>
<link rel="stylesheet" href="<?= View::escape(Asset::url('/assets/css/kichel.css')) ?>">
<button type="button" class="dg-kichel-fab" data-kichel-fab aria-expanded="false" aria-controls="dg-kichel-panel" title="Kichel — CRM-Hilfe">
  <img src="<?= View::escape(Asset::url('/assets/img/kichel.svg')) ?>" alt="">
</button>
<div id="dg-kichel-panel" class="dg-kichel-panel" data-kichel-panel hidden role="dialog" aria-label="Kichel Hilfe">
  <div class="dg-kichel-panel__head">
    <img src="<?= View::escape(Asset::url('/assets/img/kichel.svg')) ?>" alt="">
    <div>
      <strong>Kichel</strong>
      <span>Fachfragen · Code · Datenbank</span>
    </div>
    <?php if ($kichelIsAdmin) : ?>
      <span class="dg-kichel-panel__admin"><a href="/app?page=kichel-protokoll">Protokoll</a></span>
    <?php endif; ?>
    <button type="button" class="dg-kichel-panel__close" data-kichel-close aria-label="Schließen">&times;</button>
  </div>
  <div class="dg-kichel-panel__body" data-kichel-body>
    <div class="dg-kichel-chips">
      <button type="button" class="dg-kichel-chip" data-kichel-chip="Wo trage ich die USt-ID ein?">USt-ID</button>
      <button type="button" class="dg-kichel-chip" data-kichel-chip="Skonto und Mahnung">Skonto</button>
      <button type="button" class="dg-kichel-chip" data-kichel-chip="dg_contacts contact_note">Kontakt Bemerkung</button>
      <button type="button" class="dg-kichel-chip" data-kichel-chip="Wo finde ich Pflichtseiten?">Pflichtseiten</button>
      <button type="button" class="dg-kichel-chip" data-kichel-chip="Belegkette Workflow">Belegkette</button>
    </div>
    <div class="dg-kichel-msg dg-kichel-msg--bot">Hallo! Ich helfe bei CRM-Navigation, Steuer-/Buchhaltungsfragen und durchsuche Code sowie DB-Schema — alles lokal im CRM, ohne Cloud-KI.</div>
  </div>
  <form class="dg-kichel-form" data-kichel-form>
    <input type="text" data-kichel-input placeholder="Frage stellen …" autocomplete="off" maxlength="500" aria-label="Frage an Kichel">
    <button type="submit">Senden</button>
  </form>
</div>
<script>
  window.dgKichel = {
    apiUrl: '/api/kichel',
    csrf: <?= json_encode(Csrf::token(), JSON_THROW_ON_ERROR) ?>
  };
</script>
<script src="<?= View::escape(Asset::url('/assets/js/kichel.js')) ?>" defer></script>
