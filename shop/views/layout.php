<?php
/** @var string $title */
/** @var string $templateFile */
$marketingUrl = $marketingUrl ?? (string) ShopApp::config('marketing_url');
$contactEmail = $contactEmail ?? (string) ShopApp::config('contact_email');
$appName = (string) ShopApp::config('name');
$logo = (string) ShopApp::config('logo', '/assets/img/logo.png');
$favicon = (string) ShopApp::config('favicon', '/assets/img/favicon.png');
$logoShape = ShopApp::logoShape();
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= ShopView::escape($title) ?></title>
  <meta name="description" content="DG CRM – Terminkalender, Buchhaltung, Kontakte und Website. Domain, E-Mail und Hosting inklusive.">
  <link rel="icon" href="<?= ShopView::escape(ShopView::asset(ltrim($favicon, '/'))) ?>">
  <link rel="stylesheet" href="<?= ShopView::escape(ShopView::asset('assets/css/shop.css')) ?>">
  <style><?= ShopCookieConsent::bannerCss() ?></style>
</head>
<body>
  <header class="shop-top">
    <div class="shop-top__inner">
      <a class="shop-brand shop-brand--<?= ShopView::escape($logoShape) ?>" href="/">
        <img src="<?= ShopView::escape(ShopView::asset(ltrim($logo, '/'))) ?>" alt="<?= ShopView::escape($appName) ?>">
        <span class="shop-brand__text">
          <strong><?= ShopView::escape($appName) ?></strong>
          <small><?= ShopView::escape((string) ShopApp::config('tagline')) ?></small>
        </span>
      </a>
      <nav class="shop-nav" aria-label="Hauptnavigation">
        <a href="/preise">Preise</a>
        <a href="/konto"><?= ShopAccountSession::token() ? 'Mein Konto' : 'Konto' ?></a>
        <a href="<?= ShopView::escape($marketingUrl) ?>">ganz-soft.de</a>
        <a class="shop-nav__cta" href="/preise">Jetzt starten</a>
      </nav>
    </div>
  </header>

  <main>
    <?php require $templateFile; ?>
  </main>

  <footer class="shop-foot">
    <div class="shop-foot__inner">
      <div class="shop-foot__brand shop-foot__brand--<?= ShopView::escape($logoShape) ?>">
        <img src="<?= ShopView::escape(ShopView::asset(ltrim($logo, '/'))) ?>" alt="">
        <div>
          <p><strong><?= ShopView::escape($appName) ?></strong></p>
          <p><?= ShopView::escape((string) ShopApp::config('tagline')) ?></p>
        </div>
      </div>
      <p>
        <a href="/impressum">Impressum</a>
        · <a href="/datenschutz">Datenschutz</a>
        · <a href="/agb">AGB</a>
        · <a href="/widerruf">Widerruf</a>
        · <a href="<?= ShopView::escape($marketingUrl) ?>/preise">Preisliste</a>
        · <a href="mailto:<?= ShopView::escape($contactEmail) ?>"><?= ShopView::escape($contactEmail) ?></a>
      </p>
      <p class="shop-foot__note">Preise gemäß <a href="https://ganz-soft.de/preise">ganz-soft.de/preise</a>. Zahlung über Stripe, sobald freigeschaltet.</p>
    </div>
  </footer>
  <?= ShopCookieConsent::bannerHtml() ?>
  <script><?= ShopCookieConsent::bannerJs() ?></script>
</body>
</html>
