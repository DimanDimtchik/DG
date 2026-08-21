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

  <form class="dg-form dg-panel" method="post" action="/app?page=website-menu">
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
    <input type="hidden" name="website_menu_save" value="1">

    <h2>Menüpunkte</h2>
    <div id="dg-website-menu-rows">
      <?php foreach ($items as $i => $item) :
        $children = is_array($item['children'] ?? null) ? $item['children'] : [];
        ?>
        <div class="dg-website-menu-card" data-menu-row>
          <div class="dg-website-menu-card__head">
            <p class="dg-website-menu-card__title">Eintrag <?= (int) $i + 1 ?></p>
            <?php if (!$readOnly) : ?>
              <button type="button" class="dg-button dg-button--small" data-menu-remove>Entfernen</button>
            <?php endif; ?>
          </div>
          <div class="dg-form-grid">
            <label class="dg-field">
              <span>Bezeichnung</span>
              <input name="items[<?= (int) $i ?>][label]" value="<?= View::escape((string) ($item['label'] ?? '')) ?>" placeholder="z. B. Start"<?= $readOnly ? ' readonly' : '' ?>>
            </label>
            <label class="dg-field">
              <span>Link</span>
              <input name="items[<?= (int) $i ?>][url]" value="<?= View::escape((string) ($item['url'] ?? '')) ?>" placeholder="/"<?= $readOnly ? ' readonly' : '' ?>>
              <small class="dg-field-hint">Bei reinen Dropdown-Eltern kann „#“ stehen.</small>
            </label>
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

  function childHtml(parentIndex, childIndex, label, url, authOnly) {
    return '' +
      '<div class="dg-website-menu-card dg-website-menu-card--child" data-menu-child>' +
      '<div class="dg-form-grid">' +
      '<label class="dg-field"><span>Bezeichnung</span>' +
      '<input name="items[' + parentIndex + '][children][' + childIndex + '][label]" value="' + escapeHtml(label || '') + '"></label>' +
      '<label class="dg-field"><span>Link</span>' +
      '<input name="items[' + parentIndex + '][children][' + childIndex + '][url]" value="' + escapeHtml(url || '') + '"></label>' +
      '<label class="dg-field dg-field--checkbox"><span>' +
      '<input type="hidden" name="items[' + parentIndex + '][children][' + childIndex + '][auth_only]" value="0">' +
      '<input type="checkbox" name="items[' + parentIndex + '][children][' + childIndex + '][auth_only]" value="1"' + (authOnly ? ' checked' : '') + '> Nur eingeloggt' +
      '</span></label>' +
      '<div class="dg-field"><button type="button" class="dg-button dg-button--small" data-menu-child-remove>Entfernen</button></div>' +
      '</div></div>';
  }

  function reindex() {
    wrap.querySelectorAll('[data-menu-row]').forEach(function (row, index) {
      var title = row.querySelector('.dg-website-menu-card__title');
      if (title) title.textContent = 'Eintrag ' + (index + 1);

      row.querySelectorAll(':scope > .dg-form-grid input[name*="[label]"]').forEach(function (input) {
        if (input.name.indexOf('[children]') === -1) input.name = 'items[' + index + '][label]';
      });
      row.querySelectorAll(':scope > .dg-form-grid input[name*="[url]"]').forEach(function (input) {
        if (input.name.indexOf('[children]') === -1) input.name = 'items[' + index + '][url]';
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
      });
    });
  }

  function createRow(label, url) {
    var row = document.createElement('div');
    row.className = 'dg-website-menu-card';
    row.setAttribute('data-menu-row', '');
    row.innerHTML =
      '<div class="dg-website-menu-card__head"><p class="dg-website-menu-card__title">Eintrag</p>' +
      '<button type="button" class="dg-button dg-button--small" data-menu-remove>Entfernen</button></div>' +
      '<div class="dg-form-grid">' +
      '<label class="dg-field"><span>Bezeichnung</span><input name="items[0][label]" placeholder="z. B. Kontakt"></label>' +
      '<label class="dg-field"><span>Link</span><input name="items[0][url]" placeholder="/kontakt"><small class="dg-field-hint">Bei reinen Dropdown-Eltern kann „#“ stehen.</small></label>' +
      '<label class="dg-field dg-field--checkbox"><span>' +
      '<input type="hidden" name="items[0][auth_only]" value="0">' +
      '<input type="checkbox" name="items[0][auth_only]" value="1"> Nur für eingeloggte Nutzer</span></label>' +
      '</div>' +
      '<div class="dg-website-menu-children" data-menu-children>' +
      '<p class="dg-field-hint" style="margin-top:12px;">Untermenü</p>' +
      '<div data-menu-child-rows></div>' +
      '<p class="dg-bank-repeater__actions"><button type="button" class="dg-button dg-button--small" data-menu-child-add>Unterpunkt hinzufügen</button></p>' +
      '</div>';
    var inputs = row.querySelectorAll(':scope > .dg-form-grid input[type="text"], :scope > .dg-form-grid input:not([type])');
    // set label/url
    var labelInput = row.querySelector('input[name="items[0][label]"]');
    var urlInput = row.querySelector('input[name="items[0][url]"]');
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
})();
</script>
