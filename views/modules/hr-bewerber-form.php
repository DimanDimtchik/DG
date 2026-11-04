<?php
/**
 * @var array<string, mixed> $applicationForm
 * @var int|null $applicationId
 * @var string|null $formError
 * @var list<array{id: int, title: string}> $jobPostingOptions
 * @var list<array<string, mixed>> $applicationEvents
 * @var list<array<string, mixed>> $applicationFiles
 * @var array{type: string, message: string}|null $flash
 */
$isEdit = ($applicationId ?? 0) > 0;
$applicationForm = is_array($applicationForm ?? null) ? $applicationForm : JobApplicationRepository::emptyForm();
$jobPostingOptions = is_array($jobPostingOptions ?? null) ? $jobPostingOptions : [];
$applicationEvents = is_array($applicationEvents ?? null) ? $applicationEvents : [];
$applicationFiles = is_array($applicationFiles ?? null) ? $applicationFiles : [];
$statusLabels = JobApplicationRepository::statusOptions();
$sourceLabels = JobApplicationRepository::sourceOptions();
$fileKindLabels = RecruitingFileStorage::kindOptions();
$linkedContactId = (int) ($applicationForm['contact_id'] ?? 0);
$isHired = (string) ($applicationForm['status'] ?? '') === JobApplicationRepository::STATUS_HIRED;
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header">
    <?php
      View::partial('partials/back-nav', [
          'href' => '/app?page=hr-bewerber',
          'label' => 'Zurück zu Bewerber',
      ]);
    ?>
    <h1 class="dg-page-title"><?= $isEdit ? 'Bewerbung bearbeiten' : 'Neue Bewerbung' ?></h1>
    <p class="dg-lead">Person, Status und Unterlagen. Mit Status „Eingestellt“ wird der Mitarbeiter-Kontakt angelegt oder verknüpft.</p>
  </header>

  <?php if (!empty($formError)) : ?>
    <div class="dg-flash dg-flash--error"><?= View::escape((string) $formError) ?></div>
  <?php endif; ?>

  <form method="post" action="/app?page=hr-bewerber-form" class="dg-form" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
    <input type="hidden" name="job_application_save" value="1">
    <?php if ($isEdit) : ?>
      <input type="hidden" name="id" value="<?= (int) $applicationId ?>">
      <input type="hidden" name="applicant_id" value="<?= View::escape((string) ($applicationForm['applicant_id'] ?? '')) ?>">
    <?php endif; ?>

    <section class="dg-form-section">
      <h2 class="dg-subsection-title">Bewerber</h2>
      <div class="dg-form-grid">
        <label class="dg-field">
          <span>Vorname *</span>
          <input type="text" name="first_name" value="<?= View::escape((string) ($applicationForm['first_name'] ?? '')) ?>" required maxlength="120">
        </label>
        <label class="dg-field">
          <span>Nachname *</span>
          <input type="text" name="last_name" value="<?= View::escape((string) ($applicationForm['last_name'] ?? '')) ?>" required maxlength="120">
        </label>
        <label class="dg-field">
          <span>E-Mail *</span>
          <input type="email" name="email" value="<?= View::escape((string) ($applicationForm['email'] ?? '')) ?>" required maxlength="191">
        </label>
        <label class="dg-field">
          <span>Telefon</span>
          <input type="text" name="phone" value="<?= View::escape((string) ($applicationForm['phone'] ?? '')) ?>" maxlength="64">
        </label>
        <label class="dg-field">
          <span>Ort</span>
          <input type="text" name="city" value="<?= View::escape((string) ($applicationForm['city'] ?? '')) ?>" maxlength="120">
        </label>
        <label class="dg-field dg-field--checkbox">
          <span class="dg-checkbox">
            <input type="checkbox" name="privacy_consent" value="1"<?= !empty($applicationForm['privacy_consent']) ? ' checked' : '' ?>>
            <span>Einwilligung Datenschutz erfasst</span>
          </span>
        </label>
        <label class="dg-field dg-field--wide">
          <span>Interne Notiz</span>
          <textarea name="notes" rows="3"><?= View::escape((string) ($applicationForm['notes'] ?? '')) ?></textarea>
        </label>
      </div>
    </section>

    <section class="dg-form-section">
      <h2 class="dg-subsection-title">Bewerbung</h2>
      <div class="dg-form-grid">
        <label class="dg-field">
          <span>Stelle *</span>
          <select name="job_posting_id" required>
            <option value="">— bitte wählen —</option>
            <?php foreach ($jobPostingOptions as $job) : ?>
              <option value="<?= (int) ($job['id'] ?? 0) ?>"<?= (int) ($applicationForm['job_posting_id'] ?? 0) === (int) ($job['id'] ?? 0) ? ' selected' : '' ?>>
                <?= View::escape((string) ($job['title'] ?? '')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dg-field">
          <span>Status</span>
          <select name="status">
            <?php foreach ($statusLabels as $value => $label) : ?>
              <option value="<?= View::escape($value) ?>"<?= (string) ($applicationForm['status'] ?? '') === $value ? ' selected' : '' ?>><?= View::escape($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dg-field">
          <span>Quelle</span>
          <select name="source">
            <?php foreach ($sourceLabels as $value => $label) : ?>
              <option value="<?= View::escape($value) ?>"<?= (string) ($applicationForm['source'] ?? '') === $value ? ' selected' : '' ?>><?= View::escape($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dg-field">
          <span>Löschen ab (Datum)</span>
          <input type="date" name="retention_until" value="<?= View::escape((string) ($applicationForm['retention_until'] ?? '')) ?>">
          <span class="dg-field-hint">Bei Absage standardmäßig <?= (int) JobApplicationRepository::retentionMonths() ?> Monate; automatische Löschung danach. Einstellung: HR → Recruiting Datenschutz.</span>
        </label>
        <label class="dg-field dg-field--wide">
          <span>Anschreiben / Freitext</span>
          <textarea name="cover_letter" rows="6"><?= View::escape((string) ($applicationForm['cover_letter'] ?? '')) ?></textarea>
        </label>
      </div>
      <?php if ($isEdit && ($isHired || $linkedContactId > 0)) : ?>
        <div class="dg-panel dg-mt-3">
          <h3 class="dg-subsection-title">Mitarbeiter-Kontakt</h3>
          <?php if ($linkedContactId > 0) : ?>
            <p class="dg-muted">Verknüpft mit Kontakt #<?= $linkedContactId ?>.</p>
            <div class="dg-btn-row">
              <a class="dg-button dg-button--small" href="/app?page=kontakte&amp;action=edit&amp;id=<?= $linkedContactId ?>">Mitarbeiter öffnen</a>
            </div>
          <?php else : ?>
            <p class="dg-muted">Noch kein Kontakt verknüpft — Speichern mit Status „Eingestellt“ legt ihn an.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </section>

    <?php if ($isEdit) : ?>
      <section class="dg-form-section">
        <h2 class="dg-subsection-title">Unterlagen</h2>
        <?php if ($applicationFiles === []) : ?>
          <p class="dg-muted">Noch keine Dateien.</p>
        <?php else : ?>
          <div class="dg-table-wrap">
            <table class="dg-table dg-table--compact">
              <thead>
                <tr>
                  <th>Datei</th>
                  <th>Art</th>
                  <th class="dg-table__num">Größe</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($applicationFiles as $file) : ?>
                  <?php
                    $fid = (int) ($file['id'] ?? 0);
                    $kind = (string) ($file['file_kind'] ?? '');
                  ?>
                  <tr>
                    <td><?= View::escape((string) ($file['original_name'] ?? '')) ?></td>
                    <td><?= View::escape($fileKindLabels[$kind] ?? $kind) ?></td>
                    <td class="dg-table__num"><?= number_format((int) ($file['size_bytes'] ?? 0) / 1024, 0, ',', '.') ?> KB</td>
                    <td class="dg-table__actions">
                      <div class="dg-table__actions-group">
                        <button type="submit" class="dg-button dg-button--small dg-button--danger" name="recruiting_file_delete" value="<?= $fid ?>">Löschen</button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
        <div class="dg-form-grid dg-mt-3">
          <label class="dg-field">
            <span>Dateityp</span>
            <select name="file_kind">
              <?php foreach ($fileKindLabels as $value => $label) : ?>
                <option value="<?= View::escape($value) ?>"><?= View::escape($label) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="dg-field dg-field--wide">
            <span>Dateien hochladen</span>
            <input type="file" name="recruiting_files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,application/pdf">
            <span class="dg-field-hint">PDF, Bilder oder Word — max. 10 Dateien à 10 MB.</span>
          </label>
        </div>
      </section>
    <?php endif; ?>

    <div class="dg-form-actions">
      <button type="submit" class="dg-button dg-button--primary">Speichern</button>
      <a class="dg-button" href="/app?page=hr-bewerber">Abbrechen</a>
    </div>
  </form>

  <?php if ($isEdit && $applicationEvents !== []) : ?>
    <section class="dg-panel dg-mt-4">
      <h2 class="dg-subsection-title">Verlauf</h2>
      <div class="dg-table-wrap">
        <table class="dg-table dg-table--compact">
          <thead>
            <tr>
              <th>Zeit</th>
              <th>Art</th>
              <th>Eintrag</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($applicationEvents as $event) : ?>
              <tr>
                <td><?= View::escape((string) ($event['created_at'] ?? '')) ?></td>
                <td><?= View::escape(JobApplicationRepository::eventKindLabel((string) ($event['event_kind'] ?? ''))) ?></td>
                <td><?= View::escape((string) ($event['body'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>
</div>
