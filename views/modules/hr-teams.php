<?php
/**
 * @var list<array<string, mixed>> $hrTeams
 * @var bool $canManageTeams
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$hrTeams = is_array($hrTeams ?? null) ? $hrTeams : [];
$canManageTeams = !empty($canManageTeams);
?>
<div class="dg-wrap dg-hr-teams">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Teams</h1>
      <p class="dg-lead">Teams je Abteilung — Mitglieder und Inventar (Fahrzeuge, Geräte).</p>
    </div>
    <div class="dg-page-header__actions">
      <?php if ($canManageTeams) : ?>
        <a class="dg-button dg-button--primary" href="/app?page=hr-team-form&amp;action=new">Neues Team</a>
      <?php endif; ?>
      <a class="dg-button" href="/app?page=hr-abteilungen">Abteilungen</a>
    </div>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Datenbank nicht verbunden.</div>
  <?php elseif ($hrTeams === []) : ?>
    <div class="dg-flash dg-flash--info">Noch keine Teams angelegt.</div>
  <?php else : ?>
    <div class="dg-table-wrap">
      <table class="dg-table">
        <thead>
          <tr>
            <th>Team</th>
            <th>Abteilung</th>
            <th>Mitglieder</th>
            <th>Inventar</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($hrTeams as $team) : ?>
            <tr>
              <td><strong><?= View::escape((string) ($team['name'] ?? '')) ?></strong></td>
              <td><?= View::escape((string) ($team['department_name'] ?? '')) ?></td>
              <td><?= (int) ($team['member_count'] ?? 0) ?></td>
              <td><?= (int) ($team['asset_count'] ?? 0) ?></td>
              <td><?= !empty($team['is_active']) ? 'Aktiv' : 'Inaktiv' ?></td>
              <td><a href="/app?page=hr-team-form&amp;action=edit&amp;id=<?= (int) ($team['id'] ?? 0) ?>">Öffnen</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
