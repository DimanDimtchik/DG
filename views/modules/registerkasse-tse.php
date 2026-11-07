<?php
/**
 * Registerkasse — TSE-Zuordnungen Übersicht (K6a/K6b).
 *
 * @var list<array<string, mixed>> $cashTseRows
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$rows = $cashTseRows ?? [];
$tseSettingsUrl = SettingsRegistry::tabUrl('tse');
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">TSE-Zuordnung</h1>
      <p class="dg-lead">Jede Kasse mit Provider, TSE-ID und Kassen-Client. API-Schlüssel bleiben in den Einstellungen.</p>
    </div>
    <div class="dg-page-header__actions">
      <a class="dg-button" href="<?= View::escape($tseSettingsUrl) ?>">Einstellungen → TSE (Zugang)</a>
    </div>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Datenbank nicht verbunden.
      <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Einstellungen → Datenbank</a>
    </div>
  <?php endif; ?>

  <div class="dg-panel">
    <p class="dg-field-hint">
      API-Schlüssel und Beleg-Barzahlung-TSE: <a href="<?= View::escape($tseSettingsUrl) ?>">Einstellungen → TSE</a>
      (Tab bleibt als Übergang). Pro Filiale eine TSE (TSS), pro Kasse ein Client.
    </p>
    <div class="dg-table-wrap">
      <table class="dg-table">
        <thead>
          <tr>
            <th>Kasse</th>
            <th>Filiale</th>
            <th>Provider</th>
            <th>Status</th>
            <th>Geprüft</th>
            <th>Zertifikat bis</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($rows === []) : ?>
            <tr>
              <td colspan="7" class="dg-table__empty">
                Noch keine Kassen. Bitte zuerst unter Kassen anlegen.
              </td>
            </tr>
          <?php else : ?>
            <?php foreach ($rows as $row) : ?>
              <?php
                $rid = (int) ($row['register_id'] ?? 0);
                $has = (int) ($row['assignment_id'] ?? 0) > 0;
                $provider = (string) ($row['provider'] ?? '');
                $env = (string) ($row['environment'] ?? '');
                $lastState = trim((string) ($row['last_state'] ?? ''));
                $lastChecked = trim((string) ($row['last_checked_at'] ?? ''));
                $certUntil = trim((string) ($row['certificate_valid_until'] ?? ''));
                $lastError = trim((string) ($row['last_error'] ?? ''));
                $statusOk = $has && $lastState !== '' && $lastError === '';
                $statusLabel = !$has ? 'offen' : ($lastState !== '' ? $lastState : 'ungeprüft');
                $regLabel = trim((string) ($row['register_code'] ?? ''));
                if ($regLabel !== '' && trim((string) ($row['register_name'] ?? '')) !== '') {
                    $regLabel .= ' — ' . trim((string) $row['register_name']);
                }
                $locLabel = trim((string) ($row['location_code'] ?? ''));
                if ($locLabel !== '' && trim((string) ($row['location_name'] ?? '')) !== '') {
                    $locLabel .= ' — ' . trim((string) $row['location_name']);
                }
                $providerCell = $has ? ($provider . ($env !== '' ? ' / ' . $env : '')) : '—';
              ?>
              <tr>
                <td><strong><?= View::escape($regLabel !== '' ? $regLabel : '—') ?></strong></td>
                <td><?= View::escape($locLabel !== '' ? $locLabel : '—') ?></td>
                <td><?= View::escape($providerCell) ?></td>
                <td>
                  <span class="dg-badge <?= $statusOk ? 'dg-badge--ok' : ($lastError !== '' ? 'dg-badge--error' : 'dg-badge--muted') ?>"
                        <?= $lastError !== '' ? ' title="' . View::escape($lastError) . '"' : '' ?>>
                    <?= View::escape($statusLabel) ?>
                  </span>
                </td>
                <td><?= View::escape($lastChecked !== '' ? $lastChecked : '—') ?></td>
                <td><?= View::escape($certUntil !== '' ? $certUntil : '—') ?></td>
                <td class="dg-table__actions">
                  <div class="dg-table__actions-group">
                    <?php if ($canEdit && $rid > 0) : ?>
                      <a class="dg-button dg-button--small" href="/app?page=registerkasse-tse-form&amp;register_id=<?= $rid ?>">
                        <?= $has ? 'Bearbeiten' : 'Zuordnen' ?>
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
