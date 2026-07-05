(function () {
  const form = document.getElementById('dg-calendar-article-form');
  if (!form) {
    return;
  }

  const workSelect = document.getElementById('dg_article_work_minutes');
  const customWrap = document.getElementById('dg_article_custom_minutes_wrap');
  const customInput = document.getElementById('dg_article_custom_minutes');
  const idInput = document.getElementById('dg_article_id');
  const catalogKindInput = document.getElementById('dg_article_catalog_kind');
  const numberInput = document.getElementById('dg_article_number');
  const gtinInput = document.getElementById('dg_article_gtin');
  const titleInput = document.getElementById('dg_article_title');
  const unitInput = document.getElementById('dg_article_unit');
  const taxInput = document.getElementById('dg_article_tax_type');
  const priceInput = document.getElementById('dg_article_price');
  const areaSelect = document.getElementById('dg_article_area');
  const sortInput = document.getElementById('dg_article_sort');
  const descriptionInput = document.getElementById('dg_article_description');
  const noteInput = document.getElementById('dg_article_note');
  const activeInput = document.getElementById('dg_article_active');
  const formTitle = document.getElementById('dg-article-form-title');
  const formPanel = document.getElementById('dg-article-form-panel');
  const submitBtn = document.getElementById('dg-article-submit');
  const cancelBtn = document.getElementById('dg-article-cancel');
  const presets = [15, 30, 45, 60];
  const defaultNumber = numberInput ? numberInput.value : '';

  function kindLabel(kind) {
    return kind === 'product' ? 'Artikel' : 'Leistung';
  }

  function toggleCustomMinutes() {
    const show = workSelect && workSelect.value === '__custom__';
    if (customWrap) {
      customWrap.hidden = !show;
    }
    if (customInput) {
      customInput.required = Boolean(show);
    }
  }

  function formatPriceForInput(value) {
    const number = Number(value || 0);
    return number.toFixed(2).replace('.', ',');
  }

  function resetForm() {
    form.reset();
    if (idInput) {
      idInput.value = '';
    }
    if (numberInput) {
      numberInput.value = defaultNumber;
    }
    if (activeInput) {
      activeInput.checked = true;
    }
    if (formTitle) {
      formTitle.textContent = 'Neu anlegen';
    }
    if (submitBtn) {
      submitBtn.textContent = 'Speichern';
    }
    if (cancelBtn) {
      cancelBtn.hidden = true;
    }
    if (formPanel) {
      formPanel.open = false;
    }
    toggleCustomMinutes();
  }

  function setWorkMinutes(minutes) {
    if (!workSelect) {
      return;
    }
    if (presets.includes(minutes)) {
      workSelect.value = String(minutes);
    } else {
      workSelect.value = '__custom__';
      if (customInput) {
        customInput.value = String(minutes);
      }
    }
    toggleCustomMinutes();
  }

  document.querySelectorAll('.dg-cal-edit-article').forEach(function (button) {
    button.addEventListener('click', function () {
      let data;
      try {
        data = JSON.parse(button.getAttribute('data-article') || '{}');
      } catch (error) {
        return;
      }

      const kind = data.catalog_kind || 'service';

      if (idInput) {
        idInput.value = data.id || '';
      }
      if (catalogKindInput) {
        catalogKindInput.value = kind;
      }
      if (numberInput) {
        numberInput.value = data.article_number || '';
      }
      if (gtinInput) {
        gtinInput.value = data.gtin || '';
      }
      if (titleInput) {
        titleInput.value = data.title || '';
      }
      if (unitInput) {
        unitInput.value = data.unit || 'Stück';
      }
      if (taxInput) {
        taxInput.value = data.tax_type || 'ust19';
      }
      if (priceInput) {
        priceInput.value = formatPriceForInput(data.price_gross);
      }
      setWorkMinutes(Number(data.work_minutes || 30));
      if (areaSelect) {
        areaSelect.value = data.area_id ? String(data.area_id) : '';
      }
      if (sortInput) {
        sortInput.value = String(data.sort_order || 0);
      }
      if (descriptionInput) {
        descriptionInput.value = data.description || '';
      }
      if (noteInput) {
        noteInput.value = data.note || '';
      }
      if (activeInput) {
        activeInput.checked = Number(data.is_active || 0) === 1;
      }
      if (formTitle) {
        formTitle.textContent = kindLabel(kind) + ' bearbeiten';
      }
      if (submitBtn) {
        submitBtn.textContent = 'Änderungen speichern';
      }
      if (cancelBtn) {
        cancelBtn.hidden = false;
      }
      if (formPanel) {
        formPanel.open = true;
      }
      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });

  if (workSelect) {
    workSelect.addEventListener('change', toggleCustomMinutes);
  }
  if (cancelBtn) {
    cancelBtn.addEventListener('click', resetForm);
  }

  toggleCustomMinutes();
})();
