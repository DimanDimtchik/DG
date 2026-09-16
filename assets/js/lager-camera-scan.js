(function () {
  if (typeof Html5Qrcode === 'undefined') {
    return;
  }

  var activeScanner = null;
  var modal = null;

  function ensureModal() {
    if (modal) {
      return modal;
    }
    modal = document.createElement('div');
    modal.className = 'dg-camera-scan-modal';
    modal.hidden = true;
    modal.innerHTML =
      '<div class="dg-camera-scan-modal__backdrop"></div>' +
      '<div class="dg-camera-scan-modal__panel" role="dialog" aria-modal="true" aria-label="Kamera-Scan">' +
      '<header class="dg-camera-scan-modal__head">' +
      '<strong>Kamera-Scan</strong>' +
      '<button type="button" class="dg-button dg-button--small" data-camera-close>Schließen</button>' +
      '</header>' +
      '<p class="dg-field-hint">CODE128 / EAN — Kamera auf Strichcode richten. HTTPS erforderlich.</p>' +
      '<div id="dg-camera-scan-reader" class="dg-camera-scan-modal__reader"></div>' +
      '<div class="dg-camera-scan-modal__status dg-muted" data-camera-status></div>' +
      '</div>';
    document.body.appendChild(modal);

    modal.querySelector('[data-camera-close]')?.addEventListener('click', close);
    modal.querySelector('.dg-camera-scan-modal__backdrop')?.addEventListener('click', close);

    if (!document.getElementById('dg-camera-scan-styles')) {
      var style = document.createElement('style');
      style.id = 'dg-camera-scan-styles';
      style.textContent =
        '.dg-camera-scan-modal{position:fixed;inset:0;z-index:1200;display:flex;align-items:center;justify-content:center;padding:1rem}' +
        '.dg-camera-scan-modal[hidden]{display:none!important}' +
        '.dg-camera-scan-modal__backdrop{position:absolute;inset:0;background:rgba(0,0,0,.45)}' +
        '.dg-camera-scan-modal__panel{position:relative;background:#fff;border-radius:8px;max-width:520px;width:100%;padding:1rem;box-shadow:0 8px 30px rgba(0,0,0,.2)}' +
        '.dg-camera-scan-modal__head{display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem}' +
        '.dg-camera-scan-modal__reader{width:100%;min-height:240px;overflow:hidden;border:1px solid #dfe3ea;border-radius:6px}' +
        '.dg-camera-scan-btn{margin-left:.5rem}';
      document.head.appendChild(style);
    }

    return modal;
  }

  function stopScanner() {
    if (!activeScanner) {
      return Promise.resolve();
    }
    var scanner = activeScanner;
    activeScanner = null;
    return scanner.stop().then(function () {
      return scanner.clear();
    }).catch(function () {
      return undefined;
    });
  }

  function close() {
    stopScanner().finally(function () {
      if (modal) {
        modal.hidden = true;
      }
    });
  }

  function supportedFormats() {
    if (typeof Html5QrcodeSupportedFormats === 'undefined') {
      return undefined;
    }
    return [
      Html5QrcodeSupportedFormats.CODE_128,
      Html5QrcodeSupportedFormats.EAN_13,
      Html5QrcodeSupportedFormats.EAN_8,
      Html5QrcodeSupportedFormats.CODE_39,
      Html5QrcodeSupportedFormats.QR_CODE,
    ];
  }

  window.dgLagerCameraScan = {
    open: function (onCode) {
      ensureModal();
      if (!modal) {
        return;
      }
      modal.hidden = false;
      var statusEl = modal.querySelector('[data-camera-status]');
      if (statusEl) {
        statusEl.textContent = 'Kamera wird gestartet …';
      }

      stopScanner().then(function () {
        activeScanner = new Html5Qrcode('dg-camera-scan-reader');
        var config = {
          fps: 10,
          qrbox: function (viewfinderWidth, viewfinderHeight) {
            var width = Math.min(viewfinderWidth * 0.9, 320);
            var height = Math.min(viewfinderHeight * 0.55, 180);
            return { width: width, height: height };
          },
        };
        var formats = supportedFormats();
        if (formats) {
          config.formatsToSupport = formats;
        }

        return activeScanner.start(
          { facingMode: 'environment' },
          config,
          function (decodedText) {
            if (statusEl) {
              statusEl.textContent = 'Erkannt: ' + decodedText;
            }
            close();
            if (typeof onCode === 'function') {
              onCode(decodedText);
            }
          },
          function () {
            // scan failure frame — ignore
          }
        );
      }).catch(function (err) {
        if (statusEl) {
          statusEl.textContent = 'Kamera nicht verfügbar: ' + (err && err.message ? err.message : 'Berechtigung prüfen');
        }
      });
    },
    close: close,
  };

})();
