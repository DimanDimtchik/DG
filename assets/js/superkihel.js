(function () {
  'use strict';

  var cfg = window.dgKichel;
  var form = document.querySelector('[data-superkihel-form]');
  var input = document.getElementById('superkihel-query');
  var messages = document.querySelector('[data-superkihel-messages]');

  if (!cfg || !cfg.apiUrl || !cfg.csrf || !form || !input || !messages) {
    return;
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function appendMessage(text, role) {
    var el = document.createElement('div');
    el.className = 'dg-kichel-msg dg-kichel-msg--' + role;
    el.textContent = text;
    messages.appendChild(el);
    el.scrollIntoView({ block: 'nearest' });
    return el;
  }

  function appendHtmlSection(className, html) {
    var wrap = document.createElement('div');
    wrap.className = className;
    wrap.innerHTML = html;
    messages.appendChild(wrap);
    wrap.scrollIntoView({ block: 'nearest' });
  }

  function renderActionLinks(items) {
    if (!items || !items.length) {
      return;
    }
    var html = '<ul class="dg-kichel-links dg-kichel-links--action">';
    items.forEach(function (item) {
      if (item.href) {
        html +=
          '<li><a class="dg-kichel-action" href="' +
          escapeHtml(item.href) +
          '">' +
          escapeHtml(item.label || item.href) +
          '</a></li>';
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
      html += '<strong>' + escapeHtml(page.title || page.slug || '') + '</strong>';
      html += '<ul class="dg-kichel-links">';
      if (page.view_href) {
        html +=
          '<li><a href="' +
          escapeHtml(page.view_href) +
          '" target="_blank" rel="noopener">' +
          escapeHtml(page.view_label || 'Ansehen') +
          '</a></li>';
      }
      if (page.edit_href) {
        html +=
          '<li><a href="' +
          escapeHtml(page.edit_href) +
          '">' +
          escapeHtml(page.edit_label || 'Bearbeiten') +
          '</a></li>';
      }
      html += '</ul></div>';
    });
    appendHtmlSection('dg-kichel-section dg-kichel-section--pages', html);
  }

  function renderFollowUp(text) {
    if (!text) {
      return;
    }
    var html = '<p class="dg-kichel-followup__text">' + escapeHtml(text) + '</p>';
    html += '<div class="dg-kichel-feedback">';
    html += '<button type="button" data-superkihel-feedback="yes">Ja, passt</button>';
    html += '<button type="button" data-superkihel-feedback="no">Nein, nochmal</button>';
    html += '</div>';
    appendHtmlSection('dg-kichel-followup', html);
  }

  messages.addEventListener('click', function (ev) {
    var feedbackBtn = ev.target.closest('[data-superkihel-feedback]');
    if (!feedbackBtn) {
      return;
    }
    ev.preventDefault();
    var wrap = feedbackBtn.closest('.dg-kichel-followup');
    if (wrap) {
      var feedback = wrap.querySelector('.dg-kichel-feedback');
      if (feedback) {
        feedback.remove();
      }
    }
    if (feedbackBtn.getAttribute('data-superkihel-feedback') === 'yes') {
      appendMessage('Freut mich! Bei weiteren Fragen einfach melden.', 'bot');
    } else {
      appendMessage('Alles klar — formuliere die Frage gerne noch einmal.', 'bot');
      input.focus();
    }
  });

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
