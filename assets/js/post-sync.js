(function () {
  'use strict';

  var root = document.querySelector('[data-post-sync-root]');
  if (!root) {
    return;
  }

  var mailboxId = parseInt(root.getAttribute('data-mailbox') || '0', 10);
  var folder = root.getAttribute('data-folder') || 'INBOX';
  var refresh = root.getAttribute('data-refresh') === '1';
  var doSync = root.getAttribute('data-post-sync') === '1';
  var listUrl = root.getAttribute('data-list-url') || '';
  var panel = root.querySelector('[data-post-sync-panel]');
  var barEl = root.querySelector('[data-post-sync-bar]');
  var statusEl = root.querySelector('[data-post-sync-status]');
  var errorEl = root.querySelector('[data-post-sync-error]');
  var stepEls = root.querySelectorAll('[data-step]');
  var limit = parseInt(root.getAttribute('data-limit') || '10', 10) || 10;

  if (!mailboxId) {
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

  // Nur bei gezieltem Refresh oder erstem Async-Ladevorgang vom Server holen
  if (!doSync && !refresh) {
    return;
  }

  startProgress();
  setStatus('Verbindung zum Mailserver wird aufgebaut …');

  var url =
    '/api/post-sync?mailbox=' +
    encodeURIComponent(String(mailboxId)) +
    '&folder=' +
    encodeURIComponent(folder) +
    '&offset=0&limit=' +
    encodeURIComponent(String(limit)) +
    '&refresh=' +
    (refresh ? '1' : '0');

  fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    .then(function (response) {
      return response.json().then(function (data) {
        return { response: response, data: data };
      });
    })
    .then(function (result) {
      var data = result.data || {};
      if (!result.response.ok || !data.ok) {
        finishError((data && data.error) || 'Abruf fehlgeschlagen', data.hint || data.imap_error || '');
        return;
      }
      stopProgress();
      step('done');
      setBar(100);
      setStatus('Aktualisiert — Liste wird geladen …');
      setError('');
      var target = listUrl || '/app?page=post&mailbox=' + encodeURIComponent(String(mailboxId));
      window.location.replace(target);
    })
    .catch(function (error) {
      finishError(error && error.message ? error.message : 'Unbekannter Fehler', '');
    });
})();
