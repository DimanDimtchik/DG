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
  <?php endif; ?>
</div>
<script>
(function () {
  var wrap = document.getElementById('dg-website-menu-rows');
  if (!wrap) return;

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
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
    var customField = node.querySelector('[data-custom-target]');
    if (customField) {
      var customId = 'dg-submenu-icon-color-' + parentIndex + '-' + childIndex;
      customField.setAttribute('data-custom-target', customId);
      var customLabel = node.querySelector('[id^="dg-submenu-icon-color-"]');
      if (customLabel) customLabel.id = customId;
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
      if (input) input.value = value;
      if (labelEl) labelEl.textContent = text;
      var svg = pick.querySelector('svg');
      if (preview) {
        preview.innerHTML = svg ? svg.outerHTML : '<span class="dg-menu-icon-field__empty">—</span>';
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
      var willOpen = openPanel.hidden;
      document.querySelectorAll('[data-menu-icon-panel]').forEach(function (p) { p.hidden = true; });
      document.querySelectorAll('[data-menu-icon-open]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
      if (willOpen) {
        openPanel.hidden = false;
        openBtn.setAttribute('aria-expanded', 'true');
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
    var select = event.target.closest('.dg-submenu-icon-color');
    if (!select) return;
    var targetId = select.getAttribute('data-custom-target');
    var customField = targetId ? document.getElementById(targetId) : null;
    if (customField) customField.hidden = select.value !== 'custom';
  });
})();
</script>
