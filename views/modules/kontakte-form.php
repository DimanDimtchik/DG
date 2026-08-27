<?php
/** @var array<string, string> $form */
/** @var int|null $contactId */
/** @var string|null $formError */
$isEdit = ($contactId ?? 0) > 0;
$form = array_merge(ContactRepository::emptyForm(), $form ?? []);
$selectedRole = CrmRole::normalize($form['contact_role'] ?? 'dg_kunde');
$allowedContactRoles = $allowedContactRoles ?? CrmRole::options();
$showEmployeeFields = ($showEmployeeFields ?? false)
    && CrmRole::hasEmployeeProfile($selectedRole);
$employeeData = $employeeData ?? EmployeeData::empty();
$employeeFiles = $employeeFiles ?? ContactFileStorage::emptyFiles();
$bankAccounts = $bankAccounts ?? ContactRepository::defaultBankAccounts();
$bankTypes = ContactRepository::bankTypes();
$socialFields = ContactRepository::socialFormKeys();
$companyEmployees = $companyEmployees ?? [];
$employerForm = array_merge(ContactCompanyLinkRepository::emptyEmployerForm(), $employerForm ?? []);
$companyContactOptions = $companyContactOptions ?? ContactCompanyLinkRepository::companyOptions((int) ($contactId ?? 0));
$personContactOptions = $personContactOptions ?? ContactCompanyLinkRepository::personOptions((int) ($contactId ?? 0));
$isCompanyForm = ($form['salutation'] ?? '') === 'Firma';
if ($isCompanyForm && $companyEmployees === []) {
    $companyEmployees = [ContactCompanyLinkRepository::emptyEmployeeRow()];
}
?>
<div class="dg-wrap">
  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <?php
        $kontakteReturnTo = trim((string) ($kontakteReturnTo ?? ''));
        if ($kontakteReturnTo !== '' && str_contains($kontakteReturnTo, 'buchhaltung-beleg-form')) {
            $kontakteBackHref = $kontakteReturnTo;
            $kontakteBackLabel = 'Zurück zur Belegerfassung';
        } else {
            $kontakteBackHref = $isEdit ? '/app?page=kontakte&action=view&id=' . (int) $contactId : '/app?page=kontakte';
            $kontakteBackLabel = $isEdit ? 'Zurück zum Kontakt' : 'Zurück zur Liste';
        }
        View::partial('partials/back-nav', [
            'href' => $kontakteBackHref,
            'label' => $kontakteBackLabel,
        ]);
      ?>
      <h1 class="dg-page-title"><?= $isEdit ? 'Kontakt bearbeiten' : 'Neuer Kontakt' ?></h1>
    </div>
  </header>

  <?php if (!empty($formError)) : ?>
    <div class="dg-flash dg-flash--error"><?= View::escape($formError) ?></div>
  <?php endif; ?>

  <form class="dg-form dg-panel" method="post" action="/app?page=kontakte" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
    <?php if (!empty($kontakteReturnTo ?? '')) : ?>
      <input type="hidden" name="return_to" value="<?= View::escape($kontakteReturnTo) ?>">
    <?php endif; ?>
    <?php if ($isEdit) : ?><input type="hidden" name="id" value="<?= (int) $contactId ?>"><?php endif; ?>

    <h2>Stamm</h2>
    <div class="dg-form-grid">
      <label class="dg-field"><span>Benutzername *</span><input name="login" value="<?= View::escape($form['login']) ?>" required></label>
      <label class="dg-field">
        <span>Anrede</span>
        <select name="salutation" id="contact_salutation" data-salutation-select>
          <?php foreach (['', 'Herr', 'Frau', 'Divers', 'Firma', 'Team'] as $opt) : ?>
            <option value="<?= View::escape($opt) ?>"<?= $form['salutation'] === $opt ? ' selected' : '' ?>><?= $opt !== '' ? View::escape($opt) : '—' ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field"><span>Vorname</span><input name="first_name" value="<?= View::escape($form['first_name']) ?>"></label>
      <label class="dg-field"><span>Nachname</span><input name="last_name" value="<?= View::escape($form['last_name']) ?>"></label>
      <label class="dg-field"><span>Anzeigename</span><input name="display_name" value="<?= View::escape($form['display_name']) ?>"></label>
      <label class="dg-field"><span>Firmenname</span><input name="company_name" value="<?= View::escape($form['company_name']) ?>"></label>
      <label class="dg-field dg-field--wide">
        <span>Rolle</span>
        <select name="contact_role" id="contact_role" required data-role-select>
          <?php foreach ($allowedContactRoles as $val => $lbl) : ?>
            <option value="<?= View::escape($val) ?>"<?= $selectedRole === $val ? ' selected' : '' ?>><?= View::escape($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <p class="dg-lead dg-field--wide">Mitarbeiterdaten (Krankenkasse, SV-Nummer, Dokumente …) erscheinen nur bei Rolle <strong>Mitarbeiter</strong> oder <strong>Administrator</strong>.</p>
    </div>

    <section class="dg-panel dg-panel--nested" data-company-section<?= !$isCompanyForm ? ' hidden' : '' ?>>
      <h2>Mitarbeiter der Firma</h2>
      <p class="dg-lead">Verknüpfen Sie bestehende Personen-Kontakte mit dieser Firma — inkl. Zuständigkeit und Erreichbarkeit bei der Firma.</p>
      <div id="dg-company-employee-repeater" class="dg-company-employee-repeater">
        <?php foreach ($companyEmployees as $employeeIndex => $employee) : ?>
          <?php View::partial('partials/contact-company-employee-card', [
              'i' => $employeeIndex,
              'employee' => $employee,
              'personContactOptions' => $personContactOptions,
          ]); ?>
        <?php endforeach; ?>
      </div>
      <p class="dg-form-actions dg-form-actions--inline">
        <button type="button" class="dg-button" data-company-employee-add>+ Mitarbeiter verknüpfen</button>
      </p>
    </section>

    <section class="dg-panel dg-panel--nested" data-person-employer-section<?= $isCompanyForm ? ' hidden' : '' ?>>
      <h2>Arbeitgeber / Firma</h2>
      <p class="dg-lead">Optional: Person mit einer Firmen-Kontaktverknüpfen, bei der diese Person tätig ist.</p>
      <div class="dg-form-grid">
        <label class="dg-field dg-field--wide">
          <span>Firma aus Kontakten</span>
          <select name="employer_company_id" data-employer-company>
            <option value="0">— keine Verknüpfung —</option>
            <?php foreach ($companyContactOptions as $option) : ?>
              <option
                value="<?= (int) $option['id'] ?>"
                <?= (int) ($employerForm['employer_company_id'] ?? 0) === (int) $option['id'] ? ' selected' : '' ?>
              ><?= View::escape((string) $option['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dg-field dg-field--wide">
          <span>Zuständigkeitsbereich</span>
          <input name="employer_responsibility" value="<?= View::escape((string) ($employerForm['employer_responsibility'] ?? '')) ?>" placeholder="z. B. Steuerberatung, Lohnbuchhaltung">
        </label>
        <label class="dg-field">
          <span>E-Mail (dienstlich)</span>
          <input type="email" name="employer_work_email" value="<?= View::escape((string) ($employerForm['employer_work_email'] ?? '')) ?>">
        </label>
        <label class="dg-field">
          <span>Telefon (dienstlich)</span>
          <input name="employer_work_phone" value="<?= View::escape((string) ($employerForm['employer_work_phone'] ?? '')) ?>">
        </label>
        <label class="dg-field dg-field--wide">
          <span>Erreichbarkeit / Öffnungszeiten</span>
          <input name="employer_availability" value="<?= View::escape((string) ($employerForm['employer_availability'] ?? '')) ?>" placeholder="z. B. Mo–Fr 8–16 Uhr">
        </label>
      </div>
    </section>

    <h2>Kunde / Lieferant</h2>
    <div class="dg-form-grid" data-customer-section>
      <label class="dg-field"><span>Kundennummer</span><input name="customer_number" value="<?= View::escape($form['customer_number']) ?>" placeholder="intern, Nummernkreis"></label>
      <label class="dg-field"><span>Lieferantennummer</span><input name="supplier_number" value="<?= View::escape($form['supplier_number']) ?>" placeholder="intern, Nummernkreis"></label>
      <label class="dg-field dg-field--wide" data-supplier-customer-number>
        <span>Kundennummer beim Lieferanten</span>
        <input name="supplier_customer_number" value="<?= View::escape($form['supplier_customer_number'] ?? '') ?>" placeholder="Ihre Kontonummer beim Lieferanten (z. B. von Rechnungen)">
      </label>
      <label class="dg-field"><span>Steuernummer</span><input name="tax_number" value="<?= View::escape($form['tax_number']) ?>"></label>
      <label class="dg-field"><span>USt-IdNr.</span><input name="vat_id" value="<?= View::escape($form['vat_id']) ?>"></label>
      <label class="dg-field"><span>Handelsregister</span><input name="commercial_register" value="<?= View::escape($form['commercial_register'] ?? '') ?>" placeholder="z. B. Amtsgericht Stuttgart HRA 590261"></label>
      <label class="dg-field"><span>WEEE-Registrierungsnr.</span><input name="weee_registration" value="<?= View::escape($form['weee_registration'] ?? '') ?>" placeholder="z. B. DE 69804700"></label>
    </div>

    <h2>Kommunikation</h2>
    <div class="dg-form-grid">
      <label class="dg-field"><span>E-Mail</span><input type="email" name="email" value="<?= View::escape($form['email']) ?>">
        <?php if (trim((string) ($form['email_existence_status'] ?? '')) !== '' && ($form['email_existence_status'] ?? '') !== 'unknown') : ?>
          <small class="dg-field-hint">
            DNS-Prüfung: <?= View::escape((string) $form['email_existence_status']) ?>
            <?php if (trim((string) ($form['email_existence_detail'] ?? '')) !== '') : ?>
              – <?= View::escape((string) $form['email_existence_detail']) ?>
            <?php endif; ?>
            <?php if (trim((string) ($form['email_existence_checked_at'] ?? '')) !== '') : ?>
              (<?= View::escape((string) $form['email_existence_checked_at']) ?>)
            <?php endif; ?>
          </small>
        <?php else : ?>
          <small class="dg-field-hint">Bei Kunden wird die Domain per DNS (MX/A) geprüft und das Ergebnis gespeichert (erneut nach 90 Tagen oder bei E-Mail-Änderung).</small>
        <?php endif; ?>
      </label>
      <label class="dg-field"><span>E-Mail 2</span><input type="email" name="email_2" value="<?= View::escape($form['email_2']) ?>"></label>
      <label class="dg-field"><span>Telefon 1</span><input name="phone_1" value="<?= View::escape($form['phone_1']) ?>"></label>
      <label class="dg-field"><span>Telefon 2</span><input name="phone_2" value="<?= View::escape($form['phone_2']) ?>"></label>
      <label class="dg-field dg-field--wide"><span>Website</span><input name="website" value="<?= View::escape($form['website']) ?>"></label>
      <?php if (!$isEdit) : ?>
        <?php
          $mailAddressConfig = MailAddressSettings::config();
          $autoMailEnabled = !empty($mailAddressConfig['auto_on_contact_create']);
          $autoMailDefault = $autoMailEnabled && CrmRole::hasEmployeeProfile($selectedRole);
          $kasConfiguredForForm = KasSettings::isConfigured();
        ?>
        <label class="dg-field dg-field--wide" data-auto-mailbox-row>
          <span>
            <input type="checkbox" name="auto_create_mailbox" value="1" id="contact_auto_create_mailbox" data-auto-enabled="<?= $autoMailEnabled ? '1' : '0' ?>"<?= $autoMailDefault ? ' checked' : '' ?>>
            E-Mail-Adresse / privates Postfach automatisch anlegen
          </span>
          <small class="dg-field-hint">Nur für Rolle Mitarbeiter oder Administrator. Formel unter Einstellungen → E-Mail. Geprüft werden E-Mail, E-Mail 2 und E-Mail (dienstlich) beim Arbeitgeber. Weicht eine Adresse ab oder ist bereits vergeben, wird keine neue Adresse angelegt.<?php if (!$kasConfiguredForForm) : ?> <strong>Hinweis:</strong> KAS-API (<code>config/kas.local.php</code>) ist nicht konfiguriert — ohne KAS wird kein Postfach bei All-Inkl erzeugt.<?php endif; ?></small>
        </label>
      <?php endif; ?>
    </div>

    <h2>Adresse</h2>
    <div class="dg-form-grid">
      <label class="dg-field"><span>Straße</span><input name="address1_street" value="<?= View::escape($form['address1_street']) ?>"></label>
      <label class="dg-field"><span>Zusatz</span><input name="address1_extra" value="<?= View::escape($form['address1_extra']) ?>"></label>
      <label class="dg-field"><span>Ort</span><input name="address1_city" value="<?= View::escape($form['address1_city']) ?>"></label>
      <label class="dg-field"><span>PLZ</span><input name="address1_postal" value="<?= View::escape($form['address1_postal']) ?>"></label>
      <label class="dg-field"><span>Land</span><input name="address1_country" value="<?= View::escape($form['address1_country']) ?>" maxlength="2"></label>
    </div>

    <?php View::partial('partials/employee-data-form', compact('employeeData', 'employeeFiles', 'contactId', 'showEmployeeFields', 'canEdit')); ?>

    <h2>Bankverbindung</h2>
    <p class="dg-lead">Mehrere Girokonten, Kreditkarten und Zahlungsdienste möglich.</p>
    <div id="dg-bank-repeater" class="dg-bank-repeater">
      <?php foreach ($bankAccounts as $i => $account) : ?>
        <?php View::partial('partials/bank-account-card', compact('i', 'account', 'bankTypes')); ?>
      <?php endforeach; ?>
    </div>
    <p class="dg-bank-repeater__actions">
      <button type="button" class="dg-button" data-bank-add>+ Konto / Zahlungsdienst hinzufügen</button>
    </p>

    <h2>Soziale Medien</h2>
    <p class="dg-lead">Profile in sozialen Netzwerken (Website steht unter Kommunikation).</p>
    <div class="dg-form-grid">
      <?php foreach ($socialFields as $fieldKey => $fieldLabel) : ?>
        <label class="dg-field dg-field--wide">
          <span><?= View::escape($fieldLabel) ?></span>
          <input type="url" name="<?= View::escape($fieldKey) ?>" value="<?= View::escape($form[$fieldKey] ?? '') ?>" placeholder="https://">
        </label>
      <?php endforeach; ?>
    </div>

    <div class="dg-form-actions">
      <button type="submit" name="contact_save" value="1" class="dg-button dg-button--primary">Speichern</button>
      <a class="dg-button" href="<?= View::escape($kontakteBackHref) ?>">Abbrechen</a>
    </div>
  </form>

  <template id="dg-company-employee-card-template">
    <?php View::partial('partials/contact-company-employee-card', [
        'i' => '__INDEX__',
        'employee' => ContactCompanyLinkRepository::emptyEmployeeRow(),
        'personContactOptions' => $personContactOptions,
    ]); ?>
  </template>

  <?php if ($isEdit && !empty($canDeleteContact)) : ?>
    <form class="dg-form dg-panel dg-panel--danger" method="post" action="/app?page=kontakte" style="margin-top:16px" onsubmit="return confirm('Kontakt wirklich löschen?');">
      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
      <input type="hidden" name="id" value="<?= (int) $contactId ?>">
      <h2>Kontakt löschen</h2>
      <p class="dg-lead">Dieser Vorgang kann nicht rückgängig gemacht werden.</p>
      <button type="submit" name="contact_delete" value="1" class="dg-button dg-button--danger">Kontakt löschen</button>
    </form>
  <?php endif; ?>
</div>
