<?php
/** @var array<string, mixed> $form */
/** @var int|null $websitePageId */
/** @var string|null $formError */
/** @var bool $canEdit */
/** @var array{type: string, message: string}|null $flash */
$isEdit = ($websitePageId ?? 0) > 0;
$readOnly = !($canEdit ?? false);
$form = $form ?? WebsitePageRepository::emptyForm();
$layoutJson = json_encode($form['layout'] ?? WebsitePageRepository::emptyLayout(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
$statusOptions = WebsitePageRepository::statusOptions();
$initialSlug = WebsitePageRepository::sanitizeSlug((string) ($form['slug'] ?? ''));
$previewPath = $initialSlug !== '' ? '/vorschau/' . $initialSlug : '';
?>
<div class="dg-wrap dg-website-editor">
  <?php
    View::partial('partials/back-nav', [
        'href' => '/app?page=website-seiten',
        'label' => 'Zurück zu den Seiten',
    ]);
  ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title"><?= $isEdit ? 'Seite bearbeiten' : 'Neue Seite' ?></h1>
      <p class="dg-lead">Titel und Status oben, Inhalt in der Vorschau. Block markieren → Plus oben/unten/links/rechts zum Einfügen.</p>
    </div>
    <?php if ($isEdit && $previewPath !== '') : ?>
      <div class="dg-page-header__actions">
        <a
          class="dg-button"
          id="dg-website-preview-link"
          data-preview-path="<?= View::escape($previewPath) ?>"
          href="<?= View::escape($previewPath) ?>"
          target="_blank"
          rel="noopener"
        >Vorschau öffnen</a>
      </div>
    <?php endif; ?>
  </header>

  <?php View::render('partials/flash', compact('flash')); ?>

  <?php if (!empty($formError)) : ?>
    <div class="dg-flash dg-flash--error"><?= View::escape($formError) ?></div>
  <?php endif; ?>

  <form class="dg-form dg-website-editor__form" method="post" action="/app?page=website-seite-form" id="dg-website-page-form">
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
    <input type="hidden" name="website_page_save" value="1">
    <?php if ($isEdit) : ?><input type="hidden" name="id" value="<?= (int) $websitePageId ?>"><?php endif; ?>
    <textarea name="layout" id="dg-website-layout" hidden><?= View::escape($layoutJson) ?></textarea>

    <section class="dg-panel dg-website-editor__meta">
      <h2>Seite</h2>
      <div class="dg-form-grid">
        <label class="dg-field">
          <span>Titel *</span>
          <input name="title" id="dg-website-title" value="<?= View::escape((string) ($form['title'] ?? '')) ?>" required<?= $readOnly ? ' readonly' : '' ?>>
        </label>
        <label class="dg-field">
          <span>URL</span>
          <input name="slug" id="dg-website-slug" value="<?= View::escape((string) ($form['slug'] ?? '')) ?>" placeholder="start"<?= $readOnly ? ' readonly' : '' ?>>
          <small class="dg-field-hint">Nur Kleinbuchstaben, Zahlen und Bindestriche. Leer lassen zum automatischen Erzeugen.</small>
        </label>
        <label class="dg-field">
          <span>Status</span>
          <select name="status"<?= $readOnly ? ' disabled' : '' ?>>
            <?php foreach ($statusOptions as $value => $label) : ?>
              <option value="<?= View::escape($value) ?>"<?= ($form['status'] ?? '') === $value ? ' selected' : '' ?>><?= View::escape($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
    </section>

    <div class="dg-website-builder" id="dg-website-builder"<?= $readOnly ? ' data-readonly="1"' : '' ?>>
      <?php if (!$readOnly) : ?>
        <aside class="dg-panel dg-website-builder__palette" aria-label="Blöcke">
          <h2>Blöcke</h2>
          <p class="dg-field-hint" data-palette-hint>Spalte oder Block in der Vorschau anklicken — Plus-Zeichen erscheinen am Rand. Bild über einer Überschrift: Überschrift markieren → Plus oben → Bild.</p>
          <div class="dg-website-builder__palette-list">
            <button type="button" class="dg-website-tool" data-add-block="heading">Überschrift</button>
            <button type="button" class="dg-website-tool" data-add-block="text">Text</button>
            <button type="button" class="dg-website-tool" data-add-block="image">Bild</button>
            <button type="button" class="dg-website-tool" data-add-block="button">Button</button>
            <button type="button" class="dg-website-tool" data-add-block="spacer">Abstand</button>
            <button type="button" class="dg-website-tool" data-add-block="video">Video</button>
            <button type="button" class="dg-website-tool" data-add-block="divider">Trennlinie</button>
            <button type="button" class="dg-website-tool" data-add-block="html">HTML</button>
            <button type="button" class="dg-website-tool" data-add-block="form">Formular</button>
            <button type="button" class="dg-website-tool" data-add-block="gallery">Galerie</button>
          </div>
          <h2>Zeile</h2>
          <div class="dg-website-builder__palette-list">
            <button type="button" class="dg-website-tool" data-add-row="12">1 Spalte</button>
            <button type="button" class="dg-website-tool" data-add-row="6-6">2 Spalten</button>
            <button type="button" class="dg-website-tool" data-add-row="4-4-4">3 Spalten</button>
          </div>
        </aside>
      <?php endif; ?>

      <section class="dg-website-builder__canvas-wrap" aria-label="Seiteninhalt">
        <div class="dg-website-builder__canvas" id="dg-website-canvas"></div>
      </section>

      <?php if (!$readOnly) : ?>
        <aside class="dg-panel dg-website-builder__inspector" aria-label="Einstellungen">
          <h2>Einstellungen</h2>
          <div id="dg-website-inspector">
            <p class="dg-field-hint">Einen Block oder eine Spalte in der Vorschau auswählen.</p>
          </div>
        </aside>
      <?php endif; ?>
    </div>

    <?php if (!$readOnly) : ?>
      <div class="dg-form-actions">
        <button type="submit" class="dg-button dg-button--primary">Seite speichern</button>
        <a class="dg-button" href="/app?page=website-seiten">Abbrechen</a>
        <?php if ($isEdit) : ?>
          <button type="submit" name="website_page_delete" value="1" class="dg-button dg-button--danger" onclick="return confirm('Diese Seite wirklich löschen?');">Löschen</button>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </form>
</div>
