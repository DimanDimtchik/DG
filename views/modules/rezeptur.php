<?php
/**
 * @var list<array<string, mixed>> $recipeList
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$recipes = $recipeList ?? [];
$statusLabels = RecipeRepository::statusOptions();
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Rezeptur</h1>
      <p class="dg-lead">Stücklisten und Fertigungszeit — Vorkalkulation folgt in späteren Phasen.</p>
    </div>
    <div class="dg-toolbar">
      <?php if ($canEdit && $dbConnected) : ?>
        <a class="dg-button dg-button--primary" href="/app?page=rezeptur-form&amp;action=new">Neues Rezept</a>
        <a class="dg-button" href="/app?page=rezeptur-maschinen">Maschinen &amp; Stundensatz</a>
      <?php endif; ?>
    </div>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Datenbank nicht verbunden. Rezeptur benötigt
      <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Einstellungen → Datenbank</a>.
    </div>
  <?php endif; ?>

  <div class="dg-table-wrap">
    <table class="dg-table">
      <thead>
        <tr>
          <th>Titel</th>
          <th>Status</th>
          <th>Zielmenge</th>
          <th>Zeit (Min.)</th>
          <th>Stückliste</th>
          <th>Routing</th>
          <th>Version</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($recipes === []) : ?>
          <tr>
            <td colspan="8" class="dg-table__empty">Noch keine Rezepte. Legen Sie ein Rezept mit Materialien und Fertigungszeit an.</td>
          </tr>
        <?php else : ?>
          <?php foreach ($recipes as $row) : ?>
            <?php
              $rid = (int) ($row['id'] ?? 0);
              $status = (string) ($row['status'] ?? 'draft');
            ?>
            <tr>
              <td><strong><?= View::escape((string) ($row['title'] ?? '')) ?></strong></td>
              <td>
                <span class="dg-badge <?= $status === 'active' ? 'dg-badge--ok' : 'dg-badge--muted' ?>">
                  <?= View::escape($statusLabels[$status] ?? $status) ?>
                </span>
              </td>
              <td><?= View::escape((string) ($row['target_qty'] ?? '')) ?></td>
              <td><?= View::escape((string) ($row['labor_minutes'] ?? '')) ?></td>
              <td><?= (int) ($row['bom_count'] ?? 0) ?></td>
              <td><?= (int) ($row['routing_count'] ?? 0) ?></td>
              <td>v<?= (int) ($row['version'] ?? 1) ?></td>
              <td class="dg-table__actions">
                <a href="/app?page=rezeptur-form&amp;action=edit&amp;id=<?= $rid ?>"><?= $canEdit ? 'Bearbeiten' : 'Anzeigen' ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
