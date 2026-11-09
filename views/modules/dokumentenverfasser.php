<?php
/**
 * Dokumentenverfasser D2 — Liste.
 *
 * @var array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int, per_page_key: string}|list<array<string, mixed>> $writerDocumentList
 * @var Contact|null $writerFilterContact
 * @var int $writerContactFilter
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
if (isset($writerDocumentList['items']) && is_array($writerDocumentList['items'])) {
    $list = $writerDocumentList;
} else {
    $flat = is_array($writerDocumentList ?? null) ? $writerDocumentList : [];
    $list = [
        'items' => $flat,
        'total' => count($flat),
        'page' => 1,
        'per_page' => max(1, count($flat)),
        'total_pages' => 1,
        'per_page_key' => 'all',
    ];
}
$docs = $list['items'];
$perPageKey = (string) ($list['per_page_key'] ?? WriterDocumentRepository::DEFAULT_PER_PAGE);
$statusLabels = WriterDocument::statusOptions();
$filter = (string) ($writerStatusFilter ?? '');
$contactFilter = (int) ($writerContactFilter ?? 0);
$filterContact = $writerFilterContact ?? null;
$sizeOptions = [
    '10' => '10',
    '50' => '50',
    '100' => '100',
    'all' => 'Alle',
];
$writerListUrl = static function (?string $status = null, ?int $contact = null, ?string $size = null, ?int $paged = null) use ($filter, $contactFilter, $perPageKey): string {
    $params = ['page' => 'dokumentenverfasser'];
    $statusVal = $status ?? $filter;
    if ($statusVal !== '') {
        $params['status'] = $statusVal;
    }
    $contactVal = $contact ?? $contactFilter;
    if ($contactVal > 0) {
        $params['contact'] = $contactVal;
    }
    $params['per_page'] = $size ?? $perPageKey;
    if ($paged !== null && $paged > 1) {
        $params['paged'] = $paged;
    }

    return '/app?' . http_build_query($params);
};
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Dokumentenverfasser</h1>
      <p class="dg-lead">Briefe und freie Texte mit Briefpapier aus den Einstellungen — Entwurf, Final, Export folgt. — <?= (int) $list['total'] ?> Einträge</p>
    </div>
    <div class="dg-toolbar">
      <?php if ($canEdit && $dbConnected) : ?>
        <a class="dg-button dg-button--primary" href="/app?page=dokumentenverfasser-form&amp;action=new">Neues Dokument</a>
      <?php endif; ?>
      <?php if (isset($user) && $user instanceof User && SettingsRegistry::canAccess($user)) : ?>
        <a class="dg-button" href="<?= View::escape(SettingsRegistry::tabUrl('briefpapier')) ?>">Briefpapier</a>
      <?php endif; ?>
    </div>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Datenbank nicht verbunden. Dokumente benötigen
      <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Einstellungen → Datenbank</a>.
    </div>
  <?php endif; ?>

  <p class="dg-form-actions dg-mb-3">
    <a class="dg-button<?= $filter === '' ? ' dg-button--primary' : '' ?>" href="<?= View::escape($writerListUrl('')) ?>">Alle</a>
    <a class="dg-button<?= $filter === 'draft' ? ' dg-button--primary' : '' ?>" href="<?= View::escape($writerListUrl('draft')) ?>">Entwürfe</a>
    <a class="dg-button<?= $filter === 'final' ? ' dg-button--primary' : '' ?>" href="<?= View::escape($writerListUrl('final')) ?>">Final</a>
    <a class="dg-button<?= $filter === 'archived' ? ' dg-button--primary' : '' ?>" href="<?= View::escape($writerListUrl('archived')) ?>">Archiv</a>
  </p>

  <?php if ($filterContact instanceof Contact) : ?>
    <div class="dg-flash dg-flash--info dg-mb-3">
      Gefiltert nach Kontakt:
      <a href="/app?page=kontakte&amp;action=edit&amp;id=<?= (int) $filterContact->id ?>"><?= View::escape($filterContact->listLabel()) ?></a>
      · <a href="<?= View::escape($writerListUrl($filter !== '' ? $filter : '', 0)) ?>">Filter aufheben</a>
    </div>
  <?php endif; ?>

  <?php if ((int) $list['total'] > 0) : ?>
    <form class="dg-form dg-btn-row" method="get" action="/app" aria-label="Einträge pro Seite">
      <input type="hidden" name="page" value="dokumentenverfasser">
      <?php if ($filter !== '') : ?>
        <input type="hidden" name="status" value="<?= View::escape($filter) ?>">
      <?php endif; ?>
      <?php if ($contactFilter > 0) : ?>
        <input type="hidden" name="contact" value="<?= $contactFilter ?>">
      <?php endif; ?>
      <label class="dg-field">
        <span>Anzeigen</span>
        <select name="per_page" onchange="this.form.submit()">
          <?php foreach ($sizeOptions as $sizeKey => $sizeLabel) : ?>
            <option value="<?= View::escape($sizeKey) ?>"<?= $perPageKey === $sizeKey ? ' selected' : '' ?>><?= View::escape($sizeLabel) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </form>
  <?php endif; ?>

  <div class="dg-table-wrap">
    <table class="dg-table">
      <thead>
        <tr>
          <th>Titel / Betreff</th>
          <th>Kontakt</th>
          <th>Status</th>
          <th>Version</th>
          <th>Geändert</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($docs === []) : ?>
          <tr>
            <td colspan="6" class="dg-table__empty">Noch keine Dokumente. Legen Sie ein neues Dokument an.</td>
          </tr>
        <?php else : ?>
          <?php foreach ($docs as $row) : ?>
            <?php
              $did = (int) ($row['id'] ?? 0);
              $status = WriterDocument::normalizeStatus((string) ($row['status'] ?? 'draft'));
              $label = trim((string) ($row['title'] ?? ''));
              if ($label === '') {
                  $label = trim((string) ($row['subject'] ?? ''));
              }
              if ($label === '') {
                  $label = 'Ohne Betreff #' . $did;
              }
              $badgeClass = match ($status) {
                  'final' => 'dg-badge--ok',
                  'archived' => 'dg-badge--muted',
                  default => 'dg-badge--muted',
              };
              $cid = (int) ($row['contact_id'] ?? 0);
              $cRow = $cid > 0 ? ContactRepository::findById($cid) : null;
            ?>
            <tr>
              <td><strong><?= View::escape($label) ?></strong></td>
              <td>
                <?php if ($cRow instanceof Contact) : ?>
                  <a href="<?= View::escape($writerListUrl($filter !== '' ? $filter : '', (int) $cRow->id)) ?>"><?= View::escape($cRow->listLabel()) ?></a>
                <?php else : ?>
                  <span class="dg-field-hint">—</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="dg-badge <?= $badgeClass ?>">
                  <?= View::escape($statusLabels[$status] ?? $status) ?>
                </span>
              </td>
              <td>v<?= (int) ($row['version'] ?? 1) ?></td>
              <td><?= View::escape((string) ($row['updated_at'] ?? '')) ?></td>
              <td class="dg-table__actions">
                <div class="dg-table__actions-group">
                  <a class="dg-button dg-button--small" href="/app?page=dokumentenverfasser-form&amp;action=edit&amp;id=<?= $did ?>">
                    <?= ($canEdit && $status === 'draft') ? 'Bearbeiten' : 'Anzeigen' ?>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ((int) $list['total_pages'] > 1) : ?>
    <nav class="dg-pagination" aria-label="Seiten">
      <?php if ((int) $list['page'] > 1) : ?>
        <a href="<?= View::escape($writerListUrl(null, null, null, (int) $list['page'] - 1)) ?>">&laquo; Zurück</a>
      <?php endif; ?>
      <span>Seite <?= (int) $list['page'] ?> von <?= (int) $list['total_pages'] ?></span>
      <?php if ((int) $list['page'] < (int) $list['total_pages']) : ?>
        <a href="<?= View::escape($writerListUrl(null, null, null, (int) $list['page'] + 1)) ?>">Weiter &raquo;</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
</div>
