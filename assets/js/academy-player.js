(function () {
  var cfg = window.dgAcademyConfig || {};
  if (!cfg.assignmentId || !cfg.moduleId) {
    return;
  }

  var apiUrl = cfg.apiUrl || '/api/academy';
  var csrf = cfg.csrf || '';
  var sessionUuid = '';
  var video = document.getElementById('dg-academy-video');
  var messageEl = document.getElementById('dg-academy-player-message');
  var completeBtn = document.getElementById('dg-academy-complete-btn');
  var heartbeatTimer = null;
  var lastTick = Date.now();

  function showMessage(text, kind) {
    if (!messageEl) {
      return;
    }
    messageEl.textContent = text || '';
    messageEl.className = 'dg-scan-result' + (kind ? ' dg-scan-result--' + kind : '');
    messageEl.hidden = text === '';
  }

  function currentRate() {
    if (video) {
      return video.playbackRate || 1;
    }
    return 1;
  }

  function currentPosition() {
    if (video) {
      return Math.floor(video.currentTime || 0);
    }
    return 0;
  }

  function tabVisible() {
    return document.visibilityState !== 'hidden';
  }

  function sendHeartbeat(deltaSec) {
    if (!sessionUuid) {
      return;
    }
    fetch(apiUrl + '?action=heartbeat', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        session_uuid: sessionUuid,
        delta_sec: deltaSec,
        playback_rate: currentRate(),
        position_sec: currentPosition(),
        tab_visible: tabVisible(),
      }),
    }).catch(function () {});
  }

  function startHeartbeat() {
    lastTick = Date.now();
    if (heartbeatTimer) {
      clearInterval(heartbeatTimer);
    }
    heartbeatTimer = setInterval(function () {
      var now = Date.now();
      var delta = Math.round((now - lastTick) / 1000);
      lastTick = now;
      if (cfg.hasVideo && video && video.paused) {
        return;
      }
      if (delta > 0) {
        sendHeartbeat(Math.min(delta, 35));
      }
    }, cfg.hasVideo ? 30000 : 15000);
  }

  function enforceMaxRate() {
    if (!video) {
      return;
    }
    var maxRate = parseFloat(video.getAttribute('data-max-rate') || '2') || 2;
    if (video.playbackRate > maxRate) {
      video.playbackRate = maxRate;
      showMessage('Max. ' + maxRate + '× Geschwindigkeit erlaubt.', 'warning');
    }
  }

  function startSession() {
    var body = new FormData();
    body.append('_csrf', csrf);
    body.append('action', 'start');
    body.append('assignment_id', String(cfg.assignmentId));
    body.append('module_id', String(cfg.moduleId));

    return fetch(apiUrl + '?action=start', {
      method: 'POST',
      credentials: 'same-origin',
      body: body,
    })
      .then(function (res) {
        return res.json();
      })
      .then(function (result) {
        if (!result.success || !result.data) {
          throw new Error((result && result.message) || 'Sitzung konnte nicht gestartet werden.');
        }
        sessionUuid = result.data.session_uuid;
        startHeartbeat();
      });
  }

  if (video) {
    video.addEventListener('ratechange', enforceMaxRate);
    video.addEventListener('play', enforceMaxRate);
  }

  startSession().catch(function (err) {
    showMessage(err.message || 'Fehler beim Start.', 'error');
  });

  if (completeBtn) {
    completeBtn.addEventListener('click', function () {
      function postComplete() {
        if (!sessionUuid) {
          showMessage('Bitte warten — Sitzung wird gestartet …', 'info');
          return startSession().then(function () {
            return postComplete();
          });
        }
        if (!cfg.hasVideo && cfg.durationSec) {
          var chunk = 30;
          for (var t = 0; t < cfg.durationSec; t += chunk) {
            sendHeartbeat(Math.min(chunk, cfg.durationSec - t));
          }
        } else {
          sendHeartbeat(5);
        }
        var body = new FormData();
        body.append('_csrf', csrf);
        body.append('session_uuid', sessionUuid);
        return fetch(apiUrl + '?action=complete', {
          method: 'POST',
          credentials: 'same-origin',
          body: body,
        })
          .then(function (res) {
            return res.json();
          })
          .then(function (result) {
            if (!result.success) {
              var msg = (result && result.message) || 'Abschluss fehlgeschlagen.';
              // Nach SQL-/Netzfehler: neue Sitzung und einmal erneut versuchen
              if (/sitzung nicht gefunden/i.test(msg) && !completeBtn.dataset.retrying) {
                completeBtn.dataset.retrying = '1';
                sessionUuid = '';
                return startSession().then(function () {
                  return postComplete();
                }).finally(function () {
                  delete completeBtn.dataset.retrying;
                });
              }
              showMessage(msg, 'error');
              return;
            }
            var data = result.data || {};
            if (data.completed) {
              showMessage('Modul abgeschlossen.', 'success');
              setTimeout(function () {
                window.location.href = completeBtn.closest('.dg-academy-player-wrap')
                  ? '/app?page=akademie&view=kurs&slug=' + encodeURIComponent(new URLSearchParams(window.location.search).get('slug') || '')
                  : '/app?page=akademie&view=meine';
              }, 800);
            } else {
              showMessage('Noch nicht ausreichend angesehen. Bitte Modul vollständig durcharbeiten.', 'warning');
            }
          })
          .catch(function () {
            showMessage('Netzwerkfehler.', 'error');
          });
      }
      postComplete();
    });
  }
})();
