<?php
/**
 * Lohnarten-Zuordnung (Payroll).
 *
 * @var bool $canEditPayroll
 * @var list<array<string, mixed>> $payrollWageAssignments
 * @var list<array<string, mixed>> $payrollWageTypeOptions
 */
?>
      <?php if ($canEditPayroll) : ?>
        <input type="hidden" name="payroll_wage_assignments_sync" value="1">
      <?php endif; ?>
      <p class="dg-lead">
        Stamm: monatliches Festgehalt, Stundenlohnsatz o. Ä. Priorität steuert die Reihenfolge im späteren Lauf.
        Leere Zeilen und „Entfernen“ löschen die Zuordnung beim Speichern.
      </p>
      <?php
        $assignRows = $payrollWageAssignments !== []
            ? $payrollWageAssignments
            : PayrollEmployeeWageAssignmentRepository::formRows([]);
        $activeWageOptions = [];
        $inactiveWageOptions = [];
        foreach ($payrollWageTypeOptions as $wt) {
            if (!is_array($wt)) {
                continue;
            }
            if ((int) ($wt['is_active'] ?? 0) === 1) {
                $activeWageOptions[] = $wt;
            } else {
                $inactiveWageOptions[] = $wt;
            }
        }
      ?>
      <div class="dg-table-wrap">
        <table class="dg-table dg-table--compact">
          <thead>
            <tr>
              <th>Lohnart</th>
              <th>Betrag / Satz</th>
              <th>Gültig ab</th>
              <th>Gültig bis</th>
              <th>Prio</th>
              <th>Notiz</th>
              <?php if ($canEditPayroll) : ?>
                <th></th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($assignRows as $ai => $arow) : ?>
              <?php
                $aId = (int) ($arow['id'] ?? 0);
                $aWageId = (int) ($arow['wage_type_id'] ?? 0);
                $aAmount = isset($arow['amount_display']) && (string) $arow['amount_display'] !== ''
                    ? (string) $arow['amount_display']
                    : (string) ($arow['amount'] ?? '');
                $aFrom = (string) ($arow['valid_from'] ?? '');
                $aTo = (string) ($arow['valid_to'] ?? '');
                $aPrio = (int) ($arow['priority'] ?? 0);
                $aNotes = (string) ($arow['notes'] ?? '');
                $aDelete = !empty($arow['delete']);
                $roAssign = $canEditPayroll ? '' : ' disabled';
              ?>
              <tr>
                <td>
                  <?php if ($aId > 0) : ?>
                    <input type="hidden" name="payroll_wage_assignments[<?= (int) $ai ?>][id]" value="<?= $aId ?>">
                  <?php endif; ?>
                  <select name="payroll_wage_assignments[<?= (int) $ai ?>][wage_type_id]"<?= $roAssign ?>>
                    <option value="0">— wählen —</option>
                    <?php foreach ($activeWageOptions as $wt) : ?>
                      <option value="<?= (int) ($wt['id'] ?? 0) ?>"<?= $aWageId === (int) ($wt['id'] ?? 0) ? ' selected' : '' ?>>
                        <?= View::escape(trim((string) ($wt['label'] ?? '')) !== '' ? (string) $wt['label'] : 'Lohnart') ?>
                      </option>
                    <?php endforeach; ?>
                    <?php if ($aWageId > 0) : ?>
                      <?php foreach ($inactiveWageOptions as $wt) : ?>
                        <?php if ($aWageId === (int) ($wt['id'] ?? 0)) : ?>
                          <option value="<?= $aWageId ?>" selected>
                            <?= View::escape((trim((string) ($wt['label'] ?? '')) !== '' ? (string) $wt['label'] : 'Lohnart') . ' (inaktiv)') ?>
                          </option>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </select>
                </td>
                <td>
                  <input type="text" name="payroll_wage_assignments[<?= (int) $ai ?>][amount]"
                         value="<?= View::escape($aAmount) ?>" placeholder="z. B. 2.500,00"<?= $roAssign ?>>
                </td>
                <td>
                  <input type="date" name="payroll_wage_assignments[<?= (int) $ai ?>][valid_from]"
                         value="<?= View::escape($aFrom) ?>"<?= $roAssign ?>>
                </td>
                <td>
                  <input type="date" name="payroll_wage_assignments[<?= (int) $ai ?>][valid_to]"
                         value="<?= View::escape($aTo) ?>"<?= $roAssign ?>>
                </td>
                <td>
                  <input type="number" name="payroll_wage_assignments[<?= (int) $ai ?>][priority]"
                         value="<?= $aPrio ?>" step="1" style="width:4.5rem"<?= $roAssign ?>>
                </td>
                <td>
                  <input type="text" name="payroll_wage_assignments[<?= (int) $ai ?>][notes]"
                         value="<?= View::escape($aNotes) ?>" maxlength="500"<?= $roAssign ?>>
                </td>
                <?php if ($canEditPayroll) : ?>
                  <td>
                    <?php if ($aId > 0 || $aWageId > 0) : ?>
                      <label class="dg-field dg-field--checkbox">
                        <input type="checkbox" name="payroll_wage_assignments[<?= (int) $ai ?>][delete]" value="1"<?= $aDelete ? ' checked' : '' ?>>
                        Entfernen
                      </label>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($activeWageOptions === [] && $canEditPayroll) : ?>
        <p class="dg-field-hint">
          Keine aktiven Lohnarten — zuerst unter
          <a href="/app?page=lohn-lohnarten">Lohnarten</a> anlegen oder aktivieren.
        </p>
      <?php endif; ?>
