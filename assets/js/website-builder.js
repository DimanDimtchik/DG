/**
 * Website-Page-Builder — Zeilen, Spalten, Blöcke mit Einfüge-Plus (WPBakery-Stil).
 */
(function () {
  var layoutField = document.getElementById('dg-website-layout');
  var canvas = document.getElementById('dg-website-canvas');
  var inspector = document.getElementById('dg-website-inspector');
  var builder = document.getElementById('dg-website-builder');
  var titleInput = document.getElementById('dg-website-title');
  var slugInput = document.getElementById('dg-website-slug');
  var previewLink = document.getElementById('dg-website-preview-link');
  if (!layoutField || !canvas || !builder) {
    return;
  }

  var readOnly = builder.getAttribute('data-readonly') === '1';
  var selected = { type: '', rowId: '', colId: '', blockId: '' };
  /** @type {{mode: string, rowId?: string, colId?: string, blockId?: string, index?: number}|null} */
  var pendingInsert = null;
  var slugTouched = Boolean(slugInput && slugInput.value);
  var typeLabels = {
    heading: 'Überschrift',
    text: 'Text',
    image: 'Bild',
    button: 'Button',
    spacer: 'Abstand',
    video: 'Video',
    divider: 'Trennlinie',
    html: 'HTML',
    contact: 'Kontakt (wird zu Formular)',
    form: 'Formular',
    gallery: 'Galerie',
  };
  // Contact no longer offered in palette; keep label for old layouts until saved.
  var blockTypes = Object.keys(typeLabels).filter(function (t) { return t !== 'contact'; });

  function uid(prefix) {
    return prefix + '-' + Math.random().toString(16).slice(2, 10);
  }

  function parseLayout() {
    try {
      var data = JSON.parse(layoutField.value || '{}');
      if (!data.rows || !Array.isArray(data.rows)) {
        data = { rows: [] };
      }
      data.rows.forEach(function (row) {
        (row.columns || []).forEach(function (col) {
          (col.blocks || []).forEach(function (block) {
            if (block.type === 'button' && !block.label && block.text) {
              block.label = block.text;
            }
          });
        });
      });
      return data;
    } catch (e) {
      return { rows: [] };
    }
  }

  function persist(layout) {
    layoutField.value = JSON.stringify(layout);
  }

  function findColumn(layout, colId) {
    var found = null;
    layout.rows.forEach(function (row) {
      (row.columns || []).forEach(function (col) {
        if (col.id === colId) found = col;
      });
    });
    return found;
  }

  function findRow(layout, rowId) {
    for (var i = 0; i < layout.rows.length; i++) {
      if (layout.rows[i].id === rowId) return layout.rows[i];
    }
    return null;
  }

  function findBlock(layout, blockId) {
    var found = null;
    layout.rows.forEach(function (row) {
      (row.columns || []).forEach(function (col) {
        (col.blocks || []).forEach(function (block) {
          if (block.id === blockId) found = block;
        });
      });
    });
    return found;
  }

  function findBlockPosition(layout, blockId) {
    var result = null;
    layout.rows.forEach(function (row) {
      (row.columns || []).forEach(function (col) {
        (col.blocks || []).forEach(function (block, idx) {
          if (block.id === blockId) {
            result = { row: row, col: col, index: idx };
          }
        });
      });
    });
    return result;
  }

  function findColumnPosition(layout, colId) {
    var result = null;
    layout.rows.forEach(function (row, rowIndex) {
      (row.columns || []).forEach(function (col, colIndex) {
        if (col.id === colId) {
          result = { row: row, rowIndex: rowIndex, col: col, colIndex: colIndex };
        }
      });
    });
    return result;
  }

  function defaultBlock(type) {
    switch (type) {
      case 'heading': return { id: uid('blk'), type: 'heading', text: 'Überschrift', level: 'h2' };
      case 'image':   return { id: uid('blk'), type: 'image', src: '', alt: '' };
      case 'button':  return { id: uid('blk'), type: 'button', label: 'Mehr erfahren', url: '#' };
      case 'spacer':  return { id: uid('blk'), type: 'spacer', height: 24 };
      case 'video':   return { id: uid('blk'), type: 'video', url: '', caption: '' };
      case 'divider': return { id: uid('blk'), type: 'divider', style: 'solid', color: '#ddd' };
      case 'html':    return { id: uid('blk'), type: 'html', code: '' };
      case 'contact': return { id: uid('blk'), type: 'contact', email: '', subject: 'Kontaktanfrage' };
      case 'form': return { id: uid('blk'), type: 'form', form_id: 0 };
      case 'gallery': return { id: uid('blk'), type: 'gallery', images: [] };
      default:        return { id: uid('blk'), type: 'text', text: 'Neuer Textabsatz.' };
    }
  }

  function redistributeWidths(columns) {
    var n = columns.length;
    if (n <= 0) return;
    var base = Math.floor(12 / n);
    var rest = 12 - base * n;
    columns.forEach(function (col, i) {
      col.width = base + (i < rest ? 1 : 0);
    });
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function embedUrl(url) {
    url = String(url || '').trim();
    var m;
    m = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]+)/);
    if (m) return 'https://www.youtube-nocookie.com/embed/' + m[1];
    m = url.match(/vimeo\.com\/(\d+)/);
    if (m) return 'https://player.vimeo.com/video/' + m[1];
    return '';
  }

  function sanitizeSlug(value) {
    return String(value || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
  }

  function updatePreviewLink() {
    if (!previewLink || !slugInput) return;
    var slug = sanitizeSlug(slugInput.value);
    if (!slug) {
      previewLink.setAttribute('href', '#');
      previewLink.setAttribute('aria-disabled', 'true');
      return;
    }
    var path = '/vorschau/' + slug;
    previewLink.setAttribute('href', path);
    previewLink.setAttribute('data-preview-path', path);
    previewLink.removeAttribute('aria-disabled');
  }

  function plusBtn(side, attrs) {
    return '<button type="button" class="dg-website-plus dg-website-plus--' + side + '" ' + attrs + ' title="Hier einfügen" aria-label="Hier einfügen">+</button>';
  }

  function pickerMenuHtml() {
    return '<div class="dg-website-insert-menu" data-insert-menu hidden>' +
      blockTypes.map(function (type) {
        return '<button type="button" class="dg-website-insert-menu__item" data-insert-type="' + escapeHtml(type) + '">' +
          escapeHtml(typeLabels[type]) + '</button>';
      }).join('') +
      '</div>';
  }

  function renderBlock(block) {
    var isSelected = selected.blockId === block.id;
    var selectedClass = isSelected ? ' is-selected' : '';
    var html = '<div class="dg-website-block' + selectedClass + '" data-block-id="' + escapeHtml(block.id) + '">';

    if (!readOnly && isSelected) {
      html += '<div class="dg-website-plus-wrap" data-plus-wrap>';
      html += plusBtn('top', 'data-insert-at="block-before" data-block-id="' + escapeHtml(block.id) + '"');
      html += plusBtn('bottom', 'data-insert-at="block-after" data-block-id="' + escapeHtml(block.id) + '"');
      html += plusBtn('left', 'data-insert-at="col-before" data-block-id="' + escapeHtml(block.id) + '"');
      html += plusBtn('right', 'data-insert-at="col-after" data-block-id="' + escapeHtml(block.id) + '"');
      html += pickerMenuHtml();
      html += '</div>';
    }

    switch (block.type) {
      case 'heading':
        var level = block.level === 'h1' || block.level === 'h3' ? block.level : 'h2';
        html += '<p class="dg-website-block__heading dg-website-block__heading--' + level + '">' + escapeHtml(block.text) + '</p>';
        break;
      case 'image':
        html += block.src
          ? '<img class="dg-website-block__image" src="' + escapeHtml(block.src) + '" alt="' + escapeHtml(block.alt) + '">'
          : '<div class="dg-website-block__image-placeholder">Bild — Mediathek, URL oder Upload rechts</div>';
        break;
      case 'button':
        html += '<span class="dg-website-block__btn">' + escapeHtml(block.label || block.text || 'Button') + '</span>';
        break;
      case 'spacer':
        html += '<div class="dg-website-block__spacer" style="height:' + (parseInt(block.height, 10) || 24) + 'px"></div>';
        break;
      case 'video':
        var embed = embedUrl(block.url);
        html += embed
          ? '<div class="dg-website-block__video"><iframe src="' + escapeHtml(embed) + '" frameborder="0" allowfullscreen style="width:100%;aspect-ratio:16/9;border-radius:6px;"></iframe></div>'
          : '<div class="dg-website-block__image-placeholder">Video — YouTube/Vimeo-URL rechts eintragen</div>';
        break;
      case 'divider':
        var style = block.style || 'solid';
        var color = block.color || '#ddd';
        html += '<hr class="dg-website-block__divider" style="border:none;border-top:2px ' + escapeHtml(style) + ' ' + escapeHtml(color) + ';margin:8px 0;">';
        break;
      case 'html':
        html += block.code
          ? '<div class="dg-website-block__html-preview" style="padding:8px;background:#f8f8f8;border:1px dashed #ccc;border-radius:4px;font-size:0.85em;font-family:monospace;white-space:pre-wrap;">' + escapeHtml(block.code).substring(0, 200) + '</div>'
          : '<div class="dg-website-block__image-placeholder">HTML — Code rechts eingeben</div>';
        break;
      case 'contact':
        html += '<div class="dg-website-block__contact-preview" style="padding:12px;background:#f5f5f5;border:1px solid #ddd;border-radius:6px;text-align:center;color:#888;">'
          + 'Altes Kontaktformular — beim Speichern → Formular' + (block.email ? ' (' + escapeHtml(block.email) + ')' : '') + '</div>';
        break;
      case 'form':
        html += '<div class="dg-website-block__contact-preview" style="padding:12px;background:#f5f5f5;border:1px solid #ddd;border-radius:6px;text-align:center;color:#888;">'
          + 'Formular' + (block.form_id ? ' #' + escapeHtml(String(block.form_id)) : ' — bitte rechts auswählen') + '</div>';
        break;
      case 'gallery':
        var imgs = block.images || [];
        html += '<div class="dg-website-block__gallery" style="display:flex;gap:4px;flex-wrap:wrap;">';
        if (imgs.length) {
          imgs.forEach(function (img) {
            html += '<img src="' + escapeHtml(img.src) + '" alt="' + escapeHtml(img.alt || '') + '" style="width:80px;height:60px;object-fit:cover;border-radius:4px;">';
          });
        } else {
          html += '<div class="dg-website-block__image-placeholder" style="width:100%;">Galerie — Bilder rechts hinzufügen</div>';
        }
        html += '</div>';
        break;
      default:
        html += '<p class="dg-website-block__text">' + escapeHtml(block.text) + '</p>';
    }
    return html + '</div>';
  }

  function render(options) {
    var layout = parseLayout();
    if (!layout.rows.length) {
      canvas.innerHTML = '<div class="dg-website-canvas-empty">Noch keine Zeile. Links unter „Zeile“ eine Spaltenanzahl wählen — oder hier starten.</div>';
    } else {
      canvas.innerHTML = layout.rows.map(function (row, rowIndex) {
        var rowHtml = '';
        if (!readOnly && rowIndex === 0) {
          rowHtml += '<div class="dg-website-row-gap">' +
            plusBtn('row', 'data-insert-at="row-before" data-row-id="' + escapeHtml(row.id) + '"') +
            '</div>';
        }
        rowHtml += '<div class="dg-website-row" data-row-id="' + escapeHtml(row.id) + '">' +
          (row.columns || []).map(function (col) {
            var colSelected = selected.colId === col.id && !selected.blockId;
            var selectedClass = colSelected ? ' is-selected' : '';
            var blocks = col.blocks || [];
            var inner = blocks.length
              ? blocks.map(renderBlock).join('')
              : '<p class="dg-website-col__hint">Leere Spalte — Block links wählen oder + nutzen</p>';
            var colHtml = '<div class="dg-website-col' + selectedClass + '" data-col-id="' + escapeHtml(col.id) + '" style="flex:' + (col.width || 12) + '">';
            if (!readOnly && colSelected) {
              colHtml += '<div class="dg-website-plus-wrap dg-website-plus-wrap--col" data-plus-wrap>';
              colHtml += plusBtn('top', 'data-insert-at="row-before" data-row-id="' + escapeHtml(row.id) + '"');
              colHtml += plusBtn('bottom', 'data-insert-at="row-after" data-row-id="' + escapeHtml(row.id) + '"');
              colHtml += plusBtn('left', 'data-insert-at="col-before" data-col-id="' + escapeHtml(col.id) + '"');
              colHtml += plusBtn('right', 'data-insert-at="col-after" data-col-id="' + escapeHtml(col.id) + '"');
              colHtml += plusBtn('center', 'data-insert-at="block-into" data-col-id="' + escapeHtml(col.id) + '"');
              colHtml += pickerMenuHtml();
              colHtml += '</div>';
            }
            colHtml += inner + '</div>';
            return colHtml;
          }).join('') +
          '</div>';
        rowHtml += '<div class="dg-website-row-gap">' +
          (!readOnly
            ? plusBtn('row', 'data-insert-at="row-after" data-row-id="' + escapeHtml(row.id) + '"')
            : '') +
          '</div>';
        return rowHtml;
      }).join('');
    }
    if (!(options && options.keepInspector)) {
      renderInspector();
    }
    updatePaletteHint();
  }

  function updatePaletteHint() {
    var hint = builder.querySelector('[data-palette-hint]');
    if (!hint) return;
    if (pendingInsert) {
      hint.textContent = 'Einfügestelle aktiv — jetzt links einen Blocktyp wählen.';
      return;
    }
    if (selected.blockId) {
      hint.textContent = 'Block markiert: Plus-Zeichen nutzen oder Block links wählen (wird darunter eingefügt).';
      return;
    }
    if (selected.colId) {
      hint.textContent = 'Spalte markiert: Block links wählen oder Plus in der Mitte.';
      return;
    }
    hint.textContent = 'Spalte oder Block in der Vorschau anklicken, dann Block wählen — oder Plus-Zeichen nutzen.';
  }

  function fieldHtml(label, name, value, extra) {
    extra = extra || '';
    return '<label class="dg-field"><span>' + escapeHtml(label) + '</span>' +
      '<input name="' + escapeHtml(name) + '" value="' + escapeHtml(value || '') + '" ' + extra + '>' +
      '</label>';
  }

  function textareaHtml(label, name, value, rows) {
    return '<label class="dg-field"><span>' + escapeHtml(label) + '</span>' +
      '<textarea name="' + escapeHtml(name) + '" rows="' + (rows || 4) + '">' + escapeHtml(value) + '</textarea></label>';
  }

  function moveButtons() {
    return '<div class="dg-form-actions dg-website-inspector-actions" style="margin-bottom:8px;">' +
      '<button type="button" class="dg-button" data-move-block="up" title="Nach oben">↑</button>' +
      '<button type="button" class="dg-button" data-move-block="down" title="Nach unten">↓</button>' +
      '</div>';
  }

  function imageUploadHtml(name) {
    return '<div class="dg-website-image-tools">' +
      '<label class="dg-field"><span>Bild hochladen</span>' +
      '<input type="file" accept="image/*" data-upload-image="' + escapeHtml(name) + '" style="font-size:0.9rem;">' +
      '</label>' +
      '<button type="button" class="dg-button" data-media-pick="' + escapeHtml(name) + '">Aus Mediathek</button>' +
      '</div>';
  }

  function applyImageSelection(targetField, item) {
    var layout = parseLayout();
    var block = findBlock(layout, selected.blockId);
    if (!block) return;
    var url = item.url || '';
    var alt = item.alt_text || item.title || '';
    if (targetField === 'gallery_add') {
      block.images = block.images || [];
      block.images.push({ src: url, alt: alt });
    } else {
      block[targetField] = url;
      if (targetField === 'src' && alt && !(block.alt || '').trim()) {
        block.alt = alt;
      }
    }
    persist(layout);
    render();
  }

  function ensureMediaPicker() {
    var modal = document.getElementById('dg-website-media-picker');
    if (modal) return modal;

    modal = document.createElement('div');
    modal.id = 'dg-website-media-picker';
    modal.className = 'dg-modal';
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    modal.innerHTML =
      '<div class="dg-modal__backdrop" data-media-picker-close></div>' +
      '<div class="dg-modal__dialog dg-website-media-picker__dialog" role="dialog" aria-modal="true" aria-labelledby="dg-website-media-picker-title">' +
        '<header class="dg-modal__head">' +
          '<h2 id="dg-website-media-picker-title">Mediathek</h2>' +
          '<button type="button" class="dg-modal__close" data-media-picker-close aria-label="Schließen">&times;</button>' +
        '</header>' +
        '<div class="dg-website-media-picker__toolbar">' +
          '<label class="dg-field dg-website-media-picker__search"><span class="dg-visually-hidden">Suchen</span>' +
            '<input type="search" placeholder="Suchen …" data-media-picker-search>' +
          '</label>' +
          '<a class="dg-button" href="/app?page=bilder" target="_blank" rel="noopener">Zur Mediathek</a>' +
        '</div>' +
        '<div class="dg-website-media-picker__body" data-media-picker-body>' +
          '<p class="dg-field-hint">Lade Mediathek …</p>' +
        '</div>' +
        '<footer class="dg-modal__foot">' +
          '<button type="button" class="dg-button" data-media-picker-close>Abbrechen</button>' +
        '</footer>' +
      '</div>';
    document.body.appendChild(modal);

    modal.addEventListener('click', function (event) {
      if (event.target.closest('[data-media-picker-close]')) {
        closeMediaPicker();
        return;
      }
      var pick = event.target.closest('[data-media-pick-id]');
      if (!pick) return;
      var mediaId = pick.getAttribute('data-media-pick-id') || '';
      var item = (mediaPickerCache || []).find(function (row) { return row.media_id === mediaId; });
      if (!item || !mediaPickerTarget) return;
      applyImageSelection(mediaPickerTarget, item);
      closeMediaPicker();
    });

    var search = modal.querySelector('[data-media-picker-search]');
    if (search) {
      search.addEventListener('input', function () {
        renderMediaPickerItems(search.value);
      });
    }

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') closeMediaPicker();
    });

    return modal;
  }

  var mediaPickerTarget = '';
  var mediaPickerCache = null;

  function closeMediaPicker() {
    var modal = document.getElementById('dg-website-media-picker');
    if (!modal) return;
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    mediaPickerTarget = '';
  }

  function openMediaPicker(targetField) {
    mediaPickerTarget = targetField;
    var modal = ensureMediaPicker();
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    var search = modal.querySelector('[data-media-picker-search]');
    if (search) search.value = '';
    loadMediaPickerItems();
  }

  function loadMediaPickerItems() {
    var body = document.querySelector('[data-media-picker-body]');
    if (!body) return;
    body.innerHTML = '<p class="dg-field-hint">Lade Mediathek …</p>';

    var cfg = window.dgWebsiteBuilder || {};
    var url = cfg.mediaListUrl || '/api/media?action=list';
    fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (res) {
        if (!res.ok) throw new Error('Liste nicht ladbar');
        return res.json();
      })
      .then(function (data) {
        var payload = data && data.data ? data.data : data;
        mediaPickerCache = Array.isArray(payload.items) ? payload.items : [];
        renderMediaPickerItems('');
      })
      .catch(function () {
        body.innerHTML = '<p class="dg-field-hint">Mediathek konnte nicht geladen werden. Haben Sie Admin-Rechte?</p>';
      });
  }

  function renderMediaPickerItems(query) {
    var body = document.querySelector('[data-media-picker-body]');
    if (!body) return;
    var items = mediaPickerCache || [];
    var q = String(query || '').trim().toLowerCase();
    if (q) {
      items = items.filter(function (item) {
        var hay = [item.title, item.original_name, item.alt_text, item.media_id].join(' ').toLowerCase();
        return hay.indexOf(q) !== -1;
      });
    }
    if (!items.length) {
      body.innerHTML = '<p class="dg-field-hint">Keine Bilder gefunden. Laden Sie welche unter Bilder hoch.</p>';
      return;
    }
    var html = '<div class="dg-website-media-picker__grid">';
    items.forEach(function (item) {
      var label = item.title || item.original_name || item.media_id;
      var isSvg = (item.mime_type === 'image/svg+xml') || String(item.extension || '').toLowerCase() === 'svg';
      html += '<button type="button" class="dg-website-media-picker__item" data-media-pick-id="' + escapeHtml(item.media_id) + '" title="' + escapeHtml(label) + '">' +
        '<img src="' + escapeHtml(item.preview_url || item.url) + '" alt="" loading="lazy"' + (isSvg ? ' class="dg-media-thumb--svg"' : '') + '>' +
        '<span>' + escapeHtml(label) + '</span>' +
        '</button>';
    });
    html += '</div>';
    body.innerHTML = html;
  }

  function renderInspector() {
    if (!inspector) return;
    var layout = parseLayout();

    if (selected.blockId) {
      var block = findBlock(layout, selected.blockId);
      if (!block) {
        inspector.innerHTML = '<p class="dg-field-hint">Block nicht gefunden.</p>';
        return;
      }
      var html = '<p class="dg-field-hint">' + escapeHtml(typeLabels[block.type] || block.type) + '</p>';
      html += moveButtons();

      switch (block.type) {
        case 'heading':
          html += fieldHtml('Text', 'text', block.text);
          html += '<label class="dg-field"><span>Größe</span><select name="level">' +
            '<option value="h1"' + (block.level === 'h1' ? ' selected' : '') + '>Groß</option>' +
            '<option value="h2"' + (block.level !== 'h1' && block.level !== 'h3' ? ' selected' : '') + '>Mittel</option>' +
            '<option value="h3"' + (block.level === 'h3' ? ' selected' : '') + '>Klein</option>' +
            '</select></label>';
          break;
        case 'image':
          html += fieldHtml('Bild-URL', 'src', block.src);
          html += imageUploadHtml('src');
          html += fieldHtml('Alternativtext', 'alt', block.alt);
          break;
        case 'button':
          html += fieldHtml('Beschriftung', 'label', block.label || block.text);
          html += fieldHtml('Link', 'url', block.url);
          break;
        case 'spacer':
          html += fieldHtml('Höhe (px)', 'height', String(block.height || 24), 'type="number" min="8" max="160"');
          break;
        case 'video':
          html += fieldHtml('YouTube/Vimeo-URL', 'url', block.url);
          html += fieldHtml('Beschriftung', 'caption', block.caption);
          break;
        case 'divider':
          html += '<label class="dg-field"><span>Stil</span><select name="style">' +
            '<option value="solid"' + (block.style === 'solid' ? ' selected' : '') + '>Durchgezogen</option>' +
            '<option value="dashed"' + (block.style === 'dashed' ? ' selected' : '') + '>Gestrichelt</option>' +
            '<option value="dotted"' + (block.style === 'dotted' ? ' selected' : '') + '>Gepunktet</option>' +
            '</select></label>';
          html += fieldHtml('Farbe', 'color', block.color || '#ddd', 'type="color"');
          break;
        case 'html':
          html += textareaHtml('HTML-Code', 'code', block.code, 8);
          break;
        case 'contact':
          html += '<p class="dg-field-hint">Dieses klassische Kontaktformular wird beim Speichern der Seite automatisch in einen Formular-Block überführt.</p>';
          html += fieldHtml('Empfänger-E-Mail', 'email', block.email, 'type="email"');
          html += fieldHtml('Betreff', 'subject', block.subject);
          break;
        case 'form':
          html += '<label class="dg-field"><span>Formular</span><select name="form_id">';
          html += '<option value="0">— Bitte wählen —</option>';
          var forms = (window.dgWebsiteBuilder && window.dgWebsiteBuilder.forms) || [];
          forms.forEach(function (f) {
            html += '<option value="' + escapeHtml(String(f.id)) + '"' + (String(block.form_id) === String(f.id) ? ' selected' : '') + '>'
              + escapeHtml(f.title || ('#' + f.id)) + '</option>';
          });
          html += '</select></label>';
          html += '<p class="dg-field-hint"><a href="/app?page=website-formulare" target="_blank" rel="noopener">Formulare verwalten</a></p>';
          break;
        case 'gallery':
          var imgs = block.images || [];
          html += '<p class="dg-field-hint">' + imgs.length + ' Bilder</p>';
          html += '<label class="dg-field"><span>Bild hinzufügen (URL)</span>' +
            '<input name="gallery_add_url" placeholder="https://...">' +
            '</label>';
          html += imageUploadHtml('gallery_add');
          html += '<button type="button" class="dg-button" data-gallery-add style="margin-top:4px;">Bild hinzufügen</button>';
          if (imgs.length) {
            html += '<div style="margin-top:8px;">';
            imgs.forEach(function (img, i) {
              html += '<div style="display:flex;gap:4px;align-items:center;margin-bottom:4px;">' +
                '<img src="' + escapeHtml(img.src) + '" style="width:40px;height:30px;object-fit:cover;border-radius:3px;">' +
                '<span style="flex:1;font-size:0.85em;overflow:hidden;text-overflow:ellipsis;">' + escapeHtml(img.src.split('/').pop()) + '</span>' +
                '<button type="button" class="dg-button dg-button--danger" data-gallery-remove="' + i + '" style="padding:2px 6px;font-size:0.8em;">×</button>' +
                '</div>';
            });
            html += '</div>';
          }
          break;
        default:
          html += textareaHtml('Text', 'text', block.text, 6);
      }

      html += '<div class="dg-form-actions dg-website-inspector-actions">' +
        '<button type="button" class="dg-button dg-button--danger" data-remove-block>Block entfernen</button>' +
        '</div>';
      inspector.innerHTML = html;
      return;
    }
    if (selected.colId) {
      inspector.innerHTML = '<p class="dg-field-hint">Spalte ausgewählt. Plus-Zeichen nutzen oder links einen Block wählen.</p>' +
        '<div class="dg-form-actions dg-website-inspector-actions">' +
        '<button type="button" class="dg-button dg-button--danger" data-remove-row>Zeile entfernen</button>' +
        '</div>';
      return;
    }
    inspector.innerHTML = '<p class="dg-field-hint">Einen Block oder eine Spalte in der Vorschau auswählen.</p>';
  }

  function ensureSelection(layout) {
    if (selected.colId && findColumn(layout, selected.colId)) return;
    var firstRow = layout.rows[0];
    var firstCol = firstRow && firstRow.columns ? firstRow.columns[0] : null;
    selected = {
      type: firstCol ? 'column' : '',
      rowId: firstRow ? firstRow.id : '',
      colId: firstCol ? firstCol.id : '',
      blockId: '',
    };
  }

  function insertBlockAt(layout, type) {
    var block = defaultBlock(type);
    var slot = pendingInsert;

    if (slot && (slot.mode === 'block-before' || slot.mode === 'block-after') && slot.blockId) {
      var pos = findBlockPosition(layout, slot.blockId);
      if (pos) {
        var at = slot.mode === 'block-before' ? pos.index : pos.index + 1;
        pos.col.blocks.splice(at, 0, block);
        selected = { type: 'block', rowId: pos.row.id, colId: pos.col.id, blockId: block.id };
        pendingInsert = null;
        return block;
      }
    }

    if (slot && slot.mode === 'block-into' && slot.colId) {
      var colInto = findColumn(layout, slot.colId);
      var colPos = findColumnPosition(layout, slot.colId);
      if (colInto) {
        colInto.blocks = colInto.blocks || [];
        colInto.blocks.push(block);
        selected = {
          type: 'block',
          rowId: colPos ? colPos.row.id : selected.rowId,
          colId: colInto.id,
          blockId: block.id,
        };
        pendingInsert = null;
        return block;
      }
    }

    // Palette click with selected block: insert after selection
    if (selected.blockId) {
      var afterPos = findBlockPosition(layout, selected.blockId);
      if (afterPos) {
        afterPos.col.blocks.splice(afterPos.index + 1, 0, block);
        selected = { type: 'block', rowId: afterPos.row.id, colId: afterPos.col.id, blockId: block.id };
        pendingInsert = null;
        return block;
      }
    }

    ensureSelection(layout);
    var col = findColumn(layout, selected.colId);
    if (!col) return null;
    col.blocks = col.blocks || [];
    col.blocks.push(block);
    selected.blockId = block.id;
    selected.type = 'block';
    pendingInsert = null;
    return block;
  }

  function insertColumnBeside(layout, refColId, side) {
    var pos = findColumnPosition(layout, refColId);
    if (!pos) return null;
    var newCol = { id: uid('col'), width: 6, blocks: [] };
    var at = side === 'before' ? pos.colIndex : pos.colIndex + 1;
    pos.row.columns.splice(at, 0, newCol);
    redistributeWidths(pos.row.columns);
    selected = { type: 'column', rowId: pos.row.id, colId: newCol.id, blockId: '' };
    pendingInsert = { mode: 'block-into', colId: newCol.id, rowId: pos.row.id };
    return newCol;
  }

  function insertRowBeside(layout, refRowId, side, widths) {
    widths = widths || [12];
    var rowIndex = -1;
    layout.rows.forEach(function (row, i) {
      if (row.id === refRowId) rowIndex = i;
    });
    if (rowIndex < 0) return null;
    var row = {
      id: uid('row'),
      columns: widths.map(function (width) {
        return { id: uid('col'), width: width, blocks: [] };
      }),
    };
    var at = side === 'before' ? rowIndex : rowIndex + 1;
    layout.rows.splice(at, 0, row);
    selected = { type: 'column', rowId: row.id, colId: row.columns[0].id, blockId: '' };
    pendingInsert = { mode: 'block-into', colId: row.columns[0].id, rowId: row.id };
    return row;
  }

  function closeInsertMenus() {
    canvas.querySelectorAll('[data-insert-menu]').forEach(function (el) {
      el.hidden = true;
    });
  }

  function openInsertMenu(plusBtn) {
    closeInsertMenus();
    var wrap = plusBtn.closest('[data-plus-wrap]') || plusBtn.parentElement;
    var menu = wrap && wrap.querySelector('[data-insert-menu]');
    if (!menu) {
      // row-gap plus buttons have no wrap menu — create floating one
      return false;
    }
    menu.hidden = false;
    return true;
  }

  function uploadImage(file, callback) {
    var formData = new FormData();
    formData.append('file', file);
    formData.append('_csrf', document.querySelector('input[name="_csrf"]')?.value || '');
    formData.append('website_image_upload', '1');

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/app?page=website-seite-form&action=upload', true);
    xhr.onload = function () {
      if (xhr.status === 200) {
        try {
          var resp = JSON.parse(xhr.responseText);
          if (resp.url) callback(resp.url, resp);
        } catch (e) { /* ignore parse errors */ }
      }
    };
    xhr.send(formData);
  }

  // ── Canvas click / plus ───────────────────────────────────────

  canvas.addEventListener('click', function (event) {
    if (readOnly) return;

    var insertTypeBtn = event.target.closest('[data-insert-type]');
    if (insertTypeBtn) {
      event.preventDefault();
      event.stopPropagation();
      var layout = parseLayout();
      var type = insertTypeBtn.getAttribute('data-insert-type');
      insertBlockAt(layout, type);
      persist(layout);
      closeInsertMenus();
      render();
      return;
    }

    var plus = event.target.closest('[data-insert-at]');
    if (plus) {
      event.preventDefault();
      event.stopPropagation();
      var mode = plus.getAttribute('data-insert-at');
      var layout2 = parseLayout();

      if (mode === 'col-before' || mode === 'col-after') {
        var refCol = plus.getAttribute('data-col-id');
        if (!refCol && plus.getAttribute('data-block-id')) {
          var bpos = findBlockPosition(layout2, plus.getAttribute('data-block-id'));
          refCol = bpos ? bpos.col.id : '';
        }
        if (refCol) {
          insertColumnBeside(layout2, refCol, mode === 'col-before' ? 'before' : 'after');
          persist(layout2);
          render();
          // immediately open block picker via pending + palette hint
          return;
        }
      }

      if (mode === 'row-before' || mode === 'row-after') {
        var refRow = plus.getAttribute('data-row-id');
        if (refRow) {
          insertRowBeside(layout2, refRow, mode === 'row-before' ? 'before' : 'after', [12]);
          persist(layout2);
          render();
          return;
        }
      }

      // block-before / block-after / block-into → show type menu
      pendingInsert = {
        mode: mode,
        blockId: plus.getAttribute('data-block-id') || '',
        colId: plus.getAttribute('data-col-id') || '',
        rowId: plus.getAttribute('data-row-id') || '',
      };
      if (!openInsertMenu(plus)) {
        // row gap: no menu — use palette
        updatePaletteHint();
        return;
      }
      updatePaletteHint();
      return;
    }

    var blockEl = event.target.closest('[data-block-id]');
    var colEl = event.target.closest('[data-col-id]');
    var rowEl = event.target.closest('[data-row-id]');
    pendingInsert = null;
    closeInsertMenus();
    if (blockEl && !event.target.closest('[data-plus-wrap]')) {
      selected = {
        type: 'block',
        rowId: rowEl ? rowEl.getAttribute('data-row-id') : '',
        colId: colEl ? colEl.getAttribute('data-col-id') : '',
        blockId: blockEl.getAttribute('data-block-id'),
      };
    } else if (colEl) {
      selected = {
        type: 'column',
        rowId: rowEl ? rowEl.getAttribute('data-row-id') : '',
        colId: colEl.getAttribute('data-col-id'),
        blockId: '',
      };
    }
    render();
  });

  // ── Builder palette / inspector actions ───────────────────────

  builder.addEventListener('click', function (event) {
    if (readOnly) return;

    var addBlock = event.target.closest('[data-add-block]');
    var addRow = event.target.closest('[data-add-row]');
    var removeBlock = event.target.closest('[data-remove-block]');
    var removeRow = event.target.closest('[data-remove-row]');
    var moveBlock = event.target.closest('[data-move-block]');
    var galleryAdd = event.target.closest('[data-gallery-add]');
    var galleryRemove = event.target.closest('[data-gallery-remove]');
    var layout = parseLayout();

    if (addBlock) {
      var type = addBlock.getAttribute('data-add-block');
      if (!layout.rows.length) {
        layout.rows.push({
          id: uid('row'),
          columns: [{ id: uid('col'), width: 12, blocks: [] }],
        });
        selected = {
          type: 'column',
          rowId: layout.rows[0].id,
          colId: layout.rows[0].columns[0].id,
          blockId: '',
        };
      }
      insertBlockAt(layout, type);
      persist(layout);
      render();
      return;
    }

    if (addRow) {
      var widths = (addRow.getAttribute('data-add-row') || '12').split('-').map(function (n) {
        return parseInt(n, 10) || 12;
      });
      var insertIndex = layout.rows.length;
      if (selected.rowId) {
        layout.rows.forEach(function (row, i) {
          if (row.id === selected.rowId) insertIndex = i + 1;
        });
      }
      var row = {
        id: uid('row'),
        columns: widths.map(function (width) {
          return { id: uid('col'), width: width, blocks: [] };
        }),
      };
      layout.rows.splice(insertIndex, 0, row);
      selected = { type: 'column', rowId: row.id, colId: row.columns[0].id, blockId: '' };
      pendingInsert = null;
      persist(layout);
      render();
      return;
    }

    if (removeBlock && selected.blockId) {
      layout.rows.forEach(function (row) {
        (row.columns || []).forEach(function (col) {
          col.blocks = (col.blocks || []).filter(function (block) {
            return block.id !== selected.blockId;
          });
        });
      });
      selected.blockId = '';
      persist(layout);
      render();
      return;
    }

    if (removeRow && selected.rowId) {
      layout.rows = layout.rows.filter(function (row) {
        return row.id !== selected.rowId;
      });
      selected = { type: '', rowId: '', colId: '', blockId: '' };
      persist(layout);
      render();
      return;
    }

    if (moveBlock && selected.blockId) {
      var dir = moveBlock.getAttribute('data-move-block');
      var pos = findBlockPosition(layout, selected.blockId);
      if (!pos) return;
      var blocks = pos.col.blocks;
      var idx = pos.index;
      var newIdx = dir === 'up' ? idx - 1 : idx + 1;
      if (newIdx < 0 || newIdx >= blocks.length) return;
      var tmp = blocks[idx];
      blocks[idx] = blocks[newIdx];
      blocks[newIdx] = tmp;
      persist(layout);
      render();
      return;
    }

    if (galleryAdd && selected.blockId) {
      var gBlock = findBlock(layout, selected.blockId);
      if (!gBlock || gBlock.type !== 'gallery') return;
      gBlock.images = gBlock.images || [];
      var urlInput = inspector.querySelector('[name="gallery_add_url"]');
      var url = urlInput ? urlInput.value.trim() : '';
      if (url) {
        gBlock.images.push({ src: url, alt: '' });
        persist(layout);
        render();
      }
      return;
    }

    if (galleryRemove && selected.blockId) {
      var gIdx = parseInt(galleryRemove.getAttribute('data-gallery-remove'), 10);
      var gBlock2 = findBlock(layout, selected.blockId);
      if (!gBlock2 || gBlock2.type !== 'gallery') return;
      gBlock2.images = gBlock2.images || [];
      gBlock2.images.splice(gIdx, 1);
      persist(layout);
      render();
      return;
    }
  });

  if (inspector) {
    inspector.addEventListener('input', function (event) {
      var field = event.target;
      if (!field.name || !selected.blockId) return;
      var layout = parseLayout();
      var block = findBlock(layout, selected.blockId);
      if (!block) return;
      if (field.name === 'form_id') {
        block.form_id = parseInt(field.value, 10) || 0;
      } else {
        block[field.name] = field.value;
      }
      persist(layout);
      render({ keepInspector: true });
    });

    inspector.addEventListener('change', function (event) {
      var select = event.target.closest('select[name="form_id"]');
      if (select && selected.blockId) {
        var layout = parseLayout();
        var block = findBlock(layout, selected.blockId);
        if (block) {
          block.form_id = parseInt(select.value, 10) || 0;
          persist(layout);
          render({ keepInspector: true });
        }
      }
      var fileInput = event.target.closest('[data-upload-image]');
      if (!fileInput || !fileInput.files || !fileInput.files[0]) return;
      var targetField = fileInput.getAttribute('data-upload-image');

      uploadImage(fileInput.files[0], function (url, meta) {
        var layout = parseLayout();
        var block = findBlock(layout, selected.blockId);
        if (!block) return;

        if (targetField === 'gallery_add') {
          block.images = block.images || [];
          block.images.push({ src: url, alt: (meta && meta.alt) || '' });
        } else {
          block[targetField] = url;
          if (targetField === 'src' && meta && meta.alt && !(block.alt || '').trim()) {
            block.alt = meta.alt;
          }
        }
        persist(layout);
        render();
      });
    });

    inspector.addEventListener('click', function (event) {
      var pickBtn = event.target.closest('[data-media-pick]');
      if (!pickBtn) return;
      event.preventDefault();
      openMediaPicker(pickBtn.getAttribute('data-media-pick') || 'src');
    });
  }

  if (titleInput && slugInput && !readOnly) {
    slugInput.addEventListener('input', function () {
      slugTouched = true;
      updatePreviewLink();
    });
    titleInput.addEventListener('input', function () {
      if (slugTouched) return;
      slugInput.value = sanitizeSlug(titleInput.value);
      updatePreviewLink();
    });
  }

  // Hint element
  var paletteHint = builder.querySelector('.dg-website-builder__palette .dg-field-hint');
  if (paletteHint) {
    paletteHint.setAttribute('data-palette-hint', '1');
  }

  updatePreviewLink();
  render();
})();
