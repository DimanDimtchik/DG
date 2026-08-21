/**
 * Visual website form builder (field palette + canvas + inspector).
 */
(function () {
  var defField = document.getElementById('dg-website-form-definition');
  var canvas = document.getElementById('dg-website-form-canvas');
  var inspector = document.getElementById('dg-website-form-inspector');
  var builder = document.getElementById('dg-website-form-builder');
  if (!defField || !canvas || !builder) return;

  var readOnly = builder.getAttribute('data-readonly') === '1';
  var selectedId = '';
  var pendingInsert = null;

  var typeLabels = {
    text: 'Text',
    email: 'E-Mail',
    tel: 'Telefon',
    textarea: 'Textarea',
    select: 'Dropdown',
    checkbox: 'Checkboxen',
    radio: 'Radio',
    file: 'Datei-Upload',
    consent: 'Datenschutz',
    intent: 'Anliegen',
    article: 'Artikel / DL',
    appointment: 'Buchungsnummer',
    heading: 'Überschrift',
    paragraph: 'Hinweistext',
    submit: 'Absenden'
  };

  function uid(prefix) {
    return prefix + '-' + Math.random().toString(16).slice(2, 10);
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function parseDefinition() {
    try {
      var data = JSON.parse(defField.value || '{}');
      if (!data || typeof data !== 'object') data = {};
      if (!Array.isArray(data.fields)) data.fields = [];
      if (!data.settings || typeof data.settings !== 'object') data.settings = {};
      return data;
    } catch (e) {
      return { fields: [], settings: {} };
    }
  }

  function persist(def) {
    defField.value = JSON.stringify(def);
  }

  function defaultField(type) {
    var labels = {
      text: 'Textfeld',
      email: 'E-Mail',
      tel: 'Telefon',
      textarea: 'Nachricht',
      select: 'Auswahl',
      checkbox: 'Optionen',
      radio: 'Auswahl',
      file: 'Datei',
      consent: 'Ich habe die Datenschutzerklärung gelesen und stimme zu.',
      intent: 'Worum geht es?',
      article: 'Artikel / Dienstleistung',
      appointment: 'Buchungsnummer',
      heading: 'Abschnitt',
      paragraph: 'Zusätzlicher Hinweistext.',
      submit: 'Absenden'
    };
    var field = {
      id: uid('fld'),
      type: type,
      label: labels[type] || 'Feld',
      name: type === 'submit' ? 'submit' : type + '_' + Math.random().toString(16).slice(2, 6),
      required: ['heading', 'paragraph', 'submit'].indexOf(type) === -1,
      placeholder: '',
      width: 12,
      help: ''
    };
    if (type === 'textarea') field.rows = 4;
    if (type === 'select' || type === 'checkbox' || type === 'radio') {
      field.options = [
        { value: 'option_1', label: 'Option 1' },
        { value: 'option_2', label: 'Option 2' }
      ];
      field.required = type !== 'checkbox';
    }
    if (type === 'intent') {
      field.options = [
        { value: 'termin', label: 'Wegen eines Termins' },
        { value: 'artikel', label: 'Wegen Artikel / Dienstleistung' },
        { value: 'allgemein', label: 'Allgemeine Anfrage' }
      ];
      field.required = true;
    }
    if (type === 'article') {
      field.options = articleOptionsFromBuilder();
      field.help = 'Liste aus Artikel & Leistungen (aktive Einträge).';
    }
    if (type === 'appointment') {
      field.options = [];
      field.placeholder = 'z. B. DG-7K2M9P4Q';
      field.help = 'Kunde trägt die Buchungsnummer aus der Bestätigungsmail ein. Die Nummer wird beim Verlassen des Feldes geprüft.';
      field.required = false;
    }
    if (type === 'file') {
      field.accept = '.pdf,.jpg,.jpeg,.png,.webp';
      field.max_mb = 5;
    }
    if (type === 'submit') field.required = false;
    return field;
  }

  function articleOptionsFromBuilder() {
    try {
      var raw = builder.getAttribute('data-articles') || '[]';
      var list = JSON.parse(raw);
      if (!Array.isArray(list)) return [];
      return list.map(function (a) {
        return { value: String(a.id), label: a.title || ('#' + a.id) };
      });
    } catch (e) {
      return [];
    }
  }

  function recipientEmailSuggestions() {
    try {
      var raw = builder.getAttribute('data-user-emails') || '[]';
      var list = JSON.parse(raw);
      return Array.isArray(list) ? list : [];
    } catch (e) {
      return [];
    }
  }

  function findField(def, id) {
    for (var i = 0; i < def.fields.length; i++) {
      if (def.fields[i].id === id) return { field: def.fields[i], index: i };
    }
    return null;
  }

  function insertField(def, type) {
    var field = defaultField(type);
    if (pendingInsert && pendingInsert.mode && pendingInsert.fieldId) {
      var pos = findField(def, pendingInsert.fieldId);
      if (pos) {
        var idx = pendingInsert.mode === 'before' ? pos.index : pos.index + 1;
        def.fields.splice(idx, 0, field);
        pendingInsert = null;
        selectedId = field.id;
        return;
      }
    }
    // before submit if present
    var submitIdx = -1;
    for (var i = 0; i < def.fields.length; i++) {
      if (def.fields[i].type === 'submit') { submitIdx = i; break; }
    }
    if (type !== 'submit' && submitIdx >= 0) {
      def.fields.splice(submitIdx, 0, field);
    } else {
      def.fields.push(field);
    }
    selectedId = field.id;
    pendingInsert = null;
  }

  function fieldPreviewHtml(field) {
    var type = field.type;
    var label = escapeHtml(field.label || '');
    var ph = escapeHtml(field.placeholder || '');
    var req = field.required ? ' *' : '';
    if (type === 'heading') return '<h3 class="dg-website-form-preview__heading">' + label + '</h3>';
    if (type === 'paragraph') return '<p class="dg-website-form-preview__p">' + label + '</p>';
    if (type === 'submit') return '<button type="button" class="dg-button dg-button--primary" disabled>' + (label || 'Absenden') + '</button>';
    if (type === 'textarea') return '<label><span>' + label + req + '</span><textarea rows="' + (field.rows || 4) + '" placeholder="' + ph + '" disabled></textarea></label>';
    if (type === 'checkbox' || type === 'radio') {
      var html = '<div><span class="dg-website-form-preview__label">' + label + req + '</span>';
      (field.options || []).forEach(function (o) {
        html += '<label class="dg-website-form-preview__choice"><input type="' + type + '" disabled> ' + escapeHtml(o.label || o.value) + '</label>';
      });
      return html + '</div>';
    }
    if (type === 'consent') {
      return '<label class="dg-website-form-preview__choice"><input type="checkbox" disabled> <span>' + label + req + '</span></label>';
    }
    if (type === 'intent') {
      var intentOpts = (field.options || []).map(function (o) {
        return '<label class="dg-website-form-preview__choice"><input type="radio" disabled> ' + escapeHtml(o.label || o.value) + '</label>';
      }).join('');
      return '<div><div class="dg-website-form-preview__label">' + label + req + '</div>' + intentOpts + '</div>';
    }
    if (type === 'article' || type === 'select') {
      var selOpts = (field.options || []).map(function (o) {
        return '<option>' + escapeHtml(o.label || o.value) + '</option>';
      }).join('');
      return '<label><span>' + label + req + '</span><select disabled><option>— Bitte wählen —</option>' + selOpts + '</select></label>';
    }
    if (type === 'appointment') {
      return '<label><span>' + label + req + '</span><input type="text" placeholder="' +
        escapeHtml(field.placeholder || 'z. B. DG-7K2M9P4Q') + '" disabled></label>';
    }
    if (type === 'file') {
      return '<label><span>' + label + req + '</span><input type="file" disabled></label>';
    }
    var inputType = type === 'email' || type === 'tel' ? type : 'text';
    return '<label><span>' + label + req + '</span><input type="' + inputType + '" placeholder="' + ph + '" disabled></label>';
  }

  function renderCanvas() {
    var def = parseDefinition();
    if (!def.fields.length) {
      canvas.innerHTML = '<div class="dg-website-canvas-empty">Noch keine Felder — links Bausteine hinzufügen.</div>';
      return;
    }
    var html = '<div class="dg-website-form-preview">';
    def.fields.forEach(function (field, index) {
      var selected = field.id === selectedId ? ' is-selected' : '';
      html += '<div class="dg-website-form-field' + selected + '" data-field-id="' + escapeHtml(field.id) + '">';
      if (!readOnly) {
        html += '<div class="dg-website-form-field__plus dg-website-form-field__plus--before">' +
          '<button type="button" class="dg-website-plus" data-insert-at="before" data-field-id="' + escapeHtml(field.id) + '" title="Feld darüber">+</button></div>';
      }
      html += '<div class="dg-website-form-field__body">' +
        '<div class="dg-website-form-field__meta">' + escapeHtml(typeLabels[field.type] || field.type) + '</div>' +
        fieldPreviewHtml(field) +
        '</div>';
      if (!readOnly && index === def.fields.length - 1) {
        html += '<div class="dg-website-form-field__plus dg-website-form-field__plus--after">' +
          '<button type="button" class="dg-website-plus" data-insert-at="after" data-field-id="' + escapeHtml(field.id) + '" title="Feld darunter">+</button></div>';
      }
      html += '</div>';
    });
    html += '</div>';
    canvas.innerHTML = html;
  }

  function fieldHtml(label, name, value, attrs) {
    return '<label class="dg-field"><span>' + escapeHtml(label) + '</span>' +
      '<input name="' + escapeHtml(name) + '" value="' + escapeHtml(value || '') + '" ' + (attrs || '') + '></label>';
  }

  function renderInspector() {
    if (!inspector || readOnly) return;
    var def = parseDefinition();
    var html = '';

    if (!selectedId) {
      var s = def.settings || {};
      var emails = recipientEmailSuggestions();
      var listId = 'dg-recipient-email-list';
      html += '<p class="dg-field-hint">Formular</p>';
      html += '<label class="dg-field"><span>Empfänger-E-Mail</span>' +
        '<input name="recipient_email" type="email" list="' + listId + '" value="' + escapeHtml(s.recipient_email || '') + '" placeholder="frei eingeben oder Vorschlag wählen">' +
        '<datalist id="' + listId + '">';
      emails.forEach(function (row) {
        html += '<option value="' + escapeHtml(row.email) + '">' + escapeHtml((row.label || '') + (row.email ? ' <' + row.email + '>' : '')) + '</option>';
      });
      html += '</datalist><span class="dg-field-hint">Vorschläge aus CRM-Benutzern — oder eigene Adresse tippen.</span></label>';
      html += fieldHtml('E-Mail-Betreff', 'mail_subject', s.mail_subject || 'Formularanfrage');
      html += '<label class="dg-field"><span>Danke-Text</span><textarea name="success_message" rows="3">' + escapeHtml(s.success_message || '') + '</textarea></label>';
      html += '<label class="dg-field"><span><input type="checkbox" name="send_email" value="1"' + (s.send_email !== false ? ' checked' : '') + '> E-Mail senden</span></label>';
      html += '<label class="dg-field"><span><input type="checkbox" name="store_submissions" value="1"' + (s.store_submissions !== false ? ' checked' : '') + '> Eingänge speichern</span></label>';
      html += '<label class="dg-field"><span><input type="checkbox" name="honeypot" value="1"' + (s.honeypot !== false ? ' checked' : '') + '> Spam-Honeypot</span></label>';
      html += '<label class="dg-field"><span><input type="checkbox" name="captcha" value="1"' + (s.captcha !== false ? ' checked' : '') + '> Rechen-Captcha</span></label>';
      inspector.innerHTML = html;
      return;
    }

    var pos = findField(def, selectedId);
    if (!pos) {
      inspector.innerHTML = '<p class="dg-field-hint">Feld nicht gefunden.</p>';
      return;
    }
    var field = pos.field;
    html += '<p class="dg-field-hint">' + escapeHtml(typeLabels[field.type] || field.type) + '</p>';
    html += '<div class="dg-form-actions dg-website-inspector-actions" style="margin-bottom:8px;">' +
      '<button type="button" class="dg-button" data-move-field="up">↑</button>' +
      '<button type="button" class="dg-button" data-move-field="down">↓</button></div>';

    if (field.type !== 'submit') {
      html += fieldHtml('Beschriftung', 'label', field.label);
    } else {
      html += fieldHtml('Button-Text', 'label', field.label);
    }
    if (['heading', 'paragraph', 'submit', 'consent'].indexOf(field.type) === -1) {
      html += fieldHtml('Name (technisch)', 'name', field.name);
      html += fieldHtml('Platzhalter', 'placeholder', field.placeholder || '');
      html += fieldHtml('Hilfe-Text', 'help', field.help || '');
    }
    if (field.type === 'consent' || field.type === 'heading' || field.type === 'paragraph') {
      html += fieldHtml('Text', 'label', field.label);
    }
    if (['heading', 'paragraph', 'submit'].indexOf(field.type) === -1) {
      html += '<label class="dg-field"><span><input type="checkbox" name="required" value="1"' + (field.required ? ' checked' : '') + '> Pflichtfeld</span></label>';
    }
    html += fieldHtml('Breite (3–12)', 'width', String(field.width || 12), 'type="number" min="3" max="12"');
    if (field.type === 'textarea') {
      html += fieldHtml('Zeilen', 'rows', String(field.rows || 4), 'type="number" min="2" max="20"');
    }
    if (field.type === 'select' || field.type === 'checkbox' || field.type === 'radio' || field.type === 'intent' || field.type === 'article') {
      var lines = (field.options || []).map(function (o) {
        return (o.value || '') + (o.label && o.label !== o.value ? '|' + o.label : '');
      }).join('\n');
      html += '<label class="dg-field"><span>Optionen (Wert|Label je Zeile)</span><textarea name="options_text" rows="5">' + escapeHtml(lines) + '</textarea></label>';
      if (field.type === 'article') {
        html += '<p class="dg-field-hint">Standard: aktive Artikel/Leistungen. Optionen können überschrieben werden.</p>';
      }
    }
    if (field.type === 'appointment') {
      html += fieldHtml('Platzhalter', 'placeholder', field.placeholder || 'z. B. DG-7K2M9P4Q');
      html += '<p class="dg-field-hint">Kunde gibt die Buchungsnummer aus der Bestätigungsmail ein. Prüfung beim Verlassen des Feldes.</p>';
    }
    if (field.type === 'file') {
      html += fieldHtml('Erlaubte Typen', 'accept', field.accept || '.pdf,.jpg,.jpeg,.png,.webp');
      html += fieldHtml('Max. MB', 'max_mb', String(field.max_mb || 5), 'type="number" min="1" max="20"');
    }
    if (field.type !== 'submit') {
      html += '<div class="dg-form-actions dg-website-inspector-actions">' +
        '<button type="button" class="dg-button dg-button--danger" data-remove-field>Feld entfernen</button></div>';
    } else {
      html += '<p class="dg-field-hint">Absenden-Button kann nicht entfernt werden.</p>';
    }

    html += '<hr style="margin:16px 0;border:none;border-top:1px solid var(--dg-border);">';
    html += '<button type="button" class="dg-button" data-show-form-settings>Formular-Einstellungen</button>';
    inspector.innerHTML = html;
  }

  function render() {
    renderCanvas();
    renderInspector();
  }

  function parseOptionsText(text) {
    return String(text || '').split(/\r?\n/).map(function (line) {
      line = line.trim();
      if (!line) return null;
      var parts = line.split('|');
      var value = (parts[0] || '').trim();
      var label = (parts[1] || parts[0] || '').trim();
      if (!value) return null;
      return { value: value, label: label || value };
    }).filter(Boolean);
  }

  if (!readOnly) {
    builder.addEventListener('click', function (event) {
      var add = event.target.closest('[data-add-field]');
      if (add) {
        event.preventDefault();
        var def = parseDefinition();
        insertField(def, add.getAttribute('data-add-field'));
        persist(def);
        render();
        return;
      }

      var plus = event.target.closest('[data-insert-at]');
      if (plus) {
        event.preventDefault();
        pendingInsert = {
          mode: plus.getAttribute('data-insert-at'),
          fieldId: plus.getAttribute('data-field-id')
        };
        return;
      }

      var fieldEl = event.target.closest('[data-field-id]');
      if (fieldEl && canvas.contains(fieldEl)) {
        selectedId = fieldEl.getAttribute('data-field-id') || '';
        render();
      }
    });

    if (inspector) {
      inspector.addEventListener('click', function (event) {
        if (event.target.closest('[data-show-form-settings]')) {
          selectedId = '';
          render();
          return;
        }
        var move = event.target.closest('[data-move-field]');
        if (move && selectedId) {
          var def = parseDefinition();
          var pos = findField(def, selectedId);
          if (!pos) return;
          var dir = move.getAttribute('data-move-field');
          var swap = dir === 'up' ? pos.index - 1 : pos.index + 1;
          if (swap < 0 || swap >= def.fields.length) return;
          var tmp = def.fields[pos.index];
          def.fields[pos.index] = def.fields[swap];
          def.fields[swap] = tmp;
          persist(def);
          render();
          return;
        }
        if (event.target.closest('[data-remove-field]') && selectedId) {
          var def2 = parseDefinition();
          var pos2 = findField(def2, selectedId);
          if (!pos2 || pos2.field.type === 'submit') return;
          def2.fields.splice(pos2.index, 1);
          selectedId = '';
          persist(def2);
          render();
        }
      });

      inspector.addEventListener('input', function (event) {
        var t = event.target;
        if (!t.name) return;
        var def = parseDefinition();
        if (!selectedId) {
          def.settings = def.settings || {};
          if (t.type === 'checkbox') {
            def.settings[t.name] = t.checked;
          } else {
            def.settings[t.name] = t.value;
          }
          persist(def);
          return;
        }
        var pos = findField(def, selectedId);
        if (!pos) return;
        if (t.name === 'options_text') {
          pos.field.options = parseOptionsText(t.value);
        } else if (t.type === 'checkbox') {
          pos.field[t.name] = t.checked;
        } else if (t.name === 'width' || t.name === 'rows' || t.name === 'max_mb') {
          pos.field[t.name] = parseInt(t.value, 10) || 0;
        } else {
          pos.field[t.name] = t.value;
        }
        persist(def);
        renderCanvas();
      });

      inspector.addEventListener('change', function (event) {
        var t = event.target;
        if (!t.name || t.type !== 'checkbox') return;
        var def = parseDefinition();
        if (!selectedId) {
          def.settings = def.settings || {};
          def.settings[t.name] = t.checked;
          persist(def);
          return;
        }
        var pos = findField(def, selectedId);
        if (!pos) return;
        pos.field[t.name] = t.checked;
        persist(def);
        renderCanvas();
      });
    }
  }

  // Click empty canvas → form settings
  canvas.addEventListener('click', function (event) {
    if (event.target === canvas || event.target.classList.contains('dg-website-canvas-empty')) {
      selectedId = '';
      render();
    }
  });

  render();
})();
