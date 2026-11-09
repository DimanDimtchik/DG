<?php
/** @var list<array<string, mixed>> $auditEntries */
/** @var array<string, mixed>|null $auditEntriesPaged */
$auditEntriesPaged = is_array($auditEntriesPaged ?? null) ? $auditEntriesPaged : null;
if ($auditEntriesPaged !== null && isset($auditEntriesPaged['items'])) {
    $auditEntries = $auditEntriesPaged['items'];
    $auditTotal = (int) ($auditEntriesPaged['total'] ?? count($auditEntries));
    $auditPageNum = (int) ($auditEntriesPaged['page'] ?? 1);
    $auditTotalPages = (int) ($auditEntriesPaged['total_pages'] ?? 1);
    $auditPerPageKey = (string) ($auditEntriesPaged['per_page_key'] ?? ListPagination::DEFAULT);
} else {
    $auditEntries = $auditEntries ?? [];
    $auditTotal = count($auditEntries);
    $auditPageNum = 1;
    $auditTotalPages = 1;
    $auditPerPageKey = ListPagination::DEFAULT;
}
?>

<div class="dg-panel">
  <div class="dg-panel__header">
    <h1>Sicherheitsprotokoll</h1>
    <a href="/app?page=einstellungen" class="dg-button dg-button--secondary">← Einstellungen</a>
  </div>

  <?php if (empty($auditEntries)) : ?>
    <p class="dg-empty">Noch keine Einträge vorhanden.</p>
  <?php else : ?>
    <?php
      View::partial('partials/list-per-page', [
          'total' => $auditTotal,
          'perPageKey' => $auditPerPageKey,
          'listPage' => 'sicherheitsprotokoll',
          'extraQuery' => [],
      ]);
    ?>
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
    <?php
      View::partial('partials/list-pagination-nav', [
          'total' => $auditTotal,
          'page' => $auditPageNum,
          'totalPages' => $auditTotalPages,
          'perPageKey' => $auditPerPageKey,
          'listPage' => 'sicherheitsprotokoll',
          'extraQuery' => [],
      ]);
    ?>
  <?php endif; ?>
</div>
