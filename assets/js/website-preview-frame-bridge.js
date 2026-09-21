/**
 * Empfängt Live-Styles aus dem Website-Editor (Erweiterte Einstellungen)
 * und zeigt in /vorschau/?frame=1 nur den gewählten Block (Rest ausgeblendet).
 */
(function () {
  var HIGHLIGHT = 'ws-block--adv-focus';
  var HIDDEN = 'ws-adv-crop-hidden';
  var TARGET = 'ws-adv-crop-target';
  var BODY_MODE = 'ws-adv-crop-mode';
  var styleTag = null;

  function ensureCropCss() {
    if (styleTag) return;
    styleTag = document.createElement('style');
    styleTag.textContent = [
      'body.' + BODY_MODE + '{margin:0;padding:0;background:#f1f5f9;min-height:100%;overflow:hidden;}',
      'body.' + BODY_MODE + ' .ws-header,',
      'body.' + BODY_MODE + ' .ws-footer,',
      'body.' + BODY_MODE + ' .ws-preview-banner,',
      'body.' + BODY_MODE + ' .ws-legal-tabs,',
      'body.' + BODY_MODE + ' #cc-banner,',
      'body.' + BODY_MODE + ' .cc-banner,',
      'body.' + BODY_MODE + ' [class*="cookie"]{display:none!important;}',
      'body.' + BODY_MODE + ' .ws-main{padding:8px!important;margin:0!important;max-width:none!important;}',
      'body.' + BODY_MODE + ' .ws-row{display:block!important;margin:0!important;}',
      'body.' + BODY_MODE + ' .ws-col{width:100%!important;max-width:100%!important;flex:none!important;padding:0!important;}',
      'body.' + BODY_MODE + ' .' + HIDDEN + '{display:none!important;}',
      'body.' + BODY_MODE + ' .' + TARGET + '{',
      '  margin:0!important;',
      '  max-width:100%;',
      '  outline:2px solid #2563eb;',
      '  outline-offset:2px;',
      '}',
      '.' + HIGHLIGHT + '{scroll-margin:0;}',
    ].join('');
    document.head.appendChild(styleTag);
  }

  function clearCropMode() {
    document.body.classList.remove(BODY_MODE);
    document.querySelectorAll('.' + HIDDEN + ',.' + TARGET + ',.' + HIGHLIGHT).forEach(function (el) {
      el.classList.remove(HIDDEN, TARGET, HIGHLIGHT);
    });
  }

  function enterCropMode(target) {
    ensureCropCss();
    clearCropMode();
    document.body.classList.add(BODY_MODE);

    document.querySelectorAll('.ws-block').forEach(function (block) {
      if (block === target) {
        block.classList.add(TARGET, HIGHLIGHT);
      } else {
        block.classList.add(HIDDEN);
      }
    });

    // Leere Zeilen/Spalten ohne sichtbaren Block ausblenden
    document.querySelectorAll('.ws-row').forEach(function (row) {
      if (!row.querySelector('.' + TARGET)) {
        row.classList.add(HIDDEN);
      }
    });
  }

  function applyAdvanced(msg) {
    var blockId = String(msg.blockId || '');
    if (!blockId) {
      clearCropMode();
      window.parent.postMessage({ source: 'dg-website-preview-frame', type: 'advanced-preview-result', found: false }, window.location.origin);
      return;
    }
    var el = document.querySelector('[data-block-id="' + blockId.replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"]');
    if (!el) {
      clearCropMode();
      window.parent.postMessage({ source: 'dg-website-preview-frame', type: 'advanced-preview-result', found: false, blockId: blockId }, window.location.origin);
      return;
    }

    if (!el.getAttribute('data-adv-base-class')) {
      el.setAttribute('data-adv-base-class', el.className);
    }
    if (!el.hasAttribute('data-adv-base-style')) {
      el.setAttribute('data-adv-base-style', el.getAttribute('style') || '');
    }

    var baseClass = el.getAttribute('data-adv-base-class') || 'ws-block';
    var extra = String(msg.className || '').trim();
    el.className = extra ? (baseClass + ' ' + extra) : baseClass;

    var css = String(msg.css || '');
    if (css) {
      el.setAttribute('style', css);
    } else {
      var baseStyle = el.getAttribute('data-adv-base-style') || '';
      if (baseStyle) el.setAttribute('style', baseStyle);
      else el.removeAttribute('style');
    }

    var attrs = msg.attrs && typeof msg.attrs === 'object' ? msg.attrs : {};
    ['title', 'aria-label'].forEach(function (name) {
      var key = name === 'aria-label' ? 'ariaLabel' : name;
      var val = attrs[key] != null ? String(attrs[key]) : (attrs[name] != null ? String(attrs[name]) : '');
      if (val) el.setAttribute(name, val);
      else el.removeAttribute(name);
    });
    if (attrs.id) {
      var existing = document.getElementById(attrs.id);
      if (!existing || existing === el) {
        el.id = attrs.id;
      }
    }

    enterCropMode(el);

    try {
      window.scrollTo(0, 0);
      el.scrollIntoView({ behavior: 'instant', block: 'start' });
    } catch (e) {
      try { el.scrollIntoView(true); } catch (e2) { /* ignore */ }
    }

    window.parent.postMessage({
      source: 'dg-website-preview-frame',
      type: 'advanced-preview-result',
      found: true,
      blockId: blockId,
    }, window.location.origin);
  }

  window.addEventListener('message', function (event) {
    if (event.origin !== window.location.origin) return;
    var data = event.data;
    if (!data || data.source !== 'dg-website-builder' || data.type !== 'advanced-preview') return;
    applyAdvanced(data);
  });

  window.parent.postMessage({ source: 'dg-website-preview-frame', type: 'advanced-preview-ready' }, window.location.origin);
})();
