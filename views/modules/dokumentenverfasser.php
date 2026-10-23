<?php
/**
 * Dokumentenverfasser D2 — Liste.
 *
 * @var list<array<string, mixed>> $writerDocumentList
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$docs = $writerDocumentList ?? [];
$statusLabels = WriterDocument::statusOptions();
$filter = (string) ($writerStatusFilter ?? '');
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Dokumentenverfasser</h1>
      <p class="dg-lead">Briefe und freie Texte mit Briefpapier aus den Einstellungen — Entwurf, Final, Export folgt.</p>
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

  <p class="dg-form-actions" style="margin-bottom:12px">
    <a class="dg-button<?= $filter === '' ? ' dg-button--primary' : '' ?>" href="/app?page=dokumentenverfasser">Alle</a>
    <a class="dg-button<?= $filter === 'draft' ? ' dg-button--primary' : '' ?>" href="/app?page=dokumentenverfasser&amp;status=draft">Entwürfe</a>
    <a class="dg-button<?= $filter === 'final' ? ' dg-button--primary' : '' ?>" href="/app?page=dokumentenverfasser&amp;status=final">Final</a>
    <a class="dg-button<?= $filter === 'archived' ? ' dg-button--primary' : '' ?>" href="/app?page=dokumentenverfasser&amp;status=archived">Archiv</a>
  </p>

  <div class="dg-table-wrap">
    <table class="dg-table">
      <thead>
        <tr>
          <th>Titel / Betreff</th>
          <th>Status</th>
          <th>Version</th>
          <th>Geändert</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($docs === []) : ?>
          <tr>
            <td colspan="5" class="dg-table__empty">Noch keine Dokumente. Legen Sie ein neues Dokument an.</td>
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
            ?>
            <tr>
              <td><strong><?= View::escape($label) ?></strong></td>
              <td>
                <span class="dg-badge <?= $badgeClass ?>">
                  <?= View::escape($statusLabels[$status] ?? $status) ?>
                </span>
              </td>
              <td>v<?= (int) ($row['version'] ?? 1) ?></td>
              <td><?= View::escape((string) ($row['updated_at'] ?? '')) ?></td>
              <td class="dg-table__actions">
                <a href="/app?page=dokumentenverfasser-form&amp;action=edit&amp;id=<?= $did ?>">
                  <?= ($canEdit && $status === 'draft') ? 'Bearbeiten' : 'Anzeigen' ?>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
