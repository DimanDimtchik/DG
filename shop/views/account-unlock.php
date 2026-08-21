<?php
/** @var array<string, mixed> $account */
/** @var list<string> $errors */
/** @var string|null $resultMessage */
/** @var bool $autoRejected */
$account = $account ?? [];
$errors = $errors ?? [];
$autoRejected = !empty($autoRejected) || !empty($account['unlock_auto_rejected']);
$resultMessage = $resultMessage ?? null;
?>
<section class="shop-section shop-section--tight">
  <h1>Entsperrung anfragen</h1>
  <p class="shop-lead"><?= ShopView::escape((string) ($account['company_name'] ?? '')) ?> · <?= ShopView::escape((string) ($account['domain'] ?? '')) ?></p>

  <?php if (!empty($account['block_message'])): ?>
    <div class="shop-alert" role="status">
      <p><?= ShopView::escape((string) $account['block_message']) ?></p>
    </div>
  <?php endif; ?>

  <?php if ($resultMessage !== null): ?>
    <div class="shop-alert <?= $autoRejected ? '' : 'shop-alert--ok' ?>" role="status">
      <p><?= ShopView::escape($resultMessage) ?></p>
    </div>
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

  <?php if ($autoRejected): ?>
    <p>Für diesen Sperrgrund wird keine Support-Mail versendet. Die Entsperrung erfolgt nach Klärung des genannten Grunds (z. B. Zahlungseingang).</p>
    <p><a href="/konto">Zurück zum Konto</a></p>
  <?php else: ?>
    <form class="shop-form" method="post" action="/konto/entsperren" style="max-width:520px;">
      <label>
        <span>Ihre Nachricht <span class="shop-req">*</span></span>
        <textarea name="message" rows="6" required minlength="10" placeholder="Kurz schildern, warum die Sperre unzutreffend ist oder was wir prüfen sollen."></textarea>
      </label>
      <div class="shop-form__actions">
        <button type="submit" class="shop-nav__cta" style="border:0;cursor:pointer;">Anfrage senden</button>
        <a href="/konto">Abbrechen</a>
      </div>
    </form>
  <?php endif; ?>
</section>
