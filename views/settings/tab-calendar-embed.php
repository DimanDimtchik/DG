<?php
/** @var array<string, mixed> $calendarEmbedConfig */
/** @var bool $dbConnected */
$calendarEmbedConfig = $calendarEmbedConfig ?? CalendarEmbedSettings::forForm();
$publicUrl = (string) ($calendarEmbedConfig['public_url'] ?? CalendarEmbedSettings::publicBookingUrl());
$isEnabled = !empty($calendarEmbedConfig['online_booking_enabled']);
$qr = is_array($calendarEmbedConfig['qr'] ?? null) ? $calendarEmbedConfig['qr'] : CalendarEmbedSettings::qrDefaults();
$qrLogoUrl = (string) ($calendarEmbedConfig['qr_logo_url'] ?? '');
$qrFaviconUrl = (string) ($calendarEmbedConfig['qr_favicon_url'] ?? '');
$qrCenterCustomUrl = (string) ($calendarEmbedConfig['qr_center_custom_url'] ?? '');
$centerSource = (string) ($qr['center_image_source'] ?? 'none');
$emojis = ['📅', '🗓️', '✨', '💚', '🌿', '💇', '💅', '🧘', '☕', '🌸'];
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
       data-media-list-url="/api/media?action=list"
       data-csrf="<?= View::escape(Csrf::token()) ?>">
    <h4 class="dg-subsection-title">QR-Code Design &amp; Druck</h4>
    <p class="dg-field-hint">Für Flyer, Schaufenster oder Empfang — Logo/Emoji in der Mitte, Rahmenvorlagen, PNG/SVG-Download und Druck.</p>

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
            <input type="text" name="qr[center_emoji]" id="dg-qr-emoji" value="<?= View::escape((string) $qr['center_emoji']) ?>" maxlength="8" data-dg-qr-field class="dg-booking-qr-emoji-input">
            <div class="dg-booking-qr-emoji-picks" role="list">
              <?php foreach ($emojis as $em) : ?>
                <button type="button" class="dg-booking-qr-emoji-btn" data-emoji="<?= View::escape($em) ?>"><?= View::escape($em) ?></button>
              <?php endforeach; ?>
            </div>
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
      </div>

      <div class="dg-booking-qr-preview-panel">
        <h5 class="dg-subsection-title">Vorschau</h5>
        <div id="dg-qr-print-area" class="dg-booking-qr-print-area">
          <div id="dg-qr-frame" class="dg-booking-qr-frame">
            <div id="dg-qr-canvas-host"></div>
          </div>
          <p id="dg-qr-caption-preview" class="dg-booking-qr-caption"></p>
        </div>
        <div class="dg-form-actions">
          <button type="button" class="dg-button dg-button--primary" id="dg-qr-download-png">PNG laden</button>
          <button type="button" class="dg-button" id="dg-qr-download-svg">SVG laden</button>
          <button type="button" class="dg-button" id="dg-qr-print">Drucken</button>
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
