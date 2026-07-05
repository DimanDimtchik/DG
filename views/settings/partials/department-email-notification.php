<?php

/**

 * @var string $deptId

 * @var array<string, mixed> $notificationTemplateData

 */

$departmentModes = $notificationTemplateData['department_modes'] ?? [];
$inheritSources = $notificationTemplateData['inherit_sources'] ?? [];

$modeLabels = NotificationTemplateSettings::modeLabels();

?>

<h4 class="dg-subsection-title">E-Mail-Vorlagen</h4>

<p class="dg-field-hint">

  Texte pflegen Sie unter <a href="<?= View::escape(SettingsRegistry::tabUrl('benachrichtigungen')) ?>">Benachrichtigungen</a>

  („+ Neue Vorlage“ → Abteilung wählen). Hier legen Sie nur fest, welche Vorlagen diese Abteilung nutzt.

</p>



<?php
  $modeRow = $departmentModes[$deptId] ?? ['mode' => NotificationTemplateSettings::MODE_STANDARD, 'inherit_from' => ''];
  $mode = (string) ($modeRow['mode'] ?? NotificationTemplateSettings::MODE_STANDARD);
  $inheritFrom = (string) ($modeRow['inherit_from'] ?? '');
?>

<fieldset class="dg-dept-email-area" data-dept-email-area>
  <legend class="dg-dept-email-area__legend">Abteilungsvorlagen</legend>
  <p class="dg-field-hint">Freie E-Mail-Vorlagen dieser Abteilung, ohne Trennung nach Kommunikation oder Buchhaltung.</p>

  <div class="dg-dept-email-area__modes">
    <?php foreach ($modeLabels as $modeKey => $modeLabel) : ?>
      <label class="dg-field">
        <span>
          <input
            type="radio"
            name="notification_department_modes[<?= View::escape($deptId) ?>][mode]"
            value="<?= View::escape($modeKey) ?>"
            data-dept-email-mode
            <?= $mode === $modeKey ? 'checked' : '' ?>
          >
          <?= View::escape($modeLabel) ?>
        </span>
      </label>
    <?php endforeach; ?>
  </div>

  <label class="dg-field dg-field--wide" data-dept-inherit-wrap<?= $mode === NotificationTemplateSettings::MODE_INHERIT ? '' : ' hidden' ?>>
    <span>Vorlagen übernehmen von</span>
    <select name="notification_department_modes[<?= View::escape($deptId) ?>][inherit_from]" data-dept-inherit-select>
      <option value="">— Bitte wählen —</option>
      <?php foreach ($inheritSources as $source) : ?>
        <?php if ($source['id'] === $deptId) { continue; } ?>
        <option value="<?= View::escape((string) $source['id']) ?>"<?= $inheritFrom === $source['id'] ? ' selected' : '' ?>><?= View::escape((string) $source['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
</fieldset>

