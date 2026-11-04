<?php
/**
 * @var list<array<string, mixed>> $jobPostings
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$jobPostings = is_array($jobPostings ?? null) ? $jobPostings : [];
$statusLabels = JobPostingRepository::statusOptions();
$typeLabels = JobPostingRepository::employmentTypeOptions();
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Stellen</h1>
      <p class="dg-lead">Offene Stellen für die Bewerbungsseite verwalten.</p>
    </div>
    <div class="dg-page-header__actions">
      <a class="dg-button dg-button--primary" href="/app?page=hr-stelle-form&amp;action=new">Neue Stelle</a>
      <a class="dg-button" href="/app?page=hr-bewerber">Zu Bewerber</a>
      <a class="dg-button" href="<?= View::escape(JobPostingRepository::publicUrl()) ?>" target="_blank" rel="noopener">Öffentliche Seite</a>
    </div>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Datenbank nicht verbunden.</div>
  <?php else : ?>
    <div class="dg-table-wrap">
      <table class="dg-table">
        <thead>
          <tr>
            <th>Titel</th>
            <th>Ort</th>
            <th>Art</th>
            <th>Status</th>
            <th class="dg-table__num">Bewerbungen</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($jobPostings === []) : ?>
            <tr><td colspan="6" class="dg-table__empty">Noch keine Stellen angelegt.</td></tr>
          <?php else : ?>
            <?php foreach ($jobPostings as $row) : ?>
              <?php
                $id = (int) ($row['id'] ?? 0);
                $status = (string) ($row['status'] ?? '');
                $type = (string) ($row['employment_type'] ?? '');
              ?>
              <tr>
                <td><strong><?= View::escape((string) ($row['title'] ?? '')) ?></strong></td>
                <td><?= View::escape((string) ($row['location'] ?? '') !== '' ? (string) $row['location'] : '—') ?></td>
                <td><?= View::escape($typeLabels[$type] ?? $type) ?></td>
                <td><?= View::escape($statusLabels[$status] ?? $status) ?></td>
                <td class="dg-table__num"><?= (int) ($row['application_count'] ?? 0) ?></td>
                <td class="dg-table__actions">
                  <div class="dg-table__actions-group">
                    <a class="dg-button dg-button--small" href="/app?page=hr-stelle-form&amp;action=edit&amp;id=<?= $id ?>">Bearbeiten</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
