/**
 * Krankenkassen-Vorschläge (GKV + PKV) beim Tippen
 */
(function () {
  'use strict';

  const cfg = window.dgHealthInsurerConfig || {};

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
  }

  function postSuggest(value) {
    const body = new FormData();
    body.append('_csrf', cfg.csrf || '');
    body.append('value', value);

    return fetch(cfg.url || '/api/health-insurer-suggest', {
      method: 'POST',
      body,
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    }).then((response) => response.json());
  }

  function typeLabel(type) {
    return type === 'pkv' ? 'PKV' : 'GKV';
  }

  function renderMatches(container, matches, onPick) {
    container.innerHTML = '';
    if (!matches || !matches.length) {
      container.hidden = true;
      return;
    }

    const wrap = document.createElement('div');
    wrap.className = 'dg-bank-chips';
    matches.forEach((insurer) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'dg-bank-chip';
      const code = insurer.code ? ' · IK ' + insurer.code : '';
      btn.textContent = insurer.name + ' (' + typeLabel(insurer.type) + ')' + code;
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        onPick(insurer);
        container.innerHTML = '';
        container.hidden = true;
      });
      wrap.appendChild(btn);
    });
    container.appendChild(wrap);
    container.hidden = false;
  }

  function handleInput(input, container) {
    const val = input.value;
    if (val.trim().length < 2) {
      container.innerHTML = '';
      container.hidden = true;
      return;
    }

    postSuggest(val).then((response) => {
      if (!response.success) {
        return;
      }
      const matches = (response.data && response.data.matches) || [];
      renderMatches(container, matches, (insurer) => {
        input.value = insurer.name;
        input.dispatchEvent(new Event('change', { bubbles: true }));
      });
      if (!matches.length) {
        container.innerHTML =
          '<p class="dg-bank-hint dg-bank-hint-warn">Keine passende Krankenkasse gefunden.</p>';
        container.hidden = false;
      }
    });
  }

  function bindInput(input) {
    if (input.dataset.healthInsurerBound === '1') {
      return;
    }
    input.dataset.healthInsurerBound = '1';

    const container = input.parentElement?.querySelector('.dg-health-insurer-suggest');
    if (!container) {
      return;
    }

    input.addEventListener('input', () => handleInput(input, container));
    input.addEventListener('focus', () => handleInput(input, container));
  }

  function init(root) {
    const scope = root || document;
    scope.querySelectorAll('[data-health-insurer-name]').forEach(bindInput);
  }

  window.dgHealthInsurerAutocomplete = { init, bindInput };
})();
