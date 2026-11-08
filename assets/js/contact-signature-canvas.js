/**
 * Minimal signature canvas → PNG data-URL for contact form (ES3a).
 */
(function () {
  'use strict';

  function initPad(root) {
    var canvas = root.querySelector('[data-signature-canvas]');
    var form = root.closest('form');
    var hidden = form ? form.querySelector('[name="signature_png"]') : null;
    var clearBtn = root.querySelector('[data-signature-clear]');
    if (!canvas || !form || !hidden || !(canvas instanceof HTMLCanvasElement)) {
      return;
    }

    var ctx = canvas.getContext('2d');
    if (!ctx) {
      return;
    }

    var drawing = false;
    var hasStroke = false;
    var lastX = 0;
    var lastY = 0;

    function sizeCanvas() {
      var cssW = Math.max(280, Math.floor(canvas.clientWidth || 560));
      var cssH = Math.max(120, Math.floor(canvas.clientHeight || 160));
      var ratio = window.devicePixelRatio || 1;
      canvas.width = Math.floor(cssW * ratio);
      canvas.height = Math.floor(cssH * ratio);
      ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
      ctx.strokeStyle = '#111';
      ctx.lineWidth = 2.2;
      ctx.fillStyle = '#fff';
      ctx.fillRect(0, 0, cssW, cssH);
      hasStroke = false;
      hidden.value = '';
    }

    function pos(evt) {
      var rect = canvas.getBoundingClientRect();
      var src = evt.touches && evt.touches[0] ? evt.touches[0] : evt;
      return {
        x: src.clientX - rect.left,
        y: src.clientY - rect.top,
      };
    }

    function start(evt) {
      drawing = true;
      var p = pos(evt);
      lastX = p.x;
      lastY = p.y;
      evt.preventDefault();
    }

    function move(evt) {
      if (!drawing) {
        return;
      }
      var p = pos(evt);
      ctx.beginPath();
      ctx.moveTo(lastX, lastY);
      ctx.lineTo(p.x, p.y);
      ctx.stroke();
      lastX = p.x;
      lastY = p.y;
      hasStroke = true;
      evt.preventDefault();
    }

    function end(evt) {
      drawing = false;
      if (evt) {
        evt.preventDefault();
      }
    }

    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    window.addEventListener('mouseup', end);
    canvas.addEventListener('touchstart', start, { passive: false });
    canvas.addEventListener('touchmove', move, { passive: false });
    canvas.addEventListener('touchend', end, { passive: false });
    canvas.addEventListener('touchcancel', end, { passive: false });

    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        sizeCanvas();
      });
    }

    form.addEventListener('submit', function (evt) {
      if (!hasStroke) {
        evt.preventDefault();
        window.alert('Bitte zuerst eine Unterschrift zeichnen.');
        return;
      }
      hidden.value = canvas.toDataURL('image/png');
    });

    sizeCanvas();
    window.addEventListener('resize', function () {
      // Keep strokes: only size once on load for minimal pad.
    });
  }

  function boot() {
    document.querySelectorAll('[data-signature-pad]').forEach(initPad);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
