/**
 * Geräte-Simulator für /vorschau/{slug} (äußere Shell).
 * Desktop/Laptop/Tablet als Buttons; Handy & weitere Tablets im Dropdown (nach CSS-Breite gruppiert).
 */
(function () {
  var widthInput = document.getElementById('ws-preview-width');
  var heightInput = document.getElementById('ws-preview-height');
  var scaleSelect = document.getElementById('ws-preview-scale');
  var device = document.getElementById('ws-preview-device');
  var stage = document.getElementById('ws-preview-stage');
  var label = document.getElementById('ws-preview-label');
  var rotateBtn = document.getElementById('ws-preview-rotate');
  var deviceSelect = document.getElementById('ws-preview-device-select');
  var presetBtns = Array.prototype.slice.call(document.querySelectorAll('.ws-preview-device-btn[data-preset]'));

  if (!widthInput || !heightInput || !device || !stage) return;

  var STORAGE_KEY = 'dg-ws-preview-device-v2';

  /**
   * Beliebte CSS-Viewports (Portrait). Gruppen = ähnliche Breite.
   * @type {Array<{group: string, kind: string, id: string, name: string, w: number, h: number}>}
   */
  var DEVICE_CATALOG = [
    // —— Handys: ~360 CSS-px ——
    { group: 'Handy · ca. 360 px breit', kind: 'phone', id: 'galaxy-s10', name: 'Samsung Galaxy S10', w: 360, h: 760 },
    { group: 'Handy · ca. 360 px breit', kind: 'phone', id: 'galaxy-s21', name: 'Samsung Galaxy S21 / S22 / S23', w: 360, h: 800 },
    { group: 'Handy · ca. 360 px breit', kind: 'phone', id: 'galaxy-s24', name: 'Samsung Galaxy S24', w: 360, h: 780 },
    { group: 'Handy · ca. 360 px breit', kind: 'phone', id: 'galaxy-a54', name: 'Samsung Galaxy A54', w: 360, h: 800 },

    // —— Handys: ~375 ——
    { group: 'Handy · ca. 375 px breit', kind: 'phone', id: 'iphone-se', name: 'iPhone SE (3. Gen.)', w: 375, h: 667 },
    { group: 'Handy · ca. 375 px breit', kind: 'phone', id: 'iphone-13-mini', name: 'iPhone 12 / 13 mini', w: 375, h: 812 },
    { group: 'Handy · ca. 375 px breit', kind: 'phone', id: 'iphone-x', name: 'iPhone X / XS / 11 Pro', w: 375, h: 812 },

    // —— Handys: ~390–393 ——
    { group: 'Handy · ca. 390 px breit', kind: 'phone', id: 'iphone-14', name: 'iPhone 14 / 15', w: 390, h: 844 },
    { group: 'Handy · ca. 390 px breit', kind: 'phone', id: 'iphone-14-pro', name: 'iPhone 14 Pro / 15 Pro', w: 393, h: 852 },
    { group: 'Handy · ca. 390 px breit', kind: 'phone', id: 'pixel-7a', name: 'Google Pixel 7a', w: 393, h: 851 },

    // —— Handys: ~412–430 ——
    { group: 'Handy · ca. 412–430 px breit', kind: 'phone', id: 'pixel-7', name: 'Google Pixel 7 / 8', w: 412, h: 915 },
    { group: 'Handy · ca. 412–430 px breit', kind: 'phone', id: 'pixel-8-pro', name: 'Google Pixel 8 Pro', w: 412, h: 892 },
    { group: 'Handy · ca. 412–430 px breit', kind: 'phone', id: 'oneplus-12', name: 'OnePlus 12', w: 412, h: 919 },
    { group: 'Handy · ca. 412–430 px breit', kind: 'phone', id: 'iphone-14-plus', name: 'iPhone 14 Plus / 15 Plus', w: 428, h: 926 },
    { group: 'Handy · ca. 412–430 px breit', kind: 'phone', id: 'iphone-15-pro-max', name: 'iPhone 15 Pro Max', w: 430, h: 932 },

    // —— Tablets: 768 ——
    { group: 'Tablet · 768 px breit', kind: 'tablet', id: 'ipad-mini', name: 'iPad mini / iPad (klassisch)', w: 768, h: 1024 },
    { group: 'Tablet · 768 px breit', kind: 'tablet', id: 'ipad-9', name: 'iPad (9. Gen.)', w: 768, h: 1024 },

    // —— Tablets: ~800–820 ——
    { group: 'Tablet · ca. 800–820 px breit', kind: 'tablet', id: 'galaxy-tab-s8', name: 'Samsung Galaxy Tab S8', w: 800, h: 1280 },
    { group: 'Tablet · ca. 800–820 px breit', kind: 'tablet', id: 'galaxy-tab-a8', name: 'Samsung Galaxy Tab A8', w: 800, h: 1280 },
    { group: 'Tablet · ca. 800–820 px breit', kind: 'tablet', id: 'fire-hd-10', name: 'Amazon Fire HD 10', w: 800, h: 1280 },
    { group: 'Tablet · ca. 800–820 px breit', kind: 'tablet', id: 'lenovo-tab-m10', name: 'Lenovo Tab M10', w: 800, h: 1280 },
    { group: 'Tablet · ca. 800–820 px breit', kind: 'tablet', id: 'ipad-air', name: 'iPad Air (10,9″)', w: 820, h: 1180 },
    { group: 'Tablet · ca. 800–820 px breit', kind: 'tablet', id: 'ipad-10', name: 'iPad (10. Gen.)', w: 820, h: 1180 },

    // —— Tablets: ~834–912 ——
    { group: 'Tablet · ca. 834–912 px breit', kind: 'tablet', id: 'ipad-pro-11', name: 'iPad Pro 11″', w: 834, h: 1194 },
    { group: 'Tablet · ca. 834–912 px breit', kind: 'tablet', id: 'surface-go', name: 'Microsoft Surface Go 3', w: 922, h: 1344 },
    { group: 'Tablet · ca. 834–912 px breit', kind: 'tablet', id: 'surface-pro-7', name: 'Microsoft Surface Pro 7', w: 912, h: 1368 },

    // —— Tablets: 1024+ ——
    { group: 'Tablet · 1024 px und breiter', kind: 'tablet', id: 'ipad-pro-129', name: 'iPad Pro 12,9″', w: 1024, h: 1366 },
    { group: 'Tablet · 1024 px und breiter', kind: 'tablet', id: 'galaxy-tab-s9-plus', name: 'Samsung Galaxy Tab S9+', w: 1024, h: 1600 },
  ];

  function clamp(n, min, max) {
    n = Math.round(Number(n) || 0);
    if (n < min) return min;
    if (n > max) return max;
    return n;
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

  function sizeKey(w, h) {
    return w + 'x' + h;
  }

  function findCatalogMatch(w, h) {
    for (var i = 0; i < DEVICE_CATALOG.length; i++) {
      var d = DEVICE_CATALOG[i];
      if ((d.w === w && d.h === h) || (d.w === h && d.h === w)) return d;
    }
    return null;
  }

  function buildDeviceSelect() {
    if (!deviceSelect) return;
    var groups = {};
    var order = [];
    DEVICE_CATALOG.forEach(function (d) {
      if (!groups[d.group]) {
        groups[d.group] = [];
        order.push(d.group);
      }
      groups[d.group].push(d);
    });

    var html = '<option value="">Weitere Geräte …</option>';
    order.forEach(function (groupName) {
      html += '<optgroup label="' + escapeAttr(groupName) + '">';
      groups[groupName].forEach(function (d) {
        html += '<option value="' + escapeAttr(d.id) + '" data-w="' + d.w + '" data-h="' + d.h + '">' +
          escapeHtml(d.name) + ' (' + d.w + '×' + d.h + ')</option>';
      });
      html += '</optgroup>';
    });
    deviceSelect.innerHTML = html;
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function escapeAttr(value) {
    return escapeHtml(value).replace(/'/g, '&#39;');
  }

  function markPreset(w, h, deviceId) {
    var matchedBtn = false;
    presetBtns.forEach(function (btn) {
      var pw = parseInt(btn.getAttribute('data-w'), 10);
      var ph = parseInt(btn.getAttribute('data-h'), 10);
      var match = (pw === w && ph === h) || (pw === h && ph === w);
      btn.classList.toggle('is-active', match);
      if (match) matchedBtn = true;
    });

    if (!deviceSelect) return;
    if (deviceId) {
      deviceSelect.value = deviceId;
      return;
    }
    var found = findCatalogMatch(w, h);
    if (found && !matchedBtn) {
      deviceSelect.value = found.id;
    } else if (matchedBtn) {
      deviceSelect.value = '';
    } else {
      deviceSelect.value = '';
    }
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
    var deviceId = opts.deviceId || '';
    var deviceName = opts.deviceName || '';

    widthInput.value = String(w);
    heightInput.value = String(h);
    if (scaleSelect && opts.scale != null) scaleSelect.value = scale;

    device.style.width = w + 'px';
    device.style.height = (h + 28) + 'px';
    if (label) {
      label.textContent = deviceName
        ? (deviceName + ' · ' + w + ' × ' + h)
        : (w + ' × ' + h);
    }
    markPreset(w, h, deviceId);
    applyScale(w, h, scale);
    saveState({ w: w, h: h, scale: scale, deviceId: deviceId || undefined });
  }

  function applyCatalogDevice(id) {
    var found = null;
    for (var i = 0; i < DEVICE_CATALOG.length; i++) {
      if (DEVICE_CATALOG[i].id === id) {
        found = DEVICE_CATALOG[i];
        break;
      }
    }
    if (!found) return;
    applySize({
      w: found.w,
      h: found.h,
      deviceId: found.id,
      deviceName: found.name,
    });
  }

  buildDeviceSelect();

  presetBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      applySize({
        w: parseInt(btn.getAttribute('data-w'), 10),
        h: parseInt(btn.getAttribute('data-h'), 10),
        deviceId: '',
        deviceName: btn.textContent.trim(),
      });
    });
  });

  if (deviceSelect) {
    deviceSelect.addEventListener('change', function () {
      var id = deviceSelect.value;
      if (!id) return;
      applyCatalogDevice(id);
    });
  }

  function onManualChange() {
    applySize({ deviceId: '', deviceName: '' });
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
      var matched = findCatalogMatch(
        clamp(widthInput.value, 240, 2560),
        clamp(heightInput.value, 320, 2560)
      );
      applySize({
        w: heightInput.value,
        h: widthInput.value,
        deviceId: matched ? matched.id : '',
        deviceName: matched ? matched.name + ' (gedreht)' : '',
      });
    });
  }

  window.addEventListener('resize', function () {
    applySize({
      deviceId: deviceSelect ? deviceSelect.value : '',
    });
  });

  var saved = loadState();
  if (saved && saved.deviceId) {
    applyCatalogDevice(saved.deviceId);
    if (saved.scale && scaleSelect) {
      scaleSelect.value = saved.scale;
      applySize({
        w: saved.w,
        h: saved.h,
        scale: saved.scale,
        deviceId: saved.deviceId,
        deviceName: (findCatalogMatch(saved.w, saved.h) || {}).name || '',
      });
    }
  } else if (saved && saved.w && saved.h) {
    applySize(saved);
  } else {
    applySize({ w: 1440, h: 900, scale: 'fit', deviceName: 'Desktop' });
  }
})();
