(function () {
  'use strict';

  var config = window.dgBuchhaltungBelege || {};
  var apiUrl = config.apiUrl || '/api/voucher';
  var typeDescriptions = config.typeDescriptions || {};

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
  var taxTotalEl = document.getElementById('dg-voucher-tax-total');
  var taxRateEls = {
    0: document.getElementById('dg-voucher-tax-rate-0'),
    7: document.getElementById('dg-voucher-tax-rate-7'),
    19: document.getElementById('dg-voucher-tax-rate-19'),
  };
  var reverseChargeTypeSelect = document.getElementById('dg-voucher-reverse-charge-type');
  var taxKeyInput = document.getElementById('dg-voucher-tax-key');
  var bookingAmountHeader = document.getElementById('dg-voucher-booking-amount-header');
  var reverseChargeHint = document.getElementById('dg-voucher-reverse-charge-hint');
  var grossHint = document.getElementById('dg-voucher-gross-hint');
  var netHint = document.getElementById('dg-voucher-net-hint');
  var taxHint = document.getElementById('dg-voucher-tax-hint');
  var rcPanels = document.getElementById('dg-voucher-rc-panels');
  var rcPostingsBody = document.getElementById('dg-voucher-rc-postings-body');
  var rcUstvaBody = document.getElementById('dg-voucher-rc-ustva-body');
  var rcConfig = config.reverseCharge || {};
  var previewTimer = null;
  var contactSearch = document.getElementById('dg-voucher-contact-search');
  var contactIdInput = document.getElementById('dg-voucher-contact-id');
  var contactResults = document.getElementById('dg-voucher-contact-results');
  var supplierInput = document.getElementById('dg-voucher-supplier-name');
  var saveButton = document.getElementById('dg-voucher-save-btn');
  var contactHint = document.getElementById('dg-voucher-contact-hint');
  var typeSelect = document.getElementById('dg-voucher-type');
  var typeHint = document.getElementById('dg-voucher-type-hint');
  var bookingBody = document.getElementById('dg-voucher-booking-body');
  var bookingRowTemplate = document.getElementById('dg-voucher-booking-row-template');
  var bookingSumEl = document.getElementById('dg-voucher-booking-sum');
  var invoiceItemsSection = document.getElementById('dg-voucher-invoice-items-section');
  var invoiceItemsBody = document.getElementById('dg-voucher-invoice-items-body');
  var invoiceItemRowTemplate = document.getElementById('dg-voucher-invoice-item-row-template');
  var invoiceItemsSumEl = document.getElementById('dg-voucher-invoice-items-sum');
  var bookingSection = document.getElementById('dg-voucher-booking-section');
  var incomeVoucherTypes = config.incomeVoucherTypes || ['income', 'income_reduction'];
  var revenueAccounts = config.revenueAccounts || { 19: '8410', 7: '8334', 0: '8192' };
  var articleSearchTimer = null;
  var syncingBookingFromItems = false;

  var debounceTimer = null;

  function getVoucherType() {
    return typeSelect ? typeSelect.value : 'expense';
  }

  function usesIncomeItems() {
    return incomeVoucherTypes.indexOf(getVoucherType()) !== -1;
  }

  function taxTypeFromRate(rate) {
    var value = parseInt(String(rate), 10);
    if (value === 7) {
      return 'ust7';
    }
    if (value === 0) {
      return 'ust0';
    }
    return 'ust19';
  }

  function taxRateFromTaxType(taxType) {
    var value = String(taxType || '').toLowerCase();
    if (value === 'ust7') {
      return 7;
    }
    if (value === 'ust0') {
      return 0;
    }
    return 19;
  }

  function parseArticlePrice(value) {
    if (value === undefined || value === null || value === '') {
      return null;
    }
    if (typeof value === 'number') {
      return isNaN(value) ? null : value;
    }
    var normalized = String(value).replace(/\./g, '').replace(',', '.').replace(/[^\d.-]/g, '');
    var num = parseFloat(normalized);
    return isNaN(num) ? null : num;
  }

  function normalizeArticlePick(item, button) {
    var fromButton = articleFromButton(button) || {};
    var fromItem = item && typeof item === 'object' ? item : {};
    var taxRate = fromItem.tax_rate;
    if (taxRate === undefined || taxRate === null || taxRate === '') {
      taxRate = fromButton.tax_rate;
    }
    taxRate = parseInt(String(taxRate), 10);
    if (isNaN(taxRate)) {
      taxRate = taxRateFromTaxType(fromItem.tax_type || fromButton.tax_type);
    }

    var priceGross = parseArticlePrice(fromItem.price_gross);
    if (priceGross === null) {
      priceGross = parseArticlePrice(fromButton.price_gross);
    }
    if (priceGross === null) {
      priceGross = 0;
    }

    return {
      id: parseInt(String(fromItem.id != null ? fromItem.id : fromButton.id || '0'), 10) || 0,
      catalog_kind: fromItem.catalog_kind || fromButton.catalog_kind || '',
      article_number: fromItem.article_number || fromButton.article_number || '',
      title: fromItem.title || fromButton.title || '',
      unit: fromItem.unit || fromButton.unit || 'Stück',
      tax_type: fromItem.tax_type || fromButton.tax_type || taxTypeFromRate(taxRate),
      tax_rate: taxRate,
      price_gross: priceGross,
      area_id: parseInt(String(fromItem.area_id != null ? fromItem.area_id : fromButton.area_id || '0'), 10) || 0,
      area_name: fromItem.area_name || fromButton.area_name || '',
    };
  }

  function setTaxSelectValue(taxField, taxTypeField, taxRate, taxType) {
    if (!taxField) {
      return;
    }
    var rate = parseInt(String(taxRate), 10);
    if (isNaN(rate)) {
      rate = taxRateFromTaxType(taxType);
    }
    taxField.value = String(rate);
    if (taxField.value !== String(rate)) {
      var options = Array.prototype.slice.call(taxField.options || []);
      var match = options.find(function (option) {
        return parseInt(option.value, 10) === rate;
      });
      if (match) {
        taxField.value = match.value;
      }
    }
    if (taxTypeField) {
      taxTypeField.value = taxType || taxTypeFromRate(rate);
    }
  }

  function triggerFieldInput(field) {
    if (!field) {
      return;
    }
    field.dispatchEvent(new Event('input', { bubbles: true }));
    field.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function isInvoiceItemEmpty(row) {
    if (!row) {
      return true;
    }
    var title = (row.querySelector('.dg-voucher-items-title') || {}).value || '';
    var gross = parseAmount((row.querySelector('.dg-voucher-items-gross') || {}).value || '0');
    return title.trim() === '' && gross <= 0;
  }

  function hasEmptyInvoiceItem() {
    if (!invoiceItemsBody) {
      return false;
    }
    var rows = invoiceItemsBody.querySelectorAll('.dg-voucher-items__row');
    for (var i = 0; i < rows.length; i++) {
      if (isInvoiceItemEmpty(rows[i])) {
        return true;
      }
    }
    return false;
  }

  function reindexInvoiceItems() {
    if (!invoiceItemsBody) {
      return;
    }
    invoiceItemsBody.querySelectorAll('.dg-voucher-items__row').forEach(function (row, index) {
      row.setAttribute('data-item-index', String(index));
      row.querySelectorAll('[name^="items["]').forEach(function (field) {
        field.name = field.name.replace(/items\[\d+]/, 'items[' + index + ']');
      });
    });
  }

  function updateInvoiceItemLineTotal(row) {
    if (!row) {
      return 0;
    }
    var qty = parseAmount((row.querySelector('.dg-voucher-items-quantity') || {}).value || '1');
    if (qty <= 0) {
      qty = 1;
    }
    var unitPrice = parseAmount((row.querySelector('.dg-voucher-items-unit-price') || {}).value || '0');
    var gross = Math.round(qty * unitPrice * 100) / 100;
    var grossField = row.querySelector('.dg-voucher-items-gross');
    var articleId = (row.querySelector('.dg-voucher-items-article-id') || {}).value || '';
    if (grossField) {
      if (gross > 0) {
        grossField.value = formatAmount(gross);
      } else if (articleId) {
        grossField.value = '0,00';
      } else {
        grossField.value = '';
      }
    }
    return gross;
  }

  function syncInvoiceItemsSum() {
    if (!invoiceItemsBody || !invoiceItemsSumEl) {
      return 0;
    }
    var sum = 0;
    invoiceItemsBody.querySelectorAll('.dg-voucher-items__row').forEach(function (row) {
      sum += updateInvoiceItemLineTotal(row);
    });
    invoiceItemsSumEl.textContent = formatAmount(sum);
    return sum;
  }

  function collectInvoiceItems() {
    var items = [];
    if (!invoiceItemsBody) {
      return items;
    }
    invoiceItemsBody.querySelectorAll('.dg-voucher-items__row').forEach(function (row) {
      var titleField = row.querySelector('.dg-voucher-items-title');
      var title = titleField ? titleField.value.trim() : '';
      var gross = updateInvoiceItemLineTotal(row);
      var taxField = row.querySelector('.dg-voucher-items-tax');
      var taxRate = taxField ? parseInt(taxField.value, 10) : 19;
      if (title === '' && gross <= 0) {
        return;
      }
      if (gross <= 0) {
        return;
      }
      items.push({
        title: title,
        gross: gross,
        taxRate: isNaN(taxRate) ? 19 : taxRate,
      });
    });
    return items;
  }

  function syncBookingFromItems() {
    if (!bookingBody || !usesIncomeItems() || readOnly) {
      return;
    }
    syncingBookingFromItems = true;
    var items = collectInvoiceItems();
    var groups = {};
    items.forEach(function (item) {
      var key = String(item.taxRate);
      if (!groups[key]) {
        groups[key] = { taxRate: item.taxRate, gross: 0, titles: [] };
      }
      groups[key].gross += item.gross;
      if (item.title) {
        groups[key].titles.push(item.title);
      }
    });

    bookingBody.innerHTML = '';
    var rates = Object.keys(groups).sort(function (a, b) {
      return parseInt(b, 10) - parseInt(a, 10);
    });
    if (rates.length === 0) {
      addBookingRow();
      syncingBookingFromItems = false;
      syncTotalsFromLines();
      return;
    }

    var pending = rates.length;
    rates.forEach(function (rateKey) {
      var group = groups[rateKey];
      var account = revenueAccounts[group.taxRate] || revenueAccounts[19] || '8410';
      addBookingRow();
      var row = bookingBody.querySelector('.dg-voucher-split__row:last-child');
      if (!row) {
        pending -= 1;
        return;
      }
      var accountField = row.querySelector('.dg-voucher-split-account');
      var queryField = row.querySelector('.dg-voucher-split-account-query');
      var grossField = row.querySelector('.dg-voucher-split-gross');
      var taxField = row.querySelector('.dg-voucher-split-tax');
      if (accountField) {
        accountField.value = account;
      }
      if (grossField) {
        grossField.value = formatAmount(group.gross);
      }
      if (taxField) {
        taxField.value = String(group.taxRate);
      }
      fetchJson(apiUrl + '?action=account&number=' + encodeURIComponent(account) + accountLookupSuffix())
        .then(function (result) {
          if (queryField) {
            if (result.ok && result.data && result.data.success && result.data.data) {
              var acc = result.data.data;
              queryField.value = formatAccountLabel(acc.account_number, acc.name);
            } else {
              queryField.value = account;
            }
            queryField.classList.remove('dg-input--error');
          }
        })
        .catch(function () {
          if (queryField) {
            queryField.value = account;
          }
        })
        .finally(function () {
          pending -= 1;
          if (pending <= 0) {
            syncingBookingFromItems = false;
            reindexBookingRows();
            syncTotalsFromLines();
            refreshReverseChargePreview();
          }
        });
    });
  }

  function articleFromButton(button) {
    if (!button) {
      return null;
    }

    return {
      id: parseInt(button.getAttribute('data-article-id') || '0', 10) || 0,
      catalog_kind: button.getAttribute('data-catalog-kind') || '',
      article_number: button.getAttribute('data-article-number') || '',
      title: button.getAttribute('data-title') || '',
      unit: button.getAttribute('data-unit') || 'Stück',
      tax_type: button.getAttribute('data-tax-type') || 'ust19',
      tax_rate: parseInt(button.getAttribute('data-tax-rate') || '19', 10) || 19,
      price_gross: parseFloat(String(button.getAttribute('data-price-gross') || '0').replace(',', '.')) || 0,
      area_id: parseInt(button.getAttribute('data-area-id') || '0', 10) || 0,
      area_name: button.getAttribute('data-area-name') || '',
    };
  }

  function setInvoiceItemArticle(row, article) {
    if (!row || !article) {
      return;
    }
    var queryField = row.querySelector('.dg-voucher-items-article-query');
    var idField = row.querySelector('.dg-voucher-items-article-id');
    var kindField = row.querySelector('.dg-voucher-items-catalog-kind');
    var numberField = row.querySelector('.dg-voucher-items-article-number');
    var titleField = row.querySelector('.dg-voucher-items-title');
    var taxTypeField = row.querySelector('.dg-voucher-items-tax-type');
    var unitField = row.querySelector('.dg-voucher-items-unit');
    var priceField = row.querySelector('.dg-voucher-items-unit-price');
    var taxField = row.querySelector('.dg-voucher-items-tax');
    var areaField = row.querySelector('.dg-voucher-items-area');
    var areaNameField = row.querySelector('.dg-voucher-items-area-name');
    var label = article.article_number ? article.article_number + ' — ' + article.title : article.title;
    if (queryField) {
      queryField.value = label;
    }
    if (idField) {
      idField.value = String(article.id || '');
    }
    if (kindField) {
      kindField.value = article.catalog_kind || '';
    }
    if (numberField) {
      numberField.value = article.article_number || '';
    }
    if (titleField) {
      titleField.value = article.title || '';
    }
    if (unitField && article.unit) {
      unitField.value = article.unit;
    }
    if (priceField) {
      var price = parseArticlePrice(article.price_gross);
      if (price === null) {
        price = 0;
      }
      priceField.value = formatAmount(price);
      triggerFieldInput(priceField);
    }
    setTaxSelectValue(taxField, taxTypeField, article.tax_rate, article.tax_type);
    triggerFieldInput(taxField);
    if (areaField && article.area_id) {
      areaField.value = String(article.area_id);
    }
    if (areaNameField) {
      areaNameField.value = article.area_name || '';
    }
    var results = row.querySelector('.dg-voucher-items-search-results');
    if (results) {
      results.hidden = true;
    }
    var qtyField = row.querySelector('.dg-voucher-items-quantity');
    var pickedPrice = parseArticlePrice(article.price_gross);
    if (priceField && !readOnly && (pickedPrice === null || pickedPrice <= 0)) {
      priceField.focus();
      priceField.select();
    } else if (qtyField && !readOnly) {
      qtyField.focus();
      qtyField.select();
    }
    updateInvoiceItemLineTotal(row);
    onInvoiceItemInput();
  }

  function renderArticleResults(container, items, row) {
    if (!container) {
      return;
    }
    if (!items || items.length === 0) {
      container.innerHTML = '<div class="dg-account-search-results__item dg-account-search-results__item--empty">Keine Treffer</div>';
      container.hidden = false;
      return;
    }
    container.innerHTML = items.map(function (item) {
      var meta = [item.kind_label || '', item.tax_label || '', item.price_label || ''].filter(Boolean).join(' · ');
      var area = item.area_name ? ' · ' + item.area_name : '';
      return '<button type="button" class="dg-account-search-results__item dg-article-search-results__item"' +
        ' data-article-id="' + escapeHtml(String(item.id != null ? item.id : '')) + '"' +
        ' data-catalog-kind="' + escapeHtml(item.catalog_kind || '') + '"' +
        ' data-article-number="' + escapeHtml(item.article_number || '') + '"' +
        ' data-title="' + escapeHtml(item.title || '') + '"' +
        ' data-unit="' + escapeHtml(item.unit || 'Stück') + '"' +
        ' data-tax-type="' + escapeHtml(item.tax_type || 'ust19') + '"' +
        ' data-tax-rate="' + escapeHtml(item.tax_rate != null ? String(item.tax_rate) : '19') + '"' +
        ' data-price-gross="' + escapeHtml(item.price_gross != null ? String(item.price_gross) : '0') + '"' +
        ' data-area-id="' + escapeHtml(item.area_id != null ? String(item.area_id) : '0') + '"' +
        ' data-area-name="' + escapeHtml(item.area_name || '') + '">' +
        '<span class="dg-account-search-results__number">' + escapeHtml(item.article_number || '') + '</span>' +
        '<span class="dg-account-search-results__name">' + escapeHtml(item.title || '') + '</span>' +
        '<span class="dg-account-search-results__meta">' + escapeHtml(meta + area) + '</span>' +
        '</button>';
    }).join('');
    container.hidden = false;
    container.querySelectorAll('.dg-article-search-results__item').forEach(function (button, index) {
      var item = items[index] || null;
      button.addEventListener('mousedown', function (event) {
        event.preventDefault();
      });
      button.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        var picked = normalizeArticlePick(item, button);
        if (picked && (picked.title || picked.article_number || picked.id)) {
          setInvoiceItemArticle(row, picked);
        }
      });
    });
  }

  function searchArticles(query, row) {
    var container = row ? row.querySelector('.dg-voucher-items-search-results') : null;
    if (!container) {
      return;
    }
    fetchJson(apiUrl + '?action=article_search&q=' + encodeURIComponent(query))
      .then(function (result) {
        if (result.ok && result.data && result.data.success) {
          renderArticleResults(container, result.data.data.items || [], row);
        }
      })
      .catch(function () {
        container.hidden = true;
      });
  }

  function updateInvoiceItemAreaName(row) {
    if (!row) {
      return;
    }
    var areaField = row.querySelector('.dg-voucher-items-area');
    var areaNameField = row.querySelector('.dg-voucher-items-area-name');
    if (!areaField || !areaNameField) {
      return;
    }
    var selected = areaField.options[areaField.selectedIndex];
    areaNameField.value = selected ? (selected.getAttribute('data-area-name') || selected.textContent || '') : '';
  }

  function onInvoiceItemInput() {
    syncInvoiceItemsSum();
    if (usesIncomeItems()) {
      syncBookingFromItems();
    }
    if (!readOnly && invoiceItemsBody && !hasEmptyInvoiceItem()) {
      addInvoiceItemRow();
    }
  }

  function bindInvoiceItemRow(row) {
    if (!row || readOnly) {
      return;
    }
    var queryField = row.querySelector('.dg-voucher-items-article-query');
    var removeBtn = row.querySelector('.dg-voucher-items-remove');
    var areaField = row.querySelector('.dg-voucher-items-area');
    var taxField = row.querySelector('.dg-voucher-items-tax');
    row.querySelectorAll('.dg-voucher-items-quantity, .dg-voucher-items-unit-price, .dg-voucher-items-unit').forEach(function (field) {
      field.addEventListener('input', onInvoiceItemInput);
    });
    if (taxField) {
      taxField.addEventListener('change', function () {
        var taxTypeField = row.querySelector('.dg-voucher-items-tax-type');
        if (taxTypeField) {
          taxTypeField.value = taxTypeFromRate(taxField.value);
        }
        onInvoiceItemInput();
      });
    }
    if (areaField) {
      areaField.addEventListener('change', function () {
        updateInvoiceItemAreaName(row);
      });
    }
    if (queryField) {
      queryField.addEventListener('input', function () {
        var idField = row.querySelector('.dg-voucher-items-article-id');
        var titleField = row.querySelector('.dg-voucher-items-title');
        if (idField) {
          idField.value = '';
        }
        if (titleField && !titleField.value) {
          titleField.value = queryField.value.trim();
        }
        clearTimeout(articleSearchTimer);
        var value = queryField.value.trim();
        articleSearchTimer = setTimeout(function () {
          searchArticles(value, row);
        }, 250);
        onInvoiceItemInput();
      });
      queryField.addEventListener('focus', function () {
        searchArticles(queryField.value.trim(), row);
      });
      queryField.addEventListener('blur', function () {
        var titleField = row.querySelector('.dg-voucher-items-title');
        if (titleField && !titleField.value.trim()) {
          titleField.value = queryField.value.trim();
        }
      });
    }
    if (removeBtn) {
      removeBtn.addEventListener('click', function () {
        var rows = invoiceItemsBody.querySelectorAll('.dg-voucher-items__row');
        if (rows.length <= 1) {
          row.querySelectorAll('input, select').forEach(function (field) {
            if (field.type === 'hidden' || field.classList.contains('dg-voucher-items-gross')) {
              if (field.classList.contains('dg-voucher-items-gross')) {
                field.value = '';
              } else {
                field.value = '';
              }
            } else if (field.tagName === 'SELECT') {
              field.selectedIndex = 0;
            } else {
              field.value = field.classList.contains('dg-voucher-items-quantity') ? '1'
                : (field.classList.contains('dg-voucher-items-unit') ? 'Stück' : '');
            }
          });
          onInvoiceItemInput();
          return;
        }
        row.remove();
        reindexInvoiceItems();
        onInvoiceItemInput();
      });
    }
  }

  function addInvoiceItemRow() {
    if (!invoiceItemsBody || !invoiceItemRowTemplate || readOnly) {
      return;
    }
    var clone = invoiceItemRowTemplate.content.cloneNode(true);
    var row = clone.querySelector('.dg-voucher-items__row');
    invoiceItemsBody.appendChild(clone);
    if (row) {
      bindInvoiceItemRow(row);
    }
    reindexInvoiceItems();
  }

  function ensureTrailingInvoiceItemRow() {
    if (!invoiceItemsBody || readOnly || !usesIncomeItems()) {
      return;
    }
    if (invoiceItemsBody.querySelectorAll('.dg-voucher-items__row').length === 0) {
      addInvoiceItemRow();
      return;
    }
    if (!hasEmptyInvoiceItem()) {
      addInvoiceItemRow();
    }
  }

  function syncIncomeMode() {
    var income = usesIncomeItems();
    if (invoiceItemsSection) {
      invoiceItemsSection.hidden = !income;
    }
    var arapSection = document.getElementById('dg-voucher-arap-section');
    if (arapSection) {
      arapSection.hidden = income;
    }
    if (income && arapEnabledInput) {
      arapEnabledInput.checked = false;
      syncArapUi();
    }
    if (bookingSection) {
      bookingSection.classList.toggle('dg-voucher-booking-section--derived', income);
    }
    var bookingPanel = document.getElementById('dg-voucher-booking-panel');
    if (bookingPanel) {
      bookingPanel.classList.toggle('dg-voucher-booking--derived', income);
    }
    if (bookingBody) {
      bookingBody.querySelectorAll('input, select, button').forEach(function (field) {
        if (field.classList.contains('dg-voucher-split-remove')) {
          field.disabled = income;
          return;
        }
        if (field.tagName === 'SELECT') {
          field.disabled = income;
        } else if (field.type === 'search' || field.type === 'text') {
          field.readOnly = income;
        }
      });
    }
    if (income) {
      if (invoiceItemsBody && invoiceItemsBody.querySelectorAll('.dg-voucher-items__row').length === 0) {
        addInvoiceItemRow();
      }
      ensureTrailingInvoiceItemRow();
      syncInvoiceItemsSum();
      syncBookingFromItems();
    }
  }

  function hasSelectedContact() {
    return !!(contactIdInput && parseInt(contactIdInput.value, 10) > 0);
  }

  function syncContactValidation() {
    if (readOnly) {
      return;
    }
    var searchVal = contactSearch ? contactSearch.value.trim() : '';
    var valid = hasSelectedContact();
    if (saveButton) {
      saveButton.disabled = !valid;
    }
    if (contactSearch) {
      contactSearch.classList.toggle('dg-input--error', !valid && searchVal !== '');
    }
    if (contactHint) {
      if (valid) {
        contactHint.textContent = 'Kontakt aus dem CRM verknüpft.';
      } else if (searchVal !== '') {
        contactHint.textContent = 'Kein gültiger Kontakt — bitte einen Vorschlag wählen oder zuerst unter Kontakte anlegen.';
      } else {
        contactHint.textContent = 'Pflicht: einen gespeicherten Kontakt aus der Liste wählen.';
      }
    }
  }

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

  function isLineEmpty(row) {
    if (!row) {
      return true;
    }
    var accountField = row.querySelector('.dg-voucher-split-account');
    var grossField = row.querySelector('.dg-voucher-split-gross');
    var account = (accountField && accountField.value || '').replace(/\D/g, '');
    var gross = grossField ? parseAmount(grossField.value) : 0;
    return account === '' && gross <= 0;
  }

  function hasEmptyLine() {
    if (!bookingBody) {
      return false;
    }
    var rows = bookingBody.querySelectorAll('.dg-voucher-split__row');
    for (var i = 0; i < rows.length; i++) {
      if (isLineEmpty(rows[i])) {
        return true;
      }
    }
    return false;
  }

  function ensureTrailingEmptyRow() {
    if (!bookingBody || readOnly || usesIncomeItems() || syncingBookingFromItems) {
      return;
    }
    var rows = bookingBody.querySelectorAll('.dg-voucher-split__row');
    if (rows.length === 0) {
      addBookingRow();
      return;
    }
    if (!hasEmptyLine()) {
      addBookingRow();
    }
  }

  function getReverseChargeType() {
    return reverseChargeTypeSelect ? reverseChargeTypeSelect.value : '';
  }

  function getReverseChargeTypeConfig() {
    var type = getReverseChargeType();
    if (!type || !rcConfig.types) {
      return null;
    }
    return rcConfig.types[type] || null;
  }

  function isReverseCharge() {
    return getReverseChargeType() !== '';
  }

  function collectBookingLines() {
    var lines = [];
    if (!bookingBody) {
      return lines;
    }
    bookingBody.querySelectorAll('.dg-voucher-split__row').forEach(function (row) {
      if (isLineEmpty(row)) {
        return;
      }
      var accountField = row.querySelector('.dg-voucher-split-account');
      var grossField = row.querySelector('.dg-voucher-split-gross');
      var taxField = row.querySelector('select[name*="[tax_rate]"], .dg-voucher-split-tax');
      lines.push({
        account_number: (accountField && accountField.value || '').replace(/\D/g, ''),
        gross_amount: grossField ? grossField.value : '',
        tax_rate: taxField ? taxField.value : '19',
      });
    });
    return lines;
  }

  function applyLineTaxRateOptions() {
    if (!bookingBody) {
      return;
    }
    var typeCfg = getReverseChargeTypeConfig();
    var allowed = typeCfg ? typeCfg.allowedTaxRates : [0, 7, 19];
    var defaultRate = typeCfg ? String(typeCfg.defaultTaxRate || 19) : '19';
    bookingBody.querySelectorAll('.dg-voucher-split-tax, select[name*="[tax_rate]"]').forEach(function (taxField) {
      var current = parseInt(taxField.value, 10);
      taxField.innerHTML = allowed.map(function (rate) {
        return '<option value="' + rate + '">' + rate + ' %</option>';
      }).join('');
      taxField.value = allowed.indexOf(current) >= 0 ? String(current) : defaultRate;
    });
  }

  function renderReverseChargePreview(data) {
    var lineKinds = rcConfig.lineKinds || {};
    if (rcPostingsBody) {
      var rows = (data && data.lines) || [];
      if (rows.length === 0) {
        rcPostingsBody.innerHTML = '<tr><td colspan="5" class="dg-table__empty">Noch keine Buchungszeilen erfasst.</td></tr>';
      } else {
        rcPostingsBody.innerHTML = rows.map(function (line) {
          var side = line.posting_side === 'credit' ? 'H' : 'S';
          var kind = lineKinds[line.line_kind] || '';
          var label = line.account_name || line.description || kind;
          return (
            '<tr>' +
              '<td>' + escapeHtml(side) + '</td>' +
              '<td>' + escapeHtml(line.account_number || '') + '</td>' +
              '<td>' + escapeHtml(label) + '</td>' +
              '<td class="dg-table__num">' + escapeHtml(line.gross_amount || '0,00') + ' €</td>' +
              '<td>' + escapeHtml(line.ustva_kz || '') + '</td>' +
            '</tr>'
          );
        }).join('');
      }
    }
    if (rcUstvaBody) {
      var positions = (data && data.ustva_positions) || [];
      if (positions.length === 0) {
        rcUstvaBody.innerHTML = '<tr><td colspan="3" class="dg-table__empty">—</td></tr>';
      } else {
        rcUstvaBody.innerHTML = positions.map(function (pos) {
          return (
            '<tr>' +
              '<td>' + escapeHtml(pos.kz || '') + '</td>' +
              '<td class="dg-table__num">' + (pos.net > 0 ? escapeHtml(formatAmount(pos.net)) + ' €' : '—') + '</td>' +
              '<td class="dg-table__num">' + (pos.tax > 0 ? escapeHtml(formatAmount(pos.tax)) + ' €' : '—') + '</td>' +
            '</tr>'
          );
        }).join('');
      }
    }
  }

  function refreshReverseChargePreview() {
    var type = getReverseChargeType();
    if (!type) {
      if (rcPanels) {
        rcPanels.hidden = true;
      }
      renderReverseChargePreview(null);
      return;
    }
    if (rcPanels) {
      rcPanels.hidden = false;
    }
    fetch(apiUrl + '?action=reverse_charge_preview', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        type: type,
        lines: collectBookingLines(),
      }),
    }).then(function (response) {
      return response.text().then(function (text) {
        var payload = null;
        if (text) {
          payload = JSON.parse(text);
        }
        if (response.ok && payload && payload.success) {
          renderReverseChargePreview(payload.data);
        }
      });
    }).catch(function () {
      renderReverseChargePreview(null);
    });
  }

  var debouncedPreview = function () {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(refreshReverseChargePreview, 300);
  };

  function syncReverseChargeUi() {
    var reverse = isReverseCharge();
    var typeCfg = getReverseChargeTypeConfig();
    if (taxKeyInput) {
      taxKeyInput.value = reverse ? (rcConfig.taxKey || '94') : '';
    }
    if (bookingAmountHeader) {
      bookingAmountHeader.textContent = reverse ? 'Betrag netto' : 'Brutto';
    }
    if (reverseChargeHint) {
      reverseChargeHint.textContent = typeCfg
        ? (typeCfg.hint || '')
        : 'Bei EU-/Drittlandsleistungen oder Bauleistungen mit Steuerschuldnerschaft des Leistungsempfängers wählen.';
    }
    if (grossHint) {
      grossHint.textContent = reverse
        ? 'Summe der Rechnungsbeträge (netto, wie auf der Lieferantenrechnung).'
        : 'Wird aus den Buchungszeilen berechnet.';
    }
    if (netHint) {
      netHint.textContent = reverse
        ? 'Bei §13b entspricht der Nettobetrag der Summe der Zeilenbeträge.'
        : 'Wird aus den Buchungszeilen berechnet.';
    }
    if (taxHint) {
      taxHint.textContent = reverse
        ? 'Bei §13b: geschuldete Umsatzsteuer je Satz (Vorsteuer verrechnet sich).'
        : 'Enthaltene Umsatzsteuer je Steuersatz aus den Buchungszeilen.';
    }
    applyLineTaxRateOptions();
  }

  function calcLineAmounts(gross, rate) {
    if (isReverseCharge()) {
      var net = gross;
      var tax = rate > 0 ? Math.round(gross * rate / 100 * 100) / 100 : 0;
      return { net: net, tax: tax };
    }
    if (rate > 0) {
      var netInclusive = Math.round((gross / (1 + rate / 100)) * 100) / 100;
      return {
        net: netInclusive,
        tax: Math.round((gross - netInclusive) * 100) / 100,
      };
    }
    return { net: gross, tax: 0 };
  }

  function syncTotalsFromLines() {
    if (!bookingBody || !grossInput) {
      return;
    }
    var grossSum = 0;
    var netSum = 0;
    var taxSum = 0;
    var taxByRate = { 0: 0, 7: 0, 19: 0 };
    bookingBody.querySelectorAll('.dg-voucher-split__row').forEach(function (row) {
      var grossField = row.querySelector('.dg-voucher-split-gross');
      var taxField = row.querySelector('select[name*="[tax_rate]"], .dg-voucher-split-tax');
      var gross = grossField ? parseAmount(grossField.value) : 0;
      if (gross <= 0) {
        return;
      }
      var rate = taxField ? parseInt(taxField.value, 10) || 0 : 0;
      if (!Object.prototype.hasOwnProperty.call(taxByRate, rate)) {
        rate = 19;
      }
      var amounts = calcLineAmounts(gross, rate);
      grossSum += gross;
      netSum += amounts.net;
      taxSum += amounts.tax;
      taxByRate[rate] += amounts.tax;
    });
    grossInput.value = grossSum > 0 ? formatAmount(grossSum) : '';
    if (netInput) {
      netInput.value = grossSum > 0 ? formatAmount(netSum) : '';
    }
    [0, 7, 19].forEach(function (rate) {
      var el = taxRateEls[rate];
      if (!el) {
        return;
      }
      if (grossSum <= 0) {
        el.textContent = '—';
        return;
      }
      el.textContent = formatAmount(taxByRate[rate] || 0) + ' €';
    });
    if (taxTotalEl) {
      taxTotalEl.textContent = grossSum > 0 ? formatAmount(taxSum) + ' €' : '—';
    }
    if (bookingSumEl) {
      bookingSumEl.textContent = formatAmount(grossSum);
    }
    refreshArapPreview();
  }

  var arapEnabledInput = document.getElementById('dg-voucher-arap-enabled');
  var arapFieldsPanel = document.getElementById('dg-voucher-arap-fields');
  var arapCurrentInput = document.getElementById('dg-voucher-arap-current-percent');
  var arapNextInput = document.getElementById('dg-voucher-arap-next-percent');
  var arapPreviewBody = document.getElementById('dg-voucher-arap-preview-body');
  var arapCurrentLabel = document.getElementById('dg-voucher-arap-current-label');
  var arapNextLabel = document.getElementById('dg-voucher-arap-next-label');
  var arapHint = document.getElementById('dg-voucher-arap-hint');
  var voucherDateInput = document.getElementById('dg-voucher-date');
  var deliveryDateInput = document.getElementById('dg-voucher-delivery-date');
  var accrualConfig = config.accrual || {};

  // Beim Laden gespeicherte Verteilung merken (nur wenn ARAP bereits aktiv war).
  // Daran erkennen wir, dass eine Neuberechnung eine bestehende Verteilung
  // überschreiben würde – dann fragen wir vorher nach.
  var arapSavedCurrent = null;
  if (arapEnabledInput && arapEnabledInput.checked && arapCurrentInput) {
    var arapSavedInit = parseInt(arapCurrentInput.value, 10);
    if (!isNaN(arapSavedInit)) {
      arapSavedCurrent = arapSavedInit;
    }
  }

  function fiscalYearFromDate(value) {
    if (!value) {
      return new Date().getFullYear();
    }
    var parts = String(value).split('-');
    if (parts.length >= 1) {
      var year = parseInt(parts[0], 10);
      if (!isNaN(year) && year > 1900) {
        return year;
      }
    }
    return new Date().getFullYear();
  }

  function accrualAccountForType(voucherType) {
    var accounts = accrualConfig.accounts || { active: '0980', passive: '0990' };
    return incomeVoucherTypes.indexOf(voucherType) !== -1 ? accounts.passive : accounts.active;
  }

  function syncArapYearLabels() {
    var year = fiscalYearFromDate(voucherDateInput ? voucherDateInput.value : '');
    if (arapCurrentLabel) {
      arapCurrentLabel.textContent = String(year) + ' *';
    }
    if (arapNextLabel) {
      arapNextLabel.textContent = String(year + 1);
    }
  }

  function syncArapNextPercent() {
    if (!arapCurrentInput || !arapNextInput) {
      return;
    }
    var current = parseInt(arapCurrentInput.value, 10);
    if (isNaN(current)) {
      current = 100;
    }
    current = Math.max(0, Math.min(100, current));
    arapCurrentInput.value = String(current);
    arapNextInput.value = String(100 - current);
  }

  // Für die ARAP-Verteilung maßgebliches Datum: bevorzugt das Lieferdatum,
  // ersatzweise das Belegdatum.
  function arapBaseDate() {
    if (deliveryDateInput && deliveryDateInput.value) {
      return deliveryDateInput.value;
    }
    return voucherDateInput ? voucherDateInput.value : '';
  }

  // Anteil des aktuellen Jahres: Resttage vom Basisdatum bis Jahresende,
  // geteilt durch die Tage im Jahr, kaufmännisch nach oben gerundet.
  function computeArapCurrentPercent() {
    var raw = arapBaseDate();
    if (!raw) {
      return null;
    }
    var parts = String(raw).split('-');
    if (parts.length < 3) {
      return null;
    }
    var year = parseInt(parts[0], 10);
    var month = parseInt(parts[1], 10);
    var day = parseInt(parts[2], 10);
    if (isNaN(year) || isNaN(month) || isNaN(day)) {
      return null;
    }
    var base = new Date(year, month - 1, day);
    var endOfYear = new Date(year, 11, 31);
    var msPerDay = 86400000;
    var daysRemaining = Math.round((endOfYear.getTime() - base.getTime()) / msPerDay);
    if (daysRemaining < 0) {
      daysRemaining = 0;
    }
    var isLeap = (year % 4 === 0 && year % 100 !== 0) || year % 400 === 0;
    var daysInYear = isLeap ? 366 : 365;
    var percent = Math.ceil((daysRemaining / daysInYear) * 100);
    return Math.max(0, Math.min(100, percent));
  }

  // Schlägt die Verteilung automatisch anhand des Lieferdatums vor.
  function proposeArapDistribution() {
    var percent = computeArapCurrentPercent();
    if (percent === null || !arapCurrentInput) {
      return;
    }
    // Für eine echte Abgrenzung muss ein Anteil fürs Folgejahr übrig bleiben.
    if (percent >= 100) {
      percent = 99;
    }
    arapCurrentInput.value = String(percent);
    syncArapNextPercent();
  }

  // Gibt es eine gespeicherte Verteilung, die eine Neuberechnung überschreiben würde?
  function hasStoredArapDistribution() {
    return arapSavedCurrent !== null;
  }

  // Popup mit Hinweis und zwei Optionen: beibehalten oder neu berechnen.
  function showArapRecalcDialog(message, onRecalc, onKeep) {
    var modal = document.createElement('div');
    modal.className = 'dg-modal dg-arap-recalc-modal';
    modal.innerHTML =
      '<div class="dg-modal__backdrop" data-arap-keep></div>' +
      '<div class="dg-modal__dialog" role="dialog" aria-modal="true" aria-label="Rechnungsabgrenzung" ' +
        'style="width:min(520px,calc(100vw - 32px));grid-template-rows:auto auto auto;">' +
        '<div class="dg-modal__head">' +
          '<strong>Rechnungsabgrenzung (ARAP)</strong>' +
          '<button type="button" class="dg-modal__close" data-arap-keep aria-label="Schließen">&times;</button>' +
        '</div>' +
        '<div style="padding:16px;line-height:1.5;">' + escapeHtml(message) + '</div>' +
        '<div class="dg-modal__foot">' +
          '<button type="button" class="dg-button" data-arap-keep>Alte Verteilung beibehalten</button>' +
          '<button type="button" class="dg-button dg-button--primary" data-arap-recalc>Verteilung neu berechnen</button>' +
        '</div>' +
      '</div>';
    document.body.appendChild(modal);

    function close() {
      if (modal.parentNode) {
        modal.parentNode.removeChild(modal);
      }
      document.removeEventListener('keydown', onKey);
    }
    function keep() {
      close();
      if (typeof onKeep === 'function') {
        onKeep();
      }
    }
    function recalc() {
      close();
      if (typeof onRecalc === 'function') {
        onRecalc();
      }
    }
    function onKey(event) {
      if (event.key === 'Escape') {
        keep();
      }
    }

    modal.querySelectorAll('[data-arap-keep]').forEach(function (el) {
      el.addEventListener('click', keep);
    });
    var recalcBtn = modal.querySelector('[data-arap-recalc]');
    if (recalcBtn) {
      recalcBtn.addEventListener('click', recalc);
      recalcBtn.focus();
    }
    document.addEventListener('keydown', onKey);
  }

  function collectBookingLinesForPreview() {
    var lines = [];
    if (!bookingBody) {
      return lines;
    }
    bookingBody.querySelectorAll('.dg-voucher-split__row').forEach(function (row) {
      var accountField = row.querySelector('.dg-voucher-split-account');
      var queryField = row.querySelector('.dg-voucher-split-account-query');
      var grossField = row.querySelector('.dg-voucher-split-gross');
      var taxField = row.querySelector('select[name*="[tax_rate]"], .dg-voucher-split-tax');
      var account = accountField ? (accountField.value || '').replace(/\D/g, '') : '';
      var gross = grossField ? parseAmount(grossField.value) : 0;
      if (account === '' || gross <= 0) {
        return;
      }
      var rate = taxField ? parseInt(taxField.value, 10) || 19 : 19;
      var label = queryField ? queryField.value : account;
      var name = label.indexOf(' — ') >= 0 ? label.split(' — ').slice(1).join(' — ') : '';
      lines.push({
        account_number: account,
        account_name: name,
        gross_amount: gross,
        tax_rate: rate,
      });
    });
    return lines;
  }

  function refreshArapPreview() {
    if (!arapPreviewBody || readOnly) {
      return;
    }
    if (!arapEnabledInput || !arapEnabledInput.checked) {
      return;
    }
    syncArapNextPercent();
    var currentPercent = arapCurrentInput ? parseInt(arapCurrentInput.value, 10) || 0 : 0;
    var nextPercent = 100 - currentPercent;
    if (nextPercent < 1) {
      arapPreviewBody.innerHTML = '<tr><td colspan="4" class="dg-muted">Für die Abgrenzung muss ein Anteil fürs Folgejahr übrig bleiben (max. 99 % im aktuellen Jahr).</td></tr>';
      return;
    }
    var lines = collectBookingLinesForPreview();
    if (lines.length === 0) {
      arapPreviewBody.innerHTML = '<tr><td colspan="4" class="dg-muted">Buchungszeilen erfassen, dann erscheint die Verteilung.</td></tr>';
      return;
    }
    var year = fiscalYearFromDate(voucherDateInput ? voucherDateInput.value : '');
    var nextYear = year + 1;
    var accrualAccount = accrualAccountForType(getVoucherType());
    var rowsHtml = [];
    lines.forEach(function (line) {
      var currentGross = Math.round(line.gross_amount * currentPercent) / 100;
      var nextGross = Math.round((line.gross_amount - currentGross) * 100) / 100;
      rowsHtml.push(
        '<tr>' +
          '<td>' + escapeHtml(currentPercent + ' % ' + year) + '</td>' +
          '<td>' + escapeHtml(line.account_number) + '</td>' +
          '<td>' + escapeHtml(line.account_name || '—') + '</td>' +
          '<td class="dg-table__num">' + escapeHtml(formatAmount(currentGross)) + ' €</td>' +
        '</tr>'
      );
      if (nextGross > 0) {
        rowsHtml.push(
          '<tr>' +
            '<td>' + escapeHtml(nextPercent + ' % ' + nextYear) + '</td>' +
            '<td>' + escapeHtml(accrualAccount) + '</td>' +
            '<td>' + escapeHtml(incomeVoucherTypes.indexOf(getVoucherType()) !== -1 ? 'Passive Rechnungsabgrenzung (PRAP)' : 'Aktive Rechnungsabgrenzung (ARAP)') + '</td>' +
            '<td class="dg-table__num">' + escapeHtml(formatAmount(nextGross)) + ' €</td>' +
          '</tr>'
        );
      }
    });
    arapPreviewBody.innerHTML = rowsHtml.join('');
  }

  function syncArapUi() {
    var reverseChargeActive = getReverseChargeType() !== '';
    if (arapEnabledInput) {
      arapEnabledInput.disabled = reverseChargeActive;
      if (reverseChargeActive) {
        arapEnabledInput.checked = false;
      }
    }
    if (arapFieldsPanel) {
      arapFieldsPanel.hidden = !arapEnabledInput || !arapEnabledInput.checked;
    }
    syncArapYearLabels();
    refreshArapPreview();
  }

  function onArapChange() {
    // Beim Aktivieren automatisch eine Verteilung nach Lieferdatum vorschlagen.
    if (arapEnabledInput && arapEnabledInput.checked) {
      if (hasStoredArapDistribution()) {
        syncArapUi();
        showArapRecalcDialog(
          'Für diesen Beleg ist bereits eine ARAP-Verteilung gespeichert. Möchten Sie die gespeicherte Verteilung beibehalten oder anhand des Lieferdatums neu berechnen?',
          function () { proposeArapDistribution(); refreshArapPreview(); },
          function () { refreshArapPreview(); }
        );
        return;
      }
      proposeArapDistribution();
    }
    syncArapUi();
  }

  function onArapDateChange() {
    syncArapYearLabels();
    if (arapEnabledInput && arapEnabledInput.checked) {
      if (hasStoredArapDistribution()) {
        showArapRecalcDialog(
          'Das Datum wurde geändert. Möchten Sie die gespeicherte ARAP-Verteilung beibehalten oder anhand des neuen Lieferdatums neu berechnen?',
          function () { proposeArapDistribution(); refreshArapPreview(); },
          function () { refreshArapPreview(); }
        );
        return;
      }
      proposeArapDistribution();
    }
    refreshArapPreview();
  }

  function onArapPercentChange() {
    syncArapNextPercent();
    refreshArapPreview();
  }

  function onBookingInput() {
    if (usesIncomeItems() || syncingBookingFromItems) {
      return;
    }
    ensureTrailingEmptyRow();
    syncTotalsFromLines();
    debouncedPreview();
  }

  function onReverseChargeChange() {
    syncReverseChargeUi();
    syncArapUi();
    syncTotalsFromLines();
    refreshReverseChargePreview();
  }

  function voucherTypeQuery() {
    var type = getVoucherType();
    return type ? '&voucher_type=' + encodeURIComponent(type) : '';
  }

  function revalidateBookingAccounts() {
    if (!bookingBody || readOnly) {
      return;
    }
    bookingBody.querySelectorAll('.dg-voucher-split__row').forEach(function (row) {
      var accountField = row.querySelector('.dg-voucher-split-account');
      var queryField = row.querySelector('.dg-voucher-split-account-query');
      if (!accountField || !queryField) {
        return;
      }
      var number = (accountField.value || '').replace(/\D/g, '');
      if (number === '') {
        return;
      }
      fetchJson(apiUrl + '?action=account&number=' + encodeURIComponent(number) + accountLookupSuffix())
        .then(function (result) {
          if (result.ok && result.data && result.data.success && result.data.data) {
            var acc = result.data.data;
            queryField.value = formatAccountLabel(acc.account_number, acc.name);
            queryField.classList.remove('dg-input--error');
            applyAccountTaxRate(row, acc.suggested_tax_rate);
            return;
          }
          accountField.value = '';
          queryField.value = '';
          queryField.classList.add('dg-input--error');
          onBookingInput();
        })
        .catch(function () {
          accountField.value = '';
          queryField.value = '';
          queryField.classList.add('dg-input--error');
          onBookingInput();
        });
    });
  }

  function accountQuerySuffix(taxRate) {
    var suffix = voucherTypeQuery();
    if (taxRate !== undefined && taxRate !== null && taxRate !== '') {
      suffix += '&tax_rate=' + encodeURIComponent(String(taxRate));
    }
    return suffix;
  }

  function accountLookupSuffix() {
    return voucherTypeQuery();
  }

  function rowTaxRate(row) {
    if (!row) {
      return '';
    }
    var taxField = row.querySelector('select[name*="[tax_rate]"], .dg-voucher-split-tax');
    return taxField ? taxField.value : '';
  }

  function applyAccountTaxRate(row, taxRate) {
    if (!row || taxRate === undefined || taxRate === null || taxRate === '') {
      return;
    }
    var taxField = row.querySelector('select[name*="[tax_rate]"], .dg-voucher-split-tax');
    if (!taxField) {
      return;
    }
    var rate = String(taxRate);
    if (taxField.querySelector('option[value="' + rate + '"]')) {
      taxField.value = rate;
    }
  }

  function setRowAccount(row, number, name, taxRate) {
    if (!row) {
      return;
    }
    var accountField = row.querySelector('.dg-voucher-split-account');
    var queryField = row.querySelector('.dg-voucher-split-account-query');
    if (accountField) {
      accountField.value = number;
    }
    if (queryField) {
      queryField.value = formatAccountLabel(number, name);
      queryField.classList.remove('dg-input--error');
    }
    applyAccountTaxRate(row, taxRate);
    onBookingInput();
  }

  function formatAccountLabel(number, name) {
    if (!number) {
      return '';
    }
    return name ? String(number) + ' — ' + String(name) : String(number);
  }

  function lookupAccount(number, hintEl) {
    if (!number || number.length < 3) {
      return;
    }
    fetchJson(apiUrl + '?action=account&number=' + encodeURIComponent(number))
      .then(function (result) {
        if (!hintEl) {
          return;
        }
        if (result.ok && result.data && result.data.success && result.data.data) {
          var acc = result.data.data;
          var baseHint = hintEl.getAttribute('data-base-hint') || '';
          hintEl.textContent = baseHint ? baseHint + ' — ' + acc.name : acc.name;
          hintEl.classList.remove('dg-field-hint--error');
        } else {
          hintEl.classList.add('dg-field-hint--error');
        }
      })
      .catch(function () {
        if (hintEl) {
          hintEl.classList.add('dg-field-hint--error');
        }
      });
  }

  function renderAccountResults(container, items, onPick) {
    if (!container) {
      return;
    }
    if (!items || items.length === 0) {
      container.hidden = true;
      container.innerHTML = '';
      return;
    }
    container.hidden = false;
    container.innerHTML = items.map(function (item) {
      return (
        '<button type="button" class="dg-account-search-results__item" data-number="' + escapeHtml(item.account_number) + '" data-name="' + escapeHtml(item.name) + '" data-tax-rate="' + escapeHtml(item.suggested_tax_rate != null ? String(item.suggested_tax_rate) : '') + '">' +
          '<span class="dg-account-search-results__number">' + escapeHtml(item.account_number) + '</span>' +
          '<span class="dg-account-search-results__name">' + escapeHtml(item.name) + '</span>' +
        '</button>'
      );
    }).join('');
    container.querySelectorAll('.dg-account-search-results__item').forEach(function (btn) {
      btn.addEventListener('click', function () {
        onPick(
          btn.getAttribute('data-number') || '',
          btn.getAttribute('data-name') || '',
          btn.getAttribute('data-tax-rate') || ''
        );
        container.hidden = true;
        container.innerHTML = '';
      });
    });
  }

  function searchAccounts(query, container, onPick, taxRate) {
    if (!container || query.length < 2) {
      renderAccountResults(container, []);
      return;
    }
    fetchJson(apiUrl + '?action=account_search&q=' + encodeURIComponent(query) + accountQuerySuffix(taxRate))
      .then(function (result) {
        if (result.ok && result.data && result.data.success) {
          renderAccountResults(container, (result.data.data && result.data.data.items) || [], onPick);
        }
      })
      .catch(function () {
        renderAccountResults(container, []);
      });
  }

  function renderContactResults(items) {
    if (!contactResults) {
      return;
    }
    if (!items || items.length === 0) {
      contactResults.hidden = false;
      contactResults.innerHTML = '<div class="dg-voucher-contact-results__empty">Keine passenden Kontakte gefunden.</div>';
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
        syncContactValidation();
      });
    });
  }

  function searchContacts(query) {
    if (!contactResults || query.length < 1) {
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

  function reindexBookingRows() {
    if (!bookingBody) {
      return;
    }
    var rows = bookingBody.querySelectorAll('.dg-voucher-split__row');
    rows.forEach(function (row, index) {
      row.setAttribute('data-line-index', String(index));
      var accountField = row.querySelector('.dg-voucher-split-account');
      var grossField = row.querySelector('.dg-voucher-split-gross');
      var taxField = row.querySelector('select[name*="[tax_rate]"], .dg-voucher-split-tax');
      if (accountField) {
        accountField.name = 'lines[' + index + '][account_number]';
      }
      if (grossField) {
        grossField.name = 'lines[' + index + '][gross_amount]';
      }
      if (taxField) {
        taxField.name = 'lines[' + index + '][tax_rate]';
      }
    });
  }

  function bindBookingRow(row) {
    if (!row || readOnly) {
      return;
    }
    var accountField = row.querySelector('.dg-voucher-split-account');
    var queryField = row.querySelector('.dg-voucher-split-account-query');
    var resultsEl = row.querySelector('.dg-voucher-split-search-results');
    var grossField = row.querySelector('.dg-voucher-split-gross');
    var removeBtn = row.querySelector('.dg-voucher-split-remove');

    if (queryField && accountField) {
      queryField.addEventListener('focus', function () {
        queryField.select();
      });

      queryField.addEventListener('input', debounce(function () {
        var q = (queryField.value || '').trim();

        if (q === '') {
          accountField.value = '';
          queryField.classList.remove('dg-input--error');
          renderAccountResults(resultsEl, []);
          onBookingInput();
          return;
        }

        var pickedMatch = q.match(/^(\d+)\s*—\s*(.+)$/);
        if (pickedMatch) {
          accountField.value = pickedMatch[1];
          onBookingInput();
          return;
        }

        if (/^\d+$/.test(q)) {
          accountField.value = q;
          queryField.classList.remove('dg-input--error');
          renderAccountResults(resultsEl, []);
          if (q.length >= 3) {
            fetchJson(apiUrl + '?action=account&number=' + encodeURIComponent(q) + accountLookupSuffix())
              .then(function (result) {
                if (queryField.value.trim() !== q) {
                  return;
                }
                if (result.ok && result.data && result.data.success && result.data.data) {
                  var acc = result.data.data;
                  queryField.value = formatAccountLabel(acc.account_number, acc.name);
                  accountField.value = String(acc.account_number || '');
                  queryField.classList.remove('dg-input--error');
                  applyAccountTaxRate(row, acc.suggested_tax_rate);
                } else if (q.length >= 4) {
                  queryField.classList.add('dg-input--error');
                }
                onBookingInput();
              })
              .catch(function () {
                if (queryField.value.trim() === q) {
                  queryField.classList.add('dg-input--error');
                }
              });
          } else {
            onBookingInput();
          }
          return;
        }

        accountField.value = '';
        searchAccounts(q, resultsEl, function (number, name, taxRate) {
          setRowAccount(row, number, name, taxRate);
        }, rowTaxRate(row));
      }, 300));
    }

    if (grossField) {
      grossField.addEventListener('input', onBookingInput);
    }

    var taxField = row.querySelector('select[name*="[tax_rate]"], .dg-voucher-split-tax');
    if (taxField) {
      taxField.addEventListener('change', function () {
        onBookingInput();
        var q = (queryField && queryField.value || '').trim();
        if (queryField && resultsEl && q.length >= 2 && !/^\d+$/.test(q) && q.indexOf(' — ') === -1) {
          searchAccounts(q, resultsEl, function (number, name, taxRate) {
            setRowAccount(row, number, name, taxRate);
          }, rowTaxRate(row));
        } else if (accountField && queryField && (accountField.value || '').replace(/\D/g, '') !== '') {
          revalidateBookingAccounts();
        }
      });
    }

    if (removeBtn) {
      removeBtn.addEventListener('click', function () {
        var rows = bookingBody ? bookingBody.querySelectorAll('.dg-voucher-split__row') : [];
        if (rows.length <= 1) {
          if (accountField) {
            accountField.value = '';
          }
          if (queryField) {
            queryField.value = '';
            queryField.classList.remove('dg-input--error');
          }
          if (grossField) {
            grossField.value = '';
          }
          onBookingInput();
          return;
        }
        row.remove();
        reindexBookingRows();
        onBookingInput();
      });
    }
  }

  function addBookingRow() {
    if (!bookingBody || !bookingRowTemplate) {
      return;
    }
    var clone = bookingRowTemplate.content.cloneNode(true);
    var row = clone.querySelector('.dg-voucher-split__row');
    if (!row) {
      return;
    }
    var taxField = row.querySelector('.dg-voucher-split-tax');
    if (taxField) {
      var typeCfg = getReverseChargeTypeConfig();
      var allowed = typeCfg ? typeCfg.allowedTaxRates : [0, 7, 19];
      var defaultRate = typeCfg ? String(typeCfg.defaultTaxRate || 19) : '19';
      taxField.innerHTML = allowed.map(function (rate) {
        return '<option value="' + rate + '">' + rate + ' %</option>';
      }).join('');
      taxField.value = defaultRate;
    }
    bookingBody.appendChild(row);
    reindexBookingRows();
    bindBookingRow(row);
  }

  if (typeSelect) {
    typeSelect.addEventListener('change', function () {
      if (typeHint) {
        typeHint.textContent = typeDescriptions[typeSelect.value] || '';
      }
      syncInvoiceNumberField();
      syncIncomeMode();
      syncArapUi();
      revalidateBookingAccounts();
    });
  }

  function setInvoiceRequired(required) {
    var invoiceInput = document.getElementById('dg-voucher-invoice-number');
    var invoiceLabel = document.getElementById('dg-voucher-invoice-label');
    if (invoiceInput) {
      if (required) {
        invoiceInput.setAttribute('required', 'required');
      } else {
        invoiceInput.removeAttribute('required');
      }
    }
    if (invoiceLabel) {
      invoiceLabel.textContent = required ? 'Rechnungsnummer *' : 'Rechnungsnummer';
    }
  }

  function syncInvoiceNumberField() {
    var invoiceInput = document.getElementById('dg-voucher-invoice-number');
    var invoiceHint = document.getElementById('dg-voucher-invoice-hint');
    var autoTypes = config.autoInvoiceTypes || {};
    if (!invoiceInput || !typeSelect || readOnly) {
      return;
    }

    var rangeLabel = autoTypes[typeSelect.value] || '';
    var isAuto = rangeLabel !== '';
    var isSaved = invoiceInput.getAttribute('data-saved-invoice') === '1';
    var isExpense = typeSelect.value === 'expense' || typeSelect.value === 'expense_reduction';

    if (isAuto) {
      // Automatische Nummer aus dem Nummernkreis – nie Pflichtfeld.
      setInvoiceRequired(false);
      invoiceInput.readOnly = true;
      invoiceInput.classList.add('dg-input--computed');
      invoiceInput.removeAttribute('name');
      if (invoiceHint) {
        invoiceHint.hidden = false;
        if (isSaved) {
          invoiceHint.innerHTML = 'Aus dem Nummernkreis „' + escapeHtml(rangeLabel) + '“, nachträglich nicht änderbar.';
        } else {
          invoiceHint.innerHTML = 'Wird beim Speichern automatisch aus dem Nummernkreis „' + escapeHtml(rangeLabel) + '“ vergeben (Vorschau).';
        }
      }
      if (!isSaved) {
        fetchJson(apiUrl + '?action=invoice_number_preview&voucher_type=' + encodeURIComponent(typeSelect.value))
          .then(function (result) {
            if (result.ok && result.data && result.data.success && result.data.data && result.data.data.number) {
              invoiceInput.value = result.data.data.number;
            }
          })
          .catch(function () {});
      }
      invoiceInput.setAttribute('data-was-auto', '1');
      return;
    }

    invoiceInput.readOnly = false;
    invoiceInput.classList.remove('dg-input--computed');
    invoiceInput.setAttribute('name', 'invoice_number');
    if (invoiceInput.getAttribute('data-was-auto') === '1') {
      invoiceInput.value = '';
    }
    if (invoiceHint) {
      invoiceHint.hidden = true;
      invoiceHint.textContent = '';
    }
    invoiceInput.setAttribute('data-was-auto', '0');
    // Bei Ausgaben ist die Rechnungsnummer Pflicht.
    setInvoiceRequired(isExpense);
  }

  if (typeSelect) {
    syncInvoiceNumberField();
    var invoiceInputInit = document.getElementById('dg-voucher-invoice-number');
    if (invoiceInputInit && (config.autoInvoiceTypes || {})[typeSelect.value]) {
      invoiceInputInit.setAttribute('data-was-auto', '1');
    }
  }

  if (invoiceItemsBody) {
    invoiceItemsBody.querySelectorAll('.dg-voucher-items__row').forEach(bindInvoiceItemRow);
    ensureTrailingInvoiceItemRow();
    syncInvoiceItemsSum();
  }

  syncIncomeMode();

  if (bookingBody) {
    bookingBody.querySelectorAll('.dg-voucher-split__row').forEach(bindBookingRow);
    ensureTrailingEmptyRow();
    syncReverseChargeUi();
    syncTotalsFromLines();
    refreshReverseChargePreview();
  }

  if (reverseChargeTypeSelect) {
    reverseChargeTypeSelect.addEventListener('change', onReverseChargeChange);
  }

  if (arapEnabledInput) {
    arapEnabledInput.addEventListener('change', onArapChange);
  }
  if (arapCurrentInput) {
    arapCurrentInput.addEventListener('input', onArapPercentChange);
    arapCurrentInput.addEventListener('change', onArapPercentChange);
  }
  if (voucherDateInput) {
    voucherDateInput.addEventListener('change', onArapDateChange);
  }
  if (deliveryDateInput) {
    deliveryDateInput.addEventListener('change', onArapDateChange);
  }
  syncArapUi();
  syncContactValidation();

  var paymentStatusSelect = document.getElementById('dg-voucher-payment-status');
  var paymentStatusHint = document.getElementById('dg-voucher-payment-status-hint');
  var paymentStatusHints = config.paymentStatusHints || {};
  if (paymentStatusSelect && paymentStatusHint) {
    paymentStatusSelect.addEventListener('change', function () {
      paymentStatusHint.textContent = paymentStatusHints[paymentStatusSelect.value] || '';
    });
  }

  if (contactSearch && !readOnly) {
    contactSearch.addEventListener('input', debounce(function () {
      if (contactIdInput) {
        contactIdInput.value = '';
      }
      searchContacts(contactSearch.value.trim());
      syncContactValidation();
    }, 300));

    contactSearch.addEventListener('focus', function () {
      var value = contactSearch.value.trim();
      if (value.length >= 1) {
        searchContacts(value);
      }
    });

    contactSearch.addEventListener('blur', function () {
      syncContactValidation();
    });

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

  document.addEventListener('click', function (event) {
    document.querySelectorAll('.dg-voucher-split-search-results').forEach(function (el) {
      var wrap = el.closest('.dg-voucher-split-account-wrap');
      if (!el.hidden && wrap && !wrap.contains(event.target)) {
        el.hidden = true;
      }
    });
    document.querySelectorAll('.dg-voucher-items-search-results').forEach(function (el) {
      var wrap = el.closest('.dg-voucher-items-article-wrap');
      if (!el.hidden && wrap && !wrap.contains(event.target)) {
        el.hidden = true;
      }
    });
  });

  form.addEventListener('submit', function (event) {
    if (!readOnly && !hasSelectedContact()) {
      event.preventDefault();
      syncContactValidation();
      if (contactSearch) {
        contactSearch.focus();
      }
      return;
    }
    syncReverseChargeUi();
    reindexBookingRows();
    reindexInvoiceItems();
  });
})();
