<?php
/** @var array{items: list<array<string, mixed>>} $websiteMenuForm */
/** @var list<array{title: string, slug: string, url: string, status: string}> $websiteMenuSuggestions */
/** @var bool $canEdit */
/** @var bool $dbConnected */
/** @var array{type: string, message: string}|null $flash */
$items = $websiteMenuForm['items'] ?? [['label' => '', 'url' => '', 'auth_only' => false, 'children' => []]];
if ($items === []) {
    $items = [['label' => '', 'url' => '', 'auth_only' => false, 'children' => []]];
}
$suggestions = $websiteMenuSuggestions ?? [];
$readOnly = !($canEdit ?? false);
$statusLabels = WebsitePageRepository::statusOptions();
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header">
    <h1 class="dg-page-title">Menü</h1>
    <p class="dg-lead">Navigation der öffentlichen Website. Unterpunkte und „Nur eingeloggt“ steuern, was Besucher sehen.</p>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Zum Speichern ist eine Datenbankverbindung erforderlich.</div>
  <?php endif; ?>

  <?php
    $menuLayout = (string) ($websiteMenuForm['layout'] ?? 'auto');
    $menuBreakpoint = (int) ($websiteMenuForm['breakpoint'] ?? 768);
    $headerIconStyle = WebsiteMenuIcons::headerIconStyleDefaults();
    $layoutOptions = WebsiteSettings::menuLayoutOptions();
  ?>

  <form class="dg-form dg-panel" method="post" action="/app?page=website-menu">
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
    <input type="hidden" name="website_menu_save" value="1">

    <section class="dg-panel dg-panel--nested" aria-labelledby="dg-menu-layout-heading">
      <h2 id="dg-menu-layout-heading">Darstellung</h2>
      <p class="dg-field-hint">Legen Sie fest, ob die Navigation immer horizontal, immer als mobiles Menü oder ab einer Breite umgeschaltet wird.</p>
      <fieldset class="dg-field" style="border:0;padding:0;margin:0;">
        <legend class="dg-sr-only">Menüdarstellung</legend>
        <?php foreach ($layoutOptions as $value => $label) : ?>
          <label class="dg-field dg-field--checkbox">
            <span>
              <input type="radio" name="layout" value="<?= View::escape($value) ?>"<?= $menuLayout === $value ? ' checked' : '' ?><?= $readOnly ? ' disabled' : '' ?>>
              <?= View::escape($label) ?>
            </span>
          </label>
        <?php endforeach; ?>
      </fieldset>
      <label class="dg-field" id="dg-menu-breakpoint-field" style="max-width:220px;margin-top:8px;">
        <span>Umschalten unter Breite (Pixel)</span>
        <input type="number" name="breakpoint" min="320" max="2000" step="1"
               value="<?= (int) $menuBreakpoint ?>"
               <?= $readOnly ? ' readonly' : '' ?>>
        <small class="dg-field-hint">Nur bei „Automatisch“. Beispiel: 768 = Tablet und schmaler.</small>
      </label>
    </section>

    <h2>Menüpunkte</h2>
    <div id="dg-website-menu-rows">
      <?php foreach ($items as $i => $item) :
        $children = is_array($item['children'] ?? null) ? $item['children'] : [];
        ?>
        <div class="dg-website-menu-card" data-menu-row>
          <?php
            $itemIcon = (string) ($item['icon'] ?? 'auto');
            $suggested = WebsiteMenuIcons::suggest((string) ($item['label'] ?? ''), (string) ($item['url'] ?? ''), $children !== []);
            $resolvedPreview = WebsiteMenuIcons::resolve($item);
          ?>
          <div class="dg-website-menu-card__head">
            <p class="dg-website-menu-card__title dg-website-menu-card__title--with-icon">
              <?php if ($resolvedPreview !== '') : ?>
                <span class="dg-website-menu-card__preview-icon"><?= WebsiteMenuIcons::svg($resolvedPreview, 'dg-menu-icon-field__svg', $headerIconStyle) ?></span>
              <?php endif; ?>
              <span>Eintrag <?= (int) $i + 1 ?><?= trim((string) ($item['label'] ?? '')) !== '' ? ': ' . View::escape((string) $item['label']) : '' ?></span>
            </p>
            <?php if (!$readOnly) : ?>
              <button type="button" class="dg-button dg-button--small" data-menu-remove>Entfernen</button>
            <?php endif; ?>
          </div>
          <div class="dg-form-grid">
            <label class="dg-field">
              <span>Bezeichnung</span>
              <input name="items[<?= (int) $i ?>][label]" value="<?= View::escape((string) ($item['label'] ?? '')) ?>" placeholder="z. B. Start"<?= $readOnly ? ' readonly' : '' ?> data-menu-label>
            </label>
            <label class="dg-field">
              <span>Link</span>
              <input name="items[<?= (int) $i ?>][url]" value="<?= View::escape((string) ($item['url'] ?? '')) ?>" placeholder="/"<?= $readOnly ? ' readonly' : '' ?> data-menu-url>
              <small class="dg-field-hint">Bei reinen Dropdown-Eltern kann „#“ stehen.</small>
            </label>
            <div class="dg-field dg-field--wide">
              <span>Icon</span>
              <?php View::partial('partials/website-menu-icon-field', [
                  'name' => 'items[' . (int) $i . '][icon]',
                  'value' => $itemIcon,
                  'readOnly' => $readOnly,
                  'compact' => false,
                  'suggested' => $suggested,
                  'fieldId' => 'dg-menu-icon-' . (int) $i,
                  'websiteMenuIconStyle' => $headerIconStyle,
              ]); ?>
            </div>
            <label class="dg-field dg-field--checkbox">
              <span>
                <input type="hidden" name="items[<?= (int) $i ?>][auth_only]" value="0">
                <input type="checkbox" name="items[<?= (int) $i ?>][auth_only]" value="1"<?= !empty($item['auth_only']) ? ' checked' : '' ?><?= $readOnly ? ' disabled' : '' ?>>
                Nur für eingeloggte Nutzer
              </span>
            </label>
          </div>

          <div class="dg-website-menu-children" data-menu-children>
            <p class="dg-field-hint" style="margin-top:12px;">Untermenü</p>
            <div data-menu-child-rows>
              <?php foreach ($children as $c => $child) : ?>
                <div class="dg-website-menu-card dg-website-menu-card--child" data-menu-child>
                  <div class="dg-form-grid">
                    <label class="dg-field">
                      <span>Bezeichnung</span>
                      <input name="items[<?= (int) $i ?>][children][<?= (int) $c ?>][label]" value="<?= View::escape((string) ($child['label'] ?? '')) ?>"<?= $readOnly ? ' readonly' : '' ?>>
                    </label>
                    <label class="dg-field">
                      <span>Link</span>
                      <input name="items[<?= (int) $i ?>][children][<?= (int) $c ?>][url]" value="<?= View::escape((string) ($child['url'] ?? '')) ?>"<?= $readOnly ? ' readonly' : '' ?>>
                    </label>
                    <label class="dg-field dg-field--wide">
                      <span>Icon</span>
                      <?php
                        $childIcon = (string) ($child['icon'] ?? 'auto');
                        $childSuggested = WebsiteMenuIcons::suggest((string) ($child['label'] ?? ''), (string) ($child['url'] ?? ''), false);
                        $childIconStyle = WebsiteMenuIcons::normalizeSubmenuIconStyle(is_array($child['icon_style'] ?? null) ? $child['icon_style'] : []);
                        View::partial('partials/website-menu-icon-field', [
                            'name' => 'items[' . (int) $i . '][children][' . (int) $c . '][icon]',
                            'value' => $childIcon,
                            'readOnly' => $readOnly,
                            'compact' => true,
                            'suggested' => $childSuggested,
                            'fieldId' => 'dg-menu-icon-' . (int) $i . '-' . (int) $c,
                            'websiteMenuIconStyle' => $childIconStyle,
                        ]);
                      ?>
                    </label>
                    <div class="dg-field dg-field--wide">
                      <?php View::partial('partials/website-menu-submenu-icon-style', [
                          'namePrefix' => 'items[' . (int) $i . '][children][' . (int) $c . '][icon_style]',
                          'style' => is_array($child['icon_style'] ?? null) ? $child['icon_style'] : [],
                          'readOnly' => $readOnly,
                      ]); ?>
                    </div>
                    <label class="dg-field dg-field--checkbox">
                      <span>
                        <input type="hidden" name="items[<?= (int) $i ?>][children][<?= (int) $c ?>][auth_only]" value="0">
                        <input type="checkbox" name="items[<?= (int) $i ?>][children][<?= (int) $c ?>][auth_only]" value="1"<?= !empty($child['auth_only']) ? ' checked' : '' ?><?= $readOnly ? ' disabled' : '' ?>>
                        Nur eingeloggt
                      </span>
                    </label>
                    <?php if (!$readOnly) : ?>
                      <div class="dg-field">
                        <button type="button" class="dg-button dg-button--small" data-menu-child-remove>Entfernen</button>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <?php if (!$readOnly) : ?>
              <p class="dg-bank-repeater__actions">
                <button type="button" class="dg-button dg-button--small" data-menu-child-add>Unterpunkt hinzufügen</button>
              </p>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if (!$readOnly) : ?>
      <?php if ($suggestions !== []) : ?>
        <section class="dg-panel dg-panel--nested" style="margin-top:1.25rem;" aria-label="Vorschläge">
          <h2>Vorschläge</h2>
          <p class="dg-field-hint">Angelegte Seiten, die noch nicht im Menü stehen. Klicken zum Übernehmen.</p>
          <div class="dg-website-menu-suggestions" id="dg-website-menu-suggestions">
            <?php foreach ($suggestions as $suggestion) : ?>
              <button
                type="button"
                class="dg-button"
                data-menu-suggest
                data-label="<?= View::escape($suggestion['title']) ?>"
                data-url="<?= View::escape($suggestion['url']) ?>"
              >
                <?= View::escape($suggestion['title']) ?>
                <span class="dg-field-hint" style="display:block;margin:0;font-weight:400;">
                  <?= View::escape($suggestion['url']) ?>
                  · <?= View::escape($statusLabels[$suggestion['status']] ?? $suggestion['status']) ?>
                </span>
              </button>
            <?php endforeach; ?>
          </div>
        </section>
      <?php else : ?>
        <p class="dg-field-hint" style="margin-top:1rem;">Alle angelegten Seiten sind bereits im Menü.</p>
      <?php endif; ?>

      <p class="dg-bank-repeater__actions">
        <button type="button" class="dg-button" id="dg-website-menu-add">Leeren Menüpunkt hinzufügen</button>
      </p>
      <div class="dg-form-actions">
        <button type="submit" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>>Menü speichern</button>
      </div>
    <?php endif; ?>
  </form>

  <?php if (!$readOnly) : ?>
    <template id="dg-menu-icon-field-template">
      <?php View::partial('partials/website-menu-icon-field', [
          'name' => '__ICON_NAME__',
          'value' => 'auto',
          'readOnly' => false,
          'compact' => false,
          'suggested' => '',
          'fieldId' => '__ICON_ID__',
          'websiteMenuIconStyle' => $headerIconStyle,
      ]); ?>
    </template>
    <template id="dg-menu-icon-field-template-compact">
      <?php View::partial('partials/website-menu-icon-field', [
          'name' => '__ICON_NAME__',
          'value' => 'auto',
          'readOnly' => false,
          'compact' => true,
          'suggested' => '',
          'fieldId' => '__ICON_ID__',
          'websiteMenuIconStyle' => WebsiteMenuIcons::submenuIconStyleDefaults(),
      ]); ?>
    </template>
    <template id="dg-submenu-icon-style-template">
      <?php View::partial('partials/website-menu-submenu-icon-style', [
          'namePrefix' => 'items[__P__][children][__C__][icon_style]',
          'style' => WebsiteMenuIcons::submenuIconStyleDefaults(),
          'readOnly' => false,
      ]); ?>
    </template>
    <script type="application/json" id="dg-menu-icon-catalog"><?= json_encode([
        'featured' => WebsiteMenuIcons::pickerFeaturedIds(),
        'special' => [
            ['id' => 'auto', 'label' => 'Automatisch (Vorschlag)', 'paths' => ''],
            ['id' => '', 'label' => 'Kein Icon', 'paths' => ''],
        ],
        'icons' => WebsiteMenuIcons::pickerCatalog(),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?></script>
  <?php endif; ?>
</div>
<script>
(function () {
  var wrap = document.getElementById('dg-website-menu-rows');
  if (!wrap) return;

  var iconCatalog = null;
  try {
    var catalogEl = document.getElementById('dg-menu-icon-catalog');
    if (catalogEl) iconCatalog = JSON.parse(catalogEl.textContent || '{}');
  } catch (e) {
    iconCatalog = { featured: [], special: [], icons: [] };
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function iconSvgHtml(paths, stroke) {
    if (!paths) return '<span class="dg-menu-icon-grid__none">∅</span>';
    return '<svg class="dg-menu-icon-grid__svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' + (stroke || '1.75') + '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + paths + '</svg>';
  }

  function catalogLookup(id) {
    if (!iconCatalog) return null;
    var specials = iconCatalog.special || [];
    for (var i = 0; i < specials.length; i++) {
      if (specials[i].id === id) return specials[i];
    }
    var icons = iconCatalog.icons || [];
    for (var j = 0; j < icons.length; j++) {
      if (icons[j].id === id) return icons[j];
    }
    return null;
  }

  function featuredIcons() {
    if (!iconCatalog) return [];
    var featured = iconCatalog.featured || [];
    var icons = iconCatalog.icons || [];
    var map = {};
    icons.forEach(function (item) { map[item.id] = item; });
    return featured.map(function (id) { return map[id]; }).filter(Boolean);
  }

  function filterIcons(query) {
    if (!iconCatalog) return [];
    var q = String(query || '').trim().toLowerCase();
    if (!q) {
      return featuredIcons().slice(0, 48);
    }
    var out = [];
    (iconCatalog.icons || []).forEach(function (item) {
      var hay = (item.id + ' ' + item.label + ' ' + (item.tags || []).join(' ')).toLowerCase();
      if (hay.indexOf(q) !== -1) out.push(item);
    });
    return out.slice(0, 80);
  }

  function renderIconGrid(field, query) {
    var grid = field.querySelector('[data-menu-icon-grid]');
    if (!grid || !iconCatalog) return;
    var input = field.querySelector('[data-menu-icon-input]');
    var current = input ? input.value : 'auto';
    var stroke = field.getAttribute('data-stroke') || '1.75';
    var suggested = field.getAttribute('data-suggested') || '';
    var items = [];
    var q = String(query || '').trim();
    if (!q) {
      items = (iconCatalog.special || []).slice();
      featuredIcons().forEach(function (item) {
        if (!items.some(function (x) { return x.id === item.id; })) items.push(item);
      });
    } else {
      items = filterIcons(q);
    }
    var html = items.map(function (item) {
      var pickId = item.id;
      var previewPaths = item.paths;
      if (pickId === 'auto' && suggested) {
        var sug = catalogLookup(suggested);
        previewPaths = sug ? sug.paths : '';
      }
      var selected = current === pickId;
      return '<button type="button" class="dg-menu-icon-grid__item' + (selected ? ' is-selected' : '') + '" role="option" aria-selected="' + (selected ? 'true' : 'false') + '" data-menu-icon-pick data-value="' + escapeHtml(pickId) + '" data-label="' + escapeHtml(item.label) + '" data-paths="' + escapeHtml(item.paths || '') + '" title="' + escapeHtml(item.label) + '">' +
        iconSvgHtml(previewPaths, stroke) +
        '<span class="dg-menu-icon-grid__text">' + escapeHtml(item.label) + '</span></button>';
    }).join('');
    grid.innerHTML = html;
    var hint = field.querySelector('[data-menu-icon-hint]');
    if (hint) {
      if (q && items.length === 0) {
        hint.textContent = 'Kein Icon gefunden.';
        hint.hidden = false;
      } else if (q && items.length >= 80) {
        hint.textContent = 'Mehr Treffer — Suche verfeinern.';
        hint.hidden = false;
      } else {
        hint.hidden = true;
      }
    }
  }

  function ensureIconGrid(field) {
    if (!field || field.getAttribute('data-icon-grid-ready') === '1') return;
    renderIconGrid(field, '');
    field.setAttribute('data-icon-grid-ready', '1');
  }

  function iconFieldHtml(name, fieldId, compact) {
    var tpl = document.getElementById(compact ? 'dg-menu-icon-field-template-compact' : 'dg-menu-icon-field-template');
    if (!tpl || !('content' in tpl)) return '';
    var node = tpl.content.firstElementChild.cloneNode(true);
    node.querySelectorAll('[name]').forEach(function (el) {
      el.name = name;
      if (el.id) el.id = el.id.replace('__ICON_ID__', fieldId);
    });
    node.querySelectorAll('[id]').forEach(function (el) {
      el.id = (el.id || '').replace(/__ICON_ID__/g, fieldId);
    });
    node.querySelectorAll('[aria-controls]').forEach(function (el) {
      var ac = el.getAttribute('aria-controls');
      if (ac) el.setAttribute('aria-controls', ac.replace(/__ICON_ID__/g, fieldId));
    });
    var wrapper = document.createElement('div');
    wrapper.appendChild(node);
    return wrapper.innerHTML;
  }

  function submenuIconStyleHtml(parentIndex, childIndex) {
    var tpl = document.getElementById('dg-submenu-icon-style-template');
    if (!tpl || !('content' in tpl)) return '';
    var node = tpl.content.firstElementChild.cloneNode(true);
    var prefix = 'items[' + parentIndex + '][children][' + childIndex + '][icon_style]';
    node.querySelectorAll('[name]').forEach(function (el) {
      el.name = el.name.replace('__P__', String(parentIndex)).replace('__C__', String(childIndex));
    });
    var colorSelect = node.querySelector('.dg-submenu-icon-color');
    if (colorSelect) {
      var customId = 'dg-submenu-icon-color-' + parentIndex + '-' + childIndex;
      colorSelect.setAttribute('data-custom-target', customId);
      var customLabel = node.querySelector('[id^="dg-submenu-icon-color-"]');
      if (customLabel) customLabel.id = customId;
    }
    var hoverSelect = node.querySelector('.dg-submenu-icon-hover');
    if (hoverSelect) {
      var hoverId = 'dg-submenu-icon-hover-color-' + parentIndex + '-' + childIndex;
      hoverSelect.setAttribute('data-custom-target', hoverId);
      var hoverField = node.querySelector('[id^="dg-submenu-icon-hover-color-"]');
      if (hoverField) hoverField.id = hoverId;
    }
    var wrapper = document.createElement('div');
    wrapper.className = 'dg-field dg-field--wide';
    wrapper.appendChild(node);
    return wrapper.outerHTML;
  }

  function childHtml(parentIndex, childIndex, label, url, authOnly) {
    var iconName = 'items[' + parentIndex + '][children][' + childIndex + '][icon]';
    var iconId = 'dg-menu-icon-' + parentIndex + '-' + childIndex;
    return '' +
      '<div class="dg-website-menu-card dg-website-menu-card--child" data-menu-child>' +
      '<div class="dg-form-grid">' +
      '<label class="dg-field"><span>Bezeichnung</span>' +
      '<input name="items[' + parentIndex + '][children][' + childIndex + '][label]" value="' + escapeHtml(label || '') + '"></label>' +
      '<label class="dg-field"><span>Link</span>' +
      '<input name="items[' + parentIndex + '][children][' + childIndex + '][url]" value="' + escapeHtml(url || '') + '"></label>' +
      '<div class="dg-field dg-field--wide"><span>Icon</span>' + iconFieldHtml(iconName, iconId, true) + '</div>' +
      submenuIconStyleHtml(parentIndex, childIndex) +
      '<label class="dg-field dg-field--checkbox"><span>' +
      '<input type="hidden" name="items[' + parentIndex + '][children][' + childIndex + '][auth_only]" value="0">' +
      '<input type="checkbox" name="items[' + parentIndex + '][children][' + childIndex + '][auth_only]" value="1"' + (authOnly ? ' checked' : '') + '> Nur eingeloggt' +
      '</span></label>' +
      '<div class="dg-field"><button type="button" class="dg-button dg-button--small" data-menu-child-remove>Entfernen</button></div>' +
      '</div></div>';
  }

  function reindex() {
    wrap.querySelectorAll('[data-menu-row]').forEach(function (row, index) {
      var titleSpan = row.querySelector('.dg-website-menu-card__title span:last-child');
      var labelInput = row.querySelector(':scope > .dg-form-grid input[data-menu-label], :scope > .dg-form-grid input[name*="[label]"]');
      var labelVal = labelInput ? labelInput.value.trim() : '';
      if (titleSpan) {
        titleSpan.textContent = 'Eintrag ' + (index + 1) + (labelVal ? ': ' + labelVal : '');
      }

      row.querySelectorAll(':scope > .dg-form-grid input[name*="[label]"]').forEach(function (input) {
        if (input.name.indexOf('[children]') === -1) input.name = 'items[' + index + '][label]';
      });
      row.querySelectorAll(':scope > .dg-form-grid input[name*="[url]"]').forEach(function (input) {
        if (input.name.indexOf('[children]') === -1) input.name = 'items[' + index + '][url]';
      });
      row.querySelectorAll(':scope > .dg-form-grid input[data-menu-icon-input]').forEach(function (input) {
        input.name = 'items[' + index + '][icon]';
      });
      row.querySelectorAll(':scope > .dg-form-grid input[name*="[auth_only]"]').forEach(function (input) {
        if (input.name.indexOf('[children]') === -1) input.name = 'items[' + index + '][auth_only]';
      });

      var childWrap = row.querySelector('[data-menu-child-rows]');
      if (!childWrap) return;
      childWrap.querySelectorAll('[data-menu-child]').forEach(function (child, cIndex) {
        child.querySelectorAll('input').forEach(function (input) {
          if (/\[label\]$/.test(input.name)) input.name = 'items[' + index + '][children][' + cIndex + '][label]';
          if (/\[url\]$/.test(input.name)) input.name = 'items[' + index + '][children][' + cIndex + '][url]';
          if (/\[auth_only\]$/.test(input.name)) input.name = 'items[' + index + '][children][' + cIndex + '][auth_only]';
        });
        child.querySelectorAll('input[data-menu-icon-input]').forEach(function (input) {
          input.name = 'items[' + index + '][children][' + cIndex + '][icon]';
        });
        child.querySelectorAll('.dg-submenu-icon-style [name]').forEach(function (input) {
          var field = (input.name || '').match(/\[icon_style\]\[(\w+)\]$/);
          if (field) {
            input.name = 'items[' + index + '][children][' + cIndex + '][icon_style][' + field[1] + ']';
          }
        });
        var colorSelect = child.querySelector('.dg-submenu-icon-color');
        if (colorSelect) {
          var targetId = 'dg-submenu-icon-color-' + index + '-' + cIndex;
          colorSelect.setAttribute('data-custom-target', targetId);
          var customField = child.querySelector('[id^="dg-submenu-icon-color-"]');
          if (customField) customField.id = targetId;
        }
        var hoverSelect = child.querySelector('.dg-submenu-icon-hover');
        if (hoverSelect) {
          var hoverTargetId = 'dg-submenu-icon-hover-color-' + index + '-' + cIndex;
          hoverSelect.setAttribute('data-custom-target', hoverTargetId);
          var hoverField = child.querySelector('[id^="dg-submenu-icon-hover-color-"]');
          if (hoverField) hoverField.id = hoverTargetId;
        }
      });
    });
  }

  function createRow(label, url) {
    var index = wrap.querySelectorAll('[data-menu-row]').length;
    var iconName = 'items[' + index + '][icon]';
    var iconId = 'dg-menu-icon-new-' + Date.now();
    var row = document.createElement('div');
    row.className = 'dg-website-menu-card';
    row.setAttribute('data-menu-row', '');
    row.innerHTML =
      '<div class="dg-website-menu-card__head"><p class="dg-website-menu-card__title dg-website-menu-card__title--with-icon"><span>Eintrag</span></p>' +
      '<button type="button" class="dg-button dg-button--small" data-menu-remove>Entfernen</button></div>' +
      '<div class="dg-form-grid">' +
      '<label class="dg-field"><span>Bezeichnung</span><input name="items[0][label]" placeholder="z. B. Kontakt" data-menu-label></label>' +
      '<label class="dg-field"><span>Link</span><input name="items[0][url]" placeholder="/kontakt" data-menu-url><small class="dg-field-hint">Bei reinen Dropdown-Eltern kann „#“ stehen.</small></label>' +
      '<div class="dg-field dg-field--wide"><span>Icon</span>' + iconFieldHtml(iconName, iconId, false) + '</div>' +
      '<label class="dg-field dg-field--checkbox"><span>' +
      '<input type="hidden" name="items[0][auth_only]" value="0">' +
      '<input type="checkbox" name="items[0][auth_only]" value="1"> Nur für eingeloggte Nutzer</span></label>' +
      '</div>' +
      '<div class="dg-website-menu-children" data-menu-children>' +
      '<p class="dg-field-hint" style="margin-top:12px;">Untermenü</p>' +
      '<div data-menu-child-rows></div>' +
      '<p class="dg-bank-repeater__actions"><button type="button" class="dg-button dg-button--small" data-menu-child-add>Unterpunkt hinzufügen</button></p>' +
      '</div>';
    var labelInput = row.querySelector('input[data-menu-label]');
    var urlInput = row.querySelector('input[data-menu-url]');
    if (labelInput) labelInput.value = label || '';
    if (urlInput) urlInput.value = url || '';
    wrap.appendChild(row);
    reindex();
    return row;
  }

  function usedUrls() {
    var urls = {};
    wrap.querySelectorAll('input').forEach(function (input) {
      if (!/\[url\]$/.test(input.name)) return;
      var value = (input.value || '').trim().replace(/\/+$/, '') || '/';
      if (value === '#') return;
      urls[value] = true;
    });
    return urls;
  }

  function refreshSuggestions() {
    var box = document.getElementById('dg-website-menu-suggestions');
    if (!box) return;
    var used = usedUrls();
    box.querySelectorAll('[data-menu-suggest]').forEach(function (button) {
      var url = (button.getAttribute('data-url') || '').trim().replace(/\/+$/, '') || '/';
      button.hidden = Boolean(used[url]);
    });
  }

  document.getElementById('dg-website-menu-add')?.addEventListener('click', function () {
    createRow('', '');
    refreshSuggestions();
  });

  document.getElementById('dg-website-menu-suggestions')?.addEventListener('click', function (event) {
    var button = event.target.closest('[data-menu-suggest]');
    if (!button) return;
    createRow(button.getAttribute('data-label') || '', button.getAttribute('data-url') || '');
    button.hidden = true;
  });

  wrap.addEventListener('click', function (event) {
    var pick = event.target.closest('[data-menu-icon-pick]');
    if (pick) {
      var field = pick.closest('[data-menu-icon-field]');
      if (!field) return;
      var input = field.querySelector('[data-menu-icon-input]');
      var labelEl = field.querySelector('[data-menu-icon-label]');
      var preview = field.querySelector('[data-menu-icon-preview]');
      var value = pick.getAttribute('data-value') || 'auto';
      var text = pick.getAttribute('data-label') || '';
      var paths = pick.getAttribute('data-paths') || '';
      var stroke = field.getAttribute('data-stroke') || '1.75';
      if (input) input.value = value;
      if (labelEl) labelEl.textContent = text;
      if (preview) {
        if (value === 'auto') {
          var suggested = field.getAttribute('data-suggested') || '';
          var sugItem = suggested ? catalogLookup(suggested) : null;
          preview.innerHTML = sugItem && sugItem.paths ? iconSvgHtml(sugItem.paths, stroke) : '<span class="dg-menu-icon-field__empty">—</span>';
        } else if (value === '' || !paths) {
          preview.innerHTML = '<span class="dg-menu-icon-field__empty">—</span>';
        } else {
          preview.innerHTML = iconSvgHtml(paths, stroke);
        }
      }
      field.querySelectorAll('.dg-menu-icon-grid__item').forEach(function (btn) {
        var selected = btn.getAttribute('data-value') === value;
        btn.classList.toggle('is-selected', selected);
        btn.setAttribute('aria-selected', selected ? 'true' : 'false');
      });
      var panel = field.querySelector('[data-menu-icon-panel]');
      if (panel) panel.hidden = true;
      var trigger = field.querySelector('[data-menu-icon-open]');
      if (trigger) trigger.setAttribute('aria-expanded', 'false');
      event.preventDefault();
      return;
    }

    var openBtn = event.target.closest('[data-menu-icon-open]');
    if (openBtn) {
      var openField = openBtn.closest('[data-menu-icon-field]');
      var openPanel = openField && openField.querySelector('[data-menu-icon-panel]');
      if (!openPanel) return;
      ensureIconGrid(openField);
      var willOpen = openPanel.hidden;
      document.querySelectorAll('[data-menu-icon-panel]').forEach(function (p) { p.hidden = true; });
      document.querySelectorAll('[data-menu-icon-open]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
      if (willOpen) {
        openPanel.hidden = false;
        openBtn.setAttribute('aria-expanded', 'true');
        var search = openField.querySelector('[data-menu-icon-search]');
        if (search) {
          search.value = '';
          renderIconGrid(openField, '');
          search.focus();
        }
      }
      event.preventDefault();
      return;
    }

    if (!event.target.closest('[data-menu-icon-field]')) {
      document.querySelectorAll('[data-menu-icon-panel]').forEach(function (p) { p.hidden = true; });
      document.querySelectorAll('[data-menu-icon-open]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
    }

    var childAdd = event.target.closest('[data-menu-child-add]');
    if (childAdd) {
      var row = childAdd.closest('[data-menu-row]');
      var childWrap = row && row.querySelector('[data-menu-child-rows]');
      if (!childWrap) return;
      var parentIndex = Array.prototype.indexOf.call(wrap.querySelectorAll('[data-menu-row]'), row);
      var childIndex = childWrap.querySelectorAll('[data-menu-child]').length;
      childWrap.insertAdjacentHTML('beforeend', childHtml(parentIndex, childIndex, '', '', false));
      reindex();
      return;
    }

    var childRemove = event.target.closest('[data-menu-child-remove]');
    if (childRemove) {
      var child = childRemove.closest('[data-menu-child]');
      if (child) child.remove();
      reindex();
      refreshSuggestions();
      return;
    }

    var button = event.target.closest('[data-menu-remove]');
    if (!button) return;
    var row = button.closest('[data-menu-row]');
    if (!row) return;
    if (wrap.querySelectorAll('[data-menu-row]').length < 2) {
      row.querySelectorAll('input[type="text"], input:not([type]), input[type="checkbox"]').forEach(function (input) {
        if (input.type === 'checkbox') input.checked = false;
        else if (input.type !== 'hidden') input.value = '';
      });
      var childRows = row.querySelector('[data-menu-child-rows]');
      if (childRows) childRows.innerHTML = '';
      refreshSuggestions();
      return;
    }
    row.remove();
    reindex();
    refreshSuggestions();
  });

  wrap.addEventListener('input', function (event) {
    if (event.target && /\[url\]$/.test(event.target.name || '')) {
      refreshSuggestions();
    }
    var search = event.target.closest('[data-menu-icon-search]');
    if (search) {
      var searchField = search.closest('[data-menu-icon-field]');
      if (searchField) renderIconGrid(searchField, search.value);
    }
  });

  reindex();
  refreshSuggestions();

  var breakpointField = document.getElementById('dg-menu-breakpoint-field');
  function syncBreakpointVisibility() {
    if (!breakpointField) return;
    var selected = document.querySelector('input[name="layout"]:checked');
    var isAuto = selected && selected.value === 'auto';
    breakpointField.hidden = !isAuto;
  }
  document.querySelectorAll('input[name="layout"]').forEach(function (radio) {
    radio.addEventListener('change', syncBreakpointVisibility);
  });
  syncBreakpointVisibility();

  wrap.addEventListener('change', function (event) {
    var select = event.target.closest('.dg-submenu-icon-color, .dg-submenu-icon-hover');
    if (!select) return;
    var targetId = select.getAttribute('data-custom-target');
    var customField = targetId ? document.getElementById(targetId) : null;
    if (!customField) return;
    if (select.classList.contains('dg-submenu-icon-color')) {
      customField.hidden = select.value !== 'custom';
    } else {
      customField.hidden = select.value !== 'custom';
    }
  });
})();
</script>
