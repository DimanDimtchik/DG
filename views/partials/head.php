  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= View::escape($pageTitle) ?></title>
  <?php if (AppearanceSettings::hasFavicon()) : ?>
    <?php if (AppearanceSettings::faviconIsSvg()) : ?>
      <link rel="icon" href="/app/favicon" type="image/svg+xml">
    <?php else : ?>
      <link rel="icon" href="/app/favicon?size=32" type="image/png" sizes="32x32">
      <link rel="icon" href="/app/favicon?size=16" type="image/png" sizes="16x16">
      <link rel="apple-touch-icon" href="/app/favicon?size=48">
    <?php endif; ?>
  <?php endif; ?>
  <?php
    $googleFontsHref = AppearanceSettings::googleFontsHref();
    $uiFontFamily = AppearanceSettings::uiFontFamily();
  ?>
  <?php if ($googleFontsHref !== null) : ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="<?= View::escape($googleFontsHref) ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="<?= View::escape(Asset::url('/assets/css/dg.css')) ?>">
  <style>:root { --dg-font: <?= htmlspecialchars($uiFontFamily, ENT_QUOTES, 'UTF-8') ?>; <?= CrmFrontendTheme::rootDeclarations() ?> }</style>
