<?php
/** @var User $user */
/** @var array{items: list<Contact>, total: int, page: int, per_page: int, total_pages: int} $contactList */
/** @var string $contactSearch */
/** @var array{type: string, message: string}|null $flash */
$search = $contactSearch ?? '';
$list = $contactList ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 20, 'total_pages' => 1];
$baseUrl = '/app?page=kontakte';
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Kontakte</h1>
      <p class="dg-lead">Benutzer, Kunden, Lieferanten und Firmen – <?= (int) $list['total'] ?> Einträge</p>
    </div>
    <div class="dg-toolbar">
      <?php if (ContactAccessResolver::canEditContact($user)) : ?>
        <a class="dg-button dg-button--primary" href="/app?page=kontakte&amp;action=new">Neuer Kontakt</a>
      <?php endif; ?>
    </div>
  </header>

  <form class="dg-search" method="get" action="/app">
    <input type="hidden" name="page" value="kontakte">
    <label class="dg-search__label" for="kontakte-search">Suchen</label>
    <input
      class="dg-search__input"
      type="search"
      id="kontakte-search"
      name="s"
      value="<?= View::escape($search) ?>"
      placeholder="Name, Firma, E-Mail, Kundennummer …"
    >
    <button class="dg-button dg-button--primary" type="submit">Suchen</button>
    <?php if ($search !== '') : ?>
      <a class="dg-button" href="<?= View::escape($baseUrl) ?>">Zurücksetzen</a>
    <?php endif; ?>
  </form>

  <div class="dg-table-wrap">
    <table class="dg-table">
      <thead>
        <tr>
          <th>Benutzername</th>
          <th>Anzeigename</th>
          <th>Firmenname</th>
          <th>Kundennummer</th>
          <th>E-Mail</th>
          <th>Rolle</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($list['items'] === []) : ?>
          <tr>
            <td colspan="7" class="dg-table__empty">Keine Kontakte gefunden.</td>
          </tr>
        <?php else : ?>
          <?php foreach ($list['items'] as $contact) : ?>
            <tr>
              <td><strong><?= View::escape($contact->login) ?></strong></td>
              <td><?= View::escape($contact->listLabel()) ?></td>
              <td><?= View::escape($contact->companyName) ?></td>
              <td><?= View::escape($contact->customerNumber) ?></td>
              <td>
                <?php if ($contact->email !== '') : ?>
                  <a href="mailto:<?= View::escape($contact->email) ?>"><?= View::escape($contact->email) ?></a>
                <?php else : ?>
                  —
                <?php endif; ?>
              </td>
              <td><?= View::escape($contact->roleLabel()) ?></td>
              <td class="dg-table__actions">
                <?php if (ContactAccessResolver::canEditContact($user, $contact)) : ?>
                  <a href="/app?page=kontakte&amp;action=edit&amp;id=<?= $contact->id ?>">Bearbeiten</a>
                <?php else : ?>
                  <a href="/app?page=kontakte&amp;id=<?= $contact->id ?>">Anzeigen</a>
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
