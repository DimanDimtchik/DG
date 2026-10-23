(function () {
  'use strict';
  var cfg = window.dgCtiPopup;
  if (!cfg || !cfg.enabled) {
    return;
  }
  var seen = {};
  var root = null;

  function ensureRoot() {
    if (root) {
      return root;
    }
    root = document.createElement('div');
    root.id = 'dg-cti-popup-root';
    root.className = 'dg-cti-popup-root';
    document.body.appendChild(root);
    return root;
  }

  function render(list) {
    var el = ensureRoot();
    if (!list || !list.length) {
      el.innerHTML = '';
      el.hidden = true;
      return;
    }
    el.hidden = false;
    el.innerHTML = list
      .map(function (row) {
        var title = row.contact_name
          ? 'Anruf: ' + row.contact_name
          : 'Eingehender Anruf';
        var phone = row.phone_display || row.phone || '';
        var link =
          row.contact_id > 0
            ? '<a class="dg-button dg-button--primary" href="/app?page=kontakte&action=edit&id=' +
              encodeURIComponent(String(row.contact_id)) +
              '">Kontakt öffnen</a>'
            : '<a class="dg-button" href="/app?page=telefon&dial=' +
              encodeURIComponent(row.phone || '') +
              '">Telefon</a>';
        return (
          '<div class="dg-cti-popup" data-call-id="' +
          String(row.id) +
          '"><strong>' +
          escapeHtml(title) +
          '</strong><div class="dg-cti-popup__phone">' +
          escapeHtml(phone) +
          '</div><div class="dg-cti-popup__actions">' +
          link +
          '<button type="button" class="dg-button dg-cti-popup__dismiss">Schließen</button></div></div>'
        );
      })
      .join('');
    el.querySelectorAll('.dg-cti-popup__dismiss').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var box = btn.closest('.dg-cti-popup');
        if (box) {
          seen[box.getAttribute('data-call-id') || ''] = true;
          box.remove();
        }
        if (!el.querySelector('.dg-cti-popup')) {
          el.hidden = true;
        }
      });
    });
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function poll() {
    fetch('/api/cti', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (!data || !data.ok) {
          return;
        }
        var list = (data.ringing || []).filter(function (row) {
          return !seen[String(row.id)];
        });
        render(list);
      })
      .catch(function () {});
  }

  poll();
  setInterval(poll, 2500);
})();
