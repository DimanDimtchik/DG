/**
 * Dokumentenverfasser D4 — Klartext lokal + optionales LanguageTool (via CRM-API).
 */
(function () {
  'use strict';

  var cfg = window.dgWriterEditor || {};
  var resultEl = document.getElementById('dg-writer-assist-result');
  var btnKlar = document.getElementById('dg-writer-assist-klartext');
  var btnLt = document.getElementById('dg-writer-assist-lt');
  if (!resultEl || (!btnKlar && !btnLt)) {
    return;
  }

  function plainText() {
    if (window.dgWriterTipTap && typeof window.dgWriterTipTap.getText === 'function') {
      return String(window.dgWriterTipTap.getText() || '').trim();
    }
    var mount = document.getElementById(cfg.mountId || 'dg-writer-editor');
    return mount ? String(mount.innerText || '').trim() : '';
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function showLoading(label) {
    resultEl.hidden = false;
    resultEl.innerHTML = '<p class="dg-field-hint">' + escapeHtml(label) + ' …</p>';
  }

  function showError(msg) {
    resultEl.hidden = false;
    resultEl.innerHTML = '<div class="dg-flash dg-flash--error">' + escapeHtml(msg) + '</div>';
  }

  function postAssist(action) {
    var text = plainText();
    if (!text) {
      showError('Kein Text im Editor.');
      return;
    }
    showLoading(action === 'klartext' ? 'Klartext wird geprüft' : 'LanguageTool läuft');
    var body = new URLSearchParams();
    body.set('action', action);
    body.set('text', text);
    body.set('_csrf', cfg.csrf || '');
    fetch(cfg.assistUrl || '/api/writer-assist', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
    })
      .then(function (r) {
        return r.json().then(function (data) {
          return { status: r.status, data: data };
        });
      })
      .then(function (pack) {
        if (!pack.data || !pack.data.ok) {
          showError((pack.data && pack.data.error) || 'Assist fehlgeschlagen.');
          return;
        }
        if (action === 'klartext') {
          renderKlartext(pack.data.result || {});
        } else {
          renderLanguageTool(pack.data.result || {});
        }
      })
      .catch(function () {
        showError('Netzwerkfehler beim Assist.');
      });
  }

  function renderKlartext(res) {
    var tips = res.tips || [];
    var metrics = res.metrics || {};
    var html =
      '<div class="dg-writer-assist__card">' +
      '<p><strong>Klartext-Score: ' +
      escapeHtml(String(res.score != null ? res.score : '—')) +
      '/100</strong> — ' +
      escapeHtml(res.summary || '') +
      '</p>' +
      '<p class="dg-field-hint">Wörter: ' +
      escapeHtml(String(metrics.words || 0)) +
      ' · Sätze: ' +
      escapeHtml(String(metrics.sentences || 0)) +
      ' · Ø Wörter/Satz: ' +
      escapeHtml(String(metrics.avg_sentence_words || 0)) +
      ' · Lesbarkeit: ' +
      escapeHtml(String(metrics.reading_ease || 0)) +
      '</p>';
    if (tips.length) {
      html += '<ul class="dg-writer-assist__tips">';
      tips.forEach(function (t) {
        html +=
          '<li class="dg-writer-assist__tip dg-writer-assist__tip--' +
          escapeHtml(t.level || 'info') +
          '"><strong>' +
          escapeHtml(t.title || '') +
          '</strong> ' +
          escapeHtml(t.detail || '') +
          '</li>';
      });
      html += '</ul>';
    }
    html += '</div>';
    resultEl.hidden = false;
    resultEl.innerHTML = html;
  }

  function renderLanguageTool(res) {
    if (!res.ok) {
      showError(res.message || 'LanguageTool fehlgeschlagen.');
      return;
    }
    var matches = res.matches || [];
    var html =
      '<div class="dg-writer-assist__card"><p><strong>' +
      escapeHtml(res.message || '') +
      '</strong></p>';
    if (matches.length) {
      html += '<ul class="dg-writer-assist__tips">';
      matches.forEach(function (m) {
        var repl = (m.replacements || []).slice(0, 3).join(', ');
        html +=
          '<li class="dg-writer-assist__tip dg-writer-assist__tip--warn"><strong>' +
          escapeHtml(m.short || m.message || 'Hinweis') +
          '</strong> ' +
          escapeHtml(m.message || '') +
          (repl ? ' <em>Vorschläge: ' + escapeHtml(repl) + '</em>' : '') +
          '</li>';
      });
      html += '</ul>';
    }
    html += '</div>';
    resultEl.hidden = false;
    resultEl.innerHTML = html;
  }

  if (btnKlar) {
    btnKlar.addEventListener('click', function () {
      postAssist('klartext');
    });
  }
  if (btnLt) {
    btnLt.addEventListener('click', function () {
      postAssist('languagetool');
    });
  }
})();
