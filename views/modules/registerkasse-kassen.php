<?php
/**
 * Registerkasse — Kassen-Liste (K5a), gefiltert nach Filiale.
 *
 * @var list<array<string, mixed>> $cashRegisters
 * @var list<array<string, mixed>> $cashLocationOptions
 * @var int $cashRegisterLocationId 0 = alle
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$rows = $cashRegisters ?? [];
$locations = $cashLocationOptions ?? [];
$locationId = (int) ($cashRegisterLocationId ?? 0);
$typeLabels = CashRegisterRepository::typeLabels();
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Kassen</h1>
      <p class="dg-lead">Kassenterminals je Filiale — eigene Kassen-ID und Bon-Nummernkreis.</p>
    </div>
    <div class="dg-page-header__actions">
      <?php if ($canEdit && $dbConnected) : ?>
        <a class="dg-button dg-button--primary" href="/app?page=registerkasse-kasse-form<?= $locationId > 0 ? '&amp;location_id=' . $locationId : '' ?>">Kasse anlegen</a>
      <?php endif; ?>
    </div>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Datenbank nicht verbunden.
      <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Einstellungen → Datenbank</a>
    </div>
  <?php endif; ?>

  <form class="dg-panel dg-form" method="get" action="/app">
    <input type="hidden" name="page" value="registerkasse-kassen">
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
        <button type="submit" class="dg-button dg-button--primary">Anzeigen</button>
      </div>
    </div>
  </form>

  <div class="dg-panel">
    <div class="dg-table-wrap">
      <table class="dg-table">
        <thead>
          <tr>
            <th>Kassen-ID</th>
            <th>Name</th>
            <th>Filiale</th>
            <th>Typ</th>
            <th>Nächste Bon-Nr.</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($rows === []) : ?>
            <tr>
              <td colspan="7" class="dg-table__empty">
                <?php if ($locations === []) : ?>
                  Zuerst eine Filiale anlegen.
                <?php elseif ($locationId > 0) : ?>
                  Keine Kassen für diese Filiale.
                <?php else : ?>
                  Noch keine Kassen.
                <?php endif; ?>
              </td>
            </tr>
          <?php else : ?>
            <?php foreach ($rows as $row) : ?>
              <?php
                $rid = (int) ($row['id'] ?? 0);
                $active = !empty($row['is_active']);
                $type = (string) ($row['register_type'] ?? 'store');
                $locLabel = trim((string) ($row['location_code'] ?? ''));
                if ($locLabel !== '' && trim((string) ($row['location_name'] ?? '')) !== '') {
                    $locLabel .= ' — ' . trim((string) $row['location_name']);
                } elseif (trim((string) ($row['location_name'] ?? '')) !== '') {
                    $locLabel = trim((string) $row['location_name']);
                }
                $prefix = trim((string) ($row['receipt_number_prefix'] ?? ''));
                $nextNo = (int) ($row['next_receipt_number'] ?? 1);
                $nextDisplay = $prefix !== '' ? $prefix . $nextNo : (string) $nextNo;
              ?>
              <tr>
                <td><strong><?= View::escape((string) ($row['code'] ?? '')) ?></strong></td>
                <td><?= View::escape((string) ($row['name'] ?? '')) ?></td>
                <td><?= View::escape($locLabel !== '' ? $locLabel : '—') ?></td>
                <td><?= View::escape($typeLabels[$type] ?? $type) ?></td>
                <td><?= View::escape($nextDisplay) ?></td>
                <td>
                  <span class="dg-badge <?= $active ? 'dg-badge--ok' : 'dg-badge--muted' ?>">
                    <?= $active ? 'Aktiv' : 'Inaktiv' ?>
                  </span>
                </td>
                <td class="dg-table__actions">
                  <div class="dg-table__actions-group">
                    <?php if ($canEdit && $rid > 0) : ?>
                      <a class="dg-button dg-button--small" href="/app?page=registerkasse-kasse-form&amp;id=<?= $rid ?>">Bearbeiten</a>
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
