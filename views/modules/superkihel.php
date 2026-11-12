<?php
/** @var array{type: string, message: string}|null $flash */
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header">
    <div>
      <h1 class="dg-page-title">Super-Kichel</h1>
      <p class="dg-lead">Längere Hilfe-Sitzungen — Chat folgt in einem nächsten Schritt.</p>
    </div>
  </header>

  <div class="dg-panel">
    <p>Hier erscheint bald der Verlauf und die Eingabe.</p>
  </div>
</div>
