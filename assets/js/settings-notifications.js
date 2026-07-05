document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('dg-notifications-form');
  const accordion = document.getElementById('dg-notify-accordion');
  if (!form || !accordion) {
    return;
  }

  const CALENDAR_OWNER_ID = 'terminkalender';
  const previewConfig = window.dgCalendarEmailPreview || {};
  const cardTemplate = document.getElementById('dg-email-tpl-card-template');
  const deleteFields = document.getElementById('dg-notification-delete-fields');
  const addButton = document.getElementById('dg-email-tpl-add');
  const ownerDialog = document.getElementById('dg-tpl-owner-dialog');
  const ownerSelect = document.getElementById('dg-tpl-owner-select');
  const ownerCancel = document.getElementById('dg-tpl-owner-cancel');
  const ownerConfirm = document.getElementById('dg-tpl-owner-confirm');
  let lastFocusedField = null;
  let newTemplateCounter = 0;
  let activeDraftCard = null;

  form.querySelectorAll('input[type="text"], textarea').forEach((field) => {
    field.addEventListener('focus', () => {
      lastFocusedField = field;
    });
  });

  form.querySelectorAll('[data-insert-token]').forEach((chip) => {
    chip.addEventListener('click', () => {
      const token = chip.getAttribute('data-insert-token') || '';
      if (!token || !lastFocusedField) {
        return;
      }
      const start = lastFocusedField.selectionStart ?? lastFocusedField.value.length;
      const end = lastFocusedField.selectionEnd ?? lastFocusedField.value.length;
      const value = lastFocusedField.value;
      lastFocusedField.value = value.slice(0, start) + token + value.slice(end);
      lastFocusedField.focus();
      lastFocusedField.selectionStart = lastFocusedField.selectionEnd = start + token.length;
    });
  });

  function escapeAttr(value) {
    if (typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
      return CSS.escape(value);
    }

    return String(value).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
  }

  function selectedOwnerOption() {
    return ownerSelect?.selectedOptions[0] ?? null;
  }

  function isCalendarOwner(ownerId) {
    return ownerId === CALENDAR_OWNER_ID || selectedOwnerOption()?.getAttribute('data-is-calendar') === '1';
  }

  function departmentIdForOwner(ownerId) {
    return isCalendarOwner(ownerId) ? '' : ownerId;
  }

  function ownerList(ownerId) {
    return accordion.querySelector('[data-tpl-list][data-owner-id="' + escapeAttr(ownerId) + '"]');
  }

  function openOwnerSection(ownerId) {
    accordion.querySelectorAll('[data-tpl-owner-section]').forEach((section) => {
      section.open = section.getAttribute('data-owner-id') === ownerId;
    });
  }

  function removeDraftCard() {
    if (activeDraftCard) {
      activeDraftCard.remove();
      activeDraftCard = null;
    }
  }

  function wireTemplateCard(card) {
    const nameInput = card.querySelector('[data-tpl-name]');
    const summaryName = card.querySelector('[data-tpl-summary-name]');
    if (nameInput && summaryName) {
      nameInput.addEventListener('input', () => {
        summaryName.textContent = nameInput.value.trim() || 'Neue Vorlage';
      });
    }

    card.querySelector('.dg-email-preview-btn')?.addEventListener('click', (event) => {
      runPreview(event.currentTarget);
    });

    card.querySelector('[data-tpl-delete]')?.addEventListener('click', () => {
      const id = card.getAttribute('data-tpl-id') || '';
      if (id && deleteFields) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'notification_delete[]';
        input.value = id;
        deleteFields.appendChild(input);
      }
      if (card === activeDraftCard) {
        activeDraftCard = null;
      }
      card.remove();
    });
  }

  function createDraftInOwnerSection(ownerId) {
    if (!cardTemplate) {
      return null;
    }

    removeDraftCard();
    newTemplateCounter += 1;
    const id = 'new-' + Date.now() + '-' + newTemplateCounter;
    const calendar = isCalendarOwner(ownerId);
    const list = ownerList(ownerId);

    if (!list) {
      window.alert('Der gewählten Einheit ist kein Vorlagen-Bereich zugeordnet.');
      return null;
    }

    const clone = cardTemplate.content.firstElementChild.cloneNode(true);
    clone.setAttribute('data-tpl-id', id);
    clone.setAttribute('data-tpl-owner', ownerId);
    clone.setAttribute('data-tpl-category', calendar ? 'calendar' : 'department');
    clone.setAttribute('data-tpl-draft', '1');
    clone.classList.add('dg-email-tpl-type--draft');

    const prefix = 'notification_templates[' + id + ']';
    const setField = (selector, name, value) => {
      const el = clone.querySelector(selector);
      if (!el) {
        return;
      }
      el.name = prefix + name;
      if (value !== undefined) {
        el.value = value;
      }
    };

    setField('[data-field-id]', '[id]', id);
    setField('[data-field-department]', '[department_id]', departmentIdForOwner(ownerId));
    setField('[data-field-builtin]', '[builtin]', '0');
    setField('[data-field-event-slug]', '[event_slug]', '');

    const categoryFixed = clone.querySelector('[data-tpl-category-fixed]');

    if (calendar) {
      setField('[data-tpl-category-fixed]', '[category]', 'calendar');
    } else {
      setField('[data-tpl-category-fixed]', '[category]', 'department');
    }

    setField('[data-email-field="name"]', '[name]', '');
    setField('[data-email-field="subject"]', '[subject]', '');
    setField('[data-email-field="title"]', '[title]', '');
    setField('[data-email-field="intro"]', '[intro]', '');

    const subjectInput = clone.querySelector('[data-email-field="subject"]');
    const previewBtn = clone.querySelector('.dg-email-preview-btn');
    const previewBox = clone.querySelector('[data-email-preview]');
    if (subjectInput) {
      subjectInput.setAttribute('data-template-key', id);
    }
    if (previewBtn) {
      previewBtn.setAttribute('data-template-key', id);
    }
    if (previewBox) {
      previewBox.setAttribute('data-email-preview', id);
    }

    const summaryName = clone.querySelector('[data-tpl-summary-name]');
    if (summaryName) {
      summaryName.textContent = 'Neue Vorlage';
    }

    wireTemplateCard(clone);

    const emptyHint = list.querySelector('[data-tpl-empty]');
    if (emptyHint) {
      emptyHint.remove();
    }

    list.appendChild(clone);
    activeDraftCard = clone;
    openOwnerSection(ownerId);

    clone.open = true;
    clone.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    clone.querySelector('[data-tpl-name]')?.focus();

    return clone;
  }

  function confirmOwnerSelection() {
    const ownerId = ownerSelect?.value ?? '';
    if (!ownerId) {
      window.alert('Bitte wählen Sie Terminkalender oder eine Abteilung.');
      return;
    }

    ownerDialog?.close();
    createDraftInOwnerSection(ownerId);
  }

  form.querySelectorAll('[data-email-tpl-card]').forEach((card) => {
    wireTemplateCard(card);
  });

  addButton?.addEventListener('click', () => {
    if (ownerDialog && typeof ownerDialog.showModal === 'function') {
      ownerDialog.showModal();
      ownerSelect?.focus();
    }
  });

  ownerCancel?.addEventListener('click', () => {
    ownerDialog?.close();
  });

  ownerConfirm?.addEventListener('click', () => {
    confirmOwnerSelection();
  });

  ownerSelect?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      confirmOwnerSelection();
    }
  });

  ownerDialog?.addEventListener('click', (event) => {
    if (event.target === ownerDialog) {
      ownerDialog.close();
    }
  });

  ownerDialog?.addEventListener('cancel', (event) => {
    event.preventDefault();
    ownerDialog.close();
  });

  async function runPreview(button) {
    const card = button.closest('[data-email-tpl-card]');
    if (!card || !previewConfig.url) {
      return;
    }
    const templateKey = button.getAttribute('data-template-key') || card.getAttribute('data-tpl-id') || '';
    const subjectInput = card.querySelector('[data-email-field="subject"]');
    const titleInput = card.querySelector('[data-email-field="title"]');
    const introInput = card.querySelector('[data-email-field="intro"]');
    const previewBox = card.querySelector('[data-email-preview]');
    const subjectOut = card.querySelector('[data-email-preview-subject]');
    const frame = card.querySelector('.dg-email-preview__frame');

    if (!subjectInput || !titleInput || !introInput || !previewBox || !frame) {
      return;
    }

    button.disabled = true;
    try {
      const body = new FormData();
      body.append('_csrf', previewConfig.csrf || '');
      body.append('template_key', templateKey);
      body.append('event_slug', button.getAttribute('data-event-slug') || '');
      body.append('subject', subjectInput.value);
      body.append('title', titleInput.value);
      body.append('intro', introInput.value);

      const response = await fetch(previewConfig.url, {
        method: 'POST',
        body,
        credentials: 'same-origin',
      });
      const payload = await response.json();
      if (!payload.success || !payload.data) {
        throw new Error(payload.message || 'Vorschau fehlgeschlagen.');
      }

      if (subjectOut) {
        subjectOut.textContent = payload.data.subject || '';
      }
      frame.srcdoc = payload.data.html || '';
      previewBox.hidden = false;
    } catch (error) {
      window.alert(error instanceof Error ? error.message : 'Vorschau fehlgeschlagen.');
    } finally {
      button.disabled = false;
    }
  }

  const layoutAccordion = document.getElementById('dg-email-layout-accordion');
  const headerPreview = document.getElementById('dg-email-header-preview');
  const footerPreview = document.getElementById('dg-email-footer-preview');
  let layoutPreviewTimer = null;

  async function refreshLayoutPreview() {
    if (!layoutAccordion || !headerPreview || !footerPreview) {
      return;
    }

    clearTimeout(layoutPreviewTimer);
    layoutPreviewTimer = setTimeout(async () => {
      try {
        const formData = new FormData(form);
        const response = await fetch('/api/email-layout-preview', {
          method: 'POST',
          body: formData,
          credentials: 'same-origin',
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) {
          return;
        }

        headerPreview.innerHTML = payload.header_html;
        footerPreview.innerHTML = payload.footer_html;
      } catch (error) {
        // Vorschau ist optional.
      }
    }, 250);
  }

  if (layoutAccordion) {
    layoutAccordion.addEventListener('input', refreshLayoutPreview);
    layoutAccordion.addEventListener('change', refreshLayoutPreview);
  }
});
