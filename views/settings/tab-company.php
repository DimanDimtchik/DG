<?php
/** @var array<string, string> $companyConfig */
/** @var array<string, mixed> $companyExtended */
$ext = $companyExtended;
$bgData = $ext['bg_data'] ?? [];
$institutions = $ext['institutions'] ?? [];
$employmentAgency = $ext['employment_agency'] ?? [];
$finanzaemter = $ext['finanzaemter'] ?? [CompanyExtendedSettings::emptyFinanzamtRow()];
$professionalChambers = $ext['professional_chambers'] ?? [CompanyExtendedSettings::emptyOrgRow(true)];
$tradeAssociations = $ext['trade_associations'] ?? [CompanyExtendedSettings::emptyOrgRow(true)];
$memberships = $ext['memberships'] ?? [CompanyExtendedSettings::emptyMembershipRow()];
$taxNumbers = $ext['tax_numbers'] ?? [];
$tradeRegister = $ext['trade_register'] ?? ['court' => '', 'number' => ''];
$finanzamtResolved = $ext['finanzamt_resolved'] ?? [];
$addresses = $ext['addresses'] ?? [CompanyExtendedSettings::emptyAddressRow()];
$owners = $ext['owners'] ?? [CompanyExtendedSettings::emptyOwnerRow()];
$bankAccounts = $ext['bank_accounts'] ?? [BankAccountTypes::emptyAccount()];
$bankTypes = BankAccountTypes::labels();
$ownerUserOptions = CompanyExtendedSettings::ownerUserOptions();
$activeEmployeeCount = CompanyExtendedSettings::activeEmployeeCount();
$displayEmployeeCount = CompanyExtendedSettings::displayEmployeeCount($ext);
$ownersShareTotal = 0.0;
foreach ($owners as $ownerRow) {
    $ownersShareTotal += (float) str_replace(',', '.', (string) ($ownerRow['share_percent'] ?? 0));
}
$bgCarrierOptions = UvCarriers::select_options();
$bgCarrierKey = (string) ($bgData['carrier_key'] ?? '');
$industryUvSuggestion = !empty($ext['industry']) ? UvCarriers::suggest_for_industry((string) $ext['industry']) : '';
$institutionLabels = [
    'ihk' => 'Industrie- und Handelskammer (IHK)',
    'hwk' => 'Handwerkskammer (HWK)',
    'union' => 'Gewerkschaft',
    'works_council' => 'Betriebsrat',
];
$countFilledByName = static function (array $rows, string $key = 'name'): int {
    $count = 0;
    foreach ($rows as $row) {
        if (trim((string) ($row[$key] ?? '')) !== '') {
            ++$count;
        }
    }
    return $count;
};
$stammdatenParts = array_filter([
    trim($companyConfig['name']),
    trim($companyConfig['city']),
    trim($companyConfig['email']),
]);
$stammdatenSummary = $stammdatenParts !== [] ? implode(' · ', $stammdatenParts) : 'Noch nicht ausgefüllt';
$filledOwners = $countFilledByName($owners);
$ownersSummaryParts = [];
if ($filledOwners > 0) {
    $ownersSummaryParts[] = $filledOwners === 1 ? '1 Inhaber' : $filledOwners . ' Inhaber';
}
if ($ownersShareTotal > 0) {
    $ownersSummaryParts[] = 'Summe ' . number_format($ownersShareTotal, 0, ',', '.') . ' %';
}
$ownersSummary = $ownersSummaryParts !== [] ? implode(' · ', $ownersSummaryParts) : 'Keine Inhaber';
$filledAddresses = 0;
foreach ($addresses as $addr) {
    if (trim((string) ($addr['street'] ?? '')) !== '' || trim((string) ($addr['city'] ?? '')) !== '') {
        ++$filledAddresses;
    }
}
$addressesSummary = $filledAddresses > 0
    ? ($filledAddresses === 1 ? '1 Standort' : $filledAddresses . ' Standorte')
    : 'Keine Standorte';
$taxSummaryParts = [];
$estTax = trim((string) ($taxNumbers['est'] ?? $companyConfig['tax_number'] ?? ''));
if ($estTax !== '') {
    $taxSummaryParts[] = 'ESt: ' . $estTax;
}
$filledFinanzaemter = $countFilledByName($finanzaemter);
if ($filledFinanzaemter > 0) {
    $taxSummaryParts[] = $filledFinanzaemter === 1 ? '1 Finanzamt' : $filledFinanzaemter . ' Finanzämter';
}
$taxSummary = $taxSummaryParts !== [] ? implode(' · ', $taxSummaryParts) : 'Noch keine Steuerdaten';
$uvCarrier = $bgCarrierKey !== '' ? UvCarriers::get($bgCarrierKey) : null;
$uvSummary = $uvCarrier ? ($uvCarrier['short'] . ' – ' . $uvCarrier['name']) : 'Nicht zugeordnet';
$employmentParts = array_filter([
    trim((string) ($employmentAgency['name'] ?? '')),
    trim((string) ($employmentAgency['betriebsnummer'] ?? '')) !== ''
        ? 'BN ' . trim((string) ($employmentAgency['betriebsnummer'] ?? ''))
        : '',
]);
$employmentSummary = $employmentParts !== [] ? implode(' · ', $employmentParts) : 'Noch nicht ausgefüllt';
$filledInstitutions = $countFilledByName($institutions);
$institutionsSummary = $filledInstitutions > 0
    ? ($filledInstitutions === 1 ? '1 Eintrag' : $filledInstitutions . ' Einträge')
    : 'Keine Kammern hinterlegt';
$filledProfChambers = $countFilledByName($professionalChambers);
$profChambersSummary = $filledProfChambers > 0
    ? ($filledProfChambers === 1 ? '1 Kammer' : $filledProfChambers . ' Kammern')
    : 'Keine Kammern';
$filledTradeAssoc = $countFilledByName($tradeAssociations);
$tradeAssocSummary = $filledTradeAssoc > 0
    ? ($filledTradeAssoc === 1 ? '1 Verband' : $filledTradeAssoc . ' Verbände')
    : 'Keine Verbände';
$filledMemberships = $countFilledByName($memberships);
$membershipsSummary = $filledMemberships > 0
    ? ($filledMemberships === 1 ? '1 Mitgliedschaft' : $filledMemberships . ' Mitgliedschaften')
    : 'Keine Mitgliedschaften';
$filledBanks = 0;
foreach ($bankAccounts as $account) {
    if (trim((string) ($account['iban'] ?? '')) !== '' || trim((string) ($account['bank_name'] ?? '')) !== '') {
        ++$filledBanks;
    }
}
$bankSummary = $filledBanks > 0
    ? ($filledBanks === 1 ? '1 Konto' : $filledBanks . ' Konten')
    : 'Keine Bankverbindung';
?>
<form class="dg-form dg-company-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('firmendaten')) ?>">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

  <p class="dg-lead">
    Diese Angaben gelten im CRM als <strong>Ihre Firma</strong> — u. a. als
    <strong>Absendername und Absender-Adresse</strong> für E-Mails sowie für Behörden, Kammern und Versicherungsträger.
  </p>

  <div class="dg-dept-accordion__toolbar">
    <button type="button" class="dg-button dg-button--small" id="dg-company-expand-all">Alle aufklappen</button>
    <button type="button" class="dg-button dg-button--small" id="dg-company-collapse-all">Alle zuklappen</button>
  </div>

  <div id="dg-company-accordion" class="dg-dept-accordion">

  <section class="dg-dept-card is-open" data-dept-card data-company-section="stammdaten">
    <header class="dg-dept-accordion__header">
      <button type="button" class="dg-dept-accordion__trigger" data-dept-toggle aria-expanded="true">
        <span class="dg-dept-accordion__icon" aria-hidden="true"></span>
        <span class="dg-dept-accordion__label">
          <strong class="dg-dept-accordion__title">Stammdaten</strong>
          <span class="dg-dept-accordion__meta" data-dept-summary><?= View::escape($stammdatenSummary) ?></span>
        </span>
      </button>
    </header>
    <div class="dg-dept-accordion__panel" data-dept-panel>
    <div class="dg-form-grid">
      <label class="dg-field dg-field--wide">
        <span>Firmenname *</span>
        <input type="text" name="name" value="<?= View::escape($companyConfig['name']) ?>" required>
      </label>
      <label class="dg-field">
        <span>Firmen-ID (Kürzel)</span>
        <input type="text" name="company_id" value="<?= View::escape($companyConfig['company_id'] ?? '') ?>" maxlength="20" placeholder="z. B. GOM" autocapitalize="characters">
        <small class="dg-field-hint">Für Nummernkreis-Platzhalter <code>{FIRMA}</code>. Leer = Kürzel aus dem Firmennamen, z. B. „Dietrich Ganz OM“ → <code>DGOM</code>.</small>
      </label>
      <label class="dg-field dg-field--wide">
        <span>Rechtlicher Name</span>
        <input type="text" name="legal_name" value="<?= View::escape((string) ($ext['legal_name'] ?? '')) ?>">
      </label>
      <label class="dg-field dg-field--wide">
        <span>Unternehmenstyp</span>
        <select name="company_type">
          <option value="">— Bitte wählen —</option>
          <?php foreach (CompanyTypes::labels() as $typeKey => $typeLabel) : ?>
            <option value="<?= View::escape($typeKey) ?>"<?= ($ext['company_type'] ?? '') === $typeKey ? ' selected' : '' ?>><?= View::escape($typeLabel) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field">
        <span>E-Mail *</span>
        <input type="email" name="email" value="<?= View::escape($companyConfig['email']) ?>" required>
      </label>
      <label class="dg-field">
        <span>Telefon</span>
        <input type="text" name="phone" value="<?= View::escape($companyConfig['phone']) ?>">
      </label>
      <label class="dg-field dg-field--wide">
        <span>Website</span>
        <input type="url" name="website" value="<?= View::escape($companyConfig['website']) ?>" placeholder="https://">
      </label>
      <label class="dg-field dg-field--wide">
        <span>Straße</span>
        <input type="text" name="street" value="<?= View::escape($companyConfig['street']) ?>">
      </label>
      <label class="dg-field">
        <span>PLZ</span>
        <input type="text" name="postal" value="<?= View::escape($companyConfig['postal']) ?>" data-company-postal>
      </label>
      <label class="dg-field">
        <span>Ort</span>
        <input type="text" name="city" value="<?= View::escape($companyConfig['city']) ?>" data-company-city>
      </label>
      <label class="dg-field">
        <span>Land</span>
        <input type="text" name="country" value="<?= View::escape($companyConfig['country']) ?>" maxlength="2">
      </label>
      <label class="dg-field dg-field--wide">
        <span>Branche</span>
        <select name="industry" id="dg-company-industry">
          <option value="">— Bitte wählen —</option>
          <?php foreach (IndustryBranches::groups() as $group => $items) : ?>
            <optgroup label="<?= View::escape($group) ?>">
              <?php foreach ($items as $key => $item) : ?>
                <option value="<?= View::escape($key) ?>"<?= ($ext['industry'] ?? '') === $key ? ' selected' : '' ?>>
                  <?= View::escape($item['label']) ?> (WZ <?= View::escape($item['wz']) ?>, <?= View::escape($item['bg']) ?>)
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
        <small class="dg-field-hint">Orientierung an Finanzamt (WZ) und Berufsgenossenschaft.</small>
      </label>
      <div class="dg-field dg-field--wide">
        <span>Anzahl Mitarbeiter</span>
        <div class="dg-radio-row">
          <label class="dg-field dg-field--check">
            <input type="radio" name="employee_count_mode" value="auto"<?= ($ext['employee_count_mode'] ?? 'auto') !== 'manual' ? ' checked' : '' ?>>
            <span>Automatisch (<?= (int) $activeEmployeeCount ?> aktive CRM-Mitarbeiter)</span>
          </label>
          <label class="dg-field dg-field--check">
            <input type="radio" name="employee_count_mode" value="manual"<?= ($ext['employee_count_mode'] ?? '') === 'manual' ? ' checked' : '' ?>>
            <span>Manuell</span>
            <input type="number" min="0" class="dg-input--narrow" name="employee_count_manual" value="<?= View::escape((string) ($ext['employee_count_manual'] ?? 0)) ?>">
          </label>
        </div>
        <small class="dg-field-hint">Aktuell verwendet: <strong><?= (int) $displayEmployeeCount ?></strong></small>
      </div>
    </div>
    </div>
  </section>

  <section class="dg-dept-card" data-dept-card data-company-section="owners">
    <header class="dg-dept-accordion__header">
      <button type="button" class="dg-dept-accordion__trigger" data-dept-toggle aria-expanded="false">
        <span class="dg-dept-accordion__icon" aria-hidden="true"></span>
        <span class="dg-dept-accordion__label">
          <strong class="dg-dept-accordion__title">Inhaber / Gesellschafter</strong>
          <span class="dg-dept-accordion__meta" data-dept-summary><?= View::escape($ownersSummary) ?></span>
        </span>
      </button>
    </header>
    <div class="dg-dept-accordion__panel" data-dept-panel hidden>
    <p class="dg-field-hint">Ein oder mehrere Inhaber mit prozentualem Anteil am Unternehmen.</p>
    <div class="dg-company-repeater" id="dg-owners-repeater" data-repeater="owners">
      <?php foreach ($owners as $i => $owner) : ?>
        <div class="dg-company-repeater__row dg-company-repeater__row--owners" data-repeater-row>
          <label class="dg-field dg-field--wide"><span>Name</span><input type="text" name="owners[<?= (int) $i ?>][name]" value="<?= View::escape((string) ($owner['name'] ?? '')) ?>" data-owner-name></label>
          <label class="dg-field"><span>Anteil %</span><input type="number" min="0" max="100" step="0.01" name="owners[<?= (int) $i ?>][share_percent]" value="<?= View::escape((string) ($owner['share_percent'] ?? '')) ?>" data-owner-share></label>
          <label class="dg-field dg-field--wide">
            <span>CRM-Benutzer (optional)</span>
            <select name="owners[<?= (int) $i ?>][user_id]">
              <option value="0">— optional —</option>
              <?php foreach ($ownerUserOptions as $option) : ?>
                <option value="<?= (int) $option['id'] ?>"<?= (int) ($owner['user_id'] ?? 0) === (int) $option['id'] ? ' selected' : '' ?>><?= View::escape($option['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <button type="button" class="dg-button dg-button--ghost dg-repeater-remove">Entfernen</button>
        </div>
      <?php endforeach; ?>
    </div>
    <p>
      <button type="button" class="dg-button dg-button--secondary" data-repeater-add="owners">+ Inhaber hinzufügen</button>
      <span id="dg-owners-share-total" class="dg-field-hint<?= abs($ownersShareTotal - 100) > 0.01 && $ownersShareTotal > 0 ? ' dg-owners-share-warning' : '' ?>">
        Summe Anteile: <?= View::escape(number_format($ownersShareTotal, 2, ',', '.')) ?> %
      </span>
    </p>
    </div>
  </section>

  <section class="dg-dept-card" data-dept-card data-company-section="addresses">
    <header class="dg-dept-accordion__header">
      <button type="button" class="dg-dept-accordion__trigger" data-dept-toggle aria-expanded="false">
        <span class="dg-dept-accordion__icon" aria-hidden="true"></span>
        <span class="dg-dept-accordion__label">
          <strong class="dg-dept-accordion__title">Standorte / Adressen</strong>
          <span class="dg-dept-accordion__meta" data-dept-summary><?= View::escape($addressesSummary) ?></span>
        </span>
      </button>
    </header>
    <div class="dg-dept-accordion__panel" data-dept-panel hidden>
    <p class="dg-field-hint">Zentrale, Lager, Büros und weitere Standorte. Der Hauptsitz wird mit den Stammdaten oben synchronisiert.</p>
    <div class="dg-company-repeater" id="dg-addresses-repeater" data-repeater="addresses">
      <?php foreach ($addresses as $i => $addr) : ?>
        <div class="dg-company-repeater__row" data-repeater-row>
          <label class="dg-field">
            <span>Typ</span>
            <select name="addresses[<?= (int) $i ?>][type]">
              <?php foreach (CompanyAddressTypes::labels() as $typeKey => $typeLabel) : ?>
                <option value="<?= View::escape($typeKey) ?>"<?= ($addr['type'] ?? 'hauptsitz') === $typeKey ? ' selected' : '' ?>><?= View::escape($typeLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="dg-field"><span>Bezeichnung</span><input type="text" name="addresses[<?= (int) $i ?>][label]" value="<?= View::escape((string) ($addr['label'] ?? '')) ?>"></label>
          <label class="dg-field dg-field--wide"><span>Straße</span><input type="text" name="addresses[<?= (int) $i ?>][street]" value="<?= View::escape((string) ($addr['street'] ?? '')) ?>"></label>
          <label class="dg-field"><span>PLZ</span><input type="text" name="addresses[<?= (int) $i ?>][postal_code]" value="<?= View::escape((string) ($addr['postal_code'] ?? '')) ?>"></label>
          <label class="dg-field"><span>Ort</span><input type="text" name="addresses[<?= (int) $i ?>][city]" value="<?= View::escape((string) ($addr['city'] ?? '')) ?>"></label>
          <label class="dg-field"><span>Land</span><input type="text" name="addresses[<?= (int) $i ?>][country]" value="<?= View::escape((string) ($addr['country'] ?? 'DE')) ?>" maxlength="2"></label>
          <button type="button" class="dg-button dg-button--ghost dg-repeater-remove">Entfernen</button>
        </div>
      <?php endforeach; ?>
    </div>
    <p><button type="button" class="dg-button dg-button--secondary" data-repeater-add="addresses">+ Adresse hinzufügen</button></p>
    </div>
  </section>

  <section class="dg-dept-card" data-dept-card data-company-section="tax">
    <header class="dg-dept-accordion__header">
      <button type="button" class="dg-dept-accordion__trigger" data-dept-toggle aria-expanded="false">
        <span class="dg-dept-accordion__icon" aria-hidden="true"></span>
        <span class="dg-dept-accordion__label">
          <strong class="dg-dept-accordion__title">Steuernummern &amp; Handelsregister</strong>
          <span class="dg-dept-accordion__meta" data-dept-summary><?= View::escape($taxSummary) ?></span>
        </span>
      </button>
    </header>
    <div class="dg-dept-accordion__panel" data-dept-panel hidden>
    <div class="dg-form-grid">
      <label class="dg-field dg-field--wide">
        <span>ESt-Steuernummer (Finanzamt-Zuordnung)</span>
        <div class="dg-input-with-action">
          <input type="text" name="tax_numbers[est]" id="dg-tax-number-est" value="<?= View::escape((string) ($taxNumbers['est'] ?? $companyConfig['tax_number'])) ?>" data-tax-est>
          <button type="button" class="dg-button dg-button--secondary" id="dg-lookup-finanzamt">Finanzamt ermitteln</button>
        </div>
        <small class="dg-field-hint">Format z. B. 127/219/40770 oder 13-stelliges ELSTER-Format.</small>
      </label>
      <label class="dg-field">
        <span>USt-IdNr.</span>
        <input type="text" name="tax_numbers[ust]" value="<?= View::escape((string) ($taxNumbers['ust'] ?? $companyConfig['vat_id'])) ?>">
      </label>
      <label class="dg-field">
        <span>Gewerbesteuer</span>
        <input type="text" name="tax_numbers[gst]" value="<?= View::escape((string) ($taxNumbers['gst'] ?? '')) ?>">
      </label>
      <label class="dg-field">
        <span>Körperschaftsteuer</span>
        <input type="text" name="tax_numbers[kst]" value="<?= View::escape((string) ($taxNumbers['kst'] ?? '')) ?>">
      </label>
      <label class="dg-field">
        <span>Steuer-ID (11-stellig)</span>
        <input type="text" name="tax_numbers[steuer_id]" value="<?= View::escape((string) ($taxNumbers['steuer_id'] ?? '')) ?>">
      </label>
      <label class="dg-field">
        <span>Wirtschafts-ID</span>
        <input type="text" name="tax_numbers[wirtschafts_id]" value="<?= View::escape((string) ($taxNumbers['wirtschafts_id'] ?? '')) ?>">
      </label>
      <label class="dg-field dg-field--wide">
        <span>Registergericht</span>
        <input type="text" name="trade_register[court]" value="<?= View::escape((string) ($tradeRegister['court'] ?? '')) ?>">
      </label>
      <label class="dg-field">
        <span>Registernummer</span>
        <input type="text" name="trade_register[number]" value="<?= View::escape((string) ($tradeRegister['number'] ?? '')) ?>">
      </label>
    </div>

    <div class="dg-finanzamt-panel" id="dg-finanzamt-panel">
      <p class="dg-field-hint">GemFA-Datenbank: <?= (int) FinanzamtRegistry::count() ?> Finanzämter deutschlandweit.</p>
      <div class="dg-founder-lookup">
        <p class="dg-field-hint"><strong>Noch keine Steuernummer?</strong> Finanzamt über Standort oder Suche ermitteln.</p>
        <div class="dg-input-with-action">
          <input type="text" id="dg-founder-plz" class="dg-input--narrow" placeholder="PLZ" maxlength="5">
          <input type="text" id="dg-founder-city" placeholder="Ort">
          <button type="button" class="dg-button dg-button--secondary dg-founder-lookup__action" id="dg-lookup-finanzamt-location">Nach Standort</button>
        </div>
        <div class="dg-input-with-action">
          <input type="text" id="dg-founder-fa-search" placeholder="Finanzamtsname oder BuFa-Nr. …">
          <button type="button" class="dg-button dg-button--secondary dg-founder-lookup__action" id="dg-lookup-finanzamt-search">Suchen</button>
        </div>
      </div>
      <div class="dg-finanzamt-card" id="dg-finanzamt-content">
        <?php
        $office = is_array($finanzamtResolved['office'] ?? null) ? $finanzamtResolved['office'] : [];
        if (!empty($finanzamtResolved['found']) && !empty($office['name'])) :
            ?>
          <p><strong><?= View::escape((string) $office['name']) ?></strong> (BuFa <?= View::escape((string) ($finanzamtResolved['bufo_nr'] ?? '')) ?>)</p>
          <p><?= View::escape(trim(($office['street'] ?? '') . ', ' . ($office['postal_code'] ?? '') . ' ' . ($office['city'] ?? ''))) ?></p>
          <?php if (!empty($office['phone'])) : ?><p>Telefon: <?= View::escape((string) $office['phone']) ?></p><?php endif; ?>
          <?php if (!empty($office['opening_hours'])) : ?><p>Öffnungszeiten: <?= View::escape((string) $office['opening_hours']) ?></p><?php endif; ?>
        <?php elseif (!empty($finanzamtResolved['error'])) : ?>
          <p class="dg-field-hint"><?= View::escape((string) $finanzamtResolved['error']) ?></p>
        <?php else : ?>
          <p class="dg-field-hint">Steuernummer eingeben oder Standortsuche nutzen.</p>
        <?php endif; ?>
      </div>
    </div>

    <h4 class="dg-settings-section__subtitle">Zuständige Finanzämter</h4>
    <p class="dg-field-hint">Mehrere Finanzämter möglich (z. B. verschiedene Standorte oder Steuerarten).</p>
    <div class="dg-company-repeater" id="dg-finanzaemter-repeater" data-repeater="finanzaemter">
      <?php foreach ($finanzaemter as $i => $fa) : ?>
        <div class="dg-company-repeater__row" data-repeater-row>
          <label class="dg-field"><span>BuFa-Nr.</span><input type="text" name="finanzaemter[<?= (int) $i ?>][bufo_nr]" value="<?= View::escape((string) ($fa['bufo_nr'] ?? '')) ?>"></label>
          <label class="dg-field dg-field--wide"><span>Name</span><input type="text" name="finanzaemter[<?= (int) $i ?>][name]" value="<?= View::escape((string) ($fa['name'] ?? '')) ?>"></label>
          <label class="dg-field dg-field--wide"><span>Straße</span><input type="text" name="finanzaemter[<?= (int) $i ?>][street]" value="<?= View::escape((string) ($fa['street'] ?? '')) ?>"></label>
          <label class="dg-field"><span>PLZ</span><input type="text" name="finanzaemter[<?= (int) $i ?>][postal_code]" value="<?= View::escape((string) ($fa['postal_code'] ?? '')) ?>"></label>
          <label class="dg-field"><span>Ort</span><input type="text" name="finanzaemter[<?= (int) $i ?>][city]" value="<?= View::escape((string) ($fa['city'] ?? '')) ?>"></label>
          <label class="dg-field"><span>Telefon</span><input type="text" name="finanzaemter[<?= (int) $i ?>][phone]" value="<?= View::escape((string) ($fa['phone'] ?? '')) ?>"></label>
          <label class="dg-field"><span>E-Mail</span><input type="email" name="finanzaemter[<?= (int) $i ?>][email]" value="<?= View::escape((string) ($fa['email'] ?? '')) ?>"></label>
          <label class="dg-field dg-field--wide"><span>Öffnungszeiten</span><input type="text" name="finanzaemter[<?= (int) $i ?>][opening_hours]" value="<?= View::escape((string) ($fa['opening_hours'] ?? '')) ?>"></label>
          <label class="dg-field dg-field--wide"><span>Notiz</span><input type="text" name="finanzaemter[<?= (int) $i ?>][notes]" value="<?= View::escape((string) ($fa['notes'] ?? '')) ?>"></label>
          <label class="dg-field dg-field--check">
            <input type="checkbox" name="finanzaemter[<?= (int) $i ?>][is_primary]" value="1"<?= !empty($fa['is_primary']) ? ' checked' : '' ?>>
            <span>Haupt-Finanzamt</span>
          </label>
          <button type="button" class="dg-button dg-button--ghost dg-repeater-remove">Entfernen</button>
        </div>
      <?php endforeach; ?>
    </div>
    <p><button type="button" class="dg-button dg-button--secondary" data-repeater-add="finanzaemter">+ Finanzamt hinzufügen</button></p>
    </div>
  </section>

  <section class="dg-dept-card" data-dept-card data-company-section="uv">
    <header class="dg-dept-accordion__header">
      <button type="button" class="dg-dept-accordion__trigger" data-dept-toggle aria-expanded="false">
        <span class="dg-dept-accordion__icon" aria-hidden="true"></span>
        <span class="dg-dept-accordion__label">
          <strong class="dg-dept-accordion__title">Unfallversicherung (Berufsgenossenschaft / Unfallkasse)</strong>
          <span class="dg-dept-accordion__meta" data-dept-summary><?= View::escape($uvSummary) ?></span>
        </span>
      </button>
    </header>
    <div class="dg-dept-accordion__panel" data-dept-panel hidden>
    <p class="dg-field-hint">
      <?= count(UvCarriers::all()) ?> UV-Träger.
      <?php if ($industryUvSuggestion !== '') :
          $suggested = UvCarriers::get($industryUvSuggestion);
          ?>
        Vorschlag aus Branche: <?= View::escape($suggested ? $suggested['short'] . ' – ' . $suggested['name'] : $industryUvSuggestion) ?>.
      <?php endif; ?>
    </p>
    <div class="dg-form-grid">
      <label class="dg-field dg-field--wide">
        <span>UV-Träger</span>
        <div class="dg-input-with-action">
          <select name="bg_data[carrier_key]" id="dg-uv-carrier">
            <option value="">— Bitte wählen —</option>
            <?php foreach (['BG' => 'Berufsgenossenschaften', 'UK' => 'Unfallkassen'] as $type => $label) : ?>
              <?php if (!empty($bgCarrierOptions[$type])) : ?>
                <optgroup label="<?= View::escape($label) ?>">
                  <?php foreach ($bgCarrierOptions[$type] as $key => $optLabel) : ?>
                    <option value="<?= View::escape($key) ?>"<?= $bgCarrierKey === $key ? ' selected' : '' ?>><?= View::escape($optLabel) ?></option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endif; ?>
            <?php endforeach; ?>
          </select>
          <button type="button" class="dg-button dg-button--secondary" id="dg-suggest-uv-carrier">Vorschlag aus Branche</button>
        </div>
      </label>
      <label class="dg-field">
        <span>Unternehmensnummer (15-stellig)</span>
        <input type="text" name="bg_data[company_number]" id="dg-uv-company-number" maxlength="15" value="<?= View::escape((string) ($bgData['company_number'] ?? '')) ?>">
      </label>
      <label class="dg-field dg-field--wide">
        <span>Empfänger / UV-Träger</span>
        <input type="text" name="bg_data[recipient_name]" id="dg-uv-recipient" value="<?= View::escape((string) ($bgData['recipient_name'] ?? '')) ?>">
      </label>
      <label class="dg-field dg-field--wide">
        <span>Straße</span>
        <input type="text" name="bg_data[street]" id="dg-uv-street" value="<?= View::escape((string) ($bgData['street'] ?? '')) ?>">
      </label>
      <label class="dg-field"><span>PLZ</span><input type="text" name="bg_data[postal_code]" id="dg-uv-postal" value="<?= View::escape((string) ($bgData['postal_code'] ?? '')) ?>"></label>
      <label class="dg-field"><span>Ort</span><input type="text" name="bg_data[city]" id="dg-uv-city" value="<?= View::escape((string) ($bgData['city'] ?? '')) ?>"></label>
      <label class="dg-field"><span>Telefon</span><input type="text" name="bg_data[phone]" value="<?= View::escape((string) ($bgData['phone'] ?? '')) ?>"></label>
      <label class="dg-field"><span>E-Mail</span><input type="email" name="bg_data[email]" value="<?= View::escape((string) ($bgData['email'] ?? '')) ?>"></label>
    </div>
    </div>
  </section>

  <section class="dg-dept-card" data-dept-card data-company-section="employment">
    <header class="dg-dept-accordion__header">
      <button type="button" class="dg-dept-accordion__trigger" data-dept-toggle aria-expanded="false">
        <span class="dg-dept-accordion__icon" aria-hidden="true"></span>
        <span class="dg-dept-accordion__label">
          <strong class="dg-dept-accordion__title">Agentur für Arbeit</strong>
          <span class="dg-dept-accordion__meta" data-dept-summary><?= View::escape($employmentSummary) ?></span>
        </span>
      </button>
    </header>
    <div class="dg-dept-accordion__panel" data-dept-panel hidden>
    <div class="dg-form-grid">
      <label class="dg-field dg-field--wide">
        <span>Bezeichnung</span>
        <input type="text" name="employment_agency[name]" value="<?= View::escape((string) ($employmentAgency['name'] ?? 'Agentur für Arbeit')) ?>">
      </label>
      <label class="dg-field">
        <span>Betriebsnummer (8-stellig)</span>
        <input type="text" name="employment_agency[betriebsnummer]" maxlength="8" value="<?= View::escape((string) ($employmentAgency['betriebsnummer'] ?? '')) ?>">
      </label>
      <label class="dg-field"><span>Ansprechpartner</span><input type="text" name="employment_agency[contact]" value="<?= View::escape((string) ($employmentAgency['contact'] ?? '')) ?>"></label>
      <label class="dg-field"><span>Telefon</span><input type="text" name="employment_agency[phone]" value="<?= View::escape((string) ($employmentAgency['phone'] ?? '')) ?>"></label>
      <label class="dg-field"><span>E-Mail</span><input type="email" name="employment_agency[email]" value="<?= View::escape((string) ($employmentAgency['email'] ?? '')) ?>"></label>
    </div>
    </div>
  </section>

  <section class="dg-dept-card" data-dept-card data-company-section="institutions">
    <header class="dg-dept-accordion__header">
      <button type="button" class="dg-dept-accordion__trigger" data-dept-toggle aria-expanded="false">
        <span class="dg-dept-accordion__icon" aria-hidden="true"></span>
        <span class="dg-dept-accordion__label">
          <strong class="dg-dept-accordion__title">Kammern, Gewerkschaft &amp; Betriebsrat</strong>
          <span class="dg-dept-accordion__meta" data-dept-summary><?= View::escape($institutionsSummary) ?></span>
        </span>
      </button>
    </header>
    <div class="dg-dept-accordion__panel" data-dept-panel hidden>
    <div class="dg-institutions-grid">
      <?php foreach ($institutionLabels as $instKey => $instTitle) :
          $inst = $institutions[$instKey] ?? [];
          $hasMemberNo = $instKey === 'ihk' || $instKey === 'hwk';
          ?>
        <div class="dg-institution-card">
          <h4><?= View::escape($instTitle) ?></h4>
          <label class="dg-field dg-field--wide"><span>Name / Organisation</span><input type="text" name="institutions[<?= View::escape($instKey) ?>][name]" value="<?= View::escape((string) ($inst['name'] ?? '')) ?>"></label>
          <?php if ($hasMemberNo) : ?>
            <label class="dg-field"><span>Mitgliedsnummer</span><input type="text" name="institutions[<?= View::escape($instKey) ?>][member_no]" value="<?= View::escape((string) ($inst['member_no'] ?? '')) ?>"></label>
          <?php endif; ?>
          <label class="dg-field"><span>Ansprechpartner</span><input type="text" name="institutions[<?= View::escape($instKey) ?>][contact]" value="<?= View::escape((string) ($inst['contact'] ?? '')) ?>"></label>
          <label class="dg-field"><span>Telefon</span><input type="text" name="institutions[<?= View::escape($instKey) ?>][phone]" value="<?= View::escape((string) ($inst['phone'] ?? '')) ?>"></label>
          <label class="dg-field"><span>E-Mail</span><input type="email" name="institutions[<?= View::escape($instKey) ?>][email]" value="<?= View::escape((string) ($inst['email'] ?? '')) ?>"></label>
        </div>
      <?php endforeach; ?>
    </div>
    </div>
  </section>

  <section class="dg-dept-card" data-dept-card data-company-section="professional_chambers">
    <header class="dg-dept-accordion__header">
      <button type="button" class="dg-dept-accordion__trigger" data-dept-toggle aria-expanded="false">
        <span class="dg-dept-accordion__icon" aria-hidden="true"></span>
        <span class="dg-dept-accordion__label">
          <strong class="dg-dept-accordion__title">Berufsständische Kammern</strong>
          <span class="dg-dept-accordion__meta" data-dept-summary><?= View::escape($profChambersSummary) ?></span>
        </span>
      </button>
    </header>
    <div class="dg-dept-accordion__panel" data-dept-panel hidden>
    <p class="dg-field-hint">Z. B. Ärztekammer, Rechtsanwaltskammer, Architektenkammer — branchenabhängig.</p>
    <div class="dg-company-repeater" id="dg-professional-chambers-repeater" data-repeater="professional_chambers">
      <?php foreach ($professionalChambers as $i => $row) : ?>
        <div class="dg-company-repeater__row" data-repeater-row>
          <label class="dg-field dg-field--wide"><span>Kammer</span><input type="text" name="professional_chambers[<?= (int) $i ?>][name]" value="<?= View::escape((string) ($row['name'] ?? '')) ?>"></label>
          <label class="dg-field"><span>Mitgliedsnummer</span><input type="text" name="professional_chambers[<?= (int) $i ?>][member_no]" value="<?= View::escape((string) ($row['member_no'] ?? '')) ?>"></label>
          <label class="dg-field"><span>Ansprechpartner</span><input type="text" name="professional_chambers[<?= (int) $i ?>][contact]" value="<?= View::escape((string) ($row['contact'] ?? '')) ?>"></label>
          <label class="dg-field"><span>Telefon</span><input type="text" name="professional_chambers[<?= (int) $i ?>][phone]" value="<?= View::escape((string) ($row['phone'] ?? '')) ?>"></label>
          <label class="dg-field"><span>E-Mail</span><input type="email" name="professional_chambers[<?= (int) $i ?>][email]" value="<?= View::escape((string) ($row['email'] ?? '')) ?>"></label>
          <button type="button" class="dg-button dg-button--ghost dg-repeater-remove">Entfernen</button>
        </div>
      <?php endforeach; ?>
    </div>
    <p><button type="button" class="dg-button dg-button--secondary" data-repeater-add="professional_chambers">+ Kammer hinzufügen</button></p>
    </div>
  </section>

  <section class="dg-dept-card" data-dept-card data-company-section="trade_associations">
    <header class="dg-dept-accordion__header">
      <button type="button" class="dg-dept-accordion__trigger" data-dept-toggle aria-expanded="false">
        <span class="dg-dept-accordion__icon" aria-hidden="true"></span>
        <span class="dg-dept-accordion__label">
          <strong class="dg-dept-accordion__title">Berufsverbände / Innungen</strong>
          <span class="dg-dept-accordion__meta" data-dept-summary><?= View::escape($tradeAssocSummary) ?></span>
        </span>
      </button>
    </header>
    <div class="dg-dept-accordion__panel" data-dept-panel hidden>
    <div class="dg-company-repeater" id="dg-trade-associations-repeater" data-repeater="trade_associations">
      <?php foreach ($tradeAssociations as $i => $row) : ?>
        <div class="dg-company-repeater__row" data-repeater-row>
          <label class="dg-field dg-field--wide"><span>Verband / Innung</span><input type="text" name="trade_associations[<?= (int) $i ?>][name]" value="<?= View::escape((string) ($row['name'] ?? '')) ?>"></label>
          <label class="dg-field"><span>Mitgliedsnummer</span><input type="text" name="trade_associations[<?= (int) $i ?>][member_no]" value="<?= View::escape((string) ($row['member_no'] ?? '')) ?>"></label>
          <label class="dg-field"><span>Ansprechpartner</span><input type="text" name="trade_associations[<?= (int) $i ?>][contact]" value="<?= View::escape((string) ($row['contact'] ?? '')) ?>"></label>
          <label class="dg-field"><span>Telefon</span><input type="text" name="trade_associations[<?= (int) $i ?>][phone]" value="<?= View::escape((string) ($row['phone'] ?? '')) ?>"></label>
          <label class="dg-field"><span>E-Mail</span><input type="email" name="trade_associations[<?= (int) $i ?>][email]" value="<?= View::escape((string) ($row['email'] ?? '')) ?>"></label>
          <button type="button" class="dg-button dg-button--ghost dg-repeater-remove">Entfernen</button>
        </div>
      <?php endforeach; ?>
    </div>
    <p><button type="button" class="dg-button dg-button--secondary" data-repeater-add="trade_associations">+ Verband / Innung hinzufügen</button></p>
    </div>
  </section>

  <section class="dg-dept-card" data-dept-card data-company-section="memberships">
    <header class="dg-dept-accordion__header">
      <button type="button" class="dg-dept-accordion__trigger" data-dept-toggle aria-expanded="false">
        <span class="dg-dept-accordion__icon" aria-hidden="true"></span>
        <span class="dg-dept-accordion__label">
          <strong class="dg-dept-accordion__title">Pflicht- und freiwillige Mitgliedschaften</strong>
          <span class="dg-dept-accordion__meta" data-dept-summary><?= View::escape($membershipsSummary) ?></span>
        </span>
      </button>
    </header>
    <div class="dg-dept-accordion__panel" data-dept-panel hidden>
    <p class="dg-field-hint">Allgemein für branchenspezifische Organisationen (z. B. VDE, TÜV-Mitgliedschaft, Fachverband).</p>
    <div class="dg-company-repeater" id="dg-memberships-repeater" data-repeater="memberships">
      <?php foreach ($memberships as $i => $row) : ?>
        <div class="dg-company-repeater__row" data-repeater-row>
          <label class="dg-field dg-field--wide"><span>Organisation</span><input type="text" name="memberships[<?= (int) $i ?>][name]" value="<?= View::escape((string) ($row['name'] ?? '')) ?>" placeholder="z. B. VDE"></label>
          <label class="dg-field">
            <span>Art</span>
            <select name="memberships[<?= (int) $i ?>][obligation]">
              <option value="mandatory"<?= ($row['obligation'] ?? '') === 'mandatory' ? ' selected' : '' ?>>Pflichtmitgliedschaft</option>
              <option value="voluntary"<?= ($row['obligation'] ?? 'voluntary') === 'voluntary' ? ' selected' : '' ?>>Freiwillige Mitgliedschaft</option>
            </select>
          </label>
          <label class="dg-field"><span>Mitgliedsnummer</span><input type="text" name="memberships[<?= (int) $i ?>][member_no]" value="<?= View::escape((string) ($row['member_no'] ?? '')) ?>"></label>
          <label class="dg-field"><span>Ansprechpartner</span><input type="text" name="memberships[<?= (int) $i ?>][contact]" value="<?= View::escape((string) ($row['contact'] ?? '')) ?>"></label>
          <label class="dg-field"><span>Telefon</span><input type="text" name="memberships[<?= (int) $i ?>][phone]" value="<?= View::escape((string) ($row['phone'] ?? '')) ?>"></label>
          <label class="dg-field"><span>E-Mail</span><input type="email" name="memberships[<?= (int) $i ?>][email]" value="<?= View::escape((string) ($row['email'] ?? '')) ?>"></label>
          <label class="dg-field dg-field--wide"><span>Notiz</span><input type="text" name="memberships[<?= (int) $i ?>][notes]" value="<?= View::escape((string) ($row['notes'] ?? '')) ?>"></label>
          <button type="button" class="dg-button dg-button--ghost dg-repeater-remove">Entfernen</button>
        </div>
      <?php endforeach; ?>
    </div>
    <p><button type="button" class="dg-button dg-button--secondary" data-repeater-add="memberships">+ Mitgliedschaft hinzufügen</button></p>
    </div>
  </section>

  <section class="dg-dept-card" data-dept-card data-company-section="bank">
    <header class="dg-dept-accordion__header">
      <button type="button" class="dg-dept-accordion__trigger" data-dept-toggle aria-expanded="false">
        <span class="dg-dept-accordion__icon" aria-hidden="true"></span>
        <span class="dg-dept-accordion__label">
          <strong class="dg-dept-accordion__title">Bankverbindungen &amp; Zahlungsdienstleister</strong>
          <span class="dg-dept-accordion__meta" data-dept-summary><?= View::escape($bankSummary) ?></span>
        </span>
      </button>
    </header>
    <div class="dg-dept-accordion__panel" data-dept-panel hidden>
    <p class="dg-field-hint">IBAN-, BIC- und Bankname-Vorschläge wie in den Kontakten.</p>
    <div id="dg-company-bank-repeater" class="dg-bank-repeater">
      <?php foreach ($bankAccounts as $i => $account) : ?>
        <?php View::render('partials/bank-account-card', compact('i', 'account', 'bankTypes')); ?>
      <?php endforeach; ?>
    </div>
    <p><button type="button" class="dg-button dg-button--secondary" data-bank-add data-bank-repeater="dg-company-bank-repeater">+ Konto / Zahlungsdienst hinzufügen</button></p>
    </div>
  </section>

  </div>

  <div class="dg-form-actions">
    <button type="submit" name="company_save" value="1" class="dg-button dg-button--primary">Firmendaten speichern</button>
  </div>
</form>

<template id="dg-tpl-owners">
  <div class="dg-company-repeater__row dg-company-repeater__row--owners" data-repeater-row>
    <label class="dg-field dg-field--wide"><span>Name</span><input type="text" data-name="owners[__INDEX__][name]" data-owner-name></label>
    <label class="dg-field"><span>Anteil %</span><input type="number" min="0" max="100" step="0.01" data-name="owners[__INDEX__][share_percent]" data-owner-share></label>
    <label class="dg-field dg-field--wide"><span>CRM-Benutzer (optional)</span><select data-name="owners[__INDEX__][user_id]"><option value="0">— optional —</option></select></label>
    <button type="button" class="dg-button dg-button--ghost dg-repeater-remove">Entfernen</button>
  </div>
</template>
<template id="dg-tpl-addresses">
  <div class="dg-company-repeater__row" data-repeater-row>
    <label class="dg-field"><span>Typ</span><select data-name="addresses[__INDEX__][type]"><?php foreach (CompanyAddressTypes::labels() as $typeKey => $typeLabel) : ?><option value="<?= View::escape($typeKey) ?>"><?= View::escape($typeLabel) ?></option><?php endforeach; ?></select></label>
    <label class="dg-field"><span>Bezeichnung</span><input type="text" data-name="addresses[__INDEX__][label]"></label>
    <label class="dg-field dg-field--wide"><span>Straße</span><input type="text" data-name="addresses[__INDEX__][street]"></label>
    <label class="dg-field"><span>PLZ</span><input type="text" data-name="addresses[__INDEX__][postal_code]"></label>
    <label class="dg-field"><span>Ort</span><input type="text" data-name="addresses[__INDEX__][city]"></label>
    <label class="dg-field"><span>Land</span><input type="text" data-name="addresses[__INDEX__][country]" value="DE" maxlength="2"></label>
    <button type="button" class="dg-button dg-button--ghost dg-repeater-remove">Entfernen</button>
  </div>
</template>
<template id="dg-tpl-finanzaemter">
  <div class="dg-company-repeater__row" data-repeater-row>
    <label class="dg-field"><span>BuFa-Nr.</span><input type="text" data-name="finanzaemter[__INDEX__][bufo_nr]"></label>
    <label class="dg-field dg-field--wide"><span>Name</span><input type="text" data-name="finanzaemter[__INDEX__][name]"></label>
    <label class="dg-field dg-field--wide"><span>Straße</span><input type="text" data-name="finanzaemter[__INDEX__][street]"></label>
    <label class="dg-field"><span>PLZ</span><input type="text" data-name="finanzaemter[__INDEX__][postal_code]"></label>
    <label class="dg-field"><span>Ort</span><input type="text" data-name="finanzaemter[__INDEX__][city]"></label>
    <label class="dg-field"><span>Telefon</span><input type="text" data-name="finanzaemter[__INDEX__][phone]"></label>
    <label class="dg-field"><span>E-Mail</span><input type="email" data-name="finanzaemter[__INDEX__][email]"></label>
    <label class="dg-field dg-field--wide"><span>Öffnungszeiten</span><input type="text" data-name="finanzaemter[__INDEX__][opening_hours]"></label>
    <label class="dg-field dg-field--wide"><span>Notiz</span><input type="text" data-name="finanzaemter[__INDEX__][notes]"></label>
    <label class="dg-field dg-field--check"><input type="checkbox" data-name="finanzaemter[__INDEX__][is_primary]" value="1"><span>Haupt-Finanzamt</span></label>
    <button type="button" class="dg-button dg-button--ghost dg-repeater-remove">Entfernen</button>
  </div>
</template>
<template id="dg-tpl-professional_chambers">
  <div class="dg-company-repeater__row" data-repeater-row>
    <label class="dg-field dg-field--wide"><span>Kammer</span><input type="text" data-name="professional_chambers[__INDEX__][name]"></label>
    <label class="dg-field"><span>Mitgliedsnummer</span><input type="text" data-name="professional_chambers[__INDEX__][member_no]"></label>
    <label class="dg-field"><span>Ansprechpartner</span><input type="text" data-name="professional_chambers[__INDEX__][contact]"></label>
    <label class="dg-field"><span>Telefon</span><input type="text" data-name="professional_chambers[__INDEX__][phone]"></label>
    <label class="dg-field"><span>E-Mail</span><input type="email" data-name="professional_chambers[__INDEX__][email]"></label>
    <button type="button" class="dg-button dg-button--ghost dg-repeater-remove">Entfernen</button>
  </div>
</template>
<template id="dg-tpl-trade_associations">
  <div class="dg-company-repeater__row" data-repeater-row>
    <label class="dg-field dg-field--wide"><span>Verband / Innung</span><input type="text" data-name="trade_associations[__INDEX__][name]"></label>
    <label class="dg-field"><span>Mitgliedsnummer</span><input type="text" data-name="trade_associations[__INDEX__][member_no]"></label>
    <label class="dg-field"><span>Ansprechpartner</span><input type="text" data-name="trade_associations[__INDEX__][contact]"></label>
    <label class="dg-field"><span>Telefon</span><input type="text" data-name="trade_associations[__INDEX__][phone]"></label>
    <label class="dg-field"><span>E-Mail</span><input type="email" data-name="trade_associations[__INDEX__][email]"></label>
    <button type="button" class="dg-button dg-button--ghost dg-repeater-remove">Entfernen</button>
  </div>
</template>
<template id="dg-tpl-memberships">
  <div class="dg-company-repeater__row" data-repeater-row>
    <label class="dg-field dg-field--wide"><span>Organisation</span><input type="text" data-name="memberships[__INDEX__][name]" placeholder="z. B. VDE"></label>
    <label class="dg-field"><span>Art</span><select data-name="memberships[__INDEX__][obligation]"><option value="mandatory">Pflichtmitgliedschaft</option><option value="voluntary" selected>Freiwillige Mitgliedschaft</option></select></label>
    <label class="dg-field"><span>Mitgliedsnummer</span><input type="text" data-name="memberships[__INDEX__][member_no]"></label>
    <label class="dg-field"><span>Ansprechpartner</span><input type="text" data-name="memberships[__INDEX__][contact]"></label>
    <label class="dg-field"><span>Telefon</span><input type="text" data-name="memberships[__INDEX__][phone]"></label>
    <label class="dg-field"><span>E-Mail</span><input type="email" data-name="memberships[__INDEX__][email]"></label>
    <label class="dg-field dg-field--wide"><span>Notiz</span><input type="text" data-name="memberships[__INDEX__][notes]"></label>
    <button type="button" class="dg-button dg-button--ghost dg-repeater-remove">Entfernen</button>
  </div>
</template>
