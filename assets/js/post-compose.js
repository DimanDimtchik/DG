(function () {
  'use strict';
  var cfg = window.dgPostCompose || {};
  var api = cfg.api || '/api/post-compose';
  var toInput = document.getElementById('dg-post-to');
  var contactId = document.getElementById('dg-post-contact-id');
  var contactSearch = document.getElementById('dg-post-contact-search');
  var contactResults = document.getElementById('dg-post-contact-results');
  var body = document.getElementById('dg-post-body');
  var greeting = document.getElementById('dg-post-greeting');
  var closing = document.getElementById('dg-post-closing');
  var signature = document.getElementById('dg-post-signature');
  var applyBtn = document.getElementById('dg-post-apply-phrases');
  var closingDialog = document.getElementById('dg-post-closing-custom-dialog');
  var closingCustom = document.getElementById('dg-post-closing-custom');
  var customClosingText = '';
  var closingPrevValue = closing ? closing.value : '';
  var voucherSearch = document.getElementById('dg-post-voucher-search');
  var voucherResults = document.getElementById('dg-post-voucher-results');
  var voucherChips = document.getElementById('dg-post-voucher-chips');
  if (!toInput || !body) {
    return;
  }

  var voucherIds = {};
  var timer = null;

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function optionText(sel) {
    if (!sel || !sel.selectedOptions || !sel.selectedOptions[0]) {
      return '';
    }
    return sel.selectedOptions[0].getAttribute('data-text') || '';
  }

  function closingText() {
    if (!closing) {
      return '';
    }
    if (closing.value === 'custom') {
      return customClosingText.trim();
    }
    return optionText(closing).trim();
  }

  function rebuildBody() {
    var g = optionText(greeting).trim();
    var c = closingText();
    var s = optionText(signature).trim();
    body.value = g + '\n\n\n' + c + '\n\n' + s + '\n';
  }

  function openCustomClosing() {
    if (!closingDialog || !closingCustom) {
      var entered = window.prompt('Eigener Schlussabsatz:', customClosingText || '');
      if (entered === null) {
        if (closing) {
          closing.value = closingPrevValue || 'vertrauen';
        }
        return;
      }
      customClosingText = entered;
      if (closing && closing.selectedOptions[0]) {
        closing.selectedOptions[0].setAttribute('data-text', customClosingText);
      }
      if (cfg.isNew) {
        rebuildBody();
      }
      return;
    }
    closingCustom.value = customClosingText;
    if (typeof closingDialog.showModal === 'function') {
      closingDialog.showModal();
    } else {
      closingDialog.setAttribute('open', 'open');
    }
  }

  function fillGreetingOptions(list, selectedId) {
    if (!greeting || !list || !list.length) {
      return;
    }
    greeting.innerHTML = list
      .map(function (item) {
        return (
          '<option value="' +
          escapeHtml(item.id) +
          '" data-text="' +
          escapeHtml(item.text) +
          '"' +
          (item.id === selectedId ? ' selected' : '') +
          '>' +
          escapeHtml(item.label) +
          '</option>'
        );
      })
      .join('');
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
        if (!contactResults) {
          return;
        }
        var items = (data && data.items) || [];
        if (!items.length) {
          contactResults.innerHTML = '<div class="dg-voucher-contact-results__empty">Keine Kontakte.</div>';
          contactResults.hidden = false;
          return;
        }
        contactResults.innerHTML = items
          .map(function (item) {
            return (
              '<button type="button" class="dg-voucher-contact-results__item" data-id="' +
              item.id +
              '"><strong>' +
              escapeHtml(item.label || '') +
              '</strong><span>' +
              escapeHtml(item.meta || item.email || '') +
              '</span></button>'
            );
          })
          .join('');
        contactResults.hidden = false;
        contactResults.querySelectorAll('[data-id]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            pickContact(parseInt(btn.getAttribute('data-id'), 10) || 0);
          });
        });
      })
      .catch(function () {});
  }

  function pickContact(id) {
    if (id < 1) {
      return;
    }
    fetch(api + '?action=contact&id=' + encodeURIComponent(String(id)), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (!data || !data.ok) {
          return;
        }
        if (contactId) {
          contactId.value = String(data.id || '');
        }
        if (data.email) {
          toInput.value = data.email;
        }
        if (contactSearch) {
          contactSearch.value = data.label || '';
        }
        if (contactResults) {
          contactResults.hidden = true;
          contactResults.innerHTML = '';
        }
        fillGreetingOptions(data.greetings, data.default_greeting || 'neutral');
        if (cfg.isNew) {
          rebuildBody();
        }
      })
      .catch(function () {});
  }

  if (contactSearch && contactResults) {
    contactSearch.addEventListener('input', function () {
      var q = contactSearch.value.trim();
      clearTimeout(timer);
      if (q.length < 1) {
        contactResults.hidden = true;
        return;
      }
      timer = setTimeout(function () {
        searchContacts(q);
      }, 220);
    });
  }

  if (applyBtn) {
    applyBtn.addEventListener('click', rebuildBody);
  }
  if (closing) {
    closing.addEventListener('change', function () {
      if (closing.value === 'custom') {
        openCustomClosing();
        return;
      }
      closingPrevValue = closing.value;
      if (cfg.isNew) {
        rebuildBody();
      }
    });
  }
  if (closingDialog) {
    closingDialog.addEventListener('close', function () {
      var val = closingDialog.returnValue;
      if (val === 'ok') {
        customClosingText = (closingCustom && closingCustom.value) || '';
        if (closing && closing.selectedOptions[0]) {
          closing.selectedOptions[0].setAttribute('data-text', customClosingText);
        }
        closingPrevValue = 'custom';
        if (cfg.isNew) {
          rebuildBody();
        }
      } else if (closing) {
        closing.value = closingPrevValue && closingPrevValue !== 'custom' ? closingPrevValue : 'vertrauen';
      }
    });
  }
  [greeting, signature].forEach(function (sel) {
    if (sel) {
      sel.addEventListener('change', function () {
        if (cfg.isNew) {
          rebuildBody();
        }
      });
    }
  });

  function renderVoucherChips() {
    if (!voucherChips) {
      return;
    }
    var ids = Object.keys(voucherIds);
    voucherChips.innerHTML = ids
      .map(function (id) {
        var label = voucherIds[id];
        return (
          '<li><input type="hidden" name="voucher_ids[]" value="' +
          escapeHtml(id) +
          '"><span>' +
          escapeHtml(label) +
          '</span> <button type="button" data-remove="' +
          escapeHtml(id) +
          '">×</button></li>'
        );
      })
      .join('');
    voucherChips.querySelectorAll('[data-remove]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        delete voucherIds[btn.getAttribute('data-remove')];
        renderVoucherChips();
      });
    });
  }

  function searchVouchers(q) {
    fetch(api + '?action=vouchers&q=' + encodeURIComponent(q), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (!voucherResults) {
          return;
        }
        var items = (data && data.items) || [];
        if (!items.length) {
          voucherResults.innerHTML = '<div class="dg-voucher-contact-results__empty">Keine Belege.</div>';
          voucherResults.hidden = false;
          return;
        }
        voucherResults.innerHTML = items
          .map(function (item) {
            return (
              '<button type="button" class="dg-voucher-contact-results__item" data-id="' +
              item.id +
              '" data-label="' +
              escapeHtml(item.label || '') +
              '"><strong>' +
              escapeHtml(item.label || '') +
              '</strong><span>' +
              escapeHtml(item.meta || '') +
              '</span></button>'
            );
          })
          .join('');
        voucherResults.hidden = false;
        voucherResults.querySelectorAll('[data-id]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-id');
            voucherIds[id] = btn.getAttribute('data-label') || ('Beleg #' + id);
            if (voucherSearch) {
              voucherSearch.value = '';
            }
            voucherResults.hidden = true;
            renderVoucherChips();
          });
        });
      })
      .catch(function () {});
  }

  var vTimer = null;
  if (voucherSearch && voucherResults) {
    voucherSearch.addEventListener('input', function () {
      var q = voucherSearch.value.trim();
      clearTimeout(vTimer);
      if (q.length < 1) {
        voucherResults.hidden = true;
        return;
      }
      vTimer = setTimeout(function () {
        searchVouchers(q);
      }, 220);
    });
  }
})();
