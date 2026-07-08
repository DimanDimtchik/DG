/**
 * Bank autocomplete (IBAN / BIC / Bankname) – wie WG-App / dg-user-plugin
 */
(function () {
  'use strict';

  const cfg = window.dgBankConfig || {};

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
  }

  function postBank(field, value) {
    const body = new FormData();
    body.append('_csrf', cfg.csrf || '');
    body.append('field', field);
    body.append('value', value);

    return fetch(cfg.url || '/api/bank-suggest', {
      method: 'POST',
      body,
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    }).then((response) => response.json());
  }

  function findInput(card, cls) {
    return card.querySelector('.' + cls);
  }

  function findSuggest(card, cls) {
    return card.querySelector('.' + cls);
  }

  function renderChips(container, matches, onPick) {
    container.innerHTML = '';
    if (!matches || !matches.length) {
      container.hidden = true;
      return;
    }
    const wrap = document.createElement('div');
    wrap.className = 'dg-bank-chips';
    matches.forEach((bank) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'dg-bank-chip';
      btn.textContent = bank.bankName + (bank.bic ? ' (' + bank.bic + ')' : '');
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        onPick(bank);
        container.innerHTML = '';
        container.hidden = true;
      });
      wrap.appendChild(btn);
    });
    container.appendChild(wrap);
    container.hidden = false;
  }

  function handleIban(card) {
    const input = findInput(card, 'dg-bank-iban');
    const hint = findSuggest(card, 'dg-bank-iban-suggest');
    if (!input || !hint) {
      return;
    }
    const iban = input.value;
    if (iban.replace(/\s/g, '').length < 12) {
      hint.innerHTML = '';
      hint.hidden = true;
      return;
    }

    postBank('iban', iban).then((response) => {
      if (!response.success) {
        return;
      }
      const data = response.data || {};
      if (data.suggestion) {
        const bankNameInput = findInput(card, 'dg-bank-name');
        const bicInput = findInput(card, 'dg-bank-bic');

        // Leere Felder automatisch aus der IBAN füllen (BIC + Bankname).
        let autoFilled = false;
        if (bicInput && bicInput.value.trim() === '' && data.suggestion.bic) {
          bicInput.value = data.suggestion.bic;
          autoFilled = true;
        }
        if (bankNameInput && bankNameInput.value.trim() === '' && data.suggestion.bankName) {
          bankNameInput.value = data.suggestion.bankName;
          autoFilled = true;
        }

        const label = data.suggestion.bankName + (data.suggestion.bic ? ' (' + data.suggestion.bic + ')' : '');
        hint.innerHTML =
          '<p class="dg-bank-hint">' +
          escapeHtml((autoFilled ? 'Aus IBAN übernommen: ' : 'Aus IBAN erkannt: ') + label) +
          ' <button type="button" class="dg-bank-apply">Erneut übernehmen</button></p>';
        hint.hidden = false;
        hint.querySelector('.dg-bank-apply')?.addEventListener('click', (e) => {
          e.preventDefault();
          if (bankNameInput) {
            bankNameInput.value = data.suggestion.bankName;
          }
          if (bicInput) {
            bicInput.value = data.suggestion.bic;
          }
        });
      } else {
        hint.innerHTML = '<p class="dg-bank-hint dg-bank-hint-warn">Keine Bank zur IBAN gefunden.</p>';
        hint.hidden = false;
      }
      if (data.valid === false && iban.replace(/\s/g, '').length >= 15) {
        input.classList.add('dg-bank-invalid');
      } else {
        input.classList.remove('dg-bank-invalid');
      }
    });
  }

  function handleBankName(card) {
    const input = findInput(card, 'dg-bank-name');
    const container = findSuggest(card, 'dg-bank-name-suggest');
    if (!input || !container) {
      return;
    }
    const val = input.value;
    if (val.trim().length < 2) {
      container.innerHTML = '';
      container.hidden = true;
      return;
    }
    postBank('bank_name', val).then((response) => {
      if (!response.success) {
        return;
      }
      const matches = (response.data && response.data.matches) || [];
      renderChips(container, matches, (bank) => {
        input.value = bank.bankName;
        const bic = findInput(card, 'dg-bank-bic');
        if (bic) {
          bic.value = bank.bic;
        }
      });
      if (!matches.length) {
        container.innerHTML = '<p class="dg-bank-hint dg-bank-hint-warn">Keine passende Bank gefunden.</p>';
        container.hidden = false;
      }
    });
  }

  function handleBic(card) {
    const input = findInput(card, 'dg-bank-bic');
    const container = findSuggest(card, 'dg-bank-bic-suggest');
    if (!input || !container) {
      return;
    }
    const val = input.value;
    if (val.trim().length < 3) {
      container.innerHTML = '';
      container.hidden = true;
      return;
    }
    postBank('bic', val).then((response) => {
      if (!response.success) {
        return;
      }
      const matches = (response.data && response.data.matches) || [];
      renderChips(container, matches, (bank) => {
        const bankName = findInput(card, 'dg-bank-name');
        if (bankName) {
          bankName.value = bank.bankName;
        }
        input.value = bank.bic;
      });
      if (!matches.length) {
        container.innerHTML = '<p class="dg-bank-hint dg-bank-hint-warn">Kein passender BIC gefunden.</p>';
        container.hidden = false;
      }
    });
  }

  function bindBlock(card) {
    if (card.dataset.bankAutocompleteBound === '1') {
      return;
    }
    card.dataset.bankAutocompleteBound = '1';

    let ibanTimer;
    const ibanInput = findInput(card, 'dg-bank-iban');
    if (ibanInput) {
      ibanInput.addEventListener('input', () => {
        clearTimeout(ibanTimer);
        ibanTimer = setTimeout(() => handleIban(card), 300);
      });
      ibanInput.addEventListener('blur', () => handleIban(card));
    }

    const nameInput = findInput(card, 'dg-bank-name');
    if (nameInput) {
      nameInput.addEventListener('input', () => handleBankName(card));
      nameInput.addEventListener('focus', () => handleBankName(card));
    }

    const bicInput = findInput(card, 'dg-bank-bic');
    if (bicInput) {
      bicInput.addEventListener('input', () => handleBic(card));
      bicInput.addEventListener('focus', () => handleBic(card));
    }
  }

  function init(root) {
    const scope = root || document.getElementById('dg-bank-repeater');
    if (!scope) {
      return;
    }
    scope.querySelectorAll('[data-bank-card]').forEach(bindBlock);
  }

  window.dgBankAutocomplete = { init, bindBlock };
})();
