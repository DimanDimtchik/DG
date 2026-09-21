<?php
/**
 * @var array<string, mixed>|null $timeMonthReport
 * @var string $timeMonthYearMonth
 * @var int|null $timeMonthContactId
 * @var bool $timeMonthCanTeam
 * @var list<array{id: int, label: string}> $timeMonthStaffOptions
 * @var array{type: string, message: string}|null $flash
 */
$report = $timeMonthReport ?? null;
$yearMonth = (string) ($timeMonthYearMonth ?? date('Y-m'));
$contactId = (int) ($timeMonthContactId ?? 0);
$canTeam = !empty($timeMonthCanTeam);
$staffOptions = is_array($timeMonthStaffOptions ?? null) ? $timeMonthStaffOptions : [];
$totals = is_array($report['totals'] ?? null) ? $report['totals'] : [];
$days = is_array($report['days'] ?? null) ? $report['days'] : [];
$label = (string) ($report['contact_label'] ?? '');
$prev = (new DateTimeImmutable($yearMonth . '-01'))->modify('-1 month')->format('Y-m');
$next = (new DateTimeImmutable($yearMonth . '-01'))->modify('+1 month')->format('Y-m');
$baseQs = 'page=zeiterfassung-monat&month=' . rawurlencode($yearMonth);
if ($contactId > 0) {
    $baseQs .= '&contact_id=' . $contactId;
}
?>
<div class="dg-wrap dg-zeiterfassung-monat">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Monatsblatt</h1>
      <p class="dg-lead">Soll / Ist / Differenz<?= $label !== '' ? ' — ' . View::escape($label) : '' ?></p>
    </div>
    <div class="dg-toolbar">
      <a class="dg-button" href="/app?page=zeiterfassung">Stempeluhr</a>
      <?php if ($canTeam) : ?>
        <a class="dg-button" href="/app?page=zeiterfassung-team">Team heute</a>
        <a class="dg-button" href="/app?page=zeiterfassung-konto<?= $contactId > 0 ? '&amp;contact_id=' . $contactId : '' ?>">Korrektur / Konto</a>
      <?php endif; ?>
      <?php if ($contactId > 0) : ?>
        <a class="dg-button dg-button--primary" href="/app?<?= View::escape($baseQs) ?>&amp;download=csv">CSV exportieren</a>
      <?php endif; ?>
    </div>
  </header>

  <section class="dg-panel">
    <form method="get" action="/app" class="dg-form dg-form--inline">
      <input type="hidden" name="page" value="zeiterfassung-monat">
      <label class="dg-field">
        <span class="dg-field-label">Monat</span>
        <input type="month" name="month" value="<?= View::escape($yearMonth) ?>" required>
      </label>
      <?php if ($canTeam && $staffOptions !== []) : ?>
        <label class="dg-field">
          <span class="dg-field-label">Mitarbeiter</span>
          <select name="contact_id">
            <?php foreach ($staffOptions as $opt) : ?>
              <option value="<?= (int) $opt['id'] ?>"<?= (int) $opt['id'] === $contactId ? ' selected' : '' ?>>
                <?= View::escape((string) $opt['label']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
      <?php endif; ?>
      <button type="submit" class="dg-button">Anzeigen</button>
      <a class="dg-button" href="/app?page=zeiterfassung-monat&amp;month=<?= View::escape($prev) ?><?= $contactId > 0 ? '&amp;contact_id=' . $contactId : '' ?>">←</a>
      <a class="dg-button" href="/app?page=zeiterfassung-monat&amp;month=<?= View::escape($next) ?><?= $contactId > 0 ? '&amp;contact_id=' . $contactId : '' ?>">→</a>
    </form>
  </section>

  <?php if ($contactId < 1) : ?>
    <div class="dg-flash dg-flash--warning">
      Kein Mitarbeiter-Kontakt verknüpft. Bitte in <a href="/app?page=kontakte">Kontakte</a> zuordnen.
    </div>
  <?php elseif ($report === null) : ?>
    <p class="dg-muted">Kein Bericht verfügbar.</p>
  <?php else : ?>
    <section class="dg-panel">
      <p>
        <strong>Summe Soll:</strong> <?= View::escape((string) ($totals['scheduled_display'] ?? '0:00')) ?> h ·
        <strong>Ist:</strong> <?= View::escape((string) ($totals['worked_display'] ?? '0:00')) ?> h ·
        <strong>Diff:</strong> <?= View::escape((string) ($totals['diff_display'] ?? '0:00')) ?> h ·
        <strong>Überstunden:</strong> <?= View::escape((string) ($totals['overtime_display'] ?? '0:00')) ?> h
      </p>
      <div class="dg-table-wrap">
        <table class="dg-table">
          <thead>
            <tr>
              <th>Datum</th>
              <th>Tag</th>
              <th class="dg-table__num">Soll</th>
              <th class="dg-table__num">Ist</th>
              <th class="dg-table__num">Pause</th>
              <th class="dg-table__num">Diff</th>
              <th class="dg-table__num">ÜStd</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($days as $day) : ?>
              <?php
              $empty = ($day['source'] ?? '') === 'empty'
                  && (int) ($day['scheduled_minutes'] ?? 0) === 0
                  && (int) ($day['worked_minutes'] ?? 0) === 0;
              ?>
              <tr<?= $empty ? ' class="dg-muted"' : '' ?>>
                <td><?= View::escape((string) ($day['date_display'] ?? '')) ?></td>
                <td><?= View::escape((string) ($day['weekday'] ?? '')) ?></td>
                <td class="dg-table__num"><?= View::escape((string) ($day['scheduled_display'] ?? '0:00')) ?></td>
                <td class="dg-table__num"><?= View::escape((string) ($day['worked_display'] ?? '0:00')) ?></td>
                <td class="dg-table__num"><?= View::escape((string) ($day['break_display'] ?? '0:00')) ?></td>
                <td class="dg-table__num"><?= View::escape((string) ($day['diff_display'] ?? '0:00')) ?></td>
                <td class="dg-table__num"><?= View::escape((string) ($day['overtime_display'] ?? '0:00')) ?></td>
              </tr>
              <?php if (!empty($day['arbzg_flags']) && is_array($day['arbzg_flags'])) : ?>
                <tr>
                  <td colspan="7" class="dg-muted"><?= View::escape(implode(' · ', $day['arbzg_flags'])) ?></td>
                </tr>
              <?php endif; ?>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <th colspan="2">Summe</th>
              <th class="dg-table__num"><?= View::escape((string) ($totals['scheduled_display'] ?? '0:00')) ?></th>
              <th class="dg-table__num"><?= View::escape((string) ($totals['worked_display'] ?? '0:00')) ?></th>
              <th class="dg-table__num"><?= View::escape((string) ($totals['break_display'] ?? '0:00')) ?></th>
              <th class="dg-table__num"><?= View::escape((string) ($totals['diff_display'] ?? '0:00')) ?></th>
              <th class="dg-table__num"><?= View::escape((string) ($totals['overtime_display'] ?? '0:00')) ?></th>
            </tr>
          </tfoot>
        </table>
      </div>
      <p class="dg-field-hint">Quelle: aggregierte Tage (`dg_time_work_days`) oder Live-Stempel. CSV = ArbZG-Nachweis, kein DATEV-Lohn.</p>
    </section>
  <?php endif; ?>
</div>
