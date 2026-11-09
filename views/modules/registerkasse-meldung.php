<?php
/**
 * Registerkasse — §146a Meldefelder Übersicht (K19a, ohne Senden).
 *
 * @var list<array<string, mixed>> $cashErsRows
 * @var array<string, mixed> $cashErsTaxpayer
 * @var list<array<string, mixed>> $cashLocationOptions
 * @var int $cashErsLocationId
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$rows = $cashErsRows ?? [];
$taxpayer = $cashErsTaxpayer ?? [];
$locations = $cashLocationOptions ?? [];
$locationId = (int) ($cashErsLocationId ?? 0);
$typeLabels = CashErsClientRepository::typeLabels();
$csrf = Csrf::token();
$disabled = !$canEdit || !$dbConnected;
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header">
    <h1 class="dg-page-title">Kassenmeldung</h1>
    <p class="dg-lead">
      Stammdaten für die Mitteilung nach §&nbsp;146a Abs.&nbsp;4 AO speichern.
      Übermittlung an das Finanzamt folgt später — hier nur erfassen.
    </p>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Datenbank nicht verbunden.
      <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Einstellungen → Datenbank</a>
    </div>
  <?php endif; ?>

  <div class="dg-flash dg-flash--info">
    Noch kein Versand. Die Daten bleiben in dieser CRM-Instanz, bis der Meldeweg freigegeben ist.
    Steuernummer bitte im 13-stelligen Bundesschema (Umrechnung über das BMF-Tool, falls nur die Landesnummer vorliegt).
  </div>

  <form class="dg-panel dg-form" method="post" action="/app?page=registerkasse-meldung">
    <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
    <input type="hidden" name="cash_ers_taxpayer_save" value="1">
    <h2 class="dg-subsection-title">Steuerpflichtiger (Firma)</h2>
    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Steuernummer (13 Ziffern)</span>
        <input type="text" name="tax_number" maxlength="13" inputmode="numeric"
               value="<?= View::escape((string) ($taxpayer['tax_number'] ?? '')) ?>"
               <?= $disabled ? ' disabled' : '' ?>
               placeholder="1234012345678">
        <small class="dg-field-hint">Vereinheitlichtes Bundesschema zur elektronischen Übermittlung.</small>
      </label>
      <label class="dg-field">
        <span>Bundesfinanzamt-Nr. (4 Ziffern)</span>
        <input type="text" name="tax_office_number" maxlength="4" inputmode="numeric"
               value="<?= View::escape((string) ($taxpayer['tax_office_number'] ?? '')) ?>"
               <?= $disabled ? ' disabled' : '' ?>
               placeholder="1234">
        <small class="dg-field-hint">Leer = erste 4 Stellen der Steuernummer.</small>
      </label>
      <label class="dg-field">
        <span>Notiz</span>
        <input type="text" name="notes" maxlength="500"
               value="<?= View::escape((string) ($taxpayer['notes'] ?? '')) ?>"
               <?= $disabled ? ' disabled' : '' ?>>
      </label>
      <div class="dg-field dg-field--actions">
        <button type="submit" class="dg-button dg-button--primary"<?= $disabled ? ' disabled' : '' ?>>Steuerdaten speichern</button>
      </div>
    </div>
  </form>

  <form class="dg-panel dg-form" method="get" action="/app">
    <input type="hidden" name="page" value="registerkasse-meldung">
    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Filiale</span>
        <select name="location_id">
          <option value="0"<?= $locationId === 0 ? ' selected' : '' ?>>Alle Filialen</option>
          <?php foreach ($locations as $loc) : ?>
            <?php $lid = (int) ($loc['id'] ?? 0); ?>
            <option value="<?= $lid ?>"<?= $locationId === $lid ? ' selected' : '' ?>>
              <?= View::escape(trim((string) ($loc['code'] ?? '') . ' — ' . (string) ($loc['name'] ?? ''))) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="dg-field dg-field--actions">
        <button type="submit" class="dg-button">Anzeigen</button>
      </div>
    </div>
  </form>

  <div class="dg-panel">
    <h2 class="dg-subsection-title">Kassen / ERS</h2>
    <div class="dg-table-wrap">
      <table class="dg-table">
        <thead>
          <tr>
            <th>Kasse</th>
            <th>Filiale</th>
            <th>Typ</th>
            <th>Hersteller / Modell</th>
            <th>Seriennr.</th>
            <th>Stand</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($rows === []) : ?>
            <tr>
              <td colspan="7" class="dg-table__empty">Keine Kassen. Zuerst unter Kassen anlegen.</td>
            </tr>
          <?php else : ?>
            <?php foreach ($rows as $row) : ?>
              <?php
                $rid = (int) ($row['id'] ?? 0);
                $regLabel = trim((string) ($row['code'] ?? '') . ' — ' . (string) ($row['name'] ?? ''));
                $locLabel = trim((string) ($row['location_code'] ?? '') . ' — ' . (string) ($row['location_name'] ?? ''));
                $ersType = (string) ($row['ers_type'] ?? '');
                $has = !empty($row['ers_has_row']);
                $complete = !empty($row['ers_complete']);
              ?>
              <tr>
                <td><strong><?= View::escape($regLabel) ?></strong></td>
                <td><?= View::escape($locLabel !== ' — ' ? $locLabel : '—') ?></td>
                <td><?= View::escape($typeLabels[$ersType] ?? ($ersType !== '' ? $ersType : '—')) ?></td>
                <td>
                  <?php
                    $hm = trim((string) ($row['ers_manufacturer'] ?? ''));
                    $md = trim((string) ($row['ers_model'] ?? ''));
                    echo View::escape($hm !== '' || $md !== '' ? trim($hm . ' / ' . $md, ' /') : '—');
                  ?>
                </td>
                <td><?= View::escape(trim((string) ($row['ers_serial_number'] ?? '')) !== '' ? (string) $row['ers_serial_number'] : '—') ?></td>
                <td>
                  <?php if (!$has) : ?>
                    <span class="dg-badge dg-badge--muted">Offen</span>
                  <?php elseif ($complete) : ?>
                    <span class="dg-badge dg-badge--ok">Kernfelder da</span>
                  <?php else : ?>
                    <span class="dg-badge dg-badge--warning">Unvollständig</span>
                  <?php endif; ?>
                </td>
                <td class="dg-table__actions">
                  <div class="dg-table__actions-group">
                    <?php if ($rid > 0) : ?>
                      <a class="dg-button dg-button--small" href="/app?page=registerkasse-meldung-form&amp;register_id=<?= $rid ?>">
                        <?= $has ? 'Bearbeiten' : 'Erfassen' ?>
                      </a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
