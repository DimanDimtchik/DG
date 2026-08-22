<?php
/**
 * @var list<array<string, mixed>> $timeClockTeam
 * @var array{type: string, message: string}|null $flash
 */
$team = $timeClockTeam ?? [];
?>
<div class="dg-wrap dg-zeiterfassung-team">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Team heute</h1>
      <p class="dg-lead">Wer ist eingestempelt — <?= date('d.m.Y') ?></p>
    </div>
    <div class="dg-toolbar">
      <a class="dg-button" href="/app?page=zeiterfassung">Meine Zeiterfassung</a>
    </div>
  </header>

  <section class="dg-panel">
    <?php if ($team === []) : ?>
      <p class="dg-muted">Keine Mitarbeiter-Kontakte gefunden.</p>
    <?php else : ?>
      <div class="dg-table-wrap">
        <table class="dg-table">
          <thead>
            <tr>
              <th>Mitarbeiter</th>
              <th>Status</th>
              <th>Seit</th>
              <th class="dg-table__num">Ist</th>
              <th class="dg-table__num">Soll</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($team as $row) : ?>
              <tr>
                <td><?= View::escape((string) ($row['label'] ?? '')) ?></td>
                <td>
                  <span class="dg-badge dg-badge--<?= ($row['status_state'] ?? '') === 'off' ? 'muted' : 'pending' ?>">
                    <?= View::escape((string) ($row['status_label'] ?? '')) ?>
                  </span>
                </td>
                <td><?= View::escape((string) ($row['since_display'] ?? '—')) ?></td>
                <td class="dg-table__num"><?= View::escape((string) ($row['worked_display'] ?? '0:00')) ?></td>
                <td class="dg-table__num"><?= View::escape((string) ($row['scheduled_display'] ?? '8:00')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>
