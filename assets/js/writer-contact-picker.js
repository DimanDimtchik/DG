/**
 * Dokumentenverfasser: Kontakt wählen / öffnen → Adresse + Anrede.
 */
(function () {
  'use strict';

  var cfg = window.dgWriterContactPicker || {};
  var apiUrl = cfg.apiUrl || '/api/writer-assist';
  var search = document.getElementById('dg-writer-contact-search');
  var results = document.getElementById('dg-writer-contact-results');
  var contactIdInput = document.getElementById('dg-writer-contact-id');
  var recipient = document.getElementById('dg-writer-recipient-lines');
  var clearBtn = document.getElementById('dg-writer-contact-clear');
  var refillBtn = document.getElementById('dg-writer-contact-refill');
  var openBtn = document.getElementById('dg-writer-contact-open');
  var greetingSelect = document.getElementById('dg-writer-greeting-presets');
  var greetingInput = document.getElementById('dg-writer-greeting');
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

  function currentContactId() {
    return parseInt(contactIdInput.value, 10) || 0;
  }

  function writerReturnUrl() {
    var idInput = document.querySelector('#dg-writer-form input[name="id"]');
    var docId = idInput ? parseInt(idInput.value, 10) || 0 : parseInt(String(cfg.documentId || 0), 10) || 0;
    if (docId > 0) {
      return '/app?page=dokumentenverfasser-form&action=edit&id=' + docId;
    }
    return '/app?page=dokumentenverfasser-form&action=new';
  }

  function syncOpenButton() {
    if (!openBtn) {
      return;
    }
    var id = currentContactId();
    openBtn.setAttribute('data-has-contact', id > 0 ? '1' : '0');
    openBtn.setAttribute('data-contact-id', String(id));
    openBtn.textContent = id > 0 ? 'Kontakt öffnen' : 'Kontakt wählen';
    if (clearBtn) {
      clearBtn.hidden = id < 1;
    }
    if (refillBtn) {
      refillBtn.hidden = id < 1;
    }
  }

  function fillGreetingPresets(list, defaultText) {
    if (!greetingSelect || !list || !list.length) {
      return;
    }
    var html = '<option value="">Vorlage übernehmen …</option>';
    list.forEach(function (item) {
      html +=
        '<option value="' +
        escapeHtml(item.text || '') +
        '" data-text="' +
        escapeHtml(item.text || '') +
        '">' +
        escapeHtml(item.label || '') +
        '</option>';
    });
    greetingSelect.innerHTML = html;
    if (greetingInput && defaultText) {
      var current = (greetingInput.value || '').trim();
      var looksPlaceholder =
        current === '' ||
        current.indexOf('…') !== -1 ||
        current === 'Sehr geehrte/r ' ||
        current === 'Sehr geehrte Damen und Herren,';
      if (looksPlaceholder) {
        greetingInput.value = defaultText;
      }
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
    syncOpenButton();
    if (data.greetings) {
      fillGreetingPresets(data.greetings, data.default_greeting || '');
    }
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
        fetchAddress(item.id)
          .then(function (payload) {
            if (!payload || !payload.ok) {
              window.alert((payload && payload.error) || 'Kontakt konnte nicht geladen werden.');
              return;
            }
            applyContact(payload);
          })
          .catch(function () {
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

  function browseContacts() {
    results.innerHTML = '<div class="dg-voucher-contact-results__empty">Kontakte werden geladen …</div>';
    results.hidden = false;
    search.focus();
    fetch(apiUrl + '?action=contacts&browse=1', {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })
      .then(function (res) {
        return res.json();
      })
      .then(function (payload) {
        renderItems((payload && payload.items) || []);
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
    if (
      !results.hidden &&
      !results.contains(ev.target) &&
      ev.target !== search &&
      ev.target !== openBtn &&
      !(openBtn && openBtn.contains(ev.target))
    ) {
      results.hidden = true;
    }
  });

  if (openBtn) {
    openBtn.addEventListener('click', function () {
      var id = currentContactId();
      if (id > 0) {
        var ret = encodeURIComponent(writerReturnUrl());
        window.location.href =
          '/app?page=kontakte&action=edit&id=' + id + '&return_to=' + ret;
        return;
      }
      browseContacts();
    });
  }

  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      contactIdInput.value = '0';
      lastAddress = null;
      search.value = '';
      syncOpenButton();
      results.hidden = true;
      results.innerHTML = '';
      search.focus();
    });
  }

  if (refillBtn) {
    refillBtn.addEventListener('click', function () {
      var id = currentContactId();
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

  syncOpenButton();
  var initialId = currentContactId();
  if (initialId > 0) {
    fetchAddress(initialId).then(function (payload) {
      if (payload && payload.ok && payload.greetings) {
        fillGreetingPresets(payload.greetings, null);
      }
    }).catch(function () {});
  }
})();
