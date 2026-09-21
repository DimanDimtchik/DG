<?php
/**
 * @var string $timeShiftWeekMonday
 * @var list<string> $timeShiftWeekDates
 * @var list<array{id: int, label: string}> $timeShiftStaff
 * @var list<array<string, mixed>> $timeShiftActiveTemplates
 * @var array<string, array<string, mixed>> $timeShiftAssignmentMap
 * @var array{type: string, message: string}|null $flash
 */
$monday = (string) ($timeShiftWeekMonday ?? date('Y-m-d'));
$dates = is_array($timeShiftWeekDates ?? null) ? $timeShiftWeekDates : [];
$staff = is_array($timeShiftStaff ?? null) ? $timeShiftStaff : [];
$templates = is_array($timeShiftActiveTemplates ?? null) ? $timeShiftActiveTemplates : [];
$map = is_array($timeShiftAssignmentMap ?? null) ? $timeShiftAssignmentMap : [];
$weekdayLabels = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
$prev = (new DateTimeImmutable($monday))->modify('-7 days')->format('Y-m-d');
$next = (new DateTimeImmutable($monday))->modify('+7 days')->format('Y-m-d');
$weekEnd = $dates !== [] ? $dates[count($dates) - 1] : $monday;
?>
<div class="dg-wrap dg-zeiterfassung-schichten">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Schichtplan</h1>
      <p class="dg-lead">Z3c — Zuordnung Mitarbeiter × Tag · <?= View::escape(
          (new DateTimeImmutable($monday))->format('d.m.Y') . ' – ' . (new DateTimeImmutable($weekEnd))->format('d.m.Y')
      ) ?></p>
    </div>
    <div class="dg-toolbar">
      <a class="dg-button" href="/app?page=zeiterfassung-schicht-vorlagen">Vorlagen</a>
      <a class="dg-button" href="/app?page=zeiterfassung-team">Team heute</a>
    </div>
  </header>

  <section class="dg-panel">
    <form method="get" action="/app" class="dg-form dg-form--inline">
      <input type="hidden" name="page" value="zeiterfassung-schichten">
      <label class="dg-field">
        <span class="dg-field-label">Woche (beliebiges Datum)</span>
        <input type="date" name="week" value="<?= View::escape($monday) ?>" required>
      </label>
      <button type="submit" class="dg-button">Anzeigen</button>
      <a class="dg-button" href="/app?page=zeiterfassung-schichten&amp;week=<?= View::escape($prev) ?>">←</a>
      <a class="dg-button" href="/app?page=zeiterfassung-schichten&amp;week=<?= View::escape($next) ?>">→</a>
    </form>
  </section>

  <?php if ($staff === []) : ?>
    <div class="dg-flash dg-flash--warning">Keine Mitarbeiter-Kontakte gefunden.</div>
  <?php elseif ($templates === []) : ?>
    <div class="dg-flash dg-flash--warning">
      Keine aktiven Schicht-Vorlagen.
      <a href="/app?page=zeiterfassung-schicht-vorlagen">Vorlagen anlegen</a>
    </div>
  <?php else : ?>
    <section class="dg-panel">
      <form method="post" action="/app?page=zeiterfassung-schichten" class="dg-form">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <input type="hidden" name="shift_plan_action" value="save_week">
        <input type="hidden" name="week_monday" value="<?= View::escape($monday) ?>">
        <div class="dg-table-wrap">
          <table class="dg-table">
            <thead>
              <tr>
                <th>Mitarbeiter</th>
                <?php foreach ($dates as $i => $d) : ?>
                  <th>
                    <?= View::escape($weekdayLabels[$i] ?? '') ?><br>
                    <span class="dg-muted"><?= View::escape((new DateTimeImmutable($d))->format('d.m.')) ?></span>
                  </th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($staff as $person) : ?>
                <?php $cid = (int) ($person['id'] ?? 0); ?>
                <tr>
                  <td><?= View::escape((string) ($person['label'] ?? '')) ?></td>
                  <?php foreach ($dates as $d) : ?>
                    <?php
                    $key = $cid . '|' . $d;
                    $current = (int) ($map[$key]['template_id'] ?? 0);
                    ?>
                    <td>
                      <select name="assignments[<?= $cid ?>][<?= View::escape($d) ?>]">
                        <option value="0">—</option>
                        <?php foreach ($templates as $tpl) : ?>
                          <option value="<?= (int) ($tpl['id'] ?? 0) ?>"<?= (int) ($tpl['id'] ?? 0) === $current ? ' selected' : '' ?>>
                            <?= View::escape((string) ($tpl['name'] ?? '')) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="dg-field-hint">Leer = keine Schicht. Speichern überschreibt die Woche für die sichtbaren Zellen.</p>
        <button type="submit" class="dg-button dg-button--primary">Woche speichern</button>
      </form>
    </section>
  <?php endif; ?>
</div>
