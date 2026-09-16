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
  const stockFieldsWrap = document.getElementById('dg_article_stock_fields');
  const trackStockInput = document.getElementById('dg_article_track_stock');
  const initialStockWrap = document.getElementById('dg_article_initial_stock_wrap');
  const initialStockInput = document.getElementById('dg_article_initial_stock');
  const minStockInput = document.getElementById('dg_article_min_stock');
  const stockLocationSelect = document.getElementById('dg_article_stock_location');
  const stockHallSelect = document.getElementById('dg_article_stock_hall');
  const stockShelfSelect = document.getElementById('dg_article_stock_shelf');
  const stockPlaceSelect = document.getElementById('dg_article_stock_place');
  const stockPlaceModeSelect = document.getElementById('dg_article_stock_place_mode');
  const stockPositionPreview = document.getElementById('dg_article_stock_position_preview');
  const stockPositionCodeEl = document.getElementById('dg_article_stock_position_code');
  const stockStructureEl = document.getElementById('dg-stock-structure-data');
  const purchaseFieldsWrap = document.getElementById('dg_article_purchase_fields');
  const purchaseSourcesList = document.getElementById('dg-purchase-sources-list');
  const purchaseSourceAddBtn = document.getElementById('dg-purchase-source-add');
  const supplierOptionsEl = document.getElementById('dg-supplier-options');
  let supplierOptions = [];
  if (supplierOptionsEl) {
    try {
      const raw = (supplierOptionsEl.textContent || '').trim();
      const parsed = JSON.parse(raw);
      supplierOptions = Array.isArray(parsed) ? parsed : [];
    } catch (error) {
      // Fallback: ältere Seiten hatten View::escape() im Script-Tag (&quot; …)
      try {
        const decoded = document.createElement('textarea');
        decoded.innerHTML = supplierOptionsEl.textContent || '[]';
        const parsed = JSON.parse(decoded.value || '[]');
        supplierOptions = Array.isArray(parsed) ? parsed : [];
      } catch (error2) {
        supplierOptions = [];
      }
    }
  }
  let stockStructure = { halls: [], shelves: [], places: [] };
  if (stockStructureEl) {
    try {
      stockStructure = JSON.parse(stockStructureEl.textContent || '{}');
    } catch (error) {
      stockStructure = { halls: [], shelves: [], places: [] };
    }
  }
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

  function fillSelect(select, options, selectedValue, emptyLabel) {
    if (!select) {
      return;
    }
    select.innerHTML = '';
    const empty = document.createElement('option');
    empty.value = '';
    empty.textContent = emptyLabel || '— optional —';
    select.appendChild(empty);
    options.forEach(function (opt) {
      const option = document.createElement('option');
      option.value = String(opt.id);
      option.textContent = opt.label;
      if (String(selectedValue || '') === String(opt.id)) {
        option.selected = true;
      }
      select.appendChild(option);
    });
  }

  function selectedPlaceCode() {
    if (!stockPlaceSelect || !stockPlaceSelect.value) {
      return '';
    }
    const place = (stockStructure.places || []).find(function (p) {
      return String(p.id) === String(stockPlaceSelect.value);
    });
    return place && place.position_code ? place.position_code : '';
  }

  function updatePositionPreview() {
    const code = selectedPlaceCode();
    if (stockPositionPreview) {
      stockPositionPreview.hidden = !(catalogKindInput && catalogKindInput.value === 'product');
    }
    if (stockPositionCodeEl) {
      stockPositionCodeEl.textContent = code !== '' ? code : '—';
    }
  }

  function refreshStockCascade(preserve) {
    const locationId = stockLocationSelect ? stockLocationSelect.value : '';
    const hallId = preserve && stockHallSelect ? stockHallSelect.value : '';
    const shelfId = preserve && stockShelfSelect ? stockShelfSelect.value : '';
    const placeId = preserve && stockPlaceSelect ? stockPlaceSelect.value : '';

    const halls = (stockStructure.halls || []).filter(function (h) {
      return !locationId || String(h.location_id) === String(locationId);
    }).map(function (h) {
      return {
        id: h.id,
        label: (h.location_code || '') + ' / ' + (h.code || ''),
      };
    });
    fillSelect(stockHallSelect, halls, hallId);

    const activeHallId = stockHallSelect ? stockHallSelect.value : '';
    const shelves = (stockStructure.shelves || []).filter(function (s) {
      if (locationId && String(s.location_id) !== String(locationId)) {
        return false;
      }
      if (activeHallId && String(s.hall_id) !== String(activeHallId)) {
        return false;
      }
      return true;
    }).map(function (s) {
      const cap = s.capacity_summary || 'Regal';
      return {
        id: s.id,
        label: (s.location_code || '') + ' / ' + (s.hall_code || '') + ' / ' + (s.code || '') + ' (' + cap + ')',
      };
    });
    fillSelect(stockShelfSelect, shelves, shelfId);

    const activeShelfId = stockShelfSelect ? stockShelfSelect.value : '';
    const articleId = idInput ? Number(idInput.value || 0) : 0;
    const places = (stockStructure.places || []).filter(function (p) {
      if (activeShelfId && String(p.shelf_id) !== String(activeShelfId)) {
        return false;
      }
      if (!activeShelfId && activeHallId) {
        const shelf = (stockStructure.shelves || []).find(function (s) {
          return String(s.id) === String(p.shelf_id);
        });
        if (!shelf || String(shelf.hall_id) !== String(activeHallId)) {
          return false;
        }
      }
      if (p.place_mode === 'fixed' && p.fixed_article_id && articleId > 0 && Number(p.fixed_article_id) !== articleId) {
        return false;
      }
      return true;
    }).map(function (p) {
      return { id: p.id, label: p.label || p.position_code || p.code };
    });
    fillSelect(stockPlaceSelect, places, placeId);
    updatePositionPreview();
  }

  function setStockSelection(data) {
    if (stockLocationSelect) {
      stockLocationSelect.value = data.stock_location_id ? String(data.stock_location_id) : '';
    }
    if (stockPlaceModeSelect) {
      stockPlaceModeSelect.value = data.stock_place_mode || 'flexible';
    }
    refreshStockCascade(true);
    if (stockHallSelect && data.stock_hall_id) {
      stockHallSelect.value = String(data.stock_hall_id);
    }
    refreshStockCascade(true);
    if (stockShelfSelect && data.stock_shelf_id) {
      stockShelfSelect.value = String(data.stock_shelf_id);
    }
    refreshStockCascade(true);
    if (stockPlaceSelect && data.stock_place_id) {
      stockPlaceSelect.value = String(data.stock_place_id);
    }
    updatePositionPreview();
  }

  function toggleStockFields() {
    const isProduct = catalogKindInput && catalogKindInput.value === 'product';
    if (stockFieldsWrap) {
      stockFieldsWrap.hidden = !isProduct;
    }
    if (purchaseFieldsWrap) {
      purchaseFieldsWrap.hidden = !isProduct;
    }
    if (initialStockWrap && idInput) {
      initialStockWrap.hidden = !isProduct || idInput.value !== '';
    }
    updatePositionPreview();
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function formatPriceForInput(value) {
    const number = Number(value || 0);
    return number.toFixed(2).replace('.', ',');
  }

  function renderPurchaseSources(sources) {
    if (!purchaseSourcesList) {
      return;
    }
    purchaseSourcesList.innerHTML = '';
    const rows = Array.isArray(sources) && sources.length ? sources : [{}];
    rows.forEach(function (source, index) {
      purchaseSourcesList.appendChild(buildPurchaseSourceRow(source || {}, index));
    });
  }

  function buildPurchaseSourceRow(source, index) {
    const wrap = document.createElement('div');
    wrap.className = 'dg-form-grid dg-purchase-source-row';
    wrap.dataset.index = String(index);

    let supplierOptionsHtml = '<option value="">— Freitext / kein Kontakt —</option>';
    supplierOptions.forEach(function (opt) {
      const selected = Number(source.supplier_contact_id || 0) === Number(opt.id) ? ' selected' : '';
      supplierOptionsHtml += '<option value="' + String(opt.id) + '"' + selected + '>' + escapeHtml(opt.label) + '</option>';
    });

    const preferredChecked = source.is_preferred ? ' checked' : '';
    const price = source.purchase_price != null && source.purchase_price !== ''
      ? formatPriceForInput(source.purchase_price)
      : '';

    wrap.innerHTML =
      '<label class="dg-field dg-field--wide"><span>Firma / Lieferant (Kontakt)</span>' +
      '<select name="purchase_sources[' + index + '][supplier_contact_id]">' + supplierOptionsHtml + '</select></label>' +
      '<label class="dg-field"><span>Lieferant (Freitext, falls nicht in Kontakten)</span>' +
      '<input type="text" name="purchase_sources[' + index + '][supplier_name]" value="' + escapeHtml(source.supplier_name || '') + '" maxlength="191"></label>' +
      '<label class="dg-field"><span>EK-Preis (netto)</span>' +
      '<input type="text" name="purchase_sources[' + index + '][purchase_price]" inputmode="decimal" value="' + escapeHtml(price) + '" placeholder="0,00"></label>' +
      '<label class="dg-field"><span>Währung</span>' +
      '<input type="text" name="purchase_sources[' + index + '][currency]" value="' + escapeHtml(source.currency || 'EUR') + '" maxlength="3"></label>' +
      '<label class="dg-field dg-field--wide"><span>Bestell-URL / Shop</span>' +
      '<input type="url" name="purchase_sources[' + index + '][order_url]" value="' + escapeHtml(source.order_url || '') + '" placeholder="https://…" maxlength="500"></label>' +
      '<label class="dg-field"><span>Lieferanten-Artikelnr.</span>' +
      '<input type="text" name="purchase_sources[' + index + '][external_sku]" value="' + escapeHtml(source.external_sku || '') + '" maxlength="100"></label>' +
      '<label class="dg-field"><span>Notiz</span>' +
      '<input type="text" name="purchase_sources[' + index + '][note]" value="' + escapeHtml(source.note || '') + '" maxlength="500"></label>' +
      '<label class="dg-field"><span><input type="checkbox" name="purchase_sources[' + index + '][is_preferred]" value="1"' + preferredChecked + '> Bevorzugt (Nachbestellen)</span></label>' +
      '<div class="dg-field dg-field--actions"><button type="button" class="dg-button dg-button--small dg-purchase-source-remove">Entfernen</button></div>';

    const removeBtn = wrap.querySelector('.dg-purchase-source-remove');
    if (removeBtn) {
      removeBtn.addEventListener('click', function () {
        wrap.remove();
        reindexPurchaseSources();
        if (purchaseSourcesList && !purchaseSourcesList.children.length) {
          renderPurchaseSources([{}]);
        }
      });
    }
    return wrap;
  }

  function reindexPurchaseSources() {
    if (!purchaseSourcesList) {
      return;
    }
    Array.prototype.forEach.call(purchaseSourcesList.children, function (row, index) {
      row.dataset.index = String(index);
      Array.prototype.forEach.call(row.querySelectorAll('[name]'), function (input) {
        input.name = input.name.replace(/purchase_sources\[\d+]/, 'purchase_sources[' + index + ']');
      });
    });
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
    toggleStockFields();
    if (trackStockInput) {
      trackStockInput.checked = false;
    }
    if (initialStockInput) {
      initialStockInput.value = '';
    }
    if (minStockInput) {
      minStockInput.value = '';
    }
    if (stockLocationSelect) stockLocationSelect.value = '';
    if (stockPlaceModeSelect) stockPlaceModeSelect.value = 'flexible';
    refreshStockCascade(false);
    renderPurchaseSources([{}]);
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
      if (trackStockInput) {
        trackStockInput.checked = Boolean(data.track_stock);
      }
      if (minStockInput) {
        minStockInput.value = data.min_stock != null ? String(data.min_stock).replace('.', ',') : '';
      }
      setStockSelection(data);
      if (initialStockInput) {
        initialStockInput.value = '';
      }
      toggleStockFields();
      updatePositionPreview();
      renderPurchaseSources(data.purchase_sources || [{}]);
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
  if (catalogKindInput) {
    catalogKindInput.addEventListener('change', toggleStockFields);
  }
  if (stockLocationSelect) {
    stockLocationSelect.addEventListener('change', function () { refreshStockCascade(false); });
  }
  if (stockHallSelect) {
    stockHallSelect.addEventListener('change', function () { refreshStockCascade(false); });
  }
  if (stockShelfSelect) {
    stockShelfSelect.addEventListener('change', function () { refreshStockCascade(false); });
  }
  if (stockPlaceSelect) {
    stockPlaceSelect.addEventListener('change', updatePositionPreview);
  }
  if (cancelBtn) {
    cancelBtn.addEventListener('click', resetForm);
  }

  if (purchaseSourceAddBtn) {
    purchaseSourceAddBtn.addEventListener('click', function () {
      if (!purchaseSourcesList) {
        return;
      }
      const index = purchaseSourcesList.children.length;
      purchaseSourcesList.appendChild(buildPurchaseSourceRow({}, index));
    });
  }

  toggleCustomMinutes();
  toggleStockFields();
  refreshStockCascade(false);
  renderPurchaseSources([{}]);

  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('focus') === 'stock') {
    if (catalogKindInput) {
      catalogKindInput.value = 'product';
    }
    toggleStockFields();
    if (formTitle) {
      formTitle.textContent = 'Artikel mit Lagerführung anlegen';
    }
    if (formPanel) {
      formPanel.open = true;
      formPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    if (trackStockInput) {
      trackStockInput.checked = true;
    }
  }
})();
