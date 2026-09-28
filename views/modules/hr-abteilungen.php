<?php
/**
 * @var list<array<string, mixed>> $hrDepartments
 * @var bool $canManageTeams
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$hrDepartments = is_array($hrDepartments ?? null) ? $hrDepartments : [];
$canManageTeams = !empty($canManageTeams);

/**
 * @param array<string, mixed> $ma
 */
$renderPerson = static function (array $ma, bool $showTeamHint = true): void {
    $contactId = (int) ($ma['contact_id'] ?? 0);
    $label = (string) ($ma['label'] ?? '');
    $roleLabel = (string) ($ma['role_label'] ?? '');
    ?>
    <li>
      <?php if ($contactId > 0) : ?>
        <a href="/app?page=kontakte&amp;action=edit&amp;id=<?= $contactId ?>"><?= View::escape($label) ?></a>
      <?php else : ?>
        <?= View::escape($label) ?>
        <span class="dg-muted">· kein Kontakt verknüpft</span>
      <?php endif; ?>
      <?php if ($roleLabel !== '') : ?>
        <span class="dg-muted">· <?= View::escape($roleLabel) ?></span>
      <?php endif; ?>
      <?php if ($showTeamHint && !empty($ma['auto_chef'])) : ?>
        <span class="dg-muted">· fest</span>
      <?php elseif ($showTeamHint && !empty($ma['in_team'])) : ?>
        <span class="dg-muted">· im Team</span>
      <?php endif; ?>
    </li>
    <?php
};
?>
<div class="dg-wrap dg-hr-abteilungen">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Abteilungen</h1>
      <p class="dg-lead">Ansicht der Abteilungen (nur Lesen). Abteilungsleiter und Mitglieder getrennt; Zuordnung über MA-Kontakte; Namen verlinken zum Kontakt.</p>
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
    <div class="dg-dept-accordion__toolbar">
      <button type="button" class="dg-button dg-button--small" id="dg-hr-dept-expand-all">Alle aufklappen</button>
      <button type="button" class="dg-button dg-button--small" id="dg-hr-dept-collapse-all">Alle zuklappen</button>
    </div>

    <div id="dg-hr-abteilungen-accordion" class="dg-dept-accordion">
      <?php foreach ($hrDepartments as $dept) : ?>
        <?php
          $teams = is_array($dept['teams'] ?? null) ? $dept['teams'] : [];
          $without = is_array($dept['members_without_team'] ?? null) ? $dept['members_without_team'] : [];
          $leaders = is_array($dept['leaders'] ?? null) ? $dept['leaders'] : [];
          $regular = is_array($dept['regular_members'] ?? null) ? $dept['regular_members'] : [];
          $allMembers = is_array($dept['members'] ?? null) ? $dept['members'] : [];
          $summaryParts = [];
          $teamCount = count($teams);
          $leaderCount = count($leaders);
          $memberCount = count($regular);
          if ($teamCount > 0) {
              $summaryParts[] = $teamCount === 1 ? '1 Team' : $teamCount . ' Teams';
          }
          if ($leaderCount > 0) {
              $summaryParts[] = $leaderCount === 1 ? '1 Leiter' : $leaderCount . ' Leiter';
          }
          if ($memberCount > 0) {
              $summaryParts[] = $memberCount === 1 ? '1 Mitglied' : $memberCount . ' Mitglieder';
          } elseif ($allMembers === [] && $leaderCount < 1) {
              $summaryParts[] = 'Keine Mitglieder';
          }
          if (!empty($dept['is_hr'])) {
              $summaryParts[] = 'HR';
          }
          $summary = $summaryParts !== [] ? implode(' · ', $summaryParts) : 'Keine Teams / Mitglieder';
        ?>
        <section class="dg-dept-card" data-hr-dept-card>
          <header class="dg-dept-accordion__header">
            <button type="button" class="dg-dept-accordion__trigger" data-hr-dept-toggle aria-expanded="false">
              <span class="dg-dept-accordion__icon" aria-hidden="true"></span>
              <span class="dg-dept-accordion__label">
                <strong class="dg-dept-accordion__title">
                  <?= View::escape((string) ($dept['name'] ?? '')) ?>
                  <?php if (!empty($dept['is_hr'])) : ?>
                    <span class="dg-muted">· HR</span>
                  <?php endif; ?>
                </strong>
                <span class="dg-dept-accordion__meta"><?= View::escape($summary) ?></span>
              </span>
            </button>
          </header>

          <div class="dg-dept-accordion__panel" data-hr-dept-panel hidden>
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

            <h3 class="dg-subsection-title">Abteilungsleiter</h3>
            <?php if ($leaders === []) : ?>
              <p class="dg-muted">Kein Abteilungsleiter zugeordnet.</p>
            <?php else : ?>
              <ul class="dg-list">
                <?php foreach ($leaders as $ma) : ?>
                  <?php $renderPerson($ma, true); ?>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>

            <h3 class="dg-subsection-title">Abteilungsmitglieder</h3>
            <?php if ($regular === []) : ?>
              <p class="dg-muted">Keine weiteren Mitglieder.</p>
            <?php else : ?>
              <ul class="dg-list">
                <?php foreach ($regular as $ma) : ?>
                  <?php $renderPerson($ma, true); ?>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>

            <h3 class="dg-subsection-title">Teams</h3>
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
                        $teamMembers = is_array($team['members'] ?? null) ? $team['members'] : [];
                        $assets = is_array($team['assets'] ?? null) ? $team['assets'] : [];
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
                        <td>
                          <?php if ($teamMembers === []) : ?>
                            —
                          <?php else : ?>
                            <?php
                              $parts = [];
                              foreach ($teamMembers as $tm) {
                                $cid = (int) ($tm['contact_id'] ?? 0);
                                $tLabel = View::escape((string) ($tm['label'] ?? ''));
                                if ($cid > 0) {
                                  $parts[] = '<a href="/app?page=kontakte&amp;action=edit&amp;id=' . $cid . '">' . $tLabel . '</a>';
                                } else {
                                  $parts[] = $tLabel;
                                }
                              }
                              echo implode(', ', $parts);
                            ?>
                          <?php endif; ?>
                        </td>
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
            <?php if ($without === []) : ?>
              <p class="dg-muted">Keine: alle Abteilungsmitglieder sind einem Team zugeordnet — oder die Abteilung hat keine Mitglieder.</p>
            <?php else : ?>
              <p class="dg-muted" style="margin:0 0 0.5rem;">Der Abteilung zugeordnet, aber noch keinem Team.</p>
              <ul class="dg-list">
                <?php foreach ($without as $ma) : ?>
                  <?php $renderPerson($ma, false); ?>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </section>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
