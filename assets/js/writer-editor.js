/**
 * Dokumentenverfasser D3 — TipTap-Editor (Absätze, Tabellen, Emoji, Lucide, Mediathek).
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
      Placeholder.configure({
        placeholder: 'Text schreiben … Absätze mit Enter. Tabellen und Medien über die Leiste.',
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
    content: initialDoc || (cfg.initialHtml || '<p></p>'),
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
      fn();
      editor.chain().focus().run();
      syncHidden();
    });
  }

  btn('dg-writer-cmd-bold', function () {
    editor.chain().focus().toggleBold().run();
  });
  btn('dg-writer-cmd-italic', function () {
    editor.chain().focus().toggleItalic().run();
  });
  btn('dg-writer-cmd-paragraph', function () {
    editor.chain().focus().setParagraph().run();
  });
  btn('dg-writer-cmd-bullet', function () {
    editor.chain().focus().toggleBulletList().run();
  });
  btn('dg-writer-cmd-ordered', function () {
    editor.chain().focus().toggleOrderedList().run();
  });
  btn('dg-writer-cmd-table', function () {
    editor
      .chain()
      .focus()
      .insertTable({ rows: 3, cols: 3, withHeaderRow: true })
      .run();
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
        fill(tab.getAttribute('data-group'));
        return;
      }
      var pick = ev.target.closest('.dg-writer-emoji-btn');
      if (pick) {
        editor.chain().focus().insertContent(pick.getAttribute('data-emoji') || '').run();
        emojiPanel.hidden = true;
        syncHidden();
      }
    };
  }

  /* —— Lucide —— */
  var lucidePanel = document.getElementById('dg-writer-lucide-panel');
  btn('dg-writer-cmd-lucide', function () {
    if (!lucidePanel) return;
    lucidePanel.hidden = !lucidePanel.hidden;
    if (!lucidePanel.hidden) {
      renderLucidePanel('');
    }
  });

  function catalogIcons() {
    try {
      var el = document.getElementById('dg-menu-icon-catalog');
      var data = el ? JSON.parse(el.textContent || '{}') : {};
      return data.icons || [];
    } catch (e) {
      return [];
    }
  }

  function lucideDataUrl(paths) {
    var svg =
      '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#333333" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' +
      paths +
      '</svg>';
    return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
  }

  function renderLucidePanel(query) {
    if (!lucidePanel) return;
    var icons = catalogIcons();
    var q = String(query || '')
      .trim()
      .toLowerCase();
    if (q) {
      icons = icons.filter(function (ic) {
        return (
          String(ic.id || '')
            .toLowerCase()
            .indexOf(q) !== -1 ||
          String(ic.label || '')
            .toLowerCase()
            .indexOf(q) !== -1
        );
      });
    }
    icons = icons.slice(0, 120);
    lucidePanel.innerHTML =
      '<label class="dg-field"><span>Lucide suchen</span><input type="search" id="dg-writer-lucide-search" value="' +
      escapeHtml(query || '') +
      '" placeholder="z. B. mail, check"></label>' +
      '<div class="dg-writer-picker__grid dg-writer-lucide-grid">' +
      icons
        .map(function (ic) {
          return (
            '<button type="button" class="dg-writer-lucide-btn" data-id="' +
            escapeHtml(ic.id) +
            '" title="' +
            escapeHtml(ic.label || ic.id) +
            '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" width="22" height="22">' +
            (ic.paths || '') +
            '</svg></button>'
          );
        })
        .join('') +
      '</div>';
    var search = document.getElementById('dg-writer-lucide-search');
    if (search) {
      search.addEventListener('input', function () {
        renderLucidePanel(search.value);
      });
      search.focus();
    }
    lucidePanel.onclick = function (ev) {
      var pick = ev.target.closest('.dg-writer-lucide-btn');
      if (!pick) return;
      var id = pick.getAttribute('data-id');
      var ic = catalogIcons().find(function (x) {
        return x.id === id;
      });
      if (!ic || !ic.paths) return;
      editor
        .chain()
        .focus()
        .setImage({
          src: lucideDataUrl(ic.paths),
          alt: ic.label || ic.id,
          title: ic.id,
        })
        .run();
      lucidePanel.hidden = true;
      syncHidden();
    };
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

  function loadMediaPanel() {
    if (!mediaPanel) return;
    mediaPanel.innerHTML = '<p class="dg-field-hint">Mediathek wird geladen …</p>';
    var url = cfg.mediaListUrl || '/api/media?action=list';
    fetch(url, { credentials: 'same-origin' })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        var items = (data && data.items) || [];
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
