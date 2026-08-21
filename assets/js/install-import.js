(function () {
  'use strict';

  var root = document.querySelector('[data-install-import-root]');
  if (!root || root.getAttribute('data-install-import') !== '1') {
    return;
  }

  var panel = root.querySelector('[data-install-sync-panel]');
  var barEl = root.querySelector('[data-install-sync-bar]');
  var statusEl = root.querySelector('[data-install-sync-status]');
  var errorEl = root.querySelector('[data-install-sync-error]');
  var stepEls = root.querySelectorAll('[data-install-sync-steps] [data-job]');
  var successPanel = document.getElementById('install-success-panel');

  var progressTimer = null;
  var progressValue = 8;
  var running = false;

  function setBar(percent) {
    progressValue = Math.max(0, Math.min(100, percent));
    if (barEl) {
      barEl.style.width = progressValue + '%';
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

  function updateSteps(state) {
    var jobs = (state && state.jobs) || [];
    var currentIndex = (state && state.current_index) || 0;

    stepEls.forEach(function (el) {
      var key = el.getAttribute('data-job');
      el.classList.remove('is-active', 'is-done', 'is-error');

      if (key === 'done') {
        if (state && state.phase === 'done') {
          el.classList.add('is-done');
        } else if (currentIndex >= jobs.length && jobs.length > 0) {
          el.classList.add('is-active');
        }
        return;
      }

      var jobIndex = -1;
      for (var i = 0; i < jobs.length; i++) {
        if (jobs[i].type === key) {
          jobIndex = i;
          break;
        }
      }
      if (jobIndex < 0) {
        return;
      }

      var job = jobs[jobIndex];
      if (job.status === 'error') {
        el.classList.add('is-error');
      } else if (job.status === 'done') {
        el.classList.add('is-done');
      } else if (jobIndex === currentIndex) {
        el.classList.add('is-active');
      }
    });
  }

  function startProgress() {
    if (progressTimer) {
      clearInterval(progressTimer);
    }
    progressTimer = setInterval(function () {
      if (progressValue < 92) {
        setBar(progressValue + 2);
      }
    }, 500);
  }

  function stopProgress() {
    if (progressTimer) {
      clearInterval(progressTimer);
      progressTimer = null;
    }
  }

  function showSuccess() {
    if (successPanel) {
      successPanel.hidden = false;
    }
    root.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function finishImport() {
    return fetch('/install.php?action=import-finish', {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })
      .then(function (response) {
        return response.json().then(function (data) {
          return { response: response, data: data };
        });
      })
      .then(function (result) {
        if (!result.response.ok || !result.data.ok) {
          throw new Error((result.data && result.data.error) || 'Abschluss fehlgeschlagen');
        }
        stopProgress();
        setBar(100);
        setStatus('Import abgeschlossen.');
        setError('');
        if (panel) {
          panel.classList.remove('is-error');
        }
        showSuccess();
      });
  }

  function runBatch() {
    if (running) {
      return;
    }
    running = true;

    fetch('/install.php?action=import-run', {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })
      .then(function (response) {
        return response.json().then(function (data) {
          return { response: response, data: data };
        });
      })
      .then(function (result) {
        running = false;
        var data = result.data || {};

        if (!result.response.ok || !data.ok) {
          stopProgress();
          if (panel) {
            panel.classList.add('is-error');
          }
          setStatus('Import fehlgeschlagen');
          setError((data && data.error) || (data && data.message) || 'Unbekannter Fehler');
          updateSteps(data.state || {});
          return;
        }

        updateSteps(data.state || {});
        if (typeof data.progress === 'number') {
          setBar(Math.max(progressValue, data.progress));
        }
        if (data.message) {
          setStatus(data.message);
        }

        if (data.done) {
          return finishImport();
        }

        setTimeout(runBatch, 120);
      })
      .catch(function (error) {
        running = false;
        stopProgress();
        if (panel) {
          panel.classList.add('is-error');
        }
        setStatus('Import fehlgeschlagen');
        setError(error && error.message ? error.message : 'Netzwerkfehler');
      });
  }

  startProgress();
  setStatus('Import wird gestartet …');
  runBatch();
})();
