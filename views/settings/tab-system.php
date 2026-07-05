<?php
/** @var array<string, mixed> $dbConfig */
/** @var bool $dbConnected */
?>
<form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

  <p class="dg-lead">MariaDB/MySQL-Zugangsdaten aus dem All-Inkl KAS. Passwort leer lassen = unverändert lassen.</p>

  <?php if ($dbConnected) : ?>
    <div class="dg-flash dg-flash--success">Datenbank ist verbunden.</div>
  <?php else : ?>
    <div class="dg-flash dg-flash--warning">Noch keine aktive Verbindung – Zugangsdaten eintragen und testen.</div>
  <?php endif; ?>

  <div class="dg-form-grid">
    <label class="dg-field">
      <span>Host</span>
      <input type="text" name="host" value="<?= View::escape((string) $dbConfig['host']) ?>" required>
    </label>
    <label class="dg-field">
      <span>Port</span>
      <input type="number" name="port" value="<?= (int) $dbConfig['port'] ?>" min="1" max="65535">
    </label>
    <label class="dg-field">
      <span>Datenbank</span>
      <input type="text" name="database" value="<?= View::escape((string) $dbConfig['database']) ?>" required>
    </label>
    <label class="dg-field">
      <span>Benutzer</span>
      <input type="text" name="username" value="<?= View::escape((string) $dbConfig['username']) ?>" required>
    </label>
    <label class="dg-field dg-field--wide">
      <span>Passwort</span>
      <input type="password" name="password" value="" autocomplete="new-password" placeholder="<?= $dbConfig['password'] !== '' ? '•••••••• (gespeichert)' : '' ?>">
    </label>
    <label class="dg-field">
      <span>Zeichensatz</span>
      <input type="text" name="charset" value="<?= View::escape((string) $dbConfig['charset']) ?>">
    </label>
  </div>

  <div class="dg-form-actions">
    <button type="submit" name="db_action" value="test" class="dg-button">Verbindung testen</button>
    <button type="submit" name="db_action" value="save" class="dg-button dg-button--primary">Speichern</button>
    <button type="submit" name="db_action" value="migrate" class="dg-button">Speichern &amp; Tabellen anlegen</button>
  </div>
</form>
