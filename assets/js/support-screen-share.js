/**
 * Support screen share (customer = getDisplayMedia, support = watch).
 * Signaling via JSON POST/GET polling.
 */
(function () {
  'use strict';

  var cfg = window.DG_SUPPORT_SHARE;
  if (!cfg || !cfg.signalUrl) return;

  var role = cfg.role === 'support' ? 'support' : 'customer';
  var pc = null;
  var localStream = null;
  var lastId = 0;
  var pollTimer = null;
  var statusEl = document.getElementById('dg-support-share-status');

  function setStatus(msg) {
    if (statusEl) statusEl.textContent = msg || '';
  }

  function postSignal(payload) {
    return fetch(cfg.signalUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ _csrf: cfg.csrf, payload: payload })
    }).then(function (r) { return r.json(); });
  }

  function pullSignals() {
    return fetch(cfg.signalUrl + '?after=' + lastId, {
      credentials: 'same-origin'
    }).then(function (r) { return r.json(); }).then(function (data) {
      if (!data || !data.ok || !Array.isArray(data.messages)) return;
      data.messages.forEach(function (m) {
        lastId = Math.max(lastId, m.id || 0);
        handleSignal(m.payload || {});
      });
    }).catch(function () {});
  }

  function ensurePc() {
    if (pc) return pc;
    pc = new RTCPeerConnection({
      iceServers: [{ urls: 'stun:stun.l.google.com:19302' }]
    });
    pc.onicecandidate = function (ev) {
      if (ev.candidate) {
        postSignal({ type: 'ice', candidate: ev.candidate.toJSON() });
      }
    };
    pc.onconnectionstatechange = function () {
      setStatus('Verbindung: ' + (pc.connectionState || ''));
    };
    if (role === 'support') {
      pc.ontrack = function (ev) {
        var video = document.getElementById('dg-support-remote-video');
        if (video) {
          video.srcObject = ev.streams[0] || new MediaStream([ev.track]);
          setStatus('Bildschirm empfangen');
        }
      };
    }
    return pc;
  }

  async function handleSignal(payload) {
    var type = payload.type;
    if (!type) return;
    var conn = ensurePc();

    if (type === 'offer' && role === 'support') {
      await conn.setRemoteDescription(payload.sdp);
      var answer = await conn.createAnswer();
      await conn.setLocalDescription(answer);
      await postSignal({ type: 'answer', sdp: conn.localDescription });
      setStatus('Antwort gesendet – warte auf Stream…');
    } else if (type === 'answer' && role === 'customer') {
      await conn.setRemoteDescription(payload.sdp);
    } else if (type === 'ice' && payload.candidate) {
      try {
        await conn.addIceCandidate(payload.candidate);
      } catch (e) {}
    } else if (type === 'ready' && role === 'customer' && localStream) {
      await startOffer();
    }
  }

  async function startOffer() {
    var conn = ensurePc();
    var offer = await conn.createOffer();
    await conn.setLocalDescription(offer);
    await postSignal({ type: 'offer', sdp: conn.localDescription });
    setStatus('Angebot gesendet – Support verbindet…');
  }

  async function startShare() {
    try {
      localStream = await navigator.mediaDevices.getDisplayMedia({
        video: { cursor: 'always' },
        audio: false
      });
    } catch (e) {
      setStatus('Bildschirmfreigabe abgebrochen oder nicht erlaubt.');
      return;
    }
    var preview = document.getElementById('dg-support-local-preview');
    if (preview) {
      preview.srcObject = localStream;
      preview.hidden = false;
    }
    var conn = ensurePc();
    localStream.getTracks().forEach(function (t) {
      conn.addTrack(t, localStream);
      t.addEventListener('ended', stopShare);
    });
    document.getElementById('dg-support-share-start').hidden = true;
    var stopBtn = document.getElementById('dg-support-share-stop');
    if (stopBtn) stopBtn.hidden = false;
    await postSignal({ type: 'ready' });
    await startOffer();
  }

  function stopShare() {
    if (localStream) {
      localStream.getTracks().forEach(function (t) { t.stop(); });
      localStream = null;
    }
    if (pc) {
      pc.close();
      pc = null;
    }
    var preview = document.getElementById('dg-support-local-preview');
    if (preview) {
      preview.srcObject = null;
      preview.hidden = true;
    }
    var startBtn = document.getElementById('dg-support-share-start');
    var stopBtn = document.getElementById('dg-support-share-stop');
    if (startBtn) startBtn.hidden = false;
    if (stopBtn) stopBtn.hidden = true;
    setStatus('Teilen beendet.');
  }

  if (role === 'customer') {
    var startBtn = document.getElementById('dg-support-share-start');
    var stopBtn = document.getElementById('dg-support-share-stop');
    if (startBtn) startBtn.addEventListener('click', startShare);
    if (stopBtn) stopBtn.addEventListener('click', stopShare);
  } else {
    ensurePc();
    postSignal({ type: 'viewer_waiting' });
    setStatus('Warte auf Bildschirmfreigabe durch den Kunden…');
  }

  pollTimer = setInterval(pullSignals, 1500);
  pullSignals();
})();
