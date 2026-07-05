<?php

/** @var string|null $error */

/** @var string|null $success */

/** @var string $identifier */

$pageTitle = 'Passwort vergessen – ' . App::config('crm_name');

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

          <h1>Passwort vergessen</h1>

          <span><?= View::escape((string) App::config('crm_name')) ?></span>

        </div>

      </div>



      <?php if (!empty($error)) : ?>

        <div class="dg-login__error" role="alert"><?= View::escape($error) ?></div>

      <?php endif; ?>



      <?php if (!empty($success)) : ?>

        <div class="dg-login__success" role="status"><?= View::escape($success) ?></div>

      <?php endif; ?>



      <?php if (empty($success)) : ?>

        <p class="dg-login__hint">Geben Sie die E-Mail-Adresse oder den Benutzernamen Ihres Kontos ein. Sie erhalten einen Link zum Festlegen eines neuen Passworts.</p>



        <form method="post" action="/passwort-vergessen" class="dg-login__form">

          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

          <label>

            <span>E-Mail oder Benutzername</span>

            <input type="text" name="identifier" value="<?= View::escape($identifier) ?>" autocomplete="username" required autofocus>

          </label>

          <button type="submit" class="dg-button dg-button--primary">Link senden</button>

        </form>

      <?php endif; ?>



      <p class="dg-login__footer"><a href="/login">Zurück zur Anmeldung</a></p>

    </div>

  </div>

</body>

</html>

