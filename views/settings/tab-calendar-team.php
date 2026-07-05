<?php
/** @var bool $dbConnected */
/** @var string $calendarTeamTab */
/** @var list<array<string, mixed>> $calendarAreas */
/** @var list<array<string, mixed>> $calendarEmployees */
/** @var list<array<string, mixed>> $calendarAbsences */
/** @var list<User> $calendarLinkUsers */
/** @var list<array{id: string, name: string}> $calendarDepartmentOptions */
/** @var list<array{id: int, label: string}> $calendarLinkContacts */
/** @var list<array<string, mixed>> $calendarDepartmentSuggestions */

$baseUrl = SettingsRegistry::tabUrl('kalender-team');
$calendarDepartmentOptions = $calendarDepartmentOptions ?? [];
$calendarLinkContacts = $calendarLinkContacts ?? [];
$calendarDepartmentSuggestions = $calendarDepartmentSuggestions ?? [];
$csrf = Csrf::token();
?>
<nav class="dg-subtabs" aria-label="Team & Bereiche">
  <a href="<?= View::escape($baseUrl . '&ctab=bereiche') ?>" class="dg-subtabs__link<?= $calendarTeamTab === 'bereiche' ? ' is-active' : '' ?>">Bereiche</a>
  <a href="<?= View::escape($baseUrl . '&ctab=mitarbeiter') ?>" class="dg-subtabs__link<?= $calendarTeamTab === 'mitarbeiter' ? ' is-active' : '' ?>">Mitarbeiter</a>
</nav>

<?php if (!$dbConnected) : ?>
  <div class="dg-flash dg-flash--warning" style="margin-top:16px">
    Zum Speichern ist eine funktionierende <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Datenbankverbindung</a> erforderlich.
  </div>
<?php endif; ?>

<input type="hidden" id="dg-calendar-staff-csrf" value="<?= View::escape($csrf) ?>">

<?php if ($calendarTeamTab === 'bereiche') : ?>
  <section class="dg-panel" style="margin-top:16px">
    <p class="dg-lead">Tätigkeitsbereiche für den Terminkalender. Optional mit einer CRM-Abteilung verknüpfen — dann gelten nur Mitarbeiter dieser Abteilung (CRM-Benutzer oder verknüpfter Kontakt).</p>

    <div class="dg-table-wrap">
      <table class="dg-table dg-table--compact dg-cal-staff-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Abteilung</th>
            <th>Reihenfolge</th>
            <th>Aktiv</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($calendarAreas === []) : ?>
            <tr><td colspan="5">Noch keine Bereiche angelegt.</td></tr>
          <?php else : ?>
            <?php foreach ($calendarAreas as $area) : ?>
              <tr>
                <td><?= View::escape((string) $area['name']) ?></td>
                <td><?= View::escape((string) ($area['department_name'] ?? '') ?: '—') ?></td>
                <td><?= (int) $area['sort_order'] ?></td>
                <td><?= !empty($area['is_active']) ? 'Ja' : 'Nein' ?></td>
                <td>
                  <button type="button" class="dg-button dg-button--small dg-cal-edit-area" data-area="<?= View::escape(json_encode([
                      'id' => (int) $area['id'],
                      'name' => (string) $area['name'],
                      'department_id' => (string) ($area['department_id'] ?? ''),
                      'sort_order' => (int) $area['sort_order'],
                      'is_active' => (int) $area['is_active'],
                  ], JSON_THROW_ON_ERROR)) ?>">Bearbeiten</button>
                  <button type="button" class="dg-button dg-button--small dg-button--danger dg-cal-delete-area" data-id="<?= (int) $area['id'] ?>">Löschen</button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <h3 class="dg-subsection-title" id="dg-area-form-title">Neuen Bereich anlegen</h3>
    <form id="dg-calendar-area-form" class="dg-form">
      <input type="hidden" name="area_id" id="dg_area_id" value="">
      <div class="dg-form-grid">
        <label class="dg-field">
          <span>Bezeichnung *</span>
          <input type="text" name="name" id="dg_area_name" required>
        </label>
        <label class="dg-field">
          <span>Abteilung (CRM)</span>
          <select name="department_id" id="dg_area_department">
            <option value="">— Keine Zuordnung —</option>
            <?php foreach ($calendarDepartmentOptions as $department) : ?>
              <option value="<?= View::escape((string) $department['id']) ?>"><?= View::escape((string) $department['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <small class="dg-field-hint">Optional: Buchbare Mitarbeiter im Bereich werden auf diese Abteilung gefiltert.</small>
        </label>
        <label class="dg-field">
          <span>Reihenfolge</span>
          <input type="number" name="sort_order" id="dg_area_sort" min="0" value="0">
        </label>
        <label class="dg-field">
          <span><input type="checkbox" name="is_active" id="dg_area_active" value="1" checked> Bereich ist aktiv</span>
        </label>
      </div>
      <div class="dg-form-actions">
        <button type="submit" class="dg-button dg-button--primary" id="dg-area-submit">Bereich speichern</button>
        <button type="button" class="dg-button" id="dg-area-cancel" hidden>Abbrechen</button>
      </div>
    </form>
  </section>

<?php else : ?>
  <section class="dg-panel" style="margin-top:16px">
    <p class="dg-lead">Kalender-Mitarbeiter Bereichen zuordnen, optional mit einem Mitarbeiter-Kontakt verknüpfen, Arbeitszeiten und Abwesenheiten pflegen.</p>

    <?php if ($calendarAreas === []) : ?>
      <div class="dg-flash dg-flash--warning">
        Bitte legen Sie zuerst mindestens einen <a href="<?= View::escape($baseUrl . '&ctab=bereiche') ?>">Bereich</a> an.
      </div>
    <?php else : ?>
      <?php
        $actionableSuggestions = array_values(array_filter(
            $calendarDepartmentSuggestions,
            static fn (array $suggestion): bool => !empty($suggestion['can_add'])
        ));
      ?>
      <?php if ($calendarDepartmentSuggestions !== []) : ?>
        <details class="dg-cal-suggestions"<?= $actionableSuggestions !== [] ? ' open' : '' ?>>
          <summary class="dg-subsection-title dg-cal-suggestions__summary">
            Vorschläge aus Abteilungsmitgliedern
            <?php if ($actionableSuggestions !== []) : ?>
              <span class="dg-cal-suggestions__badge"><?= count($actionableSuggestions) ?> übernehmbar</span>
            <?php endif; ?>
          </summary>
          <p class="dg-lead">Mitglieder von CRM-Abteilungen, deren Bereiche mit derselben Abteilung verknüpft sind. Der passende Mitarbeiter-Kontakt wird per E-Mail oder Login zugeordnet.</p>
          <div class="dg-table-wrap">
            <table class="dg-table dg-table--compact dg-cal-suggestions-table">
              <thead>
                <tr>
                  <th>Abteilung</th>
                  <th>CRM-Benutzer</th>
                  <th>Kontakt</th>
                  <th>Bereiche</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($calendarDepartmentSuggestions as $suggestion) : ?>
                  <tr class="<?= !empty($suggestion['can_add']) ? '' : 'dg-cal-suggestions__row--muted' ?>">
                    <td><?= View::escape((string) $suggestion['department_name']) ?></td>
                    <td><?= View::escape((string) $suggestion['user_label']) ?></td>
                    <td>
                      <?php if (!empty($suggestion['has_contact'])) : ?>
                        <a href="/app?page=kontakte&amp;id=<?= (int) $suggestion['contact_id'] ?>"><?= View::escape((string) $suggestion['contact_label']) ?></a>
                      <?php else : ?>
                        <span class="dg-cal-warning">Nicht gefunden</span>
                      <?php endif; ?>
                    </td>
                    <td><?= View::escape(implode(', ', $suggestion['area_names'] ?? [])) ?></td>
                    <td>
                      <?php if (!empty($suggestion['can_add'])) : ?>
                        Bereit
                      <?php else : ?>
                        <?= View::escape((string) ($suggestion['hint'] ?? '')) ?>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if (!empty($suggestion['can_add'])) : ?>
                        <button
                          type="button"
                          class="dg-button dg-button--small dg-button--primary dg-cal-apply-suggestion"
                          data-suggestion="<?= View::escape(json_encode([
                              'contact_id' => (int) $suggestion['contact_id'],
                              'contact_label' => (string) $suggestion['contact_label'],
                              'user_id' => (int) $suggestion['user_id'],
                              'user_label' => (string) $suggestion['user_label'],
                              'name' => (string) $suggestion['contact_label'],
                              'area_ids' => array_map('intval', $suggestion['area_ids'] ?? []),
                          ], JSON_THROW_ON_ERROR)) ?>"
                        >Übernehmen</button>
                      <?php elseif (empty($suggestion['has_contact'])) : ?>
                        <a class="dg-button dg-button--small" href="/app?page=kontakte&amp;form=new">Kontakt anlegen</a>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </details>
      <?php endif; ?>

      <div class="dg-table-wrap">
        <table class="dg-table dg-table--compact dg-cal-staff-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Kontakt</th>
              <th>Bereiche</th>
              <th>Arbeitszeiten</th>
              <th>Reihenf.</th>
              <th>CRM-Benutzer</th>
              <th>Vorgesetzter</th>
              <th>Aktiv</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($calendarEmployees === []) : ?>
              <tr><td colspan="9">Noch keine Mitarbeiter angelegt.</td></tr>
            <?php else : ?>
              <?php foreach ($calendarEmployees as $employee) : ?>
                <?php
                  $areaNames = [];
                  foreach ($calendarAreas as $area) {
                      if (in_array((int) $area['id'], $employee['area_ids'] ?? [], true)) {
                          $areaNames[] = (string) $area['name'];
                      }
                  }
                  $hasHours = CalendarStaffRepository::employeeHasHours((int) $employee['id']);
                  $hoursSummary = CalendarStaffRepository::hoursSummary((int) $employee['id']);
                  $linkedUserId = (int) ($employee['user_id'] ?? 0);
                  $linkedUser = $linkedUserId > 0 ? UserRepository::findById($linkedUserId) : null;
                  $supervisorId = (int) ($employee['supervisor_id'] ?? 0);
                  $supervisorName = '—';
                  if ($supervisorId > 0) {
                      foreach ($calendarEmployees as $emp) {
                          if ((int) $emp['id'] === $supervisorId) {
                              $supervisorName = (string) $emp['name'];
                              break;
                          }
                      }
                  }
                  $linkedContactId = (int) ($employee['contact_id'] ?? 0);
                  $linkedContactLabel = (string) ($employee['contact_label'] ?? '');
                ?>
                <tr data-employee-row="<?= (int) $employee['id'] ?>">
                  <td><?= View::escape((string) $employee['name']) ?></td>
                  <td>
                    <?php if ($linkedContactId > 0) : ?>
                      <a href="/app?page=kontakte&amp;id=<?= $linkedContactId ?>"><?= View::escape($linkedContactLabel !== '' ? $linkedContactLabel : 'Kontakt #' . $linkedContactId) ?></a>
                    <?php else : ?>
                      —
                    <?php endif; ?>
                  </td>
                  <td><?= View::escape($areaNames !== [] ? implode(', ', $areaNames) : '—') ?></td>
                  <td>
                    <?php if ($hasHours) : ?>
                      <span class="dg-cal-hours-summary"><?= View::escape($hoursSummary) ?></span>
                    <?php else : ?>
                      <span class="dg-cal-warning">Noch keine Arbeitszeiten (nicht buchbar)</span>
                    <?php endif; ?>
                  </td>
                  <td><?= (int) $employee['sort_order'] ?></td>
                  <td><?= $linkedUser ? View::escape($linkedUser->displayName) : '—' ?></td>
                  <td><?= View::escape($supervisorName) ?></td>
                  <td><?= !empty($employee['is_active']) ? 'Ja' : 'Nein' ?></td>
                  <td>
                    <button type="button" class="dg-button dg-button--small dg-cal-toggle-hours" data-employee-id="<?= (int) $employee['id'] ?>">Zeiten</button>
                    <button type="button" class="dg-button dg-button--small dg-cal-edit-employee" data-employee="<?= View::escape(json_encode([
                        'id' => (int) $employee['id'],
                        'name' => (string) $employee['name'],
                        'contact_id' => $linkedContactId,
                        'contact_label' => $linkedContactLabel,
                        'sort_order' => (int) $employee['sort_order'],
                        'is_active' => (int) $employee['is_active'],
                        'user_id' => $linkedUserId,
                        'supervisor_id' => $supervisorId,
                        'area_ids' => array_map('intval', $employee['area_ids'] ?? []),
                    ], JSON_THROW_ON_ERROR)) ?>">Bearbeiten</button>
                    <button type="button" class="dg-button dg-button--small dg-button--danger dg-cal-delete-employee" data-id="<?= (int) $employee['id'] ?>">Löschen</button>
                  </td>
                </tr>
                <tr class="dg-cal-hours-row" data-employee-id="<?= (int) $employee['id'] ?>" hidden>
                  <td colspan="9"><div class="dg-cal-hours-host"></div></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <h3 class="dg-subsection-title" id="dg-employee-form-title">Neuen Mitarbeiter anlegen</h3>
      <form id="dg-calendar-employee-form" class="dg-form">
        <input type="hidden" name="employee_id" id="dg_employee_id" value="">
        <div class="dg-form-grid">
          <label class="dg-field">
            <span>Kontakt (Mitarbeiter)</span>
            <select name="contact_id" id="dg_employee_contact">
              <option value="0">— Kein Kontakt —</option>
              <?php foreach ($calendarLinkContacts as $contact) : ?>
                <option value="<?= (int) $contact['id'] ?>" data-label="<?= View::escape((string) $contact['label']) ?>"><?= View::escape((string) $contact['label']) ?></option>
              <?php endforeach; ?>
            </select>
            <small class="dg-field-hint">Eigenmitarbeiter aus Kontakte. Name wird beim Speichern übernommen, wenn leer.</small>
          </label>
          <label class="dg-field dg-field--wide">
            <span>Name *</span>
            <input type="text" name="name" id="dg_employee_name" required>
          </label>
          <label class="dg-field dg-field--wide">
            <span>Bereiche *</span>
            <div class="dg-cal-area-checkboxes">
              <?php foreach ($calendarAreas as $area) : ?>
                <label class="dg-cal-area-check">
                  <input
                    type="checkbox"
                    name="area_ids[]"
                    value="<?= (int) $area['id'] ?>"
                    data-department-id="<?= View::escape(trim((string) ($area['department_id'] ?? ''))) ?>"
                  >
                  <?= View::escape((string) $area['name']) ?><?= empty($area['is_active']) ? ' (inaktiv)' : '' ?>
                </label>
              <?php endforeach; ?>
            </div>
          </label>
          <label class="dg-field">
            <span>Reihenfolge</span>
            <input type="number" name="sort_order" id="dg_employee_sort" min="0" value="0">
          </label>
          <label class="dg-field">
            <span>CRM-Benutzer</span>
            <select name="user_id" id="dg_employee_user">
              <option value="0">— Kein Benutzer —</option>
              <?php foreach ($calendarLinkUsers as $linkUser) : ?>
                <option value="<?= (int) $linkUser->id ?>"><?= View::escape($linkUser->displayName) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="dg-field">
            <span>Vorgesetzter</span>
            <select name="supervisor_id" id="dg_employee_supervisor">
              <option value="0">— Kein Vorgesetzter —</option>
              <?php foreach ($calendarEmployees as $supervisorOption) : ?>
                <option value="<?= (int) $supervisorOption['id'] ?>"><?= View::escape((string) $supervisorOption['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="dg-field">
            <span><input type="checkbox" name="is_active" id="dg_employee_active" value="1" checked> Mitarbeiter ist aktiv</span>
          </label>
        </div>
        <div class="dg-form-actions">
          <button type="submit" class="dg-button dg-button--primary" id="dg-employee-submit">Mitarbeiter speichern</button>
          <button type="button" class="dg-button" id="dg-employee-cancel" hidden>Abbrechen</button>
        </div>
      </form>

      <?php if ($calendarEmployees !== []) : ?>
        <hr class="dg-cal-divider">
        <h3 class="dg-subsection-title">Abwesenheiten</h3>
        <p class="dg-lead">Urlaub, Krankheit und andere Abwesenheiten blockieren ganze Kalendertage für den gewählten Mitarbeiter.</p>

        <?php if ($calendarAbsences !== []) : ?>
          <div class="dg-table-wrap">
            <table class="dg-table dg-table--compact">
              <thead>
                <tr>
                  <th>Mitarbeiter</th>
                  <th>Art</th>
                  <th>Von</th>
                  <th>Bis</th>
                  <th>Notiz</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($calendarAbsences as $absence) : ?>
                  <tr>
                    <td><?= View::escape((string) $absence['employee_name']) ?></td>
                    <td><?= View::escape(CalendarStaffRepository::absenceTypeLabel((string) $absence['absence_type'])) ?></td>
                    <td><?= View::escape((string) $absence['start_date']) ?></td>
                    <td><?= View::escape((string) $absence['end_date']) ?></td>
                    <td><?= View::escape((string) ($absence['note'] ?? '')) ?: '—' ?></td>
                    <td>
                      <button type="button" class="dg-button dg-button--small dg-button--danger dg-cal-delete-absence" data-id="<?= (int) $absence['id'] ?>">Löschen</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else : ?>
          <p class="dg-lead">Keine Abwesenheiten eingetragen.</p>
        <?php endif; ?>

        <h4 class="dg-subsection-title">Abwesenheit hinzufügen</h4>
        <form id="dg-calendar-absence-form" class="dg-form">
          <div class="dg-form-grid">
            <label class="dg-field">
              <span>Mitarbeiter *</span>
              <select name="employee_id" required>
                <option value="">— Bitte wählen —</option>
                <?php foreach ($calendarEmployees as $employee) : ?>
                  <option value="<?= (int) $employee['id'] ?>"><?= View::escape((string) $employee['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="dg-field">
              <span>Art</span>
              <select name="absence_type">
                <option value="vacation">Urlaub</option>
                <option value="sick">Krank</option>
                <option value="other">Sonstiges</option>
              </select>
            </label>
            <label class="dg-field">
              <span>Von *</span>
              <input type="date" name="start_date" required>
            </label>
            <label class="dg-field">
              <span>Bis *</span>
              <input type="date" name="end_date" required>
            </label>
            <label class="dg-field dg-field--wide">
              <span>Notiz</span>
              <input type="text" name="note" maxlength="255">
            </label>
          </div>
          <div class="dg-form-actions">
            <button type="submit" class="dg-button dg-button--primary">Abwesenheit hinzufügen</button>
          </div>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </section>
<?php endif; ?>
