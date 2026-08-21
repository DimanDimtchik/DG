<?php
/** @var array<string, mixed> $account */
/** @var string|null $flashOk */
/** @var string|null $flashErr */
$account = $account ?? [];
$blocked = !empty($account['blocked']);
?>
<section class="shop-section shop-section--tight">
  <h1>Mein SaaS-Konto</h1>
  <p class="shop-lead"><?= ShopView::escape((string) ($account['company_name'] ?? '')) ?> · <?= ShopView::escape((string) ($account['domain'] ?? '')) ?></p>

  <?php if (!empty($flashOk)): ?>
    <div class="shop-alert shop-alert--ok" role="status"><p><?= ShopView::escape($flashOk) ?></p></div>
  <?php endif; ?>
  <?php if (!empty($flashErr)): ?>
    <div class="shop-alert" role="alert"><p><?= ShopView::escape($flashErr) ?></p></div>
  <?php endif; ?>

  <?php if ($blocked): ?>
    <div class="shop-alert" role="alert">
      <p><strong>Account gesperrt</strong><?php if (!empty($account['block_reason_label'])): ?> — <?= ShopView::escape((string) $account['block_reason_label']) ?><?php endif; ?></p>
      <p><?= ShopView::escape((string) ($account['block_message'] ?? '')) ?></p>
    </div>
  <?php else: ?>
    <div class="shop-alert shop-alert--ok" role="status">
      <p>Status: <strong><?= ShopView::escape((string) ($account['status_label'] ?? $account['status'] ?? '')) ?></strong></p>
    </div>
  <?php endif; ?>

  <div class="shop-panel" style="margin:1.5rem 0;padding:1.25rem;border:1px solid var(--shop-line);border-radius:8px;background:var(--shop-bg-soft);">
    <p><strong>Tarif:</strong> <?= ShopView::escape((string) ($account['tariff_label'] ?? '–')) ?>
      (<?= ShopView::escape((string) ($account['billing_cycle'] ?? '')) ?>)</p>
    <p><strong>Lizenz:</strong> <?= !empty($account['has_license']) ? ShopView::escape((string) ($account['license_masked'] ?? '')) : 'noch nicht zugewiesen' ?></p>
    <p><strong>Kontakt:</strong> <?= ShopView::escape((string) ($account['contact_email'] ?? '')) ?></p>
  </div>

  <p class="shop-form__actions" style="display:flex;gap:1rem;flex-wrap:wrap;">
    <?php if ($blocked): ?>
      <a class="shop-nav__cta" href="/konto/entsperren">Entsperrung anfragen</a>
    <?php endif; ?>
    <a href="/konto/logout">Abmelden</a>
  </p>
</section>
