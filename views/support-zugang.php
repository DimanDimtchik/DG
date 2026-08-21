<?php
/** @var string|null $error */
/** @var array<string, mixed>|null $grant */
$pageTitle = 'Support-Zugang – ' . App::config('crm_name');
$error = $error ?? null;
?>
<!DOCTYPE html>
<html lang="de">
<head>
<?php View::render('partials/head', compact('pageTitle')); ?>
</head>
<body class="dg-login-page">
  <div class="dg-login">
    <div class="dg-login__card">
      <h1>Support-Zugang</h1>
      <p class="dg-muted">Ganz Soft Support – zeitlich begrenzte Freigabe</p>
      <?php if ($error) : ?>
        <div class="dg-login__error" role="alert"><?= View::escape($error) ?></div>
        <p><a href="/login">Zur normalen Anmeldung</a></p>
      <?php else : ?>
        <p>Zugang wird eingerichtet…</p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
