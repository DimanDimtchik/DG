(function () {
  'use strict';

  function renderBookingQr(node) {
    if (!node || node.dataset.qrRendered === '1') {
      return;
    }
    if (typeof qrcode === 'undefined') {
      node.textContent = 'QR-Bibliothek nicht geladen.';
      return;
    }
    var url = node.getAttribute('data-qr-url') || '';
    if (url === '') {
      node.textContent = 'Keine Buchungs-URL hinterlegt.';
      return;
    }
    if (qrcode.stringToBytesFuncs && qrcode.stringToBytesFuncs['UTF-8']) {
      qrcode.stringToBytes = qrcode.stringToBytesFuncs['UTF-8'];
    }
    try {
      var qr = qrcode(0, 'M');
      qr.addData(url, 'Byte');
      qr.make();
      var img = document.createElement('img');
      img.src = qr.createDataURL(6, 12);
      img.alt = 'QR-Code zur Online-Terminbuchung';
      img.className = 'dg-booking-qr__img';
      node.innerHTML = '';
      node.appendChild(img);
      node.dataset.qrRendered = '1';
    } catch (e) {
      node.textContent = 'QR-Code konnte nicht erzeugt werden.';
    }
  }

  function init() {
    var nodes = document.querySelectorAll('#dg-booking-qrcode[data-qr-url], .dg-booking-qr__canvas[data-qr-url]');
    for (var i = 0; i < nodes.length; i += 1) {
      renderBookingQr(nodes[i]);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
