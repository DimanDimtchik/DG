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

  <div class="dg-stack">
    <section class="dg-panel" aria-labelledby="superkihel-program-title">
      <div class="dg-panel__toolbar">
        <h2 id="superkihel-program-title" class="dg-section-title">Programmieren</h2>
        <a
          class="dg-button dg-button--primary"
          href="http://127.0.0.1:3000/"
          target="_blank"
          rel="noopener noreferrer"
        >Agent öffnen</a>
      </div>
      <p class="dg-lead">Hier starten Sie den Programmier-Agenten auf Ihrem PC (wie eine lokale Entwicklungshilfe). Dafür muss der Tunnel zum Server laufen.</p>
      <p class="dg-field-hint">
        Am PC in PowerShell (Fenster offen lassen):
        <code>ssh -o IdentitiesOnly=yes -i $env:USERPROFILE\.ssh\id_ed25519_hetzner_agent -N -L 3000:127.0.0.1:3000 deploy@2.29.242.28</code>
        Danach „Agent öffnen“. In den Agent-Einstellungen: Basis-URL <code>http://127.0.0.1:11434/v1</code>, Modell z. B. <code>openai/qwen2.5-coder:14b</code>, API-Schlüssel beliebig.
        Ohne Tunnel bleibt die Seite unerreichbar — das ist Absicht.
      </p>
    </section>

    <section class="dg-panel" aria-labelledby="superkihel-help-title">
      <h2 id="superkihel-help-title" class="dg-section-title">Hilfe im CRM</h2>
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
    </section>
  </div>
</div>
<script src="<?= View::escape(Asset::url('/assets/js/superkihel.js')) ?>" defer></script>
