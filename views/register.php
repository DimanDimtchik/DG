<?php
/** @var string|null $error */
/** @var array<string, string>|null $form */
$pageTitle = 'Registrieren – ' . App::config('crm_name');
$values = $form ?? ['username' => '', 'email' => '', 'display_name' => ''];
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
          <h1>Kundenkonto</h1>
          <span>Registrierung für <?= View::escape((string) App::config('crm_name')) ?></span>
        </div>
      </div>

      <?php if (!empty($error)) : ?>
        <div class="dg-login__error" role="alert"><?= View::escape($error) ?></div>
      <?php endif; ?>

      <form method="post" action="/register" class="dg-login__form">
        <label>
          <span>Benutzername</span>
          <input type="text" name="username" value="<?= View::escape($values['username']) ?>" autocomplete="username" required autofocus>
        </label>
        <label>
          <span>Anzeigename</span>
          <input type="text" name="display_name" value="<?= View::escape($values['display_name']) ?>" required>
        </label>
        <label>
          <span>E-Mail</span>
          <input type="email" name="email" value="<?= View::escape($values['email']) ?>" autocomplete="email" required>
        </label>
        <label>
          <span>Passwort</span>
          <input type="password" name="password" autocomplete="new-password" required minlength="8">
        </label>
        <label>
          <span>Passwort wiederholen</span>
          <input type="password" name="password_confirm" autocomplete="new-password" required minlength="8">
        </label>
        <button type="submit" class="dg-button dg-button--primary">Registrieren</button>
      </form>

      <p class="dg-login__footer"><a href="/login">Bereits ein Konto? Anmelden</a></p>
    </div>
  </div>
</body>
</html>
