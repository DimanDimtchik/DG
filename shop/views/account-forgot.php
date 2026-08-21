<?php
/** @var list<string> $errors */
/** @var string $email */
/** @var string|null $success */
$errors = $errors ?? [];
$email = $email ?? '';
$success = $success ?? null;
?>
<section class="shop-section shop-section--tight">
  <h1>Passwort vergessen</h1>
  <p class="shop-lead">Geben Sie die E-Mail Ihres SaaS-Kontos ein. Wenn ein Konto existiert, senden wir Ihnen einen Link zum Zurücksetzen.</p>

  <?php if ($success !== null): ?>
    <div class="shop-alert shop-alert--ok" role="status"><p><?= ShopView::escape($success) ?></p></div>
  <?php endif; ?>

  <?php if ($errors !== []): ?>
    <div class="shop-alert" role="alert">
      <ul>
        <?php foreach ($errors as $e): ?>
          <li><?= ShopView::escape($e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($success === null): ?>
  <form class="shop-form" method="post" action="/konto/passwort-vergessen" style="max-width:420px;">
    <label>
      <span>E-Mail <span class="shop-req">*</span></span>
      <input type="email" name="email" required value="<?= ShopView::escape($email) ?>" autocomplete="username">
    </label>
    <div class="shop-form__actions">
      <button type="submit" class="shop-nav__cta" style="border:0;cursor:pointer;">Link anfordern</button>
      <a href="/konto/login">Zur Anmeldung</a>
    </div>
  </form>
  <?php else: ?>
    <p><a href="/konto/login">Zur Anmeldung</a></p>
  <?php endif; ?>
</section>
