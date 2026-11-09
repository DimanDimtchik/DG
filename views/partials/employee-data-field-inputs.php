<?php
/**
 * Feld-Eingaben einer Mitarbeiterdaten-Sektion.
 *
 * @var string $sectionKey
 * @var array<string, array<string, mixed>> $sectionFields
 * @var array<string, string> $employeeData
 * @var bool $showEmployeeFields
 * @var bool $canEditPayroll
 * @var list<array<string, string>> $bankAccounts
 * @var array<string, string> $disabilitySupplementaryOptions
 */
?>
      <?php foreach ($sectionFields as $key => $meta) : ?>
        <?php
          $name = 'employee[' . $key . ']';
          $value = $employeeData[$key] ?? '';
          $required = !empty($meta['required']) && $showEmployeeFields;
          if ($key === 'social_security_number' && ($employeeData['social_security_status'] ?? 'pending') !== 'received') {
              $required = false;
          }
          $selectOptions = $meta['type'] === 'select' ? EmployeeData::selectOptions($key) : [];
          $payrollReadonly = $sectionKey === 'payroll' && !$canEditPayroll;
          $roAttr = $payrollReadonly ? ' disabled' : '';
        ?>
        <?php if ($sectionKey === 'payroll' && $key === 'payroll_payout_bank_index') : ?>
          <label class="dg-field dg-field--wide">
            <span><?= View::escape($meta['label']) ?></span>
            <select name="<?= View::escape($name) ?>"<?= $roAttr ?>>
              <?php if ($bankAccounts === []) : ?>
                <option value="0">Kein Bankkonto — bitte unter Bankverbindung anlegen</option>
              <?php else : ?>
                <?php foreach ($bankAccounts as $bi => $account) : ?>
                  <?php
                    $iban = trim((string) ($account['iban'] ?? ''));
                    $blabel = trim((string) ($account['label'] ?? ''));
                    $holder = trim((string) ($account['account_holder'] ?? ''));
                    $opt = implode(' · ', array_filter([$blabel !== '' ? $blabel : null, $holder !== '' ? $holder : null, $iban !== '' ? $iban : 'ohne IBAN']));
                  ?>
                  <option value="<?= (int) $bi ?>"<?= (string) (int) $value === (string) (int) $bi ? ' selected' : '' ?>><?= View::escape($opt) ?></option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
            <small class="dg-field-hint">Konten aus dem Block Bankverbindung dieses Kontakts.</small>
          </label>
        <?php elseif ($sectionKey === 'payroll' && $key === 'child_allowances') : ?>
          <label class="dg-field">
            <span><?= View::escape($meta['label']) ?></span>
            <input type="number" name="<?= View::escape($name) ?>" value="<?= View::escape($value) ?>" min="0" max="99" step="0.5" placeholder="0 oder 0,5 …"<?= $roAttr ?>>
          </label>
        <?php elseif ($sectionKey === 'payroll' && $key === 'annual_earnings_estimate_eur') : ?>
          <label class="dg-field">
            <span><?= View::escape($meta['label']) ?></span>
            <input type="number" name="<?= View::escape($name) ?>" value="<?= View::escape($value) ?>" min="0" step="0.01" placeholder="z. B. 22000"<?= $roAttr ?>>
          </label>
        <?php elseif ($sectionKey === 'payroll' && $key === 'sv_exempt') : ?>
          <label class="dg-field dg-field--checkbox">
            <span>
              <input type="checkbox" name="<?= View::escape($name) ?>" value="1"<?= $value === '1' ? ' checked' : '' ?><?= $roAttr ?>>
              <?= View::escape($meta['label']) ?>
            </span>
          </label>
          <p class="dg-field-hint dg-field--wide">Nur wirksam mit Flag „Beherrschender Geschäftsführer“ — sonst beim Speichern verworfen.</p>
        <?php elseif ($meta['type'] === 'textarea') : ?>
          <label class="dg-field dg-field--wide">
            <span><?= View::escape($meta['label']) ?><?= $required ? ' *' : '' ?></span>
            <textarea name="<?= View::escape($name) ?>" rows="3"<?= $required ? ' required data-employee-required' : '' ?><?= $roAttr ?>><?= View::escape($value) ?></textarea>
          </label>
        <?php elseif ($meta['type'] === 'select' && $selectOptions !== []) : ?>
          <label class="dg-field<?= $key === 'social_filing_office' ? ' dg-field--wide' : '' ?>">
            <span><?= View::escape($meta['label']) ?><?= $required ? ' *' : '' ?></span>
            <select
              name="<?= View::escape($name) ?>"
              <?php if ($key === 'social_security_status') : ?>data-social-status<?= $required ? ' required data-employee-required' : '' ?><?php endif; ?>
              <?php if ($key === 'social_filing_office') : ?>data-social-filing-office<?= $required ? ' required data-employee-required' : '' ?><?php endif; ?>
              <?php if ($key === 'gender') : ?><?= $required ? ' required data-employee-required' : '' ?><?php endif; ?>
              <?php if ($key === 'payroll_employment_type') : ?>data-payroll-employment-type<?php endif; ?>
              <?= $roAttr ?>
            >
              <?php foreach ($selectOptions as $optVal => $optLabel) : ?>
                <option value="<?= View::escape((string) $optVal) ?>"<?= (string) $value === (string) $optVal ? ' selected' : '' ?>><?= View::escape($optLabel) ?></option>
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
              <input type="checkbox" name="<?= View::escape($name) ?>" value="1"<?= $value === '1' ? ' checked' : '' ?><?= $roAttr ?>>
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
              <?= $roAttr ?>
            >
          </label>
        <?php elseif ($key === 'health_insurance_website') : ?>
          <label class="dg-field dg-field--wide">
            <span><?= View::escape($meta['label']) ?><?= $required ? ' *' : '' ?></span>
            <input
              type="url"
              inputmode="url"
              name="<?= View::escape($name) ?>"
              value="<?= View::escape($value) ?>"
              data-health-insurer-website
              placeholder="https://…"
              <?= $required ? ' required data-employee-required' : '' ?>
              <?= $roAttr ?>
            >
          </label>
        <?php elseif ($key === 'health_insurance_appointment_url') : ?>
          <label class="dg-field dg-field--wide">
            <span><?= View::escape($meta['label']) ?><?= $required ? ' *' : '' ?></span>
            <input
              type="url"
              inputmode="url"
              name="<?= View::escape($name) ?>"
              value="<?= View::escape($value) ?>"
              data-health-insurer-appointment
              placeholder="https://…"
              <?= $required ? ' required data-employee-required' : '' ?>
              <?= $roAttr ?>
            >
          </label>
        <?php elseif ($key === 'person_group_key' || $key === 'contribution_group_key') : ?>
          <label class="dg-field">
            <span><?= View::escape($meta['label']) ?><?= $required ? ' *' : '' ?></span>
            <input
              type="text"
              inputmode="numeric"
              name="<?= View::escape($name) ?>"
              value="<?= View::escape($value) ?>"
              <?php if ($key === 'person_group_key') : ?>data-person-group-key maxlength="3" placeholder="z. B. 101"<?php endif; ?>
              <?php if ($key === 'contribution_group_key') : ?>data-contribution-group-key maxlength="8" placeholder="z. B. 1111"<?php endif; ?>
              <?= $required ? ' required data-employee-required' : '' ?>
              <?= $roAttr ?>
            >
            <?php if ($key === 'person_group_key') : ?>
              <small class="dg-field-hint" data-sv-group-hint>Wird aus der Beschäftigungsart (Lohn) vorgeschlagen, wenn leer oder Standardwert.</small>
            <?php else : ?>
              <small class="dg-field-hint">Vier Stellen KV·RV·AV·PV — Vorschlag aus Beschäftigungsart, jederzeit änderbar.</small>
            <?php endif; ?>
          </label>
        <?php elseif ($key === 'training_video_url') : ?>
          <?php
            $trainingLinkOptions = class_exists('AcademyRepository')
              ? AcademyRepository::trainingLinkOptions()
              : [];
            $trainingCourses = [];
            $trainingVideos = [];
            foreach ($trainingLinkOptions as $opt) {
                if (($opt['kind'] ?? '') === 'course') {
                    $trainingCourses[] = $opt;
                } else {
                    $trainingVideos[] = $opt;
                }
            }
          ?>
          <label class="dg-field dg-field--wide" data-training-video-field>
            <span><?= View::escape($meta['label']) ?><?= $required ? ' *' : '' ?></span>
            <input
              type="url"
              inputmode="url"
              name="<?= View::escape($name) ?>"
              value="<?= View::escape($value) ?>"
              data-training-video-url
              placeholder="https://…"
              <?= $required ? ' required data-employee-required' : '' ?>
              <?= $roAttr ?>
            >
            <?php if ($trainingLinkOptions !== []) : ?>
              <select data-training-video-pick<?= $roAttr !== '' ? ' disabled' : '' ?> aria-label="Aus Akademie wählen">
                <option value="">— Kurs oder Video aus Akademie wählen —</option>
                <?php if ($trainingCourses !== []) : ?>
                  <optgroup label="Kurse">
                    <?php foreach ($trainingCourses as $opt) : ?>
                      <option value="<?= View::escape((string) $opt['url']) ?>"<?= $value === (string) $opt['url'] ? ' selected' : '' ?>>
                        <?= View::escape((string) $opt['label']) ?>
                      </option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endif; ?>
                <?php if ($trainingVideos !== []) : ?>
                  <optgroup label="Videos">
                    <?php foreach ($trainingVideos as $opt) : ?>
                      <option value="<?= View::escape((string) $opt['url']) ?>"<?= $value === (string) $opt['url'] ? ' selected' : '' ?>>
                        <?= View::escape((string) $opt['label']) ?>
                      </option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endif; ?>
              </select>
              <small class="dg-field-hint">Auswahl übernimmt den Link. Externe URLs bleiben manuell möglich.</small>
            <?php else : ?>
              <small class="dg-field-hint">Noch keine veröffentlichten Akademie-Kurse oder Videos — Link manuell eintragen oder unter Akademie anlegen.</small>
            <?php endif; ?>
          </label>
        <?php else : ?>
          <label class="dg-field">
            <span><?= View::escape($meta['label']) ?><?= $required ? ' *' : '' ?></span>
            <input type="<?= View::escape($meta['type']) ?>" name="<?= View::escape($name) ?>" value="<?= View::escape($value) ?>"<?= $required ? ' required data-employee-required' : '' ?><?= $roAttr ?>>
          </label>
        <?php endif; ?>
      <?php endforeach; ?>
