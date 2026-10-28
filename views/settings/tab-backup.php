<?php
/**
 * @var array{last_backup: string|null, last_status: string|null, last_error: string|null, backups: list<array{date: string, size: int}>} $backupInfo
 * @var bool $dbConnected
 */
$backupInfo = is_array($backupInfo ?? null) ? $backupInfo : BackupService::info();
$backups = is_array($backupInfo['backups'] ?? null) ? $backupInfo['backups'] : [];
$lastStatus = (string) ($backupInfo['last_status'] ?? '');
$lastBackup = (string) ($backupInfo['last_backup'] ?? '');
$lastError = (string) ($backupInfo['last_error'] ?? '');
$formatBytes = static function (int $bytes): string {
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    $units = ['KB', 'MB', 'GB', 'TB'];
    $value = $bytes / 1024;
    foreach ($units as $unit) {
        if ($value < 1024 || $unit === 'TB') {
            return number_format($value, 1, ',', '.') . ' ' . $unit;
        }
        $value /= 1024;
    }
    return $bytes . ' B';
};
?>
<section class="dg-panel dg-panel--notice">
  <p>
    <strong>Instanzdaten-Backup:</strong>
    gesichert werden Datenbank-Dump, <code>storage/</code> ohne <code>storage/backups/</code> und
    <code>storage/cache/</code>, sowie alle <code>config/*.local.php</code>.
    Der zentrale CRM-Code wird bewusst nicht pro Kundeninstanz gesichert.
  </p>
  <p class="dg-muted">
    Restore bleibt eine Server-Aufgabe mit Protokollierung. Diese Maske zeigt Status und erlaubt ein manuelles Backup.
  </p>
</section>

<?php if (!$dbConnected) : ?>
  <div class="dg-flash dg-flash--warning">Datenbank nicht verbunden. Backup ist erst nach eingerichteter Instanz möglich.</div>
<?php endif; ?>

<section class="dg-panel">
  <h3 class="dg-subsection-title">Status</h3>
  <dl class="dg-dl">
    <div>
      <dt>Letztes Tages-Backup</dt>
      <dd><?= $lastBackup !== '' ? View::escape($lastBackup) : 'noch nicht gelaufen' ?></dd>
    </div>
    <div>
      <dt>Status</dt>
      <dd>
        <?php if ($lastStatus === 'ok') : ?>
          <span class="dg-badge dg-badge--ok">OK</span>
        <?php elseif ($lastStatus === 'error') : ?>
          <span class="dg-badge dg-badge--danger">Fehler</span>
        <?php elseif ($lastStatus === 'paused_hotfix') : ?>
          <span class="dg-badge dg-badge--pending">Pausiert</span>
        <?php else : ?>
          <span class="dg-badge dg-badge--pending">Unbekannt</span>
        <?php endif; ?>
      </dd>
    </div>
    <?php if ($lastError !== '') : ?>
      <div>
        <dt>Letzter Fehler</dt>
        <dd><?= View::escape($lastError) ?></dd>
      </div>
    <?php endif; ?>
    <div>
      <dt>Aufbewahrung lokal</dt>
      <dd>max. 14 Backup-Ordner unter <code>storage/backups/</code></dd>
    </div>
  </dl>
</section>

<form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('backup')) ?>">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
  <div class="dg-form-actions">
    <button type="submit" name="backup_action" value="create" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>>
      Backup jetzt erstellen
    </button>
  </div>
</form>

<section class="dg-panel">
  <h3 class="dg-subsection-title">Vorhandene Backups</h3>
  <?php if ($backups === []) : ?>
    <p class="dg-muted">Noch keine lokalen Backup-Ordner gefunden.</p>
  <?php else : ?>
    <div class="dg-table-wrap">
      <table class="dg-table">
        <thead>
          <tr>
            <th>Ordner</th>
            <th>Größe</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($backups as $backup) : ?>
            <tr>
              <td><code><?= View::escape((string) ($backup['date'] ?? '')) ?></code></td>
              <td><?= View::escape($formatBytes((int) ($backup['size'] ?? 0))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
