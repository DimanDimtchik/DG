<?php
/** @var string $disabledReason */
$disabledReason = $disabledReason ?? 'Die Online-Terminbuchung ist derzeit nicht verfügbar.';
$pageTitle = 'Terminbuchung – ' . CompanySettings::displayName();
$companyName = CompanySettings::displayName();
?>
<!DOCTYPE html>
<html lang="de">
<head>
<?php View::render('partials/head', compact('pageTitle')); ?>
<style><?= CalendarFrontendTheme::inlineCss() ?></style>
</head>
<body class="tk-book-page tk-book-page--disabled">
  <div class="tk-book">
    <header class="tk-book__header">
      <?php if (AppearanceSettings::logoUrl() !== '') : ?>
        <img class="tk-book__logo <?= View::escape(AppearanceSettings::logoShapeClass()) ?>" src="<?= View::escape(AppearanceSettings::logoUrl()) ?>" alt="<?= View::escape(AppearanceSettings::logoAlt()) ?>">
      <?php endif; ?>
      <div>
        <h1 class="tk-book__title"><?= View::escape($companyName !== '' ? $companyName : (string) App::config('crm_name')) ?></h1>
        <p class="tk-book__subtitle">Online-Terminbuchung</p>
      </div>
    </header>
    <main class="tk-book__card tk-book__card--center">
      <p class="tk-book__lead"><?= View::escape($disabledReason) ?></p>
      <?php if ($companyName !== '' && CompanySettings::config()['phone'] !== '') : ?>
        <p class="tk-book__muted">Telefon: <?= View::escape(CompanySettings::config()['phone']) ?></p>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
