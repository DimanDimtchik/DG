<?php
/**
 * @var array<string, mixed> $timeClockSummary
 * @var array{state: string, label: string, since_display: string|null} $timeClockStatus
 * @var int|null $timeClockContactId
 * @var string $timeClockEmployeeLabel
 * @var bool $timeClockCanTeam
 * @var array{type: string, message: string}|null $flash
 */
$summary = $timeClockSummary ?? [];
$status = $timeClockStatus ?? ['state' => 'off', 'label' => 'Nicht eingestempelt', 'since_display' => null];
$contactId = (int) ($timeClockContactId ?? 0);
$employeeLabel = (string) ($timeClockEmployeeLabel ?? '');
$events = is_array($summary['events'] ?? null) ? $summary['events'] : [];
$warnings = is_array($summary['warnings'] ?? null) ? $summary['warnings'] : [];
$state = (string) ($status['state'] ?? 'off');
?>
<div class="dg-wrap dg-zeiterfassung">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Zeiterfassung</h1>
      <p class="dg-lead">Einstempeln, Pausen und Tagesübersicht</p>
    </div>
    <?php if ($timeClockCanTeam ?? false) : ?>
      <div class="dg-toolbar">
        <a class="dg-button" href="/app?page=zeiterfassung-team">Team heute</a>
      </div>
    <?php endif; ?>
  </header>

  <?php if ($contactId < 1) : ?>
    <div class="dg-flash dg-flash--warning">
      Kein Mitarbeiter-Kontakt mit Ihrem Login verknüpft. Bitte in
      <a href="/app?page=kontakte">Kontakte</a> die Rolle Mitarbeiter setzen und E-Mail oder Login abstimmen.
    </div>
  <?php else : ?>
    <section class="dg-panel dg-time-clock-card">
      <h2 class="dg-subsection-title">Guten Tag, <?= View::escape($employeeLabel) ?></h2>
      <p class="dg-time-clock-status">
        Status:
        <strong class="dg-time-clock-status__value dg-time-clock-status--<?= View::escape($state) ?>">
          <?= View::escape((string) ($status['label'] ?? '')) ?>
        </strong>
        <?php if (!empty($status['since_display'])) : ?>
          <span class="dg-muted">seit <?= View::escape((string) $status['since_display']) ?></span>
        <?php endif; ?>
      </p>

      <div class="dg-time-clock-actions">
        <?php if ($state === 'off') : ?>
          <form method="post" action="/app?page=zeiterfassung">
            <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
            <input type="hidden" name="time_clock_action" value="clock_in">
            <button type="submit" class="dg-button dg-button--primary">Einstempeln</button>
          </form>
        <?php elseif ($state === 'working') : ?>
          <form method="post" action="/app?page=zeiterfassung" class="dg-inline-form">
            <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
            <input type="hidden" name="time_clock_action" value="clock_out">
            <button type="submit" class="dg-button dg-button--warning">Ausstempeln</button>
          </form>
          <form method="post" action="/app?page=zeiterfassung" class="dg-inline-form">
            <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
            <input type="hidden" name="time_clock_action" value="break_start">
            <button type="submit" class="dg-button">Pause starten</button>
          </form>
        <?php elseif ($state === 'break') : ?>
          <form method="post" action="/app?page=zeiterfassung">
            <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
            <input type="hidden" name="time_clock_action" value="break_end">
            <button type="submit" class="dg-button dg-button--primary">Pause beenden</button>
          </form>
        <?php endif; ?>
      </div>

      <div class="dg-time-clock-summary">
        <p><strong>Heute:</strong> <?= View::escape((string) ($summary['worked_display'] ?? '0:00')) ?> h
          (Pause: <?= View::escape((string) ($summary['break_display'] ?? '0:00')) ?> h)</p>
        <p><strong>Soll heute:</strong> <?= View::escape((string) ($summary['scheduled_display'] ?? '8:00')) ?> h</p>
      </div>

      <?php foreach ($warnings as $warning) : ?>
        <div class="dg-flash dg-flash--warning"><?= View::escape((string) $warning) ?></div>
      <?php endforeach; ?>
    </section>

    <section class="dg-panel">
      <h2 class="dg-subsection-title">Stempel heute</h2>
      <?php if ($events === []) : ?>
        <p class="dg-muted">Noch keine Stempelungen heute.</p>
      <?php else : ?>
        <div class="dg-table-wrap">
          <table class="dg-table">
            <thead>
              <tr>
                <th>Zeit</th>
                <th>Aktion</th>
                <th>Quelle</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($events as $event) : ?>
                <tr>
                  <td><?= View::escape((string) ($event['occurred_display'] ?? '')) ?></td>
                  <td><?= View::escape((string) ($event['event_label'] ?? '')) ?></td>
                  <td><?= View::escape((string) ($event['source'] ?? '')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>
