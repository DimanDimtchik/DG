<?php
/** @var string|null $error */
/** @var string $token */
/** @var bool $tokenValid */

$pageTitle = 'Postfach-Passwort – ' . App::config('crm_name');
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
          <h1>Postfach-Passwort</h1>
          <span><?= View::escape((string) App::config('crm_name')) ?></span>
        </div>
      </div>

      <?php if (!empty($error)) : ?>
        <div class="dg-login__error" role="alert"><?= View::escape($error) ?></div>
      <?php endif; ?>

      <?php if (!$tokenValid) : ?>
        <p class="dg-login__hint">Der Link ist ungültig oder abgelaufen. Bitte wenden Sie sich an Ihren Administrator.</p>
      <?php else : ?>
        <p class="dg-login__hint">Vergessen Sie selbst ein Passwort für Ihr geschäftliches Postfach (mindestens 8 Zeichen).</p>
        <form method="post" action="/postfach-passwort" class="dg-login__form">
          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
          <input type="hidden" name="token" value="<?= View::escape($token) ?>">
          <label>
            <span>Passwort</span>
            <input type="password" name="password" autocomplete="new-password" required minlength="8" autofocus>
          </label>
          <label>
            <span>Passwort wiederholen</span>
            <input type="password" name="password_confirm" autocomplete="new-password" required minlength="8">
          </label>
          <button type="submit" class="dg-button dg-button--primary">Speichern</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
