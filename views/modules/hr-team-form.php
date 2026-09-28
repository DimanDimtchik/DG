<?php
/**
 * @var array<string, mixed> $form
 * @var int|null $teamId
 * @var string|null $formError
 * @var bool $canEditTeam
 * @var bool $readOnly
 * @var list<array{id: string, name: string}> $departmentOptions
 * @var array<int, string> $employeeContactOptions
 * @var array{type: string, message: string}|null $flash
 */
$isEdit = ($teamId ?? 0) > 0;
$readOnly = !empty($readOnly);
$canEditTeam = !empty($canEditTeam) && !$readOnly;
$form = is_array($form ?? null) ? $form : TeamRepository::emptyForm();
$departmentOptions = is_array($departmentOptions ?? null) ? $departmentOptions : [];
$employeeContactOptions = is_array($employeeContactOptions ?? null) ? $employeeContactOptions : [];
$members = is_array($form['members'] ?? null) ? $form['members'] : [];
if ($members === []) {
    $members = [['contact_id' => '', 'member_role' => 'member']];
}
$assets = is_array($form['assets'] ?? null) ? $form['assets'] : [];
if ($assets === []) {
    $assets = [['temp_key' => 'a0', 'kind' => 'vehicle', 'name' => '', 'inventory_no' => '', 'notes' => '', 'parent_temp' => '', 'sort_order' => '0']];
}
$vehicleTemps = [];
foreach ($assets as $i => $asset) {
    $kind = (string) ($asset['kind'] ?? '');
    $temp = (string) ($asset['temp_key'] ?? ('a' . $i));
    if ($kind === 'vehicle' || ($kind !== 'device' && trim((string) ($asset['parent_temp'] ?? '')) === '' && (int) ($asset['parent_asset_id'] ?? 0) < 1)) {
        $vehicleTemps[$temp] = trim((string) ($asset['name'] ?? '')) !== '' ? (string) $asset['name'] : ('Fahrzeug ' . ($i + 1));
    }
}
?>
<div class="dg-wrap dg-hr-team-form">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <?php
        View::partial('partials/back-nav', [
            'href' => '/app?page=hr-teams',
            'label' => 'Zurück zu Teams',
        ]);
      ?>
      <h1 class="dg-page-title"><?= $isEdit ? ($readOnly ? 'Team anzeigen' : 'Team bearbeiten') : 'Neues Team' ?></h1>
      <p class="dg-lead">Mitglieder werden am Kontakt automatisch mit diesem Team verknüpft. Inventar: Fahrzeuge und Geräte (z. B. Gerät im Fahrzeug).</p>
    </div>
  </header>

  <?php if (!empty($formError)) : ?>
    <div class="dg-flash dg-flash--error"><?= View::escape((string) $formError) ?></div>
  <?php endif; ?>

  <form method="post" action="/app?page=hr-team-form" class="dg-form" id="dg-hr-team-form">
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
    <input type="hidden" name="team_save" value="1">
    <?php if ($isEdit) : ?>
      <input type="hidden" name="id" value="<?= (int) $teamId ?>">
    <?php endif; ?>

    <section class="dg-form-section">
      <h2 class="dg-subsection-title">Stammdaten</h2>
      <div class="dg-form-grid">
        <label class="dg-field">
          <span>Abteilung *</span>
          <select name="department_id" required<?= $readOnly ? ' disabled' : '' ?>>
            <option value="">— bitte wählen —</option>
            <?php foreach ($departmentOptions as $dept) : ?>
              <option value="<?= View::escape((string) ($dept['id'] ?? '')) ?>"<?= (string) ($form['department_id'] ?? '') === (string) ($dept['id'] ?? '') ? ' selected' : '' ?>>
                <?= View::escape((string) ($dept['name'] ?? '')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dg-field">
          <span>Name *</span>
          <input type="text" name="name" value="<?= View::escape((string) ($form['name'] ?? '')) ?>" required<?= $readOnly ? ' readonly' : '' ?>>
        </label>
        <label class="dg-field">
          <span>Reihenfolge</span>
          <input type="number" name="sort_order" value="<?= View::escape((string) ($form['sort_order'] ?? '0')) ?>"<?= $readOnly ? ' readonly' : '' ?>>
        </label>
        <label class="dg-field">
          <span>Status</span>
          <select name="is_active"<?= $readOnly ? ' disabled' : '' ?>>
            <option value="1"<?= (string) ($form['is_active'] ?? '1') === '1' ? ' selected' : '' ?>>Aktiv</option>
            <option value="0"<?= (string) ($form['is_active'] ?? '1') === '0' ? ' selected' : '' ?>>Inaktiv</option>
          </select>
          <?php if ($readOnly) : ?>
            <input type="hidden" name="is_active" value="<?= View::escape((string) ($form['is_active'] ?? '1')) ?>">
          <?php endif; ?>
        </label>
        <label class="dg-field dg-field--wide">
          <span>Beschreibung</span>
          <textarea name="description" rows="2"<?= $readOnly ? ' readonly' : '' ?>><?= View::escape((string) ($form['description'] ?? '')) ?></textarea>
        </label>
      </div>
    </section>

    <section class="dg-form-section">
      <h2 class="dg-subsection-title">Mitglieder</h2>
      <p class="dg-field-hint">Kontakte (Mitarbeiter/Administrator). Die Verknüpfung erscheint am Kontakt unter „Teams“.</p>
      <div class="dg-table-wrap">
        <table class="dg-table dg-table--compact" id="dg-hr-team-members">
          <thead>
            <tr>
              <th>Kontakt</th>
              <th>Rolle</th>
              <?php if (!$readOnly) : ?><th></th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($members as $mi => $member) : ?>
              <tr>
                <td>
                  <select name="members[<?= (int) $mi ?>][contact_id]"<?= $readOnly ? ' disabled' : '' ?>>
                    <option value="">—</option>
                    <?php foreach ($employeeContactOptions as $cid => $label) : ?>
                      <option value="<?= (int) $cid ?>"<?= (int) ($member['contact_id'] ?? 0) === (int) $cid ? ' selected' : '' ?>><?= View::escape($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td>
                  <select name="members[<?= (int) $mi ?>][member_role]"<?= $readOnly ? ' disabled' : '' ?>>
                    <option value="member"<?= (string) ($member['member_role'] ?? '') !== 'lead' ? ' selected' : '' ?>>Mitglied</option>
                    <option value="lead"<?= (string) ($member['member_role'] ?? '') === 'lead' ? ' selected' : '' ?>>Teamleitung</option>
                  </select>
                </td>
                <?php if (!$readOnly) : ?>
                  <td><button type="button" class="dg-button dg-button--small dg-button--danger" data-remove-row>Entfernen</button></td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if (!$readOnly) : ?>
        <button type="button" class="dg-button dg-button--small" id="dg-hr-team-member-add">+ Mitglied</button>
      <?php endif; ?>
    </section>

    <section class="dg-form-section">
      <h2 class="dg-subsection-title">Inventar</h2>
      <p class="dg-field-hint">Fahrzeug als eigener Eintrag; Geräte optional einem Fahrzeug zuordnen.</p>
      <div class="dg-table-wrap">
        <table class="dg-table dg-table--compact" id="dg-hr-team-assets">
          <thead>
            <tr>
              <th>Art</th>
              <th>Name</th>
              <th>Inv.-Nr.</th>
              <th>Im Fahrzeug</th>
              <th>Notiz</th>
              <?php if (!$readOnly) : ?><th></th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($assets as $ai => $asset) : ?>
              <?php $tempKey = (string) ($asset['temp_key'] ?? ('a' . $ai)); ?>
              <tr>
                <td>
                  <input type="hidden" name="assets[<?= (int) $ai ?>][temp_key]" value="<?= View::escape($tempKey) ?>">
                  <select name="assets[<?= (int) $ai ?>][kind]"<?= $readOnly ? ' disabled' : '' ?>>
                    <?php foreach (TeamRepository::ASSET_KINDS as $kind => $kindLabel) : ?>
                      <option value="<?= View::escape($kind) ?>"<?= (string) ($asset['kind'] ?? '') === $kind ? ' selected' : '' ?>><?= View::escape($kindLabel) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td><input type="text" name="assets[<?= (int) $ai ?>][name]" value="<?= View::escape((string) ($asset['name'] ?? '')) ?>"<?= $readOnly ? ' readonly' : '' ?>></td>
                <td><input type="text" name="assets[<?= (int) $ai ?>][inventory_no]" value="<?= View::escape((string) ($asset['inventory_no'] ?? '')) ?>"<?= $readOnly ? ' readonly' : '' ?>></td>
                <td>
                  <select name="assets[<?= (int) $ai ?>][parent_temp]"<?= $readOnly ? ' disabled' : '' ?>>
                    <option value="">—</option>
                    <?php foreach ($vehicleTemps as $vTemp => $vName) : ?>
                      <?php if ($vTemp === $tempKey) {
                          continue;
                      } ?>
                      <option value="<?= View::escape($vTemp) ?>"<?= (string) ($asset['parent_temp'] ?? '') === $vTemp ? ' selected' : '' ?>><?= View::escape($vName) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td><input type="text" name="assets[<?= (int) $ai ?>][notes]" value="<?= View::escape((string) ($asset['notes'] ?? '')) ?>"<?= $readOnly ? ' readonly' : '' ?>></td>
                <?php if (!$readOnly) : ?>
                  <td><button type="button" class="dg-button dg-button--small dg-button--danger" data-remove-row>Entfernen</button></td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if (!$readOnly) : ?>
        <button type="button" class="dg-button dg-button--small" id="dg-hr-team-asset-add">+ Inventar</button>
      <?php endif; ?>
    </section>

    <?php if ($canEditTeam) : ?>
      <div class="dg-form-actions">
        <button type="submit" class="dg-button dg-button--primary">Speichern</button>
        <a class="dg-button" href="/app?page=hr-teams">Abbrechen</a>
        <?php if ($isEdit) : ?>
          <button type="submit" name="team_delete" value="1" class="dg-button dg-button--danger" onclick="return confirm('Team wirklich löschen?');">Löschen</button>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </form>
</div>

<?php if (!$readOnly) : ?>
<script>
(function () {
  function reindex(table, prefix) {
    var rows = table.querySelectorAll('tbody tr');
    rows.forEach(function (row, index) {
      row.querySelectorAll('select, input').forEach(function (el) {
        var name = el.getAttribute('name') || '';
        el.setAttribute('name', name.replace(new RegExp('^' + prefix + '\\[\\d+\\]'), prefix + '[' + index + ']'));
      });
    });
  }
  function bindRemove(table, prefix) {
    table.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-remove-row]');
      if (!btn) return;
      var tbody = table.querySelector('tbody');
      if (tbody.querySelectorAll('tr').length <= 1) {
        tbody.querySelectorAll('select, input[type="text"]').forEach(function (el) {
          if (el.tagName === 'SELECT') el.selectedIndex = 0;
          else el.value = '';
        });
        return;
      }
      btn.closest('tr').remove();
      reindex(table, prefix);
    });
  }
  var memberTable = document.getElementById('dg-hr-team-members');
  var assetTable = document.getElementById('dg-hr-team-assets');
  if (memberTable) {
    bindRemove(memberTable, 'members');
    var addMember = document.getElementById('dg-hr-team-member-add');
    if (addMember) {
      addMember.addEventListener('click', function () {
        var tbody = memberTable.querySelector('tbody');
        var row = tbody.querySelector('tr').cloneNode(true);
        row.querySelectorAll('select').forEach(function (el) { el.selectedIndex = 0; });
        tbody.appendChild(row);
        reindex(memberTable, 'members');
      });
    }
  }
  if (assetTable) {
    bindRemove(assetTable, 'assets');
    var addAsset = document.getElementById('dg-hr-team-asset-add');
    if (addAsset) {
      addAsset.addEventListener('click', function () {
        var tbody = assetTable.querySelector('tbody');
        var row = tbody.querySelector('tr').cloneNode(true);
        var key = 'a' + Date.now();
        row.querySelectorAll('input, select').forEach(function (el) {
          if (el.name && el.name.indexOf('[temp_key]') !== -1) el.value = key;
          else if (el.tagName === 'SELECT') el.selectedIndex = 0;
          else el.value = '';
        });
        tbody.appendChild(row);
        reindex(assetTable, 'assets');
      });
    }
  }
})();
</script>
<?php endif; ?>
