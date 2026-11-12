<?php
/** Kichel — KI-Helfer (regelbasiert, ohne externe API). */
/** @var User|null $user */
$kichelUser = $user ?? AuthService::user();
$kichelIsAdmin = $kichelUser instanceof User && RoleResolver::isAdmin($kichelUser);
$kichelIconPath = is_file(DG_ROOT . '/assets/img/kichel-mascot.jpg')
    ? '/assets/img/kichel-mascot.jpg'
    : '/assets/img/kichel.svg';
$kichelIconUrl = Asset::url($kichelIconPath);
$kichelIconClass = str_ends_with($kichelIconPath, '.jpg') || str_ends_with($kichelIconPath, '.png')
    ? ' dg-kichel-avatar--photo'
    : '';
?>
<link rel="stylesheet" href="<?= View::escape(Asset::url('/assets/css/kichel.css')) ?>">
<button type="button" class="dg-kichel-fab<?= $kichelIconClass ?>" data-kichel-fab aria-expanded="false" aria-controls="dg-kichel-panel" title="Kichel — CRM-Hilfe">
  <img src="<?= View::escape($kichelIconUrl) ?>" alt="">
</button>
<div id="dg-kichel-panel" class="dg-kichel-panel" data-kichel-panel hidden role="dialog" aria-label="Kichel Hilfe">
  <div class="dg-kichel-panel__head">
    <img class="<?= trim($kichelIconClass) ?>" src="<?= View::escape($kichelIconUrl) ?>" alt="">
    <div>
      <strong>Kichel</strong>
      <span>Schnelle Hilfe im CRM</span>
    </div>
    <span class="dg-kichel-panel__admin">
      <a class="dg-kichel-action" href="/app?page=superkihel" data-kichel-super>Super-Kichel</a>
      <?php if ($kichelIsAdmin) : ?>
        <a href="/app?page=kichel-protokoll" data-kichel-protokoll>Protokoll</a>
      <?php endif; ?>
    </span>
    <button type="button" class="dg-kichel-panel__close" data-kichel-close aria-label="Schließen">&times;</button>
  </div>
  <div class="dg-kichel-panel__body" data-kichel-body>
    <div class="dg-kichel-chips">
      <button type="button" class="dg-kichel-chip" data-kichel-chip="Wo trage ich die USt-ID ein?">USt-ID</button>
      <button type="button" class="dg-kichel-chip" data-kichel-chip="Skonto und Mahnung">Skonto</button>
      <button type="button" class="dg-kichel-chip" data-kichel-chip="Interne Kontakt-Bemerkung">Kontakt-Notiz</button>
      <button type="button" class="dg-kichel-chip" data-kichel-chip="Wo finde ich Pflichtseiten?">Pflichtseiten</button>
      <button type="button" class="dg-kichel-chip" data-kichel-chip="Wo finde ich Belege?">Belege</button>
    </div>
    <div class="dg-kichel-messages" data-kichel-messages>
      <div class="dg-kichel-msg dg-kichel-msg--bot">Hallo! Ich helfe dir, im CRM schnell den richtigen Weg zu finden — stell einfach deine Frage oder wähle ein Stichwort.</div>
    </div>
  </div>
  <form class="dg-kichel-form" data-kichel-form>
    <input type="text" data-kichel-input placeholder="Frage stellen …" autocomplete="off" maxlength="500" aria-label="Frage an Kichel">
    <button type="submit">Senden</button>
  </form>
</div>
<script>
  window.dgKichel = {
    apiUrl: '/api/kichel',
    csrf: <?= json_encode(Csrf::token(), JSON_THROW_ON_ERROR) ?>,
    chatVersion: '4'
  };
</script>
<script src="<?= View::escape(Asset::url('/assets/js/kichel.js')) ?>" defer></script>
