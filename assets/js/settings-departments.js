document.addEventListener('DOMContentLoaded', () => {
  const repeater = document.getElementById('dg-departments-repeater');
  const addDeptBtn = document.getElementById('dg-add-department');
  const expandAllBtn = document.getElementById('dg-dept-expand-all');
  const collapseAllBtn = document.getElementById('dg-dept-collapse-all');
  const memberTemplate = document.getElementById('dg-dept-member-template');

  if (!repeater || !addDeptBtn || !memberTemplate) {
    return;
  }

  function syncHrDeleteFlag(card) {
    const isHr = card.querySelector('[data-dept-is-hr]');
    const deleteWrap = card.querySelector('[data-dept-delete-flag]');
    if (!isHr || !deleteWrap) {
      return;
    }
    deleteWrap.hidden = !isHr.checked;
    if (!isHr.checked) {
      const deleteCheckbox = deleteWrap.querySelector('input[type="checkbox"]');
      if (deleteCheckbox) {
        deleteCheckbox.checked = false;
      }
    }
    updateDeptSummary(card);
  }

  function memberCount(card) {
    let count = 0;
    card.querySelectorAll('[data-dept-member] select[name*="[user_id]"], [data-dept-member] select[data-member-user]').forEach((select) => {
      if (parseInt(select.value, 10) > 0) {
        count += 1;
      }
    });
    return count;
  }

  function updateDeptSummary(card) {
    const title = card.querySelector('[data-dept-title]');
    const summary = card.querySelector('[data-dept-summary]');
    const nameInput = card.querySelector('input[type="text"]');
    if (!title || !summary) {
      return;
    }

    const cards = Array.from(repeater.querySelectorAll('[data-dept-card]'));
    const deptIndex = cards.indexOf(card);
    const name = nameInput ? nameInput.value.trim() : '';
    title.textContent = name !== '' ? name : 'Abteilung #' + (deptIndex + 1);

    const parts = [];
    const count = memberCount(card);
    if (count > 0) {
      parts.push(count === 1 ? '1 Mitglied' : count + ' Mitglieder');
    }
    const isHr = card.querySelector('[data-dept-is-hr]');
    if (isHr && isHr.checked) {
      parts.push('HR-Rechte');
    }
    const allowCatalog = card.querySelector('[data-dept-allow-catalog]');
    if (allowCatalog && allowCatalog.checked) {
      parts.push('Artikel/Leistungen');
    }
    summary.textContent = parts.length > 0 ? parts.join(' · ') : 'Keine Mitglieder';
  }

  function setDeptOpen(card, open) {
    const panel = card.querySelector('[data-dept-panel]');
    const toggle = card.querySelector('[data-dept-toggle]');
    if (!panel || !toggle) {
      return;
    }
    panel.hidden = !open;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    card.classList.toggle('is-open', open);
  }

  function setAllDepartmentsOpen(open) {
    repeater.querySelectorAll('[data-dept-card]').forEach((card) => {
      setDeptOpen(card, open);
    });
  }

  function reindexDepartments() {
    repeater.querySelectorAll('[data-dept-card]').forEach((card, deptIndex) => {
      const idInput = card.querySelector('input[type="hidden"]');
      if (idInput) {
        idInput.name = 'departments[' + deptIndex + '][id]';
      }

      const nameInput = card.querySelector('input[type="text"]');
      if (nameInput) {
        nameInput.name = 'departments[' + deptIndex + '][name]';
      }

      const description = card.querySelector('textarea');
      if (description) {
        description.name = 'departments[' + deptIndex + '][description]';
      }

      const isHr = card.querySelector('[data-dept-is-hr]');
      if (isHr) {
        isHr.name = 'departments[' + deptIndex + '][is_hr]';
      }

      const deleteCheckbox = card.querySelector('[data-dept-delete-flag] input[type="checkbox"]');
      if (deleteCheckbox) {
        deleteCheckbox.name = 'departments[' + deptIndex + '][allow_contact_delete]';
      }

      const catalogCheckbox = card.querySelector('[data-dept-allow-catalog]');
      if (catalogCheckbox) {
        catalogCheckbox.name = 'departments[' + deptIndex + '][allow_article_catalog]';
      }

      card.querySelectorAll('.dg-dept-module-table input[type="radio"]').forEach((radio) => {
        const match = radio.name.match(/\[modules\]\[([^\]]+)\]$/);
        if (match) {
          radio.name = 'departments[' + deptIndex + '][modules][' + match[1] + ']';
        }
      });

      card.querySelectorAll('[data-dept-member]').forEach((row, memberIndex) => {
        const userSelect = row.querySelector('select[data-member-user], select[name*="[user_id]"]');
        const roleSelect = row.querySelector('select[data-member-role], select[name*="[role]"]');
        if (userSelect) {
          userSelect.name = 'departments[' + deptIndex + '][members][' + memberIndex + '][user_id]';
        }
        if (roleSelect) {
          roleSelect.name = 'departments[' + deptIndex + '][members][' + memberIndex + '][role]';
        }
      });

      syncHrDeleteFlag(card);
      updateDeptSummary(card);
    });
  }

  function addMemberRow(card) {
    const members = card.querySelector('[data-dept-members]');
    if (!members) {
      return;
    }
    const clone = memberTemplate.content.firstElementChild.cloneNode(true);
    members.appendChild(clone);
    reindexDepartments();
  }

  function createDepartmentCard() {
    const templateCard = repeater.querySelector('[data-dept-card]');
    if (!templateCard) {
      return null;
    }
    const card = templateCard.cloneNode(true);
    card.classList.remove('is-open');
    card.querySelectorAll('input[type="text"], textarea').forEach((field) => {
      field.value = '';
    });
    const hiddenId = card.querySelector('input[type="hidden"]');
    if (hiddenId) {
      hiddenId.value = '';
    }
    card.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
      checkbox.checked = false;
    });
    card.querySelectorAll('[data-dept-member]').forEach((row, index) => {
      if (index > 0) {
        row.remove();
      } else {
        row.querySelectorAll('select').forEach((select) => {
          select.selectedIndex = 0;
        });
      }
    });
    setDeptOpen(card, false);
    syncHrDeleteFlag(card);
    return card;
  }

  addDeptBtn.addEventListener('click', () => {
    const card = createDepartmentCard();
    if (card) {
      repeater.appendChild(card);
      reindexDepartments();
      setDeptOpen(card, true);
    }
  });

  if (expandAllBtn) {
    expandAllBtn.addEventListener('click', () => {
      setAllDepartmentsOpen(true);
    });
  }

  if (collapseAllBtn) {
    collapseAllBtn.addEventListener('click', () => {
      setAllDepartmentsOpen(false);
    });
  }

  repeater.addEventListener('change', (event) => {
    const card = event.target.closest('[data-dept-card]');
    if (!card) {
      return;
    }
    if (event.target.matches('[data-dept-is-hr]')) {
      syncHrDeleteFlag(card);
    }
    if (event.target.matches('[data-dept-allow-catalog]')) {
      updateDeptSummary(card);
    }
    if (event.target.matches('select[name*="[user_id]"], select[data-member-user]')) {
      updateDeptSummary(card);
    }
  });

  repeater.addEventListener('input', (event) => {
    if (event.target.matches('input[type="text"]')) {
      const card = event.target.closest('[data-dept-card]');
      if (card) {
        updateDeptSummary(card);
      }
    }
  });

  repeater.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-dept-toggle]');
    if (toggle) {
      const card = toggle.closest('[data-dept-card]');
      if (card) {
        const panel = card.querySelector('[data-dept-panel]');
        setDeptOpen(card, panel ? panel.hidden : true);
      }
      event.preventDefault();
      return;
    }

    const addMember = event.target.closest('[data-member-add]');
    if (addMember) {
      const card = addMember.closest('[data-dept-card]');
      if (card) {
        addMemberRow(card);
      }
      event.preventDefault();
      return;
    }

    const removeMember = event.target.closest('[data-member-remove]');
    if (removeMember) {
      const card = removeMember.closest('[data-dept-card]');
      const row = removeMember.closest('[data-dept-member]');
      const members = card?.querySelectorAll('[data-dept-member]') ?? [];
      if (card && row) {
        if (members.length <= 1) {
          row.querySelectorAll('select').forEach((select) => {
            select.selectedIndex = 0;
          });
        } else {
          row.remove();
          reindexDepartments();
        }
        updateDeptSummary(card);
      }
      event.preventDefault();
      return;
    }

    const removeDept = event.target.closest('[data-dept-remove]');
    if (removeDept) {
      const cards = repeater.querySelectorAll('[data-dept-card]');
      const card = removeDept.closest('[data-dept-card]');
      if (!card) {
        return;
      }
      if (cards.length <= 1) {
        card.querySelectorAll('input[type="text"], textarea').forEach((field) => {
          field.value = '';
        });
        const hiddenId = card.querySelector('input[type="hidden"]');
        if (hiddenId) {
          hiddenId.value = '';
        }
        card.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
          checkbox.checked = false;
        });
        card.querySelectorAll('[data-dept-member]').forEach((row, index) => {
          if (index > 0) {
            row.remove();
          } else {
            row.querySelectorAll('select').forEach((select) => {
              select.selectedIndex = 0;
            });
          }
        });
        setDeptOpen(card, false);
        syncHrDeleteFlag(card);
        updateDeptSummary(card);
      } else {
        card.remove();
        reindexDepartments();
      }
      event.preventDefault();
    }
  });

  repeater.querySelectorAll('[data-dept-card]').forEach((card) => {
    syncHrDeleteFlag(card);
    updateDeptSummary(card);
  });
  reindexDepartments();

  const deptForm = document.getElementById('dg-departments-form');
  if (deptForm) {
    deptForm.querySelectorAll('[data-dept-email-area]').forEach((area) => {
      const sync = () => {
        const modeInput = area.querySelector('[data-dept-email-mode]:checked');
        const mode = modeInput ? modeInput.value : 'standard';
        const inheritWrap = area.querySelector('[data-dept-inherit-wrap]');
        if (inheritWrap) {
          inheritWrap.hidden = mode !== 'inherit';
        }
      };
      sync();
      area.querySelectorAll('[data-dept-email-mode]').forEach((radio) => {
        radio.addEventListener('change', sync);
      });
    });
  }
});
