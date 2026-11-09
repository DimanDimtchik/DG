<?php
/**
 * Registerkasse — TSE einer Kasse zuordnen (K6a–K6c).
 *
 * @var array<string, mixed> $cashTseForm
 * @var array<string, mixed> $cashTseRegister
 * @var int $cashTseRegisterId
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$form = $cashTseForm ?? [];
$register = $cashTseRegister ?? [];
$registerId = (int) ($cashTseRegisterId ?? 0);
$disabled = !$canEdit || !$dbConnected;
$csrf = Csrf::token();
$regLabel = trim((string) ($register['code'] ?? '') . ' — ' . (string) ($register['name'] ?? ''));
$tseSettingsUrl = SettingsRegistry::tabUrl('tse');
$hasAssignment = (int) ($form['id'] ?? 0) > 0;
$envIsTest = strtolower(trim((string) ($form['environment'] ?? 'test'))) === 'test';
$canPing = $canEdit && $dbConnected && $hasAssignment && $envIsTest;
$lastState = trim((string) ($form['last_state'] ?? ''));
$lastChecked = trim((string) ($form['last_checked_at'] ?? ''));
$certUntil = trim((string) ($form['certificate_valid_until'] ?? ''));
$lastError = trim((string) ($form['last_error'] ?? ''));
$tssSerial = trim((string) ($form['tss_serial_number'] ?? ''));
$clientSerial = trim((string) ($form['client_serial_number'] ?? ''));
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <a class="dg-button dg-button--back" href="/app?page=registerkasse-tse">← Zurück zur Übersicht</a>

  <header class="dg-page-header">
    <h1 class="dg-page-title">TSE zuordnen</h1>
    <p class="dg-lead">Kasse <?= View::escape($regLabel !== ' — ' ? $regLabel : '#' . $registerId) ?></p>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Datenbank nicht verbunden.</div>
  <?php endif; ?>

  <?php
    $cashTseOutageActive = !empty($form['outage_active']);
    $cashTseOutageMessage = $cashTseOutageActive
        ? trim((string) ($form['last_error'] ?? ''))
        : '';
    $cashTseOutages = [];
    if ($cashTseOutageActive) {
        $cashTseOutages[] = [
            'register_id' => $registerId,
            'register_code' => (string) ($register['code'] ?? ''),
            'register_name' => (string) ($register['name'] ?? ''),
            'outage_since' => (string) ($form['outage_since'] ?? ''),
            'last_error' => (string) ($form['last_error'] ?? ''),
        ];
    }
    View::render('partials/cash-tse-outage-banner', compact('cashTseOutages', 'cashTseOutageActive', 'cashTseOutageMessage'));
  ?>

  <div class="dg-panel">
    <h2 class="dg-subsection-title">Prüfstatus</h2>
    <dl class="dg-dl">
      <dt>Ausfall</dt>
      <dd>
        <?php if (!empty($form['outage_active'])) : ?>
          <span class="dg-badge dg-badge--error">Aktiv</span>
          <?php if (trim((string) ($form['outage_since'] ?? '')) !== '') : ?>
            seit <?= View::escape((string) $form['outage_since']) ?>
          <?php endif; ?>
        <?php else : ?>
          <span class="dg-badge dg-badge--muted">Nein</span>
        <?php endif; ?>
      </dd>
      <dt>Letzter Status</dt>
      <dd>
        <?php if ($lastState !== '') : ?>
          <span class="dg-badge <?= ($lastError === '' && empty($form['outage_active'])) ? 'dg-badge--ok' : 'dg-badge--error' ?>">
            <?= View::escape($lastState) ?>
          </span>
        <?php else : ?>
          <span class="dg-muted">Noch nicht geprüft</span>
        <?php endif; ?>
      </dd>
      <dt>Zuletzt geprüft</dt>
      <dd><?= View::escape($lastChecked !== '' ? $lastChecked : '—') ?></dd>
      <dt>Zertifikat gültig bis</dt>
      <dd><?= View::escape($certUntil !== '' ? $certUntil : '—') ?></dd>
      <dt>TSE-Seriennummer</dt>
      <dd><?= View::escape($tssSerial !== '' ? $tssSerial : '—') ?></dd>
      <dt>Client-Seriennummer</dt>
      <dd><?= View::escape($clientSerial !== '' ? $clientSerial : '—') ?></dd>
      <dt>Letzte Meldung</dt>
      <dd><?= View::escape($lastError !== '' ? $lastError : ($hasAssignment ? '—' : 'Keine Zuordnung gespeichert')) ?></dd>
    </dl>
    <p class="dg-field-hint">Werte aus der letzten Verbindungsprüfung (nur Test-Umgebung).</p>
  </div>

  <form class="dg-form dg-panel" method="post" action="/app?page=registerkasse-tse-form&amp;register_id=<?= $registerId ?>">
    <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
    <input type="hidden" name="register_id" value="<?= $registerId ?>">

    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Provider *</span>
        <select name="provider" required <?= $disabled ? ' disabled' : '' ?>>
          <option value="mock"<?= ((string) ($form['provider'] ?? 'mock') === 'mock') ? ' selected' : '' ?>>mock (Entwicklung)</option>
          <option value="fiskaly"<?= ((string) ($form['provider'] ?? '') === 'fiskaly') ? ' selected' : '' ?>>fiskaly SIGN DE</option>
        </select>
      </label>

      <label class="dg-field">
        <span>Umgebung *</span>
        <select name="environment" required <?= $disabled ? ' disabled' : '' ?>>
          <option value="test"<?= ((string) ($form['environment'] ?? 'test') === 'test') ? ' selected' : '' ?>>test</option>
          <option value="live"<?= ((string) ($form['environment'] ?? '') === 'live') ? ' selected' : '' ?>>live</option>
        </select>
        <small class="dg-field-hint">Verbindungsprüfung nur bei „test“.</small>
      </label>

      <label class="dg-field dg-field--full">
        <span>TSE-ID (TSS)</span>
        <input type="text" name="tss_id" maxlength="36"
               value="<?= View::escape((string) ($form['tss_id'] ?? '')) ?>"
               <?= $disabled ? ' disabled' : '' ?>
               placeholder="UUID der TSS">
        <small class="dg-field-hint">Eine TSS pro Filiale — gleiche ID für alle Kassen am Standort.</small>
      </label>

      <label class="dg-field dg-field--full">
        <span>Kassen-Client-ID</span>
        <input type="text" name="client_id" maxlength="36"
               value="<?= View::escape((string) ($form['client_id'] ?? '')) ?>"
               <?= $disabled ? ' disabled' : '' ?>
               placeholder="UUID des Clients">
        <small class="dg-field-hint">Ein Client pro Kasse.</small>
      </label>

      <label class="dg-field dg-field--full">
        <span>Notiz</span>
        <textarea name="notes" rows="2" maxlength="500" <?= $disabled ? ' disabled' : '' ?>><?= View::escape((string) ($form['notes'] ?? '')) ?></textarea>
      </label>

      <label class="dg-field dg-field--checkbox">
        <input type="checkbox" name="is_active" value="1"
               <?= !empty($form['is_active']) || !array_key_exists('is_active', $form) ? ' checked' : '' ?>
               <?= $disabled ? ' disabled' : '' ?>>
        <span>Zuordnung aktiv</span>
      </label>
    </div>

    <p class="dg-field-hint">
      Prüfung nutzt die gespeicherte Zuordnung (Änderungen zuerst speichern).
      Zugangsdaten: <a href="<?= View::escape($tseSettingsUrl) ?>">Einstellungen → TSE / Kassensicherung</a>.
      <?php if ($hasAssignment && !$envIsTest) : ?>
        Live: keine Verbindungsprüfung über diesen Button.
      <?php elseif (!$hasAssignment) : ?>
        Nach dem ersten Speichern erscheint „Verbindung prüfen“.
      <?php endif; ?>
    </p>

    <?php if ($canEdit && $dbConnected) : ?>
      <div class="dg-form-actions">
        <button type="submit" name="cash_tse_save" value="1" class="dg-button dg-button--primary">Speichern</button>
        <button type="submit" name="cash_tse_ping" value="1" class="dg-button"<?= !$canPing ? ' disabled' : '' ?>>Verbindung prüfen</button>
        <a class="dg-button" href="/app?page=registerkasse-tse">Abbrechen</a>
      </div>
    <?php endif; ?>
  </form>
</div>
