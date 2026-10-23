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
  var panel = root.querySelector('[data-post-sync-panel]');
  var barEl = root.querySelector('[data-post-sync-bar]');
  var statusEl = root.querySelector('[data-post-sync-status]');
  var errorEl = root.querySelector('[data-post-sync-error]');
  var tbody = root.querySelector('[data-post-sync-body]');
  var emptyEl = root.querySelector('[data-post-sync-empty]');
  var tableWrap = root.querySelector('[data-post-sync-table]');
  var stepEls = root.querySelectorAll('[data-step]');
  var loadMoreWrap = root.querySelector('[data-post-load-more-wrap]');
  var loadMoreBtn = root.querySelector('[data-post-load-more]');

  if (!mailboxId || !tbody) {
    return;
  }

  var offset = parseInt(root.getAttribute('data-offset') || '0', 10) || 0;
  var limit = parseInt(root.getAttribute('data-limit') || '50', 10) || 50;
  var hasMore = root.getAttribute('data-has-more') === '1';
  var progressTimer = null;
  var progressValue = 12;
  var loadingMore = false;

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

  function updateHasMore(flag, nextOffset) {
    hasMore = !!flag;
    if (typeof nextOffset === 'number') {
      offset = nextOffset;
      root.setAttribute('data-offset', String(offset));
    }
    root.setAttribute('data-has-more', hasMore ? '1' : '0');
    if (loadMoreWrap) {
      loadMoreWrap.hidden = !hasMore;
    }
  }

  function finishSuccess(data, append) {
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
    renderRows(rows, !!append);
    updateHasMore(!!data.has_more, typeof data.next_offset === 'number' ? data.next_offset : offset + rows.length);

    var totalHint = tbody.querySelectorAll('tr').length;
    if (totalHint === 0) {
      setStatus('Keine Nachrichten in diesem Ordner.');
    } else if (data.synced > 0) {
      setStatus(totalHint + ' Nachrichten — neu abgerufen: ' + data.synced + '.');
    } else {
      setStatus(totalHint + ' Nachrichten.');
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

  function renderRows(rows, append) {
    if (!append && (!rows || rows.length === 0)) {
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

    var html = (rows || []).map(function (row) {
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

    if (append) {
      tbody.insertAdjacentHTML('beforeend', html);
    } else {
      tbody.innerHTML = html;
    }
  }

  function fetchPage(opts) {
    opts = opts || {};
    var append = !!opts.append;
    var pageOffset = append ? offset : 0;
    var url = '/api/post-sync?mailbox=' + encodeURIComponent(String(mailboxId)) +
      '&folder=' + encodeURIComponent(folder) +
      '&offset=' + encodeURIComponent(String(pageOffset)) +
      '&limit=' + encodeURIComponent(String(limit));
    if (!append && (refresh || doSync)) {
      url += '&refresh=' + (refresh ? '1' : '0');
    }

    return fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (response) {
        return response.json().then(function (data) {
          return { response: response, data: data };
        });
      });
  }

  if (loadMoreBtn) {
    loadMoreBtn.addEventListener('click', function () {
      if (!hasMore || loadingMore) {
        return;
      }
      loadingMore = true;
      loadMoreBtn.disabled = true;
      fetchPage({ append: true })
        .then(function (result) {
          var data = result.data || {};
          if (!result.response.ok || data.ok === false) {
            setError((data && data.error) || 'Weitere Nachrichten konnten nicht geladen werden.');
            return;
          }
          finishSuccess(data, true);
        })
        .catch(function (error) {
          setError(error && error.message ? error.message : 'Unbekannter Fehler');
        })
        .finally(function () {
          loadingMore = false;
          loadMoreBtn.disabled = false;
        });
    });
  }

  updateHasMore(hasMore, offset);

  if (!doSync && !refresh) {
    return;
  }

  startProgress();
  setStatus('Verbindung zum Mailserver wird aufgebaut …');

  fetchPage({ append: false })
    .then(function (result) {
      var data = result.data || {};
      if (!result.response.ok || !data.ok) {
        var msg = (data && data.error) || 'Abruf fehlgeschlagen';
        finishError(msg, data.hint || data.imap_error || '');
        if (Array.isArray(data.rows)) {
          renderRows(data.rows, false);
          updateHasMore(!!data.has_more, typeof data.next_offset === 'number' ? data.next_offset : data.rows.length);
        }
        return;
      }
      finishSuccess(data, false);
    })
    .catch(function (error) {
      finishError(error && error.message ? error.message : 'Unbekannter Fehler', '');
    });
})();
