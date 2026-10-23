(function () {
  'use strict';
  var cfg = window.dgTelefonDial || {};
  var btn = document.getElementById('dg-telefon-call-btn');
  var input = document.getElementById('dg-telefon-number');
  if (!btn || !input || !cfg.enabled) {
    return;
  }
  btn.addEventListener('click', function () {
    var phone = (input.value || '').trim();
    if (!phone) {
      input.focus();
      return;
    }
    btn.disabled = true;
    fetch('/api/cti', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ action: 'dial', phone: phone, _csrf: cfg.csrf || '' })
    })
      .then(function (r) {
        return r.json().then(function (data) {
          return { ok: r.ok, data: data };
        });
      })
      .then(function (res) {
        btn.disabled = false;
        if (!res.ok || !res.data || !res.data.href) {
          window.alert((res.data && res.data.error) || 'Wahl fehlgeschlagen');
          return;
        }
        window.location.href = res.data.href;
      })
      .catch(function () {
        btn.disabled = false;
        window.alert('Netzwerkfehler');
      });
  });
})();
