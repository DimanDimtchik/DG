document.addEventListener('DOMContentLoaded', () => {
  const config = window.dgFontSettings;
  if (!config) {
    return;
  }

  const families = config.families || {};
  const customInputs = {
    ui: document.querySelector('[name="custom_ui_font"]'),
    email: document.querySelector('[name="custom_email_font"]'),
  };
  const emailSizeInput = document.querySelector('[name="email_font_size"]');

  function familyFor(scope) {
    const select = document.querySelector(`[data-font-select="${scope}"]`);
    if (!select) {
      return families.system || 'system-ui, sans-serif';
    }
    if (select.value === 'custom') {
      const custom = customInputs[scope]?.value?.trim();
      return custom || families.system || 'system-ui, sans-serif';
    }
    return families[select.value] || families.system || 'system-ui, sans-serif';
  }

  function syncCustomVisibility(scope) {
    const select = document.querySelector(`[data-font-select="${scope}"]`);
    const customField = document.querySelector(`[data-font-custom="${scope}"]`);
    if (!select || !customField) {
      return;
    }
    customField.hidden = select.value !== 'custom';
  }

  function updatePreview(scope) {
    const preview = document.querySelector(`[data-font-preview="${scope}"]`);
    if (!preview) {
      return;
    }
    preview.style.fontFamily = familyFor(scope);
    if (scope === 'email' && emailSizeInput) {
      preview.style.fontSize = `${emailSizeInput.value || 16}px`;
    }
  }

  document.querySelectorAll('[data-font-select]').forEach((select) => {
    const scope = select.getAttribute('data-font-select');
    select.addEventListener('change', () => {
      syncCustomVisibility(scope);
      updatePreview(scope);
    });
  });

  Object.entries(customInputs).forEach(([scope, input]) => {
    input?.addEventListener('input', () => updatePreview(scope));
  });
  emailSizeInput?.addEventListener('input', () => updatePreview('email'));

  ['ui', 'email'].forEach((scope) => {
    syncCustomVisibility(scope);
    updatePreview(scope);
  });
});
