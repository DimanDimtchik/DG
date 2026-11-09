<?php
/** @var array<string, string> $employeeData */
/** @var array<string, array<string, string>> $employeeFiles */
/** @var int|null $contactId */
/** @var bool $showEmployeeFields */
/** @var bool $canEdit */
/** @var list<array<string, string>> $bankAccounts */
/** @var list<array<string, mixed>> $payrollWageAssignments */
/** @var list<array<string, mixed>> $payrollWageTypeOptions */
/** @var string $employeeRenderMode personal|rest|all */
$employeeData = $employeeData ?? EmployeeData::empty();
$employeeFiles = $employeeFiles ?? ContactFileStorage::emptyFiles();
$showEmployeeFields = $showEmployeeFields ?? false;
$canEdit = $canEdit ?? false;
$bankAccounts = $bankAccounts ?? [];
$payrollWageAssignments = $payrollWageAssignments ?? [];
$payrollWageTypeOptions = $payrollWageTypeOptions ?? [];
$employeeRenderMode = (string) ($employeeRenderMode ?? 'all');
if (!in_array($employeeRenderMode, ['personal', 'rest', 'all'], true)) {
    $employeeRenderMode = 'all';
}
$authUser = class_exists('AuthService') ? AuthService::user() : null;
$canViewPayroll = $authUser instanceof User && PayrollEmployeeAccess::canView($authUser);
$canEditPayroll = $canEdit && $authUser instanceof User && PayrollEmployeeAccess::canEdit($authUser);
$payrollWarnings = $canViewPayroll ? EmployeeData::collectPayrollWarnings($employeeData) : [];
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
$kkFieldKeys = [
    'health_insurance',
    'health_insurance_number',
    'health_insurance_website',
    'health_insurance_appointment_url',
];

$renderSectionFields = static function (string $sectionKey, array $sectionFields) use (
    $employeeData,
    $showEmployeeFields,
    $canEditPayroll,
    $bankAccounts,
    $disabilitySupplementaryOptions
): void {
    if ($sectionFields === []) {
        return;
    }
    echo '<div class="dg-form-grid">';
    View::partial('partials/employee-data-field-inputs', compact(
        'sectionKey',
        'sectionFields',
        'employeeData',
        'showEmployeeFields',
        'canEditPayroll',
        'bankAccounts',
        'disabilitySupplementaryOptions'
    ));
    echo '</div>';
};

if ($employeeRenderMode === 'personal') {
    $personalFields = $grouped['personal'] ?? [];
    ?>
<div
  class="dg-employee-section"
  data-employee-section<?= $showEmployeeFields ? '' : ' hidden' ?>
>
  <?php if ($personalFields !== []) : ?>
    <p class="dg-lead">Persönliche Angaben für Mitarbeiterrollen.</p>
    <?php $renderSectionFields('personal', $personalFields); ?>
  <?php endif; ?>
</div>
    <?php
    return;
}
?>
<div
  class="dg-employee-section"
  data-employee-section<?= $showEmployeeFields ? '' : ' hidden' ?>
  data-filing-hints="<?= View::escape(json_encode($filingOfficeHints, JSON_UNESCAPED_UNICODE)) ?>"
  data-social-steps="<?= View::escape(json_encode($socialApplicationSteps, JSON_UNESCAPED_UNICODE)) ?>"
  data-sv-group-suggestions="<?= View::escape(json_encode(EmployeeData::svGroupKeySuggestionsByEmploymentType(), JSON_UNESCAPED_UNICODE)) ?>"
  data-sv-known-person="<?= View::escape(json_encode(EmployeeData::knownDefaultPersonGroupKeys(), JSON_UNESCAPED_UNICODE)) ?>"
  data-sv-known-contrib="<?= View::escape(json_encode(EmployeeData::knownDefaultContributionGroupKeys(), JSON_UNESCAPED_UNICODE)) ?>"
>
  <details class="dg-form-accordion">
    <summary>Mitarbeiterdaten</summary>
    <div class="dg-form-accordion__body">
      <p class="dg-lead">Vertrauliche Personalinformationen — nur für Rolle Mitarbeiter oder Administrator. Speicherdauer: 10 Jahre nach Austritt.</p>
      <?php if (($contactId ?? 0) > 0) : ?>
        <?php View::partial('partials/employee-document-list', compact('employeeFiles', 'contactId')); ?>
      <?php endif; ?>
    </div>
  </details>

  <?php
    $svDone = false;
    $contractDone = false;
    foreach ($grouped as $sectionKey => $sectionFields) :
        if ($sectionKey === 'personal' && $employeeRenderMode === 'rest') {
            continue;
        }
        if ($sectionKey === 'payroll' && !$canViewPayroll) {
            continue;
        }
        if (in_array($sectionKey, ['social', 'health', 'disability'], true)) {
            if ($svDone) {
                continue;
            }
            $svDone = true;
            $socialFields = $grouped['social'] ?? [];
            $healthFields = $grouped['health'] ?? [];
            $disabilityFields = $grouped['disability'] ?? [];
            $kkFields = [];
            $healthOnlyFields = [];
            foreach ($healthFields as $hk => $hm) {
                if (in_array($hk, $kkFieldKeys, true)) {
                    $kkFields[$hk] = $hm;
                } else {
                    $healthOnlyFields[$hk] = $hm;
                }
            }
            ?>
  <details class="dg-form-accordion">
    <summary>Sozialversicherung</summary>
    <div class="dg-form-accordion__body">
      <details class="dg-form-accordion dg-form-accordion--nested">
        <summary>Krankenkasse</summary>
        <div class="dg-form-accordion__body">
          <p class="dg-lead">
            <strong>Krankenkasse / Krankenversicherung</strong> (GKV oder PKV) und die
            <strong>Versichertennummer</strong> von der Versichertenkarte — mit Vorschlägen beim Tippen des Versicherernamens.
          </p>
          <?php $renderSectionFields('health', $kkFields); ?>
        </div>
      </details>

      <details class="dg-form-accordion dg-form-accordion--nested">
        <summary>Deutsche Rentenversicherung</summary>
        <div class="dg-form-accordion__body">
          <p class="dg-lead">
            Die <strong>Sozialversicherungsnummer</strong> ist eine einheitliche Nummer für alle Zweige.
            Fehlt sie, Status <strong>„Noch nicht vorhanden“</strong> oder <strong>„Beantragt“</strong> wählen,
            <strong>Meldestelle</strong> festlegen — die Anmeldung läuft dort (nicht direkt bei der DRV).
            Den Namen der Krankenkasse erfassen Sie unter <strong>Krankenkasse</strong>.
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
          <?php $renderSectionFields('social', $socialFields); ?>
        </div>
      </details>

      <details class="dg-form-accordion dg-form-accordion--nested">
        <summary>Behinderung</summary>
        <div class="dg-form-accordion__body">
          <p class="dg-lead">
            <strong>Grad der Behinderung</strong> und <strong>Zusatzbuchstaben</strong> wie auf dem Schwerbehindertenausweis.
            Bei chronischen Krankheiten, Arbeitsunfällen o. Ä. können zusätzlich <strong>ärztliche Atteste</strong> hochgeladen werden.
          </p>
          <?php $renderSectionFields('disability', $disabilityFields); ?>
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
        </div>
      </details>

      <details class="dg-form-accordion dg-form-accordion--nested">
        <summary>Gesundheitsdaten</summary>
        <div class="dg-form-accordion__body">
          <?php $renderSectionFields('health', $healthOnlyFields); ?>
        </div>
      </details>
    </div>
  </details>
            <?php
            continue;
        }

        if ($sectionKey === 'employment' || $sectionKey === 'contract') {
            if ($contractDone) {
                continue;
            }
            $contractDone = true;
            $employmentFields = $grouped['employment'] ?? [];
            $contractFields = $grouped['contract'] ?? [];
            ?>
  <details class="dg-form-accordion">
    <summary>Beschäftigungsdaten</summary>
    <div class="dg-form-accordion__body">
      <?php if ($employmentFields !== []) : ?>
        <h3 class="dg-subsection-title">Beschäftigung</h3>
        <?php $renderSectionFields('employment', $employmentFields); ?>
      <?php endif; ?>
      <?php if ($contractFields !== []) : ?>
        <h3 class="dg-subsection-title">Arbeitsvertragsdaten</h3>
        <?php $renderSectionFields('contract', $contractFields); ?>
      <?php endif; ?>
    </div>
  </details>
            <?php
            continue;
        }

        if ($sectionKey === 'payroll') {
            ?>
  <details class="dg-form-accordion">
    <summary>Lohnabrechnung</summary>
    <div class="dg-form-accordion__body">
      <p class="dg-lead">
        Stammdaten für die <strong>eigene Lohnabrechnung</strong>. Steuerklasse manuell — ELStAM folgt später.
        SV-Meldestelle und Krankenkassenname bleiben unter Sozialversicherung.
        Lohnarten-Zuordnung im eigenen Abschnitt; Freitext „Lohn / Gehalt“ unter Beschäftigungsdaten ist nur Hinweis.
        Kein Monatslauf in diesem Block.
      </p>
      <?php if (!$canEditPayroll) : ?>
        <p class="dg-field-hint">Nur Lesen — zum Speichern der Lohnfelder Admin-Recht bzw. Modul „Lohn &amp; Gehalt“ nötig.</p>
      <?php endif; ?>
      <?php if ($payrollWarnings !== []) : ?>
        <div class="dg-flash dg-flash--warning">
          <p><strong>Hinweise</strong> (Speichern bleibt möglich; Monatslauf später):</p>
          <ul>
            <?php foreach ($payrollWarnings as $w) : ?>
              <li><?= View::escape((string) $w) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      <?php $renderSectionFields('payroll', $sectionFields); ?>
    </div>
  </details>
  <details class="dg-form-accordion">
    <summary>Lohnarten-Zuordnung</summary>
    <div class="dg-form-accordion__body">
      <?php View::partial('partials/employee-data-wage-assignments', compact(
          'canEditPayroll',
          'payrollWageAssignments',
          'payrollWageTypeOptions'
      )); ?>
    </div>
  </details>
            <?php
            continue;
        }

        if ($employeeRenderMode === 'all' && $sectionKey === 'personal') {
            $title = $sections[$sectionKey] ?? $sectionKey;
        } else {
            $title = $sections[$sectionKey] ?? $sectionKey;
        }
        ?>
  <details class="dg-form-accordion">
    <summary><?= View::escape($title) ?></summary>
    <div class="dg-form-accordion__body">
      <?php $renderSectionFields($sectionKey, $sectionFields); ?>
    </div>
  </details>
    <?php endforeach; ?>

  <details class="dg-form-accordion">
    <summary><?= View::escape($sections['documents'] ?? 'Dokumente') ?></summary>
    <div class="dg-form-accordion__body">
      <p class="dg-lead">PDF, JPG, PNG oder WEBP — max. 10 MB pro Datei.</p>
      <div class="dg-form-grid">
        <?php foreach (EmployeeData::documentTypes() as $docType => $docLabel) : ?>
          <label class="dg-field dg-field--wide">
            <span><?= View::escape($docLabel) ?></span>
            <input type="file" name="employee_files[<?= View::escape($docType) ?>]" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*">
          </label>
        <?php endforeach; ?>
        <label class="dg-field dg-field--wide">
          <span>Externe Lohn-PDFs / payroll_slip (Upload)</span>
          <p class="dg-lead">
            Vom Steuerberater/Lohnbüro erhaltene PDF ablegen (Monat im Dateinamen empfohlen).
            Parallel erzeugt das CRM eigene Final-PDFs nach Sperre des Monatslaufs —
            siehe <a href="/app?page=lohn-lohnkonto">Lohnkonto</a> und Kontaktansicht „CRM-Abrechnungen“.
            Mehrere Dateien möglich; fremde Uploads werden nicht automatisch gelöscht.
          </p>
          <input type="file" name="employee_files[payroll_slip][]" multiple accept=".pdf,application/pdf">
        </label>
      </div>
    </div>
  </details>

  <details class="dg-form-accordion">
    <summary><?= View::escape($sections['retention'] ?? 'Speicherdauer') ?></summary>
    <div class="dg-form-accordion__body">
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
  </details>
</div>
