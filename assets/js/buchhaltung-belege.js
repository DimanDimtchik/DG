(function () {
  'use strict';

  var config = window.dgBuchhaltungBelege || {};
  var apiUrl = config.apiUrl || '/api/voucher';
  var chartApiUrl = config.chartApiUrl || '/api/chart-account';

  var listYear = document.getElementById('dg-voucher-year');
  var listType = document.getElementById('dg-voucher-type-filter');

  if (listYear && listType) {
    listYear.addEventListener('change', function () {
      listYear.form.submit();
    });
    listType.addEventListener('change', function () {
      listType.form.submit();
    });
  }

  var form = document.getElementById('dg-voucher-form');
  if (!form) {
    return;
  }

  var readOnly = form.getAttribute('data-readonly') === '1';
  var grossInput = document.getElementById('dg-voucher-gross');
  var netInput = document.getElementById('dg-voucher-net');
  var taxAmountInput = document.getElementById('dg-voucher-tax-amount');
  var taxRateSelect = document.getElementById('dg-voucher-tax-rate');
  var accountInput = document.getElementById('dg-voucher-account-number');
  var accountHint = document.getElementById('dg-voucher-account-hint');
  var contactSearch = document.getElementById('dg-voucher-contact-search');
  var contactIdInput = document.getElementById('dg-voucher-contact-id');
  var contactResults = document.getElementById('dg-voucher-contact-results');
  var supplierInput = document.getElementById('dg-voucher-supplier-name');

  var debounceTimer = null;
  var accountTimer = null;

  function parseAmount(value) {
    if (typeof value !== 'string') {
      value = String(value || '');
    }
    var normalized = value.replace(/\./g, '').replace(',', '.').replace(/[^\d.-]/g, '');
    var num = parseFloat(normalized);
    return isNaN(num) ? 0 : num;
  }

  function formatAmount(num) {
    return num.toFixed(2).replace('.', ',');
  }

  function calcAmounts() {
    if (!grossInput || !netInput || !taxAmountInput || !taxRateSelect) {
      return;
    }
    var gross = parseAmount(grossInput.value);
    var rate = parseInt(taxRateSelect.value, 10) || 0;
    var net = gross;
    var tax = 0;
    if (rate > 0 && gross > 0) {
      net = Math.round((gross / (1 + rate / 100)) * 100) / 100;
      tax = Math.round((gross - net) * 100) / 100;
    }
    netInput.value = gross > 0 ? formatAmount(net) : '';
    taxAmountInput.value = gross > 0 ? formatAmount(tax) : '';
  }

  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function debounce(fn, delay) {
    return function () {
      var args = arguments;
      var ctx = this;
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function () {
        fn.apply(ctx, args);
      }, delay);
    };
  }

  function fetchJson(url) {
    return fetch(url, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    }).then(function (response) {
      return response.text().then(function (text) {
        var data = null;
        if (text) {
          try {
            data = JSON.parse(text);
          } catch (e) {
            throw new Error('Ungültige Server-Antwort.');
          }
        }
        return { ok: response.ok, data: data };
      });
    });
  }

  function lookupAccount() {
    if (!accountInput || !accountHint) {
      return;
    }
    var number = (accountInput.value || '').replace(/\D/g, '');
    if (number.length < 3) {
      return;
    }
    fetchJson(apiUrl + '?action=account&number=' + encodeURIComponent(number))
      .then(function (result) {
        if (result.ok && result.data && result.data.success && result.data.data) {
          var acc = result.data.data;
          var baseHint = accountHint.getAttribute('data-base-hint') || accountHint.textContent.split('—')[0].trim();
          accountHint.textContent = baseHint + ' — ' + acc.name;
          accountHint.classList.remove('dg-field-hint--error');
        } else {
          accountHint.classList.add('dg-field-hint--error');
        }
      })
      .catch(function () {
        accountHint.classList.add('dg-field-hint--error');
      });
  }

  function renderContactResults(items) {
    if (!contactResults) {
      return;
    }
    if (!items || items.length === 0) {
      contactResults.hidden = true;
      contactResults.innerHTML = '';
      return;
    }
    contactResults.hidden = false;
    contactResults.innerHTML = items.map(function (item) {
      return (
        '<button type="button" class="dg-voucher-contact-results__item" data-id="' + item.id + '" data-label="' + escapeHtml(item.label) + '">' +
          '<span class="dg-voucher-contact-results__label">' + escapeHtml(item.label) + '</span>' +
          (item.meta ? '<span class="dg-voucher-contact-results__meta">' + escapeHtml(item.meta) + '</span>' : '') +
        '</button>'
      );
    }).join('');

    contactResults.querySelectorAll('.dg-voucher-contact-results__item').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (contactIdInput) {
          contactIdInput.value = btn.getAttribute('data-id') || '';
        }
        if (contactSearch) {
          contactSearch.value = btn.getAttribute('data-label') || '';
        }
        if (supplierInput && !supplierInput.value) {
          supplierInput.value = btn.getAttribute('data-label') || '';
        }
        contactResults.hidden = true;
        contactResults.innerHTML = '';
      });
    });
  }

  function searchContacts(query) {
    if (!contactResults || query.length < 2) {
      renderContactResults([]);
      return;
    }
    fetchJson(apiUrl + '?action=contacts&q=' + encodeURIComponent(query))
      .then(function (result) {
        if (result.ok && result.data && result.data.success) {
          renderContactResults((result.data.data && result.data.data.items) || []);
        }
      })
      .catch(function () {
        renderContactResults([]);
      });
  }

  if (accountHint && !accountHint.getAttribute('data-base-hint')) {
    accountHint.setAttribute('data-base-hint', accountHint.textContent.split('—')[0].trim());
  }

  if (grossInput) {
    grossInput.addEventListener('input', calcAmounts);
  }
  if (taxRateSelect) {
    taxRateSelect.addEventListener('change', calcAmounts);
  }

  if (accountInput && !readOnly) {
    accountInput.addEventListener('input', function () {
      clearTimeout(accountTimer);
      accountTimer = setTimeout(lookupAccount, 350);
    });
    if (accountInput.value) {
      lookupAccount();
    }
  }

  if (contactSearch && !readOnly) {
    contactSearch.addEventListener('input', debounce(function () {
      if (contactIdInput) {
        contactIdInput.value = '';
      }
      searchContacts(contactSearch.value.trim());
    }, 300));

    document.addEventListener('click', function (event) {
      if (!contactResults || contactResults.hidden) {
        return;
      }
      if (event.target === contactSearch || contactResults.contains(event.target)) {
        return;
      }
      contactResults.hidden = true;
    });
  }

  calcAmounts();
})();
