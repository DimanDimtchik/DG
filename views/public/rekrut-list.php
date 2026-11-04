<?php
/**
 * Öffentliche Stellenliste /rekrut
 *
 * @var list<array<string, mixed>> $rekrutJobs
 * @var string $rekrutCompany
 */
$jobs = is_array($rekrutJobs ?? null) ? $rekrutJobs : [];
$company = (string) ($rekrutCompany ?? 'Karriere');
$typeLabels = JobPostingRepository::employmentTypeOptions();
$pageTitle = 'Stellenangebote – ' . $company;
?>
<!DOCTYPE html>
<html lang="de">
<head>
<?php View::render('partials/head', compact('pageTitle')); ?>
<meta name="robots" content="index,follow">
</head>
<body>
<div class="dg-wrap">
  <header class="dg-page-header">
    <p class="dg-muted"><?= View::escape($company) ?></p>
    <h1 class="dg-page-title">Offene Stellen</h1>
    <p class="dg-lead">Bewerbungen nehmen wir über diese Seite entgegen — Ihre Daten bleiben bei uns.</p>
  </header>

  <?php if ($jobs === []) : ?>
    <section class="dg-panel">
      <p class="dg-muted">Derzeit sind keine Stellen veröffentlicht.</p>
    </section>
  <?php else : ?>
    <div class="dg-stack dg-stack--loose">
      <?php foreach ($jobs as $job) : ?>
        <?php
          $slug = (string) ($job['slug'] ?? '');
          $type = (string) ($job['employment_type'] ?? '');
          $loc = trim((string) ($job['location'] ?? ''));
          $summary = trim((string) ($job['summary'] ?? ''));
        ?>
        <section class="dg-panel">
          <h2 class="dg-subsection-title"><?= View::escape((string) ($job['title'] ?? '')) ?></h2>
          <p class="dg-muted">
            <?= View::escape($typeLabels[$type] ?? $type) ?>
            <?php if ($loc !== '') : ?>
              · <?= View::escape($loc) ?>
            <?php endif; ?>
          </p>
          <?php if ($summary !== '') : ?>
            <p><?= nl2br(View::escape($summary)) ?></p>
          <?php endif; ?>
          <div class="dg-btn-row dg-mt-3">
            <a class="dg-button dg-button--primary" href="<?= View::escape(JobPostingRepository::publicUrl($slug)) ?>">Anzeigen &amp; bewerben</a>
          </div>
        </section>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
