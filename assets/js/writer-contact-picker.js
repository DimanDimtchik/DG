/**
 * Dokumentenverfasser: Kontakt suchen → Adresse + contact_id setzen.
 */
(function () {
  'use strict';

  var cfg = window.dgWriterContactPicker || {};
  var apiUrl = cfg.apiUrl || '/api/writer-assist';
  var search = document.getElementById('dg-writer-contact-search');
  var results = document.getElementById('dg-writer-contact-results');
  var contactIdInput = document.getElementById('dg-writer-contact-id');
  var recipient = document.getElementById('dg-writer-recipient-lines');
  var actions = document.getElementById('dg-writer-contact-actions');
  var clearBtn = document.getElementById('dg-writer-contact-clear');
  var refillBtn = document.getElementById('dg-writer-contact-refill');
  var openLink = document.getElementById('dg-writer-contact-open');
  if (!search || !results || !contactIdInput || !recipient) {
    return;
  }

  var timer = null;
  var lastAddress = null;

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function showActions(show) {
    if (actions) {
      actions.hidden = !show;
    }
  }

  function setOpenHref(id) {
    if (!openLink) {
      return;
    }
    if (id > 0) {
      openLink.href = '/app?page=kontakte&action=edit&id=' + id;
      openLink.hidden = false;
    } else {
      openLink.href = '#';
      openLink.hidden = true;
    }
  }

  function applyContact(data) {
    var id = parseInt(data.id, 10) || 0;
    var lines = Array.isArray(data.recipient_lines) ? data.recipient_lines : [];
    lastAddress = { id: id, label: data.label || '', lines: lines };
    contactIdInput.value = String(id);
    if (lines.length) {
      recipient.value = lines.join('\n');
      recipient.dispatchEvent(new Event('input', { bubbles: true }));
    }
    search.value = data.label || search.value;
    results.hidden = true;
    results.innerHTML = '';
    showActions(id > 0);
    setOpenHref(id);
  }

  function fetchAddress(id) {
    return fetch(apiUrl + '?action=contact_address&id=' + encodeURIComponent(String(id)), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    }).then(function (res) {
      return res.json();
    });
  }

  function renderItems(items) {
    results.innerHTML = '';
    if (!items || !items.length) {
      results.innerHTML = '<div class="dg-voucher-contact-results__empty">Keine Kontakte gefunden.</div>';
      results.hidden = false;
      return;
    }
    items.forEach(function (item) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'dg-voucher-contact-results__item';
      btn.innerHTML =
        '<strong>' +
        escapeHtml(item.label || '') +
        '</strong>' +
        (item.meta
          ? '<span class="dg-voucher-contact-results__meta">' + escapeHtml(item.meta) + '</span>'
          : '');
      btn.addEventListener('click', function () {
        fetchAddress(item.id).then(function (payload) {
          if (!payload || !payload.ok) {
            window.alert((payload && payload.error) || 'Kontakt konnte nicht geladen werden.');
            return;
          }
          applyContact(payload);
        }).catch(function () {
          window.alert('Kontakt konnte nicht geladen werden.');
        });
      });
      results.appendChild(btn);
    });
    results.hidden = false;
  }

  function runSearch(query) {
    if (!query || query.length < 1) {
      results.hidden = true;
      results.innerHTML = '';
      return;
    }
    fetch(apiUrl + '?action=contacts&q=' + encodeURIComponent(query), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })
      .then(function (res) {
        return res.json();
      })
      .then(function (payload) {
        if (!payload || !payload.ok) {
          renderItems([]);
          return;
        }
        renderItems(payload.items || []);
      })
      .catch(function () {
        renderItems([]);
      });
  }

  search.addEventListener('input', function () {
    var q = search.value.trim();
    window.clearTimeout(timer);
    timer = window.setTimeout(function () {
      runSearch(q);
    }, 220);
  });

  search.addEventListener('focus', function () {
    if (search.value.trim().length >= 1 && results.innerHTML !== '') {
      results.hidden = false;
    }
  });

  document.addEventListener('click', function (ev) {
    if (!results.hidden && !results.contains(ev.target) && ev.target !== search) {
      results.hidden = true;
    }
  });

  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      contactIdInput.value = '0';
      lastAddress = null;
      search.value = '';
      showActions(false);
      setOpenHref(0);
      results.hidden = true;
      results.innerHTML = '';
      search.focus();
    });
  }

  if (refillBtn) {
    refillBtn.addEventListener('click', function () {
      var id = parseInt(contactIdInput.value, 10) || 0;
      if (id < 1) {
        return;
      }
      if (lastAddress && lastAddress.id === id && lastAddress.lines && lastAddress.lines.length) {
        recipient.value = lastAddress.lines.join('\n');
        recipient.dispatchEvent(new Event('input', { bubbles: true }));
        return;
      }
      fetchAddress(id).then(function (payload) {
        if (!payload || !payload.ok) {
          window.alert((payload && payload.error) || 'Adresse konnte nicht geladen werden.');
          return;
        }
        applyContact(payload);
      });
    });
  }

  var initialId = parseInt(contactIdInput.value, 10) || 0;
  if (initialId > 0) {
    showActions(true);
    setOpenHref(initialId);
  }
})();
