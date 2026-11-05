/**
 * Weiche Vorbelegung Personen-/Beitragsgruppenschlüssel aus Beschäftigungsart (Lohn).
 * Beim Tippen/Speichern: nur leere Felder. Beim Wechsel der Beschäftigungsart: auch bekannte Defaults.
 */
(function () {
  'use strict';

  function parseJson(raw, fallback) {
    try {
      return JSON.parse(raw || '');
    } catch (e) {
      return fallback;
    }
  }

  function canReplace(value, known, replaceKnown) {
    const v = (value || '').trim();
    if (v === '') {
      return true;
    }
    return replaceKnown && known.indexOf(v) !== -1;
  }

  function applySuggestions(root, replaceKnown) {
    const select = root.querySelector('[data-payroll-employment-type]');
    const person = root.querySelector('[data-person-group-key]');
    const contrib = root.querySelector('[data-contribution-group-key]');
    const hint = root.querySelector('[data-sv-group-hint]');
    if (!select || !person || !contrib) {
      return;
    }

    const map = parseJson(root.getAttribute('data-sv-group-suggestions'), {});
    const knownPerson = parseJson(root.getAttribute('data-sv-known-person'), ['101', '109']);
    const knownContrib = parseJson(root.getAttribute('data-sv-known-contrib'), ['1111', '6500', '6100']);
    const svExempt = root.querySelector('input[name="employee[sv_exempt]"]');
    const pet = (select.value || '').trim();
    const suggest = map[pet];

    if (!suggest || (pet === 'managing_director' && svExempt && svExempt.checked)) {
      if (hint) {
        hint.textContent = 'Wird aus der Beschäftigungsart (Lohn) vorgeschlagen, wenn leer oder Standardwert.';
      }
      return;
    }

    if (canReplace(person.value, knownPerson, replaceKnown)) {
      person.value = suggest.person_group_key || '';
      person.dispatchEvent(new Event('change', { bubbles: true }));
    }
    if (canReplace(contrib.value, knownContrib, replaceKnown)) {
      contrib.value = suggest.contribution_group_key || '';
      contrib.dispatchEvent(new Event('change', { bubbles: true }));
    }
    if (hint && suggest.hint) {
      hint.textContent = suggest.hint;
    }
  }

  function bind(root) {
    const select = root.querySelector('[data-payroll-employment-type]');
    if (!select) {
      return;
    }
    select.addEventListener('change', function () {
      applySuggestions(root, true);
    });
    const svExempt = root.querySelector('input[name="employee[sv_exempt]"]');
    if (svExempt) {
      svExempt.addEventListener('change', function () {
        applySuggestions(root, false);
      });
    }
    applySuggestions(root, false);
  }

  document.querySelectorAll('[data-employee-section]').forEach(bind);
})();
