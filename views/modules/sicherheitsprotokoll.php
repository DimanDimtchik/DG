<?php
/** @var list<array<string, mixed>> $auditEntries */
?>

<div class="dg-panel">
  <div class="dg-panel__header">
    <h1>Sicherheitsprotokoll</h1>
    <a href="/app?page=einstellungen" class="dg-button dg-button--secondary">← Einstellungen</a>
  </div>

  <?php if (empty($auditEntries)) : ?>
    <p class="dg-empty">Noch keine Einträge vorhanden.</p>
  <?php else : ?>
    <div class="dg-table-wrap">
      <table class="dg-table">
        <thead>
          <tr>
            <th>Zeitpunkt</th>
            <th>Aktion</th>
            <th>Benutzer</th>
            <th>IP-Adresse</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($auditEntries as $entry) : ?>
            <tr>
              <td><?= View::escape((string) ($entry['created_at'] ?? '')) ?></td>
              <td>
                <?php
                $actionLabels = [
                    'login_success' => 'Anmeldung',
                    'login_failed'  => 'Fehlgeschlagen',
                    'logout'        => 'Abmeldung',
                    'password_change' => 'Passwort geändert',
                    'user_created'  => 'Benutzer angelegt',
                    'settings_changed' => 'Einstellungen',
                ];
                $action = (string) ($entry['action'] ?? '');
                $label = $actionLabels[$action] ?? $action;
                $badgeClass = match (true) {
                    str_contains($action, 'failed') => 'dg-badge--danger',
                    str_contains($action, 'success') || $action === 'login_success' => 'dg-badge--success',
                    default => 'dg-badge--neutral',
                };
                ?>
                <span class="dg-badge <?= $badgeClass ?>"><?= View::escape($label) ?></span>
              </td>
              <td><?= View::escape((string) ($entry['username'] ?? '–')) ?></td>
              <td><code><?= View::escape((string) ($entry['ip'] ?? '')) ?></code></td>
              <td><?= View::escape(mb_substr((string) ($entry['details'] ?? ''), 0, 120)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
