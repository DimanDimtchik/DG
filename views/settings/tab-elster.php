<?php
/**
 * @var array<string, mixed> $elsterConfig
 * @var bool $dbConnected
 */
$cfg = $elsterConfig ?? ElsterSettings::forForm();
$readiness = is_array($cfg['readiness'] ?? null) ? $cfg['readiness'] : ElsterEricClient::readiness();
$supportsEric = (bool) ($cfg['server_supports_eric'] ?? false);
$mode = (string) ($cfg['mode'] ?? ElsterSettings::MODE_CSV);
?>
<form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('elster')) ?>">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

  <div class="dg-panel dg-panel--notice">
    <p>
      <strong>Aktuell:</strong> UStVA und EÜR als <strong>CSV</strong> für manuelle ELSTER-Eingabe
      (Buchhaltung → UStVA). Direkte Übermittlung via <strong>ERiC</strong> ist vorbereitet,
      aber erst nach dem Umzug auf einen Root-Server (Favorit: <strong>Hetzner SX65-2</strong>).
    </p>
    <p>
      Plan im Repository: <code>docs/SERVER-MIGRATION.md</code> und <code>docs/ELSTER-ERIC-TODO.md</code>
    </p>
  </div>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Datenbank nicht verbunden.</div>
  <?php endif; ?>

  <fieldset class="dg-form-grid"<?= !$dbConnected ? ' disabled' : '' ?>>
    <legend>Betriebsmodus</legend>
    <label class="dg-field dg-field--wide">
      <span>ELSTER-Modus</span>
      <select name="elster_mode">
        <option value="csv"<?= $mode === ElsterSettings::MODE_CSV ? ' selected' : '' ?>>
          CSV — Kennziffern exportieren, manuell in ELSTER eintragen (Standard)
        </option>
        <option value="eric"<?= $mode === ElsterSettings::MODE_ERIC ? ' selected' : '' ?> <?= !$supportsEric ? ' disabled' : '' ?>>
          ERiC — direkte Übermittlung ans Finanzamt (nur nach Server-Umzug)
        </option>
      </select>
      <?php if (!$supportsEric) : ?>
        <small class="dg-field-hint">
          ERiC ist auf dem derzeitigen Kasserver nicht verfügbar. Nach Umzug
          <code>config/elster.local.php</code> anlegen und <code>server_ready = true</code> setzen.
        </small>
      <?php endif; ?>
    </label>

    <label class="dg-field">
      <span>Hersteller-ID (Vorbereitung)</span>
      <input type="text" name="elster_manufacturer_id" value="<?= View::escape((string) ($cfg['manufacturer_id'] ?? '')) ?>" placeholder="nach ELSTER-Registrierung">
    </label>

    <label class="dg-field dg-field--wide">
      <span>ERiC-Worker-URL (Vorbereitung)</span>
      <input type="url" name="elster_eric_worker_url" value="<?= View::escape((string) ($cfg['eric_worker_url'] ?? '')) ?>" placeholder="http://127.0.0.1:9109">
      <small class="dg-field-hint">Interner Worker nach ERiC-Installation — nicht öffentlich ohne Absicherung.</small>
    </label>

    <label class="dg-field">
      <span>Testmodus (für spätere ERiC-Tests)</span>
      <select name="elster_eric_test_mode">
        <option value="1"<?= !empty($cfg['eric_test_mode']) ? ' selected' : '' ?>>Ja — Testmerker + Test-Zertifikat</option>
        <option value="0"<?= empty($cfg['eric_test_mode']) ? ' selected' : '' ?>>Nein — Produktions-Abgaben</option>
      </select>
    </label>
  </fieldset>

  <section class="dg-panel">
    <h3 class="dg-subsection-title">Readiness-Check</h3>
    <ul>
      <?php foreach ($readiness['items'] as $item) : ?>
        <li>
          <span class="dg-badge <?= !empty($item['ok']) ? 'dg-badge--ok' : 'dg-badge--pending' ?>">
            <?= !empty($item['ok']) ? 'OK' : 'Offen' ?>
          </span>
          <?= View::escape((string) ($item['label'] ?? '')) ?>
          — <?= View::escape((string) ($item['detail'] ?? '')) ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="dg-muted">CLI: <code>php bin/elster-readiness.php</code></p>
  </section>

  <p class="dg-form-actions">
    <button type="submit" name="elster_save" value="1" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>>Vorbereitung speichern</button>
  </p>
</form>

<section class="dg-panel">
  <h3 class="dg-subsection-title">Server-Favorit &amp; Mindestanforderungen</h3>
  <dl class="dg-dl">
    <div><dt>Favorit</dt><dd>Hetzner SX65-2 (Dedicated Storage, Ryzen 7 3700X, 64 GB RAM, NVMe + HDD)</dd></div>
    <div><dt>Minimum ERiC</dt><dd>2 vCPU, 2 GB RAM, 20 GB SSD, Linux x64</dd></div>
    <div><dt>Empfohlen DG+ERiC</dt><dd>4+ Kerne, 8–16 GB RAM, 40 GB NVMe (+ Backup-Disk)</dd></div>
    <div><dt>Derzeit</dt><dd>Kasserver Shared Hosting — kein Docker, kein ERiC</dd></div>
  </dl>
</section>
