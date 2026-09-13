(function () {
  const cfg = window.dgLagerScanConfig || {};
  const scanApiUrl = cfg.scanApiUrl || '/api/stock-scan';
  const csrf = cfg.csrf || '';

  function fmtQty(value) {
    const n = Number(value);
    if (!Number.isFinite(n)) {
      return '0';
    }
    return String(n).replace('.', ',');
  }

  function showScanMessage(el, text, kind) {
    if (!el) {
      return;
    }
    el.textContent = text || '';
    el.className = 'dg-scan-result' + (kind ? ' dg-scan-result--' + kind : '');
    el.hidden = text === '';
  }

  function appendLine(tbody, lineData, mode) {
    const tr = document.createElement('tr');
    const index = tbody.children.length;
    const articleId = lineData.article_id || '';
    const title = lineData.title || '';
    const number = lineData.article_number || '';
    const qty = lineData.quantity != null ? lineData.quantity : 1;
    const placeId = lineData.place_id || '';
    const packageBarcode = lineData.package_barcode || '';
    const createPackage = !!lineData.create_package;

    tr.innerHTML =
      '<td><input type="hidden" name="lines[' + index + '][article_id]" value="' + articleId + '">' +
      (number ? number + ' — ' : '') + title + '</td>' +
      '<td><input type="text" class="dg-input--compact" name="lines[' + index + '][quantity]" value="' + fmtQty(qty) + '" inputmode="decimal" required></td>' +
      (mode === 'receipt'
        ? '<td><input type="hidden" name="lines[' + index + '][place_id]" value="' + placeId + '">' +
          (placeId ? 'Platz #' + placeId : '—') +
          '</td>' +
          '<td><label><input type="checkbox" name="lines[' + index + '][create_package]" value="1"' + (createPackage ? ' checked' : '') + '> Karton</label></td>' +
          '<td><input type="text" class="dg-input--compact" name="lines[' + index + '][package_barcode]" value="' + packageBarcode + '" placeholder="optional"></td>'
        : '<td><input type="text" class="dg-input--compact" name="lines[' + index + '][package_barcode]" value="' + packageBarcode + '" placeholder="Karton-Scan"></td>') +
      '<td><button type="button" class="dg-button dg-button--small dg-line-remove">×</button></td>';

    tbody.appendChild(tr);
    tr.querySelector('.dg-line-remove')?.addEventListener('click', function () {
      tr.remove();
      reindexLines(tbody);
    });
    reindexLines(tbody);
  }

  function reindexLines(tbody) {
    Array.from(tbody.querySelectorAll('tr')).forEach(function (tr, index) {
      tr.querySelectorAll('[name^="lines["]').forEach(function (input) {
        const field = input.name.replace(/^lines\[\d+\]/, '');
        input.name = 'lines[' + index + ']' + field;
      });
    });
  }

  function applyScanResult(data, tbody, mode, messageEl) {
    if (!data || !tbody) {
      return;
    }

    const type = data.type || '';
    const article = data.article || null;
    const place = data.place || null;
    const pkg = data.package || null;

    if (type === 'package' && pkg) {
      appendLine(tbody, {
        article_id: article ? article.id : '',
        article_number: article ? article.article_number : '',
        title: article ? article.title : data.label,
        quantity: pkg.quantity,
        unit: article ? article.unit : '',
        place_id: pkg.place_id || (place ? place.id : ''),
        package_barcode: pkg.barcode,
      }, mode);
      showScanMessage(messageEl, data.label + ' — Karton zur Liste hinzugefügt.', 'success');
      return;
    }

    if (type === 'place' && place) {
      showScanMessage(messageEl, 'Platz gescannt: ' + (place.position_code || place.code) + '. Nächsten Artikel scannen.', 'info');
      window.dgLagerPendingPlaceId = place.id;
      return;
    }

    if (type === 'article' && article) {
      appendLine(tbody, {
        article_id: article.id,
        article_number: article.article_number,
        title: article.title,
        quantity: 1,
        unit: article.unit,
        place_id: window.dgLagerPendingPlaceId || '',
        create_package: mode === 'receipt',
      }, mode);
      window.dgLagerPendingPlaceId = null;
      showScanMessage(messageEl, 'Artikel hinzugefügt: ' + article.title, 'success');
      return;
    }

    showScanMessage(messageEl, 'Scan nicht zuordenbar.', 'warning');
  }

  function bindScanForm(form, tbody, mode) {
    const input = form.querySelector('[data-scan-input]');
    const messageEl = form.querySelector('[data-scan-message]');
    if (!input || !tbody) {
      return;
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      const code = (input.value || '').trim();
      if (code === '') {
        showScanMessage(messageEl, 'Bitte Strichcode eingeben oder scannen.', 'warning');
        return;
      }

      showScanMessage(messageEl, 'Suche …', 'info');
      const url = scanApiUrl + '?action=scan&code=' + encodeURIComponent(code);
      fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (res) {
          return res.json().then(function (body) {
            return { ok: res.ok, body: body };
          });
        })
        .then(function (result) {
          if (!result.ok || !result.body.success) {
            showScanMessage(messageEl, (result.body && result.body.message) || 'Strichcode nicht gefunden.', 'error');
            return;
          }
          applyScanResult(result.body.data, tbody, mode, messageEl);
          input.value = '';
          input.focus();
        })
        .catch(function () {
          showScanMessage(messageEl, 'Netzwerkfehler beim Scan.', 'error');
        });
    });
  }

  function bindVoucherSelect(select, tbody, mode) {
    if (!select || !tbody) {
      return;
    }
    select.addEventListener('change', function () {
      const voucherId = select.value;
      if (!voucherId) {
        return;
      }
      const url = scanApiUrl + '?action=voucher-lines&voucher_id=' + encodeURIComponent(voucherId);
      fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (res) {
          return res.json();
        })
        .then(function (body) {
          if (!body.success || !body.data || !Array.isArray(body.data.lines)) {
            return;
          }
          tbody.innerHTML = '';
          body.data.lines.forEach(function (line) {
            appendLine(tbody, line, mode);
          });
        })
        .catch(function () {
          // still allow manual entry
        });
    });
  }

  document.querySelectorAll('[data-lager-scan="receipt"]').forEach(function (form) {
    const tbody = document.querySelector('#dg-receipt-lines tbody');
    bindScanForm(form, tbody, 'receipt');
  });

  document.querySelectorAll('[data-lager-scan="issue"]').forEach(function (form) {
    const tbody = document.querySelector('#dg-issue-lines tbody');
    bindScanForm(form, tbody, 'issue');
  });

  const voucherSelect = document.getElementById('dg-issue-voucher');
  const issueTbody = document.querySelector('#dg-issue-lines tbody');
  if (voucherSelect && issueTbody) {
    bindVoucherSelect(voucherSelect, issueTbody, 'issue');
  }
})();
