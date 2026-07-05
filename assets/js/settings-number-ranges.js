document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('[data-number-range-form]');
  if (!form) {
    return;
  }

  const previewEl = document.getElementById('dg-number-range-preview');
  const counterHint = document.querySelector('[data-number-range-counter-hint]');
  const countryHint = document.getElementById('dg-number-range-country-hint');
  const partInputs = form.querySelectorAll('[data-number-range-part]');
  const csrf = form.querySelector('input[name="_csrf"]')?.value || '';
  let timer = null;
  let lastFocusedPart = partInputs[0] || null;

  partInputs.forEach((input) => {
    input.addEventListener('focus', () => {
      lastFocusedPart = input;
    });
  });

  form.querySelectorAll('[data-insert-code]').forEach((button) => {
    button.addEventListener('click', () => {
      const code = button.getAttribute('data-insert-code') || '';
      const target = lastFocusedPart || partInputs[0];
      if (!target || code === '') {
        return;
      }
      const start = target.selectionStart ?? target.value.length;
      const end = target.selectionEnd ?? target.value.length;
      target.value = target.value.slice(0, start) + code + target.value.slice(end);
      target.focus();
      const caret = start + code.length;
      target.setSelectionRange(caret, caret);
      schedulePreview();
    });
  });

  function schedulePreview() {
    if (timer) {
      clearTimeout(timer);
    }
    timer = setTimeout(refreshPreview, 200);
  }

  function refreshPreview() {
    const body = new FormData(form);
    body.append('_csrf', csrf);

    fetch('/api/number-range-preview', {
      method: 'POST',
      body,
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
      },
    })
      .then((response) => response.json())
      .then((payload) => {
        if (!payload.success || !payload.data) {
          return;
        }
        if (previewEl) {
          previewEl.textContent = payload.data.preview || '';
        }
        if (counterHint) {
          counterHint.textContent =
            'Dezimal ' +
            String(payload.data.sequence) +
            ' → Anzeige für {NR}: ' +
            String(payload.data.sequence_display);
        }
        if (countryHint) {
          countryHint.hidden = !payload.data.uses_country;
        }
      })
      .catch(() => {
        // Vorschau optional
      });
  }

  form.addEventListener('input', schedulePreview);
  form.addEventListener('change', schedulePreview);
});
