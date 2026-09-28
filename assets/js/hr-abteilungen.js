document.addEventListener('DOMContentLoaded', () => {
  const accordion = document.getElementById('dg-hr-abteilungen-accordion');
  if (!accordion) {
    return;
  }

  const expandAllBtn = document.getElementById('dg-hr-dept-expand-all');
  const collapseAllBtn = document.getElementById('dg-hr-dept-collapse-all');

  function setOpen(card, open) {
    const panel = card.querySelector('[data-hr-dept-panel]');
    const toggle = card.querySelector('[data-hr-dept-toggle]');
    if (!panel || !toggle) {
      return;
    }
    panel.hidden = !open;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    card.classList.toggle('is-open', open);
  }

  function setAllOpen(open) {
    accordion.querySelectorAll('[data-hr-dept-card]').forEach((card) => {
      setOpen(card, open);
    });
  }

  accordion.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-hr-dept-toggle]');
    if (!toggle || !accordion.contains(toggle)) {
      return;
    }
    const card = toggle.closest('[data-hr-dept-card]');
    if (!card) {
      return;
    }
    const open = toggle.getAttribute('aria-expanded') !== 'true';
    setOpen(card, open);
  });

  if (expandAllBtn) {
    expandAllBtn.addEventListener('click', () => setAllOpen(true));
  }
  if (collapseAllBtn) {
    collapseAllBtn.addEventListener('click', () => setAllOpen(false));
  }

  // Standard: alle zugeklappt
  setAllOpen(false);
});
