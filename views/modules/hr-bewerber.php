<?php
/**
 * Bewerber (Recruiting Phase A — Menü/Platzhalter).
 *
 * @var array{type: string, message: string}|null $flash
 */
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Bewerber</h1>
      <p class="dg-lead">Bewerbungen und Bewerberakte im CRM führen.</p>
    </div>
    <div class="dg-page-header__actions">
      <a class="dg-button" href="/app?page=hr-stellen">Zu Stellen</a>
    </div>
  </header>

  <section class="dg-panel">
    <p class="dg-muted">Hier erscheinen demnächst eingegangene Bewerbungen mit Status und Unterlagen. Speichern und Löschfristen folgen mit dem Datenmodell.</p>
  </section>
</div>
