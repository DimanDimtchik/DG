<?php
/**
 * Stellen (Recruiting Phase A — Menü/Platzhalter).
 *
 * @var array{type: string, message: string}|null $flash
 */
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Stellen</h1>
      <p class="dg-lead">Offene Stellen für die Bewerbungsseite verwalten.</p>
    </div>
    <div class="dg-page-header__actions">
      <a class="dg-button" href="/app?page=hr-bewerber">Zu Bewerber</a>
    </div>
  </header>

  <section class="dg-panel">
    <p class="dg-muted">Hier erscheinen demnächst Stellenanzeigen (Titel, Text, Status). Die öffentliche Bewerbungsseite folgt im nächsten Schritt.</p>
  </section>
</div>
