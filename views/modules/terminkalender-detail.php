<?php
/** @var Booking $booking */
?>
<div class="dg-wrap">
  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <?php View::partial('partials/back-nav', [
          'href' => '/app?page=terminkalender',
          'label' => 'Zurück zur Liste',
      ]); ?>
      <h1 class="dg-page-title"><?= View::escape($booking->customerName) ?></h1>
      <p class="dg-lead"><?= View::escape($booking->slotFormatted()) ?> · <?= View::escape($booking->statusLabel()) ?></p>
    </div>
    <div class="dg-toolbar">
      <?php if ($canEdit ?? false) : ?>
        <a class="dg-button" href="/app?page=terminkalender&amp;action=edit&amp;id=<?= $booking->id ?>">Bearbeiten</a>
      <?php endif; ?>
    </div>
  </header>

  <div class="dg-detail-grid">
    <section class="dg-panel">
      <h2>Termin</h2>
      <dl class="dg-dl">
        <dt>Datum/Uhrzeit</dt><dd><?= View::escape($booking->slotFormatted()) ?></dd>
        <dt>Leistung</dt><dd><?= $booking->articleId > 0 ? View::escape(CalendarArticleRepository::title($booking->articleId)) : '—' ?><?php if ($booking->articleId > 0) : ?> · <?= View::escape(CalendarArticleValidator::formatPrice(CalendarArticleRepository::priceGross($booking->articleId))) ?><?php endif; ?></dd>
        <dt>Mitarbeiter</dt><dd><?= $booking->employeeId > 0 ? View::escape(CalendarStaffRepository::employeeName($booking->employeeId)) : '—' ?></dd>
        <dt>Status</dt><dd><?= View::escape($booking->statusLabel()) ?></dd>
        <dt>Erstellt</dt><dd><?= View::escape($booking->createdAt !== '' ? date('d.m.Y H:i', strtotime($booking->createdAt) ?: 0) : '—') ?></dd>
      </dl>
    </section>
    <section class="dg-panel">
      <h2>Kunde</h2>
      <dl class="dg-dl">
        <dt>Name</dt><dd><?= View::escape($booking->customerName) ?></dd>
        <dt>E-Mail</dt><dd><?= $booking->customerEmail !== '' ? '<a href="mailto:' . View::escape($booking->customerEmail) . '">' . View::escape($booking->customerEmail) . '</a>' : '—' ?></dd>
        <dt>Telefon</dt><dd><?= View::escape($booking->customerPhone !== '' ? $booking->customerPhone : '—') ?></dd>
      </dl>
    </section>
    <?php if ($booking->adminNotes !== '') : ?>
      <section class="dg-panel dg-panel--wide">
        <h2>Notizen</h2>
        <p><?= nl2br(View::escape($booking->adminNotes)) ?></p>
      </section>
    <?php endif; ?>
  </div>
</div>
