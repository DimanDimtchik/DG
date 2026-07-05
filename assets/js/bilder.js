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
})();
