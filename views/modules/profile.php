<?php
/** @var User $user */
$readOnly = RoleResolver::isCustomer($user);
?>
<div class="dg-wrap">
  <header class="dg-page-header">
    <?php if (!$readOnly) : ?>
      <?php View::partial('partials/back-nav', [
          'href' => '/app',
          'label' => 'Zurück zum Dashboard',
      ]); ?>
    <?php endif; ?>
    <h1 class="dg-page-title">Mein Profil</h1>
    <p class="dg-lead">
      <?= View::escape($user->displayName) ?> · <?= View::escape(RoleResolver::roleLabel($user)) ?>
      <?php if ($readOnly) : ?> · Nur Lesezugriff<?php endif; ?>
    </p>
  </header>

  <?php if ($readOnly) : ?>
    <div class="dg-flash dg-flash--warning">Als Kunde sehen Sie nur Ihr Profil. Änderungen an Geschäftsdaten bitte an uns richten.</div>
  <?php endif; ?>

  <div class="dg-panel">
    <dl class="dg-dl">
      <dt>Benutzername</dt>
      <dd><?= View::escape($user->username) ?></dd>
      <dt>Anzeigename</dt>
      <dd><?= View::escape($user->displayName) ?></dd>
      <dt>E-Mail</dt>
      <dd><?= View::escape($user->email !== '' ? $user->email : '—') ?></dd>
      <dt>Rolle</dt>
      <dd><?= View::escape(RoleResolver::roleLabel($user)) ?></dd>
    </dl>
  </div>
</div>
