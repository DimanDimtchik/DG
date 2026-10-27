<?php
/**
 * @var list<array<string, mixed>> $giftVoucherList
 * @var array<string, mixed>|null $flyerGift
 * @var array<string, mixed>|null $flyerPrint
 * @var string $companyName
 * @var string $companyLogoUrl
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$gift = $flyerGift ?? null;
$list = $giftVoucherList ?? [];
$print = $flyerPrint ?? null;
$companyName = $companyName ?? 'Gutschein';
$logo = $companyLogoUrl ?? '';
if ($print === null && is_array($gift)) {
    $print = FlyerService::giftPrintModel($gift, null, $companyName, $logo);
}
$accent = is_array($print) ? (string) ($print['accent'] ?? '#2f5d50') : '#2f5d50';
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Gutschein-Flyer</h1>
      <p class="dg-lead">Formular-Flyer mit Gutscheinnummer — zum Ausdrucken oder Mitgeben. (Terminkalender-QR-Flyer sind separat unter Einstellungen.)</p>
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

  <?php if (is_array($gift) && is_array($print)) : ?>
    <style>
      .dg-gift-flyer {
        --gf-ink: #1a1a1a;
        --gf-muted: #5c5c5c;
        --gf-line: #e6e6e6;
        --gf-accent: <?= View::escape($accent) ?>;
        max-width: 420px;
        margin: var(--dg-space-5) auto;
        padding: 2.5rem 2rem;
        border: 1px solid var(--gf-line);
        border-radius: var(--dg-radius-sm);
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
        margin-bottom: var(--dg-space-4);
      }
      .dg-gift-flyer__logo {
        max-height: 48px;
        margin-bottom: var(--dg-space-3);
      }
      .dg-gift-flyer__title {
        font-size: 1.75rem;
        font-weight: 600;
        margin: 0 0 var(--dg-space-2);
        color: var(--gf-ink);
      }
      .dg-gift-flyer__subtitle {
        margin: 0 0 var(--dg-space-3);
        color: var(--gf-muted);
        font-size: 0.95rem;
      }
      .dg-gift-flyer__amount {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--gf-accent);
        margin: var(--dg-space-4) 0;
      }
      .dg-gift-flyer__code {
        display: inline-block;
        margin-top: var(--dg-space-3);
        padding: var(--dg-space-2) var(--dg-space-4);
        border: 1px dashed var(--gf-accent);
        letter-spacing: 0.08em;
        font-weight: 600;
      }
      .dg-gift-flyer__meta {
        margin-top: var(--dg-space-4);
        font-size: 0.9rem;
        color: var(--gf-muted);
      }
      @media print {
        .dg-print-hide, .dg-sidebar, .dg-topbar, .dg-page-header .dg-toolbar { display: none !important; }
        .dg-gift-flyer { border: none; margin: 0; max-width: none; box-shadow: none; }
      }
    </style>
    <article class="dg-gift-flyer">
      <?php if (($print['logo_url'] ?? '') !== '') : ?>
        <img class="dg-gift-flyer__logo" src="<?= View::escape((string) $print['logo_url']) ?>" alt="">
      <?php endif; ?>
      <div class="dg-gift-flyer__brand"><?= View::escape((string) $print['company_name']) ?></div>
      <h2 class="dg-gift-flyer__title"><?= View::escape((string) $print['headline']) ?></h2>
      <?php if (trim((string) ($print['subtitle'] ?? '')) !== '') : ?>
        <p class="dg-gift-flyer__subtitle"><?= View::escape((string) $print['subtitle']) ?></p>
      <?php endif; ?>
      <div class="dg-gift-flyer__amount"><?= View::escape((string) $print['amount_label']) ?></div>
      <div class="dg-gift-flyer__code"><?= View::escape((string) $print['code']) ?></div>
      <p class="dg-gift-flyer__meta">
        Bitte diesen Flyer oder die Rechnung beim Einlösen vorzeigen.<br>
        <?php if (!empty($print['valid_until'])) : ?>
          Gültig bis <?= View::escape((string) $print['valid_until']) ?>.
        <?php else : ?>
          Ohne Ablaufdatum.
        <?php endif; ?>
      </p>
    </article>
  <?php endif; ?>
</div>
