<?php
/**
 * Unterschriften-Historie + Upload + Canvas (ES2–ES3, Texte ES8a).
 * Kein Löschen-Button; Zweck Brief/Mail; kein QES.
 *
 * @var int|null $contactId
 * @var list<array<string, mixed>>|null $contactSignatures
 * @var bool|null $canUploadContactSignature
 * @var bool|null $signatureOwnerMissing
 */
$contactId = (int) ($contactId ?? 0);
$contactSignatures = $contactSignatures ?? [];
$canUploadContactSignature = !empty($canUploadContactSignature);
$signatureOwnerMissing = !empty($signatureOwnerMissing);
$inAccordion = !empty($inAccordion);
if ($contactId <= 0) {
    return;
}

$sourceLabels = [
    'upload' => 'Upload',
    'canvas' => 'Zeichenfläche',
    'app' => 'App',
    'import' => 'Import',
];

$formatDt = static function (string $raw): string {
    $raw = trim($raw);
    if ($raw === '') {
        return '—';
    }
    $ts = strtotime($raw);

    return $ts !== false ? date('d.m.Y H:i', $ts) : $raw;
};
?>
<section class="<?= $inAccordion ? 'dg-stack' : 'dg-panel dg-panel--wide dg-mt-4' ?>" id="contact-signatures">
  <?php if (!$inAccordion) : ?>
    <h2 class="dg-subsection-title">Unterschriften</h2>
  <?php endif; ?>
  <div class="dg-flash dg-flash--info">
    <strong>Zweck:</strong> Unterschriftsbild für Briefe und E-Mails in diesem CRM (nur für Berechtigte).
    Es ist eine einfache elektronische Unterschrift — keine gesetzliche Schriftform und keine qualifizierte elektronische Signatur (QES).
    Frühere Versionen bleiben in der Historie und können hier nicht gelöscht werden.
  </div>
  <p class="dg-field-hint dg-mt-3">Liste chronologisch, neueste zuerst. Verwendungszeitraum bleibt erhalten. Löschen ist nicht vorgesehen.</p>

  <?php if ($contactSignatures === []) : ?>
    <p class="dg-lead">Noch keine Unterschriften für diesen Kontakt.</p>
  <?php else : ?>
    <div class="dg-table-wrap">
      <table class="dg-table dg-table--compact">
        <thead>
          <tr>
            <th>Zeitraum</th>
            <th>Quelle</th>
            <th>Status</th>
            <th>Vorschau</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($contactSignatures as $row) : ?>
            <?php
              $mediaId = (string) ($row['media_id'] ?? '');
              $source = (string) ($row['source'] ?? '');
              $validFrom = (string) ($row['valid_from'] ?? '');
              $validTo = $row['valid_to'] ?? null;
              $isActive = !empty($row['is_active']) || $validTo === null || $validTo === '';
              $fromLabel = $formatDt($validFrom);
              $period = $isActive
                  ? $fromLabel . ' – offen'
                  : $fromLabel . ' – ' . $formatDt((string) $validTo);
              $previewUrl = MediaId::isValid($mediaId) ? MediaStorage::publicUrl($mediaId) : '';
            ?>
            <tr>
              <td><?= View::escape($period) ?></td>
              <td><?= View::escape($sourceLabels[$source] ?? $source) ?></td>
              <td>
                <?php if ($isActive) : ?>
                  <span class="dg-badge dg-badge--ok">Aktiv</span>
                <?php else : ?>
                  <span class="dg-badge dg-badge--muted">Beendet</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($previewUrl !== '') : ?>
                  <img src="<?= View::escape($previewUrl) ?>" alt="Unterschrift" width="120" height="40">
                <?php else : ?>
                  —
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($canUploadContactSignature) : ?>
    <form
      class="dg-form dg-mt-4"
      method="post"
      action="/app?page=kontakte"
      enctype="multipart/form-data"
    >
      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
      <input type="hidden" name="id" value="<?= (int) $contactId ?>">
      <h3 class="dg-subsection-title">Neue Unterschrift (Upload)</h3>
      <p class="dg-field-hint">PNG oder WebP. Die bisher aktive Version wird beendet und bleibt sichtbar in der Historie (kein Löschen).</p>
      <div class="dg-form-grid">
        <label class="dg-field dg-field--wide">
          <span>Bilddatei</span>
          <input
            type="file"
            name="signature_file"
            accept=".png,.webp,image/png,image/webp"
            required
          >
        </label>
      </div>
      <div class="dg-form-actions">
        <button type="submit" name="contact_signature_upload" value="1" class="dg-button dg-button--primary">
          Unterschrift speichern
        </button>
      </div>
    </form>

    <form
      class="dg-form dg-mt-4"
      method="post"
      action="/app?page=kontakte"
      data-signature-canvas-form
    >
      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
      <input type="hidden" name="id" value="<?= (int) $contactId ?>">
      <input type="hidden" name="signature_png" value="">
      <h3 class="dg-subsection-title">Neue Unterschrift (zeichnen)</h3>
      <p class="dg-field-hint">Mit Maus oder Finger zeichnen. Speichern legt eine neue aktive Version an; die alte bleibt in der Historie.</p>
      <div class="dg-signature-pad__wrap" data-signature-pad>
        <canvas
          class="dg-signature-pad"
          data-signature-canvas
          width="560"
          height="160"
          aria-label="Unterschrift zeichnen"
        ></canvas>
        <div class="dg-btn-row dg-mt-3">
          <button type="button" class="dg-button" data-signature-clear>Zeichenfläche leeren</button>
        </div>
      </div>
      <div class="dg-form-actions">
        <button type="submit" name="contact_signature_canvas" value="1" class="dg-button dg-button--primary">
          Zeichnung speichern
        </button>
      </div>
    </form>
    <script src="<?= View::escape(Asset::url('/assets/js/contact-signature-canvas.js')) ?>" defer></script>
  <?php elseif ($signatureOwnerMissing) : ?>
    <p class="dg-field-hint dg-mt-4">
      Upload nicht möglich: kein CRM-Benutzer ist mit diesem Kontakt verknüpft (E-Mail oder Login).
    </p>
  <?php endif; ?>
</section>
