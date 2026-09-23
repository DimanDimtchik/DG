(function () {
  'use strict';

  var root = document.querySelector('.dg-booking-qr-designer');
  if (!root) {
    return;
  }

  var PRESETS = {
    none: { enabled: 0, width: 0, radius: 0, padding: 0 },
    classic: { enabled: 1, width: 3, radius: 12, padding: 16 },
    soft: { enabled: 1, width: 2, radius: 24, padding: 20 },
    bold: { enabled: 1, width: 6, radius: 8, padding: 12 },
    card: { enabled: 1, width: 1, radius: 16, padding: 28 },
  };

  var qrInstance = null;
  var updateTimer = null;
  var customUrl = root.getAttribute('data-center-custom-url') || '';
  var emojiActiveGroup = 'smileys';
  var emojiSearchQuery = '';

  function $(sel) {
    return root.querySelector(sel) || document.querySelector(sel);
  }

  function catalog() {
    return window.DgEmojiCatalog || null;
  }

  function renderEmojiPicker() {
    var cat = catalog();
    var tabs = document.getElementById('dg-qr-emoji-tabs');
    var picks = document.getElementById('dg-qr-emoji-picks');
    var countEl = document.getElementById('dg-qr-emoji-count');
    if (!tabs || !picks) {
      return;
    }
    if (!cat) {
      picks.innerHTML = '<p class="dg-field-hint">Emoji-Katalog nicht geladen.</p>';
      return;
    }
    if (countEl) {
      countEl.textContent = String(cat.count);
    }
    var groups = emojiSearchQuery ? cat.search(emojiSearchQuery) : cat.groups;
    if (!groups.length) {
      tabs.innerHTML = '';
      picks.innerHTML = '<p class="dg-field-hint">Keine Treffer.</p>';
      return;
    }
    if (!groups.some(function (g) { return g.id === emojiActiveGroup; })) {
      emojiActiveGroup = groups[0].id;
    }
    tabs.innerHTML = groups.map(function (g) {
      return (
        '<button type="button" class="dg-booking-qr-emoji-tab' +
        (g.id === emojiActiveGroup ? ' is-active' : '') +
        '" data-emoji-group="' +
        g.id +
        '" role="tab">' +
        g.label +
        '</button>'
      );
    }).join('');
    var active = groups.find(function (g) { return g.id === emojiActiveGroup; }) || groups[0];
    var selected = val('dg-qr-emoji', '');
    picks.innerHTML = (active.chars || [])
      .map(function (em) {
        return (
          '<button type="button" class="dg-booking-qr-emoji-btn' +
          (em === selected ? ' is-selected' : '') +
          '" data-emoji="' +
          em +
          '" title="' +
          em +
          '">' +
          em +
          '</button>'
        );
      })
      .join('');
  }

  function val(id, fallback) {
    var el = document.getElementById(id);
    if (!el) {
      return fallback;
    }
    return el.value;
  }

  function centerSource() {
    var checked = root.querySelector('input[name="qr[center_image_source]"]:checked');
    return checked ? checked.value : 'none';
  }

  function setStatus(msg, isError) {
    var el = document.getElementById('dg-qr-status');
    if (!el) {
      return;
    }
    el.textContent = msg || '';
    el.classList.toggle('is-error', !!isError);
  }

  function emojiDataUrl(emoji) {
    var canvas = document.createElement('canvas');
    canvas.width = 256;
    canvas.height = 256;
    var ctx = canvas.getContext('2d');
    if (!ctx) {
      return '';
    }
    ctx.clearRect(0, 0, 256, 256);
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, 256, 256);
    ctx.font = '180px "Segoe UI Emoji","Apple Color Emoji","Noto Color Emoji",sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(emoji || '📅', 128, 140);
    return canvas.toDataURL('image/png');
  }

  function centerImageUrl() {
    var source = centerSource();
    if (source === 'logo') {
      return root.getAttribute('data-logo-url') || '';
    }
    if (source === 'favicon') {
      return root.getAttribute('data-favicon-url') || '';
    }
    if (source === 'custom') {
      return customUrl || '';
    }
    if (source === 'emoji') {
      return emojiDataUrl(val('dg-qr-emoji', '📅'));
    }
    return '';
  }

  function usesCenter() {
    return centerImageUrl() !== '';
  }

  function centerSizeRatio() {
    var source = centerSource();
    if (source === 'favicon') {
      return 0.12;
    }
    if (source === 'emoji') {
      return 0.16;
    }
    return val('dg-qr-center-size', 'tiny') === 'small' ? 0.18 : 0.14;
  }

  function errorCorrection() {
    if (usesCenter()) {
      return 'H';
    }
    return val('dg-qr-ec', 'Q');
  }

  function toggleCenterUi() {
    var source = centerSource();
    var customWrap = document.getElementById('dg-qr-center-custom-wrap');
    var emojiWrap = document.getElementById('dg-qr-center-emoji-wrap');
    var note = document.getElementById('dg-qr-ec-note');
    var ec = document.getElementById('dg-qr-ec');
    if (customWrap) {
      customWrap.hidden = source !== 'custom';
    }
    if (emojiWrap) {
      emojiWrap.hidden = source !== 'emoji';
      if (source === 'emoji') {
        renderEmojiPicker();
      }
    }
    if (note) {
      note.hidden = !usesCenter() && source === 'none';
    }
    if (ec) {
      if (usesCenter() || source !== 'none') {
        if (!ec.dataset.prevEc) {
          ec.dataset.prevEc = ec.value;
        }
        ec.value = 'H';
        ec.disabled = true;
      } else {
        ec.disabled = false;
        if (ec.dataset.prevEc) {
          ec.value = ec.dataset.prevEc;
        }
      }
    }
  }

  function applyPreset(name) {
    var preset = PRESETS[name] || PRESETS.classic;
    var enabled = document.getElementById('dg-qr-frame-enabled');
    var width = document.getElementById('dg-qr-frame-width');
    var radius = document.getElementById('dg-qr-frame-radius');
    var padding = document.getElementById('dg-qr-frame-padding');
    if (enabled) {
      enabled.value = String(preset.enabled);
    }
    if (width) {
      width.value = String(preset.width);
    }
    if (radius) {
      radius.value = String(preset.radius);
    }
    if (padding) {
      padding.value = String(preset.padding);
    }
  }

  function readOptions(pixelSize) {
    var frameEnabled = val('dg-qr-frame-enabled', '0') === '1';
    var fg = val('dg-qr-fg', '#1a1a1a');
    var styling = {
      width: pixelSize,
      height: pixelSize,
      type: 'canvas',
      data: root.getAttribute('data-public-url') || '',
      margin: parseInt(val('dg-qr-margin', '12'), 10) || 0,
      shape: val('dg-qr-shape', 'square'),
      qrOptions: { errorCorrectionLevel: errorCorrection() },
      dotsOptions: { type: val('dg-qr-dots', 'rounded'), color: fg },
      cornersSquareOptions: { type: val('dg-qr-corners-sq', 'extra-rounded'), color: fg },
      cornersDotOptions: { type: val('dg-qr-corners-dot', 'dot'), color: fg },
      backgroundOptions: { color: val('dg-qr-bg', '#ffffff') },
    };
    var imageUrl = centerImageUrl();
    if (imageUrl) {
      styling.image = imageUrl;
      styling.imageOptions = {
        hideBackgroundDots: true,
        imageSize: centerSizeRatio(),
        margin: 4,
        crossOrigin: 'anonymous',
      };
      styling.qrOptions.errorCorrectionLevel = 'H';
    }
    return {
      styling: styling,
      frame: {
        enabled: frameEnabled,
        width: parseInt(val('dg-qr-frame-width', '3'), 10) || 0,
        color: val('dg-qr-frame-color', '#1a1a1a'),
        radius: parseInt(val('dg-qr-frame-radius', '12'), 10) || 0,
        padding: parseInt(val('dg-qr-frame-padding', '16'), 10) || 0,
      },
      caption: val('dg-qr-caption', '').trim(),
      downloadName: (val('dg-qr-download-name', 'termin-buchen').trim() || 'termin-buchen'),
    };
  }

  function applyFrameStyles(frame) {
    var el = document.getElementById('dg-qr-frame');
    if (!el) {
      return;
    }
    if (!frame.enabled) {
      el.style.border = 'none';
      el.style.padding = '0';
      el.style.borderRadius = '0';
      return;
    }
    el.style.border = frame.width + 'px solid ' + frame.color;
    el.style.padding = frame.padding + 'px';
    el.style.borderRadius = frame.radius + 'px';
    el.style.display = 'inline-block';
    el.style.background = '#fff';
  }

  function renderPreview() {
    if (typeof window.QRCodeStyling === 'undefined') {
      setStatus('QR-Bibliothek nicht geladen.', true);
      return;
    }
    toggleCenterUi();
    var url = root.getAttribute('data-public-url') || '';
    if (!url) {
      setStatus('Keine Buchungs-URL.', true);
      return;
    }
    var source = centerSource();
    if (source === 'logo' && !root.getAttribute('data-logo-url')) {
      setStatus('Kein CRM-Logo hinterlegt (Einstellungen → Schriften/Darstellung).', true);
      return;
    }
    if (source === 'favicon' && !root.getAttribute('data-favicon-url')) {
      setStatus('Kein Favicon hinterlegt (Bilder → als Favicon setzen).', true);
      return;
    }
    if (source === 'custom' && !customUrl) {
      setStatus('Bitte ein Bild aus der Mediathek wählen.', true);
      return;
    }

    var opts = readOptions(parseInt(val('dg-qr-size', '280'), 10) || 280);
    applyFrameStyles(opts.frame);
    var cap = document.getElementById('dg-qr-caption-preview');
    if (cap) {
      cap.textContent = opts.caption;
    }
    var host = document.getElementById('dg-qr-canvas-host');
    if (!host) {
      return;
    }
    host.innerHTML = '';
    try {
      qrInstance = new window.QRCodeStyling(opts.styling);
      qrInstance.append(host);
      setStatus(usesCenter() ? 'Tipp: Mit dem Handy scannen und Lesbarkeit prüfen.' : '');
    } catch (e) {
      setStatus('QR-Code konnte nicht erzeugt werden.', true);
    }
  }

  function scheduleUpdate() {
    clearTimeout(updateTimer);
    updateTimer = setTimeout(renderPreview, 200);
  }

  function download(extension) {
    if (typeof window.QRCodeStyling === 'undefined') {
      setStatus('QR-Bibliothek nicht geladen.', true);
      return;
    }
    try {
      var exportSize = parseInt(val('dg-qr-export-size', '1200'), 10) || 1200;
      var opts = readOptions(exportSize);
      var instance = new window.QRCodeStyling(
        Object.assign({}, opts.styling, {
          type: extension === 'svg' ? 'svg' : 'canvas',
          width: exportSize,
          height: exportSize,
        })
      );
      instance.download({ name: opts.downloadName, extension: extension });
      setStatus(extension === 'png' ? 'PNG heruntergeladen.' : 'SVG heruntergeladen.');
    } catch (e) {
      setStatus('Download fehlgeschlagen.', true);
    }
  }

  function blobToDataUrl(blob) {
    return new Promise(function (resolve, reject) {
      var reader = new FileReader();
      reader.onload = function () {
        resolve(String(reader.result || ''));
      };
      reader.onerror = reject;
      reader.readAsDataURL(blob);
    });
  }

  function printQr() {
    if (typeof window.QRCodeStyling === 'undefined') {
      setStatus('QR-Bibliothek nicht geladen.', true);
      return;
    }
    var win = window.open('', '_blank', 'width=800,height=900');
    if (!win) {
      setStatus('Popup blockiert — Druckfenster erlauben.', true);
      return;
    }
    win.document.write(
      '<!doctype html><html><head><title>QR-Code Druck</title></head><body>' +
        '<p style="font-family:system-ui;text-align:center;margin-top:40px">QR wird vorbereitet …</p>' +
        '</body></html>'
    );
    win.document.close();

    var exportSize = parseInt(val('dg-qr-export-size', '1200'), 10) || 1200;
    var opts = readOptions(exportSize);
    var instance = new window.QRCodeStyling(
      Object.assign({}, opts.styling, {
        type: 'canvas',
        width: exportSize,
        height: exportSize,
      })
    );

    Promise.resolve()
      .then(function () {
        return instance.getRawData('png').then(function (blob) {
          if (blob) {
            return blobToDataUrl(blob);
          }
          return null;
        }).catch(function () {
          return null;
        });
      })
      .then(function (dataUrl) {
        if (dataUrl) {
          return dataUrl;
        }
        // Fallback: sichtbare Vorschau als PNG (Canvas speichert Pixel — innerHTML nicht)
        var previewCanvas = document.querySelector('#dg-qr-canvas-host canvas');
        if (previewCanvas && typeof previewCanvas.toDataURL === 'function') {
          return previewCanvas.toDataURL('image/png');
        }
        throw new Error('empty');
      })
      .then(function (dataUrl) {
        if (!dataUrl) {
          throw new Error('empty');
        }
        var frameCss = opts.frame.enabled
          ? 'border:' +
            opts.frame.width +
            'px solid ' +
            opts.frame.color +
            ';padding:' +
            opts.frame.padding +
            'px;border-radius:' +
            opts.frame.radius +
            'px;display:inline-block;background:#fff;'
          : 'display:inline-block;background:#fff;';
        var captionHtml = opts.caption
          ? '<p class="caption">' +
            opts.caption
              .replace(/&/g, '&amp;')
              .replace(/</g, '&lt;')
              .replace(/>/g, '&gt;') +
            '</p>'
          : '';
        win.document.open();
        win.document.write(
          '<!doctype html><html><head><title>QR-Code Druck</title><style>' +
            'body{font-family:system-ui,sans-serif;text-align:center;padding:24px;color:#111;}' +
            '.frame{margin:0 auto;}' +
            '.frame img{display:block;max-width:min(90vw,520px);height:auto;}' +
            '.caption{margin-top:16px;font-size:18px;font-weight:600;}' +
            '@media print{body{padding:0;} .frame img{max-width:140mm;}}' +
            '</style></head><body>' +
            '<div class="frame" style="' +
            frameCss +
            '"><img src="' +
            dataUrl +
            '" alt="QR-Code"></div>' +
            captionHtml +
            '</body></html>'
        );
        win.document.close();
        win.focus();
        // Bild kurz laden lassen, dann drucken
        setTimeout(function () {
          try {
            win.print();
          } catch (ignore) {}
        }, 350);
        setStatus('Druckvorschau geöffnet.');
      })
      .catch(function () {
        try {
          win.close();
        } catch (ignore) {}
        setStatus('Druckvorschau fehlgeschlagen — bitte PNG laden und daraus drucken.', true);
      });
  }

  function openMediaPicker() {
    var listUrl = root.getAttribute('data-media-list-url') || '/api/media?action=list';
    var existing = document.getElementById('dg-qr-media-picker');
    if (existing) {
      existing.remove();
    }
    var modal = document.createElement('div');
    modal.id = 'dg-qr-media-picker';
    modal.className = 'dg-modal';
    modal.innerHTML =
      '<div class="dg-modal__backdrop" data-close></div>' +
      '<div class="dg-modal__dialog" role="dialog" aria-modal="true">' +
      '<header class="dg-modal__header"><h2>Bild für QR-Mitte</h2>' +
      '<button type="button" class="dg-modal__close" data-close aria-label="Schließen">&times;</button></header>' +
      '<div class="dg-modal__body" data-body><p class="dg-field-hint">Laden …</p></div>' +
      '<footer class="dg-modal__footer"><button type="button" class="dg-button" data-close>Abbrechen</button></footer>' +
      '</div>';
    document.body.appendChild(modal);
    modal.addEventListener('click', function (ev) {
      if (ev.target.closest('[data-close]')) {
        modal.remove();
      }
    });
    fetch(listUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) {
        return r.json();
      })
      .then(function (payload) {
        var body = modal.querySelector('[data-body]');
        var items = [];
        if (payload && payload.data && Array.isArray(payload.data.items)) {
          items = payload.data.items;
        } else if (payload && Array.isArray(payload.items)) {
          items = payload.items;
        }
        if (!items.length) {
          body.innerHTML = '<p>Keine Bilder in der Mediathek. Bitte unter „Bilder“ hochladen.</p>';
          return;
        }
        var html = '<div class="dg-website-media-picker__grid">';
        items.forEach(function (item) {
          var id = item.media_id || item.id || '';
          var url = item.url || item.public_url || ('/app/media?id=' + encodeURIComponent(id));
          var title = item.title || item.original_name || id;
          html +=
            '<button type="button" class="dg-website-media-picker__item" data-id="' +
            id.replace(/"/g, '') +
            '" data-url="' +
            String(url).replace(/"/g, '&quot;') +
            '" title="' +
            String(title).replace(/"/g, '&quot;') +
            '"><img src="' +
            String(url).replace(/"/g, '&quot;') +
            '" alt=""></button>';
        });
        html += '</div>';
        body.innerHTML = html;
        body.addEventListener('click', function (ev) {
          var btn = ev.target.closest('[data-id]');
          if (!btn) {
            return;
          }
          var id = btn.getAttribute('data-id') || '';
          var url = btn.getAttribute('data-url') || '';
          var mediaInput = document.getElementById('dg-qr-center-media-id');
          if (mediaInput) {
            mediaInput.value = id;
          }
          customUrl = url;
          root.setAttribute('data-center-custom-url', url);
          var preview = document.getElementById('dg-qr-center-preview');
          if (preview) {
            preview.innerHTML = url ? '<img src="' + url + '" alt="" width="48" height="48">' : '';
          }
          var customRadio = root.querySelector('input[name="qr[center_image_source]"][value="custom"]');
          if (customRadio) {
            customRadio.checked = true;
          }
          modal.remove();
          scheduleUpdate();
        });
      })
      .catch(function () {
        var body = modal.querySelector('[data-body]');
        if (body) {
          body.innerHTML = '<p>Mediathek konnte nicht geladen werden.</p>';
        }
      });
  }

  root.addEventListener('change', function (ev) {
    if (ev.target && ev.target.matches('[data-dg-qr-preset]')) {
      applyPreset(ev.target.value);
    }
    if (ev.target && ev.target.matches('[data-dg-qr-field], input[name="qr[center_image_source]"]')) {
      scheduleUpdate();
    }
  });
  root.addEventListener('input', function (ev) {
    if (ev.target && ev.target.id === 'dg-qr-emoji-search') {
      emojiSearchQuery = ev.target.value || '';
      renderEmojiPicker();
      return;
    }
    if (ev.target && ev.target.matches('[data-dg-qr-field]')) {
      scheduleUpdate();
    }
  });
  root.addEventListener('click', function (ev) {
    var groupBtn = ev.target.closest('[data-emoji-group]');
    if (groupBtn) {
      emojiActiveGroup = groupBtn.getAttribute('data-emoji-group') || 'smileys';
      renderEmojiPicker();
      return;
    }
    var emojiBtn = ev.target.closest('[data-emoji]');
    if (emojiBtn) {
      var input = document.getElementById('dg-qr-emoji');
      if (input) {
        input.value = emojiBtn.getAttribute('data-emoji') || '';
      }
      var emojiRadio = root.querySelector('input[name="qr[center_image_source]"][value="emoji"]');
      if (emojiRadio) {
        emojiRadio.checked = true;
      }
      renderEmojiPicker();
      scheduleUpdate();
      return;
    }
    if (ev.target.closest('#dg-qr-pick-media')) {
      openMediaPicker();
      return;
    }
    if (ev.target.closest('#dg-qr-clear-media')) {
      var mediaInput = document.getElementById('dg-qr-center-media-id');
      if (mediaInput) {
        mediaInput.value = '';
      }
      customUrl = '';
      root.setAttribute('data-center-custom-url', '');
      var preview = document.getElementById('dg-qr-center-preview');
      if (preview) {
        preview.innerHTML = '';
      }
      scheduleUpdate();
      return;
    }
    if (ev.target.closest('#dg-qr-download-png')) {
      download('png');
      return;
    }
    if (ev.target.closest('#dg-qr-download-svg')) {
      download('svg');
      return;
    }
    if (ev.target.closest('#dg-qr-print')) {
      printQr();
    }
  });

  // Sync frame_enabled from current preset on load
  var presetChecked = root.querySelector('input[name="qr[frame_preset]"]:checked');
  if (presetChecked && !val('dg-qr-frame-width', '')) {
    applyPreset(presetChecked.value);
  }

  renderEmojiPicker();

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderPreview);
  } else {
    renderPreview();
  }
})();
