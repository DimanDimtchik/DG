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
$textColor = is_array($print) ? (string) ($print['text_color'] ?? '#1a1a1a') : '#1a1a1a';
$format = is_array($print) ? (string) ($print['format'] ?? 'a6') : 'a6';
$pageMm = is_array($print) && is_array($print['page_mm'] ?? null)
    ? $print['page_mm']
    : ['w' => 105, 'h' => 148];
$bgMode = is_array($print) ? (string) ($print['bg_mode'] ?? 'color') : 'color';
$bgColor = is_array($print) ? (string) ($print['bg_color'] ?? '#ffffff') : '#ffffff';
$bgUrl = is_array($print) ? (string) ($print['bg_url'] ?? '') : '';
$bgOpacity = is_array($print) ? (float) ($print['bg_opacity'] ?? 1) : 1;
$hasBookingQr = is_array($print) && trim((string) ($print['qr_booking_url'] ?? '')) !== '';
$socialQrs = is_array($print) && is_array($print['qr_social'] ?? null) ? $print['qr_social'] : [];
if ($socialQrs === [] && is_array($print) && trim((string) ($print['qr_social_url'] ?? '')) !== '') {
    $socialQrs = [['key' => 'social', 'label' => 'Social', 'url' => (string) $print['qr_social_url']]];
}
$hasSocialQr = $socialQrs !== [];
$hw = is_array($print) && is_array($print['handwriting'] ?? null) ? $print['handwriting'] : ['enabled' => false, 'label' => ''];
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Gutschein-Flyer</h1>
      <p class="dg-lead">Formular-Flyer mit Gutscheinnummer — Format und Layout aus der Vorlage. (Terminkalender-QR separat unter Einstellungen.)</p>
    </div>
    <div class="dg-toolbar">
      <a class="dg-button" href="/app?page=gutscheine">Gutscheine</a>
      <a class="dg-button" href="/app?page=gutscheine-flyer-vorlagen">Vorlagen</a>
      <?php if (is_array($gift)) : ?>
        <button type="button" class="dg-button dg-button--primary" onclick="window.print()">Drucken</button>
      <?php endif; ?>
    </div>
  </header>

  <?php
    $isPreview = is_array($gift) && (int) ($gift['id'] ?? 0) === 0 && (string) ($gift['code'] ?? '') === 'VORSCHAU-DEMO';
  ?>
  <?php if ($isPreview) : ?>
    <div class="dg-flash dg-flash--warning dg-print-hide">
      Vorschau mit Demo-Code — kein echter Gutschein. Zum Druck bitte einen ausgestellten Gutschein wählen.
    </div>
  <?php endif; ?>

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
      .dg-gift-flyer-sheet {
        --gf-ink: <?= View::escape($textColor) ?>;
        --gf-muted: #5c5c5c;
        --gf-accent: <?= View::escape($accent) ?>;
        --gf-w: <?= (float) $pageMm['w'] ?>mm;
        --gf-h: <?= (float) $pageMm['h'] ?>mm;
        width: var(--gf-w);
        min-height: var(--gf-h);
        margin: var(--dg-space-5) auto;
        position: relative;
        overflow: hidden;
        box-sizing: border-box;
        color: var(--gf-ink);
        font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
        background: <?= $bgMode === 'color' ? View::escape($bgColor) : '#ffffff' ?>;
        border: 1px solid #e6e6e6;
      }
      .dg-gift-flyer-sheet__bg {
        position: absolute;
        inset: 0;
        background-size: cover;
        background-position: center;
        opacity: <?= max(0.2, min(1, $bgOpacity)) ?>;
        z-index: 0;
        pointer-events: none;
      }
      .dg-gift-flyer-sheet__inner {
        position: relative;
        z-index: 1;
        padding: 8mm 8mm 7mm;
        min-height: var(--gf-h);
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
        text-align: center;
        gap: 3mm;
      }
      .dg-gift-flyer-sheet__brand {
        font-size: 9pt;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        opacity: 0.75;
      }
      .dg-gift-flyer-sheet__logo {
        max-height: 12mm;
        max-width: 40mm;
        margin: 0 auto;
        object-fit: contain;
      }
      .dg-gift-flyer-sheet__title-wrap {
        margin: 1mm 0;
      }
      .dg-gift-flyer-sheet__title-wrap--banner {
        background: rgba(255, 255, 255, 0.88);
        padding: 2mm 3mm;
        border-radius: 2mm;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
      }
      .dg-gift-flyer-sheet__title {
        font-size: 16pt;
        font-weight: 600;
        margin: 0;
        line-height: 1.2;
        text-shadow: <?= !empty($print['contrast_banner']) ? 'none' : '0 0 4px rgba(255,255,255,0.7)' ?>;
      }
      .dg-gift-flyer-sheet__subtitle {
        margin: 1mm 0 0;
        font-size: 9pt;
        opacity: 0.85;
      }
      .dg-gift-flyer-sheet__image {
        max-height: 28mm;
        max-width: 100%;
        margin: 1mm auto;
        object-fit: contain;
      }
      .dg-gift-flyer-sheet__amount {
        font-size: 22pt;
        font-weight: 700;
        color: var(--gf-accent);
        margin: 2mm 0;
      }
      .dg-gift-flyer-sheet__code {
        display: inline-block;
        margin: 0 auto;
        padding: 2mm 4mm;
        border: 1px dashed var(--gf-accent);
        letter-spacing: 0.08em;
        font-weight: 600;
        font-size: 11pt;
        background: rgba(255, 255, 255, 0.9);
      }
      .dg-gift-flyer-sheet__meta {
        font-size: 8pt;
        opacity: 0.85;
      }
      .dg-gift-flyer-sheet__qrs {
        display: flex;
        justify-content: center;
        gap: 8mm;
        margin: 2mm 0;
        flex-wrap: wrap;
      }
      .dg-gift-flyer-sheet__qr {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1.5mm;
        /* Ruhezone ~8 mm um den QR */
        padding: 8mm;
        background: #ffffff;
        border-radius: 1mm;
      }
      .dg-gift-flyer-sheet__qr canvas,
      .dg-gift-flyer-sheet__qr img {
        display: block;
        width: 22mm !important;
        height: 22mm !important;
      }
      .dg-gift-flyer-sheet__qr-label {
        font-size: 7pt;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #333;
      }
      .dg-gift-flyer-sheet__hw {
        margin-top: auto;
        border: 1px dashed #9a9a9a;
        border-radius: 1mm;
        padding: 2mm 3mm 3mm;
        text-align: left;
        background: rgba(255, 255, 255, 0.75);
        min-height: 28mm;
      }
      .dg-gift-flyer-sheet__hw-label {
        font-size: 7.5pt;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        opacity: 0.7;
        margin-bottom: 2mm;
      }
      .dg-gift-flyer-sheet__hw-lines {
        display: flex;
        flex-direction: column;
        gap: 6mm;
      }
      .dg-gift-flyer-sheet__hw-lines span {
        display: block;
        border-bottom: 1px solid #c8c8c8;
        height: 0;
      }
      .dg-gift-flyer-sheet__footer {
        font-size: 7.5pt;
        opacity: 0.8;
        margin-top: 1mm;
      }
      .dg-gift-flyer-sheet__legal {
        font-size: 6.5pt;
        line-height: 1.35;
        opacity: 0.85;
        text-align: left;
        margin: 2mm 0 0;
      }
      @media print {
        .dg-print-hide,
        .dg-sidebar,
        .dg-topbar,
        .dg-page-header,
        .dg-flash {
          display: none !important;
        }
        .dg-wrap {
          margin: 0 !important;
          padding: 0 !important;
          max-width: none !important;
        }
        .dg-gift-flyer-sheet {
          border: none;
          margin: 0;
          box-shadow: none;
          width: var(--gf-w);
          height: var(--gf-h);
          min-height: var(--gf-h);
          page-break-after: always;
        }
        @page {
          size: <?= (float) $pageMm['w'] ?>mm <?= (float) $pageMm['h'] ?>mm;
          margin: 0;
        }
      }
    </style>

    <article
      class="dg-gift-flyer-sheet"
      data-dg-gift-flyer
      data-format="<?= View::escape($format) ?>"
      <?php if ($hasBookingQr) : ?>data-qr-booking="<?= View::escape((string) $print['qr_booking_url']) ?>"<?php endif; ?>
    >
      <?php if ($bgMode === 'image' && $bgUrl !== '') : ?>
        <div class="dg-gift-flyer-sheet__bg" style="background-image:url('<?= View::escape($bgUrl) ?>')"></div>
      <?php endif; ?>
      <div class="dg-gift-flyer-sheet__inner">
        <?php if (!empty($print['show_logo']) && trim((string) ($print['logo_url'] ?? '')) !== '') : ?>
          <img class="dg-gift-flyer-sheet__logo" src="<?= View::escape((string) $print['logo_url']) ?>" alt="">
        <?php endif; ?>
        <div class="dg-gift-flyer-sheet__brand"><?= View::escape((string) $print['company_name']) ?></div>

        <div class="dg-gift-flyer-sheet__title-wrap<?= !empty($print['contrast_banner']) ? ' dg-gift-flyer-sheet__title-wrap--banner' : '' ?>">
          <h2 class="dg-gift-flyer-sheet__title"><?= View::escape((string) $print['headline']) ?></h2>
          <?php if (trim((string) ($print['subtitle'] ?? '')) !== '') : ?>
            <p class="dg-gift-flyer-sheet__subtitle"><?= View::escape((string) $print['subtitle']) ?></p>
          <?php endif; ?>
        </div>

        <?php if (trim((string) ($print['image_url'] ?? '')) !== '') : ?>
          <img class="dg-gift-flyer-sheet__image" src="<?= View::escape((string) $print['image_url']) ?>" alt="">
        <?php endif; ?>

        <div class="dg-gift-flyer-sheet__amount"><?= View::escape((string) $print['amount_label']) ?></div>
        <div class="dg-gift-flyer-sheet__code"><?= View::escape((string) $print['code']) ?></div>

        <p class="dg-gift-flyer-sheet__meta">
          <?php if (!empty($print['valid_until'])) : ?>
            Gültig bis <?= View::escape((string) $print['valid_until']) ?>.
          <?php else : ?>
            Ohne Ablaufdatum.
          <?php endif; ?>
        </p>

        <?php if ($hasBookingQr || $hasSocialQr) : ?>
          <div class="dg-gift-flyer-sheet__qrs">
            <?php if ($hasBookingQr) : ?>
              <div class="dg-gift-flyer-sheet__qr">
                <div data-dg-gift-qr="booking" aria-hidden="true"></div>
                <span class="dg-gift-flyer-sheet__qr-label">Termin</span>
              </div>
            <?php endif; ?>
            <?php foreach ($socialQrs as $qi => $sqr) : ?>
              <?php if (trim((string) ($sqr['url'] ?? '')) === '') { continue; } ?>
              <div class="dg-gift-flyer-sheet__qr">
                <div data-dg-gift-qr="social" data-qr-url="<?= View::escape((string) $sqr['url']) ?>" aria-hidden="true"></div>
                <span class="dg-gift-flyer-sheet__qr-label"><?= View::escape((string) ($sqr['label'] ?? 'Social')) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($hw['enabled'])) : ?>
          <div class="dg-gift-flyer-sheet__hw">
            <div class="dg-gift-flyer-sheet__hw-label"><?= View::escape((string) ($hw['label'] ?? 'Persönliche Grüße')) ?></div>
            <div class="dg-gift-flyer-sheet__hw-lines" aria-hidden="true">
              <span></span><span></span><span></span>
            </div>
          </div>
        <?php endif; ?>

        <?php if (trim((string) ($print['footer'] ?? '')) !== '') : ?>
          <p class="dg-gift-flyer-sheet__footer"><?= View::escape((string) $print['footer']) ?></p>
        <?php endif; ?>
        <?php if (trim((string) ($print['legal_notes'] ?? '')) !== '') : ?>
          <p class="dg-gift-flyer-sheet__legal"><?= View::escape((string) $print['legal_notes']) ?></p>
        <?php endif; ?>
      </div>
    </article>

    <script>
      (function () {
        function renderQr(el, text) {
          if (!el || !text || typeof qrcode !== 'function') {
            return;
          }
          try {
            var qr = qrcode(0, 'M');
            qr.addData(text);
            qr.make();
            el.innerHTML = qr.createImgTag(4, 0);
            var img = el.querySelector('img');
            if (img) {
              img.alt = '';
            }
          } catch (e) {
            el.textContent = '';
          }
        }
        function run() {
          var sheet = document.querySelector('[data-dg-gift-flyer]');
          if (!sheet) return;
          var booking = sheet.getAttribute('data-qr-booking') || '';
          renderQr(sheet.querySelector('[data-dg-gift-qr="booking"]'), booking);
          sheet.querySelectorAll('[data-dg-gift-qr="social"]').forEach(function (el) {
            renderQr(el, el.getAttribute('data-qr-url') || '');
          });
        }
        if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', run);
        } else {
          run();
        }
        window.addEventListener('load', run);
      })();
    </script>
  <?php endif; ?>
</div>
