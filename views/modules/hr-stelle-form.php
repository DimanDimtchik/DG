<?php
/**
 * @var array<string, mixed> $jobForm
 * @var int|null $jobPostingId
 * @var string|null $formError
 * @var list<array{id: string, name: string}> $departmentOptions
 * @var list<array<string, mixed>> $publishEvents
 * @var array{type: string, message: string}|null $flash
 */
$isEdit = ($jobPostingId ?? 0) > 0;
$jobForm = is_array($jobForm ?? null) ? $jobForm : JobPostingRepository::emptyForm();
$departmentOptions = is_array($departmentOptions ?? null) ? $departmentOptions : [];
$publishEvents = is_array($publishEvents ?? null) ? $publishEvents : [];
$statusLabels = JobPostingRepository::statusOptions();
$typeLabels = JobPostingRepository::employmentTypeOptions();
$publishStatusLabels = JobPostingRepository::publishStatusOptions();
$channelLabels = JobPostingRepository::channelOptions();
$xingReady = XingRecruitingSettings::isReady();
$xingStatusLabel = XingRecruitingSettings::statusLabel(XingRecruitingSettings::config());
$applyUrl = (string) ($jobForm['slug'] ?? '') !== ''
    ? JobPostingRepository::publicUrl((string) $jobForm['slug'])
    : JobPostingRepository::publicUrl();
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
    <p class="dg-lead">Stammdaten für die öffentliche Bewerbungsseite. Xing/LinkedIn bekommen nur den Link auf unsere Seite — Bewerbungen laufen immer hier.</p>
    <?php if ($isEdit && (string) ($jobForm['status'] ?? '') === JobPostingRepository::STATUS_PUBLISHED && (string) ($jobForm['slug'] ?? '') !== '') : ?>
      <p class="dg-muted">
        Öffentlich:
        <a href="<?= View::escape($applyUrl) ?>" target="_blank" rel="noopener">
          <?= View::escape($applyUrl) ?>
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

    <section class="dg-form-section">
      <h2 class="dg-subsection-title">Anzeigen-Export</h2>
      <p class="dg-muted">Optional auf Xing oder LinkedIn ankündigen. Bewerben-URL bleibt immer die eigene Seite:</p>
      <p><code><?= View::escape($applyUrl) ?></code></p>
      <div class="dg-form-grid">
        <label class="dg-field dg-field--checkbox">
          <span class="dg-checkbox">
            <input type="checkbox" name="export_xing" value="1"<?= !empty($jobForm['export_xing']) ? ' checked' : '' ?>>
            <span>Auf Xing veröffentlichen</span>
          </span>
        </label>
        <label class="dg-field dg-field--checkbox">
          <span class="dg-checkbox">
            <input type="checkbox" name="export_linkedin" value="1"<?= !empty($jobForm['export_linkedin']) ? ' checked' : '' ?>>
            <span>Auf LinkedIn veröffentlichen</span>
          </span>
        </label>
      </div>
      <?php if ($isEdit) : ?>
        <div class="dg-form-grid dg-mt-3">
          <div class="dg-field">
            <span>Xing-Status</span>
            <p class="dg-muted">
              <?= View::escape($publishStatusLabels[(string) ($jobForm['xing_publish_status'] ?? '')] ?? (string) ($jobForm['xing_publish_status'] ?? '—')) ?>
            </p>
            <?php if (trim((string) ($jobForm['xing_last_message'] ?? '')) !== '') : ?>
              <p class="dg-field-hint"><?= View::escape((string) $jobForm['xing_last_message']) ?></p>
            <?php endif; ?>
          </div>
          <div class="dg-field">
            <span>LinkedIn-Status</span>
            <p class="dg-muted">
              <?= View::escape($publishStatusLabels[(string) ($jobForm['linkedin_publish_status'] ?? '')] ?? (string) ($jobForm['linkedin_publish_status'] ?? '—')) ?>
            </p>
            <?php if (trim((string) ($jobForm['linkedin_last_message'] ?? '')) !== '') : ?>
              <p class="dg-field-hint"><?= View::escape((string) $jobForm['linkedin_last_message']) ?></p>
            <?php endif; ?>
          </div>
        </div>
        <p class="dg-field-hint dg-mt-2">
          Xing-API: <?= View::escape($xingStatusLabel) ?>.
          <?php if (!$xingReady) : ?>
            Einrichtung unter
            <a href="<?= View::escape(SettingsRegistry::tabUrl('xing-recruiting')) ?>">Einstellungen → HR → Xing Stellenexport</a>
            (nur mit Kunden-Jobvertrag oder Test-IDs).
          <?php else : ?>
            Mit „Speichern und an Xing senden“ wird die Anzeige angelegt; Bewerben-Link bleibt diese URL.
          <?php endif; ?>
        </p>
      <?php endif; ?>
    </section>

    <div class="dg-form-actions">
      <button type="submit" class="dg-button dg-button--primary">Speichern</button>
      <?php if ($isEdit && $xingReady) : ?>
        <button type="submit" class="dg-button" name="xing_publish_now" value="1">Speichern und an Xing senden</button>
      <?php endif; ?>
      <a class="dg-button" href="/app?page=hr-stellen">Abbrechen</a>
    </div>
  </form>

  <?php if ($isEdit && $publishEvents !== []) : ?>
    <section class="dg-panel dg-mt-4">
      <h2 class="dg-subsection-title">Publish-Protokoll</h2>
      <div class="dg-table-wrap">
        <table class="dg-table dg-table--compact">
          <thead>
            <tr>
              <th>Zeit</th>
              <th>Kanal</th>
              <th>Art</th>
              <th>Eintrag</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($publishEvents as $event) : ?>
              <?php
                $ch = (string) ($event['channel'] ?? '');
                $kind = (string) ($event['event_kind'] ?? '');
              ?>
              <tr>
                <td><?= View::escape((string) ($event['created_at'] ?? '')) ?></td>
                <td><?= View::escape($channelLabels[$ch] ?? $ch) ?></td>
                <td><?= View::escape($kind) ?></td>
                <td><?= View::escape((string) ($event['body'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>
</div>
