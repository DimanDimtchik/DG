<?php
/** @var User|null $user */
/** @var array{type: string, message: string}|null $flash */
$superkihelUser = $user ?? AuthService::user();
$superkihelIsAdmin = $superkihelUser instanceof User && RoleResolver::isAdmin($superkihelUser);
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header<?= $superkihelIsAdmin ? ' dg-page-header--toolbar' : '' ?>">
    <div>
      <h1 class="dg-page-title">Super-Kichel</h1>
      <p class="dg-lead">Längere Hilfe-Sitzungen mit dem CRM-Helfer — dieselbe Hilfe wie im kleinen Kichel-Fenster.</p>
    </div>
    <?php if ($superkihelIsAdmin) : ?>
      <div class="dg-toolbar">
        <a class="dg-button" href="/app?page=kichel-protokoll">Hilfe-Protokoll</a>
      </div>
    <?php endif; ?>
  </header>

  <div class="dg-panel">
    <div class="dg-kichel-messages" data-superkihel-messages>
      <div class="dg-kichel-msg dg-kichel-msg--bot">Hallo! Schreiben Sie Ihre Frage unten und senden Sie ab.</div>
    </div>

    <form class="dg-form" data-superkihel-form method="post" action="" aria-busy="false">
      <div class="dg-field">
        <label for="superkihel-query">Ihre Frage</label>
        <textarea id="superkihel-query" name="query" rows="3" maxlength="500" placeholder="Frage stellen …" autocomplete="off"></textarea>
      </div>
      <div class="dg-form-actions">
        <button type="submit" class="dg-button dg-button--primary">Senden</button>
      </div>
    </form>
  </div>
</div>
<script src="<?= View::escape(Asset::url('/assets/js/superkihel.js')) ?>" defer></script>
