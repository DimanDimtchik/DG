<?php
/** @var User $user */
/** @var string|null $navMode */
/** @var list<array{id: string, name: string, member_role: string}> $departments */

$dashboardTiles = MenuRegistry::dashboardTiles($user);
?>
<div class="dg-wrap">
  <header class="dg-page-header">
    <h1 class="dg-page-title">Willkommen, <?= View::escape($user->displayName) ?></h1>
    <p class="dg-lead">Wählen Sie im Menü links ein Modul oder starten Sie direkt über eine Kachel.</p>
  </header>

  <?php if ($dashboardTiles !== []) : ?>
    <div class="dg-grid">
      <?php foreach ($dashboardTiles as $tile) : ?>
        <a class="dg-card" href="<?= View::escape($tile['href']) ?>">
          <span class="dg-card__icon" aria-hidden="true">
            <?php View::render('partials/icon', ['name' => $tile['icon']]); ?>
          </span>
          <h2><?= View::escape($tile['label']) ?></h2>
          <?php if ($tile['description'] !== '') : ?>
            <p><?= View::escape($tile['description']) ?></p>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else : ?>
    <div class="dg-panel">
      <p class="dg-lead">Für Ihr Konto sind derzeit keine Module freigeschaltet.</p>
    </div>
  <?php endif; ?>

  <?php if ($navMode === null && RoleResolver::isActiveEmployee($user)) : ?>
    <div class="dg-panel dg-panel--notice" style="margin-top: 24px;">
      <h2>Keine Abteilungszuordnung</h2>
      <p>Sie sind als Mitarbeiter registriert, aber noch keiner Abteilung zugeordnet. Bitte wenden Sie sich an die Verwaltung.</p>
    </div>
  <?php endif; ?>
</div>
