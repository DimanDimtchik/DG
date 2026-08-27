<?php
/** @var string $title */
/** @var string|null $error */
/** @var string $returnTo */
/** @var bool $adminConfigured */
?>
<section class="shop-admin shop-admin--narrow">
  <h1 class="shop-admin__title">Shop-Verwaltung</h1>
  <p class="shop-admin__lead">Anmelden, um den Wartungsmodus zu steuern.</p>

  <?php if (!$adminConfigured) : ?>
    <div class="shop-panel shop-panel--warn">
      <p>Es ist noch kein Admin-Passwort hinterlegt. Bitte <code>config/admin.local.php</code> auf dem Server anlegen.</p>
    </div>
  <?php endif; ?>

  <?php if ($error) : ?>
    <p class="shop-flash shop-flash--err"><?= ShopView::escape($error) ?></p>
  <?php endif; ?>

  <form class="shop-form shop-panel" method="post" action="/admin/login">
    <input type="hidden" name="return" value="<?= ShopView::escape($returnTo) ?>">
    <label class="shop-field">
      <span>Passwort</span>
      <input type="password" name="password" autocomplete="current-password" required<?= !$adminConfigured ? ' disabled' : '' ?>>
    </label>
    <div class="shop-form__actions">
      <button type="submit" class="shop-button shop-button--primary"<?= !$adminConfigured ? ' disabled' : '' ?>>Anmelden</button>
    </div>
  </form>
</section>
