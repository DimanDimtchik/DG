<?php
/** @var array{items: list<Booking>, total: int, page: int, per_page: int, total_pages: int} $bookingList */
/** @var string $bookingSearch */
/** @var array{type: string, message: string}|null $flash */
$list = $bookingList ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 20, 'total_pages' => 1];
$search = $bookingSearch ?? '';
$baseUrl = '/app?page=terminkalender';
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Terminkalender</h1>
      <p class="dg-lead">Buchungen und Termine – <?= (int) $list['total'] ?> Einträge</p>
    </div>
    <div class="dg-toolbar">
      <?php if ($canEdit ?? false) : ?>
        <a class="dg-button dg-button--primary" href="/app?page=terminkalender&amp;action=new">Neuer Termin</a>
      <?php endif; ?>
    </div>
  </header>

  <form class="dg-search" method="get" action="/app">
    <input type="hidden" name="page" value="terminkalender">
    <label class="dg-search__label" for="tk-search">Suchen</label>
    <input class="dg-search__input" type="search" id="tk-search" name="s" value="<?= View::escape($search) ?>" placeholder="Kunde, E-Mail, Status …">
    <button class="dg-button dg-button--primary" type="submit">Suchen</button>
    <?php if ($search !== '') : ?><a class="dg-button" href="<?= View::escape($baseUrl) ?>">Zurücksetzen</a><?php endif; ?>
  </form>

  <div class="dg-table-wrap">
    <table class="dg-table">
      <thead>
        <tr>
          <th>Termin</th>
          <th>Leistung</th>
          <th>Mitarbeiter</th>
          <th>Kunde</th>
          <th>E-Mail</th>
          <th>Telefon</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($list['items'] === []) : ?>
          <tr><td colspan="8" class="dg-table__empty">Keine Buchungen gefunden.</td></tr>
        <?php else : ?>
          <?php foreach ($list['items'] as $booking) : ?>
            <tr>
              <td><strong><?= View::escape($booking->slotFormatted()) ?></strong></td>
              <td><?= $booking->articleId > 0 ? View::escape(CalendarArticleRepository::title($booking->articleId)) : '—' ?><?php if ($booking->articleId > 0 && CalendarArticleRepository::priceGross($booking->articleId) > 0) : ?><br><small class="dg-muted"><?= View::escape(CalendarArticleValidator::formatPrice(CalendarArticleRepository::priceGross($booking->articleId))) ?></small><?php endif; ?></td>
              <td><?= $booking->employeeId > 0 ? View::escape(CalendarStaffRepository::employeeName($booking->employeeId)) : '—' ?></td>
              <td><?= View::escape($booking->customerName) ?></td>
              <td><?= $booking->customerEmail !== '' ? '<a href="mailto:' . View::escape($booking->customerEmail) . '">' . View::escape($booking->customerEmail) . '</a>' : '—' ?></td>
              <td><?= View::escape($booking->customerPhone !== '' ? $booking->customerPhone : '—') ?></td>
              <td><?= View::escape($booking->statusLabel()) ?></td>
              <td class="dg-table__actions">
                <?php if ($canEdit ?? false) : ?>
                  <a href="/app?page=terminkalender&amp;action=edit&amp;id=<?= $booking->id ?>">Bearbeiten</a>
                <?php else : ?>
                  <a href="/app?page=terminkalender&amp;id=<?= $booking->id ?>">Anzeigen</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($list['total_pages'] > 1) : ?>
    <nav class="dg-pagination" aria-label="Seiten">
      <?php if ($list['page'] > 1) : ?>
        <a href="<?= View::escape($baseUrl . ($search !== '' ? '&s=' . rawurlencode($search) : '') . '&paged=' . ($list['page'] - 1)) ?>">&laquo; Zurück</a>
      <?php endif; ?>
      <span>Seite <?= (int) $list['page'] ?> von <?= (int) $list['total_pages'] ?></span>
      <?php if ($list['page'] < $list['total_pages']) : ?>
        <a href="<?= View::escape($baseUrl . ($search !== '' ? '&s=' . rawurlencode($search) : '') . '&paged=' . ($list['page'] + 1)) ?>">Weiter &raquo;</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
</div>
