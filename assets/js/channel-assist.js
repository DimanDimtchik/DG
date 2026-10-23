/**
 * Weiche E-Mail-/Telefon-Assistenz (Blur): normalisieren + Hinweis, ohne Speichern zu blockieren.
 */
(function () {
  'use strict';

  var cfg = window.dgChannelAssist || {};
  var defaultCountry = String(cfg.defaultCountry || 'DE').toUpperCase();

  var dialMap = {
    DE: '49',
    AT: '43',
    CH: '41',
    NL: '31',
    FR: '33',
    BE: '32',
    LU: '352',
    IT: '39',
    ES: '34',
    PL: '48',
    CZ: '420',
    DK: '45',
    SE: '46',
    GB: '44',
    UK: '44',
    US: '1',
  };

  function isEmailTarget(el) {
    if (!el || el.disabled || el.readOnly) return false;
    if (el.getAttribute('data-email-assist') === '0') return false;
    if (el.getAttribute('data-email-assist') === '1') return true;
    if (el.type === 'email') return true;
    if (el.name === 'email' || el.name === 'email_2' || /_email$|\[email\]/.test(el.name || '')) return true;
    return false;
  }

  function isPhoneTarget(el) {
    if (!el || el.disabled || el.readOnly) return false;
    if (el.getAttribute('data-phone-assist') === '0') return false;
    if (el.getAttribute('data-phone-assist') === '1') return true;
    if (el.type === 'tel') return true;
    if (/^phone(_\d+)?$|_phone$|\[phone\]/.test(el.name || '')) return true;
    return false;
  }

  function ensureHint(input, cls) {
    var next = input.nextElementSibling;
    if (next && next.classList && next.classList.contains(cls)) {
      return next;
    }
    // Skip existing static hints until we find ours or insert after first hint group
    var hint = document.createElement('span');
    hint.className = 'dg-field-hint ' + cls;
    hint.hidden = true;
    input.insertAdjacentElement('afterend', hint);
    return hint;
  }

  function setHint(input, cls, text, severity) {
    var hint = ensureHint(input, cls);
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

  function normalizeEmail(value) {
    var v = String(value || '')
      .replace(/[\u200B-\u200D\uFEFF]/g, '')
      .replace(/\s+/g, '')
      .trim();
    if (!v) return '';
    var at = v.lastIndexOf('@');
    if (at < 0) return v;
    return v.slice(0, at) + '@' + v.slice(at + 1).toLowerCase().replace(/\.+$/, '');
  }

  function emailLooksValid(v) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
  }

  function normalizePhone(value) {
    var s = String(value || '')
      .replace(/[\u200B-\u200D\uFEFF]/g, '')
      .trim();
    if (!s) return '';
    s = s.replace(/[^\d+]/g, '');
    if (s.indexOf('00') === 0) s = '+' + s.slice(2);
    var dial = dialMap[defaultCountry] || '49';
    if (s.charAt(0) === '+') {
      var d = s.slice(1).replace(/\D/g, '');
      if (d.length < 8 || d.length > 15) return s;
      return '+' + d;
    }
    var digits = s.replace(/\D/g, '');
    if (!digits) return '';
    if (digits.charAt(0) === '0') digits = dial + digits.slice(1);
    else if (digits.indexOf(dial) !== 0 && digits.length <= 11) digits = dial + digits;
    if (digits.length < 8 || digits.length > 15) return s;
    return '+' + digits;
  }

  function matchCountryCode(digits) {
    var codes = Object.keys(dialMap)
      .map(function (k) {
        return dialMap[k];
      })
      .filter(function (v, i, a) {
        return a.indexOf(v) === i;
      })
      .sort(function (a, b) {
        return b.length - a.length;
      });
    for (var i = 0; i < codes.length; i++) {
      if (digits.indexOf(codes[i]) === 0) return codes[i];
    }
    return null;
  }

  function formatPhoneDisplay(e164) {
    if (!e164 || e164.charAt(0) !== '+') return e164;
    var digits = e164.slice(1);
    var cc = matchCountryCode(digits);
    if (!cc) return e164;
    var rest = digits.slice(cc.length);
    if (!rest) return e164;
    if (cc === '49' && rest.charAt(0) === '1' && rest.length >= 10) {
      return '+49 ' + rest.slice(0, 3) + ' ' + rest.slice(3);
    }
    if (cc === '49' && rest.length >= 6) {
      var areaLen = rest.length >= 10 ? 3 : 2;
      return '+49 ' + rest.slice(0, areaLen) + ' ' + rest.slice(areaLen);
    }
    return '+' + cc + ' ' + rest;
  }

  function runEmail(input) {
    var before = input.value;
    var after = normalizeEmail(before);
    if (after !== before) input.value = after;
    if (!after) {
      setHint(input, 'dg-channel-email-hint', '', 'none');
      return;
    }
    if (!emailLooksValid(after)) {
      var msg = after.indexOf('@') < 0 ? 'E-Mail braucht ein @ (z. B. name@firma.de).' : 'E-Mail-Format scheint ungültig.';
      setHint(input, 'dg-channel-email-hint', msg, 'warn');
    } else {
      setHint(input, 'dg-channel-email-hint', '', 'none');
    }
  }

  function runPhone(input) {
    var before = input.value;
    var after = normalizePhone(before);
    if (!String(before || '').trim()) {
      setHint(input, 'dg-channel-phone-hint', '', 'none');
      return;
    }
    if (after && after.charAt(0) === '+') {
      var display = formatPhoneDisplay(after);
      if (display !== before) input.value = display;
      setHint(input, 'dg-channel-phone-hint', '', 'none');
      return;
    }
    if (after && after !== before) input.value = after;
    var digits = String(before).replace(/\D/g, '');
    var msg =
      digits.length < 6
        ? 'Telefonnummer wirkt zu kurz.'
        : 'Nummer ungewöhnlich — wird trotzdem gespeichert.';
    setHint(input, 'dg-channel-phone-hint', msg, 'warn');
  }

  function onBlur(e) {
    var el = e.target;
    if (isEmailTarget(el)) runEmail(el);
    else if (isPhoneTarget(el)) runPhone(el);
  }

  function onSubmit(e) {
    var form = e.target;
    if (!form || !form.querySelectorAll) return;
    form.querySelectorAll('input').forEach(function (input) {
      if (isEmailTarget(input)) {
        input.value = normalizeEmail(input.value);
      } else if (isPhoneTarget(input)) {
        var n = normalizePhone(input.value);
        if (n) input.value = n;
      }
    });
  }

  document.addEventListener('blur', onBlur, true);
  document.addEventListener('submit', onSubmit, true);
})();
