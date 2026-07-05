(function () {
  'use strict';

  var root = document.querySelector('[data-post-sync-root]');
  if (!root || root.getAttribute('data-post-sync') !== '1') {
    return;
  }

  var mailboxId = parseInt(root.getAttribute('data-mailbox') || '0', 10);
  var folder = root.getAttribute('data-folder') || 'INBOX';
  var refresh = root.getAttribute('data-refresh') === '1';
  var panel = root.querySelector('[data-post-sync-panel]');
  var barEl = root.querySelector('[data-post-sync-bar]');
  var statusEl = root.querySelector('[data-post-sync-status]');
  var errorEl = root.querySelector('[data-post-sync-error]');
  var tbody = root.querySelector('[data-post-sync-body]');
  var emptyEl = root.querySelector('[data-post-sync-empty]');
  var tableWrap = root.querySelector('[data-post-sync-table]');
  var stepEls = root.querySelectorAll('[data-step]');

  if (!mailboxId || !tbody) {
    return;
  }

  var progressTimer = null;
  var progressValue = 12;

  function step(name) {
    stepEls.forEach(function (el) {
      var key = el.getAttribute('data-step');
      el.classList.remove('is-active', 'is-done', 'is-error');
      if (key === name) {
        el.classList.add('is-active');
      } else if (isStepBefore(key, name)) {
        el.classList.add('is-done');
      }
    });
  }

  function isStepBefore(a, b) {
    var order = ['connect', 'fetch', 'done'];
    return order.indexOf(a) < order.indexOf(b);
  }

  function setBar(percent) {
    progressValue = Math.max(0, Math.min(100, percent));
    if (barEl) {
      barEl.style.width = progressValue + '%';
    }
  }

  function startProgress() {
    step('connect');
    setBar(12);
    if (progressTimer) {
      clearInterval(progressTimer);
    }
    progressTimer = setInterval(function () {
      if (progressValue < 78) {
        setBar(progressValue + 4);
      }
      if (progressValue >= 35) {
        step('fetch');
      }
    }, 450);
  }

  function stopProgress() {
    if (progressTimer) {
      clearInterval(progressTimer);
      progressTimer = null;
    }
  }

  function setStatus(text) {
    if (statusEl) {
      statusEl.textContent = text;
    }
  }

  function setError(text) {
    if (!errorEl) {
      return;
    }
    if (!text) {
      errorEl.hidden = true;
      errorEl.textContent = '';
      return;
    }
    errorEl.hidden = false;
    errorEl.textContent = text;
  }

  function finishSuccess(data) {
    stopProgress();
    if (panel) {
      panel.classList.remove('is-error');
    }
    step('done');
    setBar(100);
    stepEls.forEach(function (el) {
      if (el.getAttribute('data-step') !== 'done') {
        el.classList.add('is-done');
        el.classList.remove('is-active');
      } else {
        el.classList.add('is-done');
        el.classList.remove('is-active');
      }
    });

    var rows = data.rows || [];
    renderRows(rows);
    if (rows.length === 0) {
      setStatus('Keine Nachrichten in diesem Ordner.');
    } else if (data.synced > 0) {
      setStatus(rows.length + ' Nachrichten — neu abgerufen: ' + data.synced + '.');
    } else {
      setStatus(rows.length + ' Nachrichten (lokal zwischengespeichert).');
    }
    setError('');
  }

  function finishError(message, hint) {
    stopProgress();
    if (panel) {
      panel.classList.add('is-error');
    }
    setBar(100);
    stepEls.forEach(function (el) {
      el.classList.remove('is-active', 'is-done');
      if (el.getAttribute('data-step') === 'fetch' || el.getAttribute('data-step') === 'connect') {
        el.classList.add('is-error');
      }
    });
    setStatus('Abruf fehlgeschlagen');
    setError(hint ? message + ' ' + hint : message);
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function renderRows(rows) {
    if (!rows || rows.length === 0) {
      if (tableWrap) {
        tableWrap.hidden = true;
      }
      if (emptyEl) {
        emptyEl.hidden = false;
      }
      tbody.innerHTML = '';
      return;
    }

    if (tableWrap) {
      tableWrap.hidden = false;
    }
    if (emptyEl) {
      emptyEl.hidden = true;
    }

    tbody.innerHTML = rows.map(function (row) {
      var subject = row.url
        ? '<a href="' + escapeHtml(row.url) + '">' + escapeHtml(row.subject || '') + '</a>'
        : escapeHtml(row.subject || '');

      return '<tr class="' + (row.is_read ? '' : 'dg-row--unread') + '">' +
        '<td>' + escapeHtml(row.display_name || '') + '</td>' +
        '<td>' + escapeHtml(row.address || '') + '</td>' +
        '<td>' + subject + '</td>' +
        '<td>' + escapeHtml(row.date || '') + '</td>' +
        '</tr>';
    }).join('');
  }

  startProgress();
  setStatus('Verbindung zum Mailserver wird aufgebaut …');

  var url = '/api/post-sync?mailbox=' + encodeURIComponent(String(mailboxId)) +
    '&folder=' + encodeURIComponent(folder);
  if (refresh) {
    url += '&refresh=1';
  }

  fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    .then(function (response) {
      return response.json().then(function (data) {
        return { response: response, data: data };
      });
    })
    .then(function (result) {
      var data = result.data || {};
      if (!result.response.ok || !data.ok) {
        var msg = (data && data.error) || 'Abruf fehlgeschlagen';
        finishError(msg, data.hint || data.imap_error || '');
        if (Array.isArray(data.rows)) {
          renderRows(data.rows);
        }
        return;
      }
      finishSuccess(data);
    })
    .catch(function (error) {
      finishError(error && error.message ? error.message : 'Unbekannter Fehler', '');
    });
})();
