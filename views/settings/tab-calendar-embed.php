<?php
/** @var array<string, mixed> $calendarEmbedConfig */
/** @var bool $dbConnected */
$calendarEmbedConfig = $calendarEmbedConfig ?? CalendarEmbedSettings::forForm();
$publicUrl = (string) ($calendarEmbedConfig['public_url'] ?? CalendarEmbedSettings::publicBookingUrl());
$isEnabled = !empty($calendarEmbedConfig['online_booking_enabled']);
$qr = is_array($calendarEmbedConfig['qr'] ?? null) ? $calendarEmbedConfig['qr'] : CalendarEmbedSettings::qrDefaults();
$flyer = is_array($calendarEmbedConfig['flyer'] ?? null) ? $calendarEmbedConfig['flyer'] : CalendarEmbedSettings::flyerDefaults();
$qrLogoUrl = (string) ($calendarEmbedConfig['qr_logo_url'] ?? '');
$qrFaviconUrl = (string) ($calendarEmbedConfig['qr_favicon_url'] ?? '');
$qrCenterCustomUrl = (string) ($calendarEmbedConfig['qr_center_custom_url'] ?? '');
$flyerAccent = (string) ($calendarEmbedConfig['flyer_accent_effective'] ?? ($flyer['accent_color'] ?: '#0f766e'));
$centerSource = (string) ($qr['center_image_source'] ?? 'none');
$headlinePresets = [
    'Wunschtermin in 60 Sekunden sichern!',
    'Jetzt online Termin buchen',
    'Scannen – Termin wählen – fertig',
];
$ctaPresets = [
    'Code scannen & Termin buchen',
    'Einfach scannen und Wunschtermin wählen',
    'Jetzt scannen – online buchen',
];
?>
<form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('kalender-einbindung')) ?>" id="dg-calendar-embed-form">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Zum Speichern ist eine funktionierende <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Datenbankverbindung</a> erforderlich.
    </div>
  <?php endif; ?>

  <p class="dg-lead">
    Öffentliche Buchungsseite für Kunden — unabhängig vom CRM-Login. Design und Farben kommen aus
    <a href="<?= View::escape(SettingsRegistry::tabUrl('kalender-darstellung')) ?>">Kalender Design</a>.
  </p>

  <div class="dg-form-grid">
    <label class="dg-field dg-field--wide">
      <span>
        <input type="checkbox" name="online_booking_enabled" value="1"<?= $isEnabled ? ' checked' : '' ?><?= !$dbConnected ? ' disabled' : '' ?>>
        Terminvereinbarung online stellen
      </span>
      <small class="dg-field-hint">Wenn aktiv, ist die öffentliche Seite erreichbar und Kunden können Termine buchen.</small>
    </label>
    <label class="dg-field dg-field--wide">
      <span>Seitentitel</span>
      <input type="text" name="page_title" value="<?= View::escape((string) ($calendarEmbedConfig['page_title'] ?? '')) ?>" placeholder="Termin online vereinbaren"<?= !$dbConnected ? ' disabled' : '' ?>>
    </label>
    <label class="dg-field dg-field--wide">
      <span>Einleitungstext</span>
      <textarea name="intro_text" rows="3"<?= !$dbConnected ? ' disabled' : '' ?>><?= View::escape((string) ($calendarEmbedConfig['intro_text'] ?? '')) ?></textarea>
    </label>
    <label class="dg-field dg-field--wide">
      <span>Bestätigungstext nach Buchung</span>
      <textarea name="success_message" rows="2"<?= !$dbConnected ? ' disabled' : '' ?>><?= View::escape((string) ($calendarEmbedConfig['success_message'] ?? '')) ?></textarea>
    </label>
  </div>

  <div class="dg-panel dg-mail-signature-rules" style="margin-top:8px">
    <h4 class="dg-subsection-title">Öffentliche Adresse</h4>
    <p class="dg-field-hint">Diese URL können Sie auf Ihrer Webseite verlinken oder als QR-Code verwenden.</p>
    <label class="dg-field dg-field--wide">
      <span>Link zur Buchungsseite</span>
      <input type="text" class="dg-input-copy" readonly value="<?= View::escape($publicUrl) ?>" onclick="this.select()" id="dg-booking-public-url">
    </label>
    <p class="dg-form-actions" style="margin-top:0">
      <a class="dg-button" href="<?= View::escape($publicUrl) ?>" target="_blank" rel="noopener">Seite öffnen</a>
      <?php if ($isEnabled) : ?>
        <span class="dg-badge dg-badge--ok">Online-Buchung aktiv</span>
      <?php else : ?>
        <span class="dg-badge">Deaktiviert — Seite zeigt Hinweis</span>
      <?php endif; ?>
    </p>
  </div>

  <?php if ($publicUrl !== '') : ?>
  <div class="dg-panel dg-booking-qr-designer" style="margin-top:16px"
       data-public-url="<?= View::escape($publicUrl) ?>"
       data-logo-url="<?= View::escape($qrLogoUrl) ?>"
       data-favicon-url="<?= View::escape($qrFaviconUrl) ?>"
       data-center-custom-url="<?= View::escape($qrCenterCustomUrl) ?>"
       data-company-name="<?= View::escape((string) ($calendarEmbedConfig['qr_company_name'] ?? '')) ?>"
       data-flyer-font="<?= View::escape((string) ($calendarEmbedConfig['flyer_font_family'] ?? 'system-ui,sans-serif')) ?>"
       data-flyer-brand="<?= View::escape((string) ($calendarEmbedConfig['flyer_brand_primary'] ?? '#0f766e')) ?>"
       data-media-list-url="/api/media?action=list"
       data-csrf="<?= View::escape(Csrf::token()) ?>">
    <h4 class="dg-subsection-title">QR-Code Design &amp; Flyer</h4>
    <p class="dg-field-hint">QR gestalten und als Flyer drucken: Logo oben, Headline, Code mit Weißraum, klare Handlungsaufforderung.</p>

    <div class="dg-booking-qr-layout">
      <div class="dg-booking-qr-fields">
        <label class="dg-field dg-field--wide">
          <span>Text unter dem Code</span>
          <input type="text" name="qr[caption]" id="dg-qr-caption" value="<?= View::escape((string) $qr['caption']) ?>" data-dg-qr-field maxlength="120">
        </label>

        <div class="dg-form-grid">
          <label class="dg-field">
            <span>Vordergrund</span>
            <input type="color" name="qr[fg_color]" id="dg-qr-fg" value="<?= View::escape((string) $qr['fg_color']) ?>" data-dg-qr-field>
          </label>
          <label class="dg-field">
            <span>Hintergrund</span>
            <input type="color" name="qr[bg_color]" id="dg-qr-bg" value="<?= View::escape((string) $qr['bg_color']) ?>" data-dg-qr-field>
          </label>
        </div>

        <fieldset class="dg-field dg-field--wide">
          <legend>Mitte (Logo / Emoji)</legend>
          <label class="dg-radio"><input type="radio" name="qr[center_image_source]" value="none"<?= $centerSource === 'none' ? ' checked' : '' ?> data-dg-qr-field> Kein Logo</label>
          <label class="dg-radio"><input type="radio" name="qr[center_image_source]" value="logo"<?= $centerSource === 'logo' ? ' checked' : '' ?><?= $qrLogoUrl === '' ? ' disabled' : '' ?> data-dg-qr-field> CRM-Logo</label>
          <label class="dg-radio"><input type="radio" name="qr[center_image_source]" value="favicon"<?= $centerSource === 'favicon' ? ' checked' : '' ?><?= $qrFaviconUrl === '' ? ' disabled' : '' ?> data-dg-qr-field> Favicon (klein)</label>
          <label class="dg-radio"><input type="radio" name="qr[center_image_source]" value="custom"<?= $centerSource === 'custom' ? ' checked' : '' ?> data-dg-qr-field> Bild aus Mediathek</label>
          <label class="dg-radio"><input type="radio" name="qr[center_image_source]" value="emoji"<?= $centerSource === 'emoji' ? ' checked' : '' ?> data-dg-qr-field> Emoji</label>

          <input type="hidden" name="qr[center_media_id]" id="dg-qr-center-media-id" value="<?= View::escape((string) $qr['center_media_id']) ?>">

          <div id="dg-qr-center-custom-wrap" class="dg-booking-qr-sub"<?= $centerSource === 'custom' ? '' : ' hidden' ?>>
            <button type="button" class="dg-button" id="dg-qr-pick-media">Bild wählen</button>
            <button type="button" class="dg-button" id="dg-qr-clear-media">Entfernen</button>
            <span id="dg-qr-center-preview" class="dg-booking-qr-center-preview">
              <?php if ($centerSource === 'custom' && $qrCenterCustomUrl !== '') : ?>
                <img src="<?= View::escape($qrCenterCustomUrl) ?>" alt="" width="48" height="48">
              <?php endif; ?>
            </span>
            <label class="dg-field" style="margin-top:8px">
              <span>Größe im Code</span>
              <select name="qr[center_image_size]" id="dg-qr-center-size" data-dg-qr-field>
                <option value="tiny"<?= ($qr['center_image_size'] ?? '') === 'tiny' ? ' selected' : '' ?>>Sehr klein (~14 %)</option>
                <option value="small"<?= ($qr['center_image_size'] ?? '') === 'small' ? ' selected' : '' ?>>Klein (~18 %)</option>
              </select>
            </label>
          </div>

          <div id="dg-qr-center-emoji-wrap" class="dg-booking-qr-sub"<?= $centerSource === 'emoji' ? '' : ' hidden' ?>>
            <div class="dg-booking-qr-emoji-toolbar">
              <input type="text" name="qr[center_emoji]" id="dg-qr-emoji" value="<?= View::escape((string) $qr['center_emoji']) ?>" maxlength="32" data-dg-qr-field class="dg-booking-qr-emoji-input" aria-label="Gewähltes Emoji">
              <input type="search" id="dg-qr-emoji-search" class="dg-booking-qr-emoji-search" placeholder="Suchen …" autocomplete="off">
            </div>
            <div id="dg-qr-emoji-tabs" class="dg-booking-qr-emoji-tabs" role="tablist"></div>
            <div id="dg-qr-emoji-picks" class="dg-booking-qr-emoji-picks" role="listbox" aria-label="Emoji-Auswahl"></div>
            <p class="dg-field-hint">Über <span id="dg-qr-emoji-count">…</span> Emojis — Kategorien oder Suche. Beliebiges Emoji auch per Zwischenablage einfügen.</p>
          </div>
          <p class="dg-field-hint" id="dg-qr-ec-note"<?= in_array($centerSource, ['logo', 'favicon', 'custom', 'emoji'], true) ? '' : ' hidden' ?>>
            Bei Logo/Emoji wird die Fehlerkorrektur automatisch auf H (max.) gesetzt — bitte einmal mit dem Handy scannen.
          </p>
        </fieldset>

        <div class="dg-form-grid">
          <label class="dg-field">
            <span>Modul-Form</span>
            <select name="qr[dots_type]" id="dg-qr-dots" data-dg-qr-field>
              <?php foreach (['square' => 'Quadratisch', 'rounded' => 'Abgerundet', 'dots' => 'Punkte', 'classy' => 'Classy', 'classy-rounded' => 'Classy abgerundet', 'extra-rounded' => 'Extra rund'] as $val => $lab) : ?>
                <option value="<?= $val ?>"<?= ($qr['dots_type'] ?? '') === $val ? ' selected' : '' ?>><?= $lab ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="dg-field">
            <span>Gesamtform</span>
            <select name="qr[shape]" id="dg-qr-shape" data-dg-qr-field>
              <option value="square"<?= ($qr['shape'] ?? '') === 'square' ? ' selected' : '' ?>>Quadratisch</option>
              <option value="circle"<?= ($qr['shape'] ?? '') === 'circle' ? ' selected' : '' ?>>Kreis</option>
            </select>
          </label>
          <label class="dg-field">
            <span>Eck-Rahmen</span>
            <select name="qr[corners_square_type]" id="dg-qr-corners-sq" data-dg-qr-field>
              <option value="square"<?= ($qr['corners_square_type'] ?? '') === 'square' ? ' selected' : '' ?>>Quadrat</option>
              <option value="dot"<?= ($qr['corners_square_type'] ?? '') === 'dot' ? ' selected' : '' ?>>Punkt</option>
              <option value="extra-rounded"<?= ($qr['corners_square_type'] ?? '') === 'extra-rounded' ? ' selected' : '' ?>>Extra rund</option>
            </select>
          </label>
          <label class="dg-field">
            <span>Eck-Punkt</span>
            <select name="qr[corners_dot_type]" id="dg-qr-corners-dot" data-dg-qr-field>
              <option value="square"<?= ($qr['corners_dot_type'] ?? '') === 'square' ? ' selected' : '' ?>>Quadrat</option>
              <option value="dot"<?= ($qr['corners_dot_type'] ?? '') === 'dot' ? ' selected' : '' ?>>Punkt</option>
            </select>
          </label>
        </div>

        <fieldset class="dg-field dg-field--wide">
          <legend>Rahmenvorlage</legend>
          <label class="dg-radio"><input type="radio" name="qr[frame_preset]" value="none"<?= ($qr['frame_preset'] ?? '') === 'none' ? ' checked' : '' ?> data-dg-qr-preset data-dg-qr-field> Kein Rahmen</label>
          <label class="dg-radio"><input type="radio" name="qr[frame_preset]" value="classic"<?= ($qr['frame_preset'] ?? '') === 'classic' ? ' checked' : '' ?> data-dg-qr-preset data-dg-qr-field> Klassisch</label>
          <label class="dg-radio"><input type="radio" name="qr[frame_preset]" value="soft"<?= ($qr['frame_preset'] ?? '') === 'soft' ? ' checked' : '' ?> data-dg-qr-preset data-dg-qr-field> Weich</label>
          <label class="dg-radio"><input type="radio" name="qr[frame_preset]" value="bold"<?= ($qr['frame_preset'] ?? '') === 'bold' ? ' checked' : '' ?> data-dg-qr-preset data-dg-qr-field> Kräftig</label>
          <label class="dg-radio"><input type="radio" name="qr[frame_preset]" value="card"<?= ($qr['frame_preset'] ?? '') === 'card' ? ' checked' : '' ?> data-dg-qr-preset data-dg-qr-field> Karte</label>
          <input type="hidden" name="qr[frame_enabled]" id="dg-qr-frame-enabled" value="<?= !empty($qr['frame_enabled']) ? '1' : '0' ?>">
          <div class="dg-form-grid" style="margin-top:8px">
            <label class="dg-field"><span>Rahmenfarbe</span><input type="color" name="qr[frame_color]" id="dg-qr-frame-color" value="<?= View::escape((string) $qr['frame_color']) ?>" data-dg-qr-field></label>
            <label class="dg-field"><span>Stärke (px)</span><input type="number" name="qr[frame_width]" id="dg-qr-frame-width" value="<?= (int) $qr['frame_width'] ?>" min="0" max="24" data-dg-qr-field></label>
            <label class="dg-field"><span>Radius (px)</span><input type="number" name="qr[frame_radius]" id="dg-qr-frame-radius" value="<?= (int) $qr['frame_radius'] ?>" min="0" max="80" data-dg-qr-field></label>
            <label class="dg-field"><span>Innenabstand (px)</span><input type="number" name="qr[frame_padding]" id="dg-qr-frame-padding" value="<?= (int) $qr['frame_padding'] ?>" min="0" max="80" data-dg-qr-field></label>
          </div>
        </fieldset>

        <div class="dg-form-grid">
          <label class="dg-field">
            <span>Vorschau-Größe (px)</span>
            <input type="number" name="qr[size]" id="dg-qr-size" value="<?= (int) $qr['size'] ?>" min="160" max="800" step="10" data-dg-qr-field>
          </label>
          <label class="dg-field">
            <span>Quiet Zone (px)</span>
            <input type="number" name="qr[margin]" id="dg-qr-margin" value="<?= (int) $qr['margin'] ?>" min="0" max="80" data-dg-qr-field>
          </label>
          <label class="dg-field">
            <span>Download-Auflösung (px)</span>
            <input type="number" name="qr[export_size]" id="dg-qr-export-size" value="<?= (int) $qr['export_size'] ?>" min="400" max="2400" step="50" data-dg-qr-field>
          </label>
          <label class="dg-field">
            <span>Dateiname</span>
            <input type="text" name="qr[download_name]" id="dg-qr-download-name" value="<?= View::escape((string) $qr['download_name']) ?>" data-dg-qr-field>
          </label>
          <label class="dg-field">
            <span>Fehlerkorrektur</span>
            <select name="qr[error_correction]" id="dg-qr-ec" data-dg-qr-field>
              <option value="L"<?= ($qr['error_correction'] ?? '') === 'L' ? ' selected' : '' ?>>L (~7 %)</option>
              <option value="M"<?= ($qr['error_correction'] ?? '') === 'M' ? ' selected' : '' ?>>M (~15 %)</option>
              <option value="Q"<?= ($qr['error_correction'] ?? '') === 'Q' ? ' selected' : '' ?>>Q (~25 %)</option>
              <option value="H"<?= ($qr['error_correction'] ?? '') === 'H' ? ' selected' : '' ?>>H (~30 %)</option>
            </select>
          </label>
        </div>
        <fieldset class="dg-field dg-field--wide" style="margin-top:16px">
          <legend>Flyer-Text &amp; Layout</legend>
          <label class="dg-field dg-field--wide">
            <span>Überschrift (Headline)</span>
            <input type="text" name="flyer[headline]" id="dg-flyer-headline" value="<?= View::escape((string) $flyer['headline']) ?>" maxlength="120" data-dg-qr-field data-dg-flyer-field>
            <small class="dg-field-hint">Kurz und nutzenorientiert — z.&nbsp;B. „Wunschtermin in 60 Sekunden sichern!“</small>
            <div class="dg-booking-flyer-presets">
              <?php foreach ($headlinePresets as $hp) : ?>
                <button type="button" class="dg-button dg-button--small" data-flyer-set="headline" data-value="<?= View::escape($hp) ?>"><?= View::escape($hp) ?></button>
              <?php endforeach; ?>
            </div>
          </label>
          <label class="dg-field dg-field--wide">
            <span>Handlungsaufforderung (CTA)</span>
            <input type="text" name="flyer[cta]" id="dg-flyer-cta" value="<?= View::escape((string) $flyer['cta']) ?>" maxlength="80" data-dg-qr-field data-dg-flyer-field>
            <div class="dg-booking-flyer-presets">
              <?php foreach ($ctaPresets as $cp) : ?>
                <button type="button" class="dg-button dg-button--small" data-flyer-set="cta" data-value="<?= View::escape($cp) ?>"><?= View::escape($cp) ?></button>
              <?php endforeach; ?>
            </div>
          </label>
          <div class="dg-form-grid">
            <label class="dg-field">
              <span>CTA-Position</span>
              <select name="flyer[cta_position]" id="dg-flyer-cta-pos" data-dg-qr-field data-dg-flyer-field>
                <option value="below"<?= ($flyer['cta_position'] ?? '') === 'below' ? ' selected' : '' ?>>Unter dem QR-Code</option>
                <option value="above"<?= ($flyer['cta_position'] ?? '') === 'above' ? ' selected' : '' ?>>Über dem QR-Code</option>
              </select>
            </label>
            <label class="dg-field">
              <span>Format</span>
              <select name="flyer[format]" id="dg-flyer-format" data-dg-qr-field data-dg-flyer-field>
                <option value="a6"<?= ($flyer['format'] ?? '') === 'a6' ? ' selected' : '' ?>>A6 Hochformat</option>
                <option value="a5"<?= ($flyer['format'] ?? '') === 'a5' ? ' selected' : '' ?>>A5 Hochformat</option>
                <option value="square"<?= ($flyer['format'] ?? '') === 'square' ? ' selected' : '' ?>>Quadrat (Social/Aufkleber)</option>
              </select>
            </label>
            <label class="dg-field">
              <span>Weißraum um QR (mm)</span>
              <input type="number" name="flyer[quiet_mm]" id="dg-flyer-quiet" value="<?= (int) ($flyer['quiet_mm'] ?? 15) ?>" min="10" max="25" data-dg-qr-field data-dg-flyer-field>
            </label>
            <label class="dg-field">
              <span>Akzentfarbe</span>
              <input type="color" name="flyer[accent_color]" id="dg-flyer-accent" value="<?= View::escape($flyerAccent) ?>" data-dg-qr-field data-dg-flyer-field title="Leer speichern = CRM-Primärfarbe: Feld auf Markenfarbe lassen oder zurücksetzen">
              <small class="dg-field-hint">Standard: CRM-Markenfarbe</small>
            </label>
            <label class="dg-field">
              <span>Flyer-Hintergrund</span>
              <input type="color" name="flyer[bg_color]" id="dg-flyer-bg" value="<?= View::escape((string) $flyer['bg_color']) ?>" data-dg-qr-field data-dg-flyer-field>
            </label>
            <label class="dg-field">
              <span>Textfarbe</span>
              <input type="color" name="flyer[text_color]" id="dg-flyer-text" value="<?= View::escape((string) $flyer['text_color']) ?>" data-dg-qr-field data-dg-flyer-field>
            </label>
          </div>
          <label class="dg-field dg-field--wide">
            <span>
              <input type="hidden" name="flyer[show_logo]" value="0">
              <input type="checkbox" name="flyer[show_logo]" id="dg-flyer-show-logo" value="1"<?= !empty($flyer['show_logo']) ? ' checked' : '' ?> data-dg-qr-field data-dg-flyer-field>
              Logo oben anzeigen (CRM-Logo)
            </span>
          </label>
        </fieldset>
      </div>

      <div class="dg-booking-qr-preview-panel">
        <div class="dg-booking-preview-tabs" role="tablist">
          <button type="button" class="dg-booking-preview-tab is-active" data-preview-mode="qr" role="tab">QR-Code</button>
          <button type="button" class="dg-booking-preview-tab" data-preview-mode="flyer" role="tab">Flyer</button>
        </div>

        <div id="dg-qr-mode-qr" class="dg-booking-preview-mode">
          <h5 class="dg-subsection-title">QR-Vorschau</h5>
          <div id="dg-qr-print-area" class="dg-booking-qr-print-area">
            <div id="dg-qr-frame" class="dg-booking-qr-frame">
              <div id="dg-qr-canvas-host"></div>
            </div>
            <p id="dg-qr-caption-preview" class="dg-booking-qr-caption"></p>
          </div>
          <div class="dg-form-actions">
            <button type="button" class="dg-button dg-button--primary" id="dg-qr-download-png">PNG laden</button>
            <button type="button" class="dg-button" id="dg-qr-download-svg">SVG laden</button>
            <button type="button" class="dg-button" id="dg-qr-print">QR drucken</button>
          </div>
        </div>

        <div id="dg-qr-mode-flyer" class="dg-booking-preview-mode" hidden>
          <h5 class="dg-subsection-title">Flyer-Vorschau</h5>
          <div id="dg-flyer-sheet" class="dg-booking-flyer-sheet dg-booking-flyer-sheet--a6" aria-label="Flyer-Vorschau">
            <div class="dg-booking-flyer-brand" id="dg-flyer-brand">
              <img id="dg-flyer-logo" class="dg-booking-flyer-logo" alt="" hidden>
              <p class="dg-booking-flyer-company" id="dg-flyer-company"></p>
            </div>
            <h2 class="dg-booking-flyer-headline" id="dg-flyer-headline-preview"></h2>
            <p class="dg-booking-flyer-cta dg-booking-flyer-cta--above" id="dg-flyer-cta-above" hidden></p>
            <div class="dg-booking-flyer-qr-wrap" id="dg-flyer-qr-wrap">
              <div id="dg-flyer-qr-host" class="dg-booking-flyer-qr-host"></div>
            </div>
            <p class="dg-booking-flyer-cta dg-booking-flyer-cta--below" id="dg-flyer-cta-below"></p>
          </div>
          <div class="dg-form-actions">
            <button type="button" class="dg-button dg-button--primary" id="dg-flyer-print">Flyer drucken</button>
            <button type="button" class="dg-button" id="dg-flyer-download-png">Flyer als PNG</button>
          </div>
        </div>

        <p id="dg-qr-status" class="dg-field-hint" aria-live="polite"></p>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="dg-form-actions">
    <button type="submit" name="calendar_embed_save" value="1" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>>Speichern</button>
  </div>
</form>
