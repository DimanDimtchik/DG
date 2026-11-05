<?php
/**
 * @var string $arbeitsplanWeekMonday
 * @var list<string> $arbeitsplanWeekDates
 * @var int $arbeitsplanEmployeeId
 * @var string $arbeitsplanEmployeeName
 * @var list<array{id: int, name: string}> $arbeitsplanEmployeeOptions
 * @var bool $arbeitsplanCanPickEmployee
 * @var array<string, list<array{start_time: string, end_time: string}>> $arbeitsplanWindowsByDate
 * @var bool $arbeitsplanTableReady
 * @var array{type: string, message: string}|null $flash
 */
$monday = (string) ($arbeitsplanWeekMonday ?? date('Y-m-d'));
$dates = is_array($arbeitsplanWeekDates ?? null) ? $arbeitsplanWeekDates : [];
$employeeId = (int) ($arbeitsplanEmployeeId ?? 0);
$employeeName = (string) ($arbeitsplanEmployeeName ?? '');
$options = is_array($arbeitsplanEmployeeOptions ?? null) ? $arbeitsplanEmployeeOptions : [];
$canPick = !empty($arbeitsplanCanPickEmployee);
$byDate = is_array($arbeitsplanWindowsByDate ?? null) ? $arbeitsplanWindowsByDate : [];
$tableReady = !empty($arbeitsplanTableReady);
$weekdayLabels = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
$prev = (new DateTimeImmutable($monday))->modify('-7 days')->format('Y-m-d');
$next = (new DateTimeImmutable($monday))->modify('+7 days')->format('Y-m-d');
$weekEnd = $dates !== [] ? $dates[count($dates) - 1] : $monday;

$hm = static function (string $t): string {
    if (preg_match('/^(\d{1,2}):(\d{2})/', $t, $m)) {
        return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
    }

    return '';
};
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Arbeitsplan</h1>
      <p class="dg-lead">
        Geplante Zeiten öffnen buchbare Termine. Stempelzeiten bleiben getrennt.
        Mit Planfenster an einem Tag gilt nur der Plan — sonst das Wochenraster unter Termine → Mitarbeiter.
      </p>
    </div>
    <div class="dg-page-header__actions">
      <a class="dg-button" href="/app?page=zeiterfassung-schichten">Schichtplan</a>
      <a class="dg-button" href="/app?page=zeiterfassung">Zeiterfassung</a>
    </div>
  </header>

  <?php if (!$tableReady) : ?>
    <div class="dg-flash dg-flash--warning">Arbeitsplan-Tabelle fehlt — Migration 141 ausführen.</div>
  <?php elseif ($employeeId < 1) : ?>
    <div class="dg-flash dg-flash--warning">
      Kein Kalender-Mitarbeiter verknüpft. Unter Einstellungen → Termine → Bereiche und Mitglieder
      den Kontakt zuordnen.
    </div>
  <?php else : ?>
    <section class="dg-panel">
      <form method="get" action="/app" class="dg-form dg-form-grid">
        <input type="hidden" name="page" value="zeiterfassung-arbeitsplan">
        <label class="dg-field">
          <span>Woche (beliebiges Datum)</span>
          <input type="date" name="week" value="<?= View::escape($monday) ?>" required>
        </label>
        <?php if ($canPick && $options !== []) : ?>
          <label class="dg-field">
            <span>Mitarbeiter</span>
            <select name="employee_id">
              <?php foreach ($options as $opt) : ?>
                <option value="<?= (int) ($opt['id'] ?? 0) ?>"<?= (int) ($opt['id'] ?? 0) === $employeeId ? ' selected' : '' ?>>
                  <?= View::escape((string) ($opt['name'] ?? '')) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
        <?php endif; ?>
        <div class="dg-field dg-field--actions">
          <button type="submit" class="dg-button">Anzeigen</button>
          <a class="dg-button" href="/app?page=zeiterfassung-arbeitsplan&amp;week=<?= View::escape($prev) ?>&amp;employee_id=<?= $employeeId ?>">←</a>
          <a class="dg-button" href="/app?page=zeiterfassung-arbeitsplan&amp;week=<?= View::escape($next) ?>&amp;employee_id=<?= $employeeId ?>">→</a>
        </div>
      </form>
    </section>

    <section class="dg-panel">
      <h2 class="dg-subsection-title"><?= View::escape($employeeName) ?></h2>
      <p class="dg-field-hint">
        <?= View::escape(
            (new DateTimeImmutable($monday))->format('d.m.Y') . ' – ' . (new DateTimeImmutable($weekEnd))->format('d.m.Y')
        ) ?>
        · Leer lassen = kein Planfenster (Wochenraster). Raster 15 Minuten.
      </p>
      <form method="post" action="/app?page=zeiterfassung-arbeitsplan" class="dg-form">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <input type="hidden" name="arbeitsplan_action" value="save_week">
        <input type="hidden" name="week_monday" value="<?= View::escape($monday) ?>">
        <input type="hidden" name="employee_id" value="<?= $employeeId ?>">
        <div class="dg-stack">
          <?php foreach ($dates as $i => $d) : ?>
            <?php
              $wins = $byDate[$d] ?? [];
              if ($wins === []) {
                  $wins = [['start_time' => '', 'end_time' => '']];
              }
              // Immer zwei Zeilen anbieten
              while (count($wins) < 2) {
                  $wins[] = ['start_time' => '', 'end_time' => ''];
              }
            ?>
            <div class="dg-panel">
              <p class="dg-lead">
                <?= View::escape($weekdayLabels[$i] ?? '') ?>
                · <?= View::escape((new DateTimeImmutable($d))->format('d.m.Y')) ?>
              </p>
              <div class="dg-form-grid">
                <?php foreach ($wins as $wi => $win) : ?>
                  <label class="dg-field">
                    <span>Von <?= ((int) $wi) + 1 ?></span>
                    <input
                      type="time"
                      name="days[<?= View::escape($d) ?>][<?= (int) $wi ?>][start_time]"
                      value="<?= View::escape($hm((string) ($win['start_time'] ?? ''))) ?>"
                      step="900"
                    >
                  </label>
                  <label class="dg-field">
                    <span>Bis <?= ((int) $wi) + 1 ?></span>
                    <input
                      type="time"
                      name="days[<?= View::escape($d) ?>][<?= (int) $wi ?>][end_time]"
                      value="<?= View::escape($hm((string) ($win['end_time'] ?? ''))) ?>"
                      step="900"
                    >
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="dg-form-actions">
          <button type="submit" class="dg-button dg-button--primary">Woche speichern</button>
        </div>
      </form>
    </section>
  <?php endif; ?>
</div>
