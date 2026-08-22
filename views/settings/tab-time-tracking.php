<?php
/**
 * @var array<string, mixed> $timeTrackingSettings
 * @var bool $dbConnected
 */
$settings = $timeTrackingSettings ?? TimeTrackingSettings::forForm();
?>
<form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('zeiterfassung')) ?>">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Zum Speichern ist eine funktionierende <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Datenbankverbindung</a> erforderlich.
    </div>
  <?php endif; ?>

  <section class="dg-form-section">
    <h3 class="dg-subsection-title">Automatische Pause (ArbZG)</h3>
    <p class="dg-field-hint">
      Wenn keine ausreichende manuelle Pause erfasst wurde, wird beim Tagesauszug die gesetzliche Mindestpause abgezogen
      (30&nbsp;min ab 6&nbsp;h, 45&nbsp;min ab 9&nbsp;h Arbeitszeit — Standard).
    </p>

    <label class="dg-field dg-field--checkbox">
      <span>
        <input type="checkbox" name="auto_break_enabled" value="1"<?= !empty($settings['auto_break_enabled']) ? ' checked' : '' ?>>
        Automatische Pause bei Tagesberechnung anwenden
      </span>
    </label>

    <label class="dg-field dg-field--checkbox">
      <span>
        <input type="checkbox" name="force_break_before_clock_out" value="1"<?= !empty($settings['force_break_before_clock_out']) ? ' checked' : '' ?>>
        Zwangspause: Ausstempeln nur nach ausreichender manueller Pause
      </span>
    </label>

    <label class="dg-field dg-field--checkbox">
      <span>
        <input type="checkbox" name="auto_close_open_days" value="1"<?= !empty($settings['auto_close_open_days']) ? ' checked' : '' ?>>
        Offene Tage automatisch schließen (vergessen auszustempeln — Autostart beim ersten Request)
      </span>
    </label>

    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Schwelle 6&nbsp;h (Minuten Arbeitszeit)</span>
        <input type="number" name="break_threshold_6h_minutes" min="60" max="720" value="<?= (int) ($settings['break_threshold_6h_minutes'] ?? 360) ?>">
      </label>
      <label class="dg-field">
        <span>Pause ab 6&nbsp;h (Minuten)</span>
        <input type="number" name="break_after_6h_minutes" min="0" max="120" value="<?= (int) ($settings['break_after_6h_minutes'] ?? 30) ?>">
      </label>
      <label class="dg-field">
        <span>Schwelle 9&nbsp;h (Minuten Arbeitszeit)</span>
        <input type="number" name="break_threshold_9h_minutes" min="60" max="960" value="<?= (int) ($settings['break_threshold_9h_minutes'] ?? 540) ?>">
      </label>
      <label class="dg-field">
        <span>Pause ab 9&nbsp;h (Minuten)</span>
        <input type="number" name="break_after_9h_minutes" min="0" max="120" value="<?= (int) ($settings['break_after_9h_minutes'] ?? 45) ?>">
      </label>
    </div>
  </section>

  <div class="dg-form-actions">
    <button type="submit" name="time_tracking_save" value="1" class="dg-button dg-button--primary"<?= $dbConnected ? '' : ' disabled' ?>>Speichern</button>
  </div>
</form>
