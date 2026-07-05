document.addEventListener('click', (event) => {
  const toggle = event.target.closest('[data-menu-toggle]');
  const menus = document.querySelectorAll('[data-menu]');

  if (toggle) {
    const menu = toggle.closest('[data-menu]');
    const panel = menu.querySelector('[data-menu-panel]');
    const isOpen = toggle.getAttribute('aria-expanded') === 'true';

    menus.forEach((other) => {
      if (other !== menu) {
        closeMenu(other);
      }
    });

    if (isOpen) {
      closeMenu(menu);
    } else {
      openMenu(menu);
    }

    event.preventDefault();
    return;
  }

  if (!event.target.closest('[data-menu]')) {
    menus.forEach(closeMenu);
  }
});

document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') {
    document.querySelectorAll('[data-menu]').forEach(closeMenu);
  }
});

function openMenu(menu) {
  const toggle = menu.querySelector('[data-menu-toggle]');
  const panel = menu.querySelector('[data-menu-panel]');
  toggle.setAttribute('aria-expanded', 'true');
  panel.hidden = false;
}

function closeMenu(menu) {
  const toggle = menu.querySelector('[data-menu-toggle]');
  const panel = menu.querySelector('[data-menu-panel]');
  if (!toggle || !panel) {
    return;
  }
  toggle.setAttribute('aria-expanded', 'false');
  panel.hidden = true;
}

const bankFieldMap = {
  giro: ['account_holder', 'bank_name', 'iban', 'bic'],
  sparkonto: ['account_holder', 'bank_name', 'iban', 'bic'],
  kreditkarte: ['provider', 'card_number_masked', 'expiry'],
  paypal: ['email', 'merchant_id'],
  klarna: ['merchant_id'],
  stripe: ['account_id'],
  mollie: ['profile_id'],
  amazon_pay: ['merchant_id'],
  apple_pay: ['provider'],
  google_pay: ['provider'],
  sepa_lastschrift: ['creditor_id', 'iban', 'account_holder'],
  sonstiges: ['bank_name', 'merchant_id', 'account_id'],
};

function syncBankCardFields(card) {
  const type = card.querySelector('[data-bank-type]')?.value || 'giro';
  const visible = new Set(bankFieldMap[type] || bankFieldMap.giro);
  card.querySelectorAll('[data-bank-field]').forEach((field) => {
    const key = field.getAttribute('data-bank-field');
    field.hidden = !visible.has(key);
  });
}

function reindexBankCards(repeater) {
  repeater.querySelectorAll('[data-bank-card]').forEach((card, index) => {
    const title = card.querySelector('[data-bank-title]');
    if (title) {
      title.textContent = 'Konto / Zahlungsdienst #' + (index + 1);
    }
    card.querySelectorAll('[name]').forEach((input) => {
      input.name = input.name.replace(/bank_accounts\[\d+\]/, 'bank_accounts[' + index + ']');
    });
    syncBankCardFields(card);
  });
}

function clearBankCard(card) {
  card.removeAttribute('data-bank-autocomplete-bound');
  card.querySelectorAll('input').forEach((input) => {
    input.value = '';
    input.classList.remove('dg-bank-invalid');
  });
  card.querySelectorAll('select').forEach((select) => {
    select.selectedIndex = 0;
  });
  card.querySelectorAll('.dg-bank-suggest').forEach((el) => {
    el.innerHTML = '';
    el.hidden = true;
  });
  syncBankCardFields(card);
}

function initBankAutocomplete(root) {
  if (window.dgBankAutocomplete) {
    window.dgBankAutocomplete.init(root || document.getElementById('dg-bank-repeater'));
  }
}

document.addEventListener('click', (event) => {
  const addButton = event.target.closest('[data-bank-add]');
  if (addButton) {
    const repeaterId = addButton.getAttribute('data-bank-repeater') || 'dg-bank-repeater';
    const repeater = document.getElementById(repeaterId);
    if (!repeater) {
      return;
    }
    const first = repeater.querySelector('[data-bank-card]');
    if (!first) {
      return;
    }
    const clone = first.cloneNode(true);
    clearBankCard(clone);
    repeater.appendChild(clone);
    reindexBankCards(repeater);
    initBankAutocomplete(repeater);
    event.preventDefault();
    return;
  }

  const removeButton = event.target.closest('[data-bank-remove]');
  if (removeButton) {
    const card = removeButton.closest('[data-bank-card]');
    const repeater = card ? card.closest('.dg-bank-repeater, #dg-bank-repeater, #dg-company-bank-repeater') : null;
    if (!repeater || !card) {
      return;
    }
    const cards = repeater.querySelectorAll('[data-bank-card]');
    if (cards.length <= 1) {
      clearBankCard(card);
    } else {
      card.remove();
      reindexBankCards(repeater);
    }
    event.preventDefault();
  }
});

document.addEventListener('change', (event) => {
  if (event.target.matches('[data-bank-type]')) {
    const card = event.target.closest('[data-bank-card]');
    if (card) {
      syncBankCardFields(card);
    }
  }
});

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('#dg-bank-repeater, #dg-company-bank-repeater').forEach((repeater) => {
    reindexBankCards(repeater);
    initBankAutocomplete(repeater);
  });
  if (window.dgHealthInsurerAutocomplete) {
    window.dgHealthInsurerAutocomplete.init();
  }
  if (window.dgDisabilitySupplementary) {
    window.dgDisabilitySupplementary.init();
  }
  syncEmployeeSection();
  syncSocialInsuranceFields();
});

const employeeRoles = ['dg_eigenmitarbeiter', 'administrator'];

function syncEmployeeSection() {
  const select = document.querySelector('[data-role-select]');
  const section = document.querySelector('[data-employee-section]');
  if (!select || !section) {
    return;
  }
  const show = employeeRoles.includes(select.value);
  section.hidden = !show;
  section.querySelectorAll('[data-employee-required]').forEach((field) => {
    if (show) {
      field.setAttribute('required', 'required');
    } else {
      field.removeAttribute('required');
    }
  });
}

document.addEventListener('change', (event) => {
  if (event.target.matches('[data-role-select]')) {
    syncEmployeeSection();
  }
  if (event.target.matches('[data-social-status]')) {
    syncSocialInsuranceFields();
  }
  if (event.target.matches('[data-social-filing-office]')) {
    syncSocialInsuranceFields();
  }
});

document.addEventListener('input', (event) => {
  if (event.target.matches('[data-social-sv-number]')) {
    syncSocialInsuranceFields();
  }
});

function syncSocialInsuranceFields() {
  const section = document.querySelector('[data-employee-section]');
  if (!section) {
    return;
  }
  const status = section.querySelector('[data-social-status]');
  const svInput = section.querySelector('[data-social-sv-number]');
  const filing = section.querySelector('[data-social-filing-office]');
  const hint = section.querySelector('[data-filing-hint]');
  const guide = section.querySelector('[data-social-guide]');
  const guideSteps = section.querySelector('[data-social-guide-steps]');
  const received = status && status.value === 'received';
  const svEmpty = !svInput || svInput.value.trim() === '';

  if (svInput) {
    if (received) {
      svInput.setAttribute('required', 'required');
      svInput.setAttribute('data-employee-required', 'required');
    } else {
      svInput.removeAttribute('required');
      svInput.removeAttribute('data-employee-required');
    }
  }

  if (hint && filing) {
    let hints = {};
    try {
      hints = JSON.parse(section.getAttribute('data-filing-hints') || '{}');
    } catch (e) {
      hints = {};
    }
    const text = hints[filing.value] || '';
    if (text) {
      hint.textContent = text;
      hint.hidden = false;
    } else {
      hint.textContent = '';
      hint.hidden = true;
    }
  }

  if (guide && guideSteps && status && filing) {
    let stepsByOffice = {};
    try {
      stepsByOffice = JSON.parse(section.getAttribute('data-social-steps') || '{}');
    } catch (e) {
      stepsByOffice = {};
    }

    if (!received && svEmpty && filing.value) {
      const officeSteps = stepsByOffice[filing.value] || [];
      const steps = [...officeSteps];
      if (status.value === 'requested') {
        steps.unshift('Anmeldung läuft bereits — SV-Nummer abwarten und nach Erhalt unten eintragen.');
      } else {
        steps.unshift('SV-Nummer fehlt noch — Mitarbeiter beim Arbeitgeber anmelden (siehe Schritte).');
      }
      guideSteps.innerHTML = '';
      steps.forEach((step) => {
        const li = document.createElement('li');
        li.textContent = step;
        guideSteps.appendChild(li);
      });
      guide.hidden = steps.length === 0;
    } else {
      guideSteps.innerHTML = '';
      guide.hidden = true;
    }
  }
}
