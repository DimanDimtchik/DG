document.addEventListener('DOMContentLoaded', () => {
  const csrfInput = document.getElementById('dg-calendar-staff-csrf');
  if (!csrfInput) {
    return;
  }

  const apiUrl = '/api/calendar-staff';
  const csrf = csrfInput.value;
  const timeOptions = (window.dgCalendarStaff && window.dgCalendarStaff.timeOptions) || [];
  const linkContacts = (window.dgCalendarStaff && window.dgCalendarStaff.linkContacts) || [];
  const departmentSuggestions = (window.dgCalendarStaff && window.dgCalendarStaff.departmentSuggestions) || [];

  function postStaff(action, data) {
    const body = new FormData();
    body.append('action', action);
    body.append('_csrf', csrf);
    Object.entries(data).forEach(([key, value]) => {
      if (Array.isArray(value)) {
        value.forEach((item) => body.append(key + '[]', String(item)));
      } else if (value !== undefined && value !== null) {
        body.append(key, String(value));
      }
    });

    return fetch(apiUrl, { method: 'POST', body, credentials: 'same-origin' })
      .then((response) => response.json())
      .then((json) => {
        if (!json.success) {
          throw new Error(json.message || 'Anfrage fehlgeschlagen.');
        }
        if (json.data && json.data.reload) {
          window.location.reload();
        }
        return json.data || {};
      });
  }

  function timeSelectHtml(className, selected) {
    let html = '<select class="' + className + '"><option value="">—</option>';
    timeOptions.forEach((option) => {
      html += '<option value="' + option + '"' + (option === selected ? ' selected' : '') + '>' + option + '</option>';
    });
    html += '</select>';
    return html;
  }

  function ensureContactOption(contactId, label) {
    const select = document.getElementById('dg_employee_contact');
    if (!select || !contactId) {
      return;
    }
    if (!Array.from(select.options).some((option) => Number(option.value) === Number(contactId))) {
      const option = document.createElement('option');
      option.value = String(contactId);
      option.textContent = label || ('Kontakt #' + contactId);
      select.appendChild(option);
    }
    select.value = String(contactId);
  }

  function ensureUserOption(userId, label) {
    const select = document.getElementById('dg_employee_user');
    if (!select || !userId) {
      return;
    }
    if (!Array.from(select.options).some((option) => Number(option.value) === Number(userId))) {
      const option = document.createElement('option');
      option.value = String(userId);
      option.textContent = label || ('Benutzer #' + userId);
      select.appendChild(option);
    }
    select.value = String(userId);
  }

  function selectedAreaDepartmentIds(form) {
    const departmentIds = new Set();
    form.querySelectorAll('input[name="area_ids[]"]:checked').forEach((input) => {
      const departmentId = (input.getAttribute('data-department-id') || '').trim();
      if (departmentId) {
        departmentIds.add(departmentId);
      }
    });
    return departmentIds;
  }

  function refreshContactSelectForAreas(form) {
    const select = document.getElementById('dg_employee_contact');
    if (!select || linkContacts.length === 0) {
      return;
    }

    const currentValue = select.value;
    const currentOption = select.options[select.selectedIndex];
    const currentLabel = currentOption && Number(currentOption.value) > 0
      ? (currentOption.getAttribute('data-label') || currentOption.textContent)
      : '';
    const departmentIds = selectedAreaDepartmentIds(form);
    const suggestedContactIds = new Set();

    if (departmentIds.size > 0) {
      departmentSuggestions.forEach((suggestion) => {
        if (suggestion.can_add && departmentIds.has(suggestion.department_id) && suggestion.contact_id) {
          suggestedContactIds.add(Number(suggestion.contact_id));
        }
      });
    }

    select.innerHTML = '<option value="0">— Kein Kontakt —</option>';

    if (suggestedContactIds.size > 0) {
      const optgroup = document.createElement('optgroup');
      optgroup.label = 'Aus Abteilungsmitgliedern';
      linkContacts.forEach((contact) => {
        if (!suggestedContactIds.has(Number(contact.id))) {
          return;
        }
        const option = document.createElement('option');
        option.value = String(contact.id);
        option.textContent = contact.label;
        option.setAttribute('data-label', contact.label);
        optgroup.appendChild(option);
      });
      if (optgroup.children.length > 0) {
        select.appendChild(optgroup);
      }
    }

    const otherGroup = document.createElement('optgroup');
    otherGroup.label = suggestedContactIds.size > 0 ? 'Weitere Kontakte' : 'Alle Kontakte';
    linkContacts.forEach((contact) => {
      if (suggestedContactIds.has(Number(contact.id))) {
        return;
      }
      const option = document.createElement('option');
      option.value = String(contact.id);
      option.textContent = contact.label;
      option.setAttribute('data-label', contact.label);
      otherGroup.appendChild(option);
    });
    select.appendChild(otherGroup);

    if (currentValue && Array.from(select.options).some((option) => option.value === currentValue)) {
      select.value = currentValue;
    } else if (Number(currentValue) > 0) {
      ensureContactOption(Number(currentValue), currentLabel);
    }
  }

  function applyEmployeeSuggestion(suggestion) {
    const form = document.getElementById('dg-calendar-employee-form');
    if (!form || !suggestion) {
      return;
    }

    document.getElementById('dg_employee_id').value = '';
    ensureContactOption(suggestion.contact_id || 0, suggestion.contact_label || '');
    document.getElementById('dg_employee_name').value = suggestion.name || suggestion.contact_label || '';
    ensureUserOption(suggestion.user_id || 0, suggestion.user_label || '');
    document.getElementById('dg_employee_supervisor').value = '0';
    document.getElementById('dg_employee_sort').value = '0';
    document.getElementById('dg_employee_active').checked = true;

    form.querySelectorAll('input[name="area_ids[]"]').forEach((input) => {
      const areaId = parseInt(input.value, 10);
      input.checked = (suggestion.area_ids || []).includes(areaId);
    });

    refreshContactSelectForAreas(form);
    ensureContactOption(suggestion.contact_id || 0, suggestion.contact_label || '');
    ensureUserOption(suggestion.user_id || 0, suggestion.user_label || '');

    document.getElementById('dg-employee-form-title').textContent = 'Neuen Mitarbeiter anlegen';
    document.getElementById('dg-employee-submit').textContent = 'Mitarbeiter speichern';
    document.getElementById('dg-employee-cancel').hidden = true;

    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  document.querySelectorAll('.dg-cal-apply-suggestion').forEach((button) => {
    button.addEventListener('click', () => {
      try {
        applyEmployeeSuggestion(JSON.parse(button.getAttribute('data-suggestion') || '{}'));
      } catch (error) {
        alert('Vorschlag konnte nicht geladen werden.');
      }
    });
  });

  const areaForm = document.getElementById('dg-calendar-area-form');
  if (areaForm) {
    areaForm.addEventListener('submit', (event) => {
      event.preventDefault();
      postStaff('save_area', {
        area_id: document.getElementById('dg_area_id').value,
        name: document.getElementById('dg_area_name').value,
        department_id: document.getElementById('dg_area_department').value,
        sort_order: document.getElementById('dg_area_sort').value,
        is_active: document.getElementById('dg_area_active').checked ? 1 : '',
      }).catch((error) => alert(error.message));
    });

    document.getElementById('dg-area-cancel')?.addEventListener('click', () => {
      areaForm.reset();
      document.getElementById('dg_area_id').value = '';
      document.getElementById('dg_area_active').checked = true;
      if (document.getElementById('dg_area_department')) {
        document.getElementById('dg_area_department').value = '';
      }
      document.getElementById('dg-area-form-title').textContent = 'Neuen Bereich anlegen';
      document.getElementById('dg-area-submit').textContent = 'Bereich speichern';
      document.getElementById('dg-area-cancel').hidden = true;
    });

    document.querySelectorAll('.dg-cal-edit-area').forEach((button) => {
      button.addEventListener('click', () => {
        const data = JSON.parse(button.getAttribute('data-area') || '{}');
        document.getElementById('dg_area_id').value = data.id || '';
        document.getElementById('dg_area_name').value = data.name || '';
        document.getElementById('dg_area_department').value = data.department_id || '';
        document.getElementById('dg_area_sort').value = data.sort_order || 0;
        document.getElementById('dg_area_active').checked = !!parseInt(data.is_active, 10);
        document.getElementById('dg-area-form-title').textContent = 'Bereich bearbeiten';
        document.getElementById('dg-area-submit').textContent = 'Änderungen speichern';
        document.getElementById('dg-area-cancel').hidden = false;
        areaForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });

    document.querySelectorAll('.dg-cal-delete-area').forEach((button) => {
      button.addEventListener('click', () => {
        if (!confirm('Bereich wirklich löschen?')) {
          return;
        }
        postStaff('delete_area', { id: button.getAttribute('data-id') }).catch((error) => alert(error.message));
      });
    });
  }

  const employeeForm = document.getElementById('dg-calendar-employee-form');
  if (employeeForm) {
    employeeForm.querySelectorAll('input[name="area_ids[]"]').forEach((input) => {
      input.addEventListener('change', () => refreshContactSelectForAreas(employeeForm));
    });

    employeeForm.addEventListener('submit', (event) => {
      event.preventDefault();
      const areaIds = [];
      employeeForm.querySelectorAll('input[name="area_ids[]"]:checked').forEach((input) => {
        areaIds.push(input.value);
      });
      if (!areaIds.length) {
        alert('Bitte wählen Sie mindestens einen Bereich aus.');
        return;
      }
      postStaff('save_employee', {
        employee_id: document.getElementById('dg_employee_id').value,
        contact_id: document.getElementById('dg_employee_contact').value,
        name: document.getElementById('dg_employee_name').value,
        sort_order: document.getElementById('dg_employee_sort').value,
        is_active: document.getElementById('dg_employee_active').checked ? 1 : '',
        user_id: document.getElementById('dg_employee_user').value,
        supervisor_id: document.getElementById('dg_employee_supervisor').value,
        area_ids: areaIds,
      }).catch((error) => alert(error.message));
    });

    document.getElementById('dg-employee-cancel')?.addEventListener('click', () => {
      employeeForm.reset();
      document.getElementById('dg_employee_id').value = '';
      document.getElementById('dg_employee_contact').value = '0';
      document.getElementById('dg_employee_active').checked = true;
      document.getElementById('dg_employee_user').value = '0';
      document.getElementById('dg_employee_supervisor').value = '0';
      employeeForm.querySelectorAll('input[name="area_ids[]"]').forEach((input) => {
        input.checked = false;
      });
      refreshContactSelectForAreas(employeeForm);
      document.getElementById('dg-employee-form-title').textContent = 'Neuen Mitarbeiter anlegen';
      document.getElementById('dg-employee-submit').textContent = 'Mitarbeiter speichern';
      document.getElementById('dg-employee-cancel').hidden = true;
    });

    document.querySelectorAll('.dg-cal-edit-employee').forEach((button) => {
      button.addEventListener('click', () => {
        const data = JSON.parse(button.getAttribute('data-employee') || '{}');
        document.getElementById('dg_employee_id').value = data.id || '';
        ensureContactOption(data.contact_id || 0, data.contact_label || '');
        if (!data.contact_id) {
          document.getElementById('dg_employee_contact').value = '0';
        }
        document.getElementById('dg_employee_name').value = data.name || '';
        document.getElementById('dg_employee_sort').value = data.sort_order || 0;
        document.getElementById('dg_employee_active').checked = !!parseInt(data.is_active, 10);
        document.getElementById('dg_employee_user').value = data.user_id ? String(data.user_id) : '0';
        document.getElementById('dg_employee_supervisor').value = data.supervisor_id ? String(data.supervisor_id) : '0';
        employeeForm.querySelectorAll('input[name="area_ids[]"]').forEach((input) => {
          input.checked = (data.area_ids || []).includes(parseInt(input.value, 10));
        });
        document.getElementById('dg-employee-form-title').textContent = 'Mitarbeiter bearbeiten';
        document.getElementById('dg-employee-submit').textContent = 'Änderungen speichern';
        document.getElementById('dg-employee-cancel').hidden = false;
        employeeForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });

    document.getElementById('dg_employee_contact')?.addEventListener('change', (event) => {
      const select = event.target;
      const option = select.options[select.selectedIndex];
      const nameInput = document.getElementById('dg_employee_name');
      if (!nameInput || !option || Number(option.value) < 1) {
        return;
      }
      const label = option.getAttribute('data-label') || option.textContent;
      if (!nameInput.value.trim()) {
        nameInput.value = label;
      }
    });

    document.querySelectorAll('.dg-cal-delete-employee').forEach((button) => {
      button.addEventListener('click', () => {
        if (!confirm('Mitarbeiter wirklich löschen?')) {
          return;
        }
        postStaff('delete_employee', { id: button.getAttribute('data-id') }).catch((error) => alert(error.message));
      });
    });

    document.querySelectorAll('.dg-cal-toggle-hours').forEach((button) => {
      button.addEventListener('click', () => {
        const employeeId = button.getAttribute('data-employee-id');
        const row = document.querySelector('.dg-cal-hours-row[data-employee-id="' + employeeId + '"]');
        if (!row) {
          return;
        }
        const host = row.querySelector('.dg-cal-hours-host');
        const isOpen = !row.hidden;
        document.querySelectorAll('.dg-cal-hours-row').forEach((other) => {
          other.hidden = true;
        });
        if (isOpen) {
          return;
        }
        row.hidden = false;
        if (host && host.innerHTML.trim() === '') {
          postStaff('load_employee_hours_editor', { employee_id: employeeId })
            .then((data) => {
              host.innerHTML = data.html || '';
              bindHoursEditor(host);
            })
            .catch((error) => alert(error.message));
        } else if (host) {
          bindHoursEditor(host);
        }
      });
    });
  }

  function bindHoursEditor(root) {
    root.querySelectorAll('.dg-cal-add-window').forEach((button) => {
      button.onclick = () => {
        const day = button.closest('.dg-cal-schedule-day');
        const windows = day.querySelector('.dg-cal-schedule-day__windows');
        const row = document.createElement('div');
        row.className = 'dg-cal-schedule-window';
        row.innerHTML = timeSelectHtml('schedule-start', '') +
          '<span class="dg-cal-schedule-sep">–</span>' +
          timeSelectHtml('schedule-end', '') +
          '<button type="button" class="dg-button dg-button--small dg-cal-remove-window" title="Fenster entfernen">&times;</button>';
        windows.appendChild(row);
        bindRemoveWindow(row);
      };
    });

    root.querySelectorAll('.dg-cal-remove-window').forEach(bindRemoveWindow);

    root.querySelectorAll('.dg-cal-save-hours').forEach((button) => {
      button.onclick = () => {
        const employeeId = button.getAttribute('data-employee-id');
        const panel = button.closest('.dg-cal-schedule-panel');
        const windows = [];
        panel.querySelectorAll('.dg-cal-schedule-day').forEach((day) => {
          const weekday = parseInt(day.getAttribute('data-weekday'), 10);
          day.querySelectorAll('.dg-cal-schedule-window').forEach((row) => {
            const start = row.querySelector('.schedule-start')?.value || '';
            const end = row.querySelector('.schedule-end')?.value || '';
            if (start && end) {
              windows.push({ weekday, start_time: start, end_time: end });
            }
          });
        });
        postStaff('save_employee_hours', {
          employee_id: employeeId,
          windows_json: JSON.stringify(windows),
        }).catch((error) => alert(error.message));
      };
    });
  }

  function bindRemoveWindow(button) {
    button.onclick = () => {
      const row = button.closest('.dg-cal-schedule-window');
      const windows = row.parentElement;
      if (windows.querySelectorAll('.dg-cal-schedule-window').length <= 1) {
        row.querySelectorAll('select').forEach((select) => {
          select.value = '';
        });
      } else {
        row.remove();
      }
    };
  }

  const absenceForm = document.getElementById('dg-calendar-absence-form');
  if (absenceForm) {
    absenceForm.addEventListener('submit', (event) => {
      event.preventDefault();
      const data = Object.fromEntries(new FormData(absenceForm).entries());
      postStaff('save_employee_absence', data).catch((error) => alert(error.message));
    });

    document.querySelectorAll('.dg-cal-delete-absence').forEach((button) => {
      button.addEventListener('click', () => {
        if (!confirm('Abwesenheit wirklich löschen?')) {
          return;
        }
        postStaff('delete_employee_absence', { id: button.getAttribute('data-id') }).catch((error) => alert(error.message));
      });
    });
  }
});
