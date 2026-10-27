<?php
/**
 * @var list<array<string, mixed>> $giftVoucherList
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$rows = $giftVoucherList ?? [];
$typeLabels = GiftVoucherRepository::typeOptions();
$statusLabels = GiftVoucherRepository::statusOptions();
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Gutscheine</h1>
      <p class="dg-lead">Mehrzweck-Gutscheine: Verkauf ohne USt, Einlösung über Rechnung oder Kasse.</p>
    </div>
    <div class="dg-toolbar">
      <?php if ($canEdit && $dbConnected) : ?>
        <a class="dg-button dg-button--primary" href="/app?page=gutscheine-form&amp;action=new">Gutschein ausstellen</a>
        <a class="dg-button" href="/app?page=gutscheine-flyer-vorlagen">Flyer-Vorlagen</a>
        <a class="dg-button" href="/app?page=website-gutschein-flyer">Flyer drucken</a>
      <?php endif; ?>
    </div>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Datenbank nicht verbunden.
      <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Einstellungen → Datenbank</a>
    </div>
  <?php endif; ?>

  <div class="dg-table-wrap">
    <table class="dg-table">
      <thead>
        <tr>
          <th>Code</th>
          <th>Typ</th>
          <th>Status</th>
          <th>Betrag</th>
          <th>Rest</th>
          <th>Gültig bis</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($rows === []) : ?>
          <tr><td colspan="7" class="dg-table__empty">Noch keine Gutscheine.</td></tr>
        <?php else : ?>
          <?php foreach ($rows as $row) : ?>
            <?php
              $gid = (int) ($row['id'] ?? 0);
              $status = (string) ($row['status'] ?? '');
              $type = (string) ($row['voucher_type'] ?? '');
            ?>
            <tr>
              <td><strong><?= View::escape((string) ($row['code'] ?? '')) ?></strong></td>
              <td><?= View::escape($typeLabels[$type] ?? $type) ?></td>
              <td>
                <span class="dg-badge <?= $status === 'active' ? 'dg-badge--ok' : 'dg-badge--muted' ?>">
                  <?= View::escape($statusLabels[$status] ?? $status) ?>
                </span>
              </td>
              <td><?= View::escape(number_format((float) ($row['face_value_gross'] ?? $row['amount'] ?? 0), 2, ',', '.')) ?> €</td>
              <td><?= View::escape(number_format((float) ($row['remaining_gross'] ?? $row['remaining'] ?? 0), 2, ',', '.')) ?> €</td>
              <td><?= View::escape((string) ($row['valid_until'] ?? '—')) ?></td>
              <td class="dg-table__actions">
                <a href="/app?page=gutscheine-form&amp;action=view&amp;id=<?= $gid ?>">Anzeigen</a>
                ·
                <a href="/app?page=website-gutschein-flyer&amp;id=<?= $gid ?>">Flyer</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
