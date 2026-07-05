(function () {
  'use strict';

  var form = document.getElementById('dg-mail-address-form');
  if (!form) {
    return;
  }

  var presetSelect = document.getElementById('dg-mail-preset');
  var patternInput = document.getElementById('dg-mail-local-pattern');
  var separatorInput = document.getElementById('dg-mail-separator');
  var previewEl = document.getElementById('dg-mail-address-preview');

  var presets = {
    v1_dot_nn: '{V1}{TRENNER}{NN}',
    vn_dot_nn: '{VN}{TRENNER}{NN}',
    vn_nn: '{VN}{NN}',
    login: '{LOGIN}',
    v1_nn: '{V1}{NN}',
    nn_dot_vn: '{NN}{TRENNER}{VN}',
    vn_underscore_nn: '{VN}_{NN}'
  };

  function updatePatternFromPreset() {
    if (!presetSelect || !patternInput) {
      return;
    }
    var value = presetSelect.value;
    if (value !== 'custom' && presets[value]) {
      patternInput.value = presets[value];
    }
  }

  function refreshPreview() {
    if (!previewEl) {
      return;
    }
    var params = new URLSearchParams({
      first_name: 'Max',
      last_name: 'Mustermann',
      login: 'maxm',
      separator: separatorInput ? separatorInput.value : '.',
      local_pattern: patternInput ? patternInput.value : ''
    });
    if (presetSelect && presetSelect.value !== 'custom') {
      params.set('preset', presetSelect.value);
    }
    fetch('/api/mail-address-preview?' + params.toString(), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.ok) {
          previewEl.textContent = 'Vorschau: ' + data.email;
        } else {
          previewEl.textContent = 'Vorschau: —';
        }
      })
      .catch(function () {
        previewEl.textContent = 'Vorschau: —';
      });
  }

  if (presetSelect) {
    presetSelect.addEventListener('change', function () {
      updatePatternFromPreset();
      refreshPreview();
    });
  }

  [patternInput, separatorInput].forEach(function (el) {
    if (el) {
      el.addEventListener('input', refreshPreview);
    }
  });

  form.querySelectorAll('[data-mail-token]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!patternInput) {
        return;
      }
      var token = btn.getAttribute('data-mail-token') || '';
      patternInput.value += token;
      if (presetSelect) {
        presetSelect.value = 'custom';
      }
      refreshPreview();
    });
  });

  updatePatternFromPreset();
  refreshPreview();
})();
