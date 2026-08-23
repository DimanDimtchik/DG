<?php
/** @var string $name dashboard|contacts|calendar|clock|settings|… */
$icon = $name ?? 'dashboard';
?>
<?php if ($icon === 'dashboard') : ?>
<svg class="dg-sidebar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5Z"/></svg>
<?php elseif ($icon === 'contacts') : ?>
<svg class="dg-sidebar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M5 20a7 7 0 0 1 14 0"/></svg>
<?php elseif ($icon === 'calendar') : ?>
<svg class="dg-sidebar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/></svg>
<?php elseif ($icon === 'clock') : ?>
<svg class="dg-sidebar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>
<?php elseif ($icon === 'settings') : ?>
<svg class="dg-sidebar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
<?php elseif ($icon === 'images') : ?>
<svg class="dg-sidebar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="m21 17-5.5-5.5a1.5 1.5 0 0 0-2.12 0L3 19"/></svg>
<?php elseif ($icon === 'profile') : ?>
<svg class="dg-sidebar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M5 20a7 7 0 0 1 14 0"/></svg>
<?php elseif ($icon === 'catalog') : ?>
<svg class="dg-sidebar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/></svg>
<?php elseif ($icon === 'mail') : ?>
<svg class="dg-sidebar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6M3 7v10l9 6 9-6V7"/></svg>
<?php elseif ($icon === 'accounting') : ?>
<svg class="dg-sidebar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M4 4h16v16H4z"/><path d="M8 8h8M8 12h8M8 16h5"/><path d="M4 9h16"/></svg>
<?php elseif ($icon === 'receipt') : ?>
<svg class="dg-sidebar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M6 3h12v18l-2-1.5L14 21l-2-1.5L10 21l-2-1.5L6 21V3z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>
<?php elseif ($icon === 'transfer') : ?>
<svg class="dg-sidebar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M4 9h13M4 9l4-4M4 9l4 4"/><path d="M20 15H7M20 15l-4-4M20 15l-4 4"/></svg>
<?php elseif ($icon === 'ledger') : ?>
<svg class="dg-sidebar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M5 3h14v18H5z"/><path d="M9 3v18"/><path d="M12 8h4M12 12h4M12 16h3"/></svg>
<?php elseif ($icon === 'yearclose') : ?>
<svg class="dg-sidebar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M4 5h16v16H4z"/><path d="M4 9h16M8 3v4M16 3v4"/><path d="M9.5 15l2 2 3.5-4"/></svg>
<?php elseif ($icon === 'document') : ?>
<svg class="dg-sidebar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M6 2h8l4 4v16H6z"/><path d="M14 2v4h4"/><path d="M9 13h6M9 17h6"/></svg>
<?php elseif ($icon === 'paperclip') : ?>
<svg class="dg-sidebar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M21 11.5 12 20.5a5 5 0 0 1-7-7l9-9a3.5 3.5 0 0 1 5 5l-8.5 8.5a2 2 0 0 1-3-3l8-8"/></svg>
<?php elseif ($icon === 'website') : ?>
<svg class="dg-sidebar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3.2 4.5 6.2 4.5 9S15 17.8 12 21c-3-3.2-4.5-6.2-4.5-9S9 6.2 12 3Z"/></svg>
<?php elseif ($icon === 'nav') : ?>
<svg class="dg-sidebar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
<?php elseif ($icon === 'layout') : ?>
<svg class="dg-sidebar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M3 15h18"/></svg>
<?php elseif ($icon === 'palette') : ?>
<svg class="dg-sidebar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M12 3a9 9 0 1 0 0 18h1.5a2.5 2.5 0 0 0 0-5H12"/><circle cx="7.5" cy="10" r="1"/><circle cx="10.5" cy="7.5" r="1"/><circle cx="14.5" cy="7.5" r="1"/><circle cx="16.5" cy="11" r="1"/></svg>
<?php endif; ?>
