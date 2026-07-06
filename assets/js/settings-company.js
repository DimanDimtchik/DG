/**
 * Firmendaten: Website-Vorschlag, Finanzamt-Lookup, UV-Träger, Repeater.
 */
(function () {
  'use strict';

  const TLD_PATTERN =
    /\.(de|com|net|org|eu|info|at|ch|io|shop|online|berlin|bayern|app|dev|biz|co\.uk|uk)(?:\/|$)/i;

  const cfg = window.dgCompanySettings || {};

  function hasProtocol(value) {
    return /^https?:\/\//i.test(value);
  }

  function looksLikeDomain(value) {
    const v = value.trim();
    if (!v || hasProtocol(v) || /\s/.test(v)) {
      return false;
    }
    if (!/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+/i.test(v)) {
      return false;
    }
    return TLD_PATTERN.test(v);
  }

  function buildSuggestion(value) {
    const v = value.trim();
    if (!looksLikeDomain(v)) {
      return null;
    }
    return 'https://' + v.replace(/\/+$/, '');
  }

  function initWebsiteField(input) {
    const field = input.closest('.dg-field');
    if (!field || field.querySelector('.dg-url-suggest')) {
      return;
    }

    const hint = document.createElement('div');
    hint.className = 'dg-url-suggest';
    hint.hidden = true;
    field.appendChild(hint);

    function render() {
      const suggestion = buildSuggestion(input.value);
      hint.innerHTML = '';
      if (!suggestion || suggestion === input.value.trim()) {
        hint.hidden = true;
        return;
      }

      const label = document.createElement('span');
      label.className = 'dg-url-suggest__label';
      label.textContent = 'Vorschlag:';

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'dg-url-chip';
      btn.textContent = suggestion;
      btn.addEventListener('click', () => {
        input.value = suggestion;
        hint.hidden = true;
        input.focus();
      });

      hint.appendChild(label);
      hint.appendChild(btn);
      hint.hidden = false;
    }

    input.addEventListener('input', render);
    input.addEventListener('blur', () => {
      const suggestion = buildSuggestion(input.value);
      if (suggestion && !hasProtocol(input.value)) {
        input.value = suggestion;
      }
      window.setTimeout(() => {
        hint.hidden = true;
      }, 150);
    });
    render();
  }

  function finanzamtRequest(payload) {
    const body = new FormData();
    body.append('_csrf', cfg.csrf || '');
    Object.keys(payload).forEach((key) => body.append(key, payload[key]));

    return fetch(cfg.finanzamtUrl || '/api/finanzamt-lookup', {
      method: 'POST',
      body,
      credentials: 'same-origin',
    }).then((r) => r.json());
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
  }

  function renderFinanzamtPanel(data) {
    const panel = document.getElementById('dg-finanzamt-content');
    if (!panel || !data) {
      return;
    }

    const office = data.office || {};
    let html = '';

    if (data.found && office.name) {
      html += '<p><strong>' + escapeHtml(office.name) + '</strong>';
      if (data.bufo_nr) {
        html += ' (BuFa ' + escapeHtml(data.bufo_nr) + ')';
      }
      html += '</p>';
      const addr = [office.street, office.postal_code, office.city].filter(Boolean).join(', ');
      if (addr) {
        html += '<p>' + escapeHtml(addr) + '</p>';
      }
      if (office.phone) {
        html += '<p>Telefon: ' + escapeHtml(office.phone) + '</p>';
      }
      if (office.opening_hours) {
        html += '<p>Öffnungszeiten: ' + escapeHtml(office.opening_hours) + '</p>';
      }
    } else if (data.error) {
      html += '<p class="dg-field-hint">' + escapeHtml(data.error) + '</p>';
    }

    if (data.elster_number) {
      html += '<p>ELSTER-Steuernummer: <code>' + escapeHtml(data.elster_number) + '</code></p>';
    }
    if (data.deadlines && data.deadlines.summary) {
      html += '<p>' + escapeHtml(data.deadlines.summary) + '</p>';
    }

    if (Array.isArray(data.alternatives) && data.alternatives.length) {
      html += '<p class="dg-field-hint">Weitere Treffer:</p><ul>';
      data.alternatives.forEach((alt) => {
        html +=
          '<li><button type="button" class="dg-link-button" data-bufo="' +
          escapeHtml(alt.bufo_nr || '') +
          '">' +
          escapeHtml(alt.name || '') +
          '</button></li>';
      });
      html += '</ul>';
    }

    panel.innerHTML = html || '<p class="dg-field-hint">Kein Finanzamt gefunden.</p>';

    panel.querySelectorAll('[data-bufo]').forEach((btn) => {
      btn.addEventListener('click', () => {
        addFinanzamtFromOffice(data.alternatives.find((o) => o.bufo_nr === btn.dataset.bufo) || office);
      });
    });

    if (data.found && office.name) {
      addFinanzamtFromOffice(
        Object.assign({}, office, { bufo_nr: data.bufo_nr || office.bufo_nr || '' })
      );
    }
  }

  function addFinanzamtFromOffice(office, bufoNr) {
    const repeater = document.getElementById('dg-finanzaemter-repeater');
    if (!repeater || !office || !office.name) {
      return;
    }

    const bufo = bufoNr || office.bufo_nr || '';
    const existing = Array.from(repeater.querySelectorAll('input[name*="[bufo_nr]"]')).some(
      (input) => input.value.trim() === String(bufo).trim() && bufo !== ''
    );
    if (existing) {
      return;
    }

    const emptyRow = Array.from(repeater.querySelectorAll('[data-repeater-row]')).find((row) => {
      const nameInput = row.querySelector('input[name*="[name]"]');
      return nameInput && !nameInput.value.trim();
    });

    const row = emptyRow || addRepeaterRow('finanzaemter');
    if (!row) {
      return;
    }

    setRowValue(row, 'bufo_nr', bufo);
    setRowValue(row, 'name', office.name || '');
    setRowValue(row, 'street', office.street || '');
    setRowValue(row, 'postal_code', office.postal_code || '');
    setRowValue(row, 'city', office.city || '');
    setRowValue(row, 'phone', office.phone || '');
    setRowValue(row, 'email', office.email || '');
    setRowValue(row, 'opening_hours', office.opening_hours || '');

    const taxSection = document.querySelector('[data-company-section="tax"]');
    if (taxSection) {
      updateCompanySummary(taxSection);
    }
  }

  function setRowValue(row, field, value) {
    const input = row.querySelector('[name*="[' + field + ']"]');
    if (input) {
      input.value = value;
    }
  }

  function initFinanzamtLookup() {
    const taxInput = document.getElementById('dg-tax-number-est');
    const lookupBtn = document.getElementById('dg-lookup-finanzamt');
    const locationBtn = document.getElementById('dg-lookup-finanzamt-location');
    const searchBtn = document.getElementById('dg-lookup-finanzamt-search');

    function runLookup(payload, btn) {
      if (btn) {
        btn.disabled = true;
      }
      finanzamtRequest(payload)
        .then((response) => {
          if (response.success) {
            renderFinanzamtPanel(response.data);
          }
        })
        .finally(() => {
          if (btn) {
            btn.disabled = false;
          }
        });
    }

    if (lookupBtn) {
      lookupBtn.addEventListener('click', () => {
        runLookup({ mode: 'tax_number', tax_number: taxInput ? taxInput.value : '' }, lookupBtn);
      });
    }

    if (locationBtn) {
      locationBtn.addEventListener('click', () => {
        const plz = document.getElementById('dg-founder-plz');
        const city = document.getElementById('dg-founder-city');
        const postalInput = document.querySelector('[data-company-postal]');
        const cityInput = document.querySelector('[data-company-city]');
        runLookup(
          {
            mode: 'location',
            postal: (plz && plz.value) || (postalInput && postalInput.value) || '',
            city: (city && city.value) || (cityInput && cityInput.value) || '',
          },
          locationBtn
        );
      });
    }

    if (searchBtn) {
      searchBtn.addEventListener('click', () => {
        const search = document.getElementById('dg-founder-fa-search');
        runLookup({ mode: 'search', query: search ? search.value : '' }, searchBtn);
      });
    }
  }

  function fillUvCarrier(key) {
    const carrier = (cfg.uvCarriers || {})[key];
    if (!carrier) {
      return;
    }
    const map = {
      'dg-uv-recipient': carrier.name,
      'dg-uv-street': carrier.street,
      'dg-uv-postal': carrier.zip,
      'dg-uv-city': carrier.city,
    };
    Object.keys(map).forEach((id) => {
      const el = document.getElementById(id);
      if (el && !el.value.trim()) {
        el.value = map[id];
      }
    });
  }

  function initUvCarrier() {
    const select = document.getElementById('dg-uv-carrier');
    const suggestBtn = document.getElementById('dg-suggest-uv-carrier');
    const industry = document.getElementById('dg-company-industry');
    const uvSection = document.querySelector('[data-company-section="uv"]');

    function refreshUvSummary() {
      if (uvSection) {
        updateCompanySummary(uvSection);
      }
    }

    if (select) {
      select.addEventListener('change', () => {
        fillUvCarrier(select.value);
        refreshUvSummary();
      });
    }

    if (suggestBtn && industry) {
      suggestBtn.addEventListener('click', () => {
        const key = (cfg.industryUvMap || {})[industry.value];
        if (!key || !select) {
          return;
        }
        select.value = key;
        fillUvCarrier(key);
        refreshUvSummary();
      });
    }
  }

  function reindexRepeater(repeater) {
    const type = repeater.dataset.repeater;
    repeater.querySelectorAll('[data-repeater-row]').forEach((row, index) => {
      row.querySelectorAll('[name], [data-name]').forEach((el) => {
        const attr = el.hasAttribute('name') ? 'name' : 'data-name';
        const current = el.getAttribute(attr) || '';
        if (!current.includes('__INDEX__') && !current.includes('[')) {
          return;
        }
        const updated = current.replace(/\[\d+\]/, '[' + index + ']').replace(/__INDEX__/g, String(index));
        if (el.hasAttribute('data-name')) {
          el.setAttribute('name', updated);
          el.removeAttribute('data-name');
        } else {
          el.setAttribute(attr, updated);
        }
      });
    });
  }

  function addRepeaterRow(type) {
    const tpl = document.getElementById('dg-tpl-' + type);
    const repeater = document.querySelector('[data-repeater="' + type + '"]');
    if (!tpl || !repeater) {
      return null;
    }

    const index = repeater.querySelectorAll('[data-repeater-row]').length;
    const html = tpl.innerHTML.replace(/__INDEX__/g, String(index));
    const wrap = document.createElement('div');
    wrap.innerHTML = html.trim();
    const row = wrap.firstElementChild;
    repeater.appendChild(row);

    if (type === 'owners') {
      const sourceSelect = repeater.querySelector('select[name*="[user_id]"]');
      const targetSelect = row.querySelector('select[data-name*="[user_id]"], select[name*="[user_id]"]');
      if (sourceSelect && targetSelect) {
        targetSelect.innerHTML = sourceSelect.innerHTML;
        targetSelect.value = '0';
      }
    }

    bindRepeaterRow(row);
    reindexRepeater(repeater);
    if (type === 'owners') {
      updateOwnersShareTotal();
    }
    return row;
  }

  function bindRepeaterRow(row) {
    const removeBtn = row.querySelector('.dg-repeater-remove');
    if (removeBtn) {
      removeBtn.addEventListener('click', () => {
        const repeater = row.closest('[data-repeater]');
        const section = row.closest('[data-company-section]');
        row.remove();
        if (repeater) {
          reindexRepeater(repeater);
          if (repeater.dataset.repeater === 'owners') {
            updateOwnersShareTotal();
          }
        }
        if (section) {
          updateCompanySummary(section);
        }
      });
    }
    row.querySelectorAll('[data-owner-share]').forEach((input) => {
      input.addEventListener('input', () => {
        updateOwnersShareTotal();
        const section = row.closest('[data-company-section]');
        if (section) {
          updateCompanySummary(section);
        }
      });
    });
    row.querySelectorAll('[data-owner-name]').forEach((input) => {
      input.addEventListener('input', () => {
        const section = row.closest('[data-company-section]');
        if (section) {
          updateCompanySummary(section);
        }
      });
    });
  }

  function updateOwnersShareTotal() {
    const totalEl = document.getElementById('dg-owners-share-total');
    if (!totalEl) {
      return;
    }
    let total = 0;
    document.querySelectorAll('[data-owner-share]').forEach((input) => {
      const value = parseFloat(String(input.value).replace(',', '.'));
      if (!Number.isNaN(value)) {
        total += value;
      }
    });
    totalEl.textContent = 'Summe Anteile: ' + total.toLocaleString('de-DE', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + ' %';
    totalEl.classList.toggle('dg-owners-share-warning', total > 0 && Math.abs(total - 100) > 0.01);
  }

  function initRepeaters() {
    document.querySelectorAll('[data-repeater]').forEach((repeater) => {
      repeater.querySelectorAll('[data-repeater-row]').forEach(bindRepeaterRow);
    });

    document.querySelectorAll('[data-repeater-add]').forEach((btn) => {
      btn.addEventListener('click', () => {
        addRepeaterRow(btn.dataset.repeaterAdd);
        const section = btn.closest('[data-company-section]');
        if (section) {
          updateCompanySummary(section);
        }
      });
    });
  }

  function setCompanyOpen(card, open) {
    const panel = card.querySelector('[data-dept-panel]');
    const toggle = card.querySelector('[data-dept-toggle]');
    if (!panel || !toggle) {
      return;
    }
    panel.hidden = !open;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    card.classList.toggle('is-open', open);
  }

  function countFilledNames(card, selector) {
    let count = 0;
    card.querySelectorAll(selector).forEach((input) => {
      if (input.value.trim() !== '') {
        count += 1;
      }
    });
    return count;
  }

  function updateCompanySummary(card) {
    const summary = card.querySelector('[data-dept-summary]');
    if (!summary) {
      return;
    }

    const section = card.dataset.companySection || '';
    let text = '';

    switch (section) {
      case 'stammdaten': {
        const parts = [];
        const name = card.querySelector('input[name="name"]');
        const city = card.querySelector('input[name="city"]');
        const email = card.querySelector('input[name="email"]');
        if (name && name.value.trim()) {
          parts.push(name.value.trim());
        }
        if (city && city.value.trim()) {
          parts.push(city.value.trim());
        }
        if (email && email.value.trim()) {
          parts.push(email.value.trim());
        }
        text = parts.length > 0 ? parts.join(' · ') : 'Noch nicht ausgefüllt';
        break;
      }
      case 'owners': {
        const filled = countFilledNames(card, '[data-owner-name]');
        const parts = [];
        if (filled > 0) {
          parts.push(filled === 1 ? '1 Inhaber' : filled + ' Inhaber');
        }
        let total = 0;
        card.querySelectorAll('[data-owner-share]').forEach((input) => {
          const value = parseFloat(String(input.value).replace(',', '.'));
          if (!Number.isNaN(value)) {
            total += value;
          }
        });
        if (total > 0) {
          parts.push(
            'Summe ' +
              total.toLocaleString('de-DE', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) +
              ' %'
          );
        }
        text = parts.length > 0 ? parts.join(' · ') : 'Keine Inhaber';
        break;
      }
      case 'addresses': {
        let filled = 0;
        card.querySelectorAll('[data-repeater-row]').forEach((row) => {
          const street = row.querySelector('input[name*="[street]"]');
          const city = row.querySelector('input[name*="[city]"]');
          if (
            (street && street.value.trim()) ||
            (city && city.value.trim())
          ) {
            filled += 1;
          }
        });
        text =
          filled > 0
            ? filled === 1
              ? '1 Standort'
              : filled + ' Standorte'
            : 'Keine Standorte';
        break;
      }
      case 'tax': {
        const parts = [];
        const est = card.querySelector('#dg-tax-number-est, [data-tax-est]');
        if (est && est.value.trim()) {
          parts.push('ESt: ' + est.value.trim());
        }
        const faCount = countFilledNames(card, '#dg-finanzaemter-repeater input[name*="[name]"]');
        if (faCount > 0) {
          parts.push(faCount === 1 ? '1 Finanzamt' : faCount + ' Finanzämter');
        }
        text = parts.length > 0 ? parts.join(' · ') : 'Noch keine Steuerdaten';
        break;
      }
      case 'uv': {
        const select = card.querySelector('#dg-uv-carrier');
        if (select && select.value) {
          const option = select.options[select.selectedIndex];
          text = option ? option.textContent.trim() : 'Nicht zugeordnet';
        } else {
          text = 'Nicht zugeordnet';
        }
        break;
      }
      case 'employment': {
        const parts = [];
        const name = card.querySelector('input[name="employment_agency[name]"]');
        const bn = card.querySelector('input[name="employment_agency[betriebsnummer]"]');
        if (name && name.value.trim()) {
          parts.push(name.value.trim());
        }
        if (bn && bn.value.trim()) {
          parts.push('BN ' + bn.value.trim());
        }
        text = parts.length > 0 ? parts.join(' · ') : 'Noch nicht ausgefüllt';
        break;
      }
      case 'institutions': {
        const filled = countFilledNames(card, 'input[name*="institutions"][name*="[name]"]');
        text =
          filled > 0
            ? filled === 1
              ? '1 Eintrag'
              : filled + ' Einträge'
            : 'Keine Kammern hinterlegt';
        break;
      }
      case 'professional_chambers': {
        const filled = countFilledNames(card, 'input[name*="professional_chambers"][name*="[name]"]');
        text =
          filled > 0
            ? filled === 1
              ? '1 Kammer'
              : filled + ' Kammern'
            : 'Keine Kammern';
        break;
      }
      case 'trade_associations': {
        const filled = countFilledNames(card, 'input[name*="trade_associations"][name*="[name]"]');
        text =
          filled > 0
            ? filled === 1
              ? '1 Verband'
              : filled + ' Verbände'
            : 'Keine Verbände';
        break;
      }
      case 'memberships': {
        const filled = countFilledNames(card, 'input[name*="memberships"][name*="[name]"]');
        text =
          filled > 0
            ? filled === 1
              ? '1 Mitgliedschaft'
              : filled + ' Mitgliedschaften'
            : 'Keine Mitgliedschaften';
        break;
      }
      case 'bank': {
        let filled = 0;
        card.querySelectorAll('[data-bank-card]').forEach((bankCard) => {
          const iban = bankCard.querySelector('.dg-bank-iban, input[name*="[iban]"]');
          const bankName = bankCard.querySelector('.dg-bank-name, input[name*="[bank_name]"]');
          if (
            (iban && iban.value.trim()) ||
            (bankName && bankName.value.trim())
          ) {
            filled += 1;
          }
        });
        text =
          filled > 0
            ? filled === 1
              ? '1 Konto'
              : filled + ' Konten'
            : 'Keine Bankverbindung';
        break;
      }
      default:
        return;
    }

    summary.textContent = text;
  }

  function initCompanyAccordion() {
    const accordion = document.getElementById('dg-company-accordion');
    const expandAllBtn = document.getElementById('dg-company-expand-all');
    const collapseAllBtn = document.getElementById('dg-company-collapse-all');
    if (!accordion) {
      return;
    }

    function setAllOpen(open) {
      accordion.querySelectorAll('[data-dept-card]').forEach((card) => {
        setCompanyOpen(card, open);
      });
    }

    if (expandAllBtn) {
      expandAllBtn.addEventListener('click', () => setAllOpen(true));
    }
    if (collapseAllBtn) {
      collapseAllBtn.addEventListener('click', () => setAllOpen(false));
    }

    accordion.addEventListener('click', (event) => {
      const toggle = event.target.closest('[data-dept-toggle]');
      if (!toggle || !accordion.contains(toggle)) {
        return;
      }
      const card = toggle.closest('[data-dept-card]');
      if (card) {
        const panel = card.querySelector('[data-dept-panel]');
        setCompanyOpen(card, panel ? panel.hidden : true);
      }
      event.preventDefault();
    });

    accordion.addEventListener('input', (event) => {
      const card = event.target.closest('[data-dept-card]');
      if (card) {
        updateCompanySummary(card);
      }
    });

    accordion.addEventListener('change', (event) => {
      const card = event.target.closest('[data-dept-card]');
      if (card) {
        updateCompanySummary(card);
      }
    });

    accordion.querySelectorAll('[data-dept-card]').forEach((card) => {
      updateCompanySummary(card);
    });

    const bankRepeater = document.getElementById('dg-company-bank-repeater');
    if (bankRepeater) {
      const bankCard = accordion.querySelector('[data-company-section="bank"]');
      const observer = new MutationObserver(() => {
        if (bankCard) {
          updateCompanySummary(bankCard);
        }
      });
      observer.observe(bankRepeater, { childList: true, subtree: true });
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    const websiteInput = document.querySelector('.dg-company-form input[name="website"]');
    if (websiteInput) {
      initWebsiteField(websiteInput);
    }
    initFinanzamtLookup();
    initUvCarrier();
    initRepeaters();
    initCompanyAccordion();
    updateOwnersShareTotal();
  });
})();
