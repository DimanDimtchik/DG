<?php
/** @var array<string, string> $employeeData */
/** @var array<string, array<string, string>> $employeeFiles */
/** @var int|null $contactId */
/** @var bool $showEmployeeFields */
/** @var bool $canEdit */
$employeeData = $employeeData ?? EmployeeData::empty();
$employeeFiles = $employeeFiles ?? ContactFileStorage::emptyFiles();
$showEmployeeFields = $showEmployeeFields ?? false;
$canEdit = $canEdit ?? false;
$svDraftStatus = trim($employeeData['social_registration_status'] ?? SocialSecurityRegistrationDraft::STATUS_NONE);
$svDraftAt = trim($employeeData['social_registration_draft_at'] ?? '');
$hasSvDraft = trim($employeeData['social_registration_draft_json'] ?? '') !== '';
$sections = EmployeeData::sectionLabels();
$fields = EmployeeData::fields();
$grouped = [];
foreach ($fields as $key => $meta) {
    $grouped[$meta['section']][$key] = $meta;
}
$retentionUntil = EmployeeData::retentionUntil($employeeData);
$retentionStatus = EmployeeData::retentionStatus($employeeData);
$filingOfficeHints = [
    'kk_employee' => EmployeeData::filingOfficeHint('kk_employee'),
    'kbs_minijob' => EmployeeData::filingOfficeHint('kbs_minijob'),
    'kbs_sector' => EmployeeData::filingOfficeHint('kbs_sector'),
    'drv_regional' => EmployeeData::filingOfficeHint('drv_regional'),
];
$disabilitySupplementaryOptions = EmployeeData::disabilitySupplementaryOptions();
$socialApplicationSteps = EmployeeData::socialSecurityApplicationStepsByOffice();
?><div class="dg-employee-section" data-employee-section<?= $showEmployeeFields ? '' : ' hidden' ?> data-filing-hints="<?= View::escape(json_encode($filingOfficeHints, JSON_UNESCAPED_UNICODE)) ?>" data-social-steps="<?= View::escape(json_encode($socialApplicationSteps, JSON_UNESCAPED_UNICODE)) ?>">
  <h2>Mitarbeiterdaten</h2>
  <p class="dg-lead">Vertrauliche Personalinformationen — nur für Rolle Mitarbeiter oder Administrator. Speicherdauer: 10 Jahre nach Austritt.</p>
  <?php if (($contactId ?? 0) > 0) : ?>
    <?php View::partial('partials/employee-document-list', compact('employeeFiles', 'contactId')); ?>
  <?php endif; ?>

  <?php foreach ($grouped as $sectionKey => $sectionFields) : ?>
    <h3><?= View::escape($sections[$sectionKey] ?? $sectionKey) ?></h3>
    <?php if ($sectionKey === 'social') : ?>
      <p class="dg-lead">
        Die <strong>Sozialversicherungsnummer</strong> ist eine einheitliche Nummer für alle Zweige.
        Fehlt sie, Status <strong>„Noch nicht vorhanden“</strong> oder <strong>„Beantragt“</strong> wählen,
        <strong>Meldestelle</strong> festlegen — die Anmeldung läuft dort (nicht direkt bei der DRV).
        Den Namen der Krankenkasse erfassen Sie unter <strong>Gesundheitsdaten</strong>.
      </p>
      <p class="dg-lead dg-filing-hint" data-filing-hint hidden></p>
      <div class="dg-social-guide" data-social-guide hidden>
        <p class="dg-social-guide__title"><strong>SV-Nummer beantragen — Vorgehen:</strong></p>
        <ol class="dg-social-guide__steps" data-social-guide-steps></ol>
      </div>
      <?php if (($contactId ?? 0) > 0 && $hasSvDraft) : ?>
        <div class="dg-sv-draft">
          <p><strong>Anmeldungs-Entwurf:</strong> <?= View::escape(SocialSecurityRegistrationDraft::draftStatusLabel($svDraftStatus)) ?></p>
          <?php if ($svDraftAt !== '') : ?>
            <?php
              try {
                  $draftLabel = (new DateTimeImmutable($svDraftAt))->format('d.m.Y H:i');
              } catch (Throwable) {
                  $draftLabel = $svDraftAt;
              }
            ?>
            <p class="dg-lead">Zuletzt vorbereitet: <?= View::escape($draftLabel) ?> — nur im CRM, <strong>nicht versendet</strong>.</p>
          <?php endif; ?>
          <p class="dg-lead">
            <a class="dg-button dg-button--small" href="/app?page=kontakte&amp;action=sv-draft&amp;id=<?= (int) $contactId ?>">Entwurf als JSON herunterladen</a>
          </p>
        </div>
      <?php endif; ?>
      <?php if ($canEdit && ($contactId ?? 0) > 0) : ?>
        <div class="dg-sv-draft-actions">
          <button type="submit" name="prepare_sv_registration" value="1" class="dg-button">
            Anmeldung vorbereiten (nicht absenden)
          </button>
          <p class="dg-lead">Speichert die aktuellen Angaben und erstellt einen DEÜV-Anmeldungs-Entwurf im CRM. Es erfolgt <strong>keine</strong> Übermittlung an Krankenkasse oder Knappschaft.</p>
        </div>
      <?php endif; ?>
    <?php endif; ?>
    <?php if ($sectionKey === 'health') : ?>
      <p class="dg-lead">
        <strong>Krankenkasse / Krankenversicherung</strong> (GKV oder PKV) und die
        <strong>Versichertennummer</strong> von der Versichertenkarte — mit Vorschlägen beim Tippen des Versicherernamens.
      </p>
    <?php endif; ?>
    <?php if ($sectionKey === 'disability') : ?>
      <p class="dg-lead">
        <strong>Grad der Behinderung</strong> und <strong>Zusatzbuchstaben</strong> wie auf dem Schwerbehindertenausweis.
        Bei chronischen Krankheiten, Arbeitsunfällen o. Ä. können zusätzlich <strong>ärztliche Atteste</strong> hochgeladen werden.
      </p>
    <?php endif; ?>
    <div class="dg-form-grid">
      <?php foreach ($sectionFields as $key => $meta) : ?>
        <?php
          $name = 'employee[' . $key . ']';
          $value = $employeeData[$key] ?? '';
          $required = !empty($meta['required']) && $showEmployeeFields;
          if ($key === 'social_security_number' && ($employeeData['social_security_status'] ?? 'pending') !== 'received') {
              $required = false;
          }
          $selectOptions = $meta['type'] === 'select' ? EmployeeData::selectOptions($key) : [];
        ?>
        <?php if ($meta['type'] === 'textarea') : ?>
          <label class="dg-field dg-field--wide">
            <span><?= View::escape($meta['label']) ?><?= $required ? ' *' : '' ?></span>
            <textarea name="<?= View::escape($name) ?>" rows="3"<?= $required ? ' required data-employee-required' : '' ?>><?= View::escape($value) ?></textarea>
          </label>
        <?php elseif ($meta['type'] === 'select' && $selectOptions !== []) : ?>
          <label class="dg-field<?= $key === 'social_filing_office' ? ' dg-field--wide' : '' ?>">
            <span><?= View::escape($meta['label']) ?><?= $required ? ' *' : '' ?></span>
            <select
              name="<?= View::escape($name) ?>"
              <?php if ($key === 'social_security_status') : ?>data-social-status<?= $required ? ' required data-employee-required' : '' ?><?php endif; ?>
              <?php if ($key === 'social_filing_office') : ?>data-social-filing-office<?= $required ? ' required data-employee-required' : '' ?><?php endif; ?>
              <?php if ($key === 'gender') : ?><?= $required ? ' required data-employee-required' : '' ?><?php endif; ?>
            >
              <?php foreach ($selectOptions as $optVal => $optLabel) : ?>
                <option value="<?= View::escape($optVal) ?>"<?= $value === $optVal ? ' selected' : '' ?>><?= View::escape($optLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        <?php elseif ($key === 'social_security_number') : ?>
          <label class="dg-field">
            <span><?= View::escape($meta['label']) ?><?= $required ? ' *' : '' ?></span>
            <input
              type="text"
              name="<?= View::escape($name) ?>"
              value="<?= View::escape($value) ?>"
              data-social-sv-number
              placeholder="12-stellig, z. B. 12 123456 M 123"
              <?= $required ? ' required data-employee-required' : '' ?>
            >
          </label>
        <?php elseif ($key === 'health_insurance') : ?>
          <label class="dg-field">
            <span><?= View::escape($meta['label']) ?><?= $required ? ' *' : '' ?></span>
            <input
              type="text"
              name="<?= View::escape($name) ?>"
              value="<?= View::escape($value) ?>"
              data-health-insurer-name
              autocomplete="off"
              <?= $required ? ' required data-employee-required' : '' ?>
            >
            <div class="dg-health-insurer-suggest dg-bank-suggest" hidden></div>
          </label>
        <?php elseif ($key === 'health_insurance_number') : ?>
          <label class="dg-field">
            <span><?= View::escape($meta['label']) ?><?= $required ? ' *' : '' ?></span>
            <input
              type="text"
              name="<?= View::escape($name) ?>"
              value="<?= View::escape($value) ?>"
              placeholder="10-stellige Nummer von der Versichertenkarte"
              <?= $required ? ' required data-employee-required' : '' ?>
            >
          </label>
        <?php elseif ($key === 'disability_degree') : ?>
          <label class="dg-field">
            <span><?= View::escape($meta['label']) ?><?= $required ? ' *' : '' ?></span>
            <input
              type="number"
              name="<?= View::escape($name) ?>"
              value="<?= View::escape($value) ?>"
              min="0"
              max="100"
              step="1"
              inputmode="numeric"
              placeholder="z. B. 50"
              <?= $required ? ' required data-employee-required' : '' ?>
            >
          </label>
        <?php elseif ($key === 'disability_supplementary_codes') : ?>
          <label class="dg-field dg-field--wide">
            <span><?= View::escape($meta['label']) ?><?= $required ? ' *' : '' ?></span>
            <input
              type="text"
              name="<?= View::escape($name) ?>"
              value="<?= View::escape($value) ?>"
              data-disability-supplementary
              autocomplete="off"
              placeholder="z. B. G, H, Bl — mehrere durch Komma trennen"
              <?= $required ? ' required data-employee-required' : '' ?>
            >
            <div
              class="dg-disability-supplementary-suggest"
              data-disability-options="<?= View::escape(json_encode($disabilitySupplementaryOptions, JSON_UNESCAPED_UNICODE)) ?>"
            ></div>
          </label>
        <?php elseif ($meta['type'] === 'checkbox') : ?>
          <label class="dg-field dg-field--checkbox">
            <span>
              <input type="checkbox" name="<?= View::escape($name) ?>" value="1"<?= $value === '1' ? ' checked' : '' ?>>
              <?= View::escape($meta['label']) ?>
            </span>
          </label>
        <?php elseif ($meta['type'] === 'number') : ?>
          <label class="dg-field">
            <span><?= View::escape($meta['label']) ?><?= $required ? ' *' : '' ?></span>
            <input
              type="number"
              name="<?= View::escape($name) ?>"
              value="<?= View::escape($value) ?>"
              min="0"
              max="960"
              step="1"
              placeholder="z. B. 480 (= 8 h)"
              <?= $required ? ' required data-employee-required' : '' ?>
            >
          </label>
        <?php else : ?>
          <label class="dg-field">
            <span><?= View::escape($meta['label']) ?><?= $required ? ' *' : '' ?></span>
            <input type="<?= View::escape($meta['type']) ?>" name="<?= View::escape($name) ?>" value="<?= View::escape($value) ?>"<?= $required ? ' required data-employee-required' : '' ?>>
          </label>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <?php if ($sectionKey === 'disability') : ?>
      <div class="dg-form-grid">
        <?php foreach (EmployeeData::disabilityDocumentTypes() as $docType => $docLabel) : ?>
          <label class="dg-field dg-field--wide">
            <span><?= View::escape($docLabel) ?> (Upload)</span>
            <input type="file" name="employee_files[<?= View::escape($docType) ?>]" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*">
          </label>
        <?php endforeach; ?>
        <label class="dg-field dg-field--wide">
          <span>Ärztliche Atteste (Upload)</span>
          <p class="dg-lead">Chronische Krankheiten, Arbeitsunfall, Arbeitsunfähigkeit u. Ä. — mehrere Dateien möglich.</p>
          <input type="file" name="employee_files[medical_certificates][]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*">
        </label>
      </div>
    <?php endif; ?>
  <?php endforeach; ?>

  <h3><?= View::escape($sections['documents']) ?></h3>
  <p class="dg-lead">PDF, JPG, PNG oder WEBP — max. 10 MB pro Datei.</p>
  <div class="dg-form-grid">
    <?php foreach (EmployeeData::documentTypes() as $docType => $docLabel) : ?>
      <label class="dg-field dg-field--wide">
        <span><?= View::escape($docLabel) ?></span>
        <input type="file" name="employee_files[<?= View::escape($docType) ?>]" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*">
      </label>
    <?php endforeach; ?>
  </div>

  <h3><?= View::escape($sections['retention']) ?></h3>
  <div class="dg-retention dg-retention--<?= View::escape($retentionStatus['status']) ?>">
    <p class="dg-lead">
      Nach dem <strong>Austritt</strong> werden Mitarbeiterdaten noch <strong>10 Jahre</strong> aufbewahrt.
      Danach werden <strong>nur</strong> die Mitarbeiter-spezifischen Felder und Dokumente automatisch entfernt;
      Name, Adresse, Kontakt und Kundendaten bleiben erhalten. Die Rolle wird dann auf <strong>Kunde</strong> gesetzt.
      Die Bereinigung erfolgt automatisch in den ersten zwei Januarwochen (<?= View::escape(EmployeeRetentionService::purgeWindowLabel()) ?>),
      sobald ein Administrator oder Mitarbeiter das CRM nutzt (einmal pro Login-Sitzung).
    </p>
    <p><strong>Status:</strong> <?= View::escape($retentionStatus['label']) ?></p>
    <?php if ($retentionUntil !== null) : ?>
      <p><strong>Löschfrist:</strong> <?= View::escape($retentionUntil) ?></p>
    <?php elseif (trim($employeeData['exit_date'] ?? '') === '') : ?>
      <p>Bitte <strong>Austritt</strong> erfassen, sobald die Beschäftigung endet.</p>
    <?php endif; ?>
    <?php if ($retentionStatus['status'] === 'expired') : ?>
      <p class="dg-retention__warn">Löschfrist abgelaufen — in den ersten zwei Januarwochen (<?= View::escape(EmployeeRetentionService::purgeWindowLabel()) ?>) werden Mitarbeiterdaten beim CRM-Aufruf entfernt und die Rolle auf Kunde gesetzt.</p>
    <?php endif; ?>
  </div>
</div>
