<?php
/** @var array<string, mixed>|null $mediaItem */
/** @var bool $mediaIsNew */
/** @var array{type: string, message: string}|null $flash */

$mediaId = $mediaIsNew ? '' : (string) ($mediaItem['media_id'] ?? '');
$isSvg = !$mediaIsNew && ($mediaItem['mime_type'] ?? '') === 'image/svg+xml';
$isLogo = $mediaId !== '' && AppearanceSettings::logoMediaId() === $mediaId;
$isFavicon = $mediaId !== '' && AppearanceSettings::faviconMediaId() === $mediaId;
$previewUrl = $mediaIsNew ? '' : MediaStorage::adminPreviewUrl(
    $mediaId,
    !$mediaIsNew ? strtotime((string) ($mediaItem['updated_at'] ?? '')) ?: null : null
);
$previewAlt = $mediaIsNew ? '' : trim((string) ($mediaItem['alt_text'] ?? ''));
if ($previewAlt === '' && !$mediaIsNew) {
    $previewAlt = trim((string) ($mediaItem['title'] ?? ''));
}
if ($previewAlt === '' && !$mediaIsNew) {
    $previewAlt = 'Bildvorschau';
}
$usages = (!$mediaIsNew && is_array($mediaItem['usages'] ?? null)) ? $mediaItem['usages'] : [];
$formatUsageDate = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '—';
    }
    $ts = strtotime($value);

    return $ts !== false ? date('d.m.Y H:i', $ts) : $value;
};
?>
<div class="dg-wrap dg-media-edit" data-media-new="<?= $mediaIsNew ? '1' : '0' ?>">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header">
    <?php View::partial('partials/back-nav', [
        'href' => '/app?page=bilder',
        'label' => 'Zurück zur Liste',
    ]); ?>
    <h1 class="dg-page-title"><?= $mediaIsNew ? 'Bild hochladen' : 'Bild bearbeiten' ?></h1>
    <?php if (!$mediaIsNew && $mediaId !== '') : ?>
      <dl class="dg-media-edit-ids">
        <div>
          <dt>ID</dt>
          <dd><code><?= View::escape($mediaId) ?></code></dd>
        </div>
        <div>
          <dt>URL</dt>
          <dd><a href="<?= View::escape(MediaStorage::publicUrl($mediaId)) ?>" target="_blank" rel="noopener noreferrer"><code><?= View::escape(MediaStorage::publicUrl($mediaId)) ?></code></a></dd>
        </div>
      </dl>
    <?php else : ?>
      <p class="dg-lead">Datei wählen, Metadaten pflegen und mit einem Klick speichern.</p>
    <?php endif; ?>
  </header>

  <div class="dg-media-edit-layout">
    <section class="dg-panel dg-media-edit-preview">
      <?php if ($mediaIsNew) : ?>
        <h2 class="dg-media-edit-preview__title">Vorschau</h2>
        <label class="dg-field dg-field--wide">
          <span>Bilddatei *</span>
          <input type="file" id="dg-media-upload" accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml" required>
          <small class="dg-field-hint">JPG, PNG, WebP, GIF oder SVG — keine PDFs oder Office-Dateien.</small>
        </label>
        <div class="dg-media-edit-preview__frame">
          <img id="dg-media-preview" class="dg-media-preview--empty" src="" alt="" hidden>
          <p class="dg-media-edit-preview__placeholder" id="dg-media-preview-placeholder">Noch kein Bild gewählt</p>
        </div>
      <?php else : ?>
        <h2 class="dg-media-edit-preview__title">Vorschau</h2>
        <div class="dg-media-edit-preview__frame">
          <img id="dg-media-preview" src="<?= View::escape($previewUrl) ?>" alt="<?= View::escape($previewAlt) ?>">
        </div>
        <p class="dg-media-edit-preview__open">
          <a href="<?= View::escape($previewUrl) ?>" target="_blank" rel="noopener noreferrer">Vollbild in neuem Tab öffnen</a>
        </p>
        <dl class="dg-media-meta-dl">
          <div><dt>Abmessungen</dt><dd><?= !empty($mediaItem['width']) ? (int) $mediaItem['width'] . '×' . (int) $mediaItem['height'] . ' px' : '—' ?></dd></div>
          <div><dt>Dateigröße</dt><dd><?= View::escape(number_format(((int) ($mediaItem['size_bytes'] ?? 0)) / 1024, 1, ',', '.') . ' KB') ?></dd></div>
          <div><dt>Format</dt><dd><?= View::escape(strtoupper((string) ($mediaItem['extension'] ?? ''))) ?></dd></div>
          <div><dt>Hochgeladen</dt><dd><?= View::escape((string) ($mediaItem['uploaded_at'] ?? '')) ?></dd></div>
        </dl>
        <label class="dg-field dg-field--wide dg-media-favicon-toggle">
          <span>
            <input type="checkbox" id="dg-media-use-logo"<?= $isLogo ? ' checked' : '' ?>>
            Als CRM-Logo in Kopfzeile
          </span>
          <small class="dg-field-hint">Zeigt dieses Bild in der CRM-Kopfzeile und auf der Login-Seite — nur wenn Sie es aktivieren.</small>
        </label>
        <label class="dg-field dg-field--wide dg-media-favicon-toggle">
          <span>
            <input type="checkbox" id="dg-media-use-favicon"<?= $isFavicon ? ' checked' : '' ?>>
            Als Favicon für Browser benutzen
          </span>
          <small class="dg-field-hint">Erzeugt 16×16, 32×32 und 48×48 px für Browser-Tabs — nur wenn Sie es aktivieren.</small>
        </label>
      <?php endif; ?>
    </section>

    <div class="dg-media-edit-forms">
      <form class="dg-form dg-panel" id="dg-media-meta-form">
        <h2><?= $mediaIsNew ? 'Bild &amp; Metadaten' : 'Metadaten' ?></h2>
        <input type="hidden" name="media_id" value="<?= View::escape($mediaId) ?>">
        <label class="dg-field dg-field--wide">
          <span>Titel</span>
          <input type="text" name="title" value="<?= View::escape((string) ($mediaItem['title'] ?? '')) ?>">
        </label>
        <label class="dg-field dg-field--wide">
          <span>Alt-Text</span>
          <input type="text" name="alt_text" value="<?= View::escape((string) ($mediaItem['alt_text'] ?? '')) ?>">
        </label>
        <label class="dg-field dg-field--wide">
          <span>Quelle / Rechte</span>
          <textarea name="source_note" rows="4" placeholder="Freitext, URL (https://…) oder HTML — z. B. &lt;a href=&quot;…&quot;&gt;Lizenz&lt;/a&gt;"><?= View::escape((string) ($mediaItem['source_note'] ?? '')) ?></textarea>
          <small class="dg-field-hint">Freitext, URLs und HTML erlaubt. In der Bilderliste wird nur der Klartext-Vorschau angezeigt.</small>
        </label>
        <div class="dg-form-actions">
          <button type="submit" class="dg-button dg-button--primary" id="dg-media-save-primary"><?= $mediaIsNew ? 'Hochladen &amp; speichern' : 'Metadaten speichern' ?></button>
          <a class="dg-button" href="/app?page=bilder">Zurück zur Liste</a>
        </div>
      </form>

      <?php if (!$mediaIsNew) : ?>
        <?php if (!$isSvg) : ?>
          <form
            class="dg-form dg-panel"
            id="dg-media-transform-form"
            data-orig-width="<?= !empty($mediaItem['width']) ? (int) $mediaItem['width'] : '' ?>"
            data-orig-height="<?= !empty($mediaItem['height']) ? (int) $mediaItem['height'] : '' ?>"
          >
            <h2>Größe &amp; Format</h2>
            <input type="hidden" name="media_id" value="<?= View::escape($mediaId) ?>">
            <div class="dg-form-grid">
              <label class="dg-field">
                <span>Breite (px)</span>
                <input type="number" name="max_width" min="1" max="10000" placeholder="z. B. 1200">
              </label>
              <label class="dg-field">
                <span>Höhe (px)</span>
                <input type="number" name="max_height" min="1" max="10000" placeholder="z. B. 800">
              </label>
              <label class="dg-field">
                <span>Zielformat</span>
                <select name="target_format">
                  <option value="keep">Behalten</option>
                  <option value="webp">WebP (empfohlen)</option>
                  <option value="jpeg">JPEG</option>
                  <option value="png">PNG</option>
                </select>
              </label>
            </div>
            <label class="dg-field dg-field--wide dg-media-favicon-toggle">
              <span>
                <input type="checkbox" name="keep_aspect" id="dg-media-keep-aspect" value="1" checked>
                Verhältnis beibehalten
              </span>
              <small class="dg-field-hint">Angehakt: der andere Wert folgt dem gleichen Faktor wie das Original, z.&nbsp;B. Höhe = 89 × (174 ÷ 87). Ohne Haken: Breite und Höhe unabhängig.</small>
            </label>
            <p class="dg-field-hint dg-media-transform-calc" id="dg-media-transform-calc" hidden></p>
            <div class="dg-form-actions">
              <button type="submit" class="dg-button dg-button--primary">Als neues Bild anlegen</button>
            </div>
            <p class="dg-field-hint">Erzeugt einen neuen Eintrag in der Liste. Das Original bleibt unverändert.</p>
          </form>

          <section class="dg-panel">
            <h2>Zuschneiden</h2>
            <p class="dg-field-hint">Legt ein neues Bild mit dem Zuschnitt an — das Original bleibt erhalten.</p>
            <button type="button" class="dg-button" id="dg-media-crop-open">Zuschneiden …</button>
          </section>

          <section class="dg-panel">
            <h2>Hintergrund entfernen / Freistellen</h2>
            <p class="dg-field-hint">Kostenlos im Browser (WASM). Erzeugt ein neues freigestelltes Bild; das Original bleibt erhalten.</p>
            <p class="dg-field-hint" id="dg-media-bg-status">Beim ersten Mal kann das Laden etwas dauern.</p>
            <button type="button" class="dg-button" id="dg-media-bg-remove">Hintergrund entfernen</button>
          </section>
        <?php else : ?>
          <div class="dg-panel dg-panel--notice">
            <p>SVG: Metadaten bearbeiten; Raster-Umwandlung nicht verfügbar.</p>
          </div>
        <?php endif; ?>

        <section class="dg-panel dg-panel--danger">
          <h2>Löschen</h2>
          <button type="button" class="dg-button dg-button--danger" id="dg-media-delete">Bild löschen</button>
        </section>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!$mediaIsNew) : ?>
    <section class="dg-panel dg-media-edit-usage">
      <div class="dg-media-edit-usage__head">
        <h2>Verwendung</h2>
        <button type="button" class="dg-button dg-button--small" id="dg-media-scan-inline">Verwendung scannen</button>
      </div>
      <?php if ($usages === []) : ?>
        <p class="dg-lead">Noch keine Referenz im CRM gefunden. Klicken Sie auf <strong>Verwendung scannen</strong>, um Seiten und Einstellungen zu prüfen.</p>
      <?php else : ?>
        <div class="dg-table-wrap">
          <table class="dg-table dg-table--compact dg-media-usage-table">
            <thead>
              <tr>
                <th>Verwendung</th>
                <th>Beginn</th>
                <th>Ende</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($usages as $usage) : ?>
                <?php $isActive = empty($usage['used_until']); ?>
                <tr class="<?= $isActive ? 'is-active' : '' ?>">
                  <td><strong><?= View::escape((string) $usage['context_label']) ?></strong></td>
                  <td><?= View::escape($formatUsageDate((string) ($usage['used_from'] ?? ''))) ?></td>
                  <td><?= View::escape($formatUsageDate($isActive ? null : (string) ($usage['used_until'] ?? ''))) ?></td>
                  <td>
                    <?php if ($isActive) : ?>
                      <span class="dg-badge dg-badge--ok">aktiv</span>
                    <?php else : ?>
                      <span class="dg-badge">beendet</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>

<?php if (!$mediaIsNew && !$isSvg) : ?>
<div class="dg-modal" id="dg-media-crop-modal" hidden aria-hidden="true">
  <div class="dg-modal__backdrop" data-crop-close></div>
  <div class="dg-modal__dialog dg-media-crop-dialog" role="dialog" aria-labelledby="dg-media-crop-title">
    <header class="dg-modal__head">
      <h2 id="dg-media-crop-title">Bild zuschneiden</h2>
      <button type="button" class="dg-modal__close" data-crop-close aria-label="Schließen">×</button>
    </header>
    <div class="dg-media-crop-body">
      <img id="dg-media-crop-image" src="" alt="">
    </div>
    <footer class="dg-modal__foot">
      <button type="button" class="dg-button" data-crop-close>Abbrechen</button>
      <button type="button" class="dg-button dg-button--primary" id="dg-media-crop-apply">Zuschnitt übernehmen</button>
    </footer>
  </div>
</div>
<?php endif; ?>
