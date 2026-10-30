<?php
/**
 * Lohn L1c — Arbeitgeber-Stamm (Settings).
 *
 * @var array<string, mixed> $payrollEmployerForm
 * @var bool $dbConnected
 */
$form = $payrollEmployerForm ?? PayrollEmployerSettings::forForm();
$lohn = is_array($form['lohn'] ?? null) ? $form['lohn'] : PayrollEmployerSettings::lohnDefaults();
$snap = is_array($form['snapshot'] ?? null) ? $form['snapshot'] : [];
$warnings = is_array($form['warnings'] ?? null) ? $form['warnings'] : [];
$bundeslandLabels = is_array($form['bundesland_labels'] ?? null) ? $form['bundesland_labels'] : PayrollEmployerSettings::bundeslandLabels();
$bankOptions = is_array($form['bank_options'] ?? null) ? $form['bank_options'] : [];
$addr = is_array($snap['address'] ?? null) ? $snap['address'] : [];
$fa = is_array($snap['finanzamt'] ?? null) ? $snap['finanzamt'] : [];
$bg = is_array($snap['bg_data'] ?? null) ? $snap['bg_data'] : [];
$firmaUrl = SettingsRegistry::tabUrl('firmendaten');
?>
<form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('lohn')) ?>">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Zum Speichern ist eine funktionierende <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Datenbankverbindung</a> erforderlich.
    </div>
  <?php endif; ?>

  <?php if ($warnings !== []) : ?>
    <div class="dg-flash dg-flash--warning">
      <p><strong>Hinweise zur Vollständigkeit</strong> (Speichern der Lohn-Felder bleibt möglich; Monatslauf erst später):</p>
      <ul>
        <?php foreach ($warnings as $w) : ?>
          <li><?= View::escape((string) $w) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <section class="dg-form-section">
    <h3 class="dg-subsection-title">Aus Firmendaten (nur Anzeige)</h3>
    <p class="dg-field-hint">
      Name, Steuernummer, Betriebsnummer, Finanzamt, BG und Bankkonten pflegen Sie unter
      <a href="<?= View::escape($firmaUrl) ?>">Einstellungen → Firmendaten</a> — hier keine Doppelpflege.
    </p>
    <div class="dg-form-grid">
      <div class="dg-field">
        <span>Arbeitgeber</span>
        <p class="dg-readonly"><?= View::escape((string) ($snap['employer_name'] ?? '—')) ?></p>
      </div>
      <div class="dg-field">
        <span>Steuernummer (ESt/LSt)</span>
        <p class="dg-readonly"><?= View::escape((string) (($snap['tax_number_est'] ?? '') !== '' ? $snap['tax_number_est'] : '—')) ?></p>
      </div>
      <div class="dg-field">
        <span>Betriebsnummer</span>
        <p class="dg-readonly"><?= View::escape((string) (($snap['betriebsnummer'] ?? '') !== '' ? $snap['betriebsnummer'] : '—')) ?></p>
      </div>
      <div class="dg-field">
        <span>Finanzamt</span>
        <p class="dg-readonly">
          <?php
            $faLabel = trim(implode(' · ', array_filter([
                (string) ($fa['name'] ?? ''),
                ($fa['bufo_nr'] ?? '') !== '' ? 'BuFö ' . (string) $fa['bufo_nr'] : '',
            ])));
            echo View::escape($faLabel !== '' ? $faLabel : '—');
          ?>
        </p>
      </div>
      <div class="dg-field dg-field--wide">
        <span>Anschrift</span>
        <p class="dg-readonly">
          <?= View::escape(trim(implode(', ', array_filter([
              (string) ($addr['street'] ?? ''),
              trim((string) ($addr['postal'] ?? '') . ' ' . (string) ($addr['city'] ?? '')),
              (string) ($addr['country'] ?? ''),
          ]))) ?: '—') ?>
        </p>
      </div>
      <div class="dg-field">
        <span>BG / UV (Mitgliedsnr.)</span>
        <p class="dg-readonly">
          <?= View::escape((string) (($bg['company_number'] ?? $bg['member_no'] ?? '') !== ''
              ? (string) ($bg['company_number'] ?? $bg['member_no'] ?? '')
              : '—')) ?>
        </p>
      </div>
    </div>
  </section>

  <section class="dg-form-section">
    <h3 class="dg-subsection-title">Lohn-Stammdaten Arbeitgeber</h3>

    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Bundesland (Lohnsteuer / KiSt)</span>
        <select name="lohn[bundesland]"<?= !$dbConnected ? ' disabled' : '' ?>>
          <option value="">— bitte wählen —</option>
          <?php foreach ($bundeslandLabels as $code => $label) : ?>
            <option value="<?= View::escape($code) ?>"<?= (($lohn['bundesland'] ?? '') === $code) ? ' selected' : '' ?>>
              <?= View::escape($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <small class="dg-field-hint">Nicht automatisch aus PLZ abgeleitet — bewusst setzen.</small>
      </label>

      <label class="dg-field">
        <span>SEPA-Absenderkonto</span>
        <select name="lohn[payroll_bank_account_index]"<?= !$dbConnected ? ' disabled' : '' ?>>
          <?php foreach ($bankOptions as $opt) : ?>
            <option value="<?= (int) $opt['index'] ?>"<?= ((int) ($lohn['payroll_bank_account_index'] ?? 0) === (int) $opt['index']) ? ' selected' : '' ?>>
              <?= View::escape((string) $opt['label']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <small class="dg-field-hint">Kontenliste aus Firmendaten. Live-SEPA erst nach Pilot (Flag in L8).</small>
      </label>
    </div>

    <fieldset class="dg-form-section" style="border:0;padding:0;margin:1rem 0 0">
      <legend class="dg-subsection-title" style="font-size:1rem">Umlagen</legend>
      <p class="dg-field-hint">Teilnahme bewusst setzen (auch „nein“). Werte fließen später in die Berechnung (L4).</p>
      <label class="dg-field dg-field--checkbox">
        <span>
          <input type="checkbox" name="lohn[umlage_u1]" value="1"<?= !empty($lohn['umlage_u1']) ? ' checked' : '' ?><?= !$dbConnected ? ' disabled' : '' ?>>
          U1 (Krankheit / Ausgleichsverfahren)
        </span>
      </label>
      <label class="dg-field">
        <span>U1-Erstattungssatz (Schlüssel)</span>
        <input type="text" name="lohn[u1_erstattungssatz_key]" maxlength="64"
               value="<?= View::escape((string) ($lohn['u1_erstattungssatz_key'] ?? '')) ?>"
               placeholder="z. B. Tarifkennzeichen — Werte ab L4"
               <?= !$dbConnected ? ' disabled' : '' ?>>
      </label>
      <label class="dg-field dg-field--checkbox">
        <span>
          <input type="checkbox" name="lohn[umlage_u2]" value="1"<?= !empty($lohn['umlage_u2']) ? ' checked' : '' ?><?= !$dbConnected ? ' disabled' : '' ?>>
          U2 (Mutterschaft)
        </span>
      </label>
      <label class="dg-field dg-field--checkbox">
        <span>
          <input type="checkbox" name="lohn[umlage_u3]" value="1"<?= !empty($lohn['umlage_u3']) ? ' checked' : '' ?><?= !$dbConnected ? ' disabled' : '' ?>>
          U3 (Insolvenzgeldumlage)
        </span>
      </label>
    </fieldset>

    <label class="dg-field dg-field--checkbox" style="margin-top:1rem">
      <span>
        <input type="checkbox" name="lohn[baulohn_enabled]" value="1"<?= !empty($lohn['baulohn_enabled']) ? ' checked' : '' ?><?= !$dbConnected ? ' disabled' : '' ?>>
        Baulohn aktiv (nur Flag — ohne Funktion bis Stufe C)
      </span>
    </label>

    <label class="dg-field dg-field--wide">
      <span>Interne Notiz (Prüfung)</span>
      <textarea name="lohn[notes]" rows="3" maxlength="2000"<?= !$dbConnected ? ' disabled' : '' ?>><?= View::escape((string) ($lohn['notes'] ?? '')) ?></textarea>
    </label>
  </section>

  <div class="dg-form-actions">
    <button type="submit" name="payroll_lohn_save" value="1" class="dg-button dg-button--primary"<?= $dbConnected ? '' : ' disabled' ?>>Lohn-Stammdaten speichern</button>
    <a class="dg-button" href="<?= View::escape($firmaUrl) ?>">Zu Firmendaten</a>
  </div>
</form>
