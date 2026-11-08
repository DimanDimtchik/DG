<?php
/**
 * ES2a–c: Unterschriften-Historie am Kontakt (neueste zuerst) + optional Upload + Soft-Hinweis.
 * Kein Löschen, kein Canvas/Mail.
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
<section class="dg-panel dg-panel--wide dg-mt-4" id="contact-signatures">
  <h2 class="dg-subsection-title">Unterschriften</h2>
  <div class="dg-flash dg-flash--info">
    Das gespeicherte Bild ist eine einfache elektronische Unterschrift für Briefe und E-Mails.
    Es ersetzt keine gesetzliche Schriftform und ist keine qualifizierte elektronische Signatur.
    Frühere Versionen bleiben sichtbar und können nicht gelöscht werden.
  </div>
  <p class="dg-field-hint dg-mt-3">Chronologisch, neueste zuerst. Verwendungszeitraum bleibt erhalten.</p>

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
      <p class="dg-field-hint">PNG oder WebP. Die bisher aktive Unterschrift wird beendet und bleibt in der Historie.</p>
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
  <?php elseif ($signatureOwnerMissing) : ?>
    <p class="dg-field-hint dg-mt-4">
      Upload nicht möglich: kein CRM-Benutzer ist mit diesem Kontakt verknüpft (E-Mail oder Login).
    </p>
  <?php endif; ?>
</section>
