/**
 * Website-URL-Assist: Leerzeichen weg, https:// ergänzen, Soft-Check (TLD/DNS).
 */
(function () {
  'use strict';

  var cfg = window.dgWebsiteUrlAssist || {};
  var timers = new WeakMap();

  function isTarget(el) {
    if (!el || el.disabled || el.readOnly) return false;
    if (el.getAttribute('data-website-url') === '0') return false;
    if (el.getAttribute('data-website-url') === '1') return true;
    if (el.name === 'website' || /\[website\]$/.test(el.name || '')) return true;
    if (/\[appointment_url\]$/.test(el.name || '')) return true;
    if (el.name === 'footer_website' || /^footer_url_/.test(el.name || '')) return true;
    if (el.type === 'url' && /website|url|homepage|link/i.test(el.name || el.id || '')) return true;
    return false;
  }

  function ensureHint(input) {
    var next = input.nextElementSibling;
    if (next && next.classList && next.classList.contains('dg-website-url-hint')) {
      return next;
    }
    var hint = document.createElement('span');
    hint.className = 'dg-field-hint dg-website-url-hint';
    hint.hidden = true;
    input.insertAdjacentElement('afterend', hint);
    return hint;
  }

  function setHint(input, text, severity) {
    var hint = ensureHint(input);
    if (!text) {
      hint.hidden = true;
      hint.textContent = '';
      hint.classList.remove('dg-field-hint--error', 'dg-field-hint--warn');
      return;
    }
    hint.hidden = false;
    hint.textContent = text;
    hint.classList.toggle('dg-field-hint--error', severity === 'error');
    hint.classList.toggle('dg-field-hint--warn', severity === 'warn' || severity === 'info');
  }

  function localNormalize(value) {
    var v = String(value || '').replace(/[\u200B-\u200D\uFEFF]/g, '').replace(/\s+/g, '').trim();
    if (!v) return '';
    if (!/^[a-z][a-z0-9+.-]*:\/\//i.test(v)) {
      if (v.indexOf('//') === 0) v = 'https:' + v;
      else if (/^(www\.)?[a-z0-9]([a-z0-9.-]*[a-z0-9])?\.[a-z0-9-]{1,}(\/.*)?$/i.test(v)) {
        v = 'https://' + v.replace(/^\/+/, '');
      }
    }
    return v;
  }

  function applyLocal(input) {
    var before = input.value;
    var after = localNormalize(before);
    if (after !== before) {
      input.value = after;
    }
    return after;
  }

  function postCheck(url) {
    var body = new FormData();
    body.append('_csrf', cfg.csrf || '');
    body.append('url', url);
    return fetch(cfg.url || '/api/website-url-check', {
      method: 'POST',
      body: body,
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    }).then(function (r) {
      return r.json();
    });
  }

  function runCheck(input) {
    var value = applyLocal(input);
    if (!value) {
      setHint(input, '', 'none');
      return;
    }
    setHint(input, 'Prüfe Domain …', 'info');
    postCheck(value)
      .then(function (json) {
        if (!json || !json.success || !json.data) {
          setHint(input, '', 'none');
          return;
        }
        var d = json.data;
        if (d.normalized && d.normalized !== input.value) {
          input.value = d.normalized;
        }
        if (d.suggestion && d.message) {
          setHint(input, d.message + ' Klick zum Übernehmen.', d.severity || 'warn');
          ensureHint(input).setAttribute('data-suggestion', d.suggestion);
          ensureHint(input).style.cursor = 'pointer';
          return;
        }
        ensureHint(input).removeAttribute('data-suggestion');
        ensureHint(input).style.cursor = '';
        if (d.message) {
          setHint(input, d.message, d.severity || 'warn');
        } else {
          setHint(input, '', 'none');
        }
      })
      .catch(function () {
        setHint(input, '', 'none');
      });
  }

  function schedule(input) {
    var prev = timers.get(input);
    if (prev) clearTimeout(prev);
    timers.set(
      input,
      setTimeout(function () {
        runCheck(input);
      }, 450)
    );
  }

  function onBlur(e) {
    var input = e.target;
    if (!isTarget(input)) return;
    applyLocal(input);
    runCheck(input);
  }

  function onInput(e) {
    var input = e.target;
    if (!isTarget(input)) return;
    schedule(input);
  }

  function onHintClick(e) {
    var hint = e.target.closest('.dg-website-url-hint');
    if (!hint) return;
    var suggestion = hint.getAttribute('data-suggestion');
    if (!suggestion) return;
    var input = hint.previousElementSibling;
    if (!input || input.tagName !== 'INPUT') return;
    input.value = suggestion;
    setHint(input, 'Übernommen: ' + suggestion, 'info');
    runCheck(input);
  }

  function onSubmit(e) {
    var form = e.target;
    if (!form || !form.querySelectorAll) return;
    form.querySelectorAll('input').forEach(function (input) {
      if (isTarget(input)) applyLocal(input);
    });
  }

  document.addEventListener('blur', onBlur, true);
  document.addEventListener('input', onInput, true);
  document.addEventListener('click', onHintClick, true);
  document.addEventListener('submit', onSubmit, true);
})();
