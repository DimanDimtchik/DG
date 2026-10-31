<?php
/** @var User $user */
/** @var array{enabled: bool, provider: string, dial_scheme: string, bridge_token: string, fritz_host: string} $ctiConfig */
/** @var list<array<string, mixed>> $callLog */
/** @var string $telefonDial */

$ctiConfig = $ctiConfig ?? CtiSettings::config();
$callLog = $callLog ?? [];
$telefonDial = $telefonDial ?? '';
$isAdmin = class_exists('RoleResolver') && RoleResolver::isAdmin($user);
$ctiOn = CtiSettings::isEnabled();
?>
<section class="dg-panel">
  <h1 class="dg-page-title">Telefon</h1>
  <p class="dg-lead">Click-to-Call über Softphone (z.&nbsp;B. Linphone) an der Fritzbox — eingehende Anrufe als Popup, wenn die lokale Bridge läuft.</p>

  <?php if (!$ctiOn) : ?>
    <p class="dg-field-hint">CTI ist noch aus.<?= $isAdmin ? ' Unten aktivieren.' : ' Bitte Admin: Einstellungen auf dieser Seite.' ?></p>
  <?php endif; ?>

  <form class="dg-form dg-telefon-dial" method="get" action="/app" id="dg-telefon-dial-form">
    <input type="hidden" name="page" value="telefon">
    <div class="dg-form-grid">
      <label class="dg-field dg-field--wide">
        <span>Nummer wählen</span>
        <input type="tel" name="dial" id="dg-telefon-number" value="<?= View::escape($telefonDial) ?>" placeholder="+49 …" autocomplete="tel">
      </label>
    </div>
    <div class="dg-form-actions">
      <button type="button" class="dg-button dg-button--primary" id="dg-telefon-call-btn"<?= $ctiOn ? '' : ' disabled' ?>>Anrufen</button>
    </div>
  </form>
</section>

<section class="dg-panel" style="margin-top:16px">
  <h2 class="dg-subsection-title">Letzte Anrufe</h2>
  <?php if ($callLog === []) : ?>
    <p class="dg-muted">Noch keine Einträge.</p>
  <?php else : ?>
    <div class="dg-table-wrap">
      <table class="dg-table">
        <thead>
          <tr>
            <th>Zeit</th>
            <th>Richtung</th>
            <th>Nummer</th>
            <th>Kontakt</th>
            <th>Status</th>
            <th>Dauer</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($callLog as $row) : ?>
            <?php
              $cid = (int) ($row['contact_id'] ?? 0);
              $cname = trim((string) ($row['contact_name'] ?? ''));
              $phone = (string) ($row['phone_e164'] ?? $row['phone_raw'] ?? '');
              $dur = (int) ($row['duration_sec'] ?? 0);
              $dir = ($row['direction'] ?? '') === 'out' ? 'Ausgehend' : 'Eingehend';
            ?>
            <tr>
              <td><?= View::escape((string) ($row['started_at'] ?? '')) ?></td>
              <td><?= View::escape($dir) ?></td>
              <td><?= View::escape(PhoneNormalizer::formatForDisplay($phone)) ?></td>
              <td>
                <?php if ($cid > 0) : ?>
                  <a href="/app?page=kontakte&amp;action=edit&amp;id=<?= $cid ?>"><?= View::escape($cname !== '' ? $cname : ('#' . $cid)) ?></a>
                <?php else : ?>
                  <span class="dg-muted">—</span>
                <?php endif; ?>
              </td>
              <td><?= View::escape((string) ($row['status'] ?? '')) ?></td>
              <td><?= $dur > 0 ? View::escape(sprintf('%d:%02d', intdiv($dur, 60), $dur % 60)) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php if ($isAdmin) : ?>
<section class="dg-panel" style="margin-top:16px">
  <h2 class="dg-subsection-title">CTI-Einstellungen (Admin)</h2>
  <p class="dg-field-hint">Anbieter derzeit: <strong>Fritzbox</strong> (CallMonitor). Andere Router nur mit eigener Anbindung.</p>
  <form class="dg-form" method="post" action="/app?page=telefon">
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
    <div class="dg-form-grid">
      <label class="dg-field dg-field--checkbox">
        <span><input type="checkbox" name="enabled" value="1"<?= !empty($ctiConfig['enabled']) ? ' checked' : '' ?>> CTI aktiv</span>
      </label>
      <label class="dg-field">
        <span>Anbieter</span>
        <select name="provider">
          <option value="fritz"<?= ($ctiConfig['provider'] ?? '') === 'fritz' ? ' selected' : '' ?>>Fritzbox</option>
          <option value="none"<?= ($ctiConfig['provider'] ?? '') === 'none' ? ' selected' : '' ?>>Kein / aus</option>
        </select>
      </label>
      <label class="dg-field">
        <span>Wahl-Schema (Softphone)</span>
        <select name="dial_scheme">
          <option value="sip"<?= ($ctiConfig['dial_scheme'] ?? '') === 'sip' ? ' selected' : '' ?>>sip: (Linphone)</option>
          <option value="tel"<?= ($ctiConfig['dial_scheme'] ?? '') === 'tel' ? ' selected' : '' ?>>tel:</option>
        </select>
      </label>
      <label class="dg-field">
        <span>Fritz-Host (für Bridge)</span>
        <input type="text" name="fritz_host" value="<?= View::escape((string) ($ctiConfig['fritz_host'] ?? 'fritz.box')) ?>">
      </label>
      <label class="dg-field dg-field--checkbox dg-field--wide">
        <span>Bridge-Token</span>
        <input type="text" name="bridge_token" value="<?= View::escape((string) ($ctiConfig['bridge_token'] ?? '')) ?>" readonly>
        <span class="dg-field-hint"><label><input type="checkbox" name="bridge_token_rotate" value="1"> neues Token erzeugen</label></span>
      </label>
    </div>
    <div class="dg-form-actions">
      <button type="submit" name="cti_save" value="1" class="dg-button dg-button--primary">Speichern</button>
    </div>
  </form>
  <?php if (($ctiConfig['bridge_token'] ?? '') !== '') : ?>
    <pre class="dg-field-hint" style="white-space:pre-wrap;margin-top:12px">php bin/fritz-callmonitor-bridge.php --host=<?= View::escape((string) $ctiConfig['fritz_host']) ?> --crm=https://DIESE-DOMAIN --token=<?= View::escape((string) $ctiConfig['bridge_token']) ?></pre>
  <?php endif; ?>
</section>
<?php endif; ?>

<script>
window.dgTelefonDial = {
  csrf: <?= json_encode(Csrf::token(), JSON_UNESCAPED_UNICODE) ?>,
  enabled: <?= $ctiOn ? 'true' : 'false' ?>
};
</script>
<script src="<?= View::escape(Asset::url('/assets/js/telefon-dial.js')) ?>" defer></script>
