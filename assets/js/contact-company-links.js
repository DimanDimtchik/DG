document.addEventListener('DOMContentLoaded', () => {
  const salutationSelect = document.querySelector('[data-salutation-select]');
  const companySection = document.querySelector('[data-company-section]');
  const personSection = document.querySelector('[data-person-employer-section]');
  const repeater = document.getElementById('dg-company-employee-repeater');
  const template = document.getElementById('dg-company-employee-card-template');

  if (!salutationSelect) {
    return;
  }

  function syncContactLinkSections() {
    const isCompany = salutationSelect.value === 'Firma';
    if (companySection) {
      companySection.hidden = !isCompany;
      companySection.querySelectorAll('select, input, button').forEach((field) => {
        if (field.matches('[data-company-employee-add], [data-company-employee-remove]')) {
          field.disabled = !isCompany;
          return;
        }
        if (!isCompany) {
          field.setAttribute('disabled', 'disabled');
        } else {
          field.removeAttribute('disabled');
        }
      });
    }
    if (personSection) {
      personSection.hidden = isCompany;
      personSection.querySelectorAll('select, input').forEach((field) => {
        if (isCompany) {
          field.setAttribute('disabled', 'disabled');
        } else {
          field.removeAttribute('disabled');
        }
      });
    }
  }

  function reindexCompanyEmployeeCards() {
    if (!repeater) {
      return;
    }
    repeater.querySelectorAll('[data-company-employee-card]').forEach((card, index) => {
      const title = card.querySelector('[data-company-employee-title]');
      if (title) {
        title.textContent = 'Mitarbeiter ' + (index + 1);
      }
      card.querySelectorAll('[name]').forEach((field) => {
        const name = field.getAttribute('name') || '';
        field.setAttribute('name', name.replace(/company_employees\[\d+]/, 'company_employees[' + index + ']'));
      });
    });
  }

  salutationSelect.addEventListener('change', syncContactLinkSections);
  syncContactLinkSections();

  if (repeater) {
    reindexCompanyEmployeeCards();
  }

  document.addEventListener('click', (event) => {
    const addButton = event.target.closest('[data-company-employee-add]');
    if (addButton && template && repeater) {
      const html = template.innerHTML.replaceAll('__INDEX__', String(repeater.querySelectorAll('[data-company-employee-card]').length));
      const wrapper = document.createElement('div');
      wrapper.innerHTML = html.trim();
      const card = wrapper.firstElementChild;
      if (card) {
        repeater.appendChild(card);
        reindexCompanyEmployeeCards();
        syncContactLinkSections();
      }
      return;
    }

    const removeButton = event.target.closest('[data-company-employee-remove]');
    if (removeButton && repeater) {
      const card = removeButton.closest('[data-company-employee-card]');
      const cards = repeater.querySelectorAll('[data-company-employee-card]');
      if (card && cards.length > 1) {
        card.remove();
        reindexCompanyEmployeeCards();
      }
    }
  });
});
