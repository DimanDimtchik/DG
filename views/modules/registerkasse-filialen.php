<?php
/**
 * Registerkasse — Filialen / Betriebsstätten (K4a Liste, K4d Statusfilter).
 *
 * @var list<array<string, mixed>> $cashLocations
 * @var string $cashLocationStatus all|active|inactive
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$rows = $cashLocations ?? [];
$status = (string) ($cashLocationStatus ?? 'all');
if (!in_array($status, ['all', 'active', 'inactive'], true)) {
    $status = 'all';
}
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Filialen</h1>
      <p class="dg-lead">Betriebsstätten der Registerkasse — Standort für Kassen und TSE.</p>
    </div>
    <div class="dg-page-header__actions">
      <?php if ($canEdit && $dbConnected) : ?>
        <a class="dg-button dg-button--primary" href="/app?page=registerkasse-filiale-form">Filiale anlegen</a>
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
    <input type="hidden" name="page" value="registerkasse-filialen">
    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Status</span>
        <select name="status">
          <option value="all"<?= $status === 'all' ? ' selected' : '' ?>>Alle</option>
          <option value="active"<?= $status === 'active' ? ' selected' : '' ?>>Nur aktiv</option>
          <option value="inactive"<?= $status === 'inactive' ? ' selected' : '' ?>>Nur inaktiv</option>
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
            <th>Kürzel</th>
            <th>Name</th>
            <th>Ort</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($rows === []) : ?>
            <tr>
              <td colspan="5" class="dg-table__empty">
                <?= $status === 'all' ? 'Noch keine Filialen.' : 'Keine Filialen für diesen Filter.' ?>
              </td>
            </tr>
          <?php else : ?>
            <?php foreach ($rows as $row) : ?>
              <?php
                $lid = (int) ($row['id'] ?? 0);
                $active = !empty($row['is_active']);
                $city = trim((string) ($row['zip'] ?? '') . ' ' . (string) ($row['city'] ?? ''));
              ?>
              <tr>
                <td><strong><?= View::escape((string) ($row['code'] ?? '')) ?></strong></td>
                <td><?= View::escape((string) ($row['name'] ?? '')) ?></td>
                <td><?= View::escape($city !== '' ? $city : '—') ?></td>
                <td>
                  <span class="dg-badge <?= $active ? 'dg-badge--ok' : 'dg-badge--muted' ?>">
                    <?= $active ? 'Aktiv' : 'Inaktiv' ?>
                  </span>
                </td>
                <td class="dg-table__actions">
                  <div class="dg-table__actions-group">
                    <?php if ($canEdit && $lid > 0) : ?>
                      <a class="dg-button dg-button--small" href="/app?page=registerkasse-filiale-form&amp;id=<?= $lid ?>">Bearbeiten</a>
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
