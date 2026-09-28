<?php
/**
 * @var list<array<string, mixed>> $hrDepartments
 * @var bool $canManageTeams
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$hrDepartments = is_array($hrDepartments ?? null) ? $hrDepartments : [];
$canManageTeams = !empty($canManageTeams);
?>
<div class="dg-wrap dg-hr-abteilungen">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Abteilungen</h1>
      <p class="dg-lead">Ansicht der in den Einstellungen gepflegten Abteilungen — nur Lesen. Teams und Mitarbeiter ohne Team je Abteilung.</p>
    </div>
    <div class="dg-page-header__actions">
      <?php if ($canManageTeams) : ?>
        <a class="dg-button dg-button--primary" href="/app?page=hr-team-form&amp;action=new">Neues Team</a>
      <?php endif; ?>
      <a class="dg-button" href="/app?page=hr-teams">Alle Teams</a>
      <a class="dg-button" href="<?= View::escape(SettingsRegistry::tabUrl('abteilungen')) ?>">Einstellungen → Abteilungen</a>
    </div>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Datenbank nicht verbunden.</div>
  <?php elseif ($hrDepartments === []) : ?>
    <div class="dg-flash dg-flash--info">Noch keine Abteilungen. Bitte unter Einstellungen → HR → Abteilungen anlegen.</div>
  <?php else : ?>
    <?php foreach ($hrDepartments as $dept) : ?>
      <section class="dg-panel" style="margin-bottom: 1.25rem;">
        <h2 class="dg-subsection-title" style="margin-top:0;">
          <?= View::escape((string) ($dept['name'] ?? '')) ?>
          <?php if (!empty($dept['is_hr'])) : ?>
            <span class="dg-muted">· HR</span>
          <?php endif; ?>
        </h2>
        <?php if (trim((string) ($dept['description'] ?? '')) !== '') : ?>
          <p class="dg-lead"><?= View::escape((string) $dept['description']) ?></p>
        <?php endif; ?>
        <p class="dg-muted" style="margin:0 0 0.75rem;">
          <?php
            $flags = [];
            if (!empty($dept['allow_article_catalog'])) {
                $flags[] = 'Artikel/Leistungen';
            }
            if (!empty($dept['allow_contact_delete'])) {
                $flags[] = 'Kontakte löschen';
            }
            $mod = is_array($dept['module_labels'] ?? null) ? $dept['module_labels'] : [];
            echo View::escape(implode(' · ', array_merge($flags, $mod)) ?: 'Keine besonderen Modulrechte');
          ?>
        </p>

        <h3 class="dg-subsection-title">Teams</h3>
        <?php $teams = is_array($dept['teams'] ?? null) ? $dept['teams'] : []; ?>
        <?php if ($teams === []) : ?>
          <p class="dg-muted">Keine Teams in dieser Abteilung.</p>
        <?php else : ?>
          <div class="dg-table-wrap">
            <table class="dg-table dg-table--compact">
              <thead>
                <tr>
                  <th>Team</th>
                  <th>Mitglieder</th>
                  <th>Inventar</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($teams as $team) : ?>
                  <?php
                    $members = is_array($team['members'] ?? null) ? $team['members'] : [];
                    $assets = is_array($team['assets'] ?? null) ? $team['assets'] : [];
                    $memberLabels = array_map(static fn (array $m): string => (string) ($m['label'] ?? ''), $members);
                    $assetLabels = [];
                    foreach ($assets as $asset) {
                      $line = (string) ($asset['kind_label'] ?? '') . ': ' . (string) ($asset['name'] ?? '');
                      if (!empty($asset['parent_asset_id'])) {
                        $line .= ' (im Fahrzeug)';
                      }
                      $assetLabels[] = $line;
                    }
                  ?>
                  <tr>
                    <td><strong><?= View::escape((string) ($team['name'] ?? '')) ?></strong></td>
                    <td><?= View::escape($memberLabels !== [] ? implode(', ', $memberLabels) : '—') ?></td>
                    <td><?= View::escape($assetLabels !== [] ? implode('; ', $assetLabels) : '—') ?></td>
                    <td><?= !empty($team['is_active']) ? 'Aktiv' : 'Inaktiv' ?></td>
                    <td>
                      <a href="/app?page=hr-team-form&amp;action=edit&amp;id=<?= (int) ($team['id'] ?? 0) ?>">Öffnen</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

        <h3 class="dg-subsection-title">Mitarbeiter ohne Team</h3>
        <?php $without = is_array($dept['members_without_team'] ?? null) ? $dept['members_without_team'] : []; ?>
        <?php if ($without === []) : ?>
          <p class="dg-muted">Alle Abteilungsmitglieder sind einem Team zugeordnet (oder es gibt keine Mitglieder).</p>
        <?php else : ?>
          <ul class="dg-list">
            <?php foreach ($without as $ma) : ?>
              <li>
                <?= View::escape((string) ($ma['label'] ?? '')) ?>
                <span class="dg-muted">· <?= View::escape((string) ($ma['role_label'] ?? '')) ?></span>
                <?php if ((int) ($ma['contact_id'] ?? 0) > 0) : ?>
                  · <a href="/app?page=kontakte&amp;action=edit&amp;id=<?= (int) $ma['contact_id'] ?>">Kontakt</a>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <h3 class="dg-subsection-title">Alle Abteilungsmitglieder</h3>
        <?php $allMembers = is_array($dept['members'] ?? null) ? $dept['members'] : []; ?>
        <?php if ($allMembers === []) : ?>
          <p class="dg-muted">Keine Mitglieder (Zuordnung unter Einstellungen → Abteilungen).</p>
        <?php else : ?>
          <ul class="dg-list">
            <?php foreach ($allMembers as $ma) : ?>
              <li>
                <?= View::escape((string) ($ma['label'] ?? '')) ?>
                <span class="dg-muted">· <?= View::escape((string) ($ma['role_label'] ?? '')) ?></span>
                <?php if (!empty($ma['in_team'])) : ?>
                  <span class="dg-muted">· im Team</span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
