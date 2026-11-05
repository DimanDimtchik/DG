<?php
/**
 * @var list<array<string, mixed>> $contractTemplates
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$contractTemplates = is_array($contractTemplates ?? null) ? $contractTemplates : [];
$statusLabels = ContractTemplateRepository::statusLabels();
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Vertragsvorlagen</h1>
      <p class="dg-lead">
        Paragraphen und Anlagen für Arbeitsverträge bearbeiten.
        Speichern legt einen Entwurf an — die PDF-Erzeugung nutzt vorerst weiter den Systemtext, bis die Freigabe folgt.
      </p>
    </div>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Datenbank nicht verbunden.</div>
  <?php elseif ($contractTemplates === []) : ?>
    <div class="dg-flash dg-flash--warning">
      Noch keine Vorlagen — Migration 140 und Seed prüfen.
    </div>
  <?php else : ?>
    <div class="dg-table-wrap">
      <table class="dg-table">
        <thead>
          <tr>
            <th>Titel</th>
            <th>Kennung</th>
            <th>Status</th>
            <th>Version</th>
            <th>Aktualisiert</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($contractTemplates as $row) : ?>
            <?php
              $id = (int) ($row['id'] ?? 0);
              $status = (string) ($row['status'] ?? '');
              $updated = (string) ($row['updated_at'] ?? '');
              try {
                  $updatedLabel = $updated !== '' ? (new DateTimeImmutable($updated))->format('d.m.Y H:i') : '—';
              } catch (Throwable) {
                  $updatedLabel = $updated !== '' ? $updated : '—';
              }
            ?>
            <tr>
              <td><strong><?= View::escape((string) ($row['title'] ?? '')) ?></strong></td>
              <td><?= View::escape((string) ($row['code'] ?? '')) ?></td>
              <td><?= View::escape($statusLabels[$status] ?? $status) ?></td>
              <td><?= View::escape((string) ($row['version'] ?? '')) ?></td>
              <td><?= View::escape($updatedLabel) ?></td>
              <td class="dg-table__actions">
                <div class="dg-table__actions-group">
                  <a class="dg-button dg-button--small" href="/app?page=hr-vertragsvorlage-form&amp;id=<?= $id ?>">Bearbeiten</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
