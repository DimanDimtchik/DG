<?php
/** @var array{items: list<Booking>, total: int, page: int, per_page: int, total_pages: int} $bookingList */
/** @var string $bookingSearch */
/** @var string $bookingPeriodFilter */
/** @var array{type: string, message: string}|null $flash */
$list = $bookingList ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 10, 'total_pages' => 1, 'per_page_key' => ListPagination::DEFAULT];
$search = $bookingSearch ?? '';
$periodFilter = $bookingPeriodFilter ?? '';
$perPageKey = (string) ($list['per_page_key'] ?? ListPagination::DEFAULT);
$baseUrl = '/app?page=terminkalender';
$listExtraQuery = array_filter([
    's' => $search !== '' ? $search : null,
    'period' => $periodFilter !== '' ? $periodFilter : null,
], static fn ($v) => $v !== null && $v !== '');
$filterChips = [
    '' => 'Alle',
    'heute' => 'Heute',
    'woche' => 'Diese Woche',
    'monat' => 'Diesen Monat',
    'zukunft' => 'Zukünftige',
    'vergangen' => 'Vergangene',
];
$filterUrl = static function (string $period) use ($baseUrl, $search, $perPageKey): string {
    $url = $baseUrl;
    if ($period !== '') {
        $url .= '&period=' . rawurlencode($period);
    }
    if ($search !== '') {
        $url .= '&s=' . rawurlencode($search);
    }
    $url .= '&per_page=' . rawurlencode($perPageKey);
    return $url;
};
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
    <?php if ($periodFilter !== '') : ?>
      <input type="hidden" name="period" value="<?= View::escape($periodFilter) ?>">
    <?php endif; ?>
    <label class="dg-search__label" for="tk-search">Suchen</label>
    <input class="dg-search__input" type="search" id="tk-search" name="s" value="<?= View::escape($search) ?>" placeholder="Kunde, E-Mail, Status …">
    <button class="dg-button dg-button--primary" type="submit">Suchen</button>
    <?php if ($search !== '' || $periodFilter !== '') : ?>
      <a class="dg-button" href="<?= View::escape($baseUrl) ?>">Zurücksetzen</a>
    <?php endif; ?>
  </form>

  <nav class="dg-subtabs" aria-label="Termine filtern">
    <?php foreach ($filterChips as $chipPeriod => $chipLabel) : ?>
      <?php $isActive = $periodFilter === $chipPeriod; ?>
      <a
        href="<?= View::escape($filterUrl($chipPeriod)) ?>"
        class="dg-subtabs__link<?= $isActive ? ' is-active' : '' ?>"
        <?= $isActive ? 'aria-current="true"' : '' ?>
      ><?= View::escape($chipLabel) ?></a>
    <?php endforeach; ?>
  </nav>

  <?php
    View::partial('partials/list-per-page', [
        'total' => (int) $list['total'],
        'perPageKey' => $perPageKey,
        'listPage' => 'terminkalender',
        'extraQuery' => $listExtraQuery,
    ]);
  ?>

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
              <td><?= ContactChannelLink::emailHtml($booking->customerEmail) ?></td>
              <td><?= ContactChannelLink::phoneHtml($booking->customerPhone) ?></td>
              <td><?= View::escape($booking->statusLabel()) ?></td>
              <td class="dg-table__actions">
                <?php if ($canEdit ?? false) : ?>
                  <a class="dg-button dg-button--small" href="/app?page=terminkalender&amp;action=edit&amp;id=<?= $booking->id ?>">Bearbeiten</a>
                <?php else : ?>
                  <a class="dg-button dg-button--small" href="/app?page=terminkalender&amp;id=<?= $booking->id ?>">Anzeigen</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php
    View::partial('partials/list-pagination-nav', [
        'total' => (int) $list['total'],
        'page' => (int) $list['page'],
        'totalPages' => (int) $list['total_pages'],
        'perPageKey' => $perPageKey,
        'listPage' => 'terminkalender',
        'extraQuery' => $listExtraQuery,
    ]);
  ?>
</div>
