(function () {
  const form = document.getElementById('dg-crm-theme-form');
  const preview = document.getElementById('dg-crm-theme-preview');
  const notice = document.getElementById('dg-crm-theme-preset-notice');
  const presets = (window.dgCrmTheme && window.dgCrmTheme.presets) || {};

  if (!form || !preview) {
    return;
  }

  function readColor(key) {
    return form.querySelector('[data-color-key="' + key + '"]')?.value || '';
  }

  function hexToRgb(hex) {
    const value = hex.replace('#', '');
    return {
      r: parseInt(value.substring(0, 2), 16),
      g: parseInt(value.substring(2, 4), 16),
      b: parseInt(value.substring(4, 6), 16),
    };
  }

  function mixHex(hex1, hex2, weight) {
    const c1 = hexToRgb(hex1);
    const c2 = hexToRgb(hex2);
    const r = Math.round(c1.r * weight + c2.r * (1 - weight));
    const g = Math.round(c1.g * weight + c2.g * (1 - weight));
    const b = Math.round(c1.b * weight + c2.b * (1 - weight));
    return '#' + [r, g, b].map((part) => part.toString(16).padStart(2, '0')).join('');
  }

  function withAlpha(hex, alpha) {
    const rgb = hexToRgb(hex);
    return 'rgba(' + rgb.r + ', ' + rgb.g + ', ' + rgb.b + ', ' + alpha + ')';
  }

  function deriveColors(base) {
    const menuBg = base.menu_bg || '#59524c';
    const menuText = base.menu_text || '#ffffff';
    const brand = base.brand || '#03a9f4';
    const primary = base.primary || '#2271b1';

    return Object.assign({}, base, {
      menu_bg_hover: mixHex(menuBg, '#000000', 0.92),
      menu_bg_active: mixHex(menuBg, '#000000', 0.84),
      menu_border: mixHex(menuBg, '#000000', 0.84),
      menu_text_muted: withAlpha(menuText, 0.72),
      brand_dark: mixHex(brand, '#000000', 0.88),
      focus_ring: withAlpha(primary, 0.25),
    });
  }

  function applyVars(colors) {
    const expanded = deriveColors(colors);
    Object.entries(expanded).forEach(([key, value]) => {
      const cssName = '--dg-' + key.replace(/_/g, '-');
      preview.style.setProperty(cssName, value);
    });
  }

  function showNotice(text) {
    if (!notice) {
      return;
    }
    notice.textContent = text;
    notice.hidden = false;
  }

  function collectFormColors() {
    const colors = {};
    form.querySelectorAll('[data-color-key]').forEach((input) => {
      colors[input.getAttribute('data-color-key')] = input.value;
    });
    return colors;
  }

  function applyPreset(colors) {
    Object.entries(colors).forEach(([key, value]) => {
      const input = form.querySelector('[data-color-key="' + key + '"]');
      if (input && value) {
        input.value = value;
      }
    });
    applyVars(collectFormColors());
  }

  form.querySelectorAll('[data-color-key]').forEach((input) => {
    input.addEventListener('input', () => {
      document.querySelectorAll('.dg-crm-theme-preset').forEach((button) => {
        button.classList.remove('is-active');
      });
      if (notice) {
        notice.hidden = true;
      }
      applyVars(collectFormColors());
    });
  });

  document.querySelectorAll('.dg-crm-theme-preset').forEach((button) => {
    button.addEventListener('click', () => {
      const presetId = button.getAttribute('data-preset-id');
      const colors = presets[presetId];
      if (!colors) {
        return;
      }

      applyPreset(colors);
      document.querySelectorAll('.dg-crm-theme-preset').forEach((other) => {
        other.classList.remove('is-active');
      });
      button.classList.add('is-active');
      showNotice('Vorlage angewendet. Bitte auf „Software Design speichern“ klicken, um die Änderung zu übernehmen.');
    });
  });

  applyVars(collectFormColors());
})();
