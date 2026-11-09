<?php
/**
 * @var list<array<string, mixed>> $jobApplications
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$jobApplicationsPaged = is_array($jobApplicationsPaged ?? null) ? $jobApplicationsPaged : null;
if ($jobApplicationsPaged !== null && isset($jobApplicationsPaged['items'])) {
    $jobApplications = $jobApplicationsPaged['items'];
    $jobAppTotal = (int) ($jobApplicationsPaged['total'] ?? count($jobApplications));
    $jobAppPageNum = (int) ($jobApplicationsPaged['page'] ?? 1);
    $jobAppTotalPages = (int) ($jobApplicationsPaged['total_pages'] ?? 1);
    $jobAppPerPageKey = (string) ($jobApplicationsPaged['per_page_key'] ?? ListPagination::DEFAULT);
} else {
    $jobApplications = is_array($jobApplications ?? null) ? $jobApplications : [];
    $jobAppTotal = count($jobApplications);
    $jobAppPageNum = 1;
    $jobAppTotalPages = 1;
    $jobAppPerPageKey = ListPagination::DEFAULT;
}
$statusLabels = JobApplicationRepository::statusOptions();
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Bewerber</h1>
      <p class="dg-lead">Bewerbungen mit Status und Unterlagen.</p>
    </div>
    <div class="dg-page-header__actions">
      <a class="dg-button dg-button--primary" href="/app?page=hr-bewerber-form&amp;action=new">Neue Bewerbung</a>
      <a class="dg-button" href="/app?page=hr-stellen">Zu Stellen</a>
    </div>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Datenbank nicht verbunden.</div>
  <?php else : ?>
    <?php
      View::partial('partials/list-per-page', [
          'total' => $jobAppTotal,
          'perPageKey' => $jobAppPerPageKey,
          'listPage' => 'hr-bewerber',
          'extraQuery' => [],
      ]);
    ?>
    <div class="dg-table-wrap">
      <table class="dg-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>E-Mail</th>
            <th>Stelle</th>
            <th>Status</th>
            <th>Aktualisiert</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($jobApplications === []) : ?>
            <tr><td colspan="6" class="dg-table__empty">Noch keine Bewerbungen.</td></tr>
          <?php else : ?>
            <?php foreach ($jobApplications as $row) : ?>
              <?php
                $id = (int) ($row['id'] ?? 0);
                $status = (string) ($row['status'] ?? '');
                $name = trim((string) ($row['applicant_first_name'] ?? '') . ' ' . (string) ($row['applicant_last_name'] ?? ''));
                if ($name === '') {
                    $name = (string) ($row['applicant_email'] ?? '—');
                }
              ?>
              <tr>
                <td><strong><?= View::escape($name) ?></strong></td>
                <td><?= View::escape((string) ($row['applicant_email'] ?? '')) ?></td>
                <td><?= View::escape((string) ($row['job_title'] ?? '')) ?></td>
                <td><?= View::escape($statusLabels[$status] ?? $status) ?></td>
                <td><?= View::escape((string) ($row['updated_at'] ?? '')) ?></td>
                <td class="dg-table__actions">
                  <div class="dg-table__actions-group">
                    <a class="dg-button dg-button--small" href="/app?page=hr-bewerber-form&amp;action=edit&amp;id=<?= $id ?>">Bearbeiten</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php
      View::partial('partials/list-pagination-nav', [
          'total' => $jobAppTotal,
          'page' => $jobAppPageNum,
          'totalPages' => $jobAppTotalPages,
          'perPageKey' => $jobAppPerPageKey,
          'listPage' => 'hr-bewerber',
          'extraQuery' => [],
      ]);
    ?>
  <?php endif; ?>
</div>
