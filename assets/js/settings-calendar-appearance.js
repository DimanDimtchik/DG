(function () {
  const form = document.getElementById('dg-calendar-appearance-form');
  const preview = document.getElementById('dg-cal-appearance-preview');
  const notice = document.getElementById('dg-cal-preset-notice');
  const presets = (window.dgCalendarAppearance && window.dgCalendarAppearance.presets) || {};

  if (!form || !preview) {
    return;
  }

  function showNotice(text) {
    if (!notice) {
      return;
    }
    notice.textContent = text;
    notice.hidden = false;
  }

  function applyColorsToPreview(colors) {
    Object.entries(colors).forEach(([key, value]) => {
      const input = form.querySelector('[data-color-key="' + key + '"]');
      if (input && value) {
        input.value = value;
      }
    });
    updatePreview();
  }

  function updatePreview() {
    const primary = form.querySelector('[data-color-key="primary_color"]')?.value || '#0a74da';
    const hover = form.querySelector('[data-color-key="button_hover"]')?.value || '#0959a3';
    const slotBg = form.querySelector('[data-color-key="slot_bg"]')?.value || '#fdfdfd';
    const slotHover = form.querySelector('[data-color-key="slot_hover"]')?.value || '#f0f0f0';
    const slotSelectedBg = form.querySelector('[data-color-key="slot_selected_bg"]')?.value || '#e6f0ff';
    const slotSelectedBorder = form.querySelector('[data-color-key="slot_selected_border"]')?.value || '#0a74da';
    const bookedBg = form.querySelector('[data-color-key="booked_bg"]')?.value || '#f8f8f8';

    const luminance = (function (hex) {
      const value = hex.replace('#', '');
      const r = parseInt(value.substring(0, 2), 16);
      const g = parseInt(value.substring(2, 4), 16);
      const b = parseInt(value.substring(4, 6), 16);
      return (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    })(primary);

    preview.style.setProperty('--tk-cal-primary', primary);
    preview.style.setProperty('--tk-cal-primary-hover', hover);
    preview.style.setProperty('--tk-cal-on-primary', luminance > 0.58 ? '#1e293b' : '#ffffff');
    preview.style.setProperty('--tk-slot-bg', slotBg);
    preview.style.setProperty('--tk-slot-hover', slotHover);
    preview.style.setProperty('--tk-slot-selected-bg', slotSelectedBg);
    preview.style.setProperty('--tk-slot-selected-border', slotSelectedBorder);
    preview.style.setProperty('--tk-slot-booked-bg', bookedBg);
  }

  form.querySelectorAll('[data-color-key]').forEach((input) => {
    input.addEventListener('input', () => {
      document.querySelectorAll('.dg-cal-color-preset').forEach((button) => {
        button.classList.remove('is-active');
      });
      if (notice) {
        notice.hidden = true;
      }
      updatePreview();
    });
  });

  document.querySelectorAll('.dg-cal-color-preset').forEach((button) => {
    button.addEventListener('click', () => {
      const presetId = button.getAttribute('data-preset-id');
      const colors = presets[presetId];
      if (!colors) {
        return;
      }

      applyColorsToPreview(colors);
      document.querySelectorAll('.dg-cal-color-preset').forEach((other) => {
        other.classList.remove('is-active');
      });
      button.classList.add('is-active');
      showNotice('Vorlage angewendet. Bitte auf „Kalender Design speichern“ klicken, um die Änderung zu übernehmen.');
    });
  });

  updatePreview();
})();
