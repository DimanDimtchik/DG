(function () {
  var cfg = window.dgLagerScanConfig || {};
  var scanApiUrl = cfg.scanApiUrl || '/api/stock-scan';
  var panel = document.getElementById('dg-place-audit-panel');
  var form = document.getElementById('dg-place-audit-form');
  if (!panel || !form) {
    return;
  }

  function esc(text) {
    var div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
  }

  function fmtQty(value) {
    var n = Number(value);
    if (!Number.isFinite(n)) {
      return '0';
    }
    return String(n).replace('.', ',');
  }

  function showMessage(text, kind) {
    var el = document.getElementById('dg-place-audit-message');
    if (!el) {
      return;
    }
    el.textContent = text || '';
    el.className = 'dg-scan-result' + (kind ? ' dg-scan-result--' + kind : '');
    el.hidden = text === '';
  }

  function renderList(title, items, renderItem) {
    if (!items || !items.length) {
      return '';
    }
    var html = '<section class="dg-audit-block"><h3 class="dg-subsection-title">' + esc(title) + '</h3><ul class="dg-audit-list">';
    items.forEach(function (item) {
      html += '<li>' + renderItem(item) + '</li>';
    });
    html += '</ul></section>';
    return html;
  }

  function renderAudit(data) {
    var html = '<div class="dg-audit-result">';
    html += '<header class="dg-audit-result__head">';
    html += '<h2 class="dg-audit-result__title">' + esc(data.title || '') + '</h2>';
    if (data.subtitle) {
      html += '<p class="dg-audit-result__subtitle">' + esc(data.subtitle) + '</p>';
    }
    if (data.summary) {
      html += '<p class="dg-field-hint"><strong>' + esc(data.summary) + '</strong></p>';
    }
    html += '</header>';

    if (data.reservation) {
      html += '<section class="dg-audit-block"><h3 class="dg-subsection-title">Reservierung / Modus</h3><p>';
      html += esc(data.reservation.label || '');
      if (data.reservation.article_number) {
        html += '<br>' + esc(data.reservation.article_number) + ' — ' + esc(data.reservation.title || '');
      }
      html += '</p></section>';
    }

    html += renderList('Aktuelle Belegung', data.occupancy, function (item) {
      return esc(item.article_number) + ' — ' + esc(item.title) + ': ' + fmtQty(item.quantity) + ' ' + esc(item.unit);
    });

    html += renderList('Kartons auf Platz', data.packages, function (item) {
      return esc(item.barcode) + ' · ' + esc(item.article_number) + ' — ' + esc(item.title) + ': ' + fmtQty(item.quantity) + ' ' + esc(item.unit);
    });

    html += renderList('Artikel-Stammplatz', data.article_links, function (item) {
      return esc(item.article_number) + ' — ' + esc(item.title) + ' (Bestand ' + fmtQty(item.stock_qty) + ' ' + esc(item.unit) + ')';
    });

    if (data.places_overview && data.places_overview.length) {
      html += '<section class="dg-audit-block"><h3 class="dg-subsection-title">Stellplätze</h3><div class="dg-table-wrap"><table class="dg-table dg-table--compact"><thead><tr><th>Code</th><th>Art</th><th>Modus</th><th>Status</th></tr></thead><tbody>';
      data.places_overview.forEach(function (place) {
        html += '<tr><td>' + esc(place.position_code) + '</td><td>' + esc(place.kind_label) + '</td><td>' + esc(place.mode_label) + '</td><td>' + (place.is_occupied ? 'belegt' : 'frei') + '</td></tr>';
      });
      html += '</tbody></table></div></section>';
    }

    if (data.movements && data.movements.length) {
      html += '<section class="dg-audit-block"><h3 class="dg-subsection-title">Letzte Bewegungen</h3><div class="dg-table-wrap"><table class="dg-table dg-table--compact"><thead><tr><th>Datum</th><th>Artikel</th><th>Menge</th><th>Art</th><th>Notiz</th></tr></thead><tbody>';
      data.movements.forEach(function (mov) {
        html += '<tr><td>' + esc(mov.movement_date) + '</td><td>' + esc(mov.article_number) + ' — ' + esc(mov.title) + '</td><td>' + fmtQty(mov.quantity) + ' ' + esc(mov.unit) + '</td><td>' + esc(mov.reason) + '</td><td>' + esc(mov.note) + '</td></tr>';
      });
      html += '</tbody></table></div></section>';
    }

    html += '</div>';
    panel.innerHTML = html;
    panel.hidden = false;
  }

  function runAudit(code) {
    code = (code || '').trim();
    if (code === '') {
      showMessage('Bitte Strichcode eingeben oder scannen.', 'warning');
      return;
    }
    showMessage('Prüfe Platz …', 'info');
    fetch(scanApiUrl + '?action=audit&code=' + encodeURIComponent(code), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })
      .then(function (res) {
        return res.json().then(function (body) {
          return { ok: res.ok, body: body };
        });
      })
      .then(function (result) {
        if (!result.ok || !result.body.success) {
          showMessage((result.body && result.body.message) || 'Nicht gefunden.', 'error');
          return;
        }
        showMessage('', '');
        renderAudit(result.body.data || {});
      })
      .catch(function () {
        showMessage('Netzwerkfehler beim Platz-Check.', 'error');
      });
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    var input = form.querySelector('[data-scan-input]');
    runAudit(input ? input.value : '');
    if (input) {
      input.value = '';
      input.focus();
    }
  });

  if (window.dgLagerCameraScan) {
    var camBtn = form.querySelector('[data-camera-scan-trigger]');
    if (camBtn) {
      camBtn.addEventListener('click', function () {
        window.dgLagerCameraScan.open(function (code) {
          runAudit(code);
        });
      });
    }
  }
})();
