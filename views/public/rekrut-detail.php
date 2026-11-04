<?php
/**
 * Öffentliche Stellen-Detailseite + Bewerbungsformular.
 *
 * @var array<string, mixed> $rekrutJob
 * @var string $rekrutCompany
 * @var array<string, mixed> $rekrutForm
 * @var string|null $rekrutError
 * @var bool $rekrutSuccess
 */
$job = is_array($rekrutJob ?? null) ? $rekrutJob : [];
$company = (string) ($rekrutCompany ?? 'Karriere');
$form = is_array($rekrutForm ?? null) ? $rekrutForm : [];
$error = (string) ($rekrutError ?? '');
$success = !empty($rekrutSuccess);
$typeLabels = JobPostingRepository::employmentTypeOptions();
$type = (string) ($job['employment_type'] ?? '');
$loc = trim((string) ($job['location'] ?? ''));
$slug = (string) ($job['slug'] ?? '');
$jobId = (int) ($job['id'] ?? 0);
$title = (string) ($job['title'] ?? 'Stelle');
$pageTitle = $title . ' – ' . $company;
$privacyUrl = RecruitingPublicService::privacyPageUrl();
$retentionHint = RecruitingPublicService::publicRetentionHint();
$consentLabel = RecruitingPublicService::publicConsentLabel();
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
    <?php
      View::partial('partials/back-nav', [
          'href' => JobPostingRepository::publicUrl(),
          'label' => 'Alle Stellen',
      ]);
    ?>
    <p class="dg-muted"><?= View::escape($company) ?></p>
    <h1 class="dg-page-title"><?= View::escape($title) ?></h1>
    <p class="dg-lead">
      <?= View::escape($typeLabels[$type] ?? $type) ?>
      <?php if ($loc !== '') : ?>
        · <?= View::escape($loc) ?>
      <?php endif; ?>
    </p>
  </header>

  <?php if (trim((string) ($job['summary'] ?? '')) !== '') : ?>
    <section class="dg-panel dg-mb-4">
      <p><?= nl2br(View::escape((string) $job['summary'])) ?></p>
    </section>
  <?php endif; ?>

  <?php if (trim((string) ($job['body'] ?? '')) !== '') : ?>
    <section class="dg-panel dg-mb-4">
      <h2 class="dg-subsection-title">Beschreibung</h2>
      <div class="dg-stack"><?= nl2br(View::escape((string) $job['body'])) ?></div>
    </section>
  <?php endif; ?>

  <section class="dg-panel" id="bewerben">
    <h2 class="dg-subsection-title">Jetzt bewerben</h2>

    <?php if ($success) : ?>
      <div class="dg-flash dg-flash--success">
        Vielen Dank — Ihre Bewerbung ist eingegangen. Wir melden uns bei Ihnen.
      </div>
      <div class="dg-btn-row dg-mt-3">
        <a class="dg-button" href="<?= View::escape(JobPostingRepository::publicUrl()) ?>">Zu allen Stellen</a>
      </div>
    <?php else : ?>
      <?php if ($error !== '') : ?>
        <div class="dg-flash dg-flash--error"><?= View::escape($error) ?></div>
      <?php endif; ?>

      <p class="dg-muted">
        <?= View::escape($retentionHint) ?>
        Details: <a href="<?= View::escape($privacyUrl) ?>">Datenschutz</a>.
      </p>

      <form method="post" action="<?= View::escape(JobPostingRepository::publicUrl($slug)) ?>#bewerben" class="dg-form" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <input type="hidden" name="job_posting_id" value="<?= $jobId ?>">
        <input type="hidden" name="rekrut_apply" value="1">

        <section class="dg-form-section">
          <div class="dg-form-grid">
            <label class="dg-field">
              <span>Vorname *</span>
              <input type="text" name="first_name" required maxlength="120" value="<?= View::escape((string) ($form['first_name'] ?? '')) ?>">
            </label>
            <label class="dg-field">
              <span>Nachname *</span>
              <input type="text" name="last_name" required maxlength="120" value="<?= View::escape((string) ($form['last_name'] ?? '')) ?>">
            </label>
            <label class="dg-field">
              <span>E-Mail *</span>
              <input type="email" name="email" required maxlength="191" value="<?= View::escape((string) ($form['email'] ?? '')) ?>">
            </label>
            <label class="dg-field">
              <span>Telefon</span>
              <input type="text" name="phone" maxlength="64" value="<?= View::escape((string) ($form['phone'] ?? '')) ?>">
            </label>
            <label class="dg-field">
              <span>Ort</span>
              <input type="text" name="city" maxlength="120" value="<?= View::escape((string) ($form['city'] ?? '')) ?>">
            </label>
            <label class="dg-field dg-field--wide">
              <span>Anschreiben / Nachricht</span>
              <textarea name="cover_letter" rows="6"><?= View::escape((string) ($form['cover_letter'] ?? '')) ?></textarea>
            </label>
            <label class="dg-field dg-field--wide">
              <span>Lebenslauf / Unterlagen (optional)</span>
              <input type="hidden" name="file_kind" value="cv">
              <input type="file" name="recruiting_files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,application/pdf">
              <span class="dg-field-hint">PDF, Bild oder Word — max. 10 Dateien à 10 MB.</span>
            </label>
            <label class="dg-field dg-field--checkbox dg-field--wide">
              <span class="dg-checkbox">
                <input type="checkbox" name="privacy_consent" value="1" required<?= !empty($form['privacy_consent']) ? ' checked' : '' ?>>
                <span><?= View::escape($consentLabel) ?></span>
              </span>
            </label>
          </div>
        </section>

        <div class="dg-form-actions">
          <button type="submit" class="dg-button dg-button--primary">Bewerbung absenden</button>
          <a class="dg-button" href="<?= View::escape(JobPostingRepository::publicUrl()) ?>">Abbrechen</a>
        </div>
      </form>
    <?php endif; ?>
  </section>
</div>
</body>
</html>
