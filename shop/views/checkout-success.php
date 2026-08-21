<?php
/** @var string|null $message */
/** @var string|null $sessionId */
/** @var bool $ok */
$ok = !empty($ok);
?>
<section class="shop-section shop-section--tight">
  <h1><?= $ok ? 'Zahlung erfolgreich' : 'Bestellung' ?></h1>
  <?php if ($ok) : ?>
    <div class="shop-alert shop-alert--ok">
      <p><strong>Vielen Dank!</strong> Ihre Zahlung ist eingegangen.</p>
      <p><?= ShopView::escape((string) ($message ?? 'Wir richten Ihr CRM ein und melden uns per E-Mail.')) ?></p>
      <?php if (!empty($sessionId)) : ?>
        <p class="shop-vat">Referenz: <code><?= ShopView::escape((string) $sessionId) ?></code></p>
      <?php endif; ?>
    </div>
    <p><a class="shop-btn shop-btn--primary" href="/konto/login">Zum Kundenkonto</a>
       <a class="shop-btn shop-btn--ghost" href="/">Zur Startseite</a></p>
  <?php else : ?>
    <div class="shop-alert" role="alert">
      <p><?= ShopView::escape((string) ($message ?? 'Die Zahlung konnte nicht bestätigt werden.')) ?></p>
    </div>
    <p><a class="shop-btn shop-btn--primary" href="/preise">Erneut versuchen</a></p>
  <?php endif; ?>
</section>
