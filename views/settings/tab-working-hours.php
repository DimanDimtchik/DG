<?php
/**
 * @var list<array<string, mixed>> $calendarWorkingHours
 * @var bool $dbConnected
 */
$weekdayLabels = CalendarWorkingHoursRepository::weekdayLabels();
$timeOptions = CalendarWorkingHoursRepository::timeOptions();
$defaultDate = (new DateTimeImmutable('now'))->format('Y') . '-01-01';
$slotStep = CalendarWorkingHoursRepository::slotStepMinutes();
?>
<div class="dg-form">
  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Zum Speichern ist eine funktionierende <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Datenbankverbindung</a> erforderlich.
    </div>
  <?php endif; ?>

  <p class="dg-lead">
    Definieren Sie Öffnungs- und Buchungszeiten ab bestimmten Daten. Freie Termine werden nur an
    ausgewählten Wochentagen zwischen Start- und Endzeit angeboten (Zeitraster: <?= (int) $slotStep ?> Minuten).
  </p>

  <div class="dg-table-wrap">
    <table class="dg-table dg-table--compact">
      <thead>
        <tr>
          <th>Ab Datum</th>
          <th>Wochentage</th>
          <th>Startzeit</th>
          <th>Endzeit</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($calendarWorkingHours === []) : ?>
          <tr>
            <td colspan="5" class="dg-muted">Noch keine Arbeitszeiten hinterlegt.</td>
          </tr>
        <?php else : ?>
          <?php foreach ($calendarWorkingHours as $row) : ?>
            <tr>
              <td><?= View::escape((string) $row['start_date_label']) ?></td>
              <td><?= View::escape((string) $row['weekdays_label']) ?></td>
              <td><?= View::escape((string) $row['start_time_hm']) ?></td>
              <td><?= View::escape((string) $row['end_time_hm']) ?></td>
              <td>
                <form method="post" action="<?= View::escape(SettingsRegistry::tabUrl('arbeitszeiten')) ?>" class="dg-inline-form">
                  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                  <input type="hidden" name="working_hours_id" value="<?= (int) $row['id'] ?>">
                  <button type="submit" name="working_hours_delete" value="1" class="dg-button dg-button--danger dg-button--small"<?= !$dbConnected ? ' disabled' : '' ?>>Löschen</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <details class="dg-collapsible-form">
    <summary class="dg-subsection-title dg-collapsible-form__summary">Neue Arbeitszeit hinzufügen</summary>
    <div class="dg-collapsible-form__body">
  <form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('arbeitszeiten')) ?>">
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Ab Datum *</span>
        <input type="date" name="start_date" value="<?= View::escape($defaultDate) ?>" required<?= !$dbConnected ? ' disabled' : '' ?>>
        <small class="dg-field-hint">Gilt für alle Termine ab diesem Datum (z. B. <?= View::escape(substr($defaultDate, 0, 4)) ?>-01-01 für das ganze Jahr).</small>
      </label>
      <label class="dg-field">
        <span>Startzeit *</span>
        <select name="start_time" required<?= !$dbConnected ? ' disabled' : '' ?>>
          <?php foreach ($timeOptions as $time) : ?>
            <option value="<?= View::escape($time) ?>"<?= $time === '09:00' ? ' selected' : '' ?>><?= View::escape($time) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field">
        <span>Endzeit *</span>
        <select name="end_time" required<?= !$dbConnected ? ' disabled' : '' ?>>
          <?php foreach ($timeOptions as $time) : ?>
            <option value="<?= View::escape($time) ?>"<?= $time === '17:00' ? ' selected' : '' ?>><?= View::escape($time) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>

    <fieldset class="dg-field dg-field--wide dg-working-hours-weekdays">
      <legend>Wochentage *</legend>
      <div class="dg-working-hours-weekdays__grid">
        <?php foreach ($weekdayLabels as $num => $label) : ?>
          <label class="dg-working-hours-weekday">
            <input type="checkbox" name="weekdays[]" value="<?= (int) $num ?>"<?= $num <= 5 ? ' checked' : '' ?><?= !$dbConnected ? ' disabled' : '' ?>>
            <?= View::escape($label) ?>
          </label>
        <?php endforeach; ?>
      </div>
      <small class="dg-field-hint">Nur an angehakten Tagen werden Termine angeboten.</small>
    </fieldset>

    <div class="dg-form-actions">
      <button type="submit" name="working_hours_save" value="1" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>>Arbeitszeit hinzufügen</button>
    </div>
  </form>
    </div>
  </details>
</div>
