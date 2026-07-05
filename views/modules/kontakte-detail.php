<?php
/** @var User $user */
/** @var Contact $contact */
?>
<div class="dg-wrap">
  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <?php View::partial('partials/back-nav', [
          'href' => '/app?page=kontakte',
          'label' => 'Zurück zur Liste',
      ]); ?>
      <h1 class="dg-page-title"><?= View::escape($contact->listLabel()) ?></h1>
      <p class="dg-lead">
        <?= View::escape($contact->salutation !== '' ? $contact->salutation : 'Kontakt') ?>
        · <?= View::escape($contact->roleLabel()) ?>
        · <?= View::escape($contact->login) ?>
      </p>
    </div>
    <div class="dg-toolbar">
      <?php if (ContactAccessResolver::canEditContact($user, $contact)) : ?>
        <a class="dg-button" href="/app?page=kontakte&amp;action=edit&amp;id=<?= $contact->id ?>">Bearbeiten</a>
      <?php endif; ?>
    </div>
  </header>

  <div class="dg-detail-grid">
    <section class="dg-panel">
      <h2>Stamm / Firma</h2>
      <dl class="dg-dl">
        <dt>Firmenname</dt>
        <dd><?= View::escape($contact->companyName !== '' ? $contact->companyName : '—') ?></dd>
        <dt>Anrede</dt>
        <dd><?= View::escape($contact->salutation !== '' ? $contact->salutation : '—') ?></dd>
        <dt>Vorname</dt>
        <dd><?= View::escape($contact->firstName !== '' ? $contact->firstName : '—') ?></dd>
        <dt>Nachname</dt>
        <dd><?= View::escape($contact->lastName !== '' ? $contact->lastName : '—') ?></dd>
        <dt>Steuernummer</dt>
        <dd><?= View::escape($contact->taxNumber !== '' ? $contact->taxNumber : '—') ?></dd>
        <dt>USt-IdNr.</dt>
        <dd><?= View::escape($contact->vatId !== '' ? $contact->vatId : '—') ?></dd>
      </dl>
    </section>

    <section class="dg-panel">
      <h2>Kunde / Lieferant</h2>
      <dl class="dg-dl">
        <dt>Kundennummer</dt>
        <dd><?= View::escape($contact->customerNumber !== '' ? $contact->customerNumber : '—') ?></dd>
        <dt>Lieferantennummer</dt>
        <dd><?= View::escape($contact->supplierNumber !== '' ? $contact->supplierNumber : '—') ?></dd>
      </dl>
    </section>

    <section class="dg-panel">
      <h2>Kommunikation</h2>
      <dl class="dg-dl">
        <dt>E-Mail</dt>
        <dd>
          <?php if ($contact->email !== '') : ?>
            <a href="mailto:<?= View::escape($contact->email) ?>"><?= View::escape($contact->email) ?></a>
          <?php else : ?>
            —
          <?php endif; ?>
        </dd>
        <dt>E-Mail 2</dt>
        <dd><?= View::escape($contact->email2 !== '' ? $contact->email2 : '—') ?></dd>
        <dt>Telefon 1</dt>
        <dd><?= View::escape($contact->phone1 !== '' ? $contact->phone1 : '—') ?></dd>
        <dt>Telefon 2</dt>
        <dd><?= View::escape($contact->phone2 !== '' ? $contact->phone2 : '—') ?></dd>
        <dt>Website</dt>
        <dd>
          <?php if ($contact->website !== '') : ?>
            <a href="<?= View::escape($contact->website) ?>" target="_blank" rel="noopener"><?= View::escape($contact->website) ?></a>
          <?php else : ?>
            —
          <?php endif; ?>
        </dd>
      </dl>
    </section>

    <section class="dg-panel">
      <h2>Adresse</h2>
      <dl class="dg-dl">
        <dt>Adresse 1</dt>
        <dd><?= View::escape($contact->addressLine1() !== '' ? $contact->addressLine1() : '—') ?></dd>
        <dt>Adresse 2</dt>
        <dd><?= View::escape($contact->addressLine2() !== '' ? $contact->addressLine2() : '—') ?></dd>
      </dl>
    </section>

    <?php if (!empty($showEmployeeFields) && CrmRole::hasEmployeeProfile($contact->contactRole)) : ?>
      <?php
        $employeeDetailFields = EmployeeData::detailFields($contact->employeeData);
        $retentionUntil = EmployeeData::retentionUntil($contact->employeeData);
        $retentionStatus = EmployeeData::retentionStatus($contact->employeeData);
      ?>
      <?php if ($retentionStatus['status'] !== 'open') : ?>
        <div class="dg-retention dg-retention--<?= View::escape($retentionStatus['status']) ?>" style="margin-bottom:16px">
          <p><strong>Aufbewahrung:</strong> <?= View::escape($retentionStatus['label']) ?></p>
          <?php if ($retentionUntil !== null) : ?>
            <p><strong>Löschfrist (10 Jahre nach Austritt):</strong> <?= View::escape($retentionUntil) ?></p>
          <?php endif; ?>
          <?php if ($retentionStatus['status'] === 'expired') : ?>
            <p class="dg-retention__warn">Löschfrist abgelaufen — Bereinigung in den ersten zwei Januarwochen (<?= View::escape(EmployeeRetentionService::purgeWindowLabel()) ?>) beim CRM-Aufruf.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <?php
        $svNumber = trim($contact->employeeData['social_security_number'] ?? '');
        $svStatus = $contact->employeeData['social_security_status'] ?? 'pending';
        $svOffice = $contact->employeeData['social_filing_office'] ?? '';
        $svApplicationSteps = $svNumber === '' && $svStatus !== 'received'
            ? EmployeeData::socialSecurityApplicationSteps($svOffice, $svStatus)
            : [];
      ?>
      <?php if ($svApplicationSteps !== []) : ?>
        <div class="dg-social-guide dg-social-guide--detail">
          <p class="dg-social-guide__title"><strong>SV-Nummer beantragen — Vorgehen:</strong></p>
          <ol class="dg-social-guide__steps">
            <?php foreach ($svApplicationSteps as $step) : ?>
              <li><?= View::escape($step) ?></li>
            <?php endforeach; ?>
          </ol>
        </div>
      <?php endif; ?>
      <?php
        $svDraftStatus = trim($contact->employeeData['social_registration_status'] ?? SocialSecurityRegistrationDraft::STATUS_NONE);
        $svDraftAt = trim($contact->employeeData['social_registration_draft_at'] ?? '');
        $hasSvDraft = trim($contact->employeeData['social_registration_draft_json'] ?? '') !== '';
      ?>
      <?php if ($hasSvDraft) : ?>
        <div class="dg-sv-draft dg-sv-draft--detail">
          <p><strong>SV-Anmeldung (Entwurf):</strong> <?= View::escape(SocialSecurityRegistrationDraft::draftStatusLabel($svDraftStatus)) ?></p>
          <?php if ($svDraftAt !== '') : ?>
            <?php
              try {
                  $draftLabel = (new DateTimeImmutable($svDraftAt))->format('d.m.Y H:i');
              } catch (Throwable) {
                  $draftLabel = $svDraftAt;
              }
            ?>
            <p>Vorbereitet am <?= View::escape($draftLabel) ?> — <strong>nicht versendet</strong>.</p>
          <?php endif; ?>
          <p><a href="/app?page=kontakte&amp;action=sv-draft&amp;id=<?= (int) $contact->id ?>">Entwurf als JSON herunterladen</a></p>
        </div>
      <?php endif; ?>
      <section class="dg-panel dg-panel--wide">
        <h2>Mitarbeiterdaten</h2>
        <?php
          $healthInsurance = trim($contact->employeeData['health_insurance'] ?? '');
          $healthInsuranceNumber = trim($contact->employeeData['health_insurance_number'] ?? '');
        ?>
        <h3 class="dg-subsection-title">Krankenversicherung</h3>
        <dl class="dg-dl">
          <dt>Krankenkasse / Krankenversicherung</dt>
          <dd><?= $healthInsurance !== '' ? View::escape($healthInsurance) : '—' ?></dd>
          <dt>Versichertennummer</dt>
          <dd><?= $healthInsuranceNumber !== '' ? View::escape($healthInsuranceNumber) : '—' ?></dd>
        </dl>
        <?php
          $otherDetailFields = array_values(array_filter(
              $employeeDetailFields,
              static fn(array $field): bool => !in_array($field['label'], [
                  'Krankenkasse / Krankenversicherung',
                  'Versichertennummer',
                  'Krankenversicherungsnummer (Versichertennr.)',
                  'Krankenversicherungsnummer',
                  'Krankenkasse',
              ], true)
          ));
        ?>
        <?php if ($otherDetailFields !== []) : ?>
          <h3 class="dg-subsection-title">Weitere Angaben</h3>
          <dl class="dg-dl">
            <?php foreach ($otherDetailFields as $field) : ?>
              <dt><?= View::escape($field['label']) ?></dt>
              <dd><?= nl2br(View::escape($field['value'])) ?></dd>
            <?php endforeach; ?>
          </dl>
        <?php elseif ($healthInsurance === '' && $healthInsuranceNumber === '') : ?>
          <p class="dg-lead">Noch keine weiteren Mitarbeiterdaten erfasst.</p>
        <?php endif; ?>
      </section>

      <?php if (EmployeeDocuments::hasUploaded($contact->employeeFiles)) : ?>
        <section class="dg-panel">
          <h2>Dokumente</h2>
          <?php View::partial('partials/employee-document-list', [
              'employeeFiles' => $contact->employeeFiles,
              'contactId' => $contact->id,
              'compact' => false,
          ]); ?>
        </section>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($contact->bankAccounts !== []) : ?>
      <section class="dg-panel dg-panel--wide">
        <h2>Bankverbindung</h2>
        <?php foreach ($contact->bankAccounts as $i => $account) : ?>
          <?php
            $typeLabel = BankAccountTypes::label((string) ($account['type'] ?? 'giro'));
            $title = trim((string) ($account['label'] ?? ''));
            if ($title === '') {
                $title = $typeLabel . ' #' . ((int) $i + 1);
            }
            $detailFields = BankAccountTypes::detailFields($account);
          ?>
          <div class="dg-bank-card" style="margin-bottom:12px">
            <p class="dg-bank-card__title"><?= View::escape($title) ?></p>
            <dl class="dg-dl">
              <dt>Typ</dt>
              <dd><?= View::escape($typeLabel) ?></dd>
              <?php foreach ($detailFields as $field) : ?>
                <dt><?= View::escape($field['label']) ?></dt>
                <dd>
                  <?php if ($field['kind'] === 'email') : ?>
                    <a href="mailto:<?= View::escape($field['value']) ?>"><?= View::escape($field['value']) ?></a>
                  <?php else : ?>
                    <?= View::escape($field['value']) ?>
                  <?php endif; ?>
                </dd>
              <?php endforeach; ?>
            </dl>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <?php if ($contact->socialLinks() !== []) : ?>
      <section class="dg-panel">
        <h2>Soziale Medien</h2>
        <dl class="dg-dl">
          <?php foreach ($contact->socialLinks() as $link) : ?>
            <dt><?= View::escape($link['label']) ?></dt>
            <dd><a href="<?= View::escape($link['url']) ?>" target="_blank" rel="noopener"><?= View::escape($link['url']) ?></a></dd>
          <?php endforeach; ?>
        </dl>
      </section>
    <?php endif; ?>

    <?php if ($contact->isCompany() && $contact->contactPersons !== []) : ?>
      <section class="dg-panel dg-panel--wide dg-panel--muted">
        <h2>Veraltete Ansprechpartner (nur Demo-Daten)</h2>
        <p class="dg-field-hint">Diese eingebetteten Einträge stammen aus älteren Demo-Daten. Bitte Personen unter „Mitarbeiter der Firma“ verknüpfen.</p>
        <div class="dg-table-wrap">
          <table class="dg-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Abteilung</th>
                <th>Zuständigkeit</th>
                <th>E-Mail</th>
                <th>Telefon</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($contact->contactPersons as $person) : ?>
                <tr>
                  <td>
                    <?= View::escape(trim(
                        ($person['salutation'] ?? '') . ' ' . ($person['first_name'] ?? '') . ' ' . ($person['last_name'] ?? '')
                    )) ?>
                  </td>
                  <td><?= View::escape((string) ($person['department'] ?? '')) ?></td>
                  <td><?= View::escape((string) ($person['responsibility'] ?? '')) ?></td>
                  <td><?= View::escape((string) ($person['email'] ?? '')) ?></td>
                  <td><?= View::escape((string) ($person['phone'] ?? '')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>

    <?php
      $companyEmployees = $companyEmployees ?? ($contact->isCompany()
          ? ContactCompanyLinkRepository::employeesForCompany($contact->id)
          : []);
      $employerLink = $employerLink ?? ($contact->isCompany()
          ? null
          : ContactCompanyLinkRepository::employerForPerson($contact->id));
    ?>
    <?php if ($contact->isCompany()) : ?>
      <section class="dg-panel dg-panel--wide">
        <h2>Mitarbeiter der Firma</h2>
        <?php if ($companyEmployees === []) : ?>
          <p class="dg-field-hint">Noch keine verknüpften Personen. Bearbeiten Sie den Kontakt, um Mitarbeiter zuzuordnen.</p>
        <?php else : ?>
          <div class="dg-table-wrap">
            <table class="dg-table">
              <thead>
                <tr>
                  <th>Person</th>
                  <th>Zuständigkeit</th>
                  <th>E-Mail (dienstlich)</th>
                  <th>Telefon (dienstlich)</th>
                  <th>Erreichbarkeit</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($companyEmployees as $employee) : ?>
                  <tr>
                    <td>
                      <a href="/app?page=kontakte&amp;id=<?= (int) $employee['person_contact_id'] ?>">
                        <?= View::escape((string) $employee['person_label']) ?>
                      </a>
                    </td>
                    <td><?= View::escape((string) ($employee['responsibility'] !== '' ? $employee['responsibility'] : '—')) ?></td>
                    <td><?= View::escape((string) ($employee['work_email'] !== '' ? $employee['work_email'] : '—')) ?></td>
                    <td><?= View::escape((string) ($employee['work_phone'] !== '' ? $employee['work_phone'] : '—')) ?></td>
                    <td><?= View::escape((string) ($employee['availability'] !== '' ? $employee['availability'] : '—')) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    <?php elseif ($employerLink !== null) : ?>
      <section class="dg-panel">
        <h2>Arbeitgeber / Firma</h2>
        <dl class="dg-dl">
          <dt>Firma</dt>
          <dd>
            <a href="/app?page=kontakte&amp;id=<?= (int) $employerLink['company_contact_id'] ?>">
              <?= View::escape((string) $employerLink['company_label']) ?>
            </a>
          </dd>
          <dt>Zuständigkeitsbereich</dt>
          <dd><?= View::escape((string) ($employerLink['responsibility'] !== '' ? $employerLink['responsibility'] : '—')) ?></dd>
          <dt>E-Mail (dienstlich)</dt>
          <dd><?= View::escape((string) ($employerLink['work_email'] !== '' ? $employerLink['work_email'] : '—')) ?></dd>
          <dt>Telefon (dienstlich)</dt>
          <dd><?= View::escape((string) ($employerLink['work_phone'] !== '' ? $employerLink['work_phone'] : '—')) ?></dd>
          <dt>Erreichbarkeit / Öffnungszeiten</dt>
          <dd><?= View::escape((string) ($employerLink['availability'] !== '' ? $employerLink['availability'] : '—')) ?></dd>
        </dl>
      </section>
    <?php endif; ?>
  </div>
</div>
