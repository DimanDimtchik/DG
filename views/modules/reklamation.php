<?php
/**
 * @var list<array<string, mixed>> $complaintList
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$rows = $complaintList ?? [];
$statusLabels = ComplaintRepository::statusOptions();
$faultLabels = ComplaintRepository::faultOptions();
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Reklamation</h1>
      <p class="dg-lead">Annahme, Beurteilung, Stellungnahme, Maßnahmen.</p>
    </div>
    <div class="dg-toolbar">
      <?php if ($canEdit && $dbConnected) : ?>
        <a class="dg-button dg-button--primary" href="/app?page=reklamation-form&amp;action=new">Neue Reklamation</a>
      <?php endif; ?>
    </div>
  </header>

  <div class="dg-table-wrap">
    <table class="dg-table">
      <thead>
        <tr>
          <th>Nr.</th>
          <th>Betreff</th>
          <th>Status</th>
          <th>Schuld</th>
          <th>Kontakt</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($rows === []) : ?>
          <tr><td colspan="6" class="dg-table__empty">Noch keine Reklamationen.</td></tr>
        <?php else : ?>
          <?php foreach ($rows as $row) : ?>
            <?php
              $cid = (int) ($row['id'] ?? 0);
              $status = (string) ($row['status'] ?? '');
              $fault = (string) ($row['fault'] ?? '');
            ?>
            <tr>
              <td><?= View::escape((string) ($row['complaint_number'] ?? '')) ?></td>
              <td><strong><?= View::escape((string) ($row['subject'] ?? '')) ?></strong></td>
              <td><?= View::escape($statusLabels[$status] ?? $status) ?></td>
              <td><?= View::escape($faultLabels[$fault] ?? $fault) ?></td>
              <td><?= View::escape((string) ($row['contact_name'] ?? '—')) ?></td>
              <td class="dg-table__actions">
                <a href="/app?page=reklamation-form&amp;action=edit&amp;id=<?= $cid ?>"><?= $canEdit ? 'Bearbeiten' : 'Anzeigen' ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
