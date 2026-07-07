(function () {
  'use strict';

  var config = window.dgBuchhaltungKonten || {};
  var apiUrl = config.apiUrl || '/api/chart-account';
  var accountDigits = config.accountDigits || 4;
  var csrfToken = config.csrf || '';

  var numberInput = document.getElementById('dg-account-number');
  var searchInput = document.getElementById('dg-account-search');
  var searchResults = document.getElementById('dg-account-search-results');
  var hintPanel = document.getElementById('dg-account-hint-panel');
  var emptyPanel = document.getElementById('dg-account-empty');
  var statusEl = document.getElementById('dg-account-status');
  var searchTermsTags = document.getElementById('dg-account-hint-search-tags');
  var searchTermsInput = document.getElementById('dg-account-hint-search-input');
  var searchTermsAddBtn = document.getElementById('dg-account-hint-search-add');
  var searchTermsSaveBtn = document.getElementById('dg-account-hint-search-save');
  var searchTermsStatus = document.getElementById('dg-account-hint-search-status');

  if (!numberInput || !searchInput) {
    return;
  }

  var debounceTimer = null;
  var numberTimer = null;
  var activeRequest = null;
  var currentAccount = null;
  var editableSearchTerms = [];
  var savedSearchTerms = [];

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

  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function setStatus(message, type) {
    if (!statusEl) {
      return;
    }
    if (!message) {
      statusEl.hidden = true;
      statusEl.textContent = '';
      statusEl.className = 'dg-account-status';
      return;
    }
    statusEl.hidden = false;
    statusEl.textContent = message;
    statusEl.className = 'dg-account-status' + (type ? ' dg-account-status--' + type : '');
  }

  function fetchJson(url) {
    if (activeRequest) {
      activeRequest.abort();
    }
    var controller = new AbortController();
    activeRequest = controller;

    return fetch(url, {
      method: 'GET',
      credentials: 'same-origin',
      signal: controller.signal,
      headers: { Accept: 'application/json' },
    }).then(function (response) {
      return response.text().then(function (text) {
        var data = null;
        if (text) {
          try {
            data = JSON.parse(text);
          } catch (parseError) {
            throw new Error('Ungültige Server-Antwort.');
          }
        }
        return { ok: response.ok, status: response.status, data: data };
      });
    }).finally(function () {
      if (activeRequest === controller) {
        activeRequest = null;
      }
    });
  }

  function showEmpty(message) {
    if (hintPanel) {
      hintPanel.hidden = true;
    }
    if (emptyPanel) {
      emptyPanel.hidden = false;
    }
    setStatus(message || '', message ? 'info' : '');
  }

  function showHint(account) {
    if (!hintPanel || !emptyPanel) {
      return;
    }

    emptyPanel.hidden = true;
    hintPanel.hidden = false;
    setStatus('');

    var numberEl = document.getElementById('dg-account-hint-number');
    var nameEl = document.getElementById('dg-account-hint-name');
    var metaEl = document.getElementById('dg-account-hint-meta');
    var summaryEl = document.getElementById('dg-account-hint-summary');

    if (numberEl) {
      numberEl.textContent = account.account_number;
    }
    if (nameEl) {
      nameEl.textContent = account.name;
    }
    if (metaEl) {
      metaEl.textContent = (account.section_label || account.section || '') +
        (account.skr_type ? ' · ' + String(account.skr_type).toUpperCase() : '');
    }
    if (summaryEl) {
      summaryEl.textContent = (account.hints && account.hints.summary) || '';
      summaryEl.hidden = !(account.hints && account.hints.summary);
    }

    renderDigits(account.digit_breakdown || []);
    renderTags('dg-account-hint-features', 'dg-account-hint-features-wrap', account.hints && account.hints.features);
    renderClassification(account.hints && account.hints.classification);
    renderTaxEffects(account.hints && account.hints.tax_effects);
    renderList('dg-account-hint-examples', 'dg-account-hint-examples-wrap', account.hints && account.hints.examples);
    renderList('dg-account-hint-edge', 'dg-account-hint-edge-wrap', account.hints && account.hints.edge_cases);
    renderList('dg-account-hint-deps', 'dg-account-hint-deps-wrap', account.hints && account.hints.dependencies);
    renderSearchTermsEditor(account);
  }

  function normalizeSearchTerm(term) {
    return String(term || '').trim().toLowerCase();
  }

  function searchTermsFromAccount(account) {
    if (account && Array.isArray(account.search_terms)) {
      return account.search_terms.map(normalizeSearchTerm).filter(Boolean);
    }
    var hints = account && account.hints ? account.hints : {};
    if (!Array.isArray(hints.search_terms)) {
      return [];
    }
    return hints.search_terms.map(normalizeSearchTerm).filter(Boolean);
  }

  function setSearchTermsStatus(message, type) {
    if (!searchTermsStatus) {
      return;
    }
    searchTermsStatus.textContent = message || '';
    searchTermsStatus.className = 'dg-field-hint' + (type ? ' dg-account-search-terms__status--' + type : '');
  }

  function searchTermsDirty() {
    if (editableSearchTerms.length !== savedSearchTerms.length) {
      return true;
    }
    var saved = savedSearchTerms.slice().sort().join('\n');
    var current = editableSearchTerms.slice().sort().join('\n');
    return saved !== current;
  }

  function updateSearchTermsSaveState() {
    if (!searchTermsSaveBtn) {
      return;
    }
    searchTermsSaveBtn.hidden = !searchTermsDirty();
    if (!searchTermsDirty()) {
      setSearchTermsStatus('');
    }
  }

  function renderSearchTermsEditor(account) {
    currentAccount = account;
    savedSearchTerms = searchTermsFromAccount(account);
    editableSearchTerms = savedSearchTerms.slice();

    if (!searchTermsTags) {
      return;
    }

    if (!editableSearchTerms.length) {
      searchTermsTags.innerHTML = '<p class="dg-field-hint">Noch keine Suchbegriffe hinterlegt.</p>';
    } else {
      searchTermsTags.innerHTML = editableSearchTerms.map(function (term, index) {
        return (
          '<span class="dg-account-hint__tag dg-account-hint__tag--editable">' +
            '<span>' + escapeHtml(term) + '</span>' +
            '<button type="button" class="dg-account-hint__tag-remove" data-index="' + index + '" aria-label="Begriff entfernen">×</button>' +
          '</span>'
        );
      }).join('');
    }

    searchTermsTags.querySelectorAll('.dg-account-hint__tag-remove').forEach(function (button) {
      button.addEventListener('click', function () {
        var index = parseInt(button.getAttribute('data-index') || '', 10);
        if (Number.isNaN(index)) {
          return;
        }
        editableSearchTerms.splice(index, 1);
        renderSearchTermsEditor(Object.assign({}, currentAccount, { search_terms: editableSearchTerms }));
      });
    });

    if (searchTermsInput) {
      searchTermsInput.value = '';
    }
    updateSearchTermsSaveState();
  }

  function addSearchTermFromInput() {
    if (!searchTermsInput || !currentAccount) {
      return;
    }
    var value = normalizeSearchTerm(searchTermsInput.value);
    if (value.length < 2) {
      setSearchTermsStatus('Mindestens 2 Zeichen.', 'info');
      return;
    }
    if (editableSearchTerms.indexOf(value) !== -1) {
      setSearchTermsStatus('Begriff ist bereits vorhanden.', 'info');
      return;
    }
    editableSearchTerms.push(value);
    searchTermsInput.value = '';
    renderSearchTermsEditor(Object.assign({}, currentAccount, { search_terms: editableSearchTerms }));
    setSearchTermsStatus('Begriff hinzugefügt — bitte speichern.', 'info');
  }

  function saveSearchTerms() {
    if (!currentAccount || !searchTermsSaveBtn) {
      return;
    }
    if (!csrfToken) {
      setSearchTermsStatus('Sitzung abgelaufen. Bitte Seite neu laden.', 'error');
      return;
    }

    searchTermsSaveBtn.disabled = true;
    setSearchTermsStatus('Speichern …', 'loading');

    fetch(apiUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        _csrf: csrfToken,
        action: 'update_search_terms',
        account_number: currentAccount.account_number,
        search_terms: editableSearchTerms,
      }),
    }).then(function (response) {
      return response.text().then(function (text) {
        var data = null;
        if (text) {
          try {
            data = JSON.parse(text);
          } catch (parseError) {
            throw new Error('Ungültige Server-Antwort.');
          }
        }
        return { ok: response.ok, status: response.status, data: data };
      });
    }).then(function (result) {
      if (result.ok && result.data && result.data.success && result.data.data && result.data.data.account) {
        showHint(result.data.data.account);
        setSearchTermsStatus(result.data.message || 'Gespeichert.', 'success');
        return;
      }
      setSearchTermsStatus((result.data && result.data.message) || 'Speichern fehlgeschlagen.', 'error');
    }).catch(function (err) {
      setSearchTermsStatus(err.message || 'Speichern fehlgeschlagen.', 'error');
    }).finally(function () {
      if (searchTermsSaveBtn) {
        searchTermsSaveBtn.disabled = false;
      }
      updateSearchTermsSaveState();
    });
  }

  function renderDigits(breakdown) {
    var container = document.getElementById('dg-account-hint-digits');
    if (!container) {
      return;
    }

    if (!breakdown.length) {
      container.innerHTML = '<p class="dg-field-hint">Keine Ziffernerklärung vorhanden.</p>';
      return;
    }

    container.innerHTML = breakdown.map(function (item) {
      var detail = item.detail ? '<span class="dg-account-hint__digit-detail">' + escapeHtml(item.detail) + '</span>' : '';
      return (
        '<div class="dg-account-hint__digit">' +
          '<span class="dg-account-hint__digit-value">' + escapeHtml(item.value) + '</span>' +
          '<div class="dg-account-hint__digit-text">' +
            '<strong>Ziffer ' + escapeHtml(String(item.digit)) + '</strong>' +
            (item.meaning ? '<span>' + escapeHtml(item.meaning) + '</span>' : '') +
            detail +
          '</div>' +
        '</div>'
      );
    }).join('');
  }

  function renderTags(elementId, wrapId, items) {
    var wrap = document.getElementById(wrapId);
    var el = document.getElementById(elementId);
    if (!wrap || !el) {
      return;
    }

    if (!items || !items.length) {
      wrap.hidden = true;
      el.innerHTML = '';
      return;
    }

    wrap.hidden = false;
    el.innerHTML = items.map(function (item) {
      var label = typeof item === 'string' ? item : (item && item.label ? item.label : String(item));
      return '<span class="dg-account-hint__tag">' + escapeHtml(label.replace(/_/g, ' ')) + '</span>';
    }).join('');
  }

  function renderClassification(classification) {
    var wrap = document.getElementById('dg-account-hint-classification-wrap');
    var el = document.getElementById('dg-account-hint-classification');
    if (!wrap || !el || !classification) {
      if (wrap) {
        wrap.hidden = true;
      }
      return;
    }

    var labels = {
      balance_sheet: 'Bilanz',
      guv: 'GuV',
      eur: 'EÜR',
    };

    var tags = [];
    Object.keys(labels).forEach(function (key) {
      if (classification[key]) {
        tags.push(labels[key]);
      }
    });

    if (!tags.length) {
      wrap.hidden = true;
      return;
    }

    wrap.hidden = false;
    el.innerHTML = tags.map(function (tag) {
      return '<span class="dg-account-hint__tag">' + escapeHtml(tag) + '</span>';
    }).join('');
  }

  function renderTaxEffects(taxEffects) {
    var wrap = document.getElementById('dg-account-hint-tax-wrap');
    var el = document.getElementById('dg-account-hint-tax');
    if (!wrap || !el || !taxEffects) {
      if (wrap) {
        wrap.hidden = true;
      }
      return;
    }

    var labels = {
      ust: 'Umsatzsteuer',
      gewst: 'Gewerbesteuer',
      kst: 'Körperschaftsteuer',
      est: 'Einkommensteuer',
    };

    var rows = Object.keys(labels).filter(function (key) {
      return taxEffects[key];
    });

    if (!rows.length) {
      wrap.hidden = true;
      return;
    }

    wrap.hidden = false;
    el.innerHTML = rows.map(function (key) {
      return (
        '<dt>' + escapeHtml(labels[key]) + '</dt>' +
        '<dd>' + escapeHtml(String(taxEffects[key])) + '</dd>'
      );
    }).join('');
  }

  function renderList(elementId, wrapId, items) {
    var wrap = document.getElementById(wrapId);
    var el = document.getElementById(elementId);
    if (!wrap || !el) {
      return;
    }

    if (!items || !items.length) {
      wrap.hidden = true;
      el.innerHTML = '';
      return;
    }

    wrap.hidden = false;
    el.innerHTML = items.map(function (item) {
      return '<li>' + escapeHtml(String(item)) + '</li>';
    }).join('');
  }

  function apiMessage(result) {
    if (result && result.data && result.data.message) {
      return String(result.data.message);
    }
    if (result && result.status === 403) {
      return 'Keine Berechtigung für Kontenabfragen.';
    }
    return 'Abfrage fehlgeschlagen.';
  }

  function loadAccountByNumber(number) {
    var clean = String(number).replace(/\D/g, '');
    if (!clean) {
      showEmpty();
      return;
    }

    setStatus('Konto wird geladen …', 'loading');

    fetchJson(apiUrl + '?number=' + encodeURIComponent(clean))
      .then(function (result) {
        if (result.ok && result.data && result.data.success && result.data.data && result.data.data.account) {
          showHint(result.data.data.account);
          return;
        }
        showEmpty(result.status === 404 ? 'Konto nicht gefunden.' : apiMessage(result));
      })
      .catch(function (err) {
        if (err.name !== 'AbortError') {
          showEmpty(err.message || 'Konto konnte nicht geladen werden.');
        }
      });
  }

  function renderSearchResults(items) {
    if (!searchResults) {
      return;
    }

    if (!items.length) {
      searchResults.hidden = true;
      searchResults.innerHTML = '';
      setStatus('Keine passenden Konten gefunden.', 'info');
      return;
    }

    setStatus(items.length + (items.length === 1 ? ' Konto gefunden.' : ' Konten gefunden.'), 'info');
    searchResults.hidden = false;
    searchResults.innerHTML = items.map(function (item) {
      return (
        '<button type="button" class="dg-account-search-results__item" data-number="' + escapeHtml(item.account_number) + '">' +
          '<span class="dg-account-search-results__number">' + escapeHtml(item.account_number) + '</span>' +
          '<span class="dg-account-search-results__name">' + escapeHtml(item.name) + '</span>' +
        '</button>'
      );
    }).join('');

    searchResults.querySelectorAll('[data-number]').forEach(function (button) {
      button.addEventListener('click', function () {
        var num = button.getAttribute('data-number') || '';
        numberInput.value = num;
        searchInput.value = '';
        searchResults.hidden = true;
        searchResults.innerHTML = '';
        loadAccountByNumber(num);
      });
    });
  }

  function searchAccounts(query) {
    var trimmed = query.trim();
    if (!trimmed) {
      if (searchResults) {
        searchResults.hidden = true;
        searchResults.innerHTML = '';
      }
      setStatus('');
      return;
    }

    if (trimmed.length < 2) {
      setStatus('Mindestens 2 Zeichen eingeben.', 'info');
      return;
    }

    setStatus('Suche läuft …', 'loading');

    fetchJson(apiUrl + '?q=' + encodeURIComponent(trimmed))
      .then(function (result) {
        if (result.ok && result.data && result.data.success) {
          renderSearchResults((result.data.data && result.data.data.items) || []);
          return;
        }
        renderSearchResults([]);
        setStatus(apiMessage(result), 'error');
      })
      .catch(function (err) {
        if (err.name !== 'AbortError') {
          renderSearchResults([]);
          setStatus(err.message || 'Suche fehlgeschlagen.', 'error');
        }
      });
  }

  numberInput.addEventListener('input', function () {
    clearTimeout(numberTimer);
    var value = numberInput.value;
    numberTimer = setTimeout(function () {
      var clean = value.replace(/\D/g, '');
      if (clean.length >= accountDigits) {
        loadAccountByNumber(clean);
      } else if (!clean) {
        showEmpty();
      } else {
        setStatus('Noch ' + (accountDigits - clean.length) + ' Ziffer(n) bis zur Kontonummer.', 'info');
      }
    }, 300);
  });

  searchInput.addEventListener('input', debounce(function () {
    searchAccounts(searchInput.value);
  }, 300));

  if (searchTermsAddBtn) {
    searchTermsAddBtn.addEventListener('click', addSearchTermFromInput);
  }
  if (searchTermsInput) {
    searchTermsInput.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        addSearchTermFromInput();
      }
    });
  }
  if (searchTermsSaveBtn) {
    searchTermsSaveBtn.addEventListener('click', saveSearchTerms);
  }

  numberInput.addEventListener('keydown', function (event) {
    if (event.key === 'Enter') {
      event.preventDefault();
      loadAccountByNumber(numberInput.value);
    }
  });
})();
