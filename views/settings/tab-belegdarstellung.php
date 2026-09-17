<?php
/**
 * @var array<string, mixed> $documentPresentationSettings
 * @var bool $dbConnected
 */
$settings = $documentPresentationSettings ?? DocumentPresentationSettings::forForm();
$texts = is_array($settings['texts'] ?? null) ? $settings['texts'] : [];
$deposit = is_array($settings['deposit'] ?? null) ? $settings['deposit'] : DocumentPresentationSettings::defaults()['deposit'];
$ku = is_array($settings['kleinunternehmer'] ?? null) ? $settings['kleinunternehmer'] : DocumentPresentationSettings::defaults()['kleinunternehmer'];
$kindLabels = [
    VoucherDocumentKind::OFFER => 'Angebot',
    VoucherDocumentKind::ORDER_CONFIRMATION => 'Auftragsbestätigung',
    VoucherDocumentKind::DELIVERY_NOTE => 'Lieferschein',
    VoucherDocumentKind::PARTIAL_INVOICE => 'Abschlagsrechnung',
    VoucherDocumentKind::INVOICE => 'Rechnung',
    VoucherDocumentKind::FINAL_INVOICE => 'Schlussrechnung',
];
?>
<form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('belegdarstellung')) ?>">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Zum Speichern ist eine funktionierende <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Datenbankverbindung</a> erforderlich.
    </div>
  <?php endif; ?>

  <p class="dg-field-hint">
    Kundendarstellung der Belegkette (Angebot → … → Rechnung). Nummern bleiben unter
    <a href="<?= View::escape(SettingsRegistry::tabUrl('nummernkreise')) ?>">Nummernkreise</a>.
    Pro Beleg bleiben Intro/Footer weiterhin editierbar; hier liegen die Vorlagen.
  </p>

  <section class="dg-form-section">
    <h3 class="dg-subsection-title">Angebot — Gültigkeit</h3>
    <label class="dg-field">
      <span>Standard „gültig bis“ (Tage ab Angebotsdatum)</span>
      <input type="number" name="offer_valid_days" min="1" max="365" step="1"
        value="<?= (int) ($settings['offer_valid_days'] ?? 14) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
      <small class="dg-field-hint">Wenn am Beleg kein Datum gesetzt ist, gilt dieser Zeitraum. Platzhalter <code>{valid_until}</code> im Footer.</small>
    </label>
  </section>

  <section class="dg-form-section">
    <h3 class="dg-subsection-title">Textvorlagen vor / nach Positionen</h3>
    <?php foreach ($kindLabels as $kind => $label) : ?>
      <?php $pair = is_array($texts[$kind] ?? null) ? $texts[$kind] : ['intro' => '', 'footer' => '']; ?>
      <fieldset class="dg-fieldset" style="margin-bottom:16px;">
        <legend><?= View::escape($label) ?></legend>
        <label class="dg-field dg-field--wide">
          <span>Text vor den Positionen</span>
          <textarea name="texts[<?= View::escape($kind) ?>][intro]" rows="3"<?= !$dbConnected ? ' disabled' : '' ?>><?= View::escape((string) ($pair['intro'] ?? '')) ?></textarea>
        </label>
        <label class="dg-field dg-field--wide">
          <span>Text nach den Positionen</span>
          <textarea name="texts[<?= View::escape($kind) ?>][footer]" rows="3"<?= !$dbConnected ? ' disabled' : '' ?>><?= View::escape((string) ($pair['footer'] ?? '')) ?></textarea>
        </label>
      </fieldset>
    <?php endforeach; ?>
  </section>

  <section class="dg-form-section">
    <h3 class="dg-subsection-title">Anzahlung / Abschlag</h3>
    <p class="dg-field-hint">
      Regelung für die Kette (Anzeige auf Angebot/AB). Materialbasiert nutzt später die Einkaufskosten aus Lagerpositionen.
    </p>
    <label class="dg-field">
      <span>Modus</span>
      <select name="deposit_mode"<?= !$dbConnected ? ' disabled' : '' ?>>
        <option value="none"<?= ($deposit['mode'] ?? '') === 'none' ? ' selected' : '' ?>>Keine Anzahlung</option>
        <option value="percent"<?= ($deposit['mode'] ?? '') === 'percent' ? ' selected' : '' ?>>Prozentual vom Belegbetrag</option>
        <option value="fixed"<?= ($deposit['mode'] ?? '') === 'fixed' ? ' selected' : '' ?>>Fester Betrag (manuell)</option>
        <option value="material"<?= ($deposit['mode'] ?? '') === 'material' ? ' selected' : '' ?>>Materialbasiert (Einkauf)</option>
      </select>
    </label>
    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Prozent</span>
        <input type="number" name="deposit_percent" min="0" max="100" step="0.01"
          value="<?= View::escape((string) ($deposit['percent'] ?? '30')) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
      </label>
      <label class="dg-field">
        <span>Fester Betrag (€)</span>
        <input type="number" name="deposit_fixed_amount" min="0" step="0.01"
          value="<?= View::escape((string) ($deposit['fixed_amount'] ?? '0')) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
      </label>
    </div>
    <label class="dg-field dg-field--wide">
      <span>Bezeichnung</span>
      <input type="text" name="deposit_label" maxlength="120"
        value="<?= View::escape((string) ($deposit['label'] ?? '')) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
    </label>
    <label class="dg-field dg-field--wide">
      <span>Erläuterungstext (Kundenansicht)</span>
      <textarea name="deposit_text" rows="3"<?= !$dbConnected ? ' disabled' : '' ?>><?= View::escape((string) ($deposit['text'] ?? '')) ?></textarea>
    </label>
  </section>

  <section class="dg-form-section">
    <h3 class="dg-subsection-title">Kleinunternehmer § 19 UStG</h3>
    <p class="dg-field-hint">
      Bei aktiver Regelung wird der Hinweis automatisch auf buchbaren Ausgangsbelegen im Zeitraum mitgedruckt.
      Vorzeitiger Abbruch endet die Regelung ab dem gesetzten Datum (einschließlich).
    </p>
    <label class="dg-field dg-field--checkbox">
      <input type="checkbox" name="kleinunternehmer_enabled" value="1"
        <?= !empty($ku['enabled']) ? ' checked' : '' ?><?= !$dbConnected ? ' disabled' : '' ?>>
      <span>Kleinunternehmerregelung aktiv</span>
    </label>
    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Gültig von</span>
        <input type="date" name="kleinunternehmer_valid_from"
          value="<?= View::escape((string) ($ku['valid_from'] ?? '')) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
      </label>
      <label class="dg-field">
        <span>Gültig bis (geplant)</span>
        <input type="date" name="kleinunternehmer_valid_to"
          value="<?= View::escape((string) ($ku['valid_to'] ?? '')) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
      </label>
      <label class="dg-field">
        <span>Vorzeitiger Abbruch ab</span>
        <input type="date" name="kleinunternehmer_ended_early_at"
          value="<?= View::escape((string) ($ku['ended_early_at'] ?? '')) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
      </label>
    </div>
    <label class="dg-field dg-field--wide">
      <span>Hinweistext auf Belegen</span>
      <textarea name="kleinunternehmer_hint_text" rows="2"<?= !$dbConnected ? ' disabled' : '' ?>><?= View::escape((string) ($ku['hint_text'] ?? '')) ?></textarea>
    </label>
  </section>

  <button type="submit" name="document_presentation_save" value="1" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>>
    Belegdarstellung speichern
  </button>
</form>
