/**
 * Bankabgleich: Beleg-Zuordnung per Live-Suche (Kunde / Rechnungsnummer).
 */
(function () {
  'use strict';

  var cfg = window.dgBankMatchConfig || {};
  var apiUrl = cfg.apiUrl || '/api/bank-match-suggest';
  var debounceMs = 220;

  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
  }

  function hideResults(box) {
    if (!box) {
      return;
    }
    box.innerHTML = '';
    box.hidden = true;
  }

  function renderResults(picker, items) {
    var box = picker.querySelector('.dg-bank-match-results');
    var hidden = picker.querySelector('input[name="bank_match_voucher_id"]');
    var input = picker.querySelector('.dg-bank-match-search');
    if (!box || !hidden || !input) {
      return;
    }

    box.innerHTML = '';
    if (!items || !items.length) {
      box.hidden = true;
      return;
    }

    items.forEach(function (item) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'dg-bank-match-results__item';
      btn.setAttribute('role', 'option');
      var meta = [];
      if (item.voucher_date) {
        meta.push(item.voucher_date);
      }
      if (item.open_amount_display) {
        meta.push(item.open_amount_display + ' € offen');
      }
      btn.innerHTML =
        '<span class="dg-bank-match-results__main">' +
        escapeHtml(item.invoice_number || '#' + item.id) +
        (item.contact_label ? ' · ' + escapeHtml(item.contact_label) : '') +
        '</span>' +
        (meta.length
          ? '<span class="dg-bank-match-results__meta">' + escapeHtml(meta.join(' · ')) + '</span>'
          : '');
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        hidden.value = String(item.id || '');
        input.value = item.label || item.invoice_number || '#' + item.id;
        input.classList.add('dg-bank-match-search--picked');
        hideResults(box);
      });
      box.appendChild(btn);
    });
    box.hidden = false;
  }

  function fetchSuggestions(picker, query) {
    var box = picker.querySelector('.dg-bank-match-results');
    if (query.trim().length < 1) {
      hideResults(box);
      return;
    }

    var url = apiUrl + (apiUrl.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(query.trim());
    var seq = (Number(picker.dataset.searchSeq) || 0) + 1;
    picker.dataset.searchSeq = String(seq);

    fetch(url, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })
      .then(function (res) {
        return res.json();
      })
      .then(function (payload) {
        if (String(picker.dataset.searchSeq) !== String(seq)) {
          return;
        }
        if (!payload || !payload.success) {
          hideResults(box);
          return;
        }
        var items = (payload.data && payload.data.items) || [];
        renderResults(picker, items);
      })
      .catch(function () {
        if (String(picker.dataset.searchSeq) === String(seq)) {
          hideResults(box);
        }
      });
  }

  function bindPicker(picker) {
    var input = picker.querySelector('.dg-bank-match-search');
    var hidden = picker.querySelector('input[name="bank_match_voucher_id"]');
    var box = picker.querySelector('.dg-bank-match-results');
    var form = picker.closest('form');
    if (!input || !hidden) {
      return;
    }

    var timer = null;
    input.addEventListener('input', function () {
      hidden.value = '';
      input.classList.remove('dg-bank-match-search--picked');
      clearTimeout(timer);
      timer = setTimeout(function () {
        fetchSuggestions(picker, input.value);
      }, debounceMs);
    });

    input.addEventListener('focus', function () {
      if (input.value.trim().length >= 1 && !hidden.value) {
        fetchSuggestions(picker, input.value);
      }
    });

    input.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        hideResults(box);
      }
    });

    document.addEventListener('click', function (e) {
      if (!picker.contains(e.target)) {
        hideResults(box);
      }
    });

    if (form) {
      form.addEventListener('submit', function (e) {
        var submitter = e.submitter;
        if (!submitter || submitter.name !== 'bank_match_manual') {
          return;
        }
        if (!hidden.value) {
          e.preventDefault();
          input.focus();
          input.classList.add('dg-input--error');
          if (input.value.trim().length >= 1) {
            fetchSuggestions(picker, input.value);
          }
          setTimeout(function () {
            input.classList.remove('dg-input--error');
          }, 1200);
        }
      });
    }
  }

  document.querySelectorAll('[data-bank-match-picker]').forEach(bindPicker);
})();
