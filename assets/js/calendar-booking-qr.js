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
      updateFlyerSheet();
    } catch (e) {
      setStatus('QR-Code konnte nicht erzeugt werden.', true);
    }
  }

  var flyerQrInstance = null;
  var flyerBgUrl = root.getAttribute('data-flyer-bg-url') || '';

  function flyerAccent() {
    var picked = val('dg-flyer-accent', '');
    return picked || root.getAttribute('data-flyer-brand') || '#0f766e';
  }

  /** mm → Preview-Pixel (A6-Vorschau ~280px ≈ 105mm). */
  function quietPreviewPx() {
    var mm = parseInt(val('dg-flyer-quiet', '8'), 10) || 8;
    var sheet = document.getElementById('dg-flyer-sheet');
    var widthPx = sheet ? sheet.clientWidth || 280 : 280;
    var pxPerMm = widthPx / 105;
    return Math.max(6, Math.round(mm * pxPerMm));
  }

  function updateFlyerSheet() {
    var sheet = document.getElementById('dg-flyer-sheet');
    if (!sheet) {
      return;
    }
    var format = val('dg-flyer-format', 'a6');
    sheet.classList.remove('dg-booking-flyer-sheet--a6', 'dg-booking-flyer-sheet--a5', 'dg-booking-flyer-sheet--square');
    sheet.classList.add('dg-booking-flyer-sheet--' + format);
    sheet.style.setProperty('--flyer-bg', val('dg-flyer-bg', '#ffffff'));
    sheet.style.setProperty('--flyer-text', val('dg-flyer-text', '#1a1a1a'));
    sheet.style.setProperty('--flyer-accent', flyerAccent());
    sheet.style.setProperty('--flyer-quiet-px', quietPreviewPx() + 'px');
    sheet.style.setProperty('--flyer-font', root.getAttribute('data-flyer-font') || 'system-ui,sans-serif');
    sheet.style.setProperty('--flyer-headline-size', (parseInt(val('dg-flyer-headline-size', '18'), 10) || 18) + 'px');
    sheet.style.setProperty('--flyer-cta-size', (parseInt(val('dg-flyer-cta-size', '13'), 10) || 13) + 'px');
    if (flyerBgUrl) {
      sheet.style.setProperty('--flyer-bg-image', 'url("' + flyerBgUrl.replace(/"/g, '\\"') + '")');
    } else {
      sheet.style.setProperty('--flyer-bg-image', 'none');
    }

    var showLogo = document.getElementById('dg-flyer-show-logo');
    var logoEl = document.getElementById('dg-flyer-logo');
    var companyEl = document.getElementById('dg-flyer-company');
    var logoUrl = root.getAttribute('data-logo-url') || '';
    var company = root.getAttribute('data-company-name') || '';
    if (companyEl) {
      companyEl.textContent = company;
    }
    if (logoEl) {
      if (showLogo && showLogo.checked && logoUrl) {
        logoEl.src = logoUrl;
        logoEl.hidden = false;
      } else {
        logoEl.removeAttribute('src');
        logoEl.hidden = true;
      }
    }

    var headline = val('dg-flyer-headline', 'Jetzt online Termin buchen');
    var headlineEl = document.getElementById('dg-flyer-headline-preview');
    if (headlineEl) {
      headlineEl.textContent = headline;
    }

    var cta = val('dg-flyer-cta', 'Code scannen & Termin buchen');
    var pos = val('dg-flyer-cta-pos', 'below');
    var above = document.getElementById('dg-flyer-cta-above');
    var below = document.getElementById('dg-flyer-cta-below');
    if (above) {
      above.textContent = cta;
      above.hidden = pos !== 'above';
    }
    if (below) {
      below.textContent = cta;
      below.hidden = pos !== 'below';
    }

    renderFlyerQr();
  }

  function renderFlyerQr() {
    if (typeof window.QRCodeStyling === 'undefined') {
      return;
    }
    var host = document.getElementById('dg-flyer-qr-host');
    if (!host) {
      return;
    }
    var format = val('dg-flyer-format', 'a6');
    var pixel = format === 'a5' ? 160 : format === 'square' ? 150 : 140;
    var opts = readOptions(pixel);
    opts.styling.margin = Math.max(4, Math.min(12, parseInt(val('dg-qr-margin', '8'), 10) || 8));
    host.innerHTML = '';
    try {
      flyerQrInstance = new window.QRCodeStyling(opts.styling);
      flyerQrInstance.append(host);
    } catch (ignore) {
      host.textContent = 'QR nicht darstellbar';
    }
  }

  function getQrPngDataUrl(pixelSize) {
    var opts = readOptions(pixelSize);
    var instance = new window.QRCodeStyling(
      Object.assign({}, opts.styling, {
        type: 'canvas',
        width: pixelSize,
        height: pixelSize,
      })
    );
    return instance.getRawData('png').then(function (blob) {
      if (!blob) {
        throw new Error('empty');
      }
      return blobToDataUrl(blob);
    });
  }

  function escapeHtml(text) {
    return String(text || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function printFlyer() {
    getQrPngDataUrl(900)
      .then(function (qrUrl) {
        var win = window.open('', '_blank', 'width=900,height=1200');
        if (!win) {
          setStatus('Popup blockiert — Druckfenster erlauben.', true);
          return;
        }
        var format = val('dg-flyer-format', 'a6');
        var pageSize = format === 'a5' ? 'A5' : format === 'square' ? '100mm 100mm' : 'A6';
        var quietMm = Math.max(5, Math.min(20, parseInt(val('dg-flyer-quiet', '8'), 10) || 8));
        var headlinePt = Math.max(12, Math.min(32, parseInt(val('dg-flyer-headline-size', '18'), 10) || 18));
        var ctaPt = Math.max(10, Math.min(22, parseInt(val('dg-flyer-cta-size', '13'), 10) || 13));
        var qrMm = format === 'a5' ? 58 : format === 'square' ? 48 : 52;
        var showLogo = document.getElementById('dg-flyer-show-logo');
        var logoUrl = root.getAttribute('data-logo-url') || '';
        var company = root.getAttribute('data-company-name') || '';
        var logoHtml =
          showLogo && showLogo.checked && logoUrl
            ? '<img class="logo" src="' + escapeHtml(logoUrl) + '" alt="">'
            : '';
        var pos = val('dg-flyer-cta-pos', 'below');
        var cta = escapeHtml(val('dg-flyer-cta', 'Code scannen & Termin buchen'));
        var ctaAbove = pos === 'above' ? '<p class="cta">' + cta + '</p>' : '';
        var ctaBelow = pos === 'below' ? '<p class="cta">' + cta + '</p>' : '';
        var bgCss =
          'background-color:' +
          escapeHtml(val('dg-flyer-bg', '#ffffff')) +
          ';' +
          (flyerBgUrl
            ? 'background-image:url("' +
              escapeHtml(flyerBgUrl) +
              '");background-size:cover;background-position:center;'
            : '');
        win.document.write(
          '<!doctype html><html><head><title>Flyer Druck</title><style>' +
            '@page{size:' +
            pageSize +
            ';margin:0;}' +
            'html,body{margin:0;padding:0;}' +
            'body{font-family:' +
            (root.getAttribute('data-flyer-font') || 'system-ui,sans-serif') +
            ';}' +
            '.sheet{box-sizing:border-box;width:100vw;min-height:100vh;padding:10mm 9mm 11mm;' +
            bgCss +
            'color:' +
            escapeHtml(val('dg-flyer-text', '#1a1a1a')) +
            ';display:flex;flex-direction:column;align-items:center;text-align:center;}' +
            '.brand{flex:0 0 auto;}' +
            '.logo{max-height:16mm;max-width:55%;object-fit:contain;margin-bottom:2mm;}' +
            '.company{margin:0 0 3mm;font-size:9pt;letter-spacing:.04em;text-transform:uppercase;font-weight:600;color:' +
            escapeHtml(flyerAccent()) +
            ';}' +
            '.headline{margin:0;font-size:' +
            headlinePt +
            'pt;line-height:1.25;font-weight:700;flex:0 0 auto;' +
            (flyerBgUrl ? 'text-shadow:0 0 4mm rgba(255,255,255,.75);' : '') +
            '}' +
            '.cta{margin:2mm 0 0;font-size:' +
            ctaPt +
            'pt;font-weight:600;color:' +
            escapeHtml(flyerAccent()) +
            ';' +
            (flyerBgUrl ? 'text-shadow:0 0 3mm rgba(255,255,255,.7);' : '') +
            '}' +
            '.qr-block{margin-top:auto;margin-bottom:2mm;display:flex;flex-direction:column;align-items:center;gap:2mm;flex:0 0 auto;}' +
            '.qr{background:#fff;padding:' +
            quietMm +
            'mm;display:inline-block;border-radius:2mm;box-shadow:0 1mm 3mm rgba(0,0,0,.12);}' +
            '.qr img{display:block;width:' +
            qrMm +
            'mm;height:' +
            qrMm +
            'mm;}' +
            '@media print{.sheet{width:auto;min-height:auto;height:100%;}}' +
            '</style></head><body><div class="sheet"><div class="brand">' +
            logoHtml +
            (company ? '<p class="company">' + escapeHtml(company) + '</p>' : '') +
            '</div>' +
            '<h1 class="headline">' +
            escapeHtml(val('dg-flyer-headline', '')) +
            '</h1>' +
            '<div class="qr-block">' +
            ctaAbove +
            '<div class="qr"><img src="' +
            qrUrl +
            '" alt="QR-Code"></div>' +
            ctaBelow +
            '</div></div></body></html>'
        );
        win.document.close();
        win.focus();
        setTimeout(function () {
          try {
            win.print();
          } catch (ignore) {}
        }, 400);
        setStatus('Flyer-Druckvorschau geöffnet.');
      })
      .catch(function () {
        setStatus('Flyer-Druck fehlgeschlagen.', true);
      });
  }

  function downloadFlyerPng() {
    getQrPngDataUrl(700)
      .then(function (qrUrl) {
        var format = val('dg-flyer-format', 'a6');
        var w = format === 'a5' ? 1240 : format === 'square' ? 1080 : 1050;
        var h = format === 'a5' ? 1754 : format === 'square' ? 1080 : 1480;
        var canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        var ctx = canvas.getContext('2d');
        if (!ctx) {
          throw new Error('canvas');
        }
        var bg = val('dg-flyer-bg', '#ffffff');
        var text = val('dg-flyer-text', '#1a1a1a');
        var accent = flyerAccent();
        var padX = Math.round(w * 0.09);
        var y = Math.round(h * 0.055);
        var headlinePx = Math.round(((parseInt(val('dg-flyer-headline-size', '18'), 10) || 18) / 105) * w * 0.85);
        var ctaPx = Math.round(((parseInt(val('dg-flyer-cta-size', '13'), 10) || 13) / 105) * w * 0.85);
        var quietMm = Math.max(5, Math.min(20, parseInt(val('dg-flyer-quiet', '8'), 10) || 8));
        var quiet = (quietMm / 105) * w;
        var qrSize = Math.min(w * 0.42, h * 0.34);
        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';

        function loadImage(src) {
          return new Promise(function (resolve) {
            if (!src) {
              resolve(null);
              return;
            }
            var img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = function () {
              resolve(img);
            };
            img.onerror = function () {
              resolve(null);
            };
            img.src = src;
          });
        }

        function drawCover(img) {
          if (!img) {
            ctx.fillStyle = bg;
            ctx.fillRect(0, 0, w, h);
            return;
          }
          var scale = Math.max(w / img.width, h / img.height);
          var dw = img.width * scale;
          var dh = img.height * scale;
          ctx.drawImage(img, (w - dw) / 2, (h - dh) / 2, dw, dh);
        }

        var showLogo = document.getElementById('dg-flyer-show-logo');
        var logoUrl = showLogo && showLogo.checked ? root.getAttribute('data-logo-url') || '' : '';
        var company = root.getAttribute('data-company-name') || '';

        return Promise.all([loadImage(flyerBgUrl), loadImage(logoUrl), loadImage(qrUrl)]).then(function (imgs) {
          drawCover(imgs[0]);
          var logoImg = imgs[1];
          var qrImg = imgs[2];
          if (logoImg) {
            var maxLogoW = w * 0.45;
            var maxLogoH = h * 0.07;
            var scale = Math.min(maxLogoW / logoImg.width, maxLogoH / logoImg.height);
            var lw = logoImg.width * scale;
            var lh = logoImg.height * scale;
            ctx.drawImage(logoImg, (w - lw) / 2, y, lw, lh);
            y += lh + h * 0.012;
          }
          if (company) {
            ctx.fillStyle = accent;
            ctx.font = '600 ' + Math.round(h * 0.02) + 'px system-ui,sans-serif';
            ctx.fillText(company.toUpperCase(), w / 2, y);
            y += h * 0.035;
          }
          ctx.fillStyle = text;
          ctx.font = '700 ' + headlinePx + 'px system-ui,sans-serif';
          var headline = val('dg-flyer-headline', '');
          var lineH = Math.round(headlinePx * 1.25);
          wrapText(ctx, headline, w / 2, y, w - padX * 2, lineH);
          y += measureWrap(ctx, headline, w - padX * 2, lineH) + h * 0.02;

          var cta = val('dg-flyer-cta', '');
          var pos = val('dg-flyer-cta-pos', 'below');
          var qrBox = qrSize + quiet * 2;
          var qrX = (w - qrBox) / 2;
          var bottomReserve = pos === 'below' ? h * 0.08 + ctaPx : h * 0.04;
          var qrY = Math.max(y + h * 0.02, h - qrBox - bottomReserve);

          if (pos === 'above') {
            ctx.fillStyle = accent;
            ctx.font = '600 ' + ctaPx + 'px system-ui,sans-serif';
            ctx.fillText(cta, w / 2, qrY - ctaPx - h * 0.015);
          }

          ctx.fillStyle = '#ffffff';
          ctx.fillRect(qrX, qrY, qrBox, qrBox);
          if (qrImg) {
            ctx.drawImage(qrImg, qrX + quiet, qrY + quiet, qrSize, qrSize);
          }
          if (pos === 'below') {
            ctx.fillStyle = accent;
            ctx.font = '600 ' + ctaPx + 'px system-ui,sans-serif';
            ctx.fillText(cta, w / 2, qrY + qrBox + h * 0.018);
          }

          var a = document.createElement('a');
          a.href = canvas.toDataURL('image/png');
          a.download = (val('dg-qr-download-name', 'termin-buchen') || 'termin-buchen') + '-flyer.png';
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
          setStatus('Flyer-PNG heruntergeladen.');
        });
      })
      .catch(function () {
        setStatus('Flyer-PNG fehlgeschlagen.', true);
      });
  }

  function wrapText(ctx, text, x, y, maxWidth, lineHeight) {
    var words = String(text || '').split(/\s+/);
    var line = '';
    var yy = y;
    words.forEach(function (word, i) {
      var test = line ? line + ' ' + word : word;
      if (ctx.measureText(test).width > maxWidth && line) {
        ctx.fillText(line, x, yy);
        line = word;
        yy += lineHeight;
      } else {
        line = test;
      }
      if (i === words.length - 1 && line) {
        ctx.fillText(line, x, yy);
      }
    });
  }

  function measureWrap(ctx, text, maxWidth, lineHeight) {
    var words = String(text || '').split(/\s+/);
    var line = '';
    var lines = 1;
    words.forEach(function (word) {
      var test = line ? line + ' ' + word : word;
      if (ctx.measureText(test).width > maxWidth && line) {
        line = word;
        lines += 1;
      } else {
        line = test;
      }
    });
    return lines * lineHeight;
  }

  function setPreviewMode(mode) {
    var qrMode = document.getElementById('dg-qr-mode-qr');
    var flyerMode = document.getElementById('dg-qr-mode-flyer');
    root.querySelectorAll('[data-preview-mode]').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute('data-preview-mode') === mode);
    });
    if (qrMode) {
      qrMode.hidden = mode !== 'qr';
    }
    if (flyerMode) {
      flyerMode.hidden = mode !== 'flyer';
    }
    if (mode === 'flyer') {
      updateFlyerSheet();
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

  function openMediaPicker(mode) {
    mode = mode || 'qr-center';
    var listUrl = root.getAttribute('data-media-list-url') || '/api/media?action=list';
    var existing = document.getElementById('dg-qr-media-picker');
    if (existing) {
      existing.remove();
    }
    var modal = document.createElement('div');
    modal.id = 'dg-qr-media-picker';
    modal.className = 'dg-modal';
    var title = mode === 'flyer-bg' ? 'Hintergrundbild für Flyer' : 'Bild für QR-Mitte';
    modal.innerHTML =
      '<div class="dg-modal__backdrop" data-close></div>' +
      '<div class="dg-modal__dialog" role="dialog" aria-modal="true">' +
      '<header class="dg-modal__header"><h2>' +
      title +
      '</h2>' +
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
          if (mode === 'flyer-bg') {
            var bgInput = document.getElementById('dg-flyer-bg-media-id');
            if (bgInput) {
              bgInput.value = id;
            }
            flyerBgUrl = url;
            root.setAttribute('data-flyer-bg-url', url);
            var bgPrev = document.getElementById('dg-flyer-bg-preview');
            if (bgPrev) {
              bgPrev.innerHTML = url ? '<img src="' + url + '" alt="" width="64" height="40">' : '';
            }
            modal.remove();
            updateFlyerSheet();
            return;
          }
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
      openMediaPicker('qr-center');
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
    if (ev.target.closest('#dg-flyer-pick-bg')) {
      openMediaPicker('flyer-bg');
      return;
    }
    if (ev.target.closest('#dg-flyer-clear-bg')) {
      var bgInput = document.getElementById('dg-flyer-bg-media-id');
      if (bgInput) {
        bgInput.value = '';
      }
      flyerBgUrl = '';
      root.setAttribute('data-flyer-bg-url', '');
      var bgPrev = document.getElementById('dg-flyer-bg-preview');
      if (bgPrev) {
        bgPrev.innerHTML = '';
      }
      updateFlyerSheet();
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
      return;
    }
    var modeBtn = ev.target.closest('[data-preview-mode]');
    if (modeBtn) {
      setPreviewMode(modeBtn.getAttribute('data-preview-mode') || 'qr');
      return;
    }
    var presetBtn = ev.target.closest('[data-flyer-set]');
    if (presetBtn) {
      var which = presetBtn.getAttribute('data-flyer-set');
      var value = presetBtn.getAttribute('data-value') || '';
      if (which === 'headline') {
        var hi = document.getElementById('dg-flyer-headline');
        if (hi) {
          hi.value = value;
        }
      }
      if (which === 'cta') {
        var ci = document.getElementById('dg-flyer-cta');
        if (ci) {
          ci.value = value;
        }
      }
      updateFlyerSheet();
      return;
    }
    if (ev.target.closest('#dg-flyer-print')) {
      printFlyer();
      return;
    }
    if (ev.target.closest('#dg-flyer-download-png')) {
      downloadFlyerPng();
    }
  });

  // Sync frame_enabled from current preset on load
  var presetChecked = root.querySelector('input[name="qr[frame_preset]"]:checked');
  if (presetChecked && !val('dg-qr-frame-width', '')) {
    applyPreset(presetChecked.value);
  }

  renderEmojiPicker();
  updateFlyerSheet();

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderPreview);
  } else {
    renderPreview();
  }
})();
