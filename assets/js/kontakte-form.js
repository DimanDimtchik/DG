/**
 * Kontaktformular: Auto-Vorlage für Login/Anzeigename, Postfach-Checkbox je Rolle.
 */
(function () {
  'use strict';

  var employeeRoles = ['dg_eigenmitarbeiter', 'administrator'];

  function suggestLogin(name) {
    return String(name || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 40);
  }

  function buildDisplayName(firstName, lastName, companyName, salutation) {
    if (salutation === 'Firma' || salutation === 'Team') {
      return String(companyName || '').trim();
    }
    return [String(firstName || '').trim(), String(lastName || '').trim()].filter(Boolean).join(' ');
  }

  function initAutoIdentityFields(form) {
    var loginField = form.querySelector('[name="login"]');
    var displayField = form.querySelector('[name="display_name"]');
    var firstNameField = form.querySelector('[name="first_name"]');
    var lastNameField = form.querySelector('[name="last_name"]');
    var companyField = form.querySelector('[name="company_name"]');
    var salutationField = form.querySelector('[name="salutation"]');
    if (!loginField || !displayField) {
      return;
    }

    var loginManual = loginField.value.trim() !== '';
    var displayManual = displayField.value.trim() !== '';

    loginField.addEventListener('focus', function () {
      loginManual = true;
    });
    displayField.addEventListener('focus', function () {
      displayManual = true;
    });

    function syncIdentityFields() {
      var salutation = salutationField ? salutationField.value : '';
      var displayName = buildDisplayName(
        firstNameField ? firstNameField.value : '',
        lastNameField ? lastNameField.value : '',
        companyField ? companyField.value : '',
        salutation
      );
      if (!displayManual && displayName) {
        displayField.value = displayName;
      }
      if (!loginManual) {
        var loginSource = salutation === 'Firma' || salutation === 'Team'
          ? (companyField ? companyField.value : '')
          : displayName;
        var login = suggestLogin(loginSource);
        if (login) {
          loginField.value = login;
        }
      }
    }

    [firstNameField, lastNameField, companyField, salutationField].forEach(function (field) {
      if (!field) {
        return;
      }
      field.addEventListener('input', syncIdentityFields);
      field.addEventListener('change', syncIdentityFields);
    });

    syncIdentityFields();
  }

  function initMailboxCheckbox(form) {
    var roleSelect = form.querySelector('[data-role-select]');
    var mailboxCheckbox = form.querySelector('#contact_auto_create_mailbox');
    if (!roleSelect || !mailboxCheckbox) {
      return;
    }

    var autoEnabled = mailboxCheckbox.getAttribute('data-auto-enabled') === '1';

    function syncMailboxCheckbox() {
      var isEmployee = employeeRoles.indexOf(roleSelect.value) !== -1;
      mailboxCheckbox.checked = autoEnabled && isEmployee;
      mailboxCheckbox.disabled = !isEmployee;
      var row = mailboxCheckbox.closest('[data-auto-mailbox-row]');
      if (row) {
        row.style.opacity = isEmployee ? '' : '0.72';
      }
    }

    roleSelect.addEventListener('change', syncMailboxCheckbox);
    syncMailboxCheckbox();
  }

  document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('form.dg-form[action*="page=kontakte"]');
    if (!form || form.querySelector('input[name="id"]')) {
      return;
    }
    initAutoIdentityFields(form);
    initMailboxCheckbox(form);
  });
})();
