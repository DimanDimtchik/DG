<?php
/**
 * Public website page template.
 *
 * Expected variables: $page, $chrome, $menu, $design, $seoMeta
 */
$title = View::escape($page['title'] ?? '');
$chrome = $chrome ?? WebsiteSettings::chromeDefaults();
$menu = $menu ?? WebsiteSettings::menuDefaults();
$design = $design ?? WebsiteSettings::designDefaults();
$headerIconStyle = WebsiteMenuIcons::headerIconStyleDefaults();
$headerIconVars = WebsiteMenuIcons::iconStyleCssVars($headerIconStyle);
$menuIconRight = $headerIconStyle['position'] === 'right';
$layout = $page['layout'] ?? ['rows' => []];
$siteName = View::escape($chrome['header_title'] ?: (string) App::config('crm_name'));
$seoPage = [
    'title' => (string) ($page['title'] ?? ''),
    'description' => (string) ($page['description'] ?? ''),
    'url' => App::publicBaseUrl() . '/' . ltrim((string) ($page['slug'] ?? ''), '/'),
    'type' => 'website',
];
if (($page['slug'] ?? '') === 'startseite') {
    $seoPage['url'] = App::publicBaseUrl() . '/';
}
$previewMode = !empty($previewMode);
$isDraft = ($page['status'] ?? '') !== WebsitePageRepository::STATUS_PUBLISHED;
if ($previewMode) {
    $seoPage['noindex'] = true;
}
WebsitePageviewTracker::trackPublicPage(is_array($page ?? null) ? $page : null, $previewMode);
$formFlashById = [];
$flashFormId = (int) ($_GET['form_id'] ?? 0);
if (!empty($_GET['form_ok']) && $flashFormId > 0) {
    $formFlashById[$flashFormId] = ['ok' => true];
}
if (!empty($_GET['form_err']) && $flashFormId > 0) {
    $formFlashById[$flashFormId] = ['error' => (string) $_GET['form_err']];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?= SeoMeta::renderHead($seoPage) ?>
  <title><?= $title ?> – <?= $siteName ?></title>
  <?php if (AppearanceSettings::hasFavicon()) : ?>
    <?php if (AppearanceSettings::faviconIsSvg()) : ?>
      <link rel="icon" href="/app/favicon" type="image/svg+xml">
    <?php endif; ?>
    <link rel="icon" href="/app/favicon?size=32" type="image/png" sizes="32x32">
    <link rel="icon" href="/app/favicon?size=16" type="image/png" sizes="16x16">
    <link rel="apple-touch-icon" href="/app/favicon?size=48">
  <?php endif; ?>
  <style>
    :root {
      --ws-primary: <?= View::escape($design['primary']) ?>;
      --ws-bg: <?= View::escape($design['background']) ?>;
      --ws-text: <?= View::escape($design['text']) ?>;
<?php foreach ($headerIconVars as $var => $val) : ?>
      <?= View::escape($var) ?>: <?= View::escape($val) ?>;
<?php endforeach; ?>
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: var(--ws-text); background: var(--ws-bg); line-height: 1.6; }
    a { color: var(--ws-primary); }

    /* Header */
    .ws-header { background: var(--ws-primary); color: #fff; padding: 20px 0; }
    .ws-header__inner { max-width: 1140px; margin: 0 auto; padding: 0 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
    .ws-header__brand { flex: 1 1 auto; min-width: 0; }
    .ws-header__title { font-size: 1.4rem; font-weight: 700; }
    .ws-header__tagline { font-size: 0.9rem; opacity: 0.85; }
    .ws-nav-toggle {
      display: none; align-items: center; justify-content: center;
      width: 42px; height: 42px; padding: 0; border: 1px solid rgba(255,255,255,0.45);
      border-radius: 6px; background: transparent; color: #fff; cursor: pointer;
    }
    .ws-nav-toggle:hover { background: rgba(255,255,255,0.15); }
    .ws-nav-toggle__bars {
      display: block; width: 18px; height: 2px; background: currentColor; position: relative;
    }
    .ws-nav-toggle__bars::before, .ws-nav-toggle__bars::after {
      content: ''; position: absolute; left: 0; width: 18px; height: 2px; background: currentColor;
    }
    .ws-nav-toggle__bars::before { top: -6px; }
    .ws-nav-toggle__bars::after { top: 6px; }
    .ws-nav { display: flex; gap: 4px; flex-wrap: wrap; align-items: center; }
    .ws-nav a, .ws-nav__toggle {
      color: #fff; text-decoration: none; padding: 6px 14px; border-radius: 4px; font-size: 0.95rem;
      transition: background 0.15s; display: inline-flex; align-items: center; gap: var(--ws-nav-icon-gap, 0.4em);
      background: transparent; border: 0; font: inherit; text-align: left; cursor: pointer;
    }
    .ws-nav.ws-nav--icon-right a,
    .ws-nav.ws-nav--icon-right .ws-nav__toggle { flex-direction: row-reverse; text-align: right; }
    .ws-nav a:hover, .ws-nav a.active { background: rgba(255,255,255,0.18); }
    .ws-nav__item { position: relative; }
    .ws-nav__toggle:hover, .ws-nav__item:hover > .ws-nav__toggle, .ws-nav__item.is-open > .ws-nav__toggle { background: rgba(255,255,255,0.18); }
    .ws-nav__icon, .ws-nav__caret {
      width: var(--ws-nav-icon-size, 1.05em); height: var(--ws-nav-icon-size, 1.05em);
      flex-shrink: 0; display: block; color: var(--ws-nav-icon-color, currentColor);
    }
    .ws-nav__caret {
      width: 0.85em; height: 0.85em; flex-shrink: 0; margin-left: 0.15em;
      transition: transform 0.15s ease;
    }
    .ws-nav__item.is-open > .ws-nav__toggle .ws-nav__caret { transform: rotate(180deg); }
    .ws-nav__sub { display: none; position: absolute; top: 100%; left: 0; min-width: 180px; background: #fff; color: var(--ws-text); border-radius: 6px; box-shadow: 0 8px 24px rgba(0,0,0,0.15); padding: 6px 0; z-index: 40; }
    .ws-nav__item:hover > .ws-nav__sub, .ws-nav__item.is-open > .ws-nav__sub { display: block; }
    .ws-nav__sub a { display: flex; align-items: center; gap: var(--ws-nav-icon-gap, 0.4em); color: var(--ws-text); padding: 8px 14px; border-radius: 0; }
    .ws-nav__sub a.ws-nav__link--icon-right { flex-direction: row-reverse; text-align: right; }
    .ws-nav__sub a .ws-nav__icon { color: var(--ws-nav-icon-color, var(--ws-primary)); stroke-width: var(--ws-nav-icon-stroke, 1.75); }
    .ws-nav__sub a:hover .ws-nav__icon, .ws-nav__sub a.active .ws-nav__icon {
      color: var(--ws-nav-icon-hover-color, var(--ws-nav-icon-color, var(--ws-primary)));
    }
    .ws-nav__sub a:hover, .ws-nav__sub a.active { background: #f3f4f6; color: var(--ws-primary); }
    .ws-nav__badge { font-size: 0.7rem; opacity: 0.85; margin-left: 4px; padding: 1px 6px; border-radius: 999px; background: rgba(110,98,88,0.12); }
    .ws-nav__sub a.ws-nav__link--icon-hide-mobile .ws-nav__icon { display: inline-flex; }
    .ws-header.is-compact .ws-nav__sub a.ws-nav__link--icon-hide-mobile .ws-nav__icon { display: none; }
    .ws-nav__sub a.ws-nav__link--no-icon .ws-nav__icon { display: none !important; }

    /* Mobile / compact nav panel */
    .ws-header.is-compact .ws-nav-toggle { display: inline-flex; }
    .ws-header.is-compact .ws-nav {
      display: none; flex-direction: column; align-items: stretch; flex: 1 1 100%;
      width: 100%; gap: 2px; padding-top: 8px;
    }
    .ws-header.is-compact.is-nav-open .ws-nav { display: flex; }
    .ws-header.is-compact .ws-nav__item { width: 100%; }
    .ws-header.is-compact .ws-nav__sub {
      position: static; box-shadow: none; border-radius: 0; margin: 0 0 4px 12px;
      background: rgba(255,255,255,0.12); padding: 4px 0;
    }
    .ws-header.is-compact .ws-nav__sub a { color: #fff; }
    .ws-header.is-compact .ws-nav__sub a:hover,
    .ws-header.is-compact .ws-nav__sub a.active { background: rgba(255,255,255,0.18); color: #fff; }
    .ws-header.is-compact .ws-nav__item:hover > .ws-nav__sub { display: none; }
    .ws-header.is-compact .ws-nav__item.is-open > .ws-nav__sub { display: block; }

    /* Main */
    .ws-main { max-width: 1140px; margin: 0 auto; padding: 40px 20px; }

    /* Row / Col grid */
    .ws-row { display: flex; gap: 24px; margin-bottom: 24px; flex-wrap: wrap; }
    .ws-col { min-width: 0; }
    .ws-col-12 { flex: 0 0 100%; max-width: 100%; }
    .ws-col-6 { flex: 0 0 calc(50% - 12px); max-width: calc(50% - 12px); }
    .ws-col-4 { flex: 0 0 calc(33.333% - 16px); max-width: calc(33.333% - 16px); }
    @media (max-width: 768px) {
      .ws-col-6, .ws-col-4 { flex: 0 0 100%; max-width: 100%; }
    }

    /* Blocks */
    .ws-block { margin-bottom: 16px; }
    .ws-block h1 { font-size: 2rem; margin-bottom: 12px; }
    .ws-block h2 { font-size: 1.5rem; margin-bottom: 10px; }
    .ws-block h3 { font-size: 1.25rem; margin-bottom: 8px; }
    .ws-block p { margin-bottom: 8px; }
    .ws-block img { max-width: 100%; height: auto; border-radius: 6px; }
    .ws-block .ws-btn { display: inline-block; padding: 10px 24px; background: var(--ws-primary); color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; transition: opacity 0.15s; border: none; cursor: pointer; font: inherit; }
    .ws-block .ws-btn:hover { opacity: 0.85; }

    .ws-form { margin: 8px 0 16px; }
    .ws-form__flash { padding: 12px 14px; border-radius: 8px; margin-bottom: 12px; }
    .ws-form__flash--ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .ws-form__flash--error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .ws-form__form { display: block; padding: 20px; background: #f9f9f9; border: 1px solid #eee; border-radius: 8px; }
    .ws-form__grid { display: flex; flex-wrap: wrap; gap: 12px; }
    .ws-form__cell { min-width: 0; }
    .ws-form__cell--12 { flex: 0 0 100%; max-width: 100%; }
    .ws-form__cell--9 { flex: 0 0 calc(75% - 9px); max-width: calc(75% - 9px); }
    .ws-form__cell--8 { flex: 0 0 calc(66.66% - 8px); max-width: calc(66.66% - 8px); }
    .ws-form__cell--6 { flex: 0 0 calc(50% - 6px); max-width: calc(50% - 6px); }
    .ws-form__cell--4 { flex: 0 0 calc(33.33% - 8px); max-width: calc(33.33% - 8px); }
    .ws-form__cell--3 { flex: 0 0 calc(25% - 9px); max-width: calc(25% - 9px); }
    @media (max-width: 768px) {
      .ws-form__cell--9, .ws-form__cell--8, .ws-form__cell--6, .ws-form__cell--4, .ws-form__cell--3 { flex: 0 0 100%; max-width: 100%; }
    }
    .ws-form__label { display: flex; flex-direction: column; gap: 6px; font-size: 0.95rem; }
    .ws-form input[type="text"], .ws-form input[type="email"], .ws-form input[type="tel"], .ws-form input[type="file"], .ws-form select, .ws-form textarea {
      padding: 10px; border: 1px solid #ddd; border-radius: 4px; font: inherit; width: 100%; background: #fff;
    }
    .ws-form__choices { display: flex; flex-direction: column; gap: 6px; }
    .ws-form__choice {
      display: flex; flex-direction: row; align-items: flex-start; justify-content: flex-start;
      gap: 8px; font-size: 0.95rem; text-align: left; width: 100%;
    }
    .ws-form__choice input[type="checkbox"],
    .ws-form__choice input[type="radio"] {
      width: auto; flex-shrink: 0; margin: 0.25em 0 0; padding: 0;
    }
    .ws-form__consent { margin: 4px 0; }
    .ws-form__appt-status--ok { color: #065f46; }
    .ws-form__appt-status--err { color: #991b1b; }
    .ws-form__help { color: #666; font-size: 0.85rem; }
    .ws-form__req { color: #b91c1c; }
    .ws-form__heading { margin: 4px 0 0; font-size: 1.2rem; }
    .ws-form__paragraph { margin: 0; color: #555; }
    .ws-form__captcha input[type="text"] { max-width: 140px; }

    /* Footer */
    .ws-footer { background: #f5f5f5; color: #666; padding: 24px 0; margin-top: 40px; border-top: 1px solid #e0e0e0; }
    .ws-footer__inner { max-width: 1140px; margin: 0 auto; padding: 0 20px; text-align: center; font-size: 0.9rem; }
  </style>
  <?= WebsiteAnalytics::headHtml() ?>
  <?php if (!empty($chrome['header_js'])): ?>
  <?= $chrome['header_js'] ?>
  <?php endif; ?>
</head>
<body>
<?= WebsiteAnalytics::bodyOpenHtml() ?>

<?php if ($previewMode) : ?>
<div style="background:#fef3c7;color:#92400e;text-align:center;padding:10px 16px;font-size:0.9rem;border-bottom:1px solid #fcd34d;">
  Vorschau<?= $isDraft ? ' (Entwurf – noch nicht öffentlich)' : '' ?>
  · <a href="/app?page=website-seite-form&amp;action=edit&amp;id=<?= (int) ($page['id'] ?? 0) ?>" style="color:#92400e;font-weight:600;">Zurück zum Editor</a>
</div>
<?php endif; ?>

<header class="ws-header" id="ws-header"
        data-nav-mode="<?= View::escape((string) ($menu['layout'] ?? 'auto')) ?>"
        data-nav-breakpoint="<?= (int) ($menu['breakpoint'] ?? 768) ?>">
  <div class="ws-header__inner">
    <div class="ws-header__brand">
      <div class="ws-header__title"><?= $siteName ?></div>
      <?php if (!empty($chrome['header_tagline'])): ?>
        <div class="ws-header__tagline"><?= View::escape($chrome['header_tagline']) ?></div>
      <?php endif; ?>
    </div>
    <button type="button" class="ws-nav-toggle" id="ws-nav-toggle" aria-controls="ws-nav" aria-expanded="false" aria-label="Menü öffnen">
      <span class="ws-nav-toggle__bars" aria-hidden="true"></span>
    </button>
    <nav class="ws-nav<?= $menuIconRight ? ' ws-nav--icon-right' : '' ?>" id="ws-nav">
      <?php
        $currentPublicPath = WebsitePageRepository::publicPath((string) ($page['slug'] ?? ''));
        $rewriteHref = static function (string $itemUrl) use ($previewMode): string {
            $itemUrl = trim($itemUrl);
            if ($itemUrl === '') {
                $itemUrl = '/';
            }
            if (!$previewMode || !str_starts_with($itemUrl, '/') || str_starts_with($itemUrl, '//')) {
                return $itemUrl;
            }
            $slugPart = ltrim($itemUrl, '/');
            if ($slugPart === '' || $slugPart === 'startseite') {
                return '/vorschau/startseite';
            }
            return '/vorschau/' . $slugPart;
        };
        $isActivePath = static function (string $itemUrl) use ($currentPublicPath): bool {
            $itemUrl = trim($itemUrl);
            if ($itemUrl === '' || $itemUrl === '#') {
                return false;
            }
            return (rtrim($itemUrl, '/') ?: '/') === (rtrim($currentPublicPath, '/') ?: '/');
        };
        foreach ($menu['items'] as $item):
          $children = is_array($item['children'] ?? null) ? $item['children'] : [];
          $itemUrl = trim((string) ($item['url'] ?? '/'));
          $href = $rewriteHref($itemUrl === '' ? '#' : $itemUrl);
          $isActive = $isActivePath($itemUrl);
          foreach ($children as $child) {
              if ($isActivePath((string) ($child['url'] ?? ''))) {
                  $isActive = true;
                  break;
              }
          }
          $iconId = WebsiteMenuIcons::resolve($item);
          $iconHtml = $iconId !== '' ? WebsiteMenuIcons::svg($iconId, 'ws-nav__icon', $headerIconStyle) : '';
          $showCaret = $children !== [] && $iconId !== 'chevron-down';
          $caretHtml = $showCaret ? WebsiteMenuIcons::svg('chevron-down', 'ws-nav__caret', $headerIconStyle) : '';
          $textHtml = '<span>' . View::escape((string) $item['label']) . '</span>'
              . (!empty($item['auth_only']) ? ' <span class="ws-nav__badge">(intern)</span>' : '');
          $labelHtml = $menuIconRight
              ? ($textHtml . $iconHtml . $caretHtml)
              : ($iconHtml . $textHtml . $caretHtml);
      ?>
        <?php if ($children !== []) : ?>
          <div class="ws-nav__item<?= $isActive ? ' is-open' : '' ?>">
            <?php if ($itemUrl !== '' && $itemUrl !== '#') : ?>
              <a class="ws-nav__toggle<?= $isActive ? ' active' : '' ?>" href="<?= View::escape($href) ?>"><?= $labelHtml ?></a>
            <?php else : ?>
              <button type="button" class="ws-nav__toggle" aria-expanded="<?= $isActive ? 'true' : 'false' ?>"><?= $labelHtml ?></button>
            <?php endif; ?>
            <div class="ws-nav__sub">
              <?php foreach ($children as $child):
                $childUrl = trim((string) ($child['url'] ?? '/'));
                $childHref = $rewriteHref($childUrl === '' ? '/' : $childUrl);
                $childActive = $isActivePath($childUrl);
                $childIconStyle = WebsiteMenuIcons::normalizeSubmenuIconStyle(is_array($child['icon_style'] ?? null) ? $child['icon_style'] : []);
                $childIcon = WebsiteMenuIcons::submenuIconVisible($child) ? WebsiteMenuIcons::resolve($child + ['children' => []]) : '';
                $childIconHtml = $childIcon !== '' ? WebsiteMenuIcons::svg($childIcon, 'ws-nav__icon', $childIconStyle) : '';
                $badgeLabel = WebsiteMenuIcons::submenuBadgeLabel($child);
                $childText = '<span>' . View::escape((string) ($child['label'] ?? '')) . '</span>'
                    . ($badgeLabel !== '' ? ' <span class="ws-nav__badge">' . View::escape($badgeLabel) . '</span>' : '')
                    . (!empty($child['auth_only']) ? ' <span class="ws-nav__badge">(intern)</span>' : '');
                $childIconRight = WebsiteMenuIcons::submenuLinkIconRight($child);
                $childLabel = $childIconRight ? ($childText . $childIconHtml) : ($childIconHtml . $childText);
                $childStyleAttr = WebsiteMenuIcons::submenuLinkStyleAttr($child);
                $childClasses = WebsiteMenuIcons::submenuLinkClasses($child, $childActive);
              ?>
                <a href="<?= View::escape($childHref) ?>"<?= $childClasses !== [] ? ' class="' . View::escape(implode(' ', $childClasses)) . '"' : '' ?><?= $childStyleAttr !== '' ? ' style="' . View::escape($childStyleAttr) . '"' : '' ?>><?= $childLabel ?></a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php else : ?>
          <a href="<?= View::escape($href) ?>"<?= $isActive ? ' class="active"' : '' ?>><?= $labelHtml ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
  </div>
</header>
<script>
(function () {
  var header = document.getElementById('ws-header');
  var toggle = document.getElementById('ws-nav-toggle');
  if (!header || !toggle) return;

  var mode = header.getAttribute('data-nav-mode') || 'auto';
  var breakpoint = parseInt(header.getAttribute('data-nav-breakpoint') || '768', 10);
  if (!breakpoint || breakpoint < 320) breakpoint = 768;

  function setOpen(open) {
    header.classList.toggle('is-nav-open', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    toggle.setAttribute('aria-label', open ? 'Menü schließen' : 'Menü öffnen');
  }

  function applyMode() {
    var compact = mode === 'mobile' || (mode === 'auto' && window.innerWidth <= breakpoint);
    header.classList.toggle('is-compact', compact);
    if (!compact) setOpen(false);
  }

  toggle.addEventListener('click', function () {
    setOpen(!header.classList.contains('is-nav-open'));
  });

  header.querySelectorAll('.ws-nav__item').forEach(function (item) {
    var trigger = item.querySelector('.ws-nav__toggle');
    if (!trigger) return;
    trigger.addEventListener('click', function (event) {
      if (!header.classList.contains('is-compact')) return;
      if (trigger.tagName === 'A' && !event.metaKey && !event.ctrlKey) {
        // Allow navigation, but also open submenu if closed
        if (!item.classList.contains('is-open')) {
          event.preventDefault();
          item.classList.add('is-open');
          if (trigger.getAttribute('aria-expanded') !== null) trigger.setAttribute('aria-expanded', 'true');
          return;
        }
      }
      if (trigger.tagName === 'BUTTON') {
        event.preventDefault();
        var open = !item.classList.contains('is-open');
        item.classList.toggle('is-open', open);
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
      }
    });
  });

  window.addEventListener('resize', applyMode);
  applyMode();
})();
</script>

<main class="ws-main">
<?php foreach ($layout['rows'] as $row): ?>
  <div class="ws-row">
    <?php foreach (($row['columns'] ?? []) as $col):
      $w = (int) ($col['width'] ?? 12);
      $colClass = match ($w) {
          6 => 'ws-col-6',
          4 => 'ws-col-4',
          default => 'ws-col-12',
      };
    ?>
    <div class="ws-col <?= $colClass ?>">
      <?php foreach (($col['blocks'] ?? []) as $block):
        $type = $block['type'] ?? '';
      ?>
      <div class="ws-block">
        <?php switch ($type):
          case 'heading':
            $level = in_array($block['level'] ?? '', ['h1','h2','h3'], true) ? $block['level'] : 'h2';
            echo "<{$level}>" . WebsiteContent::renderHeadingText((string) ($block['text'] ?? '')) . "</{$level}>";
            break;

          case 'text':
            echo '<p>' . WebsiteContent::renderTextHtml((string) ($block['text'] ?? '')) . '</p>';
            break;

          case 'image':
            $src = $block['src'] ?? '';
            $alt = $block['alt'] ?? '';
            if ($src !== '') {
                echo '<img src="' . View::escape($src) . '" alt="' . View::escape($alt) . '">';
            }
            break;

          case 'button':
            $label = $block['label'] ?? $block['text'] ?? 'Klicken';
            $url = $block['url'] ?? '#';
            echo '<a href="' . View::escape($url) . '" class="ws-btn">' . View::escape($label) . '</a>';
            break;

          case 'spacer':
            $h = max(8, min(160, (int) ($block['height'] ?? 40)));
            echo '<div style="height:' . $h . 'px;"></div>';
            break;

          case 'video':
            $vUrl = $block['url'] ?? '';
            $embed = '';
            if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]+)/', $vUrl, $m)) {
                $embed = 'https://www.youtube-nocookie.com/embed/' . $m[1];
            } elseif (preg_match('/vimeo\.com\/(\d+)/', $vUrl, $m)) {
                $embed = 'https://player.vimeo.com/video/' . $m[1];
            }
            if ($embed !== '') {
                echo '<div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:6px;">'
                    . '<iframe src="' . View::escape($embed) . '" style="position:absolute;top:0;left:0;width:100%;height:100%;" frameborder="0" allowfullscreen></iframe></div>';
                if (!empty($block['caption'])) {
                    echo '<p style="font-size:0.9rem;color:#888;margin-top:6px;">' . View::escape($block['caption']) . '</p>';
                }
            }
            break;

          case 'divider':
            $dStyle = View::escape($block['style'] ?? 'solid');
            $dColor = View::escape($block['color'] ?? '#ddd');
            echo '<hr style="border:none;border-top:2px ' . $dStyle . ' ' . $dColor . ';margin:16px 0;">';
            break;

          case 'html':
            echo WebsiteFormRenderer::expandShortcodes((string) ($block['code'] ?? ''), $formFlashById ?? null);
            break;

          case 'contact':
            // Legacy blocks: render matching Formular (auto-created if needed)
            try {
                $legacyForm = WebsiteFormRepository::formForLegacyContact(
                    (string) ($block['email'] ?? ''),
                    (string) ($block['subject'] ?? 'Kontaktanfrage')
                );
                $legacyId = (int) ($legacyForm['id'] ?? 0);
                $flash = is_array($formFlashById[$legacyId] ?? null) ? $formFlashById[$legacyId] : null;
                echo WebsiteFormRenderer::render($legacyForm, $flash);
            } catch (Throwable $e) {
                echo '<p class="ws-form__missing">Kontaktformular derzeit nicht verfügbar.</p>';
            }
            break;

          case 'form':
            $embedFormId = (int) ($block['form_id'] ?? 0);
            $embedForm = $embedFormId > 0 ? WebsiteFormRepository::findPublished($embedFormId) : null;
            if ($embedForm) {
                $flash = is_array($formFlashById[$embedFormId] ?? null) ? $formFlashById[$embedFormId] : null;
                echo WebsiteFormRenderer::render($embedForm, $flash);
            } else {
                echo '<p class="ws-form__missing">Formular nicht verfügbar (Entwurf oder nicht gewählt).</p>';
            }
            break;

          case 'gallery':
            $imgs = $block['images'] ?? [];
            if (!empty($imgs)) {
                echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px;">';
                foreach ($imgs as $img) {
                    echo '<img src="' . View::escape($img['src'] ?? '') . '" alt="' . View::escape($img['alt'] ?? '') . '" style="width:100%;border-radius:6px;object-fit:cover;aspect-ratio:4/3;">';
                }
                echo '</div>';
            }
            break;
        endswitch; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>
</main>

<footer class="ws-footer">
  <div class="ws-footer__inner">
    <?php if (!empty($chrome['footer_text'])): ?>
      <p><?= View::escape($chrome['footer_text']) ?></p>
    <?php else: ?>
      <p>&copy; <?= date('Y') ?> <?= $siteName ?></p>
    <?php endif; ?>
  </div>
</footer>

<?= CookieConsent::bannerHtml() ?>
<style><?= CookieConsent::bannerCss() ?></style>
<script><?= CookieConsent::bannerJs() ?></script>

<?php if (!empty($chrome['footer_js'])): ?>
<?= $chrome['footer_js'] ?>
<?php endif; ?>

<?= SeoMeta::organizationJsonLd() ?>
</body>
</html>
