<?php
/**
 * @var array<string, mixed> $jobForm
 * @var int|null $jobPostingId
 * @var string|null $formError
 * @var list<array{id: string, name: string}> $departmentOptions
 * @var array{type: string, message: string}|null $flash
 */
$isEdit = ($jobPostingId ?? 0) > 0;
$jobForm = is_array($jobForm ?? null) ? $jobForm : JobPostingRepository::emptyForm();
$departmentOptions = is_array($departmentOptions ?? null) ? $departmentOptions : [];
$statusLabels = JobPostingRepository::statusOptions();
$typeLabels = JobPostingRepository::employmentTypeOptions();
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header">
    <?php
      View::partial('partials/back-nav', [
          'href' => '/app?page=hr-stellen',
          'label' => 'Zurück zu Stellen',
      ]);
    ?>
    <h1 class="dg-page-title"><?= $isEdit ? 'Stelle bearbeiten' : 'Neue Stelle' ?></h1>
    <p class="dg-lead">Stammdaten für die öffentliche Bewerbungsseite unter <code>/rekrut</code>.</p>
    <?php if ($isEdit && (string) ($jobForm['status'] ?? '') === JobPostingRepository::STATUS_PUBLISHED && (string) ($jobForm['slug'] ?? '') !== '') : ?>
      <p class="dg-muted">
        Öffentlich:
        <a href="<?= View::escape(JobPostingRepository::publicUrl((string) $jobForm['slug'])) ?>" target="_blank" rel="noopener">
          <?= View::escape(JobPostingRepository::publicUrl((string) $jobForm['slug'])) ?>
        </a>
      </p>
    <?php endif; ?>
  </header>

  <?php if (!empty($formError)) : ?>
    <div class="dg-flash dg-flash--error"><?= View::escape((string) $formError) ?></div>
  <?php endif; ?>

  <form method="post" action="/app?page=hr-stelle-form" class="dg-form">
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
    <input type="hidden" name="job_posting_save" value="1">
    <?php if ($isEdit) : ?>
      <input type="hidden" name="id" value="<?= (int) $jobPostingId ?>">
    <?php endif; ?>

    <section class="dg-form-section">
      <h2 class="dg-subsection-title">Stelle</h2>
      <div class="dg-form-grid">
        <label class="dg-field dg-field--wide">
          <span>Titel *</span>
          <input type="text" name="title" value="<?= View::escape((string) ($jobForm['title'] ?? '')) ?>" required maxlength="191">
        </label>
        <label class="dg-field">
          <span>Kurz-URL (Slug)</span>
          <input type="text" name="slug" value="<?= View::escape((string) ($jobForm['slug'] ?? '')) ?>" maxlength="191" placeholder="wird aus dem Titel erzeugt">
          <span class="dg-field-hint">Leer lassen = automatisch aus dem Titel.</span>
        </label>
        <label class="dg-field">
          <span>Ort</span>
          <input type="text" name="location" value="<?= View::escape((string) ($jobForm['location'] ?? '')) ?>" maxlength="191">
        </label>
        <label class="dg-field">
          <span>Beschäftigungsart</span>
          <select name="employment_type">
            <?php foreach ($typeLabels as $value => $label) : ?>
              <option value="<?= View::escape($value) ?>"<?= (string) ($jobForm['employment_type'] ?? '') === $value ? ' selected' : '' ?>><?= View::escape($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dg-field">
          <span>Status</span>
          <select name="status">
            <?php foreach ($statusLabels as $value => $label) : ?>
              <option value="<?= View::escape($value) ?>"<?= (string) ($jobForm['status'] ?? '') === $value ? ' selected' : '' ?>><?= View::escape($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dg-field">
          <span>Abteilung</span>
          <select name="department_id">
            <option value="">— optional —</option>
            <?php foreach ($departmentOptions as $dept) : ?>
              <option value="<?= View::escape((string) ($dept['id'] ?? '')) ?>"<?= (string) ($jobForm['department_id'] ?? '') === (string) ($dept['id'] ?? '') ? ' selected' : '' ?>>
                <?= View::escape((string) ($dept['name'] ?? '')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dg-field dg-field--wide">
          <span>Kurzbeschreibung</span>
          <textarea name="summary" rows="3"><?= View::escape((string) ($jobForm['summary'] ?? '')) ?></textarea>
        </label>
        <label class="dg-field dg-field--wide">
          <span>Stellenbeschreibung</span>
          <textarea name="body" rows="10"><?= View::escape((string) ($jobForm['body'] ?? '')) ?></textarea>
        </label>
      </div>
    </section>

    <div class="dg-form-actions">
      <button type="submit" class="dg-button dg-button--primary">Speichern</button>
      <a class="dg-button" href="/app?page=hr-stellen">Abbrechen</a>
    </div>
  </form>
</div>
