<?php
/** @var string $baseUrl */
/** @var string $connectScheme */
/** @var string $apkMitarbeiter */
/** @var string $apkKalender */

$pageTitle = 'App verbinden – ' . App::config('crm_name');
$baseUrl = (string) ($baseUrl ?? '');
$connectScheme = (string) ($connectScheme ?? '');
$apkMitarbeiter = (string) ($apkMitarbeiter ?? '');
$apkKalender = (string) ($apkKalender ?? '');
?>
<!DOCTYPE html>
<html lang="de">
<head>
<?php View::render('partials/head', compact('pageTitle')); ?>
</head>
<body class="dg-login-page">
  <div class="dg-login">
    <div class="dg-login__card">
      <div class="dg-login__brand">
        <img class="dg-login__logo <?= View::escape(AppearanceSettings::logoShapeClass()) ?>" src="<?= View::escape(AppearanceSettings::logoUrl()) ?>" alt="<?= View::escape(AppearanceSettings::logoAlt()) ?>">
        <div>
          <h1>Mitarbeiter-App</h1>
          <span><?= View::escape((string) App::config('crm_name')) ?></span>
        </div>
      </div>
      <p class="dg-login__hint">
        1. App installieren (Android). 2. Mit dieser Firma verbinden — die CRM-Adresse
        <strong><?= View::escape($baseUrl) ?></strong> wird automatisch übernommen.
      </p>
      <p>
        <a class="dg-button dg-button--primary" href="<?= View::escape($apkMitarbeiter) ?>">Mitarbeiter-App (Android)</a>
      </p>
      <p>
        <a class="dg-button" href="<?= View::escape($apkKalender) ?>">Termin-App (Android)</a>
      </p>
      <p class="dg-login__hint">iOS-Download folgt (TestFlight).</p>
      <p style="margin-top:1.5rem">
        <a class="dg-button dg-button--primary" href="<?= View::escape($connectScheme) ?>">App öffnen / verbinden</a>
      </p>
      <p class="dg-login__hint" style="font-size:.85rem">
        Falls die App nicht öffnet: App starten und unter Setup diese Adresse eintragen:<br>
        <code><?= View::escape($baseUrl) ?></code>
      </p>
    </div>
  </div>
</body>
</html>
