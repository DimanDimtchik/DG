(function () {
  'use strict';

  var cfg = window.dgKichel;
  if (!cfg || !cfg.apiUrl || !cfg.csrf) {
    return;
  }

  var STORAGE_MESSAGES = 'dgKichelMessagesV3';
  var STORAGE_OPEN = 'dgKichelPanelOpen';
  var STORAGE_CLOSE_NEXT = 'dgKichelCloseNext';

  var fab = document.querySelector('[data-kichel-fab]');
  var panel = document.querySelector('[data-kichel-panel]');
  var closeBtn = document.querySelector('[data-kichel-close]');
  var form = document.querySelector('[data-kichel-form]');
  var input = document.querySelector('[data-kichel-input]');
  var scrollBody = document.querySelector('[data-kichel-body]');
  var messages = document.querySelector('[data-kichel-messages]');
  var protokollLink = document.querySelector('[data-kichel-protokoll]');

  if (!fab || !panel || !form || !input || !scrollBody || !messages) {
    return;
  }

  function scrollToBottom() {
    scrollBody.scrollTop = scrollBody.scrollHeight;
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
      scrollToBottom();
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

  messages.addEventListener('click', function (ev) {
    var feedbackBtn = ev.target.closest('[data-kichel-feedback]');
    if (feedbackBtn) {
      ev.preventDefault();
      var wrap = feedbackBtn.closest('.dg-kichel-followup');
      if (wrap) {
        wrap.querySelector('.dg-kichel-feedback').remove();
      }
      if (feedbackBtn.getAttribute('data-kichel-feedback') === 'yes') {
        appendMessage('Freut mich! Bei weiteren Fragen einfach melden.', 'bot');
      } else {
        appendMessage('Alles klar — formuliere die Frage gerne noch einmal.', 'bot');
      }
      return;
    }
    if (ev.target.tagName === 'A') {
      saveState();
    }
  });

  window.addEventListener('pagehide', saveState);

  bindChips();
  restoreState();

  function appendMessage(text, role) {
    var el = document.createElement('div');
    el.className = 'dg-kichel-msg dg-kichel-msg--' + role;
    el.textContent = text;
    messages.appendChild(el);
    scrollToBottom();
    saveState();
    return el;
  }

  function appendHtmlSection(className, html) {
    var wrap = document.createElement('div');
    wrap.className = className;
    wrap.innerHTML = html;
    messages.appendChild(wrap);
    scrollToBottom();
    saveState();
  }

  function renderActionLinks(items) {
    if (!items || !items.length) {
      return;
    }
    var html = '<ul class="dg-kichel-links dg-kichel-links--action">';
    items.forEach(function (item) {
      if (item.href) {
        html += '<li><a class="dg-kichel-action" href="' + item.href + '">' + item.label + '</a></li>';
      }
    });
    html += '</ul>';
    appendHtmlSection('dg-kichel-section dg-kichel-section--action', html);
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
    appendHtmlSection('dg-kichel-section dg-kichel-section--pages', html);
  }

  function renderFollowUp(text) {
    if (!text) {
      return;
    }
    var html = '<p class="dg-kichel-followup__text">' + text + '</p>';
    html += '<div class="dg-kichel-feedback">';
    html += '<button type="button" data-kichel-feedback="yes">Ja, passt</button>';
    html += '<button type="button" data-kichel-feedback="no">Nein, nochmal</button>';
    html += '</div>';
    appendHtmlSection('dg-kichel-followup', html);
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

    var loading = appendMessage('Einen Moment …', 'bot');

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
          renderFollowUp('Soll ich es noch einmal versuchen?');
          return;
        }
        var data = json.data || {};
        appendMessage(data.answer || 'Keine Antwort.', 'bot');
        renderPageLinks(data.page_links || []);
        renderActionLinks(data.action_links || []);
        renderFollowUp(data.follow_up);
      })
      .catch(function () {
        loading.remove();
        saveState();
        appendMessage('Verbindungsfehler — bitte erneut versuchen.', 'bot');
        renderFollowUp('Soll ich es noch einmal versuchen?');
      })
      .finally(function () {
        if (submitBtn) {
          submitBtn.disabled = false;
        }
        input.focus();
      });
  });
})();
