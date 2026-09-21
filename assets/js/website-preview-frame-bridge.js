/**
 * Empfängt Live-Styles aus dem Website-Editor (Erweiterte Einstellungen)
 * und wendet sie auf echte ws-block-Elemente in /vorschau/?frame=1 an.
 */
(function () {
  var HIGHLIGHT = 'ws-block--adv-focus';
  var styleTag = null;

  function ensureHighlightCss() {
    if (styleTag) return;
    styleTag = document.createElement('style');
    styleTag.textContent =
      '.' + HIGHLIGHT + '{' +
      'outline:2px solid #2563eb!important;' +
      'outline-offset:3px;' +
      'scroll-margin:80px;' +
      '}';
    document.head.appendChild(styleTag);
  }

  function clearHighlight() {
    document.querySelectorAll('.' + HIGHLIGHT).forEach(function (el) {
      el.classList.remove(HIGHLIGHT);
    });
  }

  function applyAdvanced(msg) {
    ensureHighlightCss();
    clearHighlight();
    var blockId = String(msg.blockId || '');
    if (!blockId) {
      window.parent.postMessage({ source: 'dg-website-preview-frame', type: 'advanced-preview-result', found: false }, window.location.origin);
      return;
    }
    var el = document.querySelector('[data-block-id="' + blockId.replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"]');
    if (!el) {
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
    el.classList.add(HIGHLIGHT);

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
    // id nur setzen wenn gültig und nicht kollidierend mit fremdem Element
    if (attrs.id) {
      var existing = document.getElementById(attrs.id);
      if (!existing || existing === el) {
        el.id = attrs.id;
      }
    }

    try {
      el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } catch (e) {
      el.scrollIntoView(true);
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

  // Parent signalisieren: Frame bereit
  window.parent.postMessage({ source: 'dg-website-preview-frame', type: 'advanced-preview-ready' }, window.location.origin);
})();
