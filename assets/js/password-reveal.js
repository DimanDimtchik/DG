document.addEventListener('click', (event) => {
  const toggle = event.target.closest('[data-password-toggle]');
  if (!toggle) {
    return;
  }

  const inputId = toggle.getAttribute('aria-controls');
  const input = inputId ? document.getElementById(inputId) : null;
  if (!input) {
    return;
  }

  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
  const label = show ? 'Passwort verbergen' : 'Passwort anzeigen';
  toggle.setAttribute('aria-label', label);
  toggle.setAttribute('title', label);

  const showIcon = toggle.querySelector('.dg-password-field__icon--show');
  const hideIcon = toggle.querySelector('.dg-password-field__icon--hide');
  if (showIcon) {
    showIcon.hidden = show;
  }
  if (hideIcon) {
    hideIcon.hidden = !show;
  }
});
