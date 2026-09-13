(function () {
  'use strict';

  var cfg = window.dgKichel;
  if (!cfg || !cfg.apiUrl || !cfg.csrf) {
    return;
  }

  var fab = document.querySelector('[data-kichel-fab]');
  var panel = document.querySelector('[data-kichel-panel]');
  var closeBtn = document.querySelector('[data-kichel-close]');
  var form = document.querySelector('[data-kichel-form]');
  var input = document.querySelector('[data-kichel-input]');
  var body = document.querySelector('[data-kichel-body]');
  var chips = document.querySelectorAll('[data-kichel-chip]');

  if (!fab || !panel || !form || !input || !body) {
    return;
  }

  function toggle(open) {
    var show = open !== undefined ? open : panel.hidden;
    panel.hidden = !show;
    fab.setAttribute('aria-expanded', show ? 'true' : 'false');
    if (show) {
      input.focus();
    }
  }

  fab.addEventListener('click', function () {
    toggle(panel.hidden);
  });

  if (closeBtn) {
    closeBtn.addEventListener('click', function () {
      toggle(false);
    });
  }

  chips.forEach(function (chip) {
    chip.addEventListener('click', function () {
      input.value = chip.getAttribute('data-kichel-chip') || '';
      form.requestSubmit();
    });
  });

  function appendMessage(text, role) {
    var el = document.createElement('div');
    el.className = 'dg-kichel-msg dg-kichel-msg--' + role;
    el.textContent = text;
    body.appendChild(el);
    body.scrollTop = body.scrollHeight;
    return el;
  }

  function appendSection(title, html) {
    var wrap = document.createElement('div');
    wrap.className = 'dg-kichel-section';
    wrap.innerHTML = '<h4>' + title + '</h4>' + html;
    body.appendChild(wrap);
    body.scrollTop = body.scrollHeight;
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
