<?php
/** @var string|null $error */
/** @var array{type: string, message: string}|null $flash */
$pageTitle = 'Anmelden â€“ ' . App::config('crm_name');
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
        <img src="<?= View::escape(AppearanceSettings::logoUrl()) ?>" alt="<?= View::escape(AppearanceSettings::logoAlt()) ?>" width="44" height="44">
        <div>
          <h1><?= View::escape((string) App::config('crm_name')) ?></h1>
          <span>CRM-Anmeldung</span>
        </div>
      </div>

      <?php if (!empty($error)) : ?>
        <div class="dg-login__error" role="alert"><?= View::escape($error) ?></div>
      <?php endif; ?>

      <?php if (!empty($flash['message'])) : ?>
        <div class="dg-login__success" role="status"><?= View::escape($flash['message']) ?></div>
      <?php endif; ?>

      <form method="post" action="/login" class="dg-login__form">
        <label>
          <span>Benutzername oder E-Mail</span>
          <input type="text" name="username" autocomplete="username email" required autofocus>
        </label>
        <label>
          <span>Passwort</span>
          <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button type="submit" class="dg-button dg-button--primary">Anmelden</button>
      </form>
      <p class="dg-login__footer">
        <a href="/passwort-vergessen">Passwort vergessen?</a>
        Â·
        <a href="/register">Als Kunde registrieren</a>
      </p>
    </div>
  </div>
</body>
</html>

