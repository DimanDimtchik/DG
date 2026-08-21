<?php

/** @var string|null $error */

/** @var string $token */

/** @var bool $tokenValid */

$pageTitle = 'Neues Passwort – ' . App::config('crm_name');

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

          <h1>Neues Passwort</h1>

          <span><?= View::escape((string) App::config('crm_name')) ?></span>

        </div>

      </div>



      <?php if (!empty($error)) : ?>

        <div class="dg-login__error" role="alert"><?= View::escape($error) ?></div>

      <?php endif; ?>



      <?php if (!$tokenValid) : ?>

        <p class="dg-login__hint">Der Link ist ungültig oder abgelaufen.</p>

        <p class="dg-login__footer"><a href="/passwort-vergessen">Neuen Link anfordern</a> · <a href="/login">Zur Anmeldung</a></p>

      <?php else : ?>

        <form method="post" action="/passwort-zuruecksetzen" class="dg-login__form">

          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

          <input type="hidden" name="token" value="<?= View::escape($token) ?>">

          <label>

            <span>Neues Passwort</span>

            <input type="password" name="password" autocomplete="new-password" required minlength="<?= (int) PasswordPolicy::MIN_LENGTH ?>" autofocus>

            <small class="dg-login__hint"><?= View::escape(PasswordPolicy::hint()) ?></small>

          </label>

          <label>

            <span>Passwort wiederholen</span>

            <input type="password" name="password_confirm" autocomplete="new-password" required minlength="<?= (int) PasswordPolicy::MIN_LENGTH ?>">

          </label>

          <button type="submit" class="dg-button dg-button--primary">Passwort speichern</button>

        </form>

        <p class="dg-login__footer"><a href="/login">Zur Anmeldung</a></p>

      <?php endif; ?>

    </div>

  </div>

</body>

</html>

