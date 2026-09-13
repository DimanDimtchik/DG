(function () {
  'use strict';

  var cfg = window.dgKichel;
  if (!cfg || !cfg.apiUrl || !cfg.csrf) {
    return;
  }

  var STORAGE_MESSAGES = 'dgKichelMessages';
  var STORAGE_OPEN = 'dgKichelPanelOpen';
  var STORAGE_CLOSE_NEXT = 'dgKichelCloseNext';

  var fab = document.querySelector('[data-kichel-fab]');
  var panel = document.querySelector('[data-kichel-panel]');
  var closeBtn = document.querySelector('[data-kichel-close]');
  var form = document.querySelector('[data-kichel-form]');
  var input = document.querySelector('[data-kichel-input]');
  var scrollBody = document.querySelector('[data-kichel-body]');
  var messages = document.querySelector('[data-kichel-messages]');
  var chips = document.querySelectorAll('[data-kichel-chip]');
  var protokollLink = document.querySelector('[data-kichel-protokoll]');

  if (!fab || !panel || !form || !input || !scrollBody || !messages) {
    return;
  }

  function saveState() {
    try {
      sessionStorage.setItem(STORAGE_MESSAGES, messages.innerHTML);
      sessionStorage.setItem(STORAGE_OPEN, panel.hidden ? '0' : '1');
    } catch (e) {
      /* sessionStorage nicht verfügbar */
    }
  }

  function restoreState() {
    try {
      var html = sessionStorage.getItem(STORAGE_MESSAGES);
      if (html) {
        messages.innerHTML = html;
      }
      var closeNext = sessionStorage.getItem(STORAGE_CLOSE_NEXT) === '1';
      if (closeNext) {
        sessionStorage.removeItem(STORAGE_CLOSE_NEXT);
        toggle(false);
        return;
      }
      if (sessionStorage.getItem(STORAGE_OPEN) === '1') {
        toggle(true);
      }
    } catch (e) {
      /* ignore */
    }
  }

  function toggle(open) {
    var show = open !== undefined ? open : panel.hidden;
    panel.hidden = !show;
    fab.setAttribute('aria-expanded', show ? 'true' : 'false');
    if (show) {
      input.focus();
      scrollBody.scrollTop = scrollBody.scrollHeight;
    }
    saveState();
  }

  function bindChips() {
    document.querySelectorAll('[data-kichel-chip]').forEach(function (chip) {
      chip.addEventListener('click', function () {
        input.value = chip.getAttribute('data-kichel-chip') || '';
        form.requestSubmit();
      });
    });
  }

  fab.addEventListener('click', function () {
    toggle(panel.hidden);
  });

  if (closeBtn) {
    closeBtn.addEventListener('click', function () {
      toggle(false);
    });
  }

  if (protokollLink) {
    protokollLink.addEventListener('click', function () {
      try {
        sessionStorage.setItem(STORAGE_MESSAGES, messages.innerHTML);
        sessionStorage.setItem(STORAGE_CLOSE_NEXT, '1');
      } catch (e) {
        /* ignore */
      }
    });
  }

  window.addEventListener('pagehide', saveState);

  bindChips();
  restoreState();

  function appendMessage(text, role) {
    var el = document.createElement('div');
    el.className = 'dg-kichel-msg dg-kichel-msg--' + role;
    el.textContent = text;
    messages.appendChild(el);
    scrollBody.scrollTop = scrollBody.scrollHeight;
    saveState();
    return el;
  }

  function appendSection(title, html) {
    var wrap = document.createElement('div');
    wrap.className = 'dg-kichel-section';
    wrap.innerHTML = '<h4>' + title + '</h4>' + html;
    messages.appendChild(wrap);
    scrollBody.scrollTop = scrollBody.scrollHeight;
    saveState();
  }

  function renderLinks(title, items, labelKey, hrefKey) {
    if (!items || !items.length) {
      return;
    }
    var html = '<ul class="dg-kichel-links">';
    items.forEach(function (item) {
      var label = item[labelKey];
      var href = item[hrefKey];
      if (!href) {
        html += '<li>' + label + '</li>';
      } else {
        html += '<li><a href="' + href + '">' + label + '</a></li>';
      }
    });
    html += '</ul>';
    appendSection(title, html);
  }

  function renderPageLinks(items) {
    if (!items || !items.length) {
      return;
    }
    var html = '';
    items.forEach(function (page) {
      html += '<div class="dg-kichel-page">';
      html += '<strong>' + (page.title || page.slug) + '</strong>';
      html += '<ul class="dg-kichel-links">';
      if (page.view_href) {
        html += '<li><a href="' + page.view_href + '" target="_blank" rel="noopener">' + (page.view_label || 'Ansehen') + '</a></li>';
      }
      if (page.edit_href) {
        html += '<li><a href="' + page.edit_href + '">' + (page.edit_label || 'Bearbeiten') + '</a></li>';
      }
      html += '</ul></div>';
    });
    appendSection('Pflichtseiten — Links', html);
  }

  function renderCodeHits(items) {
    if (!items || !items.length) {
      return;
    }
    var html = '';
    items.forEach(function (hit) {
      html += '<div class="dg-kichel-hit">' + hit.path + ':' + hit.line + '<br>' + hit.snippet + '</div>';
    });
    appendSection('Code-Treffer', html);
  }

  function renderDbHits(items) {
    if (!items || !items.length) {
      return;
    }
    var html = '<ul class="dg-kichel-links">';
    items.forEach(function (hit) {
      var cols = (hit.columns || []).slice(0, 6).join(', ');
      var count = hit.row_count != null ? ' · ' + hit.row_count + ' Zeilen' : '';
      html += '<li><strong>' + hit.table + '</strong>' + count + (cols ? '<br><span class="dg-kichel-hit">' + cols + '</span>' : '') + '</li>';
    });
    html += '</ul>';
    appendSection('Datenbank-Schema', html);
  }

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var query = (input.value || '').trim();
    if (!query) {
      return;
    }

    appendMessage(query, 'user');
    input.value = '';

    var submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
      submitBtn.disabled = true;
    }

    var loading = appendMessage('Kichel denkt nach …', 'bot');

    var fd = new FormData();
    fd.append('_csrf', cfg.csrf);
    fd.append('query', query);

    fetch(cfg.apiUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (res) {
        return res.json();
      })
      .then(function (json) {
        loading.remove();
        saveState();
        if (!json.success) {
          appendMessage(json.message || 'Fehler bei der Anfrage.', 'bot');
          return;
        }
        var data = json.data || {};
        appendMessage(data.answer || 'Keine Antwort.', 'bot');

        var topics = (data.topics || []).map(function (t) {
          return { label: t.title, href: t.href };
        });
        renderPageLinks(data.page_links || []);
        renderLinks('Übersicht', data.overview_links || [], 'label', 'href');
        renderLinks('Fachthemen', topics, 'label', 'href');
        renderLinks('Navigation', data.navigation || [], 'label', 'href');
        renderCodeHits(data.code || []);
        renderDbHits(data.database || []);

        if (data.company && data.company.length) {
          var chtml = '<ul class="dg-kichel-links">';
          data.company.forEach(function (c) {
            chtml += '<li>' + c.label + ': ' + c.value + '</li>';
          });
          chtml += '</ul>';
          appendSection('Firmendaten', chtml);
        }
      })
      .catch(function () {
        loading.remove();
        saveState();
        appendMessage('Verbindungsfehler — bitte erneut versuchen.', 'bot');
      })
      .finally(function () {
        if (submitBtn) {
          submitBtn.disabled = false;
        }
        input.focus();
      });
  });
})();
