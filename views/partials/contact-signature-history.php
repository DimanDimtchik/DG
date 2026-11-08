<?php
/**
 * ES2a: Unterschriften-Historie am Kontakt (neueste zuerst).
 * Kein Upload (ES2b), kein Löschen.
 *
 * @var int|null $contactId
 * @var list<array<string, mixed>>|null $contactSignatures
 */
$contactId = (int) ($contactId ?? 0);
$contactSignatures = $contactSignatures ?? [];
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
<section class="dg-panel dg-panel--wide dg-mt-4">
  <h2 class="dg-subsection-title">Unterschriften</h2>
  <p class="dg-field-hint">Chronologisch, neueste zuerst. Verwendungszeitraum bleibt erhalten — Löschen ist nicht möglich.</p>

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
</section>
