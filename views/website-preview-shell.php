<?php
declare(strict_types=1);

/**
 * Geräte-Simulator-Shell für /vorschau/{slug} (ohne ?frame=1).
 *
 * Erwartet: $page, $isDraft, $title, $siteName (bereits gesetzt).
 */
$slug = WebsitePageRepository::sanitizeSlug((string) ($page['slug'] ?? ''));
$frameSrc = '/vorschau/' . rawurlencode($slug !== '' ? $slug : 'startseite') . '?frame=1';
$editId = (int) ($page['id'] ?? 0);
$pageTitle = (string) ($page['title'] ?? 'Vorschau');
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow">
  <title>Vorschau · <?= View::escape($pageTitle) ?> – Geräte</title>
  <link rel="stylesheet" href="<?= View::escape(Asset::url('/assets/css/website-preview-device.css')) ?>">
</head>
<body class="ws-preview-shell">
  <header class="ws-preview-toolbar" role="banner">
    <div class="ws-preview-toolbar__left">
      <strong class="ws-preview-toolbar__title">Vorschau<?= !empty($isDraft) ? ' (Entwurf)' : '' ?></strong>
      <span class="ws-preview-toolbar__page"><?= View::escape($pageTitle) ?></span>
      <?php if ($editId > 0) : ?>
        <a class="ws-preview-toolbar__link" href="/app?page=website-seite-form&amp;action=edit&amp;id=<?= $editId ?>">Zurück zum Editor</a>
      <?php endif; ?>
    </div>
    <div class="ws-preview-toolbar__devices" role="group" aria-label="Geräte-Voreinstellungen">
      <button type="button" class="ws-preview-device-btn is-active" data-preset="desktop" data-w="1440" data-h="900" title="Desktop 1440×900">Desktop</button>
      <button type="button" class="ws-preview-device-btn" data-preset="laptop" data-w="1366" data-h="768" title="Laptop 1366×768">Laptop</button>
      <button type="button" class="ws-preview-device-btn" data-preset="tablet" data-w="768" data-h="1024" title="Tablet (iPad) 768×1024">Tablet</button>
      <label class="ws-preview-device-select-wrap">
        <span class="ws-visually-hidden">Weitere Geräte</span>
        <select id="ws-preview-device-select" class="ws-preview-device-select" aria-label="Handy und weitere Tablets">
          <option value="">Weitere Geräte …</option>
        </select>
      </label>
    </div>
    <div class="ws-preview-toolbar__size">
      <label class="ws-preview-field">
        <span>Breite</span>
        <input type="number" id="ws-preview-width" min="240" max="2560" step="1" value="1440" inputmode="numeric">
        <span class="ws-preview-field__unit">px</span>
      </label>
      <button type="button" class="ws-preview-rotate" id="ws-preview-rotate" title="Breite und Höhe tauschen" aria-label="Drehen">⇄</button>
      <label class="ws-preview-field">
        <span>Höhe</span>
        <input type="number" id="ws-preview-height" min="320" max="2560" step="1" value="900" inputmode="numeric">
        <span class="ws-preview-field__unit">px</span>
      </label>
      <label class="ws-preview-field ws-preview-field--scale">
        <span>Zoom</span>
        <select id="ws-preview-scale">
          <option value="fit">Einpassen</option>
          <option value="1">100 %</option>
          <option value="0.75">75 %</option>
          <option value="0.5">50 %</option>
        </select>
      </label>
    </div>
  </header>

  <div class="ws-preview-stage" id="ws-preview-stage">
    <div class="ws-preview-device" id="ws-preview-device" style="width:1440px;height:900px;">
      <div class="ws-preview-device__chrome" aria-hidden="true">
        <span class="ws-preview-device__dot"></span>
        <span class="ws-preview-device__label" id="ws-preview-label">1440 × 900</span>
      </div>
      <iframe
        class="ws-preview-frame"
        id="ws-preview-frame"
        title="Seiten-Vorschau"
        src="<?= View::escape($frameSrc) ?>"
      ></iframe>
    </div>
  </div>

  <script src="<?= View::escape(Asset::url('/assets/js/website-preview-device.js')) ?>" defer></script>
</body>
</html>
