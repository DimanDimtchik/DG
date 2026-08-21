<?php
/** @var list<string> $errors */
/** @var string $email */
$errors = $errors ?? [];
$email = $email ?? '';
?>
<section class="shop-section shop-section--tight">
  <h1>SaaS-Konto</h1>
  <p class="shop-lead">Hier sehen Sie nur Ihren eigenen CRM-Vertrag bei Ganz Soft – Domain, Lizenzstatus und Sperrhinweise. Das ist nicht die Kundenverwaltung in Ihrem CRM.</p>

  <?php if ($errors !== []): ?>
    <div class="shop-alert" role="alert">
      <ul>
        <?php foreach ($errors as $e): ?>
          <li><?= ShopView::escape($e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form class="shop-form" method="post" action="/konto/login" style="max-width:420px;">
    <label>
      <span>E-Mail <span class="shop-req">*</span></span>
      <input type="email" name="email" required value="<?= ShopView::escape($email) ?>" autocomplete="username">
    </label>
    <label>
      <span>Passwort <span class="shop-req">*</span></span>
      <input type="password" name="password" required autocomplete="current-password">
    </label>
    <div class="shop-form__actions">
      <button type="submit" class="shop-nav__cta" style="border:0;cursor:pointer;">Anmelden</button>
    </div>
  </form>
  <p class="shop-help" style="margin-top:1rem;">Zugangsdaten erhalten Sie von Ganz Soft. <a href="/konto/passwort-vergessen">Passwort vergessen?</a></p>
</section>
