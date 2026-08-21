/**
 * Überträgt Beleg-Entwurf und erkannte Stammdaten zwischen Belegerfassung und neuem Kontakt.
 */
(function () {
  var DRAFT_KEY = 'dg.voucherDraft';
  var SUGGESTION_KEY = 'dg.voucherExtractSuggestion';
  var formFieldsRestoredAt = 0;

  function formToObject(form) {
    var obj = {};
    new FormData(form).forEach(function (value, key) {
      if (Object.prototype.hasOwnProperty.call(obj, key)) {
        if (!Array.isArray(obj[key])) {
          obj[key] = [obj[key]];
        }
        obj[key].push(value);
      } else {
        obj[key] = value;
      }
    });
    return obj;
  }

  function countIndexedRows(obj, prefix) {
    var max = 0;
    var pattern = new RegExp('^' + prefix.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\[(\\d+)\\]');
    Object.keys(obj).forEach(function (key) {
      var match = key.match(pattern);
      if (match) {
        max = Math.max(max, parseInt(match[1], 10) + 1);
      }
    });
    return max;
  }

  function getSuggestion() {
    try {
      var raw = sessionStorage.getItem(SUGGESTION_KEY);
      return raw ? JSON.parse(raw) : null;
    } catch (error) {
      return null;
    }
  }

  function suggestLogin(name) {
    return String(name || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 40);
  }

  function buildContactPrefill(draft) {
    var suggestion = (draft && draft.suggestion) || getSuggestion() || {};
    var supplier = (draft && draft.contactSearch) || (draft && draft.voucherForm && draft.voucherForm.supplier_name) || suggestion.supplier || '';
    return {
      salutation: 'Firma',
      company_name: supplier,
      display_name: supplier,
      customer_number: '',
      supplier_number: (window.dgKontakteForm && window.dgKontakteForm.supplierNumberPreview) || '',
      supplier_customer_number: suggestion.customerNumber || '',
      tax_number: suggestion.taxNumber || '',
      vat_id: suggestion.vatId || '',
      commercial_register: suggestion.commercialRegister || '',
      weee_registration: suggestion.weeeRegistration || '',
      website: suggestion.website || '',
      phone_1: suggestion.phone || '',
      address1_street: suggestion.street || '',
      address1_postal: suggestion.postal || '',
      address1_city: suggestion.city || '',
      contact_role: 'dg_kunde',
      login: suggestLogin(supplier),
      bank_iban: suggestion.iban || '',
      bank_bic: suggestion.bic || '',
      bank_holder: supplier,
    };
  }

  function saveDraft() {
    var form = document.getElementById('dg-voucher-form');
    if (!form) {
      return;
    }
    var draft = {
      version: 1,
      voucherForm: formToObject(form),
      contactSearch: (document.getElementById('dg-voucher-contact-search') || {}).value || '',
      suggestion: getSuggestion(),
      savedAt: Date.now(),
    };
    sessionStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
  }

  function setFieldValue(form, name, value) {
    if (!value) {
      return;
    }
    var fields = form.querySelectorAll('[name="' + name.replace(/"/g, '\\"') + '"]');
    fields.forEach(function (field) {
      if (field.type === 'file' || field.type === 'checkbox') {
        return;
      }
      field.value = value;
      field.dispatchEvent(new Event('input', { bubbles: true }));
      field.dispatchEvent(new Event('change', { bubbles: true }));
    });
  }

  function fillBankAccount(form, prefill) {
    if (!prefill.bank_iban && !prefill.bank_bic) {
      return false;
    }
    var mappings = [
      ['bank_accounts[0][iban]', prefill.bank_iban],
      ['bank_accounts[0][bic]', prefill.bank_bic],
      ['bank_accounts[0][account_holder]', prefill.bank_holder],
      ['bank_accounts[0][label]', 'Girokonto'],
      ['bank_accounts[0][type]', 'giro'],
    ];
    var filled = false;
    mappings.forEach(function (entry) {
      var field = form.querySelector('[name="' + entry[0] + '"]');
      if (!field || !entry[1] || field.value) {
        return;
      }
      if (field.tagName === 'SELECT') {
        field.value = entry[1];
      } else {
        field.value = entry[1];
      }
      field.dispatchEvent(new Event('input', { bubbles: true }));
      field.dispatchEvent(new Event('change', { bubbles: true }));
      filled = true;
    });
    return filled;
  }

  function enrichBankFromIban(form) {
    var card = form.querySelector('[data-bank-card]');
    if (!card) {
      return Promise.resolve(false);
    }
    var ibanField = form.querySelector('[name="bank_accounts[0][iban]"]');
    if (!ibanField || !ibanField.value.replace(/\s/g, '')) {
      return Promise.resolve(false);
    }
    if (window.dgBankAutocomplete) {
      if (typeof window.dgBankAutocomplete.bindBlock === 'function') {
        window.dgBankAutocomplete.bindBlock(card);
      }
      if (typeof window.dgBankAutocomplete.enrichFromIban === 'function') {
        return window.dgBankAutocomplete.enrichFromIban(card, { silent: true });
      }
    }
    return Promise.resolve(false);
  }

  function showPrefillHint() {
    if (document.querySelector('.dg-voucher-contact-prefill-hint')) {
      return;
    }
    var hint = document.createElement('p');
    hint.className = 'dg-flash dg-flash--warning dg-voucher-contact-prefill-hint';
    hint.textContent = 'Stammdaten aus der Belegerfassung wurden übernommen (Lieferantennummer-Vorschau, Kundennummer beim Lieferanten, Bankverbindung). Bitte prüfen und Kontakt speichern — danach kehren Sie automatisch zum Beleg zurück.';
    var header = document.querySelector('.dg-wrap > header');
    if (header && header.nextElementSibling) {
      header.parentNode.insertBefore(hint, header.nextElementSibling);
    }
  }

  function fetchStammdatenPreview() {
    var apiUrl = (window.dgBuchhaltungBelege && window.dgBuchhaltungBelege.apiUrl) || '/api/voucher';
    return fetch(apiUrl + '?action=contact_stammdaten_preview', { credentials: 'same-origin' })
      .then(function (response) {
        return response.json().catch(function () {
          return {};
        });
      })
      .then(function (payload) {
        if (!payload || !payload.success || !payload.data) {
          return {};
        }
        return payload.data;
      })
      .catch(function () {
        return {};
      });
  }

  function resolveSupplierNumberPreview(prefill) {
    if (prefill.supplier_number) {
      return Promise.resolve(prefill.supplier_number);
    }
    if (window.dgKontakteForm && window.dgKontakteForm.supplierNumberPreview) {
      return Promise.resolve(window.dgKontakteForm.supplierNumberPreview);
    }
    return fetchStammdatenPreview().then(function (preview) {
      return preview.supplier_number || '';
    });
  }

  function ensureTemplateRows(bodyId, templateId, rowSelector, count, apiMethod) {
    var body = document.getElementById(bodyId);
    if (!body || count < 1) {
      return;
    }
    var api = window.dgVoucherFormApi || {};
    if (typeof api[apiMethod] === 'function') {
      while (body.querySelectorAll(rowSelector).length < count) {
        api[apiMethod]();
      }
      return;
    }
    var template = document.getElementById(templateId);
    if (!template) {
      return;
    }
    while (body.querySelectorAll(rowSelector).length < count) {
      body.appendChild(template.content.cloneNode(true));
    }
  }

  function shouldRestoreDraft(params) {
    if (params.get('page') !== 'buchhaltung-beleg-form') {
      return false;
    }
    var action = params.get('action') || '';
    return action === 'new' || action === 'edit';
  }

  function restoreSuggestionWithRetry(suggestion, attemptsLeft) {
    if (!suggestion) {
      return;
    }
    if (typeof window.dgVoucherImportRestoreSuggestion === 'function') {
      window.dgVoucherImportRestoreSuggestion(suggestion);
      return;
    }
    if (attemptsLeft > 0) {
      window.setTimeout(function () {
        restoreSuggestionWithRetry(suggestion, attemptsLeft - 1);
      }, 150);
    }
  }

  function restoreBelegDraft(draft, params) {
    var form = document.getElementById('dg-voucher-form');
    if (!form || !draft || !draft.voucherForm) {
      return false;
    }

    var draftKey = Number(draft.savedAt || 0);
    var alreadyRestoredForm = draftKey > 0 && formFieldsRestoredAt === draftKey;

    if (!alreadyRestoredForm) {
      var voucherForm = draft.voucherForm;
      if (voucherForm.voucher_type) {
        setFieldValue(form, 'voucher_type', voucherForm.voucher_type);
      }

      var api = window.dgVoucherFormApi || {};
      if (typeof api.syncInvoiceNumberField === 'function') {
        api.syncInvoiceNumberField();
      }
      if (typeof api.syncIncomeMode === 'function') {
        api.syncIncomeMode();
      }

      ensureTemplateRows('dg-voucher-booking-body', 'dg-voucher-booking-row-template', '.dg-voucher-split__row', countIndexedRows(voucherForm, 'lines'), 'addBookingRow');
      ensureTemplateRows('dg-voucher-invoice-items-body', 'dg-voucher-invoice-item-row-template', '.dg-voucher-items__row', countIndexedRows(voucherForm, 'items'), 'addInvoiceItemRow');

      Object.keys(voucherForm).forEach(function (name) {
        if (name === '_csrf' || name === 'voucher_save' || name === 'contact_id') {
          return;
        }
        setFieldValue(form, name, voucherForm[name]);
      });

      if (voucherForm.id) {
        setFieldValue(form, 'draft_voucher_id', voucherForm.id);
      }

      var contactId = params.get('contact_id');
      if (contactId) {
        setFieldValue(form, 'contact_id', contactId);
      } else if (draft.voucherForm.contact_id) {
        setFieldValue(form, 'contact_id', draft.voucherForm.contact_id);
      }

      var contactSearch = document.getElementById('dg-voucher-contact-search');
      if (contactSearch) {
        var contactLabel = params.get('contact_label') || '';
        var label = contactLabel || contactSearch.value || draft.contactSearch || '';
        if (label) {
          contactSearch.value = label;
          contactSearch.dispatchEvent(new Event('input', { bubbles: true }));
        }
      }

      if (typeof api.onBookingInput === 'function') {
        api.onBookingInput();
      }
      if (typeof api.syncContactValidation === 'function') {
        api.syncContactValidation();
      }

      var extractPanel = document.getElementById('dg-voucher-extract');
      if (extractPanel) {
        extractPanel.hidden = false;
      }

      var status = document.getElementById('dg-voucher-extract-status');
      if (status) {
        status.className = 'dg-voucher-extract__status dg-voucher-extract__status--ok';
        status.innerHTML = params.get('contact_id')
          ? 'Beleg-Entwurf wiederhergestellt. Der neue Kontakt wurde verknüpft — bitte Beleg speichern.'
          : 'Beleg-Entwurf wiederhergestellt.';
      }

      if (draftKey > 0) {
        formFieldsRestoredAt = draftKey;
      }
    }

    restoreSuggestionWithRetry(draft.suggestion, 24);
    return true;
  }

  function initBelegForm() {
    var form = document.getElementById('dg-voucher-form');
    if (!form) {
      return;
    }

    var newContactLink = document.getElementById('dg-voucher-new-contact-link')
      || document.querySelector('.dg-buchhaltung-beleg-form a[href*="page=kontakte"][href*="action=new"]');
    if (newContactLink) {
      newContactLink.addEventListener('mousedown', function () {
        saveDraft();
      });
      newContactLink.addEventListener('click', function () {
        saveDraft();
      });
    }

    form.addEventListener('submit', function () {
      sessionStorage.removeItem(DRAFT_KEY);
      sessionStorage.removeItem(SUGGESTION_KEY);
    });

    var params = new URLSearchParams(window.location.search);
    if (!shouldRestoreDraft(params)) {
      return;
    }
    var draftRaw = sessionStorage.getItem(DRAFT_KEY);
    if (!draftRaw) {
      return;
    }

    var attemptRestore = function (left) {
      var form = document.getElementById('dg-voucher-form');
      if (!form) {
        if (left > 0) {
          window.setTimeout(function () {
            attemptRestore(left - 1);
          }, 120);
        }
        return;
      }
      try {
        var draft = JSON.parse(draftRaw);
        var restored = restoreBelegDraft(draft, params);
        if (!restored && left > 0) {
          window.setTimeout(function () {
            attemptRestore(left - 1);
          }, 120);
        }
      } catch (error) {
        if (left > 0) {
          window.setTimeout(function () {
            attemptRestore(left - 1);
          }, 120);
        }
      }
    };

    window.setTimeout(function () {
      attemptRestore(12);
    }, 80);
  }

  function applyContactPrefill(form, prefill) {
    var filled = [];
    var scalarFields = [
      'salutation', 'company_name', 'display_name', 'customer_number', 'supplier_number', 'supplier_customer_number',
      'tax_number', 'vat_id', 'commercial_register', 'weee_registration', 'website',
      'phone_1', 'address1_street', 'address1_postal', 'address1_city', 'contact_role', 'login',
    ];

    scalarFields.forEach(function (name) {
      var field = form.querySelector('[name="' + name + '"]');
      if (!field || !prefill[name] || field.value) {
        return;
      }
      field.value = prefill[name];
      field.dispatchEvent(new Event('input', { bubbles: true }));
      field.dispatchEvent(new Event('change', { bubbles: true }));
      filled.push(name);
    });

    if (prefill.salutation) {
      var salutation = form.querySelector('[name="salutation"]');
      if (salutation) {
        salutation.value = prefill.salutation;
        salutation.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }

    if (fillBankAccount(form, prefill)) {
      filled.push('bank_accounts');
    }

    return filled;
  }

  function initKontakteForm() {
    var form = document.querySelector('form.dg-form[action*="page=kontakte"]');
    if (!form) {
      return;
    }
    var returnTo = form.querySelector('input[name="return_to"]');
    if (!returnTo || returnTo.value.indexOf('buchhaltung-beleg-form') === -1) {
      return;
    }

    var draftRaw = sessionStorage.getItem(DRAFT_KEY);
    if (!draftRaw) {
      return;
    }

    try {
      var draft = JSON.parse(draftRaw);
      var prefill = buildContactPrefill(draft);
      var filled = applyContactPrefill(form, prefill);

      enrichBankFromIban(form).then(function () {
        return resolveSupplierNumberPreview(prefill);
      }).then(function (supplierNumber) {
        if (supplierNumber) {
          var supplierField = form.querySelector('[name="supplier_number"]');
          if (supplierField && !supplierField.value) {
            supplierField.value = supplierNumber;
            supplierField.dispatchEvent(new Event('input', { bubbles: true }));
            supplierField.dispatchEvent(new Event('change', { bubbles: true }));
            if (filled.indexOf('supplier_number') === -1) {
              filled.push('supplier_number');
            }
          }
        }
        if (filled.length > 0) {
          showPrefillHint();
        }
      });
    } catch (error) {
      /* ignore */
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    initBelegForm();
    initKontakteForm();
  });
})();
