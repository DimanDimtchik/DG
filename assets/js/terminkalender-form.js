(function () {
  const form = document.getElementById('dg-booking-form');
  if (!form) {
    return;
  }

  const config = window.dgBookingForm || {};
  const articleSelect = document.getElementById('dg-booking-article');
  const employeeSelect = document.getElementById('dg-booking-employee');
  const employeeField = document.getElementById('dg-booking-employee-field');
  const employeeHint = document.getElementById('dg-booking-employee-hint');
  const dateInput = document.getElementById('dg-booking-slot-date');
  const timeSelect = document.getElementById('dg-booking-slot-time');
  const hiddenDatetime = document.getElementById('dg-booking-slot-datetime');
  const hint = document.getElementById('dg-booking-slot-hint');
  const statusSelect = document.getElementById('dg-booking-status');

  if (!dateInput || !timeSelect || !hiddenDatetime) {
    return;
  }

  const apiUrl = config.apiUrl || '/api/booking-slots';
  const articles = Array.isArray(config.articles) ? config.articles : [];
  const employees = Array.isArray(config.employees) ? config.employees : [];
  const excludeBookingId = Number(config.excludeBookingId || 0);
  const initialTime = config.initialTime || '';
  const initialEmployeeId = Number(config.employeeId || 0);

  function selectedArticle() {
    if (!articleSelect) {
      return null;
    }
    const id = Number(articleSelect.value || 0);
    if (id < 1) {
      return null;
    }
    return articles.find(function (article) { return Number(article.id) === id; }) || null;
  }

  function currentArticleId() {
    return articleSelect ? Number(articleSelect.value || 0) : 0;
  }

  function currentEmployeeId() {
    return employeeSelect ? Number(employeeSelect.value || 0) : 0;
  }

  function isAvailabilityRequired() {
    const status = (statusSelect && statusSelect.value) ? statusSelect.value.toLowerCase() : '';
    return !['storniert', 'cancelled', 'canceled', 'cancel'].includes(status);
  }

  function syncHiddenDatetime() {
    const date = dateInput.value;
    const time = timeSelect.value;
    hiddenDatetime.value = date && time ? date + 'T' + time : '';
  }

  function setHint(message, isError) {
    if (!hint) {
      return;
    }
    hint.textContent = message;
    hint.classList.toggle('dg-field-hint--error', Boolean(isError));
  }

  function renderEmployeeOptions() {
    if (!employeeSelect) {
      return;
    }

    const article = selectedArticle();
    const previous = currentEmployeeId();
    employeeSelect.innerHTML = '';

    const defaultOption = document.createElement('option');
    defaultOption.value = '0';
    defaultOption.textContent = article && article.uses_employees
      ? '— Beliebiger Mitarbeiter im Bereich —'
      : '— Nicht zugeordnet —';
    employeeSelect.appendChild(defaultOption);

    let qualified = employees;
    if (article && article.uses_employees && article.area_id) {
      qualified = employees.filter(function (employee) {
        return (employee.area_ids || []).includes(Number(article.area_id));
      });
    }

    qualified.forEach(function (employee) {
      const option = document.createElement('option');
      option.value = String(employee.id);
      option.textContent = employee.name;
      employeeSelect.appendChild(option);
    });

    if (previous > 0 && Array.from(employeeSelect.options).some(function (opt) { return Number(opt.value) === previous; })) {
      employeeSelect.value = String(previous);
    } else if (initialEmployeeId > 0 && Array.from(employeeSelect.options).some(function (opt) { return Number(opt.value) === initialEmployeeId; })) {
      employeeSelect.value = String(initialEmployeeId);
    } else {
      employeeSelect.value = '0';
    }

    const showEmployees = Boolean(article && article.uses_employees && qualified.length > 0);
    if (employeeField) {
      employeeField.hidden = !showEmployees;
    }
    if (employeeHint) {
      employeeHint.textContent = showEmployees
        ? 'Nur Mitarbeiter aus dem Bereich der Leistung. Ohne Auswahl wird ein freier Mitarbeiter gesucht.'
        : 'Bei Leistungen ohne Bereich oder ohne Mitarbeiter im Bereich nicht relevant.';
    }
  }

  function renderOptions(slots, selectedTime) {
    timeSelect.innerHTML = '';
    if (!slots.length) {
      const option = document.createElement('option');
      option.value = '';
      option.textContent = isAvailabilityRequired()
        ? 'Keine freien Termine an diesem Tag'
        : '—';
      timeSelect.appendChild(option);
      timeSelect.disabled = !isAvailabilityRequired();
      syncHiddenDatetime();
      return;
    }

    slots.forEach(function (slot) {
      const time = slot.length >= 16 ? slot.substring(11, 16) : slot;
      const option = document.createElement('option');
      option.value = time;
      option.textContent = time;
      if (selectedTime && selectedTime === time) {
        option.selected = true;
      }
      timeSelect.appendChild(option);
    });

    if (selectedTime && !Array.from(timeSelect.options).some(function (opt) { return opt.value === selectedTime; })) {
      const fallback = document.createElement('option');
      fallback.value = selectedTime;
      fallback.textContent = selectedTime + ' (aktuell)';
      fallback.selected = true;
      timeSelect.insertBefore(fallback, timeSelect.firstChild);
    }

    timeSelect.disabled = false;
    syncHiddenDatetime();
  }

  function loadSlots() {
    const date = dateInput.value;
    syncHiddenDatetime();

    if (!date) {
      renderOptions([], '');
      const article = selectedArticle();
      const duration = article ? Number(article.work_minutes || 15) : 15;
      setHint('Dauer: ' + duration + ' Min. · Raster: ' + (config.slotStepMinutes || 15) + ' Min.');
      return;
    }

    if (!isAvailabilityRequired()) {
      timeSelect.disabled = false;
      if (!timeSelect.options.length || timeSelect.options[0].value === '') {
        renderOptions([], initialTime);
      }
      setHint('Bei stornierten Terminen werden keine Arbeitszeiten geprüft.');
      return;
    }

    const params = new URLSearchParams({
      date: date,
      article_id: String(currentArticleId()),
      employee_id: String(currentEmployeeId())
    });
    if (excludeBookingId > 0) {
      params.set('exclude_booking_id', String(excludeBookingId));
    }

    setHint('Freie Termine werden geladen …');
    timeSelect.disabled = true;

    fetch(apiUrl + '?' + params.toString(), { credentials: 'same-origin' })
      .then(function (response) { return response.json(); })
      .then(function (payload) {
        if (!payload || !payload.success) {
          throw new Error((payload && payload.message) || 'Slots konnten nicht geladen werden.');
        }
        const data = payload.data || {};
        const slots = data.slots || [];
        const keepTime = initialTime && dateInput.value === config.initialDate ? initialTime : timeSelect.value;
        renderOptions(slots, keepTime);
        const duration = data.duration_minutes || 15;
        setHint(
          slots.length
            ? slots.length + ' freie Termine · Dauer ' + duration + ' Min.'
            : 'Keine freien Termine an diesem Tag.',
          slots.length === 0
        );
      })
      .catch(function (error) {
        renderOptions([], '');
        setHint(error.message || 'Fehler beim Laden der Termine.', true);
      });
  }

  if (articleSelect) {
    articleSelect.addEventListener('change', function () {
      renderEmployeeOptions();
      loadSlots();
    });
  }

  if (employeeSelect) {
    employeeSelect.addEventListener('change', loadSlots);
  }

  dateInput.addEventListener('change', loadSlots);
  timeSelect.addEventListener('change', syncHiddenDatetime);

  if (statusSelect) {
    statusSelect.addEventListener('change', loadSlots);
  }

  form.addEventListener('submit', function (event) {
    syncHiddenDatetime();
    if (!isAvailabilityRequired()) {
      return;
    }
    if (!hiddenDatetime.value) {
      event.preventDefault();
      setHint('Bitte Datum und Uhrzeit wählen.', true);
    }
  });

  renderEmployeeOptions();
  if (dateInput.value) {
    loadSlots();
  }
})();
