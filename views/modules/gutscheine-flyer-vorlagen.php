<?php
/**
 * @var list<array<string, mixed>> $flyerTemplateList
 * @var array<string, mixed>|null $flyerTemplateForm
 * @var int|null $flyerTemplateId
 * @var string $flyerTemplateMode list|edit|new
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$list = $flyerTemplateList ?? [];
$form = $flyerTemplateForm ?? null;
$mode = $flyerTemplateMode ?? 'list';
$id = (int) ($flyerTemplateId ?? 0);
$isEdit = $mode === 'edit' || $mode === 'new';
$occasionLabels = FlyerService::occasionLabels();
$formatLabels = FlyerService::formatLabels();
$layout = is_array($form) ? FlyerService::layoutFromTemplate($form) : FlyerService::defaultLayout();
$bgMediaId = (string) ($layout['bg']['media_id'] ?? '');
$bgPreview = $bgMediaId !== '' ? MediaStorage::publicUrl($bgMediaId) : '';
$imageMediaId = (string) ($layout['image_media_id'] ?? '');
$imagePreview = $imageMediaId !== '' ? MediaStorage::publicUrl($imageMediaId) : '';
$companySocials = FlyerService::companySocialLinks();
$companyWebsiteUrl = FlyerService::companyWebsiteUrl();
$offerWebsite = !empty($layout['qr']['offer_website']);
$selectedSocialKeys = is_array($layout['qr']['social_keys'] ?? null) ? array_values($layout['qr']['social_keys']) : [];
$socialSlotCount = count($companySocials);
if ($socialSlotCount === 0 && $offerWebsite && $companyWebsiteUrl !== '') {
    $socialSlotCount = 1;
}
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Flyer-Vorlagen</h1>
      <p class="dg-lead">Gutschein-Formular-Flyer (A6/A5/A4) — Format, Hintergrund, Texte, QR und Handschrift-Zone. Betrag und Code kommen immer vom ausgestellten Gutschein.</p>
    </div>
    <div class="dg-toolbar">
      <a class="dg-button" href="/app?page=gutscheine">Gutscheine</a>
      <?php if ($canEdit && $dbConnected && !$isEdit) : ?>
        <a class="dg-button dg-button--primary" href="/app?page=gutscheine-flyer-vorlagen&amp;action=new">Neue Vorlage</a>
      <?php endif; ?>
    </div>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Datenbank nicht verbunden.
      <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Einstellungen → Datenbank</a>
    </div>
  <?php endif; ?>

  <?php if ($isEdit && is_array($form)) : ?>
    <a class="dg-button dg-button--back" href="/app?page=gutscheine-flyer-vorlagen">← Zurück zur Liste</a>

    <form
      class="dg-form dg-panel"
      method="post"
      action="/app?page=gutscheine-flyer-vorlagen"
      id="dg-letterhead-settings"
      data-media-list-url="/api/media?action=list"
    >
      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
      <input type="hidden" name="flyer_template_save" value="1">
      <?php if ($id > 0) : ?>
        <input type="hidden" name="id" value="<?= $id ?>">
      <?php endif; ?>

      <h2 class="dg-section-title">Stammdaten</h2>
      <div class="dg-form-grid">
        <label class="dg-field">
          <span>Titel *</span>
          <input type="text" name="title" required maxlength="191" value="<?= View::escape((string) ($form['title'] ?? '')) ?>"<?= !$canEdit ? ' disabled' : '' ?>>
        </label>
        <label class="dg-field">
          <span>Anlass</span>
          <select name="occasion"<?= !$canEdit ? ' disabled' : '' ?>>
            <?php foreach ($occasionLabels as $k => $lab) : ?>
              <option value="<?= View::escape($k) ?>"<?= ((string) ($form['occasion'] ?? 'general')) === $k ? ' selected' : '' ?>><?= View::escape($lab) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dg-field">
          <span>Sortierung</span>
          <input type="number" name="sort_order" value="<?= (int) ($form['sort_order'] ?? 0) ?>"<?= !$canEdit ? ' disabled' : '' ?>>
        </label>
        <label class="dg-field dg-field--check">
          <input type="checkbox" name="is_active" value="1"<?= !empty($form['is_active']) || $mode === 'new' ? ' checked' : '' ?><?= !$canEdit ? ' disabled' : '' ?>>
          <span>Aktiv (auswählbar beim Ausstellen)</span>
        </label>
      </div>

      <h2 class="dg-section-title">Layout</h2>
      <div class="dg-form-grid">
        <label class="dg-field">
          <span>Format</span>
          <select name="layout[format]"<?= !$canEdit ? ' disabled' : '' ?>>
            <?php foreach ($formatLabels as $k => $lab) : ?>
              <option value="<?= View::escape($k) ?>"<?= ((string) ($layout['format'] ?? 'a6')) === $k ? ' selected' : '' ?>><?= View::escape($lab) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dg-field">
          <span>Hintergrund</span>
          <select name="layout[bg_mode]" id="dg-flyer-bg-mode"<?= !$canEdit ? ' disabled' : '' ?>>
            <option value="color"<?= (($layout['bg']['mode'] ?? '') === 'color') ? ' selected' : '' ?>>Farbe</option>
            <option value="image"<?= (($layout['bg']['mode'] ?? '') === 'image') ? ' selected' : '' ?>>Bild (Mediathek)</option>
          </select>
        </label>
        <label class="dg-field" id="dg-flyer-bg-color-wrap">
          <span>Hintergrundfarbe</span>
          <input type="color" name="layout[bg_color]" value="<?= View::escape((string) ($layout['bg']['color'] ?? '#ffffff')) ?>"<?= !$canEdit ? ' disabled' : '' ?>>
        </label>
        <label class="dg-field">
          <span>Deckkraft Hintergrundbild</span>
          <input type="number" name="layout[bg_opacity]" min="0.2" max="1" step="0.05" value="<?= View::escape((string) ($layout['bg']['opacity'] ?? '1')) ?>"<?= !$canEdit ? ' disabled' : '' ?>>
        </label>
        <label class="dg-field">
          <span>Textfarbe</span>
          <input type="color" name="layout[text_color]" value="<?= View::escape((string) ($layout['text_color'] ?? '#1a1a1a')) ?>"<?= !$canEdit ? ' disabled' : '' ?>>
        </label>
        <label class="dg-field">
          <span>Akzentfarbe</span>
          <input type="color" name="layout[accent_color]" value="<?= View::escape((string) ($layout['accent_color'] ?? '#2f5d50')) ?>"<?= !$canEdit ? ' disabled' : '' ?>>
        </label>
      </div>

      <div class="dg-field dg-field--wide" id="dg-flyer-bg-media-wrap">
        <span>Hintergrundbild</span>
        <input type="hidden" name="layout[bg_media_id]" id="dg-flyer-bg-media-id" value="<?= View::escape($bgMediaId) ?>">
        <p class="dg-form-actions" style="margin:0 0 8px">
          <button type="button" class="dg-button" data-dg-media-pick-open
            data-input="dg-flyer-bg-media-id"
            data-preview="dg-flyer-bg-preview"
            data-title="Hintergrund wählen"<?= !$canEdit ? ' disabled' : '' ?>>Bild wählen</button>
          <button type="button" class="dg-button" data-dg-media-pick-clear
            data-input="dg-flyer-bg-media-id"
            data-preview="dg-flyer-bg-preview"<?= !$canEdit ? ' disabled' : '' ?>>Entfernen</button>
        </p>
        <div id="dg-flyer-bg-preview" class="dg-letterhead-media-preview">
          <?php if ($bgPreview !== '') : ?>
            <img src="<?= View::escape($bgPreview) ?>" alt="">
          <?php else : ?>
            <span class="dg-field-hint">Kein Bild gewählt</span>
          <?php endif; ?>
        </div>
      </div>

      <div class="dg-stack">
        <label class="dg-field dg-field--check">
          <input type="checkbox" name="layout[show_logo]" value="1"<?= !empty($layout['show_logo']) ? ' checked' : '' ?><?= !$canEdit ? ' disabled' : '' ?>>
          <span>Firmenlogo anzeigen</span>
        </label>
        <label class="dg-field dg-field--check">
          <input type="checkbox" name="layout[contrast_banner]" value="1"<?= !empty($layout['contrast_banner']) ? ' checked' : '' ?><?= !$canEdit ? ' disabled' : '' ?>>
          <span>Kontrast-Banner hinter Überschrift (bei dunklem Bild)</span>
        </label>
      </div>

      <div class="dg-form-grid">
        <label class="dg-field dg-field--wide">
          <span>Überschrift</span>
          <input type="text" name="layout[headline]" maxlength="255" value="<?= View::escape((string) ($layout['headline'] ?? '')) ?>"<?= !$canEdit ? ' disabled' : '' ?>>
        </label>
        <label class="dg-field dg-field--wide">
          <span>Unterzeile</span>
          <input type="text" name="layout[subtitle]" maxlength="255" value="<?= View::escape((string) ($layout['subtitle'] ?? '')) ?>"<?= !$canEdit ? ' disabled' : '' ?>>
        </label>
      </div>

      <div class="dg-field dg-field--wide">
        <span>Bildzone (optional, z.&nbsp;B. Anlass-Grafik)</span>
        <input type="hidden" name="layout[image_media_id]" id="dg-flyer-image-media-id" value="<?= View::escape($imageMediaId) ?>">
        <p class="dg-form-actions" style="margin:0 0 8px">
          <button type="button" class="dg-button" data-dg-media-pick-open
            data-input="dg-flyer-image-media-id"
            data-preview="dg-flyer-image-preview"
            data-title="Bild wählen"<?= !$canEdit ? ' disabled' : '' ?>>Bild wählen</button>
          <button type="button" class="dg-button" data-dg-media-pick-clear
            data-input="dg-flyer-image-media-id"
            data-preview="dg-flyer-image-preview"<?= !$canEdit ? ' disabled' : '' ?>>Entfernen</button>
        </p>
        <div id="dg-flyer-image-preview" class="dg-letterhead-media-preview">
          <?php if ($imagePreview !== '') : ?>
            <img src="<?= View::escape($imagePreview) ?>" alt="">
          <?php else : ?>
            <span class="dg-field-hint">Kein Bild gewählt</span>
          <?php endif; ?>
        </div>
      </div>

      <h2 class="dg-section-title">QR-Codes</h2>
      <p class="dg-field-hint">Betrag und Gutscheincode werden immer systemseitig gedruckt. Social-Links kommen aus den Firmeneinstellungen (E-Mail-Layout → Social Media). QR mit Ruhezone ~8&nbsp;mm.</p>
      <div class="dg-stack">
        <label class="dg-field dg-field--check">
          <input type="checkbox" name="layout[qr_booking]" value="1"<?= !empty($layout['qr']['booking']) ? ' checked' : '' ?><?= !$canEdit ? ' disabled' : '' ?>>
          <span>Termin-QR (öffentliche Booking-URL)</span>
        </label>

        <?php if ($companySocials !== []) : ?>
          <?php if ($companyWebsiteUrl !== '') : ?>
            <label class="dg-field dg-field--check">
              <input type="checkbox" name="layout[offer_website]" id="dg-flyer-offer-website" value="1"<?= $offerWebsite ? ' checked' : '' ?><?= !$canEdit ? ' disabled' : '' ?>>
              <span>Eigene Website in der Auswahl anbieten</span>
            </label>
          <?php endif; ?>
          <div class="dg-form-grid" id="dg-flyer-social-slots">
            <?php for ($si = 0; $si < $socialSlotCount; $si++) :
                $selected = (string) ($selectedSocialKeys[$si] ?? '');
                $slotLabel = $socialSlotCount === 1 ? 'Social-QR' : ('Social-QR ' . ($si + 1));
                ?>
              <label class="dg-field">
                <span><?= View::escape($slotLabel) ?></span>
                <select name="layout[social_keys][]"<?= !$canEdit ? ' disabled' : '' ?>>
                  <option value="">— nicht verwenden —</option>
                  <?php foreach ($companySocials as $link) : ?>
                    <option value="<?= View::escape((string) $link['key']) ?>"<?= $selected === (string) $link['key'] ? ' selected' : '' ?>>
                      <?= View::escape((string) $link['label']) ?>
                    </option>
                  <?php endforeach; ?>
                  <?php if ($companyWebsiteUrl !== '') : ?>
                    <option
                      value="website"
                      class="dg-flyer-social-website-opt"
                      <?= $selected === 'website' ? ' selected' : '' ?>
                      <?= !$offerWebsite ? ' hidden disabled' : '' ?>
                    >Eigene Website</option>
                  <?php endif; ?>
                </select>
              </label>
            <?php endfor; ?>
          </div>
          <p class="dg-field-hint">Pro ausgefülltem Profil in den Firmeneinstellungen ein Auswahlfeld. Leere Felder erzeugen keinen QR.</p>
        <?php elseif ($companyWebsiteUrl !== '') : ?>
          <label class="dg-field dg-field--check">
            <input type="checkbox" name="layout[offer_website]" value="1"<?= $offerWebsite ? ' checked' : '' ?><?= !$canEdit ? ' disabled' : '' ?>>
            <span>Social-QR mit eigener Website</span>
          </label>
          <p class="dg-field-hint">Keine Social-Media-Links in den Firmeneinstellungen — Website nur auf ausdrücklichen Wunsch.</p>
        <?php else : ?>
          <p class="dg-field-hint">Keine Social-Media-Links und keine Website in den Firmeneinstellungen. Bitte unter Einstellungen → Benachrichtigungen (E-Mail-Layout) bzw. Firma pflegen.</p>
        <?php endif; ?>
      </div>

      <h2 class="dg-section-title">Handschrift &amp; Fußzeile</h2>
      <div class="dg-form-grid">
        <label class="dg-field dg-field--check">
          <input type="checkbox" name="layout[handwriting_enabled]" value="1"<?= !empty($layout['handwriting']['enabled']) ? ' checked' : '' ?><?= !$canEdit ? ' disabled' : '' ?>>
          <span>Handschrift-Zone (leer zum Beschreiben)</span>
        </label>
        <label class="dg-field">
          <span>Label Handschrift-Zone</span>
          <input type="text" name="layout[handwriting_label]" maxlength="120" value="<?= View::escape((string) ($layout['handwriting']['label'] ?? 'Persönliche Grüße')) ?>"<?= !$canEdit ? ' disabled' : '' ?>>
        </label>
        <label class="dg-field dg-field--wide">
          <span>Fußzeile (Einlösen-Hinweis)</span>
          <input type="text" name="layout[footer]" maxlength="500" value="<?= View::escape((string) ($layout['footer'] ?? '')) ?>"<?= !$canEdit ? ' disabled' : '' ?>>
        </label>
      </div>

      <?php if ($canEdit) : ?>
        <div class="dg-form-actions">
          <button type="submit" class="dg-button dg-button--primary">Speichern</button>
          <?php if ($id > 0) : ?>
            <a class="dg-button" href="/app?page=website-gutschein-flyer&amp;preview_template=<?= $id ?>" target="_blank" rel="noopener">Vorschau</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </form>
    <script>
      (function () {
        var mode = document.getElementById('dg-flyer-bg-mode');
        var colorWrap = document.getElementById('dg-flyer-bg-color-wrap');
        var mediaWrap = document.getElementById('dg-flyer-bg-media-wrap');
        function syncBg() {
          if (!mode) return;
          var img = mode.value === 'image';
          if (colorWrap) colorWrap.hidden = img;
          if (mediaWrap) mediaWrap.hidden = !img;
        }
        if (mode) mode.addEventListener('change', syncBg);
        syncBg();

        var offer = document.getElementById('dg-flyer-offer-website');
        function syncWebsiteOpts() {
          var on = !!(offer && offer.checked);
          document.querySelectorAll('.dg-flyer-social-website-opt').forEach(function (opt) {
            opt.hidden = !on;
            opt.disabled = !on;
            if (!on && opt.selected) {
              opt.selected = false;
              var sel = opt.closest('select');
              if (sel) sel.value = '';
            }
          });
        }
        if (offer) {
          offer.addEventListener('change', syncWebsiteOpts);
          syncWebsiteOpts();
        }
      })();
    </script>

  <?php else : ?>
    <div class="dg-table-wrap">
      <table class="dg-table">
        <thead>
          <tr>
            <th>Titel</th>
            <th>Anlass</th>
            <th>Format</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($list === []) : ?>
            <tr><td colspan="5" class="dg-table__empty">Noch keine Vorlagen.</td></tr>
          <?php else : ?>
            <?php foreach ($list as $row) : ?>
              <?php
                $tid = (int) ($row['id'] ?? 0);
                $lay = is_array($row['layout'] ?? null) ? $row['layout'] : FlyerService::layoutFromTemplate($row);
                $occ = (string) ($row['occasion'] ?? '');
              ?>
              <tr>
                <td><strong><?= View::escape((string) ($row['title'] ?? '')) ?></strong></td>
                <td><?= View::escape($occasionLabels[$occ] ?? $occ) ?></td>
                <td><?= View::escape(strtoupper((string) ($lay['format'] ?? 'a6'))) ?></td>
                <td>
                  <span class="dg-badge <?= !empty($row['is_active']) ? 'dg-badge--ok' : 'dg-badge--muted' ?>">
                    <?= !empty($row['is_active']) ? 'Aktiv' : 'Inaktiv' ?>
                  </span>
                </td>
                <td class="dg-table__actions">
                  <a href="/app?page=gutscheine-flyer-vorlagen&amp;action=edit&amp;id=<?= $tid ?>">Bearbeiten</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
