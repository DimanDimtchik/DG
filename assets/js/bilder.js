(function () {
  'use strict';

  var cfg = window.dgMedia || {};
  var apiUrl = cfg.apiUrl || '/api/media';
  var csrf = cfg.csrf || '';
  var root = document.querySelector('.dg-media-edit');
  var isNew = root && root.getAttribute('data-media-new') === '1';

  function post(action, fields, fileField) {
    var fd = new FormData();
    fd.append('action', action);
    fd.append('_csrf', csrf);
    Object.keys(fields).forEach(function (key) {
      if (fields[key] !== undefined && fields[key] !== null) {
        fd.append(key, fields[key]);
      }
    });
    if (fileField) {
      fd.append('file', fileField.blob, fileField.name);
    }
    return fetch(apiUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) {
      return r.json();
    });
  }

  function handleResponse(res, onRedirect) {
    if (!res || !res.success) {
      alert((res && res.message) || 'Aktion fehlgeschlagen.');
      return;
    }
    if (res.data && res.data.message) {
      alert(res.data.message);
    }
    if (res.data && res.data.redirect) {
      window.location.href = res.data.redirect;
      return;
    }
    if (res.data && res.data.reload) {
      window.location.reload();
      return;
    }
    if (onRedirect) {
      onRedirect(res.data || {});
    }
  }

  function scanMessage(data) {
    var refs = data.references || 0;
    var closed = data.closed || 0;
    var lines = ['Scan abgeschlossen.', ''];
    if (refs === 0) {
      lines.push('Keine Verwendung dieses Bildes im CRM gefunden.');
      lines.push('(Das Bild ist in keiner Seite oder Einstellung referenziert.)');
    } else {
      lines.push('Gefundene Referenzen: ' + refs);
    }
    if (closed > 0) {
      lines.push('Beendete frühere Nutzungen: ' + closed);
    }
    return lines.join('\n');
  }

  function bindScan(button) {
    if (!button) return;
    button.addEventListener('click', function () {
      button.disabled = true;
      post('scan', {})
        .then(function (res) {
          button.disabled = false;
          if (!res.success) {
            alert(res.message || 'Scan fehlgeschlagen.');
            return;
          }
          alert(scanMessage(res.data || {}));
          window.location.reload();
        })
        .catch(function () {
          button.disabled = false;
          alert('Netzwerkfehler.');
        });
    });
  }

  function maybeShrinkBeforeUpload(file) {
    if (!window.createImageBitmap || file.type === 'image/svg+xml') {
      return Promise.resolve(file);
    }
    if (file.size < 3 * 1024 * 1024) {
      return Promise.resolve(file);
    }
    return createImageBitmap(file)
      .then(function (bitmap) {
        var maxEdge = 2400;
        var w = bitmap.width;
        var h = bitmap.height;
        if (w <= maxEdge && h <= maxEdge) {
          bitmap.close();
          return file;
        }
        var ratio = Math.min(maxEdge / w, maxEdge / h);
        var nw = Math.max(1, Math.round(w * ratio));
        var nh = Math.max(1, Math.round(h * ratio));
        var canvas = document.createElement('canvas');
        canvas.width = nw;
        canvas.height = nh;
        var ctx = canvas.getContext('2d');
        if (!ctx) {
          bitmap.close();
          return file;
        }
        ctx.drawImage(bitmap, 0, 0, nw, nh);
        bitmap.close();
        return new Promise(function (resolve) {
          canvas.toBlob(
            function (blob) {
              if (!blob) {
                resolve(file);
                return;
              }
              resolve(new File([blob], file.name.replace(/\.[^.]+$/, '.jpg'), { type: 'image/jpeg' }));
            },
            'image/jpeg',
            0.88
          );
        });
      })
      .catch(function () {
        return file;
      });
  }

  function runUpload(file, extra) {
    var fields = extra || {};
    var saveBtn = document.getElementById('dg-media-save-primary');
    if (saveBtn) saveBtn.disabled = true;
    maybeShrinkBeforeUpload(file).then(function (prepared) {
      post('upload', fields, { blob: prepared, name: prepared.name }).then(function (res) {
        if (saveBtn) saveBtn.disabled = false;
        handleResponse(res, function (data) {
          if (data.redirect) window.location.href = data.redirect;
        });
      });
    });
  }

  var uploadInput = document.getElementById('dg-media-upload');
  if (uploadInput && isNew) {
    uploadInput.addEventListener('change', function () {
      var file = uploadInput.files && uploadInput.files[0];
      var preview = document.getElementById('dg-media-preview');
      var placeholder = document.getElementById('dg-media-preview-placeholder');
      if (!file || !preview) return;
      preview.hidden = false;
      preview.classList.remove('dg-media-preview--empty');
      preview.src = URL.createObjectURL(file);
      if (placeholder) placeholder.hidden = true;
    });
  }

  bindScan(document.getElementById('dg-media-scan'));
  bindScan(document.getElementById('dg-media-scan-inline'));

  var metaForm = document.getElementById('dg-media-meta-form');
  if (metaForm) {
    metaForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(metaForm);
      var payload = {
        title: fd.get('title'),
        alt_text: fd.get('alt_text'),
        source_note: fd.get('source_note'),
      };

      if (isNew) {
        var file = uploadInput && uploadInput.files && uploadInput.files[0];
        if (!file) {
          alert('Bitte zuerst eine Bilddatei wählen.');
          return;
        }
        runUpload(file, payload);
        return;
      }

      post('save_meta', {
        media_id: fd.get('media_id'),
        title: payload.title,
        alt_text: payload.alt_text,
        source_note: payload.source_note,
      }).then(function (res) {
        handleResponse(res);
      });
    });
  }

  var transformForm = document.getElementById('dg-media-transform-form');
  if (transformForm) {
    var origW = parseInt(transformForm.getAttribute('data-orig-width') || '0', 10);
    var origH = parseInt(transformForm.getAttribute('data-orig-height') || '0', 10);
    var widthInput = transformForm.querySelector('[name="max_width"]');
    var heightInput = transformForm.querySelector('[name="max_height"]');
    var keepAspectCheck = transformForm.querySelector('[name="keep_aspect"]');
    var transformCalc = document.getElementById('dg-media-transform-calc');
    var aspectSyncing = false;

    function calcPairedDimension(changed, value) {
      if (changed === 'width') {
        return Math.max(1, Math.round((value * origH) / origW));
      }
      return Math.max(1, Math.round((value * origW) / origH));
    }

    function updateTransformCalc(changed, value, paired) {
      if (!transformCalc || !keepAspectCheck || !keepAspectCheck.checked || origW < 1 || origH < 1) {
        if (transformCalc) {
          transformCalc.hidden = true;
          transformCalc.textContent = '';
        }
        return;
      }
      if (!(value > 0) || !(paired > 0)) {
        transformCalc.hidden = true;
        transformCalc.textContent = '';
        return;
      }
      if (changed === 'width') {
        transformCalc.textContent =
          'Höhe berechnet: ' +
          origH +
          ' × (' +
          value +
          ' ÷ ' +
          origW +
          ') = ' +
          paired +
          ' px';
      } else {
        transformCalc.textContent =
          'Breite berechnet: ' +
          origW +
          ' × (' +
          value +
          ' ÷ ' +
          origH +
          ') = ' +
          paired +
          ' px';
      }
      transformCalc.hidden = false;
    }

    function syncTransformHeightFromWidth() {
      if (aspectSyncing || !keepAspectCheck || !keepAspectCheck.checked || origW < 1 || origH < 1) return;
      var w = parseInt(widthInput.value, 10);
      if (!(w > 0)) {
        updateTransformCalc('width', 0, 0);
        return;
      }
      var h = calcPairedDimension('width', w);
      aspectSyncing = true;
      heightInput.value = String(h);
      aspectSyncing = false;
      updateTransformCalc('width', w, h);
    }

    function syncTransformWidthFromHeight() {
      if (aspectSyncing || !keepAspectCheck || !keepAspectCheck.checked || origW < 1 || origH < 1) return;
      var h = parseInt(heightInput.value, 10);
      if (!(h > 0)) {
        updateTransformCalc('height', 0, 0);
        return;
      }
      var w = calcPairedDimension('height', h);
      aspectSyncing = true;
      widthInput.value = String(w);
      aspectSyncing = false;
      updateTransformCalc('height', h, w);
    }

    if (widthInput && heightInput && keepAspectCheck && origW > 0 && origH > 0) {
      widthInput.addEventListener('input', syncTransformHeightFromWidth);
      heightInput.addEventListener('input', syncTransformWidthFromHeight);
      keepAspectCheck.addEventListener('change', function () {
        if (keepAspectCheck.checked) {
          if (widthInput.value) {
            syncTransformHeightFromWidth();
          } else if (heightInput.value) {
            syncTransformWidthFromHeight();
          }
        } else if (transformCalc) {
          transformCalc.hidden = true;
          transformCalc.textContent = '';
        }
      });
    }

    transformForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(transformForm);
      post('transform', {
        media_id: fd.get('media_id'),
        max_width: fd.get('max_width'),
        max_height: fd.get('max_height'),
        target_format: fd.get('target_format'),
        keep_aspect: keepAspectCheck && keepAspectCheck.checked ? '1' : '0',
      }).then(function (res) {
        handleResponse(res);
      });
    });
  }

  var logoCheckbox = document.getElementById('dg-media-use-logo');
  if (logoCheckbox) {
    logoCheckbox.addEventListener('change', function () {
      var mediaId = (document.querySelector('input[name="media_id"]') || {}).value;
      if (!mediaId) {
        logoCheckbox.checked = false;
        return;
      }
      var enabled = logoCheckbox.checked ? '1' : '0';
      logoCheckbox.disabled = true;
      post('set_logo', { media_id: mediaId, enabled: enabled })
        .then(function (res) {
          logoCheckbox.disabled = false;
          if (!res.success) {
            logoCheckbox.checked = !logoCheckbox.checked;
            alert(res.message || 'Logo konnte nicht gespeichert werden.');
            return;
          }
          if (res.data && res.data.message) {
            alert(res.data.message);
          }
          window.location.reload();
        })
        .catch(function () {
          logoCheckbox.disabled = false;
          logoCheckbox.checked = !logoCheckbox.checked;
          alert('Netzwerkfehler.');
        });
    });
  }

  var faviconCheckbox = document.getElementById('dg-media-use-favicon');
  if (faviconCheckbox) {
    faviconCheckbox.addEventListener('change', function () {
      var mediaId = (document.querySelector('input[name="media_id"]') || {}).value;
      if (!mediaId) {
        faviconCheckbox.checked = false;
        return;
      }
      var enabled = faviconCheckbox.checked ? '1' : '0';
      faviconCheckbox.disabled = true;
      post('set_favicon', { media_id: mediaId, enabled: enabled })
        .then(function (res) {
          faviconCheckbox.disabled = false;
          if (!res.success) {
            faviconCheckbox.checked = !faviconCheckbox.checked;
            alert(res.message || 'Favicon konnte nicht gespeichert werden.');
            return;
          }
          if (res.data && res.data.message) {
            alert(res.data.message);
          }
          window.location.reload();
        })
        .catch(function () {
          faviconCheckbox.disabled = false;
          faviconCheckbox.checked = !faviconCheckbox.checked;
          alert('Netzwerkfehler.');
        });
    });
  }

  var deleteBtn = document.getElementById('dg-media-delete');
  if (deleteBtn) {
    deleteBtn.addEventListener('click', function () {
      if (!confirm('Bild wirklich löschen?')) return;
      var mediaId = (document.querySelector('input[name="media_id"]') || {}).value;
      post('delete', { media_id: mediaId }).then(function (res) {
        handleResponse(res, function (data) {
          if (data.redirect) window.location.href = data.redirect;
        });
      });
    });
  }

  var cropOpen = document.getElementById('dg-media-crop-open');
  var cropModal = document.getElementById('dg-media-crop-modal');
  var cropImage = document.getElementById('dg-media-crop-image');
  var cropApply = document.getElementById('dg-media-crop-apply');
  var cropper = null;

  function destroyCropper() {
    if (cropper) {
      cropper.destroy();
      cropper = null;
    }
  }

  function closeCrop() {
    destroyCropper();
    if (cropModal) {
      cropModal.hidden = true;
      cropModal.setAttribute('aria-hidden', 'true');
    }
    if (cropImage) cropImage.src = '';
  }

  if (cropOpen && cropModal && cropImage) {
    cropOpen.addEventListener('click', function () {
      if (typeof window.Cropper === 'undefined') {
        alert('Cropper.js nicht geladen.');
        return;
      }
      var preview = document.getElementById('dg-media-preview');
      if (!preview || !preview.src) return;
      destroyCropper();
      cropModal.hidden = false;
      cropModal.setAttribute('aria-hidden', 'false');
      cropImage.onload = function () {
        destroyCropper();
        cropper = new window.Cropper(cropImage, {
          viewMode: 1,
          autoCropArea: 0.9,
          responsive: true,
          background: false,
        });
      };
      cropImage.src = preview.src;
      if (cropImage.complete) cropImage.onload();
    });

    cropModal.querySelectorAll('[data-crop-close]').forEach(function (el) {
      el.addEventListener('click', closeCrop);
    });

    if (cropApply) {
      cropApply.addEventListener('click', function () {
        if (!cropper) return;
        var canvas = cropper.getCroppedCanvas({ imageSmoothingQuality: 'high' });
        if (!canvas) {
          alert('Zuschnitt fehlgeschlagen.');
          return;
        }
        cropApply.disabled = true;
        canvas.toBlob(function (blob) {
          if (!blob) {
            cropApply.disabled = false;
            alert('Zuschnitt fehlgeschlagen.');
            return;
          }
          var mediaId = (document.querySelector('input[name="media_id"]') || {}).value;
          post('crop', { media_id: mediaId, variant: 'crop' }, { blob: blob, name: 'crop.png' }).then(function (res) {
            cropApply.disabled = false;
            if (res.success) closeCrop();
            handleResponse(res);
          });
        }, 'image/png');
      });
    }
  }

  var BG_REMOVAL_VERSION = '1.4.5';
  var BG_REMOVAL_URL = 'https://esm.sh/@imgly/background-removal@' + BG_REMOVAL_VERSION;
  var BG_REMOVAL_PUBLIC_PATH =
    'https://staticimgly.com/@imgly/background-removal-data/' + BG_REMOVAL_VERSION + '/dist/';

  function loadBackgroundRemoval() {
    return import(BG_REMOVAL_URL).then(function (mod) {
      var fn = mod.removeBackground || mod.default;
      if (typeof fn !== 'function') {
        throw new Error('Freistellen-Modul konnte nicht geladen werden.');
      }
      return fn;
    });
  }

  var bgBtn = document.getElementById('dg-media-bg-remove');
  var bgStatus = document.getElementById('dg-media-bg-status');
  if (bgBtn) {
    bgBtn.addEventListener('click', function () {
      var preview = document.getElementById('dg-media-preview');
      if (!preview || !preview.src) {
        alert('Kein Bild geladen.');
        return;
      }
      bgBtn.disabled = true;
      if (bgStatus) bgStatus.textContent = 'Modell wird geladen — bitte warten …';
      fetch(preview.src, { credentials: 'same-origin' })
        .then(function (r) {
          if (!r.ok) {
            throw new Error('Bild konnte nicht geladen werden.');
          }
          return r.blob();
        })
        .then(function (blob) {
          return loadBackgroundRemoval().then(function (removeBackground) {
            if (bgStatus) bgStatus.textContent = 'Hintergrund wird entfernt …';
            return removeBackground(blob, {
              publicPath: BG_REMOVAL_PUBLIC_PATH,
              output: { format: 'image/png' },
            });
          });
        })
        .then(function (resultBlob) {
          var mediaId = (document.querySelector('input[name="media_id"]') || {}).value;
          return post('crop', { media_id: mediaId, variant: 'freigestellt' }, { blob: resultBlob, name: 'freigestellt.png' });
        })
        .then(function (res) {
          bgBtn.disabled = false;
          if (bgStatus) {
            bgStatus.textContent = res.success ? 'Freistellen abgeschlossen.' : res.message || 'Fehler.';
          }
          handleResponse(res);
        })
        .catch(function (err) {
          bgBtn.disabled = false;
          if (bgStatus) bgStatus.textContent = 'Freistellen fehlgeschlagen.';
          alert((err && err.message) || 'Freistellen fehlgeschlagen.');
        });
    });
  }

  // Live SVG markup for raster export (set by SVG editor when available).
  window.dgMediaBuildSvgMarkup = null;

  // --- SVG → PNG/JPEG/WebP export (canvas; same derived-media path as crop) ---
  (function initSvgExport() {
    var exportForm = document.getElementById('dg-media-svg-export-form');
    if (!exportForm) return;

    var widthInput = document.getElementById('dg-media-svg-export-w');
    var heightInput = document.getElementById('dg-media-svg-export-h');
    var formatSelect = document.getElementById('dg-media-svg-export-format');
    var keepAspectCheck = document.getElementById('dg-media-svg-export-aspect');
    var submitBtn = document.getElementById('dg-media-svg-export-submit');
    var origW = parseInt(exportForm.getAttribute('data-orig-width') || '0', 10) || 0;
    var origH = parseInt(exportForm.getAttribute('data-orig-height') || '0', 10) || 0;
    var aspectSyncing = false;

    function pairedDim(changed, value) {
      if (origW < 1 || origH < 1) return value;
      if (changed === 'width') return Math.max(1, Math.round(origH * (value / origW)));
      return Math.max(1, Math.round(origW * (value / origH)));
    }

    if (widthInput && heightInput && keepAspectCheck && origW > 0 && origH > 0) {
      widthInput.addEventListener('input', function () {
        if (aspectSyncing || !keepAspectCheck.checked) return;
        var w = parseInt(widthInput.value, 10);
        if (!(w > 0)) return;
        aspectSyncing = true;
        heightInput.value = String(pairedDim('width', w));
        aspectSyncing = false;
      });
      heightInput.addEventListener('input', function () {
        if (aspectSyncing || !keepAspectCheck.checked) return;
        var h = parseInt(heightInput.value, 10);
        if (!(h > 0)) return;
        aspectSyncing = true;
        widthInput.value = String(pairedDim('height', h));
        aspectSyncing = false;
      });
    }

    function applyRootSize(markup, width, height) {
      return markup.replace(/<svg\b[^>]*>/i, function (tag) {
        function setOrAdd(attrName, value) {
          var re = new RegExp('\\b' + attrName + '\\s*=\\s*(["\\\'])[^"\\\']*\\1', 'i');
          if (re.test(tag)) {
            return tag.replace(re, attrName + '="' + value + '"');
          }
          return tag.replace(/<svg\b/i, '<svg ' + attrName + '="' + value + '"');
        }
        if (!/\bviewBox\s*=/i.test(tag) && !/\bviewbox\s*=/i.test(tag)) {
          tag = setOrAdd('viewBox', '0 0 ' + (origW || width) + ' ' + (origH || height));
        }
        tag = setOrAdd('width', String(width));
        tag = setOrAdd('height', String(height));
        return tag;
      });
    }

    function loadSvgMarkup() {
      if (typeof window.dgMediaBuildSvgMarkup === 'function') {
        return Promise.resolve(window.dgMediaBuildSvgMarkup());
      }
      var preview = document.getElementById('dg-media-preview');
      if (!preview || !preview.src) {
        return Promise.reject(new Error('SVG konnte nicht geladen werden.'));
      }
      return fetch(preview.src, { credentials: 'same-origin' }).then(function (r) {
        if (!r.ok) throw new Error('SVG konnte nicht geladen werden.');
        return r.text();
      });
    }

    function rasterize(markup, width, height, mime) {
      var sized = applyRootSize(markup, width, height);
      var blob = new Blob([sized], { type: 'image/svg+xml;charset=utf-8' });
      var url = URL.createObjectURL(blob);
      return new Promise(function (resolve, reject) {
        var img = new Image();
        img.onload = function () {
          try {
            var canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            var ctx = canvas.getContext('2d');
            if (!ctx) {
              URL.revokeObjectURL(url);
              reject(new Error('Canvas nicht verfügbar.'));
              return;
            }
            if (mime === 'image/jpeg') {
              ctx.fillStyle = '#ffffff';
              ctx.fillRect(0, 0, width, height);
            }
            ctx.drawImage(img, 0, 0, width, height);
            URL.revokeObjectURL(url);
            var quality = mime === 'image/jpeg' ? 0.88 : mime === 'image/webp' ? 0.85 : undefined;
            canvas.toBlob(
              function (out) {
                if (!out) {
                  reject(new Error('Export fehlgeschlagen (Browser unterstützt das Format ggf. nicht).'));
                  return;
                }
                resolve(out);
              },
              mime,
              quality
            );
          } catch (err) {
            URL.revokeObjectURL(url);
            reject(err);
          }
        };
        img.onerror = function () {
          URL.revokeObjectURL(url);
          reject(new Error('SVG konnte nicht gerendert werden.'));
        };
        img.src = url;
      });
    }

    exportForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var w = parseInt(widthInput && widthInput.value, 10);
      var h = parseInt(heightInput && heightInput.value, 10);
      if (!(w > 0) || !(h > 0) || w > 10000 || h > 10000) {
        alert('Breite und Höhe müssen zwischen 1 und 10000 Pixel liegen.');
        return;
      }
      var formatKey = (formatSelect && formatSelect.value) || 'png';
      var mime =
        formatKey === 'jpeg' ? 'image/jpeg' : formatKey === 'webp' ? 'image/webp' : 'image/png';
      var ext = formatKey === 'jpeg' ? 'jpg' : formatKey;
      var mediaId = exportForm.getAttribute('data-media-id') || '';
      if (submitBtn) submitBtn.disabled = true;
      loadSvgMarkup()
        .then(function (markup) {
          return rasterize(markup, w, h, mime);
        })
        .then(function (outBlob) {
          return post(
            'crop',
            { media_id: mediaId, variant: 'raster' },
            { blob: outBlob, name: 'export.' + ext }
          );
        })
        .then(function (res) {
          if (submitBtn) submitBtn.disabled = false;
          handleResponse(res);
        })
        .catch(function (err) {
          if (submitBtn) submitBtn.disabled = false;
          alert((err && err.message) || 'Export fehlgeschlagen.');
        });
    });
  })();

  // --- SVG color / stroke-width / size editor ---
  (function initSvgEditor() {
    var form = document.getElementById('dg-media-svg-form');
    var dataEl = document.getElementById('dg-media-svg-data');
    var preview = document.getElementById('dg-media-preview');
    var resetBtn = document.getElementById('dg-media-svg-reset');
    var sizeW = document.getElementById('dg-media-svg-size-w');
    var sizeH = document.getElementById('dg-media-svg-size-h');
    var sizeSquare = document.getElementById('dg-media-svg-size-square');
    if (!form || !dataEl || !preview) return;

    var state;
    try {
      state = JSON.parse(dataEl.textContent || '{}');
    } catch (e) {
      return;
    }
    if (!state.markup) return;

    var originalMarkup = state.markup;
    var originalSize = state.size || {};
    var sizeMin = Number(state.size_min) || 16;
    var sizeMax = Number(state.size_max) || 4096;
    var previewObjectUrl = null;

    function escapeRegExp(value) {
      return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function clampSize(value) {
      var n = Math.round(Number(value));
      if (!isFinite(n)) return null;
      if (n < sizeMin || n > sizeMax) return null;
      return n;
    }

    function collectSize() {
      if (!sizeW || !sizeH) return null;
      var width = clampSize(sizeW.value);
      var height = clampSize(sizeH.value);
      if (width === null || height === null) return null;
      var origW = originalSize.width != null ? Number(originalSize.width) : null;
      var origH = originalSize.height != null ? Number(originalSize.height) : null;
      if (origW === width && origH === height) return null;
      return { width: width, height: height };
    }

    function collectMaps() {
      var colors = {};
      var widths = {};
      form.querySelectorAll('.dg-media-svg-color-text').forEach(function (input) {
        var from = input.getAttribute('data-svg-color-from') || '';
        var to = (input.value || '').trim();
        if (from && to && to.toLowerCase() !== from.toLowerCase()) {
          colors[from] = to;
        }
      });
      form.querySelectorAll('.dg-media-svg-width').forEach(function (input) {
        var from = input.getAttribute('data-svg-width-from') || '';
        var rawTo = (input.value || '').trim();
        if (!from || !rawTo) return;
        var unitMatch = String(from).match(/(px|em|rem|pt|%)$/i);
        var to = rawTo;
        if (unitMatch && !/(px|em|rem|pt|%)$/i.test(rawTo)) {
          to = rawTo + unitMatch[1];
        }
        if (to !== from) {
          widths[from] = to;
        }
      });
      return { colors: colors, widths: widths, size: collectSize() };
    }

    function replaceColor(markup, from, to) {
      var q = escapeRegExp(from);
      var attrs = 'fill|stroke|stop-color|flood-color|lighting-color|color';
      markup = markup.replace(
        new RegExp('(\\b(?:' + attrs + ')\\s*=\\s*)(["\\\'])(' + q + ')\\2', 'gi'),
        function (_m, prefix, quote) {
          return prefix + quote + to + quote;
        }
      );
      markup = markup.replace(
        new RegExp('((?:^|[;{"\\s])(?:' + attrs + ')\\s*:\\s*)(' + q + ')(?=\\s*[;}"\\\']|$)', 'gi'),
        function (_m, prefix) {
          return prefix + to;
        }
      );
      return markup;
    }

    function replaceWidth(markup, from, to) {
      var q = escapeRegExp(from);
      markup = markup.replace(
        new RegExp('(\\bstroke-width\\s*=\\s*)(["\\\'])(' + q + ')\\2', 'gi'),
        function (_m, prefix, quote) {
          return prefix + quote + to + quote;
        }
      );
      markup = markup.replace(
        new RegExp('((?:^|[;{"\\s])stroke-width\\s*:\\s*)(' + q + ')(?=\\s*[;}"\\\']|$)', 'gi'),
        function (_m, prefix) {
          return prefix + to;
        }
      );
      return markup;
    }

    function setOrAddAttr(tag, name, value) {
      var re = new RegExp('\\b' + escapeRegExp(name) + '\\s*=\\s*(["\\\'])[^"\\\']*\\1', 'i');
      if (re.test(tag)) {
        return tag.replace(re, name + '="' + value + '"');
      }
      return tag.replace(/<svg\b/i, '<svg ' + name + '="' + value + '"');
    }

    function applyRootSize(markup, width, height) {
      return markup.replace(/<svg\b[^>]*>/i, function (tag) {
        var hasViewBox = /\bviewBox\s*=/i.test(tag) || /\bviewbox\s*=/i.test(tag);
        if (!hasViewBox) {
          var vbW = originalSize.width || width;
          var vbH = originalSize.height || height;
          tag = setOrAddAttr(tag, 'viewBox', '0 0 ' + vbW + ' ' + vbH);
        }
        tag = setOrAddAttr(tag, 'width', String(width));
        tag = setOrAddAttr(tag, 'height', String(height));
        return tag;
      });
    }

    function buildMarkup() {
      var maps = collectMaps();
      var markup = originalMarkup;
      Object.keys(maps.colors)
        .sort(function (a, b) {
          return b.length - a.length;
        })
        .forEach(function (from) {
          markup = replaceColor(markup, from, maps.colors[from]);
        });
      Object.keys(maps.widths)
        .sort(function (a, b) {
          return b.length - a.length;
        })
        .forEach(function (from) {
          markup = replaceWidth(markup, from, maps.widths[from]);
        });
      if (maps.size) {
        markup = applyRootSize(markup, maps.size.width, maps.size.height);
      } else if (sizeW && sizeH) {
        var w = clampSize(sizeW.value);
        var h = clampSize(sizeH.value);
        if (w !== null && h !== null) {
          markup = applyRootSize(markup, w, h);
        }
      }
      return markup;
    }

    window.dgMediaBuildSvgMarkup = buildMarkup;

    function updatePreview() {
      var markup = buildMarkup();
      if (previewObjectUrl) {
        URL.revokeObjectURL(previewObjectUrl);
      }
      previewObjectUrl = URL.createObjectURL(new Blob([markup], { type: 'image/svg+xml' }));
      preview.src = previewObjectUrl;
      form.querySelectorAll('.dg-media-svg-row__swatch').forEach(function (swatch) {
        var text = swatch.parentElement && swatch.parentElement.querySelector('.dg-media-svg-color-text');
        if (text && text.value) {
          swatch.style.background = text.value;
        }
      });
    }

    function syncColorInputs(source) {
      var from = source.getAttribute('data-svg-color-from');
      if (!from) return;
      var value = source.value;
      form.querySelectorAll('[data-svg-color-from="' + from.replace(/"/g, '\\"') + '"]').forEach(function (el) {
        if (el === source) return;
        if (el.type === 'color') {
          var hex = value.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
          if (hex) {
            var h = hex[1];
            if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
            el.value = '#' + h.toLowerCase();
          }
        } else {
          el.value = value;
        }
      });
      updatePreview();
    }

    function setDirty(isDirty) {
      var hint = document.getElementById('dg-media-svg-dirty');
      if (hint) hint.hidden = !isDirty;
    }

    function setSquareSize(px) {
      if (!sizeW || !sizeH) return;
      sizeW.value = String(px);
      sizeH.value = String(px);
      if (sizeSquare) sizeSquare.checked = true;
      setDirty(true);
      updatePreview();
    }

    form.querySelectorAll('.dg-media-svg-color, .dg-media-svg-color-text').forEach(function (input) {
      input.addEventListener('input', function () {
        setDirty(true);
        syncColorInputs(input);
      });
    });
    form.querySelectorAll('.dg-media-svg-width').forEach(function (input) {
      input.addEventListener('input', function () {
        setDirty(true);
        updatePreview();
      });
    });
    form.querySelectorAll('[data-svg-size-preset]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var px = clampSize(btn.getAttribute('data-svg-size-preset'));
        if (px !== null) setSquareSize(px);
      });
    });
    if (sizeW) {
      sizeW.addEventListener('input', function () {
        if (sizeSquare && sizeSquare.checked && sizeH) {
          sizeH.value = sizeW.value;
        }
        setDirty(true);
        updatePreview();
      });
    }
    if (sizeH) {
      sizeH.addEventListener('input', function () {
        if (sizeSquare && sizeSquare.checked && sizeW) {
          sizeW.value = sizeH.value;
        }
        setDirty(true);
        updatePreview();
      });
    }
    if (sizeSquare) {
      sizeSquare.addEventListener('change', function () {
        if (sizeSquare.checked && sizeW && sizeH) {
          sizeH.value = sizeW.value;
          setDirty(true);
          updatePreview();
        }
      });
    }

    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        setDirty(false);
        (state.colors || []).forEach(function (color) {
          form.querySelectorAll('[data-svg-color-from="' + String(color.value).replace(/"/g, '\\"') + '"]').forEach(function (el) {
            if (el.type === 'color' && color.hex) {
              el.value = color.hex;
            } else if (el.type !== 'color') {
              el.value = color.value;
            }
          });
        });
        (state.stroke_widths || []).forEach(function (width) {
          form.querySelectorAll('[data-svg-width-from="' + String(width.value).replace(/"/g, '\\"') + '"]').forEach(function (el) {
            el.value = String(width.value).replace(/[^0-9.]/g, '') || width.value;
          });
        });
        if (sizeW && sizeH) {
          var rw = originalSize.width != null ? Number(originalSize.width) : 150;
          var rh = originalSize.height != null ? Number(originalSize.height) : rw;
          sizeW.value = String(rw);
          sizeH.value = String(rh);
          if (sizeSquare) sizeSquare.checked = rw === rh;
        }
        updatePreview();
      });
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var maps = collectMaps();
      if (
        Object.keys(maps.colors).length === 0 &&
        Object.keys(maps.widths).length === 0 &&
        !maps.size
      ) {
        alert('Keine Änderungen zum Speichern.');
        return;
      }
      if (sizeW && sizeH && (clampSize(sizeW.value) === null || clampSize(sizeH.value) === null)) {
        alert('Größe muss zwischen ' + sizeMin + ' und ' + sizeMax + ' Pixel liegen.');
        return;
      }
      var mediaId = form.getAttribute('data-media-id') || '';
      var saveBtn = document.getElementById('dg-media-svg-save');
      if (saveBtn) saveBtn.disabled = true;
      var payload = {
        media_id: mediaId,
        colors: JSON.stringify(maps.colors),
        stroke_widths: JSON.stringify(maps.widths),
      };
      if (maps.size) {
        payload.size = JSON.stringify(maps.size);
      }
      post('svg_save', payload)
        .then(function (res) {
          if (saveBtn) saveBtn.disabled = false;
          handleResponse(res);
        })
        .catch(function () {
          if (saveBtn) saveBtn.disabled = false;
          alert('Netzwerkfehler.');
        });
    });
  })();
})();
