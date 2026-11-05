/**
 * Mitarbeiter: Akademie-Kurs/Video in „Schulungsvideo / Online-Link“ übernehmen.
 */
(function () {
  'use strict';

  function bind(root) {
    const input = root.querySelector('[data-training-video-url]');
    const pick = root.querySelector('[data-training-video-pick]');
    if (!input || !pick) {
      return;
    }

    pick.addEventListener('change', function () {
      const url = (pick.value || '').trim();
      if (!url) {
        return;
      }
      input.value = url;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      input.focus();
    });
  }

  document.querySelectorAll('[data-training-video-field]').forEach(bind);
})();
