/**
 * Shared Lucide icon picker for CRM (website menu + QR/Flyer).
 * Catalog: <script type="application/json" id="dg-menu-icon-catalog">…</script>
 */
(function () {
  'use strict';

  var catalog = null;
  var bound = false;

  function loadCatalog() {
    if (catalog) {
      return catalog;
    }
    try {
      var el = document.getElementById('dg-menu-icon-catalog');
      catalog = el ? JSON.parse(el.textContent || '{}') : { featured: [], special: [], icons: [] };
    } catch (e) {
      catalog = { featured: [], special: [], icons: [] };
    }
    return catalog;
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function iconSvgHtml(paths, stroke) {
    if (!paths) {
      return '<span class="dg-menu-icon-grid__none">∅</span>';
    }
    return (
      '<svg class="dg-menu-icon-grid__svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' +
      (stroke || '1.75') +
      '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      paths +
      '</svg>'
    );
  }

  function catalogLookup(id) {
    var cat = loadCatalog();
    var specials = cat.special || [];
    for (var i = 0; i < specials.length; i++) {
      if (specials[i].id === id) {
        return specials[i];
      }
    }
    var icons = cat.icons || [];
    for (var j = 0; j < icons.length; j++) {
      if (icons[j].id === id) {
        return icons[j];
      }
    }
    return null;
  }

  function featuredIcons() {
    var cat = loadCatalog();
    var featured = cat.featured || [];
    var icons = cat.icons || [];
    var map = {};
    icons.forEach(function (item) {
      map[item.id] = item;
    });
    return featured
      .map(function (id) {
        return map[id];
      })
      .filter(Boolean);
  }

  function filterIcons(query) {
    var cat = loadCatalog();
    var q = String(query || '').trim().toLowerCase();
    if (!q) {
      return featuredIcons().slice(0, 48);
    }
    var out = [];
    (cat.icons || []).forEach(function (item) {
      var hay = (item.id + ' ' + item.label + ' ' + (item.tags || []).join(' ')).toLowerCase();
      if (hay.indexOf(q) !== -1) {
        out.push(item);
      }
    });
    return out.slice(0, 80);
  }

  function renderIconGrid(field, query) {
    var grid = field.querySelector('[data-menu-icon-grid]');
    var cat = loadCatalog();
    if (!grid || !cat) {
      return;
    }
    var input = field.querySelector('[data-menu-icon-input]');
    var current = input ? input.value : 'auto';
    var stroke = field.getAttribute('data-stroke') || '1.75';
    var suggested = field.getAttribute('data-suggested') || '';
    var items = [];
    var q = String(query || '').trim();
    if (!q) {
      items = (cat.special || []).slice();
      featuredIcons().forEach(function (item) {
        if (
          !items.some(function (x) {
            return x.id === item.id;
          })
        ) {
          items.push(item);
        }
      });
    } else {
      items = filterIcons(q);
    }
    grid.innerHTML = items
      .map(function (item) {
        var pickId = item.id;
        var previewPaths = item.paths;
        if (pickId === 'auto' && suggested) {
          var sug = catalogLookup(suggested);
          previewPaths = sug ? sug.paths : '';
        }
        var selected = current === pickId;
        return (
          '<button type="button" class="dg-menu-icon-grid__item' +
          (selected ? ' is-selected' : '') +
          '" role="option" aria-selected="' +
          (selected ? 'true' : 'false') +
          '" data-menu-icon-pick data-value="' +
          escapeHtml(pickId) +
          '" data-label="' +
          escapeHtml(item.label) +
          '" data-paths="' +
          escapeHtml(item.paths || '') +
          '" title="' +
          escapeHtml(item.label) +
          '">' +
          iconSvgHtml(previewPaths, stroke) +
          '<span class="dg-menu-icon-grid__text">' +
          escapeHtml(item.label) +
          '</span></button>'
        );
      })
      .join('');
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
    if (!field || field.getAttribute('data-icon-grid-ready') === '1') {
      return;
    }
    renderIconGrid(field, '');
    field.setAttribute('data-icon-grid-ready', '1');
  }

  function applyPick(field, pick) {
    var input = field.querySelector('[data-menu-icon-input]');
    var labelEl = field.querySelector('[data-menu-icon-label]');
    var preview = field.querySelector('[data-menu-icon-preview]');
    var value = pick.getAttribute('data-value') || 'auto';
    var text = pick.getAttribute('data-label') || '';
    var paths = pick.getAttribute('data-paths') || '';
    var stroke = field.getAttribute('data-stroke') || '1.75';
    if (input) {
      input.value = value;
    }
    if (labelEl) {
      labelEl.textContent = text;
    }
    if (preview) {
      if (value === 'auto') {
        var suggested = field.getAttribute('data-suggested') || '';
        var sugItem = suggested ? catalogLookup(suggested) : null;
        preview.innerHTML =
          sugItem && sugItem.paths
            ? iconSvgHtml(sugItem.paths, stroke)
            : '<span class="dg-menu-icon-field__empty">—</span>';
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
    if (panel) {
      panel.hidden = true;
    }
    var trigger = field.querySelector('[data-menu-icon-open]');
    if (trigger) {
      trigger.setAttribute('aria-expanded', 'false');
    }
    field.dispatchEvent(
      new CustomEvent('dg-menu-icon-picked', {
        bubbles: true,
        detail: { id: value, label: text, paths: paths },
      })
    );
  }

  function onDocumentClick(event) {
    var pick = event.target.closest('[data-menu-icon-pick]');
    if (pick) {
      var field = pick.closest('[data-menu-icon-field]');
      if (!field) {
        return;
      }
      applyPick(field, pick);
      event.preventDefault();
      return;
    }

    var openBtn = event.target.closest('[data-menu-icon-open]');
    if (openBtn) {
      var openField = openBtn.closest('[data-menu-icon-field]');
      var openPanel = openField && openField.querySelector('[data-menu-icon-panel]');
      if (!openPanel) {
        return;
      }
      ensureIconGrid(openField);
      var willOpen = openPanel.hidden;
      document.querySelectorAll('[data-menu-icon-panel]').forEach(function (p) {
        p.hidden = true;
      });
      document.querySelectorAll('[data-menu-icon-open]').forEach(function (b) {
        b.setAttribute('aria-expanded', 'false');
      });
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
      document.querySelectorAll('[data-menu-icon-panel]').forEach(function (p) {
        p.hidden = true;
      });
      document.querySelectorAll('[data-menu-icon-open]').forEach(function (b) {
        b.setAttribute('aria-expanded', 'false');
      });
    }
  }

  function onDocumentInput(event) {
    var search = event.target.closest('[data-menu-icon-search]');
    if (!search) {
      return;
    }
    var searchField = search.closest('[data-menu-icon-field]');
    if (searchField) {
      renderIconGrid(searchField, search.value);
    }
  }

  function bind() {
    if (bound) {
      return;
    }
    bound = true;
    loadCatalog();
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('input', onDocumentInput);
  }

  /**
   * Rasterize Lucide paths to PNG data URL (for QR center).
   * @param {string} paths
   * @param {string} strokeColor
   * @returns {Promise<string>}
   */
  function pathsToDataUrl(paths, strokeColor) {
    return new Promise(function (resolve) {
      if (!paths) {
        resolve('');
        return;
      }
      var color = (strokeColor || '#1a1a1a').replace(/[^#a-fA-F0-9]/g, '');
      if (!/^#[0-9a-fA-F]{6}$/.test(color)) {
        color = '#1a1a1a';
      }
      var svg =
        '<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 24 24" fill="none" stroke="' +
        color +
        '" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' +
        paths +
        '</svg>';
      var url = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
      var img = new Image();
      img.onload = function () {
        var canvas = document.createElement('canvas');
        canvas.width = 256;
        canvas.height = 256;
        var ctx = canvas.getContext('2d');
        if (!ctx) {
          resolve('');
          return;
        }
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, 256, 256);
        var pad = 28;
        ctx.drawImage(img, pad, pad, 256 - pad * 2, 256 - pad * 2);
        resolve(canvas.toDataURL('image/png'));
      };
      img.onerror = function () {
        resolve('');
      };
      img.src = url;
    });
  }

  window.DgMenuIconPicker = {
    bind: bind,
    loadCatalog: loadCatalog,
    lookup: catalogLookup,
    paths: function (id) {
      var item = catalogLookup(id);
      return item && item.paths ? item.paths : '';
    },
    svgHtml: iconSvgHtml,
    pathsToDataUrl: pathsToDataUrl,
    renderIconGrid: renderIconGrid,
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bind);
  } else {
    bind();
  }
})();
