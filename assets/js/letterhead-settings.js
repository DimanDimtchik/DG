/**
 * Briefpapier-Einstellungen: Mediathek-Picker für Logo / Hintergrund / Wasserzeichen.
 */
(function () {
  'use strict';

  var root = document.getElementById('dg-letterhead-settings');
  if (!root) {
    return;
  }

  var listUrl = root.getAttribute('data-media-list-url') || '/api/media?action=list';

  function openPicker(opts) {
    opts = opts || {};
    var existing = document.getElementById('dg-letterhead-media-picker');
    if (existing) {
      existing.remove();
    }
    var modal = document.createElement('div');
    modal.id = 'dg-letterhead-media-picker';
    modal.className = 'dg-modal';
    modal.innerHTML =
      '<div class="dg-modal__backdrop" data-close></div>' +
      '<div class="dg-modal__dialog dg-website-media-picker__dialog" role="dialog" aria-modal="true">' +
      '<header class="dg-modal__header"><h2>' +
      escapeHtml(opts.title || 'Bild aus Mediathek') +
      '</h2>' +
      '<button type="button" class="dg-modal__close" data-close aria-label="Schließen">&times;</button></header>' +
      '<div class="dg-modal__body" data-body><p class="dg-field-hint">Laden …</p></div>' +
      '<footer class="dg-modal__footer"><button type="button" class="dg-button" data-close>Abbrechen</button></footer>' +
      '</div>';
    document.body.appendChild(modal);
    modal.addEventListener('click', function (ev) {
      if (ev.target.closest('[data-close]')) {
        modal.remove();
      }
    });

    fetch(listUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) {
        return r.json();
      })
      .then(function (payload) {
        var body = modal.querySelector('[data-body]');
        var items = [];
        if (payload && payload.data && Array.isArray(payload.data.items)) {
          items = payload.data.items;
        } else if (payload && Array.isArray(payload.items)) {
          items = payload.items;
        }
        if (!items.length) {
          body.innerHTML =
            '<p>Keine Bilder in der Mediathek. Bitte unter „Bilder“ hochladen.</p>';
          return;
        }
        var html = '<div class="dg-website-media-picker__grid">';
        items.forEach(function (item) {
          var id = String(item.media_id || item.id || '');
          var url = item.url || item.preview_url || item.public_url || '/app/media?id=' + encodeURIComponent(id);
          var title = item.title || item.original_name || id;
          html +=
            '<button type="button" class="dg-website-media-picker__item" data-id="' +
            escapeAttr(id) +
            '" data-url="' +
            escapeAttr(url) +
            '" title="' +
            escapeAttr(title) +
            '"><img src="' +
            escapeAttr(url) +
            '" alt=""></button>';
        });
        html += '</div>';
        body.innerHTML = html;
        body.addEventListener('click', function (ev) {
          var btn = ev.target.closest('[data-id]');
          if (!btn) {
            return;
          }
          applySelection(opts.inputId, opts.previewId, btn.getAttribute('data-id') || '', btn.getAttribute('data-url') || '');
          modal.remove();
        });
      })
      .catch(function () {
        var body = modal.querySelector('[data-body]');
        if (body) {
          body.innerHTML = '<p>Mediathek konnte nicht geladen werden.</p>';
        }
      });
  }

  function applySelection(inputId, previewId, id, url) {
    var input = document.getElementById(inputId);
    if (input) {
      input.value = id || '';
    }
    var preview = document.getElementById(previewId);
    if (!preview) {
      return;
    }
    if (url) {
      preview.innerHTML = '<img src="' + escapeAttr(url) + '" alt="">';
      preview.hidden = false;
    } else if (preview.getAttribute('data-fallback-url')) {
      preview.innerHTML =
        '<img src="' + escapeAttr(preview.getAttribute('data-fallback-url')) + '" alt=""> ' +
        '<span class="dg-field-hint">CRM-Logo</span>';
      preview.hidden = false;
    } else {
      preview.innerHTML = '<span class="dg-field-hint">Kein Bild gewählt</span>';
    }
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function escapeAttr(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;');
  }

  root.addEventListener('click', function (ev) {
    var openBtn = ev.target.closest('[data-dg-media-pick-open]');
    if (openBtn) {
      ev.preventDefault();
      if (openBtn.disabled) {
        return;
      }
      openPicker({
        title: openBtn.getAttribute('data-title') || 'Bild aus Mediathek',
        inputId: openBtn.getAttribute('data-input') || '',
        previewId: openBtn.getAttribute('data-preview') || '',
      });
      return;
    }
    var clearBtn = ev.target.closest('[data-dg-media-pick-clear]');
    if (clearBtn) {
      ev.preventDefault();
      if (clearBtn.disabled) {
        return;
      }
      var preview = document.getElementById(clearBtn.getAttribute('data-preview') || '');
      var fallback = preview ? preview.getAttribute('data-fallback-url') || '' : '';
      applySelection(
        clearBtn.getAttribute('data-input') || '',
        clearBtn.getAttribute('data-preview') || '',
        '',
        fallback
      );
    }
  });
})();
