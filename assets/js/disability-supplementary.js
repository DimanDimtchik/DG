/**
 * Zusatzbuchstaben (Schwerbehindertenausweis) — Vorschläge und Schnellauswahl
 */
(function () {
  'use strict';

  function parseCodes(value) {
    return value
      .split(/[\s,;]+/)
      .map((part) => part.trim())
      .filter(Boolean);
  }

  function hasCode(codes, code) {
    return codes.some((entry) => entry.toLowerCase() === code.toLowerCase());
  }

  function renderSuggestions(input, container, options) {
    const query = input.value.trim().toLowerCase();
    const selected = parseCodes(input.value);
    const matches = Object.entries(options).filter(([code, label]) => {
      if (hasCode(selected, code)) {
        return false;
      }
      if (query === '') {
        return true;
      }
      return (
        code.toLowerCase().includes(query) ||
        label.toLowerCase().includes(query) ||
        query.split(/[\s,;]+/).some((part) => part && code.toLowerCase().startsWith(part))
      );
    });

    container.innerHTML = '';
    if (!matches.length) {
      container.hidden = true;
      return;
    }

    const wrap = document.createElement('div');
    wrap.className = 'dg-bank-chips';
    matches.forEach(([code, label]) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'dg-bank-chip';
      btn.textContent = code + ' — ' + label;
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const current = parseCodes(input.value);
        current.push(code);
        input.value = current.join(', ');
        input.dispatchEvent(new Event('input', { bubbles: true }));
        renderSuggestions(input, container, options);
      });
      wrap.appendChild(btn);
    });
    container.appendChild(wrap);
    container.hidden = false;
  }

  function bindInput(input) {
    if (input.dataset.disabilitySupplementaryBound === '1') {
      return;
    }
    input.dataset.disabilitySupplementaryBound = '1';

    const container = input.parentElement?.querySelector('.dg-disability-supplementary-suggest');
    if (!container) {
      return;
    }

    let options = {};
    try {
      options = JSON.parse(container.getAttribute('data-disability-options') || '{}');
    } catch {
      options = {};
    }

    const update = () => renderSuggestions(input, container, options);
    input.addEventListener('input', update);
    input.addEventListener('focus', update);
    update();
  }

  function init(root) {
    (root || document).querySelectorAll('[data-disability-supplementary]').forEach(bindInput);
  }

  window.dgDisabilitySupplementary = { init, bindInput };
})();
