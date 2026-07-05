<?php
/** @var list<User> $crmUsers */
?>
<div class="dg-wrap">
  <header class="dg-page-header">
    <?php View::partial('partials/back-nav', [
        'href' => '/app',
        'label' => 'Zurück zum Dashboard',
    ]); ?>
    <h1 class="dg-page-title">Benutzer &amp; Rollen</h1>
    <p class="dg-lead">CRM-Zugänge: Administrator, Mitarbeiter und Kunde.</p>
  </header>

  <div class="dg-table-wrap">
    <table class="dg-table">
      <thead>
        <tr>
          <th>Benutzername</th>
          <th>Anzeigename</th>
          <th>E-Mail</th>
          <th>Rolle</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($crmUsers as $crmUser) : ?>
          <tr>
            <td><strong><?= View::escape($crmUser->username) ?></strong></td>
            <td><?= View::escape($crmUser->displayName) ?></td>
            <td><?= View::escape($crmUser->email !== '' ? $crmUser->email : '—') ?></td>
            <td><?= View::escape(RoleResolver::roleLabel($crmUser)) ?></td>
            <td>
              <?php if (RoleResolver::isAdmin($crmUser)) : ?>
                aktiv
              <?php elseif (RoleResolver::isActiveEmployee($crmUser)) : ?>
                aktiv
              <?php elseif (RoleResolver::isCustomer($crmUser)) : ?>
                aktiv (nur Profil)
              <?php else : ?>
                inaktiv
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="dg-panel dg-panel--notice" style="margin-top:16px">
    <p><strong>Rollen:</strong> Administrator (alles) · Mitarbeiter (bearbeiten) · Kunde (nur Profil, Registrierung unter <a href="/register">/register</a>)</p>
  </div>
</div>
