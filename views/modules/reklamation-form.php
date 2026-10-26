<?php
/**
 * @var array<string, mixed> $complaintForm
 * @var int|null $complaintId
 * @var list<array<string, mixed>> $complaintEvents
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$form = $complaintForm ?? ComplaintRepository::emptyForm();
$id = (int) ($complaintId ?? 0);
$events = $complaintEvents ?? [];
$statusLabels = ComplaintRepository::statusOptions();
$faultLabels = ComplaintRepository::faultOptions();
$readOnly = !$canEdit || !$dbConnected;
$apologyId = (int) ($form['apology_gift_voucher_id'] ?? 0);
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <a class="dg-button dg-button--back" href="/app?page=reklamation">← Zurück</a>

  <header class="dg-page-header">
    <h1 class="dg-page-title"><?= $id > 0 ? 'Reklamation bearbeiten' : 'Neue Reklamation' ?></h1>
    <?php if (!empty($form['complaint_number'])) : ?>
      <p class="dg-lead"><?= View::escape((string) $form['complaint_number']) ?></p>
    <?php endif; ?>
  </header>

  <form class="dg-form dg-panel" method="post" action="/app?page=reklamation-form">
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
    <input type="hidden" name="complaint_id" value="<?= $id ?>">

    <div class="dg-form-grid">
      <label class="dg-field dg-field--wide">
        <span>Betreff *</span>
        <input type="text" name="subject" required maxlength="255" value="<?= View::escape((string) ($form['subject'] ?? '')) ?>"<?= $readOnly ? ' readonly' : '' ?>>
      </label>
      <label class="dg-field">
        <span>Status</span>
        <select name="status"<?= $readOnly ? ' disabled' : '' ?>>
          <?php foreach ($statusLabels as $k => $lab) : ?>
            <option value="<?= View::escape($k) ?>"<?= ((string) ($form['status'] ?? '') === $k) ? ' selected' : '' ?>><?= View::escape($lab) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field">
        <span>Schuld</span>
        <select name="fault"<?= $readOnly ? ' disabled' : '' ?>>
          <?php foreach ($faultLabels as $k => $lab) : ?>
            <option value="<?= View::escape($k) ?>"<?= ((string) ($form['fault'] ?? '') === $k) ? ' selected' : '' ?>><?= View::escape($lab) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field">
        <span>Kontakt-Name</span>
        <input type="text" name="contact_name" value="<?= View::escape((string) ($form['contact_name'] ?? '')) ?>"<?= $readOnly ? ' readonly' : '' ?>>
      </label>
      <label class="dg-field">
        <span>Kontakt-ID</span>
        <input type="number" name="contact_id" min="0" value="<?= View::escape((string) ($form['contact_id'] ?? '')) ?>"<?= $readOnly ? ' readonly' : '' ?>>
      </label>
      <label class="dg-field">
        <span>Beleg-ID (Quelle)</span>
        <input type="number" name="source_voucher_id" min="0" value="<?= View::escape((string) ($form['source_voucher_id'] ?? '')) ?>"<?= $readOnly ? ' readonly' : '' ?>>
      </label>
      <label class="dg-field">
        <span>Artikel-ID</span>
        <input type="number" name="article_id" min="0" value="<?= View::escape((string) ($form['article_id'] ?? '')) ?>"<?= $readOnly ? ' readonly' : '' ?>>
        <small class="dg-field-hint">Beim Speichern werden Garantie/Gewährleistung aus dem Artikel übernommen.</small>
      </label>
      <label class="dg-field">
        <span>Artikel / Leistung</span>
        <input type="text" name="article_title" value="<?= View::escape((string) ($form['article_title'] ?? '')) ?>"<?= $readOnly ? ' readonly' : '' ?>>
      </label>
    </div>

    <?php if ($id > 0 && ((string) ($form['warranty_kind'] ?? 'none') !== 'none' || trim((string) ($form['warranty_text'] ?? '')) !== '')) : ?>
      <div class="dg-panel" style="margin: 1rem 0; background: var(--dg-surface-alt, #f8fafc);">
        <h2 class="dg-subsection-title">Garantie / Gewährleistung</h2>
        <p><strong><?= View::escape(ArticleWarranty::label((string) ($form['warranty_kind'] ?? 'none'))) ?></strong>
          <?php if ((int) ($form['warranty_months'] ?? 0) > 0) : ?>
            · <?= (int) $form['warranty_months'] ?> Monate
          <?php endif; ?>
        </p>
        <?php if (trim((string) ($form['warranty_text'] ?? '')) !== '') : ?>
          <p><?= nl2br(View::escape((string) $form['warranty_text'])) ?></p>
        <?php endif; ?>
        <p class="dg-field-hint"><?= View::escape(ArticleWarranty::hint()) ?></p>
      </div>
    <?php endif; ?>

    <div class="dg-form-grid">
      <label class="dg-field dg-field--wide">
        <span>1. Annahme</span>
        <textarea name="intake_text" rows="3"<?= $readOnly ? ' readonly' : '' ?>><?= View::escape((string) ($form['intake_text'] ?? '')) ?></textarea>
      </label>
      <label class="dg-field dg-field--wide">
        <span>2. Beurteilung</span>
        <textarea name="assessment_text" rows="3"<?= $readOnly ? ' readonly' : '' ?>><?= View::escape((string) ($form['assessment_text'] ?? '')) ?></textarea>
      </label>
      <label class="dg-field dg-field--wide">
        <span>3. Stellungnahme</span>
        <textarea name="statement_text" rows="3"<?= $readOnly ? ' readonly' : '' ?>><?= View::escape((string) ($form['statement_text'] ?? '')) ?></textarea>
      </label>
      <label class="dg-field dg-field--wide">
        <span>4. Maßnahmen</span>
        <textarea name="measures_text" rows="3"<?= $readOnly ? ' readonly' : '' ?>><?= View::escape((string) ($form['measures_text'] ?? '')) ?></textarea>
      </label>
    </div>

    <?php if (!$readOnly) : ?>
      <div class="dg-form-actions">
        <button type="submit" name="complaint_save" value="1" class="dg-button dg-button--primary">Speichern</button>
      </div>
    <?php endif; ?>
  </form>

  <?php if ($id > 0 && $canEdit && (string) ($form['fault'] ?? '') === ComplaintRepository::FAULT_COMPANY) : ?>
    <div class="dg-panel" style="margin-top: 1.5rem;">
      <h2 class="dg-subsection-title">Entschuldigungs-Gutschein</h2>
      <?php if ($apologyId > 0) : ?>
        <p>Bereits ausgestellt:
          <a href="/app?page=gutscheine-form&amp;action=view&amp;id=<?= $apologyId ?>">Gutschein #<?= $apologyId ?></a>
        </p>
      <?php else : ?>
        <form class="dg-form" method="post" action="/app?page=reklamation-form">
          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
          <input type="hidden" name="complaint_id" value="<?= $id ?>">
          <label class="dg-field">
            <span>Betrag (€)</span>
            <input type="text" name="apology_amount" inputmode="decimal" required placeholder="25,00">
          </label>
          <button type="submit" name="complaint_apology" value="1" class="dg-button dg-button--primary">Als Garantieleistung ausstellen</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($events !== []) : ?>
    <div class="dg-panel" style="margin-top: 1.5rem;">
      <h2 class="dg-subsection-title">Protokoll</h2>
      <ul class="dg-list">
        <?php foreach ($events as $ev) : ?>
          <li>
            <small class="dg-muted"><?= View::escape((string) ($ev['created_at'] ?? '')) ?></small>
            — <?= View::escape((string) ($ev['body'] ?? '')) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
</div>
