<?php
/** @var string $title */
/** @var bool $maintEnabled */
/** @var array{enabled: bool, headline: string, message: string, email: string} $maintenance */
/** @var string|null $flashOk */
/** @var string|null $flashErr */
/** @var bool $adminConfigured */
?>
<section class="shop-admin">
  <header class="shop-admin__head">
    <div>
      <h1 class="shop-admin__title">Shop — Wartungsmodus</h1>
      <p class="shop-admin__lead">Besucher sehen bei aktivem Wartungsmodus nur die Aufbau-Seite. Diese Verwaltung bleibt erreichbar.</p>
    </div>
    <?php if ($maintEnabled) : ?>
      <span class="shop-badge shop-badge--warn">Aktiv</span>
    <?php else : ?>
      <span class="shop-badge shop-badge--muted">Aus</span>
    <?php endif; ?>
  </header>

  <?php if ($flashOk) : ?>
    <p class="shop-flash shop-flash--ok"><?= ShopView::escape($flashOk) ?></p>
  <?php endif; ?>
  <?php if ($flashErr) : ?>
    <p class="shop-flash shop-flash--err"><?= ShopView::escape($flashErr) ?></p>
  <?php endif; ?>

  <?php if (!$adminConfigured) : ?>
    <div class="shop-panel shop-panel--warn">
      <p><strong>Admin-Passwort fehlt.</strong> Legen Sie auf dem Server <code>config/admin.local.php</code> an (Vorlage: <code>admin.local.php.example</code>) mit <code>password_hash</code>.</p>
    </div>
  <?php endif; ?>

  <form class="shop-form shop-panel" method="post" action="/admin/wartung">
    <label class="shop-field shop-field--check">
      <span>
        <input type="checkbox" name="enabled" value="1"<?= $maintEnabled ? ' checked' : '' ?><?= !$adminConfigured ? ' disabled' : '' ?>>
        Wartungsmodus einschalten (öffentlicher Shop)
      </span>
    </label>

    <label class="shop-field">
      <span>Überschrift</span>
      <input name="headline" maxlength="160" value="<?= ShopView::escape((string) ($maintenance['headline'] ?? '')) ?>"<?= !$adminConfigured ? ' disabled' : '' ?>>
    </label>

    <label class="shop-field">
      <span>Text</span>
      <textarea name="message" rows="3" maxlength="500"<?= !$adminConfigured ? ' disabled' : '' ?>><?= ShopView::escape((string) ($maintenance['message'] ?? '')) ?></textarea>
    </label>

    <label class="shop-field">
      <span>E-Mail für Fragen</span>
      <input type="email" name="email" value="<?= ShopView::escape((string) ($maintenance['email'] ?? '')) ?>" placeholder="info@ganz-soft.de"<?= !$adminConfigured ? ' disabled' : '' ?>>
    </label>

    <div class="shop-form__actions">
      <button type="submit" class="shop-button shop-button--primary"<?= !$adminConfigured ? ' disabled' : '' ?>>Wartungsmodus speichern</button>
      <a class="shop-button" href="/" target="_blank" rel="noopener">Öffentliche Ansicht prüfen</a>
      <a class="shop-button shop-button--ghost" href="/admin/logout">Abmelden</a>
    </div>
  </form>
</section>
