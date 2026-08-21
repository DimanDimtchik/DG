<?php
/** @var list<string> $errors */
/** @var string $token */
/** @var string|null $success */
$errors = $errors ?? [];
$token = $token ?? '';
$success = $success ?? null;
$passwordHint = 'Mindestens 30 Zeichen, mit Groß- und Kleinbuchstaben, Ziffer und Sonderzeichen. Unzulässig: Leerzeichen sowie " \' \\ < > `';
?>
<section class="shop-section shop-section--tight">
  <h1>Neues Passwort</h1>
  <p class="shop-lead">Legen Sie ein neues Passwort für Ihr SaaS-Konto fest.</p>

  <?php if ($success !== null): ?>
    <div class="shop-alert shop-alert--ok" role="status"><p><?= ShopView::escape($success) ?></p></div>
    <p><a class="shop-nav__cta" href="/konto/login">Jetzt anmelden</a></p>
  <?php else: ?>

  <?php if ($errors !== []): ?>
    <div class="shop-alert" role="alert">
      <ul>
        <?php foreach ($errors as $e): ?>
          <li><?= ShopView::escape($e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($token === ''): ?>
    <p>Der Link ist ungültig. Bitte <a href="/konto/passwort-vergessen">fordern Sie einen neuen Link an</a>.</p>
  <?php else: ?>
  <form class="shop-form" method="post" action="/konto/passwort-neu" style="max-width:420px;">
    <input type="hidden" name="token" value="<?= ShopView::escape($token) ?>">
    <p class="shop-help"><?= ShopView::escape($passwordHint) ?></p>
    <label>
      <span>Neues Passwort <span class="shop-req">*</span></span>
      <input type="password" name="password" required autocomplete="new-password" minlength="30">
    </label>
    <label>
      <span>Passwort wiederholen <span class="shop-req">*</span></span>
      <input type="password" name="password_confirm" required autocomplete="new-password" minlength="30">
    </label>
    <div class="shop-form__actions">
      <button type="submit" class="shop-nav__cta" style="border:0;cursor:pointer;">Passwort speichern</button>
      <a href="/konto/login">Abbrechen</a>
    </div>
  </form>
  <?php endif; ?>
  <?php endif; ?>
</section>
