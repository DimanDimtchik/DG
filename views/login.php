<?php
/** @var string|null $error */
/** @var array{type: string, message: string}|null $flash */
$pageTitle = 'Anmelden &ndash; ' . App::config('crm_name');
?>
<!DOCTYPE html>
<html lang="de">
<head>
<?php View::render('partials/head', compact('pageTitle')); ?>
  <meta name="referrer" content="no-referrer">
</head>
<body class="dg-login-page">
  <div class="dg-login">
    <div class="dg-login__card">
      <div class="dg-login__brand">
        <img class="dg-login__logo <?= View::escape(AppearanceSettings::logoShapeClass()) ?>" src="<?= View::escape(AppearanceSettings::logoUrl()) ?>" alt="<?= View::escape(AppearanceSettings::logoAlt()) ?>">
        <div>
          <h1><?= View::escape((string) App::config('crm_name')) ?></h1>
          <span>CRM-Anmeldung</span>
        </div>
      </div>

      <?php if (!empty($error)) : ?>
        <div class="dg-login__error" role="alert"><?= View::escape($error) ?></div>
      <?php endif; ?>

      <?php if (!empty($flash['message'])) : ?>
        <?php
          $flashType = (string) ($flash['type'] ?? 'info');
          $flashClass = $flashType === 'error' ? 'dg-login__error' : 'dg-login__success';
          $flashRole = $flashType === 'error' ? 'alert' : 'status';
        ?>
        <div class="<?= View::escape($flashClass) ?>" role="<?= View::escape($flashRole) ?>"><?= View::escape($flash['message']) ?></div>
      <?php endif; ?>

      <form method="post" action="/login" class="dg-login__form">
        <label>
          <span class="dg-login__label">Benutzername oder E-Mail</span>
          <input type="text" name="username" autocomplete="username email" required autofocus>
        </label>
        <label>
          <span class="dg-login__label">Passwort</span>
          <div class="dg-password-field">
            <input id="dg-login-password" type="password" name="password" autocomplete="current-password" required>
            <button
              type="button"
              class="dg-password-field__toggle"
              data-password-toggle
              aria-controls="dg-login-password"
              aria-pressed="false"
              aria-label="Passwort anzeigen"
              title="Passwort anzeigen"
            >
              <svg class="dg-password-field__icon dg-password-field__icon--show" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M12 5c-5.05 0-9.09 3.11-10.7 7.5C2.91 16.89 6.95 20 12 20s9.09-3.11 10.7-7.5C21.09 8.11 17.05 5 12 5zm0 12.5A5 5 0 1 1 12 7.5a5 5 0 0 1 0 10zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/>
              </svg>
              <svg class="dg-password-field__icon dg-password-field__icon--hide" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false" hidden>
                <path fill="currentColor" d="M3.28 2.22 2.22 3.28l3.1 3.1C3.58 8.04 2.5 10.1 2 12.5 3.61 16.89 7.65 20 12.7 20c1.82 0 3.52-.4 5.05-1.1l3 3 1.06-1.06L3.28 2.22zM12.7 18c-3.72 0-6.84-2.2-8.2-5.5.5-1.2 1.28-2.3 2.26-3.2l1.62 1.62A4.98 4.98 0 0 0 7.7 13a5 5 0 0 0 6.76 4.66l1.5 1.5c-1 .5-2.1.84-3.26.84zm8.2-5.5c-.35.84-.84 1.62-1.42 2.3l-1.5-1.5c.3-.56.47-1.17.47-1.8a5 5 0 0 0-6.4-4.8L10.3 5.3A8.9 8.9 0 0 1 12.7 5c5.05 0 9.09 3.11 10.7 7.5-.14.36-.32.72-.5 1z"/>
              </svg>
            </button>
          </div>
        </label>
        <button type="submit" class="dg-button dg-button--primary">Anmelden</button>
      </form>
      <p class="dg-login__footer">
        <a href="/passwort-vergessen">Passwort vergessen?</a>
        &middot;
        <a href="/register">Als Kunde registrieren</a>
      </p>
    </div>
  </div>
  <script src="<?= View::escape(Asset::url('/assets/js/password-reveal.js')) ?>" defer></script>
</body>
</html>
