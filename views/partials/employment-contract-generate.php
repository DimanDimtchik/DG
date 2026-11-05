<?php
/** @var int|null $contactId */
/** @var array<string, string> $employeeData */
/** @var bool $showEmployeeFields */
/** @var bool $canEdit */
$contactId = (int) ($contactId ?? 0);
$employeeData = $employeeData ?? EmployeeData::empty();
$showEmployeeFields = !empty($showEmployeeFields);
$canEdit = !empty($canEdit);
if ($contactId <= 0 || !$showEmployeeFields || !$canEdit) {
    return;
}
$contractTypeGuess = EmploymentContractContext::resolveType($employeeData);
$contractTypeLabels = EmploymentContractContext::typeLabels();
$isMinijobGuess = $contractTypeGuess === EmploymentContractContext::TYPE_MINIJOB;
$hasTrainingVideo = trim((string) ($employeeData['training_video_url'] ?? '')) !== '';
$hasIfsgDuty = !empty($employeeData['food_hygiene_duty']);
?>
<form class="dg-form dg-panel" method="post" action="/app?page=kontakte&amp;action=edit&amp;id=<?= $contactId ?>">
  <h2>Arbeitsvertrag erzeugen</h2>
  <p class="dg-lead">Erzeugt ein PDF aus Stammdaten und Firmenangaben und legt es als Dokument „Arbeitsvertrag“ ab. Ein vorhandener Vertrag wird ersetzt. Bitte vor Unterzeichnung prüfen.</p>
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
  <input type="hidden" name="id" value="<?= $contactId ?>">
  <input type="hidden" name="contact_generate_employment_contract" value="1">
  <div class="dg-form-grid">
    <label class="dg-field">
      <span>Vertragsart</span>
      <select name="contract_type">
        <?php foreach ($contractTypeLabels as $typeKey => $typeLabel) : ?>
          <option value="<?= View::escape($typeKey) ?>"<?= $contractTypeGuess === $typeKey ? ' selected' : '' ?>><?= View::escape($typeLabel) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
  <label class="dg-field dg-field--checkbox dg-field--wide">
    <span><input type="checkbox" name="annex_training" value="1" checked> Anlage Kurzschulung</span>
  </label>
  <label class="dg-field dg-field--checkbox dg-field--wide">
    <span><input type="checkbox" name="annex_pension" value="1"<?= $isMinijobGuess ? ' checked' : '' ?>> Anlage Rentenversicherungsbefreiung (Minijob)</span>
  </label>
  <label class="dg-field dg-field--checkbox dg-field--wide">
    <span><input type="checkbox" name="annex_ifsg" value="1"<?= $hasIfsgDuty ? ' checked' : '' ?>> Anlage Infektionsschutz / Lebensmittelkontakt</span>
  </label>
  <label class="dg-field dg-field--checkbox dg-field--wide">
    <span><input type="checkbox" name="annex_video" value="1"<?= $hasTrainingVideo ? ' checked' : '' ?>> Schulungsvideo-Link in Kurzschulung aufnehmen</span>
  </label>
  <label class="dg-field dg-field--checkbox dg-field--wide">
    <span><input type="checkbox" name="force" value="1"> Trotzdem erzeugen (auch bei fehlenden Pflichtfeldern)</span>
  </label>
  <div class="dg-form-actions">
    <button type="submit" class="dg-button dg-button--primary">Vertrag als PDF erzeugen</button>
  </div>
</form>
