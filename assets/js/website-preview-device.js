/**
 * Geräte-Simulator für /vorschau/{slug} (äußere Shell).
 */
(function () {
  var widthInput = document.getElementById('ws-preview-width');
  var heightInput = document.getElementById('ws-preview-height');
  var scaleSelect = document.getElementById('ws-preview-scale');
  var device = document.getElementById('ws-preview-device');
  var stage = document.getElementById('ws-preview-stage');
  var label = document.getElementById('ws-preview-label');
  var rotateBtn = document.getElementById('ws-preview-rotate');
  var presetBtns = Array.prototype.slice.call(document.querySelectorAll('[data-preset]'));

  if (!widthInput || !heightInput || !device || !stage) return;

  var STORAGE_KEY = 'dg-ws-preview-device-v1';

  function clamp(n, min, max) {
    n = Math.round(Number(n) || 0);
    if (n < min) return min;
    if (n > max) return max;
    return n;
  }

  function readSize() {
    return {
      w: clamp(widthInput.value, 240, 2560),
      h: clamp(heightInput.value, 320, 2560),
      scale: scaleSelect ? scaleSelect.value : 'fit',
    };
  }

  function saveState(size) {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(size));
    } catch (e) { /* ignore */ }
  }

  function loadState() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return null;
      return JSON.parse(raw);
    } catch (e) {
      return null;
    }
  }

  function markPreset(w, h) {
    presetBtns.forEach(function (btn) {
      var pw = parseInt(btn.getAttribute('data-w'), 10);
      var ph = parseInt(btn.getAttribute('data-h'), 10);
      var match = (pw === w && ph === h) || (pw === h && ph === w);
      btn.classList.toggle('is-active', match);
    });
  }

  function applyScale(w, h, mode) {
    var chrome = 28;
    var availW = Math.max(200, stage.clientWidth - 32);
    var availH = Math.max(200, stage.clientHeight - 32);
    var scale = 1;
    if (mode === 'fit') {
      scale = Math.min(1, availW / w, availH / (h + chrome));
    } else {
      scale = Number(mode) || 1;
    }
    device.style.transform = scale < 0.999 ? 'scale(' + scale + ')' : '';
    device.style.marginBottom = scale < 0.999 ? Math.max(0, (h + chrome) * (scale - 1)) + 'px' : '';
  }

  function applySize(opts) {
    opts = opts || {};
    var w = clamp(opts.w != null ? opts.w : widthInput.value, 240, 2560);
    var h = clamp(opts.h != null ? opts.h : heightInput.value, 320, 2560);
    var scale = opts.scale != null ? opts.scale : (scaleSelect ? scaleSelect.value : 'fit');

    widthInput.value = String(w);
    heightInput.value = String(h);
    if (scaleSelect && opts.scale != null) scaleSelect.value = scale;

    device.style.width = w + 'px';
    device.style.height = (h + 28) + 'px';
    if (label) label.textContent = w + ' × ' + h;
    markPreset(w, h);
    applyScale(w, h, scale);
    saveState({ w: w, h: h, scale: scale });
  }

  presetBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      applySize({
        w: parseInt(btn.getAttribute('data-w'), 10),
        h: parseInt(btn.getAttribute('data-h'), 10),
      });
    });
  });

  function onManualChange() {
    applySize({});
  }

  widthInput.addEventListener('change', onManualChange);
  heightInput.addEventListener('change', onManualChange);
  widthInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') onManualChange();
  });
  heightInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') onManualChange();
  });
  if (scaleSelect) {
    scaleSelect.addEventListener('change', onManualChange);
  }

  if (rotateBtn) {
    rotateBtn.addEventListener('click', function () {
      applySize({ w: heightInput.value, h: widthInput.value });
    });
  }

  window.addEventListener('resize', function () {
    applySize({});
  });

  var saved = loadState();
  if (saved && saved.w && saved.h) {
    applySize(saved);
  } else {
    applySize({ w: 1440, h: 900, scale: 'fit' });
  }
})();
