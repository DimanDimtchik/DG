<?php
/**
 * @var list<array<string, mixed>> $giftVoucherList
 * @var array<string, mixed>|null $flyerGift
 * @var string $companyName
 * @var string $companyLogoUrl
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$gift = $flyerGift ?? null;
$list = $giftVoucherList ?? [];
$companyName = $companyName ?? 'Gutschein';
$logo = $companyLogoUrl ?? '';
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Gutschein-Flyer</h1>
      <p class="dg-lead">Schlicht und modern — zum Ausdrucken oder Verschenken.</p>
    </div>
    <div class="dg-toolbar">
      <a class="dg-button" href="/app?page=gutscheine">Gutscheine</a>
      <?php if (is_array($gift)) : ?>
        <button type="button" class="dg-button dg-button--primary" onclick="window.print()">Drucken</button>
      <?php endif; ?>
    </div>
  </header>

  <form class="dg-form dg-panel dg-print-hide" method="get" action="/app">
    <input type="hidden" name="page" value="website-gutschein-flyer">
    <label class="dg-field">
      <span>Gutschein wählen</span>
      <select name="id" onchange="this.form.submit()">
        <option value="">— bitte wählen —</option>
        <?php foreach ($list as $row) : ?>
          <?php if ((string) ($row['status'] ?? '') !== 'active') { continue; } ?>
          <option value="<?= (int) ($row['id'] ?? 0) ?>"<?= is_array($gift) && (int) ($gift['id'] ?? 0) === (int) ($row['id'] ?? 0) ? ' selected' : '' ?>>
            <?= View::escape((string) ($row['code'] ?? '')) ?>
            — <?= View::escape(number_format((float) ($row['face_value_gross'] ?? $row['amount'] ?? 0), 2, ',', '.')) ?> €
          </option>
        <?php endforeach; ?>
      </select>
    </label>
  </form>

  <?php if (is_array($gift)) : ?>
    <style>
      .dg-gift-flyer {
        --gf-ink: #1a1a1a;
        --gf-muted: #5c5c5c;
        --gf-line: #e6e6e6;
        --gf-accent: #2f5d50;
        max-width: 420px;
        margin: 2rem auto;
        padding: 2.5rem 2rem;
        border: 1px solid var(--gf-line);
        border-radius: 2px;
        background: linear-gradient(180deg, #ffffff 0%, #f7f7f5 100%);
        color: var(--gf-ink);
        font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
        text-align: center;
      }
      .dg-gift-flyer__brand {
        font-size: 0.85rem;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--gf-muted);
        margin-bottom: 1.25rem;
      }
      .dg-gift-flyer__logo {
        max-height: 48px;
        margin-bottom: 1rem;
      }
      .dg-gift-flyer__title {
        font-size: 1.75rem;
        font-weight: 600;
        margin: 0 0 0.5rem;
      }
      .dg-gift-flyer__amount {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--gf-accent);
        margin: 1.25rem 0;
      }
      .dg-gift-flyer__code {
        display: inline-block;
        margin-top: 0.75rem;
        padding: 0.55rem 1rem;
        border: 1px dashed var(--gf-accent);
        letter-spacing: 0.08em;
        font-weight: 600;
      }
      .dg-gift-flyer__meta {
        margin-top: 1.5rem;
        font-size: 0.9rem;
        color: var(--gf-muted);
      }
      @media print {
        .dg-print-hide, .dg-sidebar, .dg-topbar, .dg-page-header .dg-toolbar { display: none !important; }
        .dg-gift-flyer { border: none; margin: 0; max-width: none; }
      }
    </style>
    <article class="dg-gift-flyer">
      <?php if ($logo !== '') : ?>
        <img class="dg-gift-flyer__logo" src="<?= View::escape($logo) ?>" alt="">
      <?php endif; ?>
      <div class="dg-gift-flyer__brand"><?= View::escape($companyName) ?></div>
      <h2 class="dg-gift-flyer__title"><?= View::escape((string) (($gift['title'] ?? '') !== '' ? $gift['title'] : 'Gutschein')) ?></h2>
      <div class="dg-gift-flyer__amount"><?= View::escape(number_format((float) ($gift['face_value_gross'] ?? $gift['amount'] ?? 0), 2, ',', '.')) ?> €</div>
      <div class="dg-gift-flyer__code"><?= View::escape((string) ($gift['code'] ?? '')) ?></div>
      <p class="dg-gift-flyer__meta">
        Einlösbar für Artikel und Leistungen.<br>
        <?php if (!empty($gift['valid_until'])) : ?>
          Gültig bis <?= View::escape((string) $gift['valid_until']) ?>.
        <?php else : ?>
          Ohne Ablaufdatum.
        <?php endif; ?>
      </p>
    </article>
  <?php endif; ?>
</div>
