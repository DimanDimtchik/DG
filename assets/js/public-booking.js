(function () {
  'use strict';

  var root = document.getElementById('tk-public-booking');
  if (!root) {
    return;
  }

  var config = window.tkPublicBooking || {};
  var articles = Array.isArray(config.articles) ? config.articles : [];
  var employees = Array.isArray(config.employees) ? config.employees : [];

  var state = {
    step: 1,
    articleId: 0,
    articleTitle: '',
    usesEmployees: false,
    employeeId: 0,
    slotDatetime: '',
    slotLabel: ''
  };

  var stepIndicators = root.querySelectorAll('[data-step-indicator]');
  var panels = root.querySelectorAll('[data-step-panel]');
  var serviceButtons = root.querySelectorAll('.tk-book__service');
  var dateInput = document.getElementById('tk-book-date');
  var employeeField = document.getElementById('tk-book-employee-field');
  var employeeSelect = document.getElementById('tk-book-employee');
  var slotsEl = document.getElementById('tk-book-slots');
  var slotsHint = document.getElementById('tk-book-slots-hint');
  var selectedServiceEl = document.getElementById('tk-book-selected-service');
  var selectedSlotEl = document.getElementById('tk-book-selected-slot');
  var form = document.getElementById('tk-book-form');
  var errorEl = document.getElementById('tk-book-error');
  var submitBtn = document.getElementById('tk-book-submit');
  var successMessageEl = document.getElementById('tk-book-success-message');

  function articleById(id) {
    return articles.find(function (row) { return Number(row.id) === Number(id); }) || null;
  }

  function setMinDate() {
    if (!dateInput) {
      return;
    }
    var today = new Date();
    var y = today.getFullYear();
    var m = String(today.getMonth() + 1).padStart(2, '0');
    var d = String(today.getDate()).padStart(2, '0');
    dateInput.min = y + '-' + m + '-' + d;
  }

  function showStep(step) {
    state.step = step;
    stepIndicators.forEach(function (el) {
      var n = Number(el.getAttribute('data-step-indicator'));
      el.classList.toggle('is-active', n === step);
      el.classList.toggle('is-done', n < step);
    });
    panels.forEach(function (panel) {
      var key = panel.getAttribute('data-step-panel');
      var active = String(step) === key;
      panel.classList.toggle('is-active', active);
      panel.hidden = !active;
    });
    root.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function formatSlotLabel(value) {
    if (!value) {
      return '';
    }
    var parts = value.replace('T', ' ').split(' ');
    if (parts.length < 2) {
      return value;
    }
    var dateParts = parts[0].split('-');
    var time = parts[1].slice(0, 5);
    if (dateParts.length === 3) {
      return dateParts[2] + '.' + dateParts[1] + '.' + dateParts[0] + ', ' + time + ' Uhr';
    }
    return parts[0] + ' ' + time;
  }

  function setError(message) {
    if (!errorEl) {
      return;
    }
    if (!message) {
      errorEl.hidden = true;
      errorEl.textContent = '';
      return;
    }
    errorEl.hidden = false;
    errorEl.textContent = message;
  }

  function renderSlots(slots) {
    if (!slotsEl) {
      return;
    }
    slotsEl.innerHTML = '';
    if (!slots || slots.length === 0) {
      if (slotsHint) {
        slotsHint.textContent = 'An diesem Tag sind keine Termine frei.';
      }
      return;
    }
    if (slotsHint) {
      slotsHint.textContent = slots.length + ' freie Zeiten — bitte eine auswählen:';
    }
    slots.forEach(function (slot) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'tk-book__slot';
      btn.textContent = slot.slice(11, 16);
      btn.setAttribute('data-slot', slot);
      if (state.slotDatetime === slot) {
        btn.classList.add('is-selected');
      }
      btn.addEventListener('click', function () {
        state.slotDatetime = slot;
        state.slotLabel = formatSlotLabel(slot);
        slotsEl.querySelectorAll('.tk-book__slot').forEach(function (node) {
          node.classList.toggle('is-selected', node.getAttribute('data-slot') === slot);
        });
        document.getElementById('tk-book-slot-datetime').value = slot.replace(' ', 'T');
        document.getElementById('tk-book-employee-id').value = String(state.employeeId);
        if (selectedSlotEl) {
          selectedSlotEl.textContent = state.articleTitle + ' — ' + state.slotLabel;
        }
        showStep(3);
      });
      slotsEl.appendChild(btn);
    });
  }

  function loadSlots() {
    if (!dateInput || !dateInput.value || state.articleId < 1) {
      renderSlots([]);
      return;
    }
    if (slotsHint) {
      slotsHint.textContent = 'Freie Zeiten werden geladen …';
    }
    var params = new URLSearchParams({
      date: dateInput.value,
      article_id: String(state.articleId),
      employee_id: String(state.employeeId)
    });
    fetch((config.apiSlots || '/api/booking-slots') + '?' + params.toString(), { credentials: 'same-origin' })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (!data.success) {
          renderSlots([]);
          if (slotsHint) {
            slotsHint.textContent = data.message || 'Zeiten konnten nicht geladen werden.';
          }
          return;
        }
        renderSlots((data.data && data.data.slots) || []);
      })
      .catch(function () {
        renderSlots([]);
        if (slotsHint) {
          slotsHint.textContent = 'Zeiten konnten nicht geladen werden.';
        }
      });
  }

  serviceButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = Number(btn.getAttribute('data-article-id') || 0);
      var article = articleById(id);
      if (!article) {
        return;
      }
      state.articleId = id;
      state.articleTitle = article.title || '';
      state.usesEmployees = btn.getAttribute('data-uses-employees') === '1';
      state.slotDatetime = '';
      state.employeeId = 0;
      serviceButtons.forEach(function (node) {
        node.classList.toggle('is-selected', node === btn);
      });
      document.getElementById('tk-book-article-id').value = String(id);
      if (selectedServiceEl) {
        selectedServiceEl.hidden = false;
        selectedServiceEl.textContent = 'Leistung: ' + state.articleTitle;
      }
      if (employeeField) {
        employeeField.hidden = !state.usesEmployees;
      }
      if (employeeSelect) {
        employeeSelect.value = '0';
      }
      if (dateInput) {
        dateInput.value = '';
      }
      renderSlots([]);
      showStep(2);
    });
  });

  root.querySelectorAll('[data-step-back]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      showStep(Number(btn.getAttribute('data-step-back')));
    });
  });

  if (dateInput) {
    dateInput.addEventListener('change', loadSlots);
  }
  if (employeeSelect) {
    employeeSelect.addEventListener('change', function () {
      state.employeeId = Number(employeeSelect.value || 0);
      state.slotDatetime = '';
      loadSlots();
    });
  }

  if (form) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      setError('');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Wird gesendet …';
      }
      var body = new FormData(form);
      fetch(config.apiBook || '/api/public-booking', {
        method: 'POST',
        body: body,
        credentials: 'same-origin'
      })
        .then(function (response) {
          return response.json().then(function (data) {
            return { ok: response.ok, data: data };
          });
        })
        .then(function (result) {
          if (!result.ok || !result.data.success) {
            throw new Error((result.data && result.data.message) || 'Buchung fehlgeschlagen.');
          }
          if (successMessageEl && result.data.message) {
            successMessageEl.textContent = result.data.message;
          }
          showStep('done');
        })
        .catch(function (error) {
          setError(error && error.message ? error.message : 'Buchung fehlgeschlagen.');
        })
        .finally(function () {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Termin verbindlich buchen';
          }
        });
    });
  }

  setMinDate();
  showStep(1);
})();
