<?php
/**
 * @var array<string, mixed> $complaintForm
 * @var int|null $complaintId
 * @var list<array<string, mixed>> $complaintEvents
 * @var list<array<string, mixed>> $complaintAttachments
 * @var string $complaintContactLabel
 * @var string $complaintVoucherLabel
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$form = $complaintForm ?? ComplaintRepository::emptyForm();
$id = (int) ($complaintId ?? 0);
$events = $complaintEvents ?? [];
$attachments = $complaintAttachments ?? [];
$contactLabel = (string) ($complaintContactLabel ?? '');
$voucherLabel = (string) ($complaintVoucherLabel ?? '');
$statusLabels = ComplaintRepository::statusOptions();
$faultLabels = ComplaintRepository::faultOptions();
$readOnly = !$canEdit || !$dbConnected;
$apologyId = (int) ($form['apology_gift_voucher_id'] ?? 0);
$contactIdVal = (int) ($form['contact_id'] ?? 0);
$voucherIdVal = (int) ($form['source_voucher_id'] ?? 0);
if ($contactLabel === '' && $contactIdVal > 0) {
    $contactLabel = ComplaintRepository::contactLabel($contactIdVal);
}
if ($voucherLabel === '' && $voucherIdVal > 0) {
    $voucherLabel = ComplaintRepository::voucherLabel($voucherIdVal);
}
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

  <form class="dg-form dg-panel" method="post" action="/app?page=reklamation-form" enctype="multipart/form-data">
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
    </div>

    <h2 class="dg-subsection-title">Kunde &amp; Beleg</h2>
    <div class="dg-form-grid">
      <div class="dg-field dg-field--wide">
        <span>Kunde / Kontakt</span>
        <input type="hidden" name="contact_id" id="dg-complaint-contact-id" value="<?= $contactIdVal ?>">
        <input type="hidden" name="contact_name" id="dg-complaint-contact-name" value="<?= View::escape((string) ($form['contact_name'] ?? $contactLabel)) ?>">
        <div class="dg-btn-row">
          <input
            type="search"
            id="dg-complaint-contact-search"
            class="dg-field-grow"
            value="<?= View::escape($contactLabel !== '' ? $contactLabel : (string) ($form['contact_name'] ?? '')) ?>"
            placeholder="Name, Firma oder E-Mail …"
            autocomplete="off"
            <?= $readOnly ? ' disabled' : '' ?>
          >
          <?php if (!$readOnly) : ?>
            <button type="button" class="dg-button" id="dg-complaint-contact-clear"<?= $contactIdVal < 1 ? ' hidden' : '' ?>>Löschen</button>
          <?php endif; ?>
        </div>
        <div id="dg-complaint-contact-results" class="dg-voucher-contact-results" hidden></div>
        <span class="dg-field-hint">Kontakt aus dem CRM wählen — Name wird übernommen.</span>
      </div>

      <div class="dg-field dg-field--wide">
        <span>Auftrag / Angebot / Rechnung</span>
        <input type="hidden" name="source_voucher_id" id="dg-complaint-voucher-id" value="<?= $voucherIdVal ?>">
        <label class="dg-field" style="margin:0;">
          <span class="dg-field-hint">Belege des Kunden (letzte 24 Monate)</span>
          <select id="dg-complaint-voucher-recent"<?= $readOnly ? ' disabled' : '' ?>>
            <option value=""><?= $contactIdVal > 0 ? '— Beleg wählen —' : 'Zuerst Kunde wählen …' ?></option>
          </select>
        </label>
        <div class="dg-btn-row dg-mt-2">
          <input
            type="search"
            id="dg-complaint-voucher-search"
            class="dg-field-grow"
            value="<?= View::escape($voucherLabel) ?>"
            placeholder="Belegnummer, Name, Stichwort …"
            autocomplete="off"
            <?= $readOnly ? ' disabled' : '' ?>
          >
          <?php if (!$readOnly) : ?>
            <button type="button" class="dg-button" id="dg-complaint-voucher-clear"<?= $voucherIdVal < 1 ? ' hidden' : '' ?>>Löschen</button>
          <?php endif; ?>
        </div>
        <div id="dg-complaint-voucher-results" class="dg-voucher-contact-results" hidden></div>
        <span class="dg-field-hint">Angebot, Auftrag, AB, Lieferschein, Rechnung oder Gutschrift verknüpfen. Ohne Kundenwahl auch freie Suche möglich.</span>
        <?php if ($voucherIdVal > 0) : ?>
          <p class="dg-mt-2">
            <a class="dg-button dg-button--small" href="/app?page=buchhaltung-beleg-form&amp;action=edit&amp;id=<?= $voucherIdVal ?>">Beleg öffnen</a>
          </p>
        <?php endif; ?>
      </div>

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
      <div class="dg-panel dg-panel--nested">
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

    <h2 class="dg-subsection-title">Sachverhalt</h2>
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

    <h2 class="dg-subsection-title">Dateien</h2>
    <?php if ($attachments !== []) : ?>
      <ul class="dg-list dg-mb-3">
        <?php foreach ($attachments as $file) : ?>
          <?php $fid = (int) ($file['id'] ?? 0); ?>
          <li>
            <a href="/app?page=reklamation-form&amp;action=edit&amp;id=<?= $id ?>&amp;download_attachment=<?= $fid ?>">
              <?= View::escape((string) ($file['original_name'] ?? 'Datei')) ?>
            </a>
            <span class="dg-muted">
              · <?= number_format(((int) ($file['size_bytes'] ?? 0)) / 1024, 1, ',', '.') ?> KB
              · <?= View::escape((string) ($file['created_at'] ?? '')) ?>
            </span>
            <?php if (!$readOnly && $fid > 0) : ?>
              <button type="submit" name="complaint_delete_attachment" value="<?= $fid ?>" class="dg-button dg-button--small" formnovalidate onclick="return confirm('Anhang wirklich löschen?');">Löschen</button>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php elseif ($id > 0) : ?>
      <p class="dg-muted">Noch keine Dateien.</p>
    <?php endif; ?>
    <?php if (!$readOnly) : ?>
      <label class="dg-field dg-field--wide">
        <span>Dateien hochladen</span>
        <input type="file" name="complaint_files[]" accept=".pdf,.jpg,.jpeg,.png,.webp,.gif,application/pdf,image/*" multiple>
        <span class="dg-field-hint">PDF oder Bilder, max. 10 Dateien à 10 MB. Speichern lädt die Dateien mit hoch<?= $id < 1 ? ' (auch bei neuer Reklamation)' : '' ?>.</span>
      </label>
    <?php endif; ?>

    <?php if (!$readOnly) : ?>
      <div class="dg-form-actions">
        <button type="submit" name="complaint_save" value="1" class="dg-button dg-button--primary">Speichern</button>
      </div>
    <?php endif; ?>
  </form>

  <?php if ($id > 0 && $canEdit && (string) ($form['fault'] ?? '') === ComplaintRepository::FAULT_COMPANY) : ?>
    <div class="dg-panel">
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
    <div class="dg-panel">
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
<?php if (!$readOnly) : ?>
<script>
  window.dgComplaintForm = { api: '/api/complaint' };
</script>
<script src="<?= View::escape(Asset::url('/assets/js/complaint-form.js')) ?>" defer></script>
<?php endif; ?>
