/**
 * Post-Mail: Kontakt für CRM-Verknüpfung per Name/Firma/E-Mail suchen.
 */
(function () {
  'use strict';

  var cfg = window.dgPostLinkContact || {};
  var api = cfg.api || '/api/post-compose';
  var form = document.getElementById('dg-post-link-contact-form');
  var refInput = document.getElementById('dg-post-link-contact-ref');
  var search = document.getElementById('dg-post-link-contact-search');
  var results = document.getElementById('dg-post-link-contact-results');
  if (!form || !refInput || !search || !results) {
    return;
  }

  var timer = null;
  var pickedLabel = '';

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function hideResults() {
    results.hidden = true;
    results.innerHTML = '';
  }

  function pickContact(id, label, meta) {
    refInput.value = String(id);
    pickedLabel = label || '';
    search.value = pickedLabel + (meta ? ' · ' + meta : '');
    hideResults();
  }

  function searchContacts(q) {
    fetch(api + '?action=contacts&q=' + encodeURIComponent(q), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        var items = (data && data.items) || [];
        if (!items.length) {
          results.innerHTML = '<div class="dg-voucher-contact-results__empty">Keine Kontakte.</div>';
          results.hidden = false;
          return;
        }
        results.innerHTML = items
          .map(function (item) {
            return (
              '<button type="button" class="dg-voucher-contact-results__item" data-id="' +
              item.id +
              '" data-label="' +
              escapeHtml(item.label || '') +
              '" data-meta="' +
              escapeHtml(item.meta || item.email || '') +
              '"><strong>' +
              escapeHtml(item.label || '') +
              '</strong><span>' +
              escapeHtml(item.meta || item.email || '') +
              '</span></button>'
            );
          })
          .join('');
        results.hidden = false;
        results.querySelectorAll('[data-id]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            pickContact(
              parseInt(btn.getAttribute('data-id'), 10) || 0,
              btn.getAttribute('data-label') || '',
              btn.getAttribute('data-meta') || ''
            );
          });
        });
      })
      .catch(function () {});
  }

  search.addEventListener('input', function () {
    var q = search.value.trim();
    if (pickedLabel && q !== pickedLabel && q.indexOf(pickedLabel + ' · ') !== 0) {
      refInput.value = '';
      pickedLabel = '';
    }
    if (timer) {
      clearTimeout(timer);
    }
    if (q.length < 2) {
      hideResults();
      return;
    }
    timer = setTimeout(function () {
      searchContacts(q);
    }, 220);
  });

  form.addEventListener('submit', function () {
    if (refInput.value.trim() !== '') {
      return;
    }
    // Kein Treffer gewählt: Suchfeld als ID/E-Mail/Name an Backend durchreichen.
    refInput.value = search.value.trim();
  });

  document.addEventListener('click', function (ev) {
    if (!results.contains(ev.target) && ev.target !== search) {
      hideResults();
    }
  });
})();
