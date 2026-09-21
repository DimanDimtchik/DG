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
    online_booking: 'Online-Terminbuchung',
  };
  // Contact / system blocks not in generic insert menu.
  var blockTypes = Object.keys(typeLabels).filter(function (t) {
    return t !== 'contact' && t !== 'online_booking';
  });
  var builderCfg = window.dgWebsiteBuilder || {};

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
      return normalizeLayoutMeta(data);
    } catch (e) {
      return normalizeLayoutMeta({ rows: [] });
    }
  }

  function persist(layout) {
    layoutField.value = JSON.stringify(normalizeLayoutMeta(layout));
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
      case 'video':   return { id: uid('blk'), type: 'video', label: '', url: '', caption: '' };
      case 'divider': return { id: uid('blk'), type: 'divider', style: 'solid', color: '#ddd' };
      case 'html':    return { id: uid('blk'), type: 'html', code: '' };
      case 'contact': return { id: uid('blk'), type: 'contact', email: '', subject: 'Kontaktanfrage' };
      case 'form': return { id: uid('blk'), type: 'form', form_id: 0 };
      case 'gallery': return { id: uid('blk'), type: 'gallery', images: [] };
      case 'online_booking': return { id: uid('blk'), type: 'online_booking' };
      default:        return { id: uid('blk'), type: 'text', text: 'Neuer Textabsatz.' };
    }
  }

  function normalizeLayoutMeta(layout) {
    if (builderCfg.isOnlineBookingPage) {
      layout.page_kind = 'online_booking';
    }
    return layout;
  }

  function regenerateRowIds(row) {
    row.id = uid('row');
    (row.columns || []).forEach(function (col) {
      col.id = uid('col');
      (col.blocks || []).forEach(function (block) {
        block.id = uid('blk');
      });
    });
    return row;
  }

  function insertPatternRows(layout, patternKey) {
    var patterns = builderCfg.patterns || {};
    var pattern = patterns[patternKey];
    if (!pattern || !pattern.rows || !pattern.rows.length) return;
    pattern.rows.forEach(function (row) {
      layout.rows.push(regenerateRowIds(JSON.parse(JSON.stringify(row))));
    });
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

  var ADV_DISPLAY = ['block', 'inline', 'inline-block', 'flex', 'none'];
  var ADV_VISIBILITY = ['visible', 'hidden'];
  var ADV_TEXT_ALIGN = ['left', 'center', 'right', 'justify'];
  var ADV_BORDER_STYLE = ['none', 'solid', 'dashed', 'dotted'];
  var ADV_BG_SIZE = ['cover', 'contain', 'auto'];
  var ADV_BG_REPEAT = ['no-repeat', 'repeat', 'repeat-x', 'repeat-y'];
  var ADV_BG_POS = ['center', 'top', 'bottom', 'left', 'right'];
  var ADV_TEXT_DECORATION = ['none', 'underline', 'line-through', 'overline'];
  var ADV_INTERACTION_COLOR_KEYS = ['color', 'background', 'borderColor'];

  function advPickEnum(value, allowed) {
    value = String(value || '').trim();
    return allowed.indexOf(value) !== -1 ? value : '';
  }

  function advSanitizeOpacity(value) {
    value = String(value || '').trim();
    if (value === '' || isNaN(Number(value))) return '';
    var n = Number(value);
    if (n < 0 || n > 1) return '';
    return String(Math.round(n * 1000) / 1000);
  }

  function advSanitizeLength(value) {
    value = String(value || '').trim();
    if (value === '') return '';
    if (/^auto$/i.test(value)) return 'auto';
    if (value === '0') return '0';
    var parts = value.split(/\s+/);
    if (!parts.length || parts.length > 4) return '';
    var clean = [];
    for (var i = 0; i < parts.length; i++) {
      var part = parts[i];
      if (/^auto$/i.test(part)) {
        clean.push('auto');
        continue;
      }
      if (part === '0') {
        clean.push('0');
        continue;
      }
      var m = part.match(/^(-?\d+(?:\.\d+)?)(px|%|rem|em)$/i);
      if (!m) return '';
      clean.push(m[1] + m[2].toLowerCase());
    }
    return clean.join(' ');
  }

  function advSanitizeColor(value) {
    value = String(value || '').trim();
    if (!value) return '';
    if (/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/.test(value)) {
      return value.toLowerCase();
    }
    var m = value.match(/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*(0|1|0?\.\d+)\s*)?\)$/i);
    if (!m) return '';
    var r = parseInt(m[1], 10);
    var g = parseInt(m[2], 10);
    var b = parseInt(m[3], 10);
    if (r > 255 || g > 255 || b > 255) return '';
    if (m[4] != null && m[4] !== '') {
      var a = Number(m[4]);
      if (a < 0 || a > 1) return '';
      var aFmt = String(Math.round(a * 1000) / 1000);
      return 'rgba(' + r + ',' + g + ',' + b + ',' + aFmt + ')';
    }
    return 'rgb(' + r + ',' + g + ',' + b + ')';
  }

  function advSanitizeBgImageUrl(value) {
    value = String(value || '').trim();
    if (!value) return '';
    var wrapped = value.match(/^url\(\s*['"]?(.*?)['"]?\s*\)$/i);
    if (wrapped) value = String(wrapped[1] || '').trim();
    if (!value || /[\s'"<>\\]|javascript:/i.test(value) || value.length > 500) return '';
    if (value.indexOf('/media/') === 0 || value.indexOf('/app/media') === 0) return value;
    if (/^https?:\/\/[a-z0-9.-]+(?::\d+)?(?:\/\S*)?$/i.test(value)) return value;
    return '';
  }

  function advSanitizeBgSize(value) {
    value = String(value || '').trim();
    if (!value) return '';
    var lower = value.toLowerCase();
    if (ADV_BG_SIZE.indexOf(lower) !== -1) return lower;
    var parts = value.split(/\s+/);
    if (!parts.length || parts.length > 2) return '';
    var clean = [];
    for (var i = 0; i < parts.length; i++) {
      var len = advSanitizeLength(parts[i]);
      if (!len || /\s/.test(len)) return '';
      clean.push(len);
    }
    return clean.join(' ');
  }

  function advSanitizeBgPosition(value) {
    value = String(value || '').trim();
    if (!value) return '';
    var parts = value.toLowerCase().split(/\s+/);
    if (!parts.length || parts.length > 2) return '';
    var clean = [];
    for (var i = 0; i < parts.length; i++) {
      if (ADV_BG_POS.indexOf(parts[i]) !== -1) {
        clean.push(parts[i]);
        continue;
      }
      var len = advSanitizeLength(parts[i]);
      if (!len || /\s/.test(len)) return '';
      clean.push(len);
    }
    return clean.join(' ');
  }

  function advSanitizeId(value) {
    value = String(value || '').trim();
    if (!/^[A-Za-z][A-Za-z0-9_:-]*$/.test(value) || value.length > 64) return '';
    return value;
  }

  function advSanitizeClassList(value) {
    value = String(value || '').trim();
    if (!value) return '';
    var tokens = value.split(/\s+/);
    var clean = [];
    tokens.forEach(function (token) {
      if (!/^[A-Za-z_][A-Za-z0-9_-]*$/.test(token) || token.length > 40) return;
      if (clean.indexOf(token) === -1) clean.push(token);
    });
    return clean.slice(0, 8).join(' ');
  }

  function advSanitizePlain(value, maxLen) {
    value = String(value || '').replace(/<[^>]*>/g, '').replace(/[\x00-\x1F\x7F]/g, '').trim();
    if (!value) return '';
    return value.length > maxLen ? value.slice(0, maxLen) : value;
  }

  function normalizeAdvanced(raw) {
    if (!raw || typeof raw !== 'object') return {};
    var out = {};
    var displayIn = raw.display && typeof raw.display === 'object' ? raw.display : {};
    var display = {};
    var d = advPickEnum(displayIn.display, ADV_DISPLAY);
    if (d) display.display = d;
    var v = advPickEnum(displayIn.visibility, ADV_VISIBILITY);
    if (v) display.visibility = v;
    var op = advSanitizeOpacity(displayIn.opacity);
    if (op !== '') display.opacity = op;
    ['width', 'maxWidth', 'margin', 'padding'].forEach(function (key) {
      var len = advSanitizeLength(displayIn[key]);
      if (len) display[key] = len;
    });
    var ta = advPickEnum(displayIn.textAlign, ADV_TEXT_ALIGN);
    if (ta) display.textAlign = ta;
    if (Object.keys(display).length) out.display = display;

    var colorsIn = raw.colors && typeof raw.colors === 'object' ? raw.colors : {};
    var colors = {};
    ['color', 'background'].forEach(function (key) {
      var col = advSanitizeColor(colorsIn[key]);
      if (col) colors[key] = col;
    });
    var bgImg = advSanitizeBgImageUrl(colorsIn.backgroundImage);
    if (bgImg) colors.backgroundImage = bgImg;
    var bgSize = advSanitizeBgSize(colorsIn.backgroundSize);
    if (bgSize) colors.backgroundSize = bgSize;
    var bgPos = advSanitizeBgPosition(colorsIn.backgroundPosition);
    if (bgPos) colors.backgroundPosition = bgPos;
    var bgRepeat = advPickEnum(String(colorsIn.backgroundRepeat || '').toLowerCase(), ADV_BG_REPEAT);
    if (bgRepeat) colors.backgroundRepeat = bgRepeat;
    if (Object.keys(colors).length) out.colors = colors;

    var borderIn = raw.border && typeof raw.border === 'object' ? raw.border : {};
    var border = {};
    var bw = advSanitizeLength(borderIn.width);
    if (bw) border.width = bw;
    var bs = advPickEnum(borderIn.style, ADV_BORDER_STYLE);
    var bc = advSanitizeColor(borderIn.color);
    if (bs && bs !== 'none') border.style = bs;
    else if (bs === 'none' && (bw || bc)) border.style = 'none';
    if (bc) border.color = bc;
    var br = advSanitizeLength(borderIn.radius);
    if (br) border.radius = br;
    ['radiusTL', 'radiusTR', 'radiusBR', 'radiusBL'].forEach(function (rk) {
      var rv = advSanitizeLength(borderIn[rk]);
      if (rv) border[rk] = rv;
    });
    ['top', 'right', 'bottom', 'left'].forEach(function (side) {
      var sw = advSanitizeLength(borderIn[side + 'Width']);
      if (sw) border[side + 'Width'] = sw;
      var ss = advPickEnum(borderIn[side + 'Style'], ADV_BORDER_STYLE);
      var sc = advSanitizeColor(borderIn[side + 'Color']);
      if (ss && ss !== 'none') border[side + 'Style'] = ss;
      else if (ss === 'none' && (sw || sc)) border[side + 'Style'] = 'none';
      if (sc) border[side + 'Color'] = sc;
    });
    if (Object.keys(border).length) out.border = border;

    var attrsIn = raw.attrs && typeof raw.attrs === 'object' ? raw.attrs : {};
    var attrs = {};
    var id = advSanitizeId(attrsIn.id);
    if (id) attrs.id = id;
    var cls = advSanitizeClassList(attrsIn.className);
    if (cls) attrs.className = cls;
    var title = advSanitizePlain(attrsIn.title, 200);
    if (title) attrs.title = title;
    var aria = advSanitizePlain(attrsIn.ariaLabel, 200);
    if (aria) attrs.ariaLabel = aria;
    if (Object.keys(attrs).length) out.attrs = attrs;

    ['hover', 'visited'].forEach(function (state) {
      var stateIn = raw[state] && typeof raw[state] === 'object' ? raw[state] : {};
      var stateOut = {};
      ADV_INTERACTION_COLOR_KEYS.forEach(function (ck) {
        var col = advSanitizeColor(stateIn[ck]);
        if (col) stateOut[ck] = col;
      });
      var td = advPickEnum(stateIn.textDecoration, ADV_TEXT_DECORATION);
      if (td) stateOut.textDecoration = td;
      var iop = advSanitizeOpacity(stateIn.opacity);
      if (iop !== '') stateOut.opacity = iop;
      if (Object.keys(stateOut).length) out[state] = stateOut;
    });

    return out;
  }

  function hasInteractionStyles(advanced) {
    var adv = normalizeAdvanced(advanced);
    return !!(adv.hover && Object.keys(adv.hover).length) || !!(adv.visited && Object.keys(adv.visited).length);
  }

  function interactionClassName(blockId, advanced) {
    var adv = normalizeAdvanced(advanced);
    if (!hasInteractionStyles(adv)) return '';
    var id = String(blockId || '').replace(/[^A-Za-z0-9_-]/g, '');
    if (!id) {
      id = 'x' + String(JSON.stringify({ hover: adv.hover || {}, visited: adv.visited || {} }).length);
    }
    if (id.length > 40) id = id.slice(0, 40);
    return 'ws-adv-h-' + id;
  }

  function interactionDeclarations(state) {
    state = state || {};
    var parts = [];
    if (state.color) parts.push('color:' + state.color);
    if (state.background) parts.push('background-color:' + state.background);
    if (state.borderColor) parts.push('border-color:' + state.borderColor);
    if (state.textDecoration) parts.push('text-decoration:' + state.textDecoration);
    if (state.opacity != null && state.opacity !== '') parts.push('opacity:' + state.opacity);
    return parts.join(';');
  }

  function toInteractionCss(className, advanced) {
    if (!/^ws-adv-h-[A-Za-z0-9_-]+$/.test(className)) return '';
    var adv = normalizeAdvanced(advanced);
    var chunks = [];
    var hoverDecl = interactionDeclarations(adv.hover);
    var visitedDecl = interactionDeclarations(adv.visited);
    var hoverSel = '.' + className + ' a:hover,.' + className + ' .ws-btn:hover,.' + className + ' .dg-website-block__btn:hover';
    var visitedSel = '.' + className + ' a:visited,.' + className + ' .ws-btn:visited,.' + className + ' .dg-website-block__btn:visited';
    if (hoverDecl) chunks.push(hoverSel + '{' + hoverDecl + '}');
    if (visitedDecl) chunks.push(visitedSel + '{' + visitedDecl + '}');
    return chunks.join('');
  }

  function collectInteractionCss(layout) {
    var css = '';
    (layout.rows || []).forEach(function (row) {
      (row.columns || []).forEach(function (col) {
        (col.blocks || []).forEach(function (block) {
          if (!block || (block.type !== 'button' && block.type !== 'text' && block.type !== 'heading')) return;
          var cls = interactionClassName(block.id, block.advanced);
          if (cls) css += toInteractionCss(cls, block.advanced);
        });
      });
    });
    return css;
  }

  function advancedIsActive(advanced) {
    return Object.keys(normalizeAdvanced(advanced)).length > 0;
  }

  function advancedToInlineCss(advanced) {
    var adv = normalizeAdvanced(advanced);
    var parts = [];
    var display = adv.display || {};
    if (display.display) parts.push('display:' + display.display);
    if (display.visibility) parts.push('visibility:' + display.visibility);
    if (display.opacity != null && display.opacity !== '') parts.push('opacity:' + display.opacity);
    if (display.width) parts.push('width:' + display.width);
    if (display.maxWidth) parts.push('max-width:' + display.maxWidth);
    if (display.margin) parts.push('margin:' + display.margin);
    if (display.padding) parts.push('padding:' + display.padding);
    if (display.textAlign) parts.push('text-align:' + display.textAlign);
    var colors = adv.colors || {};
    if (colors.color) parts.push('color:' + colors.color);
    if (colors.background) parts.push('background-color:' + colors.background);
    if (colors.backgroundImage) parts.push('background-image:url("' + colors.backgroundImage + '")');
    if (colors.backgroundSize) parts.push('background-size:' + colors.backgroundSize);
    if (colors.backgroundPosition) parts.push('background-position:' + colors.backgroundPosition);
    if (colors.backgroundRepeat) parts.push('background-repeat:' + colors.backgroundRepeat);
    var border = adv.border || {};
    var sides = ['top', 'right', 'bottom', 'left'];
    var hasSide = false;
    sides.forEach(function (side) {
      var sw = border[side + 'Width'] || '';
      var ss = border[side + 'Style'] || '';
      var sc = border[side + 'Color'] || '';
      if (!sw && !ss && !sc) return;
      hasSide = true;
      if (ss === 'none') {
        parts.push('border-' + side + ':none');
        return;
      }
      parts.push(
        'border-' + side + ':' +
          (sw || '1px') + ' ' +
          (ss || 'solid') + ' ' +
          (sc || '#000000')
      );
    });
    if (!hasSide) {
      if (border.style === 'none') {
        parts.push('border:none');
      } else if (border.width || border.style || border.color) {
        parts.push(
          'border:' +
            (border.width || '1px') + ' ' +
            (border.style || 'solid') + ' ' +
            (border.color || '#000000')
        );
      }
    }
    var corners = [border.radiusTL || '', border.radiusTR || '', border.radiusBR || '', border.radiusBL || ''];
    var hasCorner = corners.some(function (c) { return !!c; });
    if (hasCorner) {
      parts.push(
        'border-radius:' +
          (corners[0] || '0') + ' ' +
          (corners[1] || '0') + ' ' +
          (corners[2] || '0') + ' ' +
          (corners[3] || '0')
      );
    } else if (border.radius) {
      parts.push('border-radius:' + border.radius);
    }
    return parts.join(';');
  }

  function advancedExtraClass(advanced, blockId) {
    var adv = normalizeAdvanced(advanced);
    var parts = [];
    if (adv.attrs && adv.attrs.className) parts.push(adv.attrs.className);
    var ix = interactionClassName(blockId || '', adv);
    if (ix) parts.push(ix);
    return parts.join(' ');
  }

  function advancedAttrHtml(advanced) {
    var adv = normalizeAdvanced(advanced);
    var attrs = adv.attrs || {};
    var html = '';
    if (attrs.id) html += ' id="' + escapeHtml(attrs.id) + '"';
    if (attrs.title) html += ' title="' + escapeHtml(attrs.title) + '"';
    if (attrs.ariaLabel) html += ' aria-label="' + escapeHtml(attrs.ariaLabel) + '"';
    return html;
  }

  function getAdvancedValue(advanced, group, key) {
    var adv = advanced && typeof advanced === 'object' ? advanced : {};
    var g = adv[group] && typeof adv[group] === 'object' ? adv[group] : {};
    return g[key] != null ? String(g[key]) : '';
  }

  function setAdvancedValue(block, group, key, value) {
    if (!block.advanced || typeof block.advanced !== 'object') {
      block.advanced = {};
    }
    if (!block.advanced[group] || typeof block.advanced[group] !== 'object') {
      block.advanced[group] = {};
    }
    var trimmed = String(value == null ? '' : value).trim();
    if (trimmed === '') {
      delete block.advanced[group][key];
      if (!Object.keys(block.advanced[group]).length) {
        delete block.advanced[group];
      }
    } else {
      block.advanced[group][key] = trimmed;
    }
    block.advanced = normalizeAdvanced(block.advanced);
    if (!Object.keys(block.advanced).length) {
      delete block.advanced;
    }
  }

  var advancedPopoverEl = null;
  var advancedPopoverTab = 'display';

  function closeAdvancedPopover() {
    if (!advancedPopoverEl) return;
    advancedPopoverEl.hidden = true;
    advancedPopoverEl.setAttribute('aria-hidden', 'true');
  }

  function ensureAdvancedPopover() {
    if (advancedPopoverEl) return advancedPopoverEl;
    advancedPopoverEl = document.createElement('div');
    advancedPopoverEl.id = 'dg-website-advanced-popover';
    advancedPopoverEl.className = 'dg-website-advanced-popover';
    advancedPopoverEl.hidden = true;
    advancedPopoverEl.setAttribute('role', 'dialog');
    advancedPopoverEl.setAttribute('aria-modal', 'false');
    advancedPopoverEl.setAttribute('aria-label', 'Erweiterte Einstellungen');
    advancedPopoverEl.setAttribute('aria-hidden', 'true');
    document.body.appendChild(advancedPopoverEl);

    advancedPopoverEl.addEventListener('click', function (event) {
      var tabBtn = event.target.closest('[data-adv-tab]');
      if (tabBtn) {
        advancedPopoverTab = tabBtn.getAttribute('data-adv-tab') || 'display';
        refreshAdvancedPopoverContent();
        return;
      }
      if (event.target.closest('[data-adv-close]')) {
        closeAdvancedPopover();
        return;
      }
      var mediaBtn = event.target.closest('[data-media-pick]');
      if (mediaBtn) {
        event.preventDefault();
        openMediaPicker(mediaBtn.getAttribute('data-media-pick') || 'src');
        return;
      }
      if (event.target.closest('[data-adv-clear-bg-image]') && selected.blockId) {
        event.preventDefault();
        var layout = parseLayout();
        var block = findBlock(layout, selected.blockId);
        if (!block) return;
        setAdvancedValue(block, 'colors', 'backgroundImage', '');
        persist(layout);
        render({ keepInspector: true });
        refreshAdvancedPopoverContent();
        syncAdvancedLivePreview(block);
      }
    });

    advancedPopoverEl.addEventListener('input', function (event) {
      var field = event.target.closest('[data-adv-group][data-adv-key]');
      if (!field || !selected.blockId) return;
      var layout = parseLayout();
      var block = findBlock(layout, selected.blockId);
      if (!block) return;
      var group = field.getAttribute('data-adv-group');
      var key = field.getAttribute('data-adv-key');
      setAdvancedValue(block, group, key, field.value);
      persist(layout);
      render({ keepInspector: true });
      updateAdvancedPreviewSwatch(block);
      updateAdvancedButtonBadge();
      syncAdvancedLivePreview(block);
      var stored = getAdvancedValue(block.advanced, group, key);
      advancedPopoverEl.querySelectorAll('[data-adv-group="' + group + '"][data-adv-key="' + key + '"]').forEach(function (el) {
        if (el === field) return;
        if (el.type === 'color') {
          el.value = stored || (key === 'background' ? '#ffffff' : '#333333');
        } else {
          el.value = stored;
        }
      });
    });

    advancedPopoverEl.addEventListener('change', function (event) {
      var field = event.target.closest('[data-adv-group][data-adv-key]');
      if (!field || !selected.blockId) return;
      var layout = parseLayout();
      var block = findBlock(layout, selected.blockId);
      if (!block) return;
      var group = field.getAttribute('data-adv-group');
      var key = field.getAttribute('data-adv-key');
      setAdvancedValue(block, group, key, field.value);
      persist(layout);
      render({ keepInspector: true });
      updateAdvancedPreviewSwatch(block);
      updateAdvancedButtonBadge();
      syncAdvancedLivePreview(block);
      var stored = getAdvancedValue(block.advanced, group, key);
      advancedPopoverEl.querySelectorAll('[data-adv-group="' + group + '"][data-adv-key="' + key + '"]').forEach(function (el) {
        if (el === field) return;
        if (el.type === 'color') {
          el.value = stored || (key === 'background' ? '#ffffff' : '#333333');
        } else {
          el.value = stored;
        }
      });
    });

    window.addEventListener('message', function (event) {
      if (event.origin !== window.location.origin) return;
      var data = event.data;
      if (!data || data.source !== 'dg-website-preview-frame') return;
      if (!advancedPopoverEl || advancedPopoverEl.hidden) return;
      var frame = advancedPopoverEl.querySelector('[data-adv-live-frame]');
      var status = advancedPopoverEl.querySelector('[data-adv-live-status]');
      if (data.type === 'advanced-preview-ready' && frame) {
        frame.setAttribute('data-ready', '1');
        if (selected.blockId) {
          var layout = parseLayout();
          var block = findBlock(layout, selected.blockId);
          if (block) syncAdvancedLivePreview(block);
        }
        return;
      }
      if (data.type === 'advanced-preview-result' && status) {
        if (data.found) {
          status.textContent = 'Nur gewählter Block (echte Website-Styles).';
        } else {
          status.textContent = 'Block in gespeicherter Vorschau nicht gefunden — Seite speichern.';
        }
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') closeAdvancedPopover();
    });

    document.addEventListener('mousedown', function (event) {
      if (!advancedPopoverEl || advancedPopoverEl.hidden) return;
      if (advancedPopoverEl.contains(event.target)) return;
      if (event.target.closest('[data-open-advanced]')) return;
      closeAdvancedPopover();
    });

    return advancedPopoverEl;
  }

  function advSelectHtml(group, key, value, options) {
    var html = '<label class="dg-field"><span>' + escapeHtml(options.label) + '</span><select data-adv-group="' +
      escapeHtml(group) + '" data-adv-key="' + escapeHtml(key) + '">';
    (options.choices || []).forEach(function (choice) {
      html += '<option value="' + escapeHtml(choice.value) + '"' +
        (String(value) === String(choice.value) ? ' selected' : '') + '>' +
        escapeHtml(choice.label) + '</option>';
    });
    html += '</select></label>';
    return html;
  }

  function advInputHtml(group, key, value, options) {
    options = options || {};
    return '<label class="dg-field"><span>' + escapeHtml(options.label || key) + '</span>' +
      '<input data-adv-group="' + escapeHtml(group) + '" data-adv-key="' + escapeHtml(key) + '"' +
      (options.type ? ' type="' + escapeHtml(options.type) + '"' : '') +
      (options.placeholder ? ' placeholder="' + escapeHtml(options.placeholder) + '"' : '') +
      (options.min != null ? ' min="' + escapeHtml(String(options.min)) + '"' : '') +
      (options.max != null ? ' max="' + escapeHtml(String(options.max)) + '"' : '') +
      (options.step != null ? ' step="' + escapeHtml(String(options.step)) + '"' : '') +
      ' value="' + escapeHtml(value || '') + '">' +
      (options.hint ? '<small class="dg-field-hint">' + escapeHtml(options.hint) + '</small>' : '') +
      '</label>';
  }

  function updateAdvancedPreviewSwatch(block) {
    if (!advancedPopoverEl) return;
    var swatch = advancedPopoverEl.querySelector('[data-adv-swatch]');
    if (!swatch) return;
    var css = advancedToInlineCss(block && block.advanced);
    swatch.setAttribute('style', css || 'background:#fff;border:1px dashed #ccc;');
    swatch.textContent = advancedIsActive(block && block.advanced) ? 'Stil' : 'Standard';
  }

  function getAdvancedPreviewUrl() {
    var slug = '';
    if (slugInput && slugInput.value) {
      slug = sanitizeSlug(slugInput.value);
    } else if (previewLink) {
      var path = previewLink.getAttribute('data-preview-path') || previewLink.getAttribute('href') || '';
      var m = path.match(/\/vorschau\/([a-z0-9-]+)/);
      if (m) slug = m[1];
    }
    if (!slug) return '';
    return '/vorschau/' + encodeURIComponent(slug) + '?frame=1';
  }

  function buildAdvancedPreviewPayload(block) {
    var adv = normalizeAdvanced(block && block.advanced);
    var attrs = adv.attrs || {};
    return {
      source: 'dg-website-builder',
      type: 'advanced-preview',
      blockId: block && block.id ? String(block.id) : '',
      css: advancedToInlineCss(adv),
      className: (attrs.className || ''),
      attrs: {
        id: attrs.id || '',
        title: attrs.title || '',
        ariaLabel: attrs.ariaLabel || '',
      },
    };
  }

  function syncAdvancedLivePreview(block) {
    if (!advancedPopoverEl) return;
    var frame = advancedPopoverEl.querySelector('[data-adv-live-frame]');
    var status = advancedPopoverEl.querySelector('[data-adv-live-status]');
    var url = getAdvancedPreviewUrl();
    if (!url) {
      if (status) {
        status.textContent = 'Keine Vorschau-URL — bitte Slug setzen und Seite speichern.';
        status.hidden = false;
      }
      return;
    }
    if (!frame) return;
    if (!block || !block.id) return;

    var send = function () {
      try {
        frame.contentWindow.postMessage(buildAdvancedPreviewPayload(block), window.location.origin);
      } catch (e) { /* ignore */ }
    };

    if (frame.getAttribute('data-ready') === '1') {
      send();
      return;
    }
    // Frame noch nicht bereit: nach load senden
    frame.addEventListener('load', function onLoad() {
      frame.removeEventListener('load', onLoad);
      frame.setAttribute('data-ready', '1');
      // Bridge meldet ready — zusätzlich kurz verzögert syncen
      setTimeout(send, 50);
    }, { once: true });
  }

  function ensureAdvancedLivePreview(block) {
    if (!advancedPopoverEl) return;
    var wrap = advancedPopoverEl.querySelector('[data-adv-live-wrap]');
    if (!wrap) return;
    var url = getAdvancedPreviewUrl();
    var status = wrap.querySelector('[data-adv-live-status]');
    var frame = wrap.querySelector('[data-adv-live-frame]');
    var openLink = wrap.querySelector('[data-adv-open-preview]');

    if (!url) {
      if (frame) frame.hidden = true;
      if (status) {
        status.hidden = false;
        status.textContent = 'Echte Website-Vorschau erst nach gesetztem Slug und Speichern.';
      }
      if (openLink) openLink.hidden = true;
      return;
    }

    if (openLink) {
      openLink.hidden = false;
      openLink.setAttribute('href', url.replace('?frame=1', '').replace('&frame=1', ''));
    }

    if (!frame) {
      frame = document.createElement('iframe');
      frame.className = 'dg-website-advanced-popover__frame';
      frame.setAttribute('data-adv-live-frame', '1');
      frame.setAttribute('title', 'Echte Website-Vorschau');
      frame.setAttribute('loading', 'lazy');
      wrap.appendChild(frame);
    }

    frame.hidden = false;
    if (status) {
      status.hidden = false;
      status.textContent = 'Nur gewählter Block — Live-Styles (Texte/neue Blöcke erst nach Speichern).';
    }

    var currentSrc = frame.getAttribute('src') || '';
    if (currentSrc !== url) {
      frame.removeAttribute('data-ready');
      frame.setAttribute('src', url);
    }
    syncAdvancedLivePreview(block);
  }

  function updateAdvancedButtonBadge() {
    if (!inspector) return;
    var btn = inspector.querySelector('[data-open-advanced]');
    if (!btn || !selected.blockId) return;
    var layout = parseLayout();
    var block = findBlock(layout, selected.blockId);
    var badge = btn.querySelector('[data-adv-badge]');
    if (!badge) return;
    badge.hidden = !(block && advancedIsActive(block.advanced));
  }

  function refreshAdvancedPopoverContent() {
    if (!advancedPopoverEl || !selected.blockId) return;
    var layout = parseLayout();
    var block = findBlock(layout, selected.blockId);
    if (!block) {
      closeAdvancedPopover();
      return;
    }
    var adv = block.advanced || {};
    var tab = advancedPopoverTab;
    var tabs = [
      { id: 'display', label: 'Anzeige' },
      { id: 'colors', label: 'Farben' },
      { id: 'border', label: 'Rahmen' },
      { id: 'attrs', label: 'Attribute' },
    ];
    var blockType = block && block.type;
    var showHoverTab = blockType === 'button' || blockType === 'text' || blockType === 'heading';
    if (showHoverTab) {
      tabs.push({ id: 'hover', label: 'Hover' });
    }
    if (tab === 'hover' && !showHoverTab) {
      tab = 'display';
      advancedPopoverTab = 'display';
    }

    if (!advancedPopoverEl.querySelector('[data-adv-shell]')) {
      advancedPopoverEl.innerHTML =
        '<div data-adv-shell="1">' +
          '<div class="dg-website-advanced-popover__head">' +
            '<strong>Erweiterte Einstellungen</strong>' +
            '<button type="button" class="dg-website-advanced-popover__close" data-adv-close aria-label="Schließen">&times;</button>' +
          '</div>' +
          '<div class="dg-website-advanced-popover__tabs" role="tablist" data-adv-tabs></div>' +
          '<div class="dg-website-advanced-popover__preview">' +
            '<div class="dg-website-advanced-popover__swatch" data-adv-swatch>Stil</div>' +
            '<div class="dg-website-advanced-popover__live" data-adv-live-wrap>' +
              '<p class="dg-field-hint" data-adv-live-status></p>' +
              '<a class="dg-website-advanced-popover__open" data-adv-open-preview href="#" target="_blank" rel="noopener">Vorschau in neuem Tab</a>' +
            '</div>' +
          '</div>' +
          '<div class="dg-website-advanced-popover__body" data-adv-body></div>' +
        '</div>';
    }

    var tabsEl = advancedPopoverEl.querySelector('[data-adv-tabs]');
    var bodyEl = advancedPopoverEl.querySelector('[data-adv-body]');
    if (tabsEl) {
      var tabsHtml = '';
      tabs.forEach(function (t) {
        tabsHtml += '<button type="button" role="tab" class="dg-website-advanced-popover__tab' +
          (t.id === tab ? ' is-active' : '') + '" data-adv-tab="' + escapeHtml(t.id) + '"' +
          ' aria-selected="' + (t.id === tab ? 'true' : 'false') + '">' + escapeHtml(t.label) + '</button>';
      });
      tabsEl.innerHTML = tabsHtml;
    }

    var html = '';
    if (tab === 'display') {
      html += advSelectHtml('display', 'display', getAdvancedValue(adv, 'display', 'display'), {
        label: 'Display',
        choices: [
          { value: '', label: '— Standard —' },
          { value: 'block', label: 'block' },
          { value: 'inline', label: 'inline' },
          { value: 'inline-block', label: 'inline-block' },
          { value: 'flex', label: 'flex' },
          { value: 'none', label: 'none (verstecken)' },
        ],
      });
      html += advSelectHtml('display', 'visibility', getAdvancedValue(adv, 'display', 'visibility'), {
        label: 'Sichtbarkeit',
        choices: [
          { value: '', label: '— Standard —' },
          { value: 'visible', label: 'sichtbar' },
          { value: 'hidden', label: 'versteckt' },
        ],
      });
      html += advInputHtml('display', 'opacity', getAdvancedValue(adv, 'display', 'opacity'), {
        label: 'Deckkraft (0–1)', type: 'number', min: '0', max: '1', step: '0.05', placeholder: '1',
      });
      html += advInputHtml('display', 'width', getAdvancedValue(adv, 'display', 'width'), {
        label: 'Breite', placeholder: 'z. B. 100% oder 320px', hint: 'Einheiten: px, %, rem, em, auto',
      });
      html += advInputHtml('display', 'maxWidth', getAdvancedValue(adv, 'display', 'maxWidth'), {
        label: 'Max. Breite', placeholder: 'z. B. 720px',
      });
      html += advInputHtml('display', 'margin', getAdvancedValue(adv, 'display', 'margin'), {
        label: 'Außenabstand', placeholder: 'z. B. 16px oder 8px 0',
      });
      html += advInputHtml('display', 'padding', getAdvancedValue(adv, 'display', 'padding'), {
        label: 'Innenabstand', placeholder: 'z. B. 12px',
      });
      html += advSelectHtml('display', 'textAlign', getAdvancedValue(adv, 'display', 'textAlign'), {
        label: 'Textausrichtung',
        choices: [
          { value: '', label: '— Standard —' },
          { value: 'left', label: 'links' },
          { value: 'center', label: 'zentriert' },
          { value: 'right', label: 'rechts' },
          { value: 'justify', label: 'blocksatz' },
        ],
      });
    } else if (tab === 'colors') {
      html += advInputHtml('colors', 'color', getAdvancedValue(adv, 'colors', 'color') || '#333333', {
        label: 'Textfarbe', type: 'color',
      });
      html += advInputHtml('colors', 'color', getAdvancedValue(adv, 'colors', 'color'), {
        label: 'Textfarbe (Hex / RGBA)', placeholder: '#333 oder rgba(0,0,0,0.5)',
        hint: 'Hex (#rgb, #rrggbb, #rrggbbaa) oder rgba(r,g,b,a)',
      });
      html += advInputHtml('colors', 'background', getAdvancedValue(adv, 'colors', 'background') || '#ffffff', {
        label: 'Hintergrund', type: 'color',
      });
      html += advInputHtml('colors', 'background', getAdvancedValue(adv, 'colors', 'background'), {
        label: 'Hintergrund (Hex / RGBA)', placeholder: '#fff oder rgba(255,255,255,0.8)',
        hint: 'Hex oder rgba — benannte Farben (red) werden verworfen',
      });
      html += '<p class="dg-field-hint" style="margin-top:8px;font-weight:600;">Hintergrundbild</p>';
      html += advInputHtml('colors', 'backgroundImage', getAdvancedValue(adv, 'colors', 'backgroundImage'), {
        label: 'Bild-URL', placeholder: '/media/… oder https://…',
        hint: 'Nur /media/…, /app/media… oder http(s)-URL',
      });
      html += '<div class="dg-website-image-tools" style="margin-bottom:10px;">' +
        '<button type="button" class="dg-button" data-media-pick="adv.backgroundImage">Aus Mediathek</button>' +
        '<button type="button" class="dg-button" data-adv-clear-bg-image style="margin-left:6px;">Bild entfernen</button>' +
        '</div>';
      html += advSelectHtml('colors', 'backgroundSize', getAdvancedValue(adv, 'colors', 'backgroundSize'), {
        label: 'Größe',
        choices: [
          { value: '', label: '— Standard —' },
          { value: 'cover', label: 'cover (ausfüllen)' },
          { value: 'contain', label: 'contain (einpassen)' },
          { value: 'auto', label: 'auto' },
        ],
      });
      html += advInputHtml('colors', 'backgroundSize', getAdvancedValue(adv, 'colors', 'backgroundSize'), {
        label: 'Größe (frei)', placeholder: 'cover oder 100% 50%',
        hint: 'cover / contain / auto oder 1–2 Längen (px/%)',
      });
      html += advSelectHtml('colors', 'backgroundPosition', getAdvancedValue(adv, 'colors', 'backgroundPosition'), {
        label: 'Position',
        choices: [
          { value: '', label: '— Standard —' },
          { value: 'center', label: 'center' },
          { value: 'top', label: 'top' },
          { value: 'bottom', label: 'bottom' },
          { value: 'left', label: 'left' },
          { value: 'right', label: 'right' },
          { value: 'center top', label: 'center top' },
          { value: 'left top', label: 'left top' },
          { value: 'right bottom', label: 'right bottom' },
        ],
      });
      html += advSelectHtml('colors', 'backgroundRepeat', getAdvancedValue(adv, 'colors', 'backgroundRepeat'), {
        label: 'Wiederholen',
        choices: [
          { value: '', label: '— Standard —' },
          { value: 'no-repeat', label: 'no-repeat' },
          { value: 'repeat', label: 'repeat' },
          { value: 'repeat-x', label: 'repeat-x' },
          { value: 'repeat-y', label: 'repeat-y' },
        ],
      });
    } else if (tab === 'border') {
      var borderStyleChoices = [
        { value: '', label: '— Standard —' },
        { value: 'none', label: 'ohne' },
        { value: 'solid', label: 'durchgezogen' },
        { value: 'dashed', label: 'gestrichelt' },
        { value: 'dotted', label: 'gepunktet' },
      ];
      html += '<p class="dg-field-hint">Alle Seiten (wird ignoriert, wenn Einzel-Seiten gesetzt sind)</p>';
      html += advInputHtml('border', 'width', getAdvancedValue(adv, 'border', 'width'), {
        label: 'Stärke alle', placeholder: '1px',
      });
      html += advSelectHtml('border', 'style', getAdvancedValue(adv, 'border', 'style'), {
        label: 'Stil alle', choices: borderStyleChoices,
      });
      html += advInputHtml('border', 'color', getAdvancedValue(adv, 'border', 'color'), {
        label: 'Farbe alle (Hex / RGBA)', placeholder: '#ccc oder rgba(0,0,0,0.2)',
      });
      html += advInputHtml('border', 'radius', getAdvancedValue(adv, 'border', 'radius'), {
        label: 'Radius alle / Shorthand', placeholder: '8px oder 8px 4px',
        hint: '1–4 Längen (oben-links → oben-rechts → unten-rechts → unten-links)',
      });
      html += '<p class="dg-field-hint" style="margin-top:10px;">Radius je Ecke (überschreibt „Radius alle“, wenn gesetzt)</p>';
      html += advInputHtml('border', 'radiusTL', getAdvancedValue(adv, 'border', 'radiusTL'), {
        label: 'Radius oben links', placeholder: '8px',
      });
      html += advInputHtml('border', 'radiusTR', getAdvancedValue(adv, 'border', 'radiusTR'), {
        label: 'Radius oben rechts', placeholder: '8px',
      });
      html += advInputHtml('border', 'radiusBR', getAdvancedValue(adv, 'border', 'radiusBR'), {
        label: 'Radius unten rechts', placeholder: '8px',
      });
      html += advInputHtml('border', 'radiusBL', getAdvancedValue(adv, 'border', 'radiusBL'), {
        label: 'Radius unten links', placeholder: '8px',
      });
      [
        { side: 'top', label: 'Oben' },
        { side: 'right', label: 'Rechts' },
        { side: 'bottom', label: 'Unten' },
        { side: 'left', label: 'Links' },
      ].forEach(function (sideDef) {
        html += '<p class="dg-field-hint" style="margin-top:10px;font-weight:600;">Seite ' + escapeHtml(sideDef.label) + '</p>';
        html += advInputHtml('border', sideDef.side + 'Width', getAdvancedValue(adv, 'border', sideDef.side + 'Width'), {
          label: 'Stärke ' + sideDef.label, placeholder: '1px',
        });
        html += advSelectHtml('border', sideDef.side + 'Style', getAdvancedValue(adv, 'border', sideDef.side + 'Style'), {
          label: 'Stil ' + sideDef.label, choices: borderStyleChoices,
        });
        html += advInputHtml('border', sideDef.side + 'Color', getAdvancedValue(adv, 'border', sideDef.side + 'Color'), {
          label: 'Farbe ' + sideDef.label, placeholder: '#ccc oder rgba(…)',
        });
      });
    } else if (tab === 'hover') {
      var decoChoices = [
        { value: '', label: '— Standard —' },
        { value: 'none', label: 'keine' },
        { value: 'underline', label: 'unterstrichen' },
        { value: 'line-through', label: 'durchgestrichen' },
        { value: 'overline', label: 'überstrichen' },
      ];
      html += '<p class="dg-field-hint">Wirkt auf Buttons und Links im Block (nicht auf den ganzen Wrapper).</p>';
      html += '<p class="dg-field-hint" style="margin-top:8px;font-weight:600;">Hover</p>';
      html += advInputHtml('hover', 'color', getAdvancedValue(adv, 'hover', 'color') || '#0066cc', {
        label: 'Textfarbe', type: 'color',
      });
      html += advInputHtml('hover', 'color', getAdvancedValue(adv, 'hover', 'color'), {
        label: 'Textfarbe (Hex / RGBA)', placeholder: '#06c oder rgba(…)',
      });
      html += advInputHtml('hover', 'background', getAdvancedValue(adv, 'hover', 'background') || '#ffffff', {
        label: 'Hintergrund', type: 'color',
      });
      html += advInputHtml('hover', 'background', getAdvancedValue(adv, 'hover', 'background'), {
        label: 'Hintergrund (Hex / RGBA)', placeholder: '#fff oder rgba(…)',
      });
      html += advInputHtml('hover', 'borderColor', getAdvancedValue(adv, 'hover', 'borderColor'), {
        label: 'Rahmenfarbe', placeholder: '#ccc oder rgba(…)',
      });
      html += advSelectHtml('hover', 'textDecoration', getAdvancedValue(adv, 'hover', 'textDecoration'), {
        label: 'Textverzierung', choices: decoChoices,
      });
      html += advInputHtml('hover', 'opacity', getAdvancedValue(adv, 'hover', 'opacity'), {
        label: 'Deckkraft (0–1)', type: 'number', min: '0', max: '1', step: '0.05', placeholder: '1',
      });
      html += '<p class="dg-field-hint" style="margin-top:12px;font-weight:600;">Visited (besuchter Link)</p>';
      html += advInputHtml('visited', 'color', getAdvancedValue(adv, 'visited', 'color') || '#551a8b', {
        label: 'Textfarbe', type: 'color',
      });
      html += advInputHtml('visited', 'color', getAdvancedValue(adv, 'visited', 'color'), {
        label: 'Textfarbe (Hex / RGBA)', placeholder: '#551a8b',
      });
      html += advInputHtml('visited', 'background', getAdvancedValue(adv, 'visited', 'background'), {
        label: 'Hintergrund (Hex / RGBA)', placeholder: '#fff oder rgba(…)',
      });
      html += advInputHtml('visited', 'borderColor', getAdvancedValue(adv, 'visited', 'borderColor'), {
        label: 'Rahmenfarbe', placeholder: '#ccc',
      });
      html += advSelectHtml('visited', 'textDecoration', getAdvancedValue(adv, 'visited', 'textDecoration'), {
        label: 'Textverzierung', choices: decoChoices,
      });
      html += advInputHtml('visited', 'opacity', getAdvancedValue(adv, 'visited', 'opacity'), {
        label: 'Deckkraft (0–1)', type: 'number', min: '0', max: '1', step: '0.05', placeholder: '1',
      });
    } else {
      html += advInputHtml('attrs', 'id', getAdvancedValue(adv, 'attrs', 'id'), {
        label: 'HTML-ID', placeholder: 'mein-block', hint: 'Buchstabe am Anfang, dann Buchstaben/Zahlen/_/-/:',
      });
      html += advInputHtml('attrs', 'className', getAdvancedValue(adv, 'attrs', 'className'), {
        label: 'CSS-Klassen', placeholder: 'mein-block highlight', hint: 'Nur sichere Klassen-Namen, leerzeichengetrennt',
      });
      html += advInputHtml('attrs', 'title', getAdvancedValue(adv, 'attrs', 'title'), {
        label: 'title', placeholder: 'Hinweistext',
      });
      html += advInputHtml('attrs', 'ariaLabel', getAdvancedValue(adv, 'attrs', 'ariaLabel'), {
        label: 'aria-label', placeholder: 'Barrierefreier Name',
      });
    }

    if (bodyEl) bodyEl.innerHTML = html;
    updateAdvancedPreviewSwatch(block);
    ensureAdvancedLivePreview(block);
  }

  function openAdvancedPopover(anchor) {
    var pop = ensureAdvancedPopover();
    advancedPopoverTab = 'display';
    refreshAdvancedPopoverContent();
    pop.hidden = false;
    pop.setAttribute('aria-hidden', 'false');
    var rect = anchor.getBoundingClientRect();
    var width = Math.min(380, window.innerWidth - 24);
    var left = Math.min(window.innerWidth - width - 12, Math.max(12, rect.left + rect.width - width));
    var top = rect.bottom + 8;
    if (top + 460 > window.innerHeight) {
      top = Math.max(12, rect.top - 8 - Math.min(460, window.innerHeight - 24));
    }
    pop.style.width = width + 'px';
    pop.style.left = left + 'px';
    pop.style.top = top + 'px';
  }

  function advancedButtonHtml(block) {
    var active = advancedIsActive(block && block.advanced);
    return '<div class="dg-form-actions dg-website-inspector-actions" style="margin-top:4px;padding-top:8px;">' +
      '<button type="button" class="dg-button" data-open-advanced>' +
      'Erweiterte Einstellungen' +
      '<span class="dg-website-advanced-badge" data-adv-badge' + (active ? '' : ' hidden') + '>aktiv</span>' +
      '</button></div>';
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
    var extraClass = advancedExtraClass(block.advanced, block.id);
    if (extraClass) selectedClass += ' ' + extraClass;
    var styleCss = advancedToInlineCss(block.advanced);
    var attrHtml = advancedAttrHtml(block.advanced);
    var html = '<div class="dg-website-block' + selectedClass + '" data-block-id="' + escapeHtml(block.id) + '"' +
      (styleCss ? ' style="' + escapeHtml(styleCss) + '"' : '') +
      attrHtml + '>';

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
        html += '<p class="dg-website-block__heading dg-website-block__heading--' + level + '">' +
          (renderInlineMarkupHtml(block.text) || '<span class="dg-field-hint">Überschrift</span>') + '</p>';
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
        if ((block.label || '').trim()) {
          html += '<p class="dg-website-block__video-label" style="font-weight:600;margin:0 0 6px;">' + escapeHtml(block.label) + '</p>';
        }
        var vUrl = String(block.url || '').trim();
        var isMp4 = /\.mp4(\?|$)/i.test(vUrl) || vUrl.indexOf('/media/') === 0 || vUrl.indexOf('/app/media') === 0;
        if (isMp4 && vUrl) {
          html += '<div class="dg-website-block__video"><video controls playsinline style="width:100%;max-width:100%;border-radius:6px;background:#111;" src="' + escapeHtml(vUrl) + '"></video></div>';
        } else {
          var embed = embedUrl(block.url);
          html += embed
            ? '<div class="dg-website-block__video"><iframe src="' + escapeHtml(embed) + '" frameborder="0" allowfullscreen style="width:100%;aspect-ratio:16/9;border-radius:6px;"></iframe></div>'
            : '<div class="dg-website-block__image-placeholder">Video — aus Videobibliothek wählen oder URL eintragen</div>';
        }
        if ((block.caption || '').trim()) {
          html += '<p class="dg-field-hint" style="margin-top:6px;">' + escapeHtml(block.caption) + '</p>';
        }
        break;
      case 'online_booking':
        html += '<div class="dg-website-block__booking-preview" style="padding:16px;background:#f0f4f8;border:2px dashed #94a3b8;border-radius:8px;text-align:center;">'
          + '<strong>Online-Terminbuchung</strong><br>'
          + '<span style="color:#64748b;font-size:0.9em;">Leistung · Termin · Kontakt — live auf der Website</span></div>';
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
        var textHtml = renderInlineMarkupHtml(block.text) || '<span class="dg-field-hint">Text</span>';
        if (block.quote) {
          html += '<blockquote class="dg-website-block__quote">' + textHtml + '</blockquote>';
        } else {
          html += '<p class="dg-website-block__text">' + textHtml + '</p>';
        }
    }
    return html + '</div>';
  }

  function render(options) {
    var layout = parseLayout();
    if (!layout.rows.length) {
      canvas.innerHTML = '<div class="dg-website-canvas-empty">Noch keine Zeile. Links unter „Zeile“ eine Spaltenanzahl wählen — oder hier starten.</div>';
    } else {
      var ixCss = collectInteractionCss(layout);
      canvas.innerHTML = (ixCss ? '<style data-ws-adv-ix>' + ixCss + '</style>' : '') + layout.rows.map(function (row, rowIndex) {
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

  /** Kuratierte Zeichen für Inspector (W3) — eingefügt als Unicode, nicht als Entity-Markup. */
  var CHAR_PICKER_GROUPS = [
    {
      id: 'symbols',
      label: 'HTML-Symbole',
      chars: [
        '©', '®', '™', '€', '£', '¥', '§', '¶', '†', '‡', '•', '…', '–', '—',
        '′', '″', '°', '±', '×', '÷', '≠', '≤', '≥', '∞', '≈', '√', '∑',
        '←', '→', '↑', '↓', '↔', '★', '☆', '♥', '♠', '♣', '♦', '✓', '✗',
      ],
    },
    {
      id: 'entities',
      label: 'Sonderzeichen',
      chars: [
        '«', '»', '‹', '›', '„', '“', '”', '‘', '’', '‚', '‛',
        '¡', '¿', '¢', '¤', '¦', '¨', '´', '¸', '¯', '¬',
        'ª', 'º', 'æ', 'Æ', 'œ', 'Œ', 'ß', 'ø', 'Ø', 'å', 'Å',
      ],
    },
    {
      id: 'emoji',
      label: 'Emojis',
      chars: [
        '😀', '😊', '🙂', '😉', '👍', '👋', '🙏', '💪',
        '✅', '❌', '⚠️', '❗', '❓', 'ℹ️', '💡', '📌',
        '📞', '✉️', '📍', '🏠', '⭐', '❤️', '🔥', '🎉', '🚀', '🛒',
      ],
    },
  ];

  var charPickerState = { fieldName: 'text', start: 0, end: 0 };

  function charPickerToolbarHtml(fieldName) {
    var html = '<div class="dg-website-char-picker" data-char-picker-for="' + escapeHtml(fieldName) + '">' +
      '<button type="button" class="dg-button dg-website-char-picker__btn" data-char-picker-toggle title="Zeichen einfügen" aria-expanded="false" aria-haspopup="true">Ω</button>' +
      '<div class="dg-website-char-picker__menu" data-char-picker-menu hidden>';
    CHAR_PICKER_GROUPS.forEach(function (group) {
      html += '<div class="dg-website-char-picker__group">' +
        '<div class="dg-website-char-picker__group-label">' + escapeHtml(group.label) + '</div>' +
        '<div class="dg-website-char-picker__grid">';
      group.chars.forEach(function (ch) {
        html += '<button type="button" class="dg-website-char-picker__char" data-char-insert="' +
          escapeHtml(ch) + '" title="' + escapeHtml(ch) + '">' + escapeHtml(ch) + '</button>';
      });
      html += '</div></div>';
    });
    html += '</div></div>';
    return html;
  }

  function isSafeContentHref(url) {
    url = String(url || '').trim();
    if (!url) return false;
    if (/^https?:\/\//i.test(url)) return true;
    if (/^mailto:[^\s<>"']+$/i.test(url)) return true;
    if (url.charAt(0) === '/' && url.charAt(1) !== '/') return true;
    return false;
  }

  /** Canvas-Vorschau: gleiche Marker wie WebsiteContent (W4). */
  function renderInlineMarkupHtml(text) {
    text = String(text || '');
    if (!text) return '';
    var out = '';
    var re = /\*\*(.+?)\*\*|\[([^\[\]]+)\]\(([^)]+)\)/g;
    var last = 0;
    var m;
    while ((m = re.exec(text)) !== null) {
      if (m.index > last) out += escapeHtml(text.slice(last, m.index));
      if (m[1] != null) {
        out += '<strong>' + escapeHtml(m[1]) + '</strong>';
      } else if (m[2] != null) {
        var href = String(m[3] || '').trim();
        if (isSafeContentHref(href)) {
          out += '<a href="' + escapeHtml(href) + '">' + escapeHtml(m[2]) + '</a>';
        } else {
          out += escapeHtml(m[2]);
        }
      } else {
        out += escapeHtml(m[0]);
      }
      last = m.index + m[0].length;
    }
    if (last < text.length) out += escapeHtml(text.slice(last));
    return out;
  }

  function formatToolbarHtml(fieldName) {
    return '<div class="dg-website-format-toolbar" data-format-for="' + escapeHtml(fieldName) + '">' +
      '<button type="button" class="dg-button dg-website-format-toolbar__btn" data-format-bold title="Fett (**…**)" aria-label="Fett"><strong>B</strong></button>' +
      '<button type="button" class="dg-button dg-website-format-toolbar__btn" data-format-link title="Link ([Text](URL))" aria-label="Link">Link</button>' +
      '</div>';
  }

  function textFieldWithCharPicker(label, name, value, options) {
    options = options || {};
    var field = options.textarea
      ? textareaHtml(label, name, value, options.rows || 6)
      : fieldHtml(label, name, value, options.extra || '');
    return '<div class="dg-website-char-field">' +
      '<div class="dg-website-char-field__toolbar">' +
      '<span class="dg-website-char-field__toolbar-label">Format</span>' +
      formatToolbarHtml(name) +
      '<span class="dg-website-char-field__toolbar-label">Zeichen</span>' +
      charPickerToolbarHtml(name) +
      '</div>' +
      field +
      '</div>';
  }

  function wrapSelectionWithMarkers(fieldName, before, after, placeholder) {
    if (!inspector || !selected.blockId) return;
    var field = inspector.querySelector('input[name="' + fieldName + '"], textarea[name="' + fieldName + '"]');
    if (!field) return;
    rememberCharPickerSelection(field);
    var value = String(field.value || '');
    var start = charPickerState.start;
    var end = charPickerState.end;
    if (start < 0) start = 0;
    if (end < start) end = start;
    if (start > value.length) start = value.length;
    if (end > value.length) end = value.length;
    var selectedText = value.slice(start, end);
    if (!selectedText) selectedText = placeholder || '';
    var insert = before + selectedText + after;
    field.value = value.slice(0, start) + insert + value.slice(end);
    var selStart = start + before.length;
    var selEnd = selStart + selectedText.length;
    try {
      field.focus();
      field.setSelectionRange(selStart, selEnd);
    } catch (e) { /* ignore */ }
    charPickerState.fieldName = fieldName;
    charPickerState.start = selStart;
    charPickerState.end = selEnd;
    field.dispatchEvent(new Event('input', { bubbles: true }));
  }

  function applyFormatBold(fieldName) {
    wrapSelectionWithMarkers(fieldName, '**', '**', 'fett');
  }

  function applyFormatLink(fieldName) {
    if (!inspector || !selected.blockId) return;
    var field = inspector.querySelector('input[name="' + fieldName + '"], textarea[name="' + fieldName + '"]');
    if (!field) return;
    rememberCharPickerSelection(field);
    var url = window.prompt('Link-URL (https://, mailto: oder /pfad):', 'https://');
    if (url == null) return;
    url = String(url).trim();
    if (!isSafeContentHref(url)) {
      window.alert('Nur http(s)://, mailto: oder relative Pfade ab / sind erlaubt.');
      return;
    }
    var value = String(field.value || '');
    var start = charPickerState.start;
    var end = charPickerState.end;
    var label = value.slice(start, end) || 'Linktext';
    var insert = '[' + label + '](' + url + ')';
    field.value = value.slice(0, start) + insert + value.slice(end);
    var caret = start + insert.length;
    try {
      field.focus();
      field.setSelectionRange(caret, caret);
    } catch (e) { /* ignore */ }
    charPickerState.fieldName = fieldName;
    charPickerState.start = caret;
    charPickerState.end = caret;
    field.dispatchEvent(new Event('input', { bubbles: true }));
  }

  function rememberCharPickerSelection(field) {
    if (!field || !field.name) return;
    if (!(field.tagName === 'INPUT' || field.tagName === 'TEXTAREA')) return;
    charPickerState.fieldName = field.name;
    try {
      charPickerState.start = field.selectionStart != null ? field.selectionStart : field.value.length;
      charPickerState.end = field.selectionEnd != null ? field.selectionEnd : field.value.length;
    } catch (e) {
      charPickerState.start = field.value.length;
      charPickerState.end = field.value.length;
    }
  }

  function insertCharAtCursor(ch) {
    if (!inspector || !selected.blockId) return;
    var name = charPickerState.fieldName || 'text';
    var field = inspector.querySelector('input[name="' + name + '"], textarea[name="' + name + '"]');
    if (!field) return;
    var value = String(field.value || '');
    var start = charPickerState.start;
    var end = charPickerState.end;
    if (start < 0) start = 0;
    if (end < start) end = start;
    if (start > value.length) start = value.length;
    if (end > value.length) end = value.length;
    field.value = value.slice(0, start) + ch + value.slice(end);
    var caret = start + ch.length;
    try {
      field.focus();
      field.setSelectionRange(caret, caret);
    } catch (e) { /* ignore */ }
    charPickerState.start = caret;
    charPickerState.end = caret;
    field.dispatchEvent(new Event('input', { bubbles: true }));
  }

  function closeAllCharPickerMenus() {
    if (!inspector) return;
    inspector.querySelectorAll('[data-char-picker-menu]').forEach(function (menu) {
      menu.hidden = true;
    });
    inspector.querySelectorAll('[data-char-picker-toggle]').forEach(function (btn) {
      btn.setAttribute('aria-expanded', 'false');
    });
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

  function videoPickerHtml(name) {
    return '<div class="dg-website-image-tools">' +
      '<button type="button" class="dg-button" data-video-pick="' + escapeHtml(name) + '">Aus Videobibliothek</button>' +
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
    } else if (targetField === 'adv.backgroundImage') {
      setAdvancedValue(block, 'colors', 'backgroundImage', url);
      if (!getAdvancedValue(block.advanced, 'colors', 'backgroundSize')) {
        setAdvancedValue(block, 'colors', 'backgroundSize', 'cover');
      }
      if (!getAdvancedValue(block.advanced, 'colors', 'backgroundRepeat')) {
        setAdvancedValue(block, 'colors', 'backgroundRepeat', 'no-repeat');
      }
      if (!getAdvancedValue(block.advanced, 'colors', 'backgroundPosition')) {
        setAdvancedValue(block, 'colors', 'backgroundPosition', 'center');
      }
    } else {
      block[targetField] = url;
      if (targetField === 'src' && alt && !(block.alt || '').trim()) {
        block.alt = alt;
      }
    }
    persist(layout);
    if (targetField === 'adv.backgroundImage') {
      render({ keepInspector: true });
      if (advancedPopoverEl && !advancedPopoverEl.hidden) {
        refreshAdvancedPopoverContent();
        syncAdvancedLivePreview(block);
      }
    } else {
      render();
    }
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
          '<a class="dg-button" href="/app?page=bilder" target="_blank" rel="noopener">Zur Media-Bibliothek</a>' +
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
  var videoPickerTarget = '';
  var videoPickerCache = null;

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
      body.innerHTML = '<p class="dg-field-hint">Keine Bilder gefunden. Laden Sie welche unter Media hoch.</p>';
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

  function closeVideoPicker() {
    var modal = document.getElementById('dg-website-video-picker');
    if (!modal) return;
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    videoPickerTarget = '';
  }

  function ensureVideoPicker() {
    var modal = document.getElementById('dg-website-video-picker');
    if (modal) return modal;

    modal = document.createElement('div');
    modal.id = 'dg-website-video-picker';
    modal.className = 'dg-modal';
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    modal.innerHTML =
      '<div class="dg-modal__backdrop" data-video-picker-close></div>' +
      '<div class="dg-modal__dialog dg-website-media-picker__dialog" role="dialog" aria-modal="true" aria-labelledby="dg-website-video-picker-title">' +
        '<header class="dg-modal__head">' +
          '<h2 id="dg-website-video-picker-title">Videobibliothek</h2>' +
          '<button type="button" class="dg-modal__close" data-video-picker-close aria-label="Schließen">&times;</button>' +
        '</header>' +
        '<div class="dg-website-media-picker__toolbar">' +
          '<label class="dg-field dg-website-media-picker__search"><span class="dg-visually-hidden">Suchen</span>' +
            '<input type="search" placeholder="Suchen …" data-video-picker-search>' +
          '</label>' +
          '<a class="dg-button" href="/app?page=akademie&view=admin" target="_blank" rel="noopener">Zur Akademie</a>' +
        '</div>' +
        '<div class="dg-website-media-picker__body" data-video-picker-body>' +
          '<p class="dg-field-hint">Lade Videobibliothek …</p>' +
        '</div>' +
        '<footer class="dg-modal__foot">' +
          '<button type="button" class="dg-button" data-video-picker-close>Abbrechen</button>' +
        '</footer>' +
      '</div>';
    document.body.appendChild(modal);

    modal.addEventListener('click', function (event) {
      if (event.target.closest('[data-video-picker-close]')) {
        closeVideoPicker();
        return;
      }
      var pick = event.target.closest('[data-video-pick-id]');
      if (!pick) return;
      var videoId = pick.getAttribute('data-video-pick-id') || '';
      var item = (videoPickerCache || []).find(function (row) { return row.id === videoId; });
      if (!item || !videoPickerTarget) return;
      applyVideoSelection(videoPickerTarget, item);
      closeVideoPicker();
    });

    var search = modal.querySelector('[data-video-picker-search]');
    if (search) {
      search.addEventListener('input', function () {
        renderVideoPickerItems(search.value);
      });
    }

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') closeVideoPicker();
    });

    return modal;
  }

  function openVideoPicker(targetField) {
    videoPickerTarget = targetField;
    var modal = ensureVideoPicker();
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    var search = modal.querySelector('[data-video-picker-search]');
    if (search) search.value = '';
    loadVideoPickerItems();
  }

  function loadVideoPickerItems() {
    var body = document.querySelector('[data-video-picker-body]');
    if (!body) return;
    body.innerHTML = '<p class="dg-field-hint">Lade Videobibliothek …</p>';

    var cfg = window.dgWebsiteBuilder || {};
    var url = cfg.videoListUrl || '/api/website-videos?action=list';
    fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (res) {
        if (!res.ok) throw new Error('Liste nicht ladbar');
        return res.json();
      })
      .then(function (data) {
        videoPickerCache = Array.isArray(data.items) ? data.items : [];
        renderVideoPickerItems('');
      })
      .catch(function () {
        body.innerHTML = '<p class="dg-field-hint">Videobibliothek konnte nicht geladen werden.</p>';
      });
  }

  function formatVideoDuration(sec) {
    var n = parseInt(sec, 10);
    if (!n || n < 1) return '';
    var m = Math.floor(n / 60);
    var s = n % 60;
    return m + ':' + String(s).padStart(2, '0');
  }

  function renderVideoPickerItems(query) {
    var body = document.querySelector('[data-video-picker-body]');
    if (!body) return;
    var items = videoPickerCache || [];
    var q = String(query || '').trim().toLowerCase();
    if (q) {
      items = items.filter(function (item) {
        var hay = [item.title, item.url, item.source].join(' ').toLowerCase();
        return hay.indexOf(q) !== -1;
      });
    }
    if (!items.length) {
      body.innerHTML = '<p class="dg-field-hint">Keine Videos gefunden. Akademie-Videos unter Akademie → Admin oder MP4 in der Mediathek.</p>';
      return;
    }
    var html = '<div class="dg-website-media-picker__grid dg-website-video-picker__grid">';
    items.forEach(function (item) {
      var label = item.title || 'Video';
      var meta = item.source === 'academy' ? 'Akademie' : 'Mediathek';
      var dur = formatVideoDuration(item.duration_sec);
      if (dur) meta += ' · ' + dur;
      html += '<button type="button" class="dg-website-media-picker__item dg-website-video-picker__item" data-video-pick-id="' + escapeHtml(item.id) + '" title="' + escapeHtml(label) + '">' +
        '<span class="dg-website-video-picker__icon" aria-hidden="true">▶</span>' +
        '<span>' + escapeHtml(label) + '</span>' +
        '<small>' + escapeHtml(meta) + '</small>' +
        '</button>';
    });
    html += '</div>';
    body.innerHTML = html;
  }

  function applyVideoSelection(targetField, item) {
    var layout = parseLayout();
    var block = findBlock(layout, selected.blockId);
    if (!block) return;
    block[targetField] = item.url || '';
    if (!(block.label || '').trim() && item.title) {
      block.label = item.title;
    }
    persist(layout);
    render();
  }

  function renderInspector() {
    if (!inspector) return;
    closeAdvancedPopover();
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
          html += textFieldWithCharPicker('Text', 'text', block.text);
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
          html += textFieldWithCharPicker('Beschriftung', 'label', block.label || block.text);
          html += fieldHtml('Link', 'url', block.url);
          break;
        case 'spacer':
          html += fieldHtml('Höhe (px)', 'height', String(block.height || 24), 'type="number" min="8" max="160"');
          break;
        case 'video':
          html += fieldHtml('Label (optional)', 'label', block.label);
          html += fieldHtml('Video-URL (YouTube, Vimeo oder MP4)', 'url', block.url);
          html += videoPickerHtml('url');
          html += fieldHtml('Beschriftung unter dem Video', 'caption', block.caption);
          html += '<p class="dg-field-hint">Akademie-Videos über „Aus Videobibliothek“ — oder URL manuell eintragen.</p>';
          break;
        case 'online_booking':
          html += '<p class="dg-field-hint">Systemblock: zeigt das öffentliche Buchungsformular. Auf Terminkalender-Seiten bitte nicht entfernen.</p>';
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
          html += textFieldWithCharPicker('Text', 'text', block.text, { textarea: true, rows: 6 });
          html += '<label class="dg-field dg-field--inline"><span>' +
            '<input type="checkbox" name="quote" value="1"' + (block.quote ? ' checked' : '') + '> Als Zitat darstellen' +
            '</span></label>';
      }

      var protectBooking = builderCfg.isOnlineBookingPage && block.type === 'online_booking';
      html += advancedButtonHtml(block);
      if (!protectBooking) {
        html += '<div class="dg-form-actions dg-website-inspector-actions">' +
          '<button type="button" class="dg-button dg-button--danger" data-remove-block>Block entfernen</button>' +
          '</div>';
      }
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
    var insertPatternBtn = event.target.closest('[data-insert-pattern]');
    var addRow = event.target.closest('[data-add-row]');
    var removeBlock = event.target.closest('[data-remove-block]');
    var removeRow = event.target.closest('[data-remove-row]');
    var moveBlock = event.target.closest('[data-move-block]');
    var galleryAdd = event.target.closest('[data-gallery-add]');
    var galleryRemove = event.target.closest('[data-gallery-remove]');
    var layout = parseLayout();

    if (insertPatternBtn) {
      insertPatternRows(layout, insertPatternBtn.getAttribute('data-insert-pattern') || '');
      persist(layout);
      render();
      return;
    }

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
      var doomed = findBlock(layout, selected.blockId);
      if (doomed && doomed.type === 'online_booking' && builderCfg.isOnlineBookingPage) {
        window.alert('Der Block Online-Terminbuchung kann auf dieser Seite nicht entfernt werden.');
        return;
      }
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
    inspector.addEventListener('focusin', function (event) {
      var field = event.target;
      if (!field || !field.name) return;
      if (field.tagName === 'TEXTAREA') {
        rememberCharPickerSelection(field);
        return;
      }
      if (field.tagName !== 'INPUT') return;
      var inputType = String(field.type || 'text').toLowerCase();
      if (inputType !== 'text' && inputType !== 'search') return;
      rememberCharPickerSelection(field);
    });

    inspector.addEventListener('keyup', function (event) {
      var field = event.target;
      if (field && (field.tagName === 'INPUT' || field.tagName === 'TEXTAREA') && field.name) {
        rememberCharPickerSelection(field);
      }
    });

    inspector.addEventListener('mouseup', function (event) {
      var field = event.target;
      if (field && (field.tagName === 'INPUT' || field.tagName === 'TEXTAREA') && field.name) {
        rememberCharPickerSelection(field);
      }
    });

    inspector.addEventListener('mousedown', function (event) {
      if (event.target.closest('[data-char-picker-toggle], [data-char-insert], .dg-website-char-picker__menu, [data-format-bold], [data-format-link]')) {
        var active = document.activeElement;
        if (active && inspector.contains(active) && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA')) {
          rememberCharPickerSelection(active);
        }
        event.preventDefault();
      }
    });

    inspector.addEventListener('input', function (event) {
      var field = event.target;
      if (!field.name || !selected.blockId) return;
      if (field.type === 'checkbox' || field.type === 'radio') return;
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
      var quoteBox = event.target.closest('input[name="quote"][type="checkbox"]');
      if (quoteBox && selected.blockId) {
        var layoutQ = parseLayout();
        var blockQ = findBlock(layoutQ, selected.blockId);
        if (blockQ) {
          if (quoteBox.checked) blockQ.quote = true;
          else delete blockQ.quote;
          persist(layoutQ);
          render({ keepInspector: true });
        }
        return;
      }
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
      var formatBold = event.target.closest('[data-format-bold]');
      if (formatBold) {
        event.preventDefault();
        var fmtFor = formatBold.closest('[data-format-for]');
        var fmtName = fmtFor ? fmtFor.getAttribute('data-format-for') : 'text';
        applyFormatBold(fmtName || 'text');
        return;
      }
      var formatLink = event.target.closest('[data-format-link]');
      if (formatLink) {
        event.preventDefault();
        var linkFor = formatLink.closest('[data-format-for]');
        var linkName = linkFor ? linkFor.getAttribute('data-format-for') : 'text';
        applyFormatLink(linkName || 'text');
        return;
      }
      var pickerToggle = event.target.closest('[data-char-picker-toggle]');
      if (pickerToggle) {
        event.preventDefault();
        var picker = pickerToggle.closest('[data-char-picker-for]');
        var menu = picker ? picker.querySelector('[data-char-picker-menu]') : null;
        var forName = picker ? picker.getAttribute('data-char-picker-for') : '';
        if (forName) charPickerState.fieldName = forName;
        var wasOpen = menu && !menu.hidden;
        closeAllCharPickerMenus();
        if (menu && !wasOpen) {
          menu.hidden = false;
          pickerToggle.setAttribute('aria-expanded', 'true');
        }
        return;
      }
      var charBtn = event.target.closest('[data-char-insert]');
      if (charBtn) {
        event.preventDefault();
        var ch = charBtn.getAttribute('data-char-insert') || '';
        var pickerFor = charBtn.closest('[data-char-picker-for]');
        if (pickerFor && pickerFor.getAttribute('data-char-picker-for')) {
          charPickerState.fieldName = pickerFor.getAttribute('data-char-picker-for');
        }
        if (ch) insertCharAtCursor(ch);
        return;
      }
      if (!event.target.closest('.dg-website-char-picker')) {
        closeAllCharPickerMenus();
      }
      var advBtn = event.target.closest('[data-open-advanced]');
      if (advBtn) {
        event.preventDefault();
        openAdvancedPopover(advBtn);
        return;
      }
      var pickBtn = event.target.closest('[data-media-pick]');
      if (pickBtn) {
        event.preventDefault();
        openMediaPicker(pickBtn.getAttribute('data-media-pick') || 'src');
        return;
      }
      var videoBtn = event.target.closest('[data-video-pick]');
      if (!videoBtn) return;
      event.preventDefault();
      openVideoPicker(videoBtn.getAttribute('data-video-pick') || 'url');
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

  var patternList = document.getElementById('dg-website-pattern-list');
  if (patternList && builderCfg.patterns) {
    Object.keys(builderCfg.patterns).forEach(function (key) {
      var pattern = builderCfg.patterns[key];
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'dg-website-tool';
      btn.setAttribute('data-insert-pattern', key);
      btn.textContent = pattern.label || key;
      if (pattern.description) {
        btn.title = pattern.description;
      }
      patternList.appendChild(btn);
    });
  }

  updatePreviewLink();
  render();
})();
