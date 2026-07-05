<?php
/** @var int $i */
/** @var array<string, mixed> $employee */
/** @var list<array{id: int, label: string}> $personContactOptions */
?>
<div class="dg-company-employee-card" data-company-employee-card>
  <div class="dg-company-employee-card__head">
    <p class="dg-company-employee-card__title" data-company-employee-title>Mitarbeiter <?= (int) $i + 1 ?></p>
    <button type="button" class="dg-button dg-button--small" data-company-employee-remove>Entfernen</button>
  </div>
  <div class="dg-form-grid">
    <label class="dg-field dg-field--wide">
      <span>Person aus Kontakten *</span>
      <select name="company_employees[<?= View::escape((string) $i) ?>][person_contact_id]" data-company-employee-person>
        <option value="">— Person wählen —</option>
        <?php foreach ($personContactOptions as $option) : ?>
          <option
            value="<?= (int) $option['id'] ?>"
            <?= (int) ($employee['person_contact_id'] ?? 0) === (int) $option['id'] ? ' selected' : '' ?>
          ><?= View::escape((string) $option['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="dg-field dg-field--wide">
      <span>Zuständigkeitsbereich</span>
      <input
        name="company_employees[<?= View::escape((string) $i) ?>][responsibility]"
        value="<?= View::escape((string) ($employee['responsibility'] ?? '')) ?>"
        placeholder="z. B. Buchhaltung, Lohn, Steuererklärung"
      >
    </label>
    <label class="dg-field">
      <span>E-Mail (dienstlich)</span>
      <input
        type="email"
        name="company_employees[<?= View::escape((string) $i) ?>][work_email]"
        value="<?= View::escape((string) ($employee['work_email'] ?? '')) ?>"
      >
    </label>
    <label class="dg-field">
      <span>Telefon (dienstlich)</span>
      <input
        name="company_employees[<?= View::escape((string) $i) ?>][work_phone]"
        value="<?= View::escape((string) ($employee['work_phone'] ?? '')) ?>"
      >
    </label>
    <label class="dg-field dg-field--wide">
      <span>Erreichbarkeit / Öffnungszeiten</span>
      <input
        name="company_employees[<?= View::escape((string) $i) ?>][availability]"
        value="<?= View::escape((string) ($employee['availability'] ?? '')) ?>"
        placeholder="z. B. Mo–Do 9–17 Uhr, Fr nur Vormittag"
      >
    </label>
  </div>
</div>
