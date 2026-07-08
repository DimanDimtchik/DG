<?php
/**
 * @var string $numberRangeType
 * @var array<string, mixed> $numberRangeDoc
 * @var array<string, string> $numberRangeTypes
 * @var bool $dbConnected
 * @var list<array<string, mixed>> $numberRangeHistory
 */
$referenceGroups = InvoiceNumberTokens::referenceGroups();
$numberBases = InvoiceNumberTokens::numberBases();
$countryCodes = CountryCodes::all();
$preview = NumberRangeSettings::preview($numberRangeType);
$seqDecimal = InvoiceNumberBuilder::sequenceCounter($numberRangeDoc);
$seqDisplay = InvoiceNumberBuilder::formatSequenceValue(
    $seqDecimal,
    (string) ($numberRangeDoc['number_display'] ?? 'decimal'),
    (int) ($numberRangeDoc['number_pad'] ?? 0)
);
$usesCountry = InvoiceNumberTokens::usesCountryPlaceholder($numberRangeDoc);
$typeLabel = $numberRangeTypes[$numberRangeType] ?? $numberRangeType;
$typeGroups = NumberRangeSettings::typeGroups();
$historyActive = array_values(array_filter(
    $numberRangeHistory,
    static fn(array $row): bool => !empty($row['is_active'])
));
$historyArchive = array_values(array_filter(
    $numberRangeHistory,
    static fn(array $row): bool => empty($row['is_active'])
));
?>
<form
  class="dg-form"
  method="post"
  action="<?= View::escape(SettingsRegistry::tabUrl('nummernkreise') . '&ntype=' . rawurlencode($numberRangeType)) ?>"
  id="dg-number-range-form"
  data-number-range-form
>
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
  <input type="hidden" name="number_range_type" value="<?= View::escape($numberRangeType) ?>">

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Zum Speichern ist eine funktionierende <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Datenbankverbindung</a> erforderlich.
    </div>
  <?php endif; ?>

  <p class="dg-lead">
    Belegnummer aus Prefix, Nummer und Suffix. Schreiben Sie Platzhalter direkt ins Feld — z. B.
    <code>RE-{JJJJ}-</code> + <code>{NR}</code> + <code>-{LAND}</code>.
    Groß-/Kleinschreibung ist egal (<code>{jj}</code> = <code>{JJ}</code>). Der Zähler für <code>{NR}</code> wird intern dezimal geführt (+1 bei Vergabe).
  </p>

  <nav class="dg-subtabs" aria-label="Nummernkreis-Typ">
    <?php foreach ($typeGroups as $groupLabel => $groupTypes) : ?>
      <div class="dg-subtabs__group">
        <span class="dg-subtabs__group-label"><?= View::escape($groupLabel) ?></span>
        <?php foreach ($groupTypes as $typeKey) : ?>
          <?php if (!isset($numberRangeTypes[$typeKey])) { continue; } ?>
          <?php $typeName = $numberRangeTypes[$typeKey]; ?>
          <a
            href="<?= View::escape(SettingsRegistry::tabUrl('nummernkreise') . '&ntype=' . rawurlencode($typeKey)) ?>"
            class="dg-subtabs__link<?= $numberRangeType === $typeKey ? ' is-active' : '' ?>"
            <?= $numberRangeType === $typeKey ? 'aria-current="page"' : '' ?>
          ><?= View::escape($typeName) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </nav>

  <div class="dg-number-range-doc">
    <h3 class="dg-subsection-title"><?= View::escape($typeLabel) ?></h3>

    <section class="dg-number-range-codes" aria-label="Verfügbare Platzhalter">
      <h4 class="dg-subsection-title">Code-Kürzel</h4>
      <p class="dg-field-hint">Klick auf ein Kürzel fügt es in das zuletzt fokussierte Feld ein (Prefix, Nummer oder Suffix).</p>
      <?php foreach ($referenceGroups as $group) : ?>
        <div class="dg-number-range-code-group">
          <h5 class="dg-number-range-code-group__title"><?= View::escape((string) $group['title']) ?></h5>
          <ul class="dg-number-range-code-list">
            <?php foreach ($group['items'] as $item) : ?>
              <li class="dg-number-range-code-list__item">
                <span class="dg-number-range-code-list__label"><?= View::escape((string) $item['label']) ?></span>
                <span class="dg-number-range-code-list__codes">
                  <?php foreach ($item['codes'] as $codeIndex => $code) : ?>
                    <?php if ($codeIndex > 0) : ?><span class="dg-number-range-code-list__sep">oder</span><?php endif; ?>
                    <button type="button" class="dg-code-chip" data-insert-code="<?= View::escape($code) ?>"><?= View::escape($code) ?></button>
                  <?php endforeach; ?>
                  <?php if (!empty($item['hint'])) : ?>
                    <span class="dg-number-range-code-list__example">→ <?= View::escape((string) $item['hint']) ?></span>
                  <?php endif; ?>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    </section>

    <div class="dg-form-grid">
      <label class="dg-field dg-field--wide">
        <span>Prefix</span>
        <input
          type="text"
          class="dg-input"
          name="number_range[prefix]"
          value="<?= View::escape((string) ($numberRangeDoc['prefix'] ?? '')) ?>"
          placeholder="z. B. RE-{JJJJ}-"
          data-number-range-part
        >
      </label>
      <label class="dg-field dg-field--wide">
        <span>Nummer</span>
        <input
          type="text"
          class="dg-input"
          name="number_range[number_pattern]"
          value="<?= View::escape((string) ($numberRangeDoc['number_pattern'] ?? '{NR}')) ?>"
          placeholder="{NR}"
          data-number-range-part
        >
        <small class="dg-field-hint">Laufende Nummer über <code>{NR}</code> — Format unten (Dezimal/Hex, führende Nullen).</small>
      </label>
      <label class="dg-field dg-field--wide">
        <span>Suffix</span>
        <input
          type="text"
          class="dg-input"
          name="number_range[suffix]"
          value="<?= View::escape((string) ($numberRangeDoc['suffix'] ?? '')) ?>"
          placeholder="z. B. -{LAND}"
          data-number-range-part
        >
      </label>
    </div>

    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Aktueller Zählerstand</span>
        <input
          type="number"
          min="0"
          step="1"
          class="dg-input dg-input--small"
          name="number_range[counter]"
          value="<?= View::escape((string) ($numberRangeDoc['counter'] ?? '1')) ?>"
          data-number-range-counter
        >
        <small class="dg-field-hint" data-number-range-counter-hint>
          Dezimal <?= View::escape((string) $seqDecimal) ?> → Anzeige für <code>{NR}</code>: <?= View::escape($seqDisplay) ?>
        </small>
      </label>
      <label class="dg-field">
        <span>Zähler-Darstellung für <code>{NR}</code></span>
        <select name="number_range[number_display]" data-number-range-display>
          <?php foreach ($numberBases as $baseKey => $baseLabel) : ?>
            <option value="<?= View::escape($baseKey) ?>"<?= ($numberRangeDoc['number_display'] ?? 'decimal') === $baseKey ? ' selected' : '' ?>>
              <?= View::escape($baseLabel) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field">
        <span>Mindestlänge (Dezimal, führende Nullen)</span>
        <input
          type="number"
          min="0"
          max="12"
          step="1"
          class="dg-input dg-input--small"
          name="number_range[number_pad]"
          value="<?= View::escape((string) (int) ($numberRangeDoc['number_pad'] ?? 0)) ?>"
          data-number-range-pad
        >
      </label>
      <label class="dg-field dg-field--wide">
        <span>Standard-Länderkürzel für <code>{LAND}</code></span>
        <select name="number_range[country_code]" data-number-range-country>
          <?php
            $selectedCountry = strtoupper((string) ($numberRangeDoc['country_code'] ?? 'DE'));
            $countries = $countryCodes;
            if (isset($countries['DE'])) {
                echo '<option value="DE"' . ($selectedCountry === 'DE' ? ' selected' : '') . '>DE – ' . View::escape($countries['DE']) . '</option>';
                unset($countries['DE']);
            }
            foreach ($countries as $code => $name) :
          ?>
            <option value="<?= View::escape($code) ?>"<?= $selectedCountry === $code ? ' selected' : '' ?>>
              <?= View::escape($code . ' – ' . $name) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <small class="dg-field-hint" id="dg-number-range-country-hint"<?= $usesCountry ? '' : ' hidden' ?>>
          Wird verwendet, wenn <code>{LAND}</code> in Prefix, Nummer oder Suffix steht.
        </small>
      </label>
    </div>

    <fieldset class="dg-field dg-field--wide dg-number-range-increment">
      <legend>Erhöhen bei nächster Nummer</legend>
      <label><input type="radio" name="number_range[increment_part]" value="prefix"<?= ($numberRangeDoc['increment_part'] ?? 'number') === 'prefix' ? ' checked' : '' ?>> Prefix</label>
      <label><input type="radio" name="number_range[increment_part]" value="number"<?= ($numberRangeDoc['increment_part'] ?? 'number') === 'number' ? ' checked' : '' ?>> Nummer (Standard)</label>
      <label><input type="radio" name="number_range[increment_part]" value="suffix"<?= ($numberRangeDoc['increment_part'] ?? 'number') === 'suffix' ? ' checked' : '' ?>> Suffix</label>
    </fieldset>

    <p class="dg-field dg-field--wide">
      <span class="dg-field__label">Vorschau</span>
      <code class="dg-number-range-preview" id="dg-number-range-preview"><?= View::escape($preview) ?></code>
    </p>
  </div>

  <div class="dg-form-actions">
    <button type="submit" name="number_ranges_save" value="1" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>>Nummernkreis speichern</button>
  </div>
</form>

<section class="dg-panel dg-number-range-history">
  <h3 class="dg-subsection-title">Frühere Formeln</h3>
  <p class="dg-field-hint">
    Einträge entstehen beim Speichern, wenn sich die Formel gegenüber dem bisherigen Stand ändert.
  </p>

  <?php if ($numberRangeHistory === []) : ?>
    <p class="dg-muted">Noch keine früheren Formeln archiviert.</p>
  <?php else : ?>
    <div class="dg-notify-accordion" id="dg-number-range-history">
      <details class="dg-notify-section">
        <summary class="dg-notify-section__summary">
          <strong>Aktiv<?= $historyActive !== [] ? ' (' . count($historyActive) . ')' : '' ?></strong>
          <span class="dg-muted">Aktuell gültige Formel für <?= View::escape($typeLabel) ?></span>
        </summary>
        <div class="dg-notify-section__body">
          <?php View::partial('settings/partials/number-range-history-table', [
              'historyRows' => $historyActive,
              'showTypeColumn' => false,
          ]); ?>
        </div>
      </details>

      <details class="dg-notify-section">
        <summary class="dg-notify-section__summary">
          <strong>Archiv<?= $historyArchive !== [] ? ' (' . count($historyArchive) . ')' : '' ?></strong>
          <span class="dg-muted">Beendete Formeln mit Zeitraum und Zählerstand</span>
        </summary>
        <div class="dg-notify-section__body">
          <?php View::partial('settings/partials/number-range-history-table', [
              'historyRows' => $historyArchive,
              'showTypeColumn' => false,
          ]); ?>
        </div>
      </details>
    </div>
  <?php endif; ?>
</section>

<?php
$paymentReferenceFormula = PaymentReferenceFormula::formula();
$paymentReferenceTokens = PaymentReferenceFormula::tokens();
$paymentReferencePreview = PaymentReferenceFormula::resolve($paymentReferenceFormula, [
    'invoice_number' => 'R-2026-0815',
    'invoice_date' => date('Y-m-d'),
    'customer_number' => 'K-4711',
    'company_name' => CompanySettings::displayName() ?: 'Meine Firma',
    'supplier_name' => 'Muster Lieferant GmbH',
    'amount' => 199.00,
]);
?>
<section class="dg-panel dg-payment-reference">
  <h3 class="dg-subsection-title">Verwendungszweck für Überweisungen</h3>
  <p class="dg-lead">
    Formel für den Verwendungszweck, wenn aus einem offenen Lieferantenbeleg eine Überweisung vorbereitet wird.
    Platzhalter in eckigen Klammern <code>[…]</code> entfallen automatisch, wenn der Wert leer ist
    (z. B. <code>[ / KdNr {KUNDENNR}]</code>).
  </p>

  <form
    class="dg-form"
    method="post"
    action="<?= View::escape(SettingsRegistry::tabUrl('nummernkreise')) ?>"
    id="dg-payment-reference-form"
  >
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

    <section class="dg-number-range-codes" aria-label="Verfügbare Platzhalter">
      <h4 class="dg-subsection-title">Code-Kürzel</h4>
      <ul class="dg-number-range-code-list">
        <?php foreach ($paymentReferenceTokens as $code => $desc) : ?>
          <li class="dg-number-range-code-list__item">
            <span class="dg-number-range-code-list__codes">
              <button type="button" class="dg-code-chip" data-insert-reference="<?= View::escape($code) ?>"><?= View::escape($code) ?></button>
            </span>
            <span class="dg-number-range-code-list__label"><?= View::escape($desc) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>

    <label class="dg-field dg-field--wide">
      <span>Formel</span>
      <input
        type="text"
        class="dg-input"
        name="payment_reference_formula"
        id="dg-payment-reference-input"
        value="<?= View::escape($paymentReferenceFormula) ?>"
        placeholder="RE {RENR} vom {REDATUM}[ / KdNr {KUNDENNR}] / {FIRMA}"
      >
      <small class="dg-field-hint">
        Beispiel: <code class="dg-payment-reference-preview" id="dg-payment-reference-preview"><?= View::escape($paymentReferencePreview) ?></code>
      </small>
    </label>

    <div class="dg-form-actions">
      <button type="submit" name="payment_reference_save" value="1" class="dg-button dg-button--primary">Verwendungszweck-Formel speichern</button>
    </div>
  </form>
</section>

<script>
  (function () {
    var input = document.getElementById('dg-payment-reference-input');
    if (!input) { return; }
    document.querySelectorAll('[data-insert-reference]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var code = btn.getAttribute('data-insert-reference');
        var start = input.selectionStart != null ? input.selectionStart : input.value.length;
        var end = input.selectionEnd != null ? input.selectionEnd : input.value.length;
        input.value = input.value.slice(0, start) + code + input.value.slice(end);
        input.focus();
        input.selectionStart = input.selectionEnd = start + code.length;
      });
    });
  })();
</script>
