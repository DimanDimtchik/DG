(function () {
  'use strict';

  function makeQrDataUrl(payload) {
    if (typeof qrcode === 'undefined') {
      return null;
    }
    // EPC/GiroCode nutzt UTF-8 (Zeichensatz 1).
    if (qrcode.stringToBytesFuncs && qrcode.stringToBytesFuncs['UTF-8']) {
      qrcode.stringToBytes = qrcode.stringToBytesFuncs['UTF-8'];
    }
    var qr = qrcode(0, 'M');
    qr.addData(payload, 'Byte');
    qr.make();
    return qr.createDataURL(6, 12);
  }

  function renderOne(node) {
    if (node.dataset.qrRendered === '1') {
      return;
    }
    var payload = node.getAttribute('data-qr-payload');
    if (!payload) {
      return;
    }
    var url;
    try {
      url = makeQrDataUrl(payload);
    } catch (e) {
      node.textContent = 'QR-Code konnte nicht erzeugt werden.';
      return;
    }
    if (!url) {
      return;
    }
    var img = document.createElement('img');
    img.src = url;
    img.alt = 'GiroCode / QR-Code für Überweisung';
    img.className = 'dg-transfer-qr__img';
    node.innerHTML = '';
    node.appendChild(img);
    node.dataset.qrRendered = '1';
    node.dataset.qrUrl = url;

    var box = node.closest('.dg-transfer__qrbox');
    if (box) {
      var dl = box.querySelector('[data-qr-download]');
      if (dl) {
        dl.href = url;
      }
    }
  }

  function renderAll() {
    var nodes = document.querySelectorAll('.dg-transfer-qr[data-qr-payload]');
    Array.prototype.forEach.call(nodes, renderOne);
  }

  function esc(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function printSlip(transferId) {
    var details = document.getElementById('transfer-' + transferId);
    if (!details) {
      return;
    }
    var qrNode = details.querySelector('.dg-transfer-qr');
    if (qrNode) {
      renderOne(qrNode);
    }
    var qrUrl = qrNode ? qrNode.dataset.qrUrl : '';
    var recipient = qrNode ? qrNode.getAttribute('data-recipient') : '';
    var iban = qrNode ? qrNode.getAttribute('data-iban') : '';
    var bic = qrNode ? qrNode.getAttribute('data-bic') : '';
    var amount = qrNode ? qrNode.getAttribute('data-amount') : '';
    var purpose = qrNode ? qrNode.getAttribute('data-purpose') : '';

    var html =
      '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">' +
      '<title>Überweisung ' + esc(recipient) + '</title>' +
      '<style>' +
      'body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:24px;}' +
      '.slip{max-width:640px;border:2px solid #111;border-radius:8px;padding:20px 24px;}' +
      '.slip h1{font-size:18px;margin:0 0 4px;}' +
      '.slip .sub{color:#555;font-size:12px;margin:0 0 16px;}' +
      '.grid{display:flex;gap:24px;}' +
      '.fields{flex:1;}' +
      '.row{border-bottom:1px solid #ddd;padding:8px 0;}' +
      '.row .lbl{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#666;}' +
      '.row .val{font-size:15px;font-weight:600;word-break:break-word;}' +
      '.qr{width:180px;text-align:center;}' +
      '.qr img{width:180px;height:180px;}' +
      '.qr .cap{font-size:11px;color:#666;margin-top:6px;}' +
      '@media print{body{margin:0;}.slip{border-color:#000;}}' +
      '</style></head><body>' +
      '<div class="slip"><h1>Überweisung</h1>' +
      '<p class="sub">Fotoüberweisung / GiroCode — mit der Banking-App abfotografieren oder QR scannen.</p>' +
      '<div class="grid"><div class="fields">' +
      '<div class="row"><div class="lbl">Empfänger</div><div class="val">' + esc(recipient) + '</div></div>' +
      '<div class="row"><div class="lbl">IBAN</div><div class="val">' + esc(iban) + '</div></div>' +
      '<div class="row"><div class="lbl">BIC</div><div class="val">' + esc(bic || '—') + '</div></div>' +
      '<div class="row"><div class="lbl">Betrag</div><div class="val">' + esc(amount) + '</div></div>' +
      '<div class="row"><div class="lbl">Verwendungszweck</div><div class="val">' + esc(purpose) + '</div></div>' +
      '</div>' +
      (qrUrl ? '<div class="qr"><img src="' + qrUrl + '" alt="GiroCode"><div class="cap">GiroCode (EPC-QR)</div></div>' : '') +
      '</div></div></body></html>';

    var win = window.open('', '_blank', 'width=760,height=800');
    if (!win) {
      return;
    }
    win.document.open();
    win.document.write(html);
    win.document.close();
    win.focus();
    setTimeout(function () {
      win.print();
    }, 250);
  }

  function init() {
    renderAll();

    // QR erst rendern, wenn ein Accordion geöffnet wird (falls anfangs zugeklappt).
    document.querySelectorAll('details.dg-transfer').forEach(function (d) {
      d.addEventListener('toggle', function () {
        if (d.open) {
          var node = d.querySelector('.dg-transfer-qr');
          if (node) {
            renderOne(node);
          }
        }
      });
    });

    document.addEventListener('click', function (ev) {
      var btn = ev.target.closest ? ev.target.closest('[data-transfer-print]') : null;
      if (btn) {
        ev.preventDefault();
        printSlip(btn.getAttribute('data-transfer-print'));
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
