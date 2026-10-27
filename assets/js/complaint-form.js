/**
 * Reklamation: Kontakt- und Belegsuche.
 */
(function () {
  'use strict';

  var cfg = window.dgComplaintForm || {};
  var api = cfg.api || '/api/complaint';
  var contactId = document.getElementById('dg-complaint-contact-id');
  var contactName = document.getElementById('dg-complaint-contact-name');
  var contactSearch = document.getElementById('dg-complaint-contact-search');
  var contactResults = document.getElementById('dg-complaint-contact-results');
  var contactClear = document.getElementById('dg-complaint-contact-clear');
  var voucherId = document.getElementById('dg-complaint-voucher-id');
  var voucherSearch = document.getElementById('dg-complaint-voucher-search');
  var voucherResults = document.getElementById('dg-complaint-voucher-results');
  var voucherClear = document.getElementById('dg-complaint-voucher-clear');
  var voucherRecent = document.getElementById('dg-complaint-voucher-recent');

  if (!contactSearch || !contactId) {
    return;
  }

  var timer = null;

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function currentContactId() {
    return parseInt(contactId.value, 10) || 0;
  }

  function syncClearButtons() {
    if (contactClear) {
      contactClear.hidden = currentContactId() < 1;
    }
    if (voucherClear) {
      voucherClear.hidden = !(parseInt(voucherId && voucherId.value, 10) > 0);
    }
  }

  function renderResults(box, items, onPick) {
    if (!box) {
      return;
    }
    if (!items || !items.length) {
      box.innerHTML = '<div class="dg-voucher-contact-results__empty">Keine Treffer.</div>';
      box.hidden = false;
      return;
    }
    box.innerHTML = items
      .map(function (item) {
        return (
          '<button type="button" class="dg-voucher-contact-results__item" data-id="' +
          item.id +
          '"><strong>' +
          escapeHtml(item.label || '') +
          '</strong><span>' +
          escapeHtml(item.meta || '') +
          '</span></button>'
        );
      })
      .join('');
    box.hidden = false;
    box.querySelectorAll('[data-id]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = parseInt(btn.getAttribute('data-id'), 10) || 0;
        var found = items.filter(function (it) {
          return it.id === id;
        })[0];
        onPick(found || { id: id });
      });
    });
  }

  function searchContacts(q) {
    fetch(api + '?action=contacts&q=' + encodeURIComponent(q), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        renderResults(contactResults, (data && data.items) || [], function (item) {
          contactId.value = String(item.id || 0);
          if (contactName) {
            contactName.value = item.label || '';
          }
          contactSearch.value = item.label || '';
          if (contactResults) {
            contactResults.hidden = true;
            contactResults.innerHTML = '';
          }
          syncClearButtons();
          loadRecentVouchers();
        });
      })
      .catch(function () {});
  }

  function searchVouchers(q) {
    if (!voucherResults) {
      return;
    }
    var url =
      api +
      '?action=vouchers&q=' +
      encodeURIComponent(q || '') +
      '&contact_id=' +
      encodeURIComponent(String(currentContactId()));
    fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        renderResults(voucherResults, (data && data.items) || [], pickVoucher);
      })
      .catch(function () {});
  }

  function pickVoucher(item) {
    if (!voucherId) {
      return;
    }
    voucherId.value = String(item.id || 0);
    if (voucherSearch) {
      voucherSearch.value = item.label || '';
    }
    if (voucherResults) {
      voucherResults.hidden = true;
      voucherResults.innerHTML = '';
    }
    if ((!currentContactId() || currentContactId() < 1) && item.contact_id) {
      contactId.value = String(item.contact_id);
      if (contactName) {
        contactName.value = item.contact_label || '';
      }
      if (contactSearch) {
        contactSearch.value = item.contact_label || contactSearch.value;
      }
    }
    syncClearButtons();
  }

  function loadRecentVouchers() {
    if (!voucherRecent) {
      return;
    }
    var cid = currentContactId();
    if (cid < 1) {
      voucherRecent.innerHTML = '<option value="">Zuerst Kunde wählen …</option>';
      voucherRecent.disabled = true;
      return;
    }
    voucherRecent.disabled = true;
    voucherRecent.innerHTML = '<option value="">Lade Belege …</option>';
    fetch(api + '?action=vouchers&contact_id=' + encodeURIComponent(String(cid)), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        var items = (data && data.items) || [];
        if (!items.length) {
          voucherRecent.innerHTML = '<option value="">Keine Belege der letzten 24 Monate</option>';
          voucherRecent.disabled = true;
          return;
        }
        voucherRecent.innerHTML =
          '<option value="">— Beleg wählen —</option>' +
          items
            .map(function (item) {
              return (
                '<option value="' +
                item.id +
                '" data-label="' +
                escapeHtml(item.label || '') +
                '" data-contact-id="' +
                (item.contact_id || 0) +
                '" data-contact-label="' +
                escapeHtml(item.contact_label || '') +
                '">' +
                escapeHtml(item.label || '') +
                (item.meta ? ' (' + escapeHtml(item.meta) + ')' : '') +
                '</option>'
              );
            })
            .join('');
        voucherRecent.disabled = false;
      })
      .catch(function () {
        voucherRecent.innerHTML = '<option value="">Belege nicht ladbar</option>';
      });
  }

  contactSearch.addEventListener('input', function () {
    var q = contactSearch.value.trim();
    clearTimeout(timer);
    if (q.length < 1) {
      if (contactResults) {
        contactResults.hidden = true;
      }
      return;
    }
    timer = setTimeout(function () {
      searchContacts(q);
    }, 220);
  });

  if (contactClear) {
    contactClear.addEventListener('click', function () {
      contactId.value = '0';
      if (contactName) {
        contactName.value = '';
      }
      contactSearch.value = '';
      if (contactResults) {
        contactResults.hidden = true;
      }
      syncClearButtons();
      loadRecentVouchers();
    });
  }

  if (voucherSearch) {
    voucherSearch.addEventListener('input', function () {
      var q = voucherSearch.value.trim();
      clearTimeout(timer);
      if (q.length < 1) {
        if (voucherResults) {
          voucherResults.hidden = true;
        }
        return;
      }
      timer = setTimeout(function () {
        searchVouchers(q);
      }, 220);
    });
  }

  if (voucherClear) {
    voucherClear.addEventListener('click', function () {
      if (voucherId) {
        voucherId.value = '0';
      }
      if (voucherSearch) {
        voucherSearch.value = '';
      }
      if (voucherRecent) {
        voucherRecent.value = '';
      }
      if (voucherResults) {
        voucherResults.hidden = true;
      }
      syncClearButtons();
    });
  }

  if (voucherRecent) {
    voucherRecent.addEventListener('change', function () {
      var opt = voucherRecent.selectedOptions && voucherRecent.selectedOptions[0];
      var id = parseInt(voucherRecent.value, 10) || 0;
      if (id < 1 || !opt) {
        return;
      }
      pickVoucher({
        id: id,
        label: opt.getAttribute('data-label') || opt.textContent || '',
        contact_id: parseInt(opt.getAttribute('data-contact-id'), 10) || 0,
        contact_label: opt.getAttribute('data-contact-label') || '',
      });
    });
  }

  document.addEventListener('click', function (ev) {
    var t = ev.target;
    if (contactResults && !contactResults.contains(t) && t !== contactSearch) {
      contactResults.hidden = true;
    }
    if (voucherResults && !voucherResults.contains(t) && t !== voucherSearch) {
      voucherResults.hidden = true;
    }
  });

  syncClearButtons();
  loadRecentVouchers();
})();
