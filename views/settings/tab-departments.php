<?php
/** @var list<array{id: string, name: string, description: string, sort_order: int, members: list<array{user_id: int, role: string}>}> $departmentsData */
/** @var list<User> $departmentEmployees */
/** @var array<string, mixed> $notificationTemplateData */
/** @var bool $dbConnected */

if ($departmentsData === []) {
    $departmentsData = DefaultDepartments::withModulesAndMembers();
    foreach ($departmentsData as $index => $department) {
        if ($department['members'] === []) {
            $departmentsData[$index]['members'] = [['user_id' => 0, 'role' => 'member']];
        }
    }
}
$departmentModuleLabels = DepartmentAccess::MODULE_LABELS;
?>
<form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('abteilungen')) ?>" id="dg-departments-form">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

  <p class="dg-lead">
    Legen Sie Abteilungen an und ordnen Sie <strong>Mitarbeiter</strong> als Mitglied oder Abteilungsleiter zu.
    Pro Abteilung steuern Sie die Sidebar-Sichtbarkeit, HR-Rechte sowie optional die Artikel- und Leistungspflege.
  </p>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Zum Speichern ist eine funktionierende <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Datenbankverbindung</a> erforderlich.
      <?php if ($departmentsData !== []) : ?>
        Die Anzeige kann aus der lokalen Demo-Konfiguration stammen.
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="dg-dept-accordion__toolbar">
    <button type="button" class="dg-button dg-button--small" id="dg-dept-expand-all">Alle aufklappen</button>
    <button type="button" class="dg-button dg-button--small" id="dg-dept-collapse-all">Alle zuklappen</button>
  </div>

  <div id="dg-departments-repeater" class="dg-dept-accordion">
    <?php foreach ($departmentsData as $di => $dept) : ?>
      <?php
        $members = $dept['members'] !== [] ? $dept['members'] : [['user_id' => 0, 'role' => 'member']];
        $deptModules = is_array($dept['modules'] ?? null) ? $dept['modules'] : DepartmentAccess::defaultModules();
        $isHr = !empty($dept['is_hr']);
        $allowCatalog = !empty($dept['allow_article_catalog']);
        $deptName = trim((string) $dept['name']);
        $headerTitle = $deptName !== '' ? $deptName : 'Abteilung #' . ((int) $di + 1);
        $memberCount = 0;
        foreach ($members as $member) {
            if ((int) ($member['user_id'] ?? 0) > 0) {
                ++$memberCount;
            }
        }
      ?>
      <section class="dg-dept-card" data-dept-card>
        <input type="hidden" name="departments[<?= (int) $di ?>][id]" value="<?= View::escape((string) $dept['id']) ?>">
        <header class="dg-dept-accordion__header">
          <button type="button" class="dg-dept-accordion__trigger" data-dept-toggle aria-expanded="false">
            <span class="dg-dept-accordion__icon" aria-hidden="true"></span>
            <span class="dg-dept-accordion__label">
              <strong class="dg-dept-accordion__title" data-dept-title><?= View::escape($headerTitle) ?></strong>
              <span class="dg-dept-accordion__meta" data-dept-summary><?php
                $summaryParts = [];
                if ($memberCount > 0) {
                    $summaryParts[] = $memberCount === 1 ? '1 Mitglied' : $memberCount . ' Mitglieder';
                }
                if ($isHr) {
                    $summaryParts[] = 'HR-Rechte';
                }
                if ($allowCatalog) {
                    $summaryParts[] = 'Artikel/Leistungen';
                }
                echo View::escape($summaryParts !== [] ? implode(' · ', $summaryParts) : 'Keine Mitglieder');
              ?></span>
            </span>
          </button>
          <button type="button" class="dg-button dg-button--danger dg-button--small" data-dept-remove>Abteilung löschen</button>
        </header>

        <div class="dg-dept-accordion__panel" data-dept-panel hidden>
        <div class="dg-form-grid">
          <label class="dg-field dg-field--wide">
            <span>Name *</span>
            <input type="text" name="departments[<?= (int) $di ?>][name]" value="<?= View::escape((string) $dept['name']) ?>" placeholder="z. B. Verwaltung">
          </label>
          <label class="dg-field dg-field--wide">
            <span>Beschreibung</span>
            <textarea name="departments[<?= (int) $di ?>][description]" rows="2" placeholder="Optional"><?= View::escape((string) $dept['description']) ?></textarea>
          </label>
        </div>

        <div class="dg-form-grid dg-dept-flags">
          <label class="dg-field dg-field--wide">
            <span>
              <input type="checkbox" name="departments[<?= (int) $di ?>][is_hr]" value="1" data-dept-is-hr<?= $isHr ? ' checked' : '' ?>>
              HR-Rechte (alle Kontakte inkl. Mitarbeiter &amp; Admin)
            </span>
          </label>
          <label class="dg-field dg-field--wide dg-dept-delete-flag" data-dept-delete-flag<?= $isHr ? '' : ' hidden' ?>>
            <span>
              <input type="checkbox" name="departments[<?= (int) $di ?>][allow_contact_delete]" value="1"<?= !empty($dept['allow_contact_delete']) ? ' checked' : '' ?>>
              Kunden/Lieferanten löschen erlauben (optional)
            </span>
            <small class="dg-field-hint">Gilt für Mitglieder dieser Abteilung mit HR-Rechten. Bearbeiten ist immer erlaubt; CRM-Administratoren dürfen immer löschen.</small>
          </label>
          <label class="dg-field dg-field--wide">
            <span>
              <input type="checkbox" name="departments[<?= (int) $di ?>][allow_article_catalog]" value="1" data-dept-allow-catalog<?= $allowCatalog ? ' checked' : '' ?>>
              Artikel- und Leistungspflege erlauben
            </span>
            <small class="dg-field-hint">Zeigt Mitgliedern dieser Abteilung den Menüpunkt „Artikel &amp; Leistungen“ in der Sidebar.</small>
          </label>
        </div>

        <h4 class="dg-subsection-title">Sidebar-Zugriff</h4>
        <p class="dg-field-hint">Maximum über alle Abteilungen eines Mitarbeiters. <strong>Teilweise</strong> bei Kontakte = nur Kunden/Lieferanten (ohne HR-Rechte).</p>
        <div class="dg-table-wrap">
          <table class="dg-table dg-table--compact dg-dept-module-table">
            <thead>
              <tr>
                <th>Modul</th>
                <th>Nicht</th>
                <th>Teilweise</th>
                <th>Vollständig</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($departmentModuleLabels as $moduleKey => $moduleLabel) : ?>
                <?php $level = DepartmentAccess::normalizeLevel((string) ($deptModules[$moduleKey] ?? DepartmentAccess::defaultModules()[$moduleKey] ?? 'partial')); ?>
                <tr>
                  <td><?= View::escape($moduleLabel) ?></td>
                  <?php foreach (['none', 'partial', 'full'] as $accessLevel) : ?>
                    <td>
                      <input
                        type="radio"
                        name="departments[<?= (int) $di ?>][modules][<?= View::escape($moduleKey) ?>]"
                        value="<?= View::escape($accessLevel) ?>"
                        <?= $level === $accessLevel ? 'checked' : '' ?>
                      >
                    </td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php View::render('settings/partials/department-email-notification', [
            'deptId' => (string) $dept['id'],
            'notificationTemplateData' => $notificationTemplateData ?? NotificationTemplateSettings::forForm(),
        ]); ?>

        <h4 class="dg-subsection-title">Mitglieder</h4>
        <div class="dg-dept-members" data-dept-members>
          <?php foreach ($members as $mi => $member) : ?>
            <div class="dg-dept-member-row" data-dept-member>
              <label class="dg-field">
                <span class="dg-sr-only">Mitarbeiter</span>
                <select name="departments[<?= (int) $di ?>][members][<?= (int) $mi ?>][user_id]">
                  <option value="0">— Mitarbeiter wählen —</option>
                  <?php foreach ($departmentEmployees as $employee) : ?>
                    <option value="<?= (int) $employee->id ?>"<?= (int) ($member['user_id'] ?? 0) === $employee->id ? ' selected' : '' ?>>
                      <?= View::escape($employee->displayName) ?><?= RoleResolver::isActiveEmployee($employee) ? '' : ' (inaktiv)' ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="dg-field">
                <span class="dg-sr-only">Rolle</span>
                <select name="departments[<?= (int) $di ?>][members][<?= (int) $mi ?>][role]">
                  <option value="member"<?= ($member['role'] ?? 'member') === 'member' ? ' selected' : '' ?>>Abteilungsmitglied</option>
                  <option value="leader"<?= ($member['role'] ?? '') === 'leader' ? ' selected' : '' ?>>Abteilungsleiter</option>
                </select>
              </label>
              <button type="button" class="dg-button dg-button--small" data-member-remove>Entfernen</button>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="dg-dept-card__actions">
          <button type="button" class="dg-button dg-button--small" data-member-add>+ Mitglied</button>
        </p>
        </div>
      </section>
    <?php endforeach; ?>
  </div>

  <div id="dg-notification-delete-fields" hidden aria-hidden="true"></div>

  <p class="dg-form-actions dg-form-actions--split">
    <button type="button" class="dg-button" id="dg-add-department">+ Abteilung hinzufügen</button>
    <button type="submit" name="departments_save" value="1" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>>Speichern</button>
  </p>
</form>

<template id="dg-dept-member-template">
  <div class="dg-dept-member-row" data-dept-member>
    <label class="dg-field">
      <span class="dg-sr-only">Mitarbeiter</span>
      <select data-member-user>
        <option value="0">— Mitarbeiter wählen —</option>
        <?php foreach ($departmentEmployees as $employee) : ?>
          <option value="<?= (int) $employee->id ?>">
            <?= View::escape($employee->displayName) ?><?= RoleResolver::isActiveEmployee($employee) ? '' : ' (inaktiv)' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="dg-field">
      <span class="dg-sr-only">Rolle</span>
      <select data-member-role>
        <option value="member">Abteilungsmitglied</option>
        <option value="leader">Abteilungsleiter</option>
      </select>
    </label>
    <button type="button" class="dg-button dg-button--small" data-member-remove>Entfernen</button>
  </div>
</template>

<template id="dg-dept-email-tpl-card-template">
  <details class="dg-email-tpl-type" data-email-tpl-card data-tpl-id="" open>
    <summary class="dg-subsection-title dg-collapsible-form__summary dg-email-tpl-type__summary">
      <span data-tpl-summary-name>Neue Vorlage</span>
    </summary>
    <div class="dg-collapsible-form__body dg-email-tpl-type__body">
      <input type="hidden" name="" value="" data-field-id>
      <input type="hidden" name="" value="" data-field-category>
      <input type="hidden" name="" value="" data-field-department>
      <input type="hidden" name="" value="0" data-field-builtin>
      <input type="hidden" name="" value="" data-field-event-slug>
      <div class="dg-form-grid">
        <label class="dg-field dg-field--wide">
          <span>Bezeichnung der Vorlage *</span>
          <input type="text" name="" value="Neue Vorlage" data-email-field="name" data-tpl-name>
        </label>
        <label class="dg-field dg-field--wide">
          <span>Betreff</span>
          <input type="text" name="" value="" data-email-field="subject" data-template-key="">
        </label>
        <label class="dg-field dg-field--wide">
          <span>Überschrift in der E-Mail</span>
          <input type="text" name="" value="" data-email-field="title">
        </label>
        <label class="dg-field dg-field--wide">
          <span>Einleitungstext</span>
          <textarea name="" rows="3" data-email-field="intro"></textarea>
        </label>
      </div>
      <p class="dg-form-actions dg-form-actions--split">
        <button type="button" class="dg-button dg-button--small dg-email-preview-btn" data-template-key="" data-event-slug="">Vorschau aktualisieren</button>
        <button type="button" class="dg-button dg-button--small dg-button--danger dg-email-tpl-delete" data-tpl-delete>Vorlage entfernen</button>
      </p>
      <div class="dg-email-preview" data-email-preview="" hidden>
        <p class="dg-email-preview__subject"><strong>Betreff:</strong> <span data-email-preview-subject></span></p>
        <iframe class="dg-email-preview__frame" title="E-Mail-Vorschau" sandbox=""></iframe>
      </div>
    </div>
  </details>
</template>
