<?php
/**
 * Zeitraum-Filter für Buchhaltungsmodule.
 *
 * @var AccountingPeriodFilter $period
 * @var string $pageSlug
 * @var list<int> $years
 * @var array<string, string> $extraHidden
 */
$period = $period ?? AccountingPeriodFilter::fromRequest([]);
$pageSlug = (string) ($pageSlug ?? '');
$years = $years ?? [(int) date('Y')];
$extraHidden = is_array($extraHidden ?? null) ? $extraHidden : [];
$month = $period->month;
?>
<div class="dg-form-grid dg-form-grid--compact dg-period-filter">
  <label class="dg-field">
    <span>Jahr</span>
    <select name="year">
      <?php foreach ($years as $y) : ?>
        <option value="<?= (int) $y ?>"<?= $period->year === (int) $y ? ' selected' : '' ?>><?= (int) $y ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="dg-field">
    <span>Monat</span>
    <select name="month">
      <option value="0"<?= $month === null ? ' selected' : '' ?>>Ganzes Jahr</option>
      <?php for ($m = 1; $m <= 12; $m++) : ?>
        <option value="<?= $m ?>"<?= $month === $m ? ' selected' : '' ?>><?= sprintf('%02d', $m) ?></option>
      <?php endfor; ?>
    </select>
  </label>
  <label class="dg-field">
    <span>Von</span>
    <input type="date" name="date_from" value="<?= View::escape($period->isFullYear() && $month === null ? '' : $period->dateFrom) ?>">
  </label>
  <label class="dg-field">
    <span>Bis</span>
    <input type="date" name="date_to" value="<?= View::escape($period->isFullYear() && $month === null ? '' : $period->dateTo) ?>">
  </label>
  <?php foreach ($extraHidden as $name => $value) : ?>
    <input type="hidden" name="<?= View::escape((string) $name) ?>" value="<?= View::escape((string) $value) ?>">
  <?php endforeach; ?>
</div>
