<?php
/**
 * Briefpapier / Schreibendesign (Dokumentenverfasser B2/B3).
 *
 * @var array<string, mixed> $letterheadSettings
 * @var array<string, mixed> $letterheadCompany
 * @var list<array{key: string, label: string}> $letterheadMissingMandatory
 * @var array<string, mixed> $letterheadCompliance
 * @var bool $dbConnected
 */
$cfg = $letterheadSettings ?? LetterheadSettings::forForm();
$party = $letterheadCompany ?? LetterheadSettings::resolvedCompany($cfg);
$compliance = $letterheadCompliance ?? LetterheadSettings::complianceReport($cfg);
$missing = $compliance['mandatory_missing'] ?? ($letterheadMissingMandatory ?? []);
$recommendedGaps = $compliance['recommended_gaps'] ?? [];
$dinOff = $compliance['din']['off'] ?? [];
$dinCriticalOff = $compliance['din']['critical_off'] ?? [];
$page = is_array($cfg['page'] ?? null) ? $cfg['page'] : [];
$header = is_array($cfg['header'] ?? null) ? $cfg['header'] : [];
$sender = is_array($cfg['sender_line'] ?? null) ? $cfg['sender_line'] : [];
$window = is_array($cfg['address_window'] ?? null) ? $cfg['address_window'] : [];
$info = is_array($cfg['info_block'] ?? null) ? $cfg['info_block'] : [];
$subject = is_array($cfg['subject'] ?? null) ? $cfg['subject'] : [];
$footer = is_array($cfg['footer'] ?? null) ? $cfg['footer'] : [];
$links = is_array($cfg['links'] ?? null) ? $cfg['links'] : [];
$bg = is_array($cfg['background'] ?? null) ? $cfg['background'] : [];
$export = is_array($cfg['export_formats'] ?? null) ? $cfg['export_formats'] : [];
$legal = is_array($cfg['legal_overrides'] ?? null) ? $cfg['legal_overrides'] : [];
$infoFields = LetterheadSettings::infoFieldsSorted($cfg, false);
$logoUrl = LetterheadSettings::logoUrl($cfg);
$logoMediaId = trim((string) ($header['logo_media_id'] ?? ''));
$crmLogoUrl = class_exists('AppearanceSettings') ? AppearanceSettings::logoUrl() : Asset::url('/assets/img/logo.svg');
$bgMediaId = trim((string) ($bg['media_id'] ?? ''));
$bgMediaUrl = '';
if ($bgMediaId !== '' && class_exists('MediaRepository')) {
    MediaRepository::ensureTables();
    $bgItem = MediaRepository::find($bgMediaId);
    if (is_array($bgItem) && !empty($bgItem['url'])) {
        $bgMediaUrl = (string) $bgItem['url'];
    } elseif (class_exists('MediaStorage') && MediaId::isValid($bgMediaId)) {
        $bgMediaUrl = MediaStorage::publicUrl($bgMediaId);
    }
}
$disabled = !$dbConnected ? ' disabled' : '';
$mandatoryDefs = LetterheadSettings::mandatoryFieldsForType((string) ($party['company_type'] ?? ''));
?>
<form
  class="dg-form"
  method="post"
  action="<?= View::escape(SettingsRegistry::tabUrl('briefpapier')) ?>"
  id="dg-letterhead-settings"
  data-media-list-url="/api/media?action=list"
>
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
  <input type="hidden" name="_letterhead_form" value="1">

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Zum Speichern ist eine funktionierende <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Datenbankverbindung</a> erforderlich.
    </div>
  <?php endif; ?>

  <p class="dg-lead">
    Briefpapier nach DIN&nbsp;5008 (Zonen) mit gesetzlichen Pflichtangaben und empfohlenen Fußdaten.
    Firmendaten kommen aus <a href="<?= View::escape(SettingsRegistry::tabUrl('firmendaten')) ?>">Firmendaten</a> — hier nur Layout und Sichtbarkeit.
    Fehlende Pflichtangaben blockieren das Speichern <strong>nicht</strong> (Soft-Check).
  </p>

  <section class="dg-panel" style="margin-bottom:16px">
    <h2 class="dg-subsection-title">Prüfstatus (B3)</h2>
    <p class="dg-field-hint">
      Rechtsform: <strong><?= View::escape((string) ($compliance['legal_form_label'] ?? '—')) ?></strong>
      · <?= View::escape((string) ($compliance['summary'] ?? '')) ?>
    </p>
    <?php if (!empty($compliance['ok'])) : ?>
      <div class="dg-flash dg-flash--success" style="margin:0">Pflichtangaben und kritische DIN-Zonen sind in Ordnung.</div>
    <?php else : ?>
      <div class="dg-flash dg-flash--warning" style="margin:0 0 12px">
        Soft-Check: Speichern weiterhin möglich — bitte Lücken schließen, bevor Briefe produktiv gehen.
      </div>
    <?php endif; ?>

    <div class="dg-form-grid" style="margin-top:12px">
      <div class="dg-field dg-field--wide">
        <span>Gesetzliche Pflichtangaben</span>
        <ul class="dg-field-hint" style="margin:6px 0 0;padding-left:1.2em">
          <?php foreach ($mandatoryDefs as $def) : ?>
            <?php
              $isMissing = false;
              foreach ($missing as $m) {
                  if (($m['key'] ?? '') === ($def['key'] ?? '')) {
                      $isMissing = true;
                      break;
                  }
              }
            ?>
            <li>
              <?= $isMissing ? '⚠' : '✓' ?>
              <?= View::escape((string) $def['label']) ?>
              <?php if (!empty($def['basis'])) : ?>
                <span class="dg-muted">(<?= View::escape((string) $def['basis']) ?>)</span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
          <?php foreach ($missing as $m) : ?>
            <?php
              $already = false;
              foreach ($mandatoryDefs as $def) {
                  if (($def['key'] ?? '') === ($m['key'] ?? '')) {
                      $already = true;
                      break;
                  }
              }
              if ($already) {
                  continue;
              }
            ?>
            <li>⚠ <?= View::escape((string) ($m['label'] ?? '')) ?>
              <?php if (!empty($m['basis'])) : ?>
                <span class="dg-muted">(<?= View::escape((string) $m['basis']) ?>)</span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="dg-field dg-field--wide">
        <span>DIN-Zonen (Standardlayout)</span>
        <ul class="dg-field-hint" style="margin:6px 0 0;padding-left:1.2em">
          <?php foreach (LetterheadSettings::dinZoneDefinitions() as $zone) : ?>
            <?php $zoneOff = false; foreach ($dinOff as $z) { if (($z['path'] ?? '') === $zone['path']) { $zoneOff = true; break; } } ?>
            <li>
              <?= $zoneOff ? '○ aus' : '● an' ?>
              <?= View::escape((string) $zone['label']) ?>
              <?php if (!empty($zone['critical'])) : ?><span class="dg-muted">(kritisch)</span><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php if ($dinCriticalOff !== []) : ?>
          <p class="dg-field-hint" style="margin-top:6px">Kritisch aus: <?= View::escape(implode(', ', array_map(static fn(array $z): string => (string) $z['label'], $dinCriticalOff))) ?></p>
        <?php endif; ?>
      </div>
      <?php if ($recommendedGaps !== []) : ?>
        <div class="dg-field dg-field--wide">
          <span>Empfohlen aktiv, aber ohne Daten</span>
          <p class="dg-field-hint"><?= View::escape(implode(', ', array_map(static fn(array $g): string => (string) $g['label'], $recommendedGaps))) ?>
            — unter Firmendaten pflegen oder Schalter aus.</p>
        </div>
      <?php endif; ?>
    </div>
    <p class="dg-form-actions" style="margin-top:12px">
      <button type="submit" name="letterhead_reset_din" value="1" class="dg-button"<?= $disabled ?>
        onclick="return confirm('DIN-Standardlayout und empfohlene Fuß-Schalter zurücksetzen? Logo/Links/Overrides bleiben.');">
        DIN-Standardlayout wiederherstellen
      </button>
    </p>
  </section>

  <details class="dg-collapsible-form" open>
    <summary class="dg-subsection-title dg-collapsible-form__summary">Briefkopf &amp; Logo</summary>
    <div class="dg-collapsible-form__body">
      <div class="dg-form-grid">
        <label class="dg-checkbox dg-field">
          <input type="checkbox" name="header[show]" value="1"<?= !empty($header['show']) ? ' checked' : '' ?><?= $disabled ?>>
          Briefkopf anzeigen
        </label>
        <label class="dg-checkbox dg-field">
          <input type="checkbox" name="header[show_company_name]" value="1"<?= !empty($header['show_company_name']) ? ' checked' : '' ?><?= $disabled ?>>
          Firmenname im Kopf
        </label>
        <label class="dg-checkbox dg-field">
          <input type="checkbox" name="header[show_logo]" value="1"<?= !empty($header['show_logo']) ? ' checked' : '' ?><?= $disabled ?>>
          Logo anzeigen
        </label>
        <label class="dg-field">
          <span>Logo-Position</span>
          <select name="header[logo_position]"<?= $disabled ?>>
            <?php foreach (['left' => 'Links', 'center' => 'Mitte', 'right' => 'Rechts'] as $val => $lbl) : ?>
              <option value="<?= View::escape($val) ?>"<?= ((string) ($header['logo_position'] ?? 'left') === $val) ? ' selected' : '' ?>><?= View::escape($lbl) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dg-field">
          <span>Logo-Höhe (mm)</span>
          <input type="number" name="header[logo_max_height_mm]" min="5" max="45" step="0.5"
            value="<?= View::escape((string) ($header['logo_max_height_mm'] ?? '18')) ?>"<?= $disabled ?>>
        </label>
        <label class="dg-field">
          <span>Briefkopf-Form (DIN)</span>
          <select name="header[height_form]"<?= $disabled ?>>
            <option value="A"<?= ((string) ($header['height_form'] ?? 'B') === 'A') ? ' selected' : '' ?>>Form A (~27 mm)</option>
            <option value="B"<?= ((string) ($header['height_form'] ?? 'B') === 'B') ? ' selected' : '' ?>>Form B (~45 mm)</option>
          </select>
        </label>
        <div class="dg-field dg-field--wide">
          <span>Logo aus Mediathek</span>
          <input type="hidden" name="header[logo_media_id]" id="dg-letterhead-logo-media-id" value="<?= View::escape($logoMediaId) ?>">
          <p class="dg-form-actions" style="margin:0 0 8px">
            <button type="button" class="dg-button" data-dg-media-pick-open
              data-input="dg-letterhead-logo-media-id"
              data-preview="dg-letterhead-logo-preview"
              data-title="Logo wählen"<?= $disabled ?>>Bild wählen</button>
            <button type="button" class="dg-button" data-dg-media-pick-clear
              data-input="dg-letterhead-logo-media-id"
              data-preview="dg-letterhead-logo-preview"<?= $disabled ?>>CRM-Logo verwenden</button>
          </p>
          <div
            id="dg-letterhead-logo-preview"
            class="dg-letterhead-media-preview"
            data-fallback-url="<?= View::escape($crmLogoUrl) ?>"
          >
            <img src="<?= View::escape($logoUrl) ?>" alt="">
            <?php if ($logoMediaId === '') : ?>
              <span class="dg-field-hint">CRM-Logo</span>
            <?php else : ?>
              <span class="dg-field-hint">Mediathek</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </details>

  <details class="dg-collapsible-form" open>
    <summary class="dg-subsection-title dg-collapsible-form__summary">DIN-Zonen (Fenster, Futter, Ränder)</summary>
    <div class="dg-collapsible-form__body">
      <div class="dg-form-grid">
        <label class="dg-checkbox dg-field">
          <input type="checkbox" name="sender_line[show]" value="1"<?= !empty($sender['show']) ? ' checked' : '' ?><?= $disabled ?>>
          Absenderzeile (Futter über Anschrift)
        </label>
        <label class="dg-field">
          <span>Futter Schriftgröße (pt)</span>
          <input type="number" name="sender_line[font_size_pt]" min="6" max="12" step="0.5"
            value="<?= View::escape((string) ($sender['font_size_pt'] ?? '8')) ?>"<?= $disabled ?>>
        </label>
        <label class="dg-checkbox dg-field">
          <input type="checkbox" name="address_window[show]" value="1"<?= !empty($window['show']) ? ' checked' : '' ?><?= $disabled ?>>
          Anschriftenfenster
        </label>
        <label class="dg-checkbox dg-field">
          <input type="checkbox" name="page[show_din_guides]" value="1"<?= !empty($page['show_din_guides']) ? ' checked' : '' ?><?= $disabled ?>>
          DIN-Hilfslinien in Vorschau (B4)
        </label>
      </div>
      <p class="dg-field-hint">Fenster-Standard: <?= (int) LetterheadSettings::ADDRESS_WINDOW_WIDTH_MM ?>×<?= (int) LetterheadSettings::ADDRESS_WINDOW_HEIGHT_MM ?> mm, <?= (int) LetterheadSettings::ADDRESS_WINDOW_LEFT_MM ?> mm vom linken Rand.</p>
      <div class="dg-form-grid">
        <label class="dg-field"><span>Fenster links (mm)</span>
          <input type="number" name="address_window[left_mm]" min="10" max="40" step="0.1" value="<?= View::escape((string) ($window['left_mm'] ?? '20')) ?>"<?= $disabled ?>></label>
        <label class="dg-field"><span>Fenster oben (mm)</span>
          <input type="number" name="address_window[top_mm]" min="30" max="80" step="0.1" value="<?= View::escape((string) ($window['top_mm'] ?? '50')) ?>"<?= $disabled ?>></label>
        <label class="dg-field"><span>Fenster Breite (mm)</span>
          <input type="number" name="address_window[width_mm]" min="70" max="100" step="0.1" value="<?= View::escape((string) ($window['width_mm'] ?? '85')) ?>"<?= $disabled ?>></label>
        <label class="dg-field"><span>Fenster Höhe (mm)</span>
          <input type="number" name="address_window[height_mm]" min="35" max="55" step="0.1" value="<?= View::escape((string) ($window['height_mm'] ?? '45')) ?>"<?= $disabled ?>></label>
        <label class="dg-field"><span>Rand oben (mm)</span>
          <input type="number" name="page[margin_top_mm]" min="5" max="40" step="0.1" value="<?= View::escape((string) ($page['margin_top_mm'] ?? '17')) ?>"<?= $disabled ?>></label>
        <label class="dg-field"><span>Rand rechts (mm)</span>
          <input type="number" name="page[margin_right_mm]" min="5" max="40" step="0.1" value="<?= View::escape((string) ($page['margin_right_mm'] ?? '20')) ?>"<?= $disabled ?>></label>
        <label class="dg-field"><span>Rand unten (mm)</span>
          <input type="number" name="page[margin_bottom_mm]" min="5" max="40" step="0.1" value="<?= View::escape((string) ($page['margin_bottom_mm'] ?? '16.7')) ?>"<?= $disabled ?>></label>
        <label class="dg-field"><span>Rand links (mm)</span>
          <input type="number" name="page[margin_left_mm]" min="5" max="40" step="0.1" value="<?= View::escape((string) ($page['margin_left_mm'] ?? '24.1')) ?>"<?= $disabled ?>></label>
      </div>
      <p class="dg-field-hint">Futter-Vorschau: <?= View::escape(LetterheadSettings::senderLineText($cfg) !== '' ? LetterheadSettings::senderLineText($cfg) : '— noch keine Firmendaten —') ?></p>
    </div>
  </details>

  <details class="dg-collapsible-form" open>
    <summary class="dg-subsection-title dg-collapsible-form__summary">Infoblock (Ihre / Unsere Seite)</summary>
    <div class="dg-collapsible-form__body">
      <label class="dg-checkbox dg-field" style="margin-bottom:12px">
        <input type="checkbox" name="info_block[show]" value="1"<?= !empty($info['show']) ? ' checked' : '' ?><?= $disabled ?>>
        Infoblock rechts anzeigen
      </label>
      <div class="dg-table-wrap">
        <table class="dg-table dg-table--compact">
          <thead>
            <tr>
              <th>Anzeigen</th>
              <th>Feld</th>
              <th>Beschriftung</th>
              <th>Reihenfolge</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($infoFields as $row) : ?>
              <?php $key = $row['key']; ?>
              <tr>
                <td>
                  <label class="dg-checkbox">
                    <input type="checkbox" name="info_block[fields][<?= View::escape($key) ?>][show]" value="1"<?= !empty($row['show']) ? ' checked' : '' ?><?= $disabled ?>>
                  </label>
                </td>
                <td><code><?= View::escape($key) ?></code></td>
                <td>
                  <input type="text" name="info_block[fields][<?= View::escape($key) ?>][label]"
                    value="<?= View::escape($row['label']) ?>"<?= $disabled ?>>
                </td>
                <td style="width:6rem">
                  <input type="number" name="info_block[fields][<?= View::escape($key) ?>][order]" min="0" max="9999"
                    value="<?= (int) $row['order'] ?>"<?= $disabled ?>>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="dg-form-grid" style="margin-top:12px">
        <label class="dg-checkbox dg-field">
          <input type="checkbox" name="subject[bold]" value="1"<?= !empty($subject['bold']) ? ' checked' : '' ?><?= $disabled ?>>
          Betreff fett
        </label>
        <label class="dg-checkbox dg-field">
          <input type="checkbox" name="subject[show_label]" value="1"<?= !empty($subject['show_label']) ? ' checked' : '' ?><?= $disabled ?>>
          Wort „Betreff:“ anzeigen (DIN: eher ohne)
        </label>
      </div>
    </div>
  </details>

  <details class="dg-collapsible-form" open>
    <summary class="dg-subsection-title dg-collapsible-form__summary">Fußzeile — Pflicht &amp; empfohlen</summary>
    <div class="dg-collapsible-form__body">
      <div class="dg-form-grid">
        <label class="dg-checkbox dg-field">
          <input type="checkbox" name="footer[show]" value="1"<?= !empty($footer['show']) ? ' checked' : '' ?><?= $disabled ?>>
          Fußzeile anzeigen
        </label>
        <label class="dg-checkbox dg-field">
          <input type="checkbox" name="footer[show_rule]" value="1"<?= !empty($footer['show_rule']) ? ' checked' : '' ?><?= $disabled ?>>
          Trennlinie über Fuß
        </label>
        <label class="dg-checkbox dg-field">
          <input type="checkbox" name="footer[show_legal_mandatory]" value="1"<?= !empty($footer['show_legal_mandatory']) ? ' checked' : '' ?><?= $disabled ?>>
          Gesetzliche Pflichtangaben
        </label>
        <label class="dg-field">
          <span>Schriftgröße Fuß (pt)</span>
          <input type="number" name="footer[font_size_pt]" min="5" max="11" step="0.5"
            value="<?= View::escape((string) ($footer['font_size_pt'] ?? '7')) ?>"<?= $disabled ?>>
        </label>
        <label class="dg-field">
          <span>Spalten</span>
          <input type="number" name="footer[columns]" min="1" max="4" value="<?= (int) ($footer['columns'] ?? 3) ?>"<?= $disabled ?>>
        </label>
      </div>
      <h4 class="dg-subsection-title" style="font-size:1rem;margin-top:12px">Empfohlene Angaben</h4>
      <div class="dg-form-grid">
        <?php foreach (LetterheadSettings::recommendedFooterFields() as $rec) : ?>
          <label class="dg-checkbox dg-field">
            <input type="checkbox" name="footer[<?= View::escape($rec['key']) ?>]" value="1"<?= !empty($footer[$rec['key']]) ? ' checked' : '' ?><?= $disabled ?>>
            <?= View::escape($rec['label']) ?>
          </label>
        <?php endforeach; ?>
      </div>
      <p class="dg-field-hint" style="margin-top:8px">
        Aktuell aus Firma:
        <?= View::escape($party['legal_name'] !== '' ? $party['legal_name'] : '—') ?>
        <?php if ($party['company_type_label'] !== '') : ?> · <?= View::escape($party['company_type_label']) ?><?php endif; ?>
        <?php if ($party['register_number'] !== '') : ?> · <?= View::escape(trim($party['register_court'] . ' ' . $party['register_number'])) ?><?php endif; ?>
      </p>
    </div>
  </details>

  <details class="dg-collapsible-form">
    <summary class="dg-subsection-title dg-collapsible-form__summary">Links &amp; rechtliche Ergänzungen</summary>
    <div class="dg-collapsible-form__body">
      <?php
        $resolvedLinks = is_array($party['links'] ?? null) ? $party['links'] : [];
        $linkSources = [
            'impressum' => 'Impressum',
            'datenschutz' => 'Datenschutz',
            'agb' => 'AGB',
            'widerruf' => 'Widerruf',
        ];
      ?>
      <p class="dg-field-hint">Leer = automatisch aus E-Mail-Layout, sonst aus veröffentlichten Website-Seiten (Impressum, Datenschutz, AGB, Widerruf).</p>
      <div class="dg-form-grid">
        <?php foreach ($linkSources as $linkKey => $linkLabel) :
            $storedLink = trim((string) ($links[$linkKey] ?? ''));
            $autoLink = trim((string) ($resolvedLinks[$linkKey] ?? ''));
            $displayLink = $storedLink !== '' ? $storedLink : $autoLink;
            $autoHint = '';
            if ($storedLink === '' && $autoLink !== '') {
                $fromSite = LetterheadSettings::publishedLegalPageUrl($linkKey) === $autoLink;
                $autoHint = $fromSite
                    ? 'Übernommen aus aktiver Website-Seite'
                    : 'Übernommen aus E-Mail-Layout';
            }
            ?>
        <label class="dg-field dg-field--wide">
          <span><?= View::escape($linkLabel) ?>-URL</span>
          <input type="url" name="links[<?= View::escape($linkKey) ?>]" value="<?= View::escape($displayLink) ?>"
            placeholder="https://…"<?= $disabled ?>>
          <?php if ($autoHint !== '') : ?>
            <span class="dg-field-hint"><?= View::escape($autoHint) ?>. Ändern nur bei abweichender URL.</span>
          <?php endif; ?>
        </label>
        <?php endforeach; ?>
      </div>
      <h4 class="dg-subsection-title" style="font-size:1rem;margin-top:12px">Overrides (nur wenn Firmendaten fehlen)</h4>
      <div class="dg-form-grid">
        <label class="dg-field"><span>Sitz</span>
          <input type="text" name="legal_overrides[seat]" value="<?= View::escape((string) ($legal['seat'] ?? '')) ?>"<?= $disabled ?>></label>
        <label class="dg-field dg-field--wide"><span>Geschäftsführer / Gesellschafter</span>
          <input type="text" name="legal_overrides[managing_directors]" value="<?= View::escape((string) ($legal['managing_directors'] ?? '')) ?>"<?= $disabled ?>></label>
        <label class="dg-field"><span>Aufsichtsratsvorsitz</span>
          <input type="text" name="legal_overrides[supervisory_chair]" value="<?= View::escape((string) ($legal['supervisory_chair'] ?? '')) ?>"<?= $disabled ?>></label>
        <label class="dg-field"><span>Stammkapital</span>
          <input type="text" name="legal_overrides[share_capital]" value="<?= View::escape((string) ($legal['share_capital'] ?? '')) ?>"<?= $disabled ?>></label>
        <label class="dg-field"><span>Ausstehende Einlagen</span>
          <input type="text" name="legal_overrides[outstanding_contributions]" value="<?= View::escape((string) ($legal['outstanding_contributions'] ?? '')) ?>"<?= $disabled ?>></label>
      </div>
    </div>
  </details>

  <details class="dg-collapsible-form">
    <summary class="dg-subsection-title dg-collapsible-form__summary">Hintergrund / Wasserzeichen</summary>
    <div class="dg-collapsible-form__body">
      <div class="dg-form-grid">
        <label class="dg-field">
          <span>Modus</span>
          <select name="background[mode]"<?= $disabled ?>>
            <?php foreach (['none' => 'Keiner', 'color' => 'Farbe', 'image' => 'Bild', 'watermark' => 'Wasserzeichen'] as $val => $lbl) : ?>
              <option value="<?= View::escape($val) ?>"<?= ((string) ($bg['mode'] ?? 'none') === $val) ? ' selected' : '' ?>><?= View::escape($lbl) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dg-field">
          <span>Farbe</span>
          <input type="text" name="background[color]" value="<?= View::escape((string) ($bg['color'] ?? '')) ?>" placeholder="#f5f5f5"<?= $disabled ?>>
        </label>
        <div class="dg-field dg-field--wide">
          <span>Hintergrundbild aus Mediathek</span>
          <input type="hidden" name="background[media_id]" id="dg-letterhead-bg-media-id" value="<?= View::escape($bgMediaId) ?>">
          <p class="dg-form-actions" style="margin:0 0 8px">
            <button type="button" class="dg-button" data-dg-media-pick-open
              data-input="dg-letterhead-bg-media-id"
              data-preview="dg-letterhead-bg-preview"
              data-title="Hintergrundbild wählen"<?= $disabled ?>>Bild wählen</button>
            <button type="button" class="dg-button" data-dg-media-pick-clear
              data-input="dg-letterhead-bg-media-id"
              data-preview="dg-letterhead-bg-preview"<?= $disabled ?>>Entfernen</button>
          </p>
          <div id="dg-letterhead-bg-preview" class="dg-letterhead-media-preview">
            <?php if ($bgMediaUrl !== '') : ?>
              <img src="<?= View::escape($bgMediaUrl) ?>" alt="">
            <?php else : ?>
              <span class="dg-field-hint">Kein Bild gewählt</span>
            <?php endif; ?>
          </div>
          <p class="dg-field-hint">Wirksam bei Modus „Bild“ oder „Wasserzeichen“.</p>
        </div>
        <label class="dg-field">
          <span>Deckkraft (0,02–0,5)</span>
          <input type="number" name="background[opacity]" min="0.02" max="0.5" step="0.01"
            value="<?= View::escape((string) ($bg['opacity'] ?? '0.08')) ?>"<?= $disabled ?>>
        </label>
        <label class="dg-field">
          <span>Einpassung</span>
          <select name="background[fit]"<?= $disabled ?>>
            <?php foreach (['contain' => 'Einpassen', 'cover' => 'Füllen', 'tile' => 'Kacheln'] as $val => $lbl) : ?>
              <option value="<?= View::escape($val) ?>"<?= ((string) ($bg['fit'] ?? 'contain') === $val) ? ' selected' : '' ?>><?= View::escape($lbl) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dg-checkbox dg-field">
          <input type="checkbox" name="background[all_pages]" value="1"<?= !empty($bg['all_pages']) ? ' checked' : '' ?><?= $disabled ?>>
          Auf allen Seiten
        </label>
      </div>
    </div>
  </details>

  <details class="dg-collapsible-form" open>
    <summary class="dg-subsection-title dg-collapsible-form__summary">Export-Formate (Dokumentenverfasser)</summary>
    <div class="dg-collapsible-form__body">
      <p class="dg-field-hint">Welche Download-Buttons später auf der Schreibseite sichtbar sind. Mindestens PDF bleibt aktiv.</p>
      <div class="dg-form-grid">
        <?php foreach (['pdf' => 'PDF', 'txt' => 'Textdatei (.txt)', 'md' => 'Markdown (.md)', 'docx' => 'Word (.docx)'] as $key => $lbl) : ?>
          <label class="dg-checkbox dg-field">
            <input type="checkbox" name="export_formats[<?= View::escape($key) ?>]" value="1"<?= !empty($export[$key]) ? ' checked' : '' ?><?= $disabled ?>>
            <?= View::escape($lbl) ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
  </details>

  <div class="dg-form-actions">
    <button type="submit" name="letterhead_save" value="1" class="dg-button dg-button--primary"<?= $disabled ?>>Briefpapier speichern</button>
    <a class="dg-button" href="/app?page=dokumentenverfasser">Zum Dokumentenverfasser</a>
  </div>
</form>

<?php
  $assist = WriterAssistSettings::config();
?>
<form method="post" action="<?= View::escape(SettingsRegistry::tabUrl('briefpapier')) ?>" class="dg-form" style="margin-top:20px">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
  <details class="dg-collapsible-form" open>
    <summary class="dg-subsection-title dg-collapsible-form__summary">Text-Assist (D4)</summary>
    <div class="dg-collapsible-form__body">
      <p class="dg-field-hint">
        Browser-Rechtschreibung ist Standard. Klartext-Hinweise laufen lokal im CRM.
        LanguageTool nur optional (Self-Host empfohlen) — öffentliche Cloud nur mit bewusster Freigabe.
      </p>
      <div class="dg-form-grid">
        <label class="dg-checkbox dg-field">
          <input type="checkbox" name="klartext_enabled" value="1"<?= !empty($assist['klartext_enabled']) ? ' checked' : '' ?><?= $disabled ?>>
          Klartext-Empfehlungen anbieten
        </label>
        <label class="dg-checkbox dg-field">
          <input type="checkbox" name="languagetool_enabled" value="1"<?= !empty($assist['languagetool_enabled']) ? ' checked' : '' ?><?= $disabled ?>>
          LanguageTool aktiv
        </label>
        <label class="dg-field">
          <span>LanguageTool-URL</span>
          <input type="url" name="languagetool_url" maxlength="500" placeholder="https://lt.meine-firma.de/v2/check" value="<?= View::escape((string) ($assist['languagetool_url'] ?? '')) ?>"<?= $disabled ?>>
        </label>
        <label class="dg-field">
          <span>Sprache</span>
          <input name="languagetool_language" maxlength="10" value="<?= View::escape((string) ($assist['languagetool_language'] ?? 'de-DE')) ?>"<?= $disabled ?>>
        </label>
        <label class="dg-checkbox dg-field">
          <input type="checkbox" name="languagetool_allow_public_cloud" value="1"<?= !empty($assist['languagetool_allow_public_cloud']) ? ' checked' : '' ?><?= $disabled ?>>
          Öffentliche LanguageTool-Cloud erlauben (api.languagetool.org) — Datenabfluss, nicht Standard
        </label>
      </div>
      <div class="dg-form-actions">
        <button type="submit" name="writer_assist_save" value="1" class="dg-button dg-button--primary"<?= $disabled ?>>Assist speichern</button>
      </div>
    </div>
  </details>
</form>

<?php
  $previewGuides = !empty($page['show_din_guides']) ? '1' : '0';
  $previewUrl = SettingsRegistry::tabUrl('briefpapier') . '&letterhead_preview=1&guides=' . $previewGuides;
  $previewUrlGuidesOn = SettingsRegistry::tabUrl('briefpapier') . '&letterhead_preview=1&guides=1';
  $previewUrlGuidesOff = SettingsRegistry::tabUrl('briefpapier') . '&letterhead_preview=1&guides=0';
?>
<section class="dg-panel dg-letterhead-preview" style="margin-top:20px">
  <h2 class="dg-subsection-title">A4-Vorschau (B4)</h2>
  <p class="dg-field-hint">
    Maße in Millimetern (DIN&nbsp;A4). Nach dem Speichern neu laden. Hilfslinien folgen der Checkbox „DIN-Hilfslinien“ oben — oder hier umschalten.
  </p>
  <p class="dg-form-actions" style="margin-bottom:12px">
    <a class="dg-button" href="<?= View::escape($previewUrl) ?>" target="_blank" rel="noopener">Vorschau in neuem Tab</a>
    <a class="dg-button" href="<?= View::escape($previewUrlGuidesOn) ?>" target="dg-letterhead-preview-frame">Hilfslinien an</a>
    <a class="dg-button" href="<?= View::escape($previewUrlGuidesOff) ?>" target="dg-letterhead-preview-frame">Hilfslinien aus</a>
  </p>
  <div class="dg-letterhead-preview__frame-wrap">
    <iframe
      class="dg-letterhead-preview__frame"
      name="dg-letterhead-preview-frame"
      title="Briefpapier A4-Vorschau"
      src="<?= View::escape($previewUrl) ?>"
    ></iframe>
  </div>
</section>
