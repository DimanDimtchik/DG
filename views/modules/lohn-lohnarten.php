<?php
/**
 * Lohn L3b — Lohnarten-Stamm.
 *
 * @var list<array<string, mixed>> $payrollWageTypes
 * @var array<string, mixed>|null $payrollWageTypeEdit
 * @var bool $payrollWageCanEdit
 * @var array{type: string, message: string}|null $flash
 */
$rows = is_array($payrollWageTypes ?? null) ? $payrollWageTypes : [];
$edit = is_array($payrollWageTypeEdit ?? null) ? $payrollWageTypeEdit : null;
$editId = (int) ($edit['id'] ?? 0);
$canEditWage = !empty($payrollWageCanEdit);
$categories = PayrollWageTypeRepository::categoryLabels();
$units = PayrollWageTypeRepository::unitLabels();
$isSystem = $edit !== null && (int) ($edit['is_system'] ?? 0) === 1;
?>
<div class="dg-wrap dg-lohn-lohnarten">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Lohnarten</h1>
      <p class="dg-lead">Stufe A — Standardkatalog (ohne Bau/Gastro). SKR-Hints sind Vorschläge für L7, keine automatische Buchung.</p>
    </div>
    <div class="dg-toolbar">
      <?php if (RoleResolver::isAdmin($user ?? AuthService::user())) : ?>
        <a class="dg-button" href="<?= View::escape(SettingsRegistry::tabUrl('lohn')) ?>">AG-Stammdaten</a>
      <?php endif; ?>
    </div>
  </header>

  <?php if ($canEditWage) : ?>
    <section class="dg-panel">
      <h2 class="dg-subsection-title"><?= $editId > 0 ? 'Lohnart bearbeiten' : 'Neue Lohnart' ?></h2>
      <form method="post" action="/app?page=lohn-lohnarten" class="dg-form">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <input type="hidden" name="wage_type_action" value="save">
        <?php if ($editId > 0) : ?>
          <input type="hidden" name="id" value="<?= $editId ?>">
        <?php endif; ?>
        <div class="dg-form-grid">
          <label class="dg-field">
            <span>Code</span>
            <input type="text" name="code" maxlength="32" required
                   value="<?= View::escape((string) ($edit['code'] ?? '')) ?>"
                   placeholder="z. B. CUSTOM_ALLOWANCE"
                   <?= $isSystem ? ' readonly' : '' ?>>
            <?php if ($isSystem) : ?>
              <small class="dg-field-hint">System-Code nicht änderbar.</small>
            <?php endif; ?>
          </label>
          <label class="dg-field">
            <span>Bezeichnung</span>
            <input type="text" name="label" maxlength="120" required
                   value="<?= View::escape((string) ($edit['label'] ?? '')) ?>">
          </label>
          <label class="dg-field">
            <span>Kategorie</span>
            <select name="category">
              <?php foreach ($categories as $ck => $cl) : ?>
                <option value="<?= View::escape($ck) ?>"<?= (($edit['category'] ?? '') === $ck) ? ' selected' : '' ?>><?= View::escape($cl) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="dg-field">
            <span>Einheit</span>
            <select name="unit">
              <?php foreach ($units as $uk => $ul) : ?>
                <option value="<?= View::escape($uk) ?>"<?= (($edit['unit'] ?? 'amount') === $uk) ? ' selected' : '' ?>><?= View::escape($ul) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="dg-field">
            <span>Richtung</span>
            <select name="direction">
              <option value="earning"<?= (($edit['direction'] ?? 'earning') === 'earning') ? ' selected' : '' ?>>Bezug (earning)</option>
              <option value="deduction"<?= (($edit['direction'] ?? '') === 'deduction') ? ' selected' : '' ?>>Abzug</option>
            </select>
          </label>
          <label class="dg-field">
            <span>Sortierung</span>
            <input type="number" name="sort_order" value="<?= (int) ($edit['sort_order'] ?? 100) ?>">
          </label>
        </div>
        <label class="dg-field dg-field--checkbox"><span><input type="checkbox" name="taxable" value="1"<?= ($edit === null || !empty($edit['taxable'])) ? ' checked' : '' ?>> Steuerpflichtig (LSt)</span></label>
        <label class="dg-field dg-field--checkbox"><span><input type="checkbox" name="subject_to_sv" value="1"<?= ($edit === null || !empty($edit['subject_to_sv'])) ? ' checked' : '' ?>> SV-pflichtig</span></label>
        <label class="dg-field dg-field--checkbox"><span><input type="checkbox" name="flat_tax_possible" value="1"<?= !empty($edit['flat_tax_possible']) ? ' checked' : '' ?>> Pauschalversteuerung möglich</span></label>
        <label class="dg-field dg-field--checkbox"><span><input type="checkbox" name="increases_net" value="1"<?= ($edit === null || !isset($edit['increases_net']) || !empty($edit['increases_net'])) ? ' checked' : '' ?>> Erhöht Netto</span></label>
        <label class="dg-field dg-field--checkbox"><span><input type="checkbox" name="is_active" value="1"<?= ($edit === null || !empty($edit['is_active'])) ? ' checked' : '' ?>> Aktiv</span></label>
        <div class="dg-form-grid">
          <label class="dg-field"><span>SKR03 Aufwand (Hint)</span><input type="text" name="skr03_expense_hint" maxlength="16" value="<?= View::escape((string) ($edit['skr03_expense_hint'] ?? '')) ?>"></label>
          <label class="dg-field"><span>SKR04 Aufwand (Hint)</span><input type="text" name="skr04_expense_hint" maxlength="16" value="<?= View::escape((string) ($edit['skr04_expense_hint'] ?? '')) ?>"></label>
          <label class="dg-field"><span>SKR03 Verbindlichkeit</span><input type="text" name="skr03_liability_hint" maxlength="16" value="<?= View::escape((string) ($edit['skr03_liability_hint'] ?? '')) ?>"></label>
          <label class="dg-field"><span>SKR04 Verbindlichkeit</span><input type="text" name="skr04_liability_hint" maxlength="16" value="<?= View::escape((string) ($edit['skr04_liability_hint'] ?? '')) ?>"></label>
        </div>
        <label class="dg-field dg-field--wide">
          <span>Notiz</span>
          <textarea name="notes" rows="2"><?= View::escape((string) ($edit['notes'] ?? '')) ?></textarea>
        </label>
        <div class="dg-form-actions">
          <button type="submit" class="dg-button dg-button--primary"><?= $editId > 0 ? 'Speichern' : 'Anlegen' ?></button>
          <?php if ($editId > 0) : ?>
            <a class="dg-button" href="/app?page=lohn-lohnarten">Abbrechen</a>
          <?php endif; ?>
        </div>
      </form>
    </section>
  <?php else : ?>
    <p class="dg-field-hint">Nur Lesen — zum Bearbeiten Modul „Lohn &amp; Gehalt“ (partial/full) oder Admin.</p>
  <?php endif; ?>

  <section class="dg-panel">
    <h2 class="dg-subsection-title">Katalog</h2>
    <?php if ($rows === []) : ?>
      <p class="dg-muted">Keine Lohnarten — Migration 125 / Seed prüfen.</p>
    <?php else : ?>
      <div class="dg-table-wrap">
        <table class="dg-table">
          <thead>
            <tr>
              <th>Code</th>
              <th>Bezeichnung</th>
              <th>Kategorie</th>
              <th>LSt</th>
              <th>SV</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row) : ?>
              <tr>
                <td><code><?= View::escape((string) ($row['code'] ?? '')) ?></code><?= !empty($row['is_system']) ? ' · System' : '' ?></td>
                <td><?= View::escape((string) ($row['label'] ?? '')) ?></td>
                <td><?= View::escape($categories[(string) ($row['category'] ?? '')] ?? (string) ($row['category'] ?? '')) ?></td>
                <td><?= !empty($row['taxable']) ? 'ja' : 'nein' ?></td>
                <td><?= !empty($row['subject_to_sv']) ? 'ja' : 'nein' ?></td>
                <td><?= !empty($row['is_active']) ? 'aktiv' : 'inaktiv' ?></td>
                <td>
                  <?php if ($canEditWage) : ?>
                    <a href="/app?page=lohn-lohnarten&amp;edit=<?= (int) ($row['id'] ?? 0) ?>">Bearbeiten</a>
                    <?php if (!empty($row['is_active'])) : ?>
                      <form method="post" action="/app?page=lohn-lohnarten" style="display:inline" onsubmit="return confirm('Deaktivieren bzw. löschen?');">
                        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                        <input type="hidden" name="wage_type_action" value="deactivate">
                        <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">
                        <button type="submit" class="dg-button dg-button--small">Deaktivieren</button>
                      </form>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>
