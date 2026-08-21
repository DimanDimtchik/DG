<?php
/** @var list<array<string, mixed>> $websiteFormList */
/** @var bool $canEdit */
/** @var bool $dbConnected */
/** @var array{type: string, message: string}|null $flash */
$forms = $websiteFormList ?? [];
$statusLabels = WebsiteFormRepository::statusOptions();
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Formulare</h1>
      <p class="dg-lead">Visuelle Formulare für die Website — Felder per Bausteine, Eingänge im Posteingang.</p>
    </div>
    <div class="dg-toolbar">
      <?php if ($canEdit && $dbConnected) : ?>
        <a class="dg-button dg-button--primary" href="/app?page=website-formular-form&amp;action=new">Neues Formular</a>
      <?php endif; ?>
    </div>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Datenbank nicht verbunden. Formulare benötigen
      <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Einstellungen → Datenbank</a>.
    </div>
  <?php endif; ?>

  <div class="dg-table-wrap">
    <table class="dg-table">
      <thead>
        <tr>
          <th>Titel</th>
          <th>Status</th>
          <th>Eingänge</th>
          <th>Geändert</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($forms === []) : ?>
          <tr>
            <td colspan="5" class="dg-table__empty">Noch keine Formulare. Legen Sie z.&nbsp;B. ein Kontaktformular an und betten Sie es in eine Seite ein.</td>
          </tr>
        <?php else : ?>
          <?php foreach ($forms as $row) : ?>
            <?php
              $status = (string) ($row['status'] ?? 'draft');
              $updated = (string) ($row['updated_at'] ?? '');
              $updatedLabel = $updated !== '' ? date('d.m.Y H:i', strtotime($updated)) : '—';
              $unread = (int) ($row['unread_count'] ?? 0);
              $total = (int) ($row['submission_count'] ?? 0);
              $fid = (int) ($row['id'] ?? 0);
            ?>
            <tr>
              <td><strong><?= View::escape((string) ($row['title'] ?? '')) ?></strong></td>
              <td>
                <span class="dg-badge <?= $status === 'published' ? 'dg-badge--ok' : 'dg-badge--muted' ?>">
                  <?= View::escape($statusLabels[$status] ?? $status) ?>
                </span>
              </td>
              <td>
                <?= $total ?>
                <?php if ($unread > 0) : ?>
                  <span class="dg-badge dg-badge--ok"><?= $unread ?> neu</span>
                <?php endif; ?>
              </td>
              <td><?= View::escape($updatedLabel) ?></td>
              <td class="dg-table__actions">
                <a href="/app?page=website-formular-form&amp;action=edit&amp;id=<?= $fid ?>"><?= $canEdit ? 'Bearbeiten' : 'Anzeigen' ?></a>
                ·
                <a href="/app?page=website-formular-inbox&amp;id=<?= $fid ?>">Eingänge</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
