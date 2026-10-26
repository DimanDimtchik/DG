/**
 * Dokumentenverfasser — TipTap-Editor (kompakte Toolbar, Formatierung, Medien).
 */
import {
  Editor,
  StarterKit,
  Placeholder,
  Image,
  Table,
  TableRow,
  TableCell,
  TableHeader,
  Underline,
  TextStyle,
  Color,
  FontFamily,
  FontSize,
  TextAlign,
  Link,
  Highlight,
} from './vendor/tiptap-writer.mjs';

(function () {
  'use strict';

  var cfg = window.dgWriterEditor || null;
  if (!cfg || !cfg.mountId) {
    return;
  }

  var mount = document.getElementById(cfg.mountId);
  var form = document.getElementById(cfg.formId || 'dg-writer-form');
  var jsonInput = document.getElementById(cfg.jsonInputId || 'dg-writer-body-tiptap');
  var htmlInput = document.getElementById(cfg.htmlInputId || 'dg-writer-body-html');
  var paragraphsHost = document.getElementById(cfg.paragraphsHostId || 'dg-writer-paragraphs-sync');
  if (!mount || !form || !jsonInput || !htmlInput) {
    return;
  }

  var editable = cfg.editable !== false;
  var initialDoc = null;
  try {
    if (cfg.initialJson) {
      initialDoc = typeof cfg.initialJson === 'string' ? JSON.parse(cfg.initialJson) : cfg.initialJson;
    }
  } catch (e) {
    initialDoc = null;
  }

  function paragraphsFromDoc(doc) {
    var out = [];
    if (!doc || !Array.isArray(doc.content)) {
      return [''];
    }
    doc.content.forEach(function (node) {
      if (!node || node.type !== 'paragraph') {
        return;
      }
      var text = '';
      (node.content || []).forEach(function (child) {
        if (child && child.type === 'text' && child.text) {
          text += child.text;
        }
      });
      out.push(text);
    });
    return out.length ? out : [''];
  }

  function syncHidden() {
    var json = editor.getJSON();
    jsonInput.value = JSON.stringify(json);
    htmlInput.value = editor.getHTML();
    if (paragraphsHost) {
      paragraphsHost.innerHTML = '';
      paragraphsFromDoc(json).forEach(function (text) {
        var ta = document.createElement('textarea');
        ta.name = 'paragraphs[]';
        ta.hidden = true;
        ta.value = text;
        paragraphsHost.appendChild(ta);
      });
    }
    syncToolbarState();
  }

  var editor = new Editor({
    element: mount,
    editable: editable,
    extensions: [
      StarterKit.configure({
        heading: false,
        codeBlock: false,
        blockquote: false,
        horizontalRule: false,
      }),
      Underline,
      TextStyle,
      Color,
      FontFamily,
      FontSize,
      TextAlign.configure({
        types: ['heading', 'paragraph'],
      }),
      Link.configure({
        openOnClick: false,
        autolink: true,
        HTMLAttributes: { rel: 'noopener noreferrer', target: '_blank' },
      }),
      Highlight.configure({ multicolor: true }),
      Placeholder.configure({
        placeholder: 'Text schreiben … Absätze mit Enter. Formatierung und Medien über die Leiste.',
      }),
      Image.configure({
        inline: true,
        allowBase64: true,
        HTMLAttributes: { class: 'dg-writer-img' },
      }),
      Table.configure({ resizable: false, HTMLAttributes: { class: 'dg-writer-table' } }),
      TableRow,
      TableHeader,
      TableCell,
    ],
    content: initialDoc || cfg.initialHtml || '<p></p>',
    editorProps: {
      attributes: {
        class: 'dg-writer-prose',
        spellcheck: 'true',
        lang: 'de',
      },
    },
    onUpdate: function () {
      syncHidden();
    },
    onSelectionUpdate: function () {
      syncToolbarState();
    },
  });

  syncHidden();

  form.addEventListener('submit', function () {
    syncHidden();
  });

  function btn(id, fn) {
    var el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('click', function (ev) {
      ev.preventDefault();
      if (!editable) return;
      fn(el);
      editor.chain().focus().run();
      syncHidden();
    });
  }

  function setActive(id, on) {
    var el = document.getElementById(id);
    if (!el) return;
    el.classList.toggle('is-active', !!on);
    el.setAttribute('aria-pressed', on ? 'true' : 'false');
  }

  function syncToolbarState() {
    if (!editable) return;
    setActive('dg-writer-cmd-bold', editor.isActive('bold'));
    setActive('dg-writer-cmd-italic', editor.isActive('italic'));
    setActive('dg-writer-cmd-underline', editor.isActive('underline'));
    setActive('dg-writer-cmd-strike', editor.isActive('strike'));
    setActive('dg-writer-cmd-bullet', editor.isActive('bulletList'));
    setActive('dg-writer-cmd-ordered', editor.isActive('orderedList'));
    setActive('dg-writer-cmd-align-left', editor.isActive({ textAlign: 'left' }));
    setActive('dg-writer-cmd-align-center', editor.isActive({ textAlign: 'center' }));
    setActive('dg-writer-cmd-align-right', editor.isActive({ textAlign: 'right' }));
    setActive('dg-writer-cmd-align-justify', editor.isActive({ textAlign: 'justify' }));
    setActive('dg-writer-cmd-link', editor.isActive('link'));

    var mark = editor.getAttributes('textStyle') || {};
    var fontSel = document.getElementById('dg-writer-font-family');
    var sizeSel = document.getElementById('dg-writer-font-size');
    var colorInp = document.getElementById('dg-writer-text-color');
    if (fontSel && document.activeElement !== fontSel) {
      fontSel.value = mark.fontFamily || '';
    }
    if (sizeSel && document.activeElement !== sizeSel) {
      sizeSel.value = mark.fontSize || '';
    }
    if (colorInp && document.activeElement !== colorInp && mark.color) {
      colorInp.value = mark.color;
    }
    syncSelectionBar();
  }

  function isLucideSrc(src) {
    return typeof src === 'string' && src.indexOf('data:image/svg+xml') === 0;
  }

  function looksLikeEmoji(text) {
    var t = String(text || '').trim();
    if (!t || t.length > 8) return false;
    // Emoji / Symbol ohne Buchstaben-Wort
    return !/[A-Za-zÄÖÜäöüß0-9]{2,}/.test(t);
  }

  function recolorLucideSrc(src, color) {
    if (!isLucideSrc(src) || !color) return src;
    try {
      var raw = src;
      if (src.indexOf('charset=utf-8,') !== -1) {
        raw = decodeURIComponent(src.split('charset=utf-8,')[1] || '');
      } else if (src.indexOf(',') !== -1) {
        raw = decodeURIComponent(src.split(',').slice(1).join(','));
      }
      raw = raw.replace(/stroke="[^"]*"/g, 'stroke="' + color + '"');
      return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(raw);
    } catch (e) {
      return src;
    }
  }

  function syncSelectionBar() {
    var bar = document.getElementById('dg-writer-selection-bar');
    if (!bar || !editable) return;
    var label = document.getElementById('dg-writer-sel-label');
    var sizeWrap = document.getElementById('dg-writer-sel-size-wrap');
    var widthWrap = document.getElementById('dg-writer-sel-width-wrap');
    var colorWrap = document.getElementById('dg-writer-sel-color-wrap');
    var alignWrap = document.getElementById('dg-writer-sel-align-wrap');
    var sizeSel = document.getElementById('dg-writer-sel-size');
    var widthSel = document.getElementById('dg-writer-sel-width');
    var colorInp = document.getElementById('dg-writer-sel-color');

    var hideAll = function () {
      bar.hidden = true;
      if (sizeWrap) sizeWrap.hidden = true;
      if (widthWrap) widthWrap.hidden = true;
      if (colorWrap) colorWrap.hidden = true;
      if (alignWrap) alignWrap.hidden = true;
    };

    if (editor.isActive('image')) {
      var img = editor.getAttributes('image') || {};
      var lucide = isLucideSrc(img.src || '');
      bar.hidden = false;
      if (label) label.textContent = lucide ? 'Icon' : 'Bild';
      if (sizeWrap) sizeWrap.hidden = !lucide;
      if (widthWrap) widthWrap.hidden = !!lucide;
      if (colorWrap) colorWrap.hidden = !lucide;
      if (alignWrap) alignWrap.hidden = false;
      if (lucide && sizeSel && document.activeElement !== sizeSel) {
        var w = String(img.width || '22');
        if (w && w.indexOf('px') === -1 && w !== '100%') w = w + 'px';
        sizeSel.value = ['12px', '16px', '22px', '32px', '48px'].indexOf(w) !== -1 ? w : '22px';
      }
      if (!lucide && widthSel && document.activeElement !== widthSel) {
        widthSel.value = img.width != null && img.width !== '' ? String(img.width) : '';
      }
      return;
    }

    var sel = editor.state.selection;
    var text = '';
    try {
      text = editor.state.doc.textBetween(sel.from, sel.to, '');
    } catch (e) {
      text = '';
    }
    if (text && looksLikeEmoji(text)) {
      bar.hidden = false;
      if (label) label.textContent = 'Emoji';
      if (sizeWrap) sizeWrap.hidden = false;
      if (widthWrap) widthWrap.hidden = true;
      if (colorWrap) colorWrap.hidden = false;
      if (alignWrap) alignWrap.hidden = true;
      var mark = editor.getAttributes('textStyle') || {};
      if (sizeSel && document.activeElement !== sizeSel && mark.fontSize) {
        sizeSel.value = mark.fontSize;
      }
      if (colorInp && document.activeElement !== colorInp && mark.color) {
        colorInp.value = mark.color;
      }
      return;
    }

    hideAll();
  }

  (function bindSelectionBar() {
    var sizeSel = document.getElementById('dg-writer-sel-size');
    var widthSel = document.getElementById('dg-writer-sel-width');
    var colorInp = document.getElementById('dg-writer-sel-color');
    var alignWrap = document.getElementById('dg-writer-sel-align-wrap');

    if (sizeSel) {
      sizeSel.addEventListener('change', function () {
        if (!editable) return;
        var v = sizeSel.value;
        if (editor.isActive('image') && isLucideSrc((editor.getAttributes('image') || {}).src || '')) {
          var n = parseInt(v, 10) || 22;
          editor.chain().focus().updateAttributes('image', { width: n }).run();
        } else {
          editor.chain().focus().setFontSize(v).run();
        }
        syncHidden();
      });
    }
    if (widthSel) {
      widthSel.addEventListener('change', function () {
        if (!editable || !editor.isActive('image')) return;
        var v = widthSel.value;
        if (!v) {
          editor.chain().focus().updateAttributes('image', { width: null }).run();
        } else if (v === '100%') {
          editor.chain().focus().updateAttributes('image', { width: '100%' }).run();
        } else {
          editor.chain().focus().updateAttributes('image', { width: parseInt(v, 10) || null }).run();
        }
        syncHidden();
      });
    }
    if (colorInp) {
      colorInp.addEventListener('input', function () {
        if (!editable) return;
        if (editor.isActive('image')) {
          var img = editor.getAttributes('image') || {};
          if (!isLucideSrc(img.src || '')) return;
          editor
            .chain()
            .focus()
            .updateAttributes('image', { src: recolorLucideSrc(img.src, colorInp.value) })
            .run();
        } else {
          editor.chain().focus().setColor(colorInp.value).run();
        }
        syncHidden();
      });
    }
    if (alignWrap) {
      alignWrap.addEventListener('click', function (ev) {
        var btnEl = ev.target.closest('[data-sel-align]');
        if (!btnEl || !editable) return;
        var align = btnEl.getAttribute('data-sel-align') || 'left';
        editor.chain().focus().setTextAlign(align).run();
        syncHidden();
      });
    }
  })();

  btn('dg-writer-cmd-bold', function () {
    editor.chain().focus().toggleBold().run();
  });
  btn('dg-writer-cmd-italic', function () {
    editor.chain().focus().toggleItalic().run();
  });
  btn('dg-writer-cmd-underline', function () {
    editor.chain().focus().toggleUnderline().run();
  });
  btn('dg-writer-cmd-strike', function () {
    editor.chain().focus().toggleStrike().run();
  });
  btn('dg-writer-cmd-bullet', function () {
    editor.chain().focus().toggleBulletList().run();
  });
  btn('dg-writer-cmd-ordered', function () {
    editor.chain().focus().toggleOrderedList().run();
  });
  btn('dg-writer-cmd-align-left', function () {
    editor.chain().focus().setTextAlign('left').run();
  });
  btn('dg-writer-cmd-align-center', function () {
    editor.chain().focus().setTextAlign('center').run();
  });
  btn('dg-writer-cmd-align-right', function () {
    editor.chain().focus().setTextAlign('right').run();
  });
  btn('dg-writer-cmd-align-justify', function () {
    editor.chain().focus().setTextAlign('justify').run();
  });
  btn('dg-writer-cmd-indent-more', function () {
    editor.chain().focus().sinkListItem('listItem').run();
  });
  btn('dg-writer-cmd-indent-less', function () {
    editor.chain().focus().liftListItem('listItem').run();
  });
  btn('dg-writer-cmd-table', function () {
    editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
  });
  btn('dg-writer-cmd-table-add-row', function () {
    editor.chain().focus().addRowAfter().run();
  });
  btn('dg-writer-cmd-table-add-col', function () {
    editor.chain().focus().addColumnAfter().run();
  });
  btn('dg-writer-cmd-table-delete', function () {
    editor.chain().focus().deleteTable().run();
  });
  btn('dg-writer-cmd-undo', function () {
    editor.chain().focus().undo().run();
  });
  btn('dg-writer-cmd-redo', function () {
    editor.chain().focus().redo().run();
  });
  btn('dg-writer-cmd-clear', function () {
    editor.chain().focus().unsetAllMarks().clearNodes().run();
  });
  btn('dg-writer-cmd-link', function () {
    var prev = editor.getAttributes('link').href || 'https://';
    var url = window.prompt('Link-Adresse (URL):', prev);
    if (url === null) return;
    url = String(url).trim();
    if (url === '') {
      editor.chain().focus().extendMarkRange('link').unsetLink().run();
      return;
    }
    editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
  });
  btn('dg-writer-cmd-unlink', function () {
    editor.chain().focus().extendMarkRange('link').unsetLink().run();
  });

  var fontSel = document.getElementById('dg-writer-font-family');
  if (fontSel) {
    fontSel.addEventListener('change', function () {
      if (!editable) return;
      var v = fontSel.value;
      if (!v) {
        editor.chain().focus().unsetFontFamily().run();
      } else {
        editor.chain().focus().setFontFamily(v).run();
      }
      syncHidden();
    });
  }

  var sizeSel = document.getElementById('dg-writer-font-size');
  if (sizeSel) {
    sizeSel.addEventListener('change', function () {
      if (!editable) return;
      var v = sizeSel.value;
      if (!v) {
        editor.chain().focus().unsetFontSize().run();
      } else {
        editor.chain().focus().setFontSize(v).run();
      }
      syncHidden();
    });
  }

  var colorInp = document.getElementById('dg-writer-text-color');
  if (colorInp) {
    colorInp.addEventListener('input', function () {
      if (!editable) return;
      editor.chain().focus().setColor(colorInp.value).run();
      syncHidden();
    });
  }

  var hlInp = document.getElementById('dg-writer-highlight-color');
  if (hlInp) {
    hlInp.addEventListener('input', function () {
      if (!editable) return;
      editor.chain().focus().toggleHighlight({ color: hlInp.value }).run();
      syncHidden();
    });
  }

  /* —— Emoji —— */
  var emojiPanel = document.getElementById('dg-writer-emoji-panel');
  btn('dg-writer-cmd-emoji', function () {
    if (!emojiPanel) return;
    emojiPanel.hidden = !emojiPanel.hidden;
    if (!emojiPanel.hidden) {
      renderEmojiPanel();
    }
  });

  function renderEmojiPanel() {
    if (!emojiPanel || !window.DgEmojiCatalog) return;
    var cat = window.DgEmojiCatalog;
    var groups = cat.groups || [];
    var html = '<div class="dg-writer-picker__tabs">';
    groups.forEach(function (g, i) {
      html +=
        '<button type="button" class="dg-button dg-writer-emoji-tab' +
        (i === 0 ? ' is-active' : '') +
        '" data-group="' +
        g.id +
        '">' +
        escapeHtml(g.label) +
        '</button>';
    });
    html += '</div><div class="dg-writer-picker__grid" id="dg-writer-emoji-grid"></div>';
    emojiPanel.innerHTML = html;
    var active = groups[0] ? groups[0].id : '';
    function fill(gid) {
      var g = groups.find(function (x) {
        return x.id === gid;
      });
      var grid = document.getElementById('dg-writer-emoji-grid');
      if (!grid || !g) return;
      grid.innerHTML = (g.chars || [])
        .map(function (ch) {
          return (
            '<button type="button" class="dg-writer-emoji-btn" data-emoji="' +
            escapeHtml(ch) +
            '">' +
            ch +
            '</button>'
          );
        })
        .join('');
    }
    fill(active);
    emojiPanel.onclick = function (ev) {
      var tab = ev.target.closest('.dg-writer-emoji-tab');
      if (tab) {
        emojiPanel.querySelectorAll('.dg-writer-emoji-tab').forEach(function (t) {
          t.classList.toggle('is-active', t === tab);
        });
        fill(tab.getAttribute('data-group') || '');
        return;
      }
      var pick = ev.target.closest('.dg-writer-emoji-btn');
      if (!pick) return;
      editor.chain().focus().insertContent(pick.getAttribute('data-emoji') || '').run();
      emojiPanel.hidden = true;
      syncHidden();
    };
  }

  /* —— Lucide / Icon —— */
  var lucidePanel = document.getElementById('dg-writer-lucide-panel');
  btn('dg-writer-cmd-lucide', function () {
    if (!lucidePanel) return;
    lucidePanel.hidden = !lucidePanel.hidden;
    if (!lucidePanel.hidden) {
      renderLucidePanel('');
    }
  });

  function lucideCatalog() {
    var el = document.getElementById('dg-menu-icon-catalog');
    if (!el) return [];
    try {
      var data = JSON.parse(el.textContent || '{}');
      return data.icons || data.items || [];
    } catch (e) {
      return [];
    }
  }

  function lucideDataUrl(pathsHtml) {
    var svg =
      '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#1a1a1a" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' +
      (pathsHtml || '') +
      '</svg>';
    return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
  }

  function filterLucideIcons(query) {
    var q = String(query || '')
      .trim()
      .toLowerCase();
    var icons = lucideCatalog().filter(function (ic) {
      if (!q) return true;
      var id = String(ic.id || '').toLowerCase();
      var label = String(ic.label || '').toLowerCase();
      var tags = Array.isArray(ic.tags) ? ic.tags.join(' ').toLowerCase() : '';
      return id.indexOf(q) !== -1 || label.indexOf(q) !== -1 || tags.indexOf(q) !== -1;
    });
    return icons.slice(0, 120);
  }

  function fillLucideGrid(query) {
    var grid = document.getElementById('dg-writer-lucide-grid');
    if (!grid) return;
    grid.innerHTML = filterLucideIcons(query)
      .map(function (ic) {
        return (
          '<button type="button" class="dg-writer-lucide-btn" data-id="' +
          escapeHtml(ic.id || '') +
          '" title="' +
          escapeHtml(ic.label || ic.id || '') +
          '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" width="22" height="22">' +
          (ic.paths || '') +
          '</svg></button>'
        );
      })
      .join('');
  }

  function renderLucidePanel(query) {
    if (!lucidePanel) return;
    var search = document.getElementById('dg-writer-lucide-search');
    if (!search) {
      lucidePanel.innerHTML =
        '<label class="dg-field"><span>Icon suchen</span>' +
        '<input type="search" id="dg-writer-lucide-search" dir="ltr" autocomplete="off" spellcheck="false" ' +
        'inputmode="search" placeholder="z. B. mail, face, check" value="' +
        escapeHtml(query || '') +
        '"></label>' +
        '<div class="dg-writer-picker__grid dg-writer-lucide-grid" id="dg-writer-lucide-grid"></div>';
      search = document.getElementById('dg-writer-lucide-search');
      if (search) {
        search.addEventListener('input', function () {
          fillLucideGrid(search.value);
        });
      }
      lucidePanel.onclick = function (ev) {
        var pick = ev.target.closest('.dg-writer-lucide-btn');
        if (!pick) return;
        var id = pick.getAttribute('data-id') || '';
        var ic = lucideCatalog().find(function (x) {
          return String(x.id) === id;
        });
        if (!ic || !ic.paths) return;
        editor
          .chain()
          .focus()
          .setImage({
            src: lucideDataUrl(ic.paths),
            alt: ic.label || ic.id || 'icon',
            title: ic.id,
          })
          .run();
        lucidePanel.hidden = true;
        syncHidden();
      };
    } else if (query !== undefined && search.value !== query) {
      search.value = query || '';
    }
    fillLucideGrid(search ? search.value : query || '');
    if (search) {
      search.focus();
      var len = search.value.length;
      try {
        search.setSelectionRange(len, len);
      } catch (e) {
        /* ignore */
      }
    }
  }

  /* —— Mediathek —— */
  var mediaPanel = document.getElementById('dg-writer-media-panel');
  btn('dg-writer-cmd-media', function () {
    if (!mediaPanel) return;
    mediaPanel.hidden = !mediaPanel.hidden;
    if (!mediaPanel.hidden) {
      loadMediaPanel();
    }
  });

  function mediaItemsFromPayload(payload) {
    if (!payload) return [];
    if (Array.isArray(payload.items)) return payload.items;
    if (payload.data && Array.isArray(payload.data.items)) return payload.data.items;
    return [];
  }

  function loadMediaPanel() {
    if (!mediaPanel) return;
    mediaPanel.innerHTML = '<p class="dg-field-hint">Mediathek wird geladen …</p>';
    var url = cfg.mediaListUrl || '/api/media?action=list';
    fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) {
        return r.json().then(function (data) {
          return { ok: r.ok, status: r.status, data: data };
        });
      })
      .then(function (res) {
        if (!res.ok) {
          var msg =
            (res.data && (res.data.message || res.data.error)) ||
            'Mediathek nicht erreichbar (Rechte oder Netzwerk).';
          mediaPanel.innerHTML = '<p class="dg-field-hint">' + escapeHtml(msg) + '</p>';
          return;
        }
        var items = mediaItemsFromPayload(res.data);
        if (!items.length) {
          mediaPanel.innerHTML =
            '<p class="dg-field-hint">Keine Bilder in der Mediathek. Unter „Bilder“ hochladen.</p>';
          return;
        }
        mediaPanel.innerHTML =
          '<div class="dg-writer-picker__grid dg-writer-media-grid">' +
          items
            .slice(0, 80)
            .map(function (it) {
              var src = it.preview_url || it.url || '';
              return (
                '<button type="button" class="dg-writer-media-btn" data-url="' +
                escapeHtml(src) +
                '" data-alt="' +
                escapeHtml(it.alt_text || it.title || '') +
                '"><img src="' +
                escapeHtml(src) +
                '" alt=""></button>'
              );
            })
            .join('') +
          '</div>';
        mediaPanel.onclick = function (ev) {
          var pick = ev.target.closest('.dg-writer-media-btn');
          if (!pick) return;
          editor
            .chain()
            .focus()
            .setImage({
              src: pick.getAttribute('data-url') || '',
              alt: pick.getAttribute('data-alt') || '',
            })
            .run();
          mediaPanel.hidden = true;
          syncHidden();
        };
      })
      .catch(function () {
        mediaPanel.innerHTML =
          '<p class="dg-field-hint">Mediathek nicht erreichbar (Rechte oder Netzwerk).</p>';
      });
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  window.dgWriterTipTap = editor;
})();
