<?php
/** @var array<string, mixed> $form */
/** @var int|null $websiteFormId */
/** @var string|null $formError */
/** @var bool $canEdit */
/** @var array{type: string, message: string}|null $flash */
$isEdit = ($websiteFormId ?? 0) > 0;
$readOnly = !($canEdit ?? false);
$form = $form ?? WebsiteFormRepository::emptyForm();
$definitionJson = json_encode($form['definition'] ?? WebsiteFormRepository::emptyDefinition(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
$statusOptions = WebsiteFormRepository::statusOptions();
$formUserEmails = [];
foreach (UserRepository::all() as $u) {
    $email = trim((string) ($u->email ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        continue;
    }
    $formUserEmails[] = [
        'email' => $email,
        'label' => trim((string) ($u->displayName ?? $u->username ?? '')),
    ];
}
$formArticles = [];
if (Database::isConfigured()) {
    foreach (CalendarArticleRepository::all(true) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $formArticles[] = [
            'id' => (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
        ];
    }
}
?>
<div class="dg-wrap dg-website-editor">
  <?php
    View::partial('partials/back-nav', [
        'href' => '/app?page=website-formulare',
        'label' => 'Zurück zu den Formularen',
    ]);
  ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title"><?= $isEdit ? 'Formular bearbeiten' : 'Neues Formular' ?></h1>
      <p class="dg-lead">Felder links wählen, in der Vorschau anordnen, rechts konfigurieren — wie beim Seiten-Editor.</p>
    </div>
    <?php if ($isEdit) : ?>
      <div class="dg-page-header__actions">
        <a class="dg-button" href="/app?page=website-formular-inbox&amp;id=<?= (int) $websiteFormId ?>">Eingänge</a>
      </div>
    <?php endif; ?>
  </header>

  <?php View::render('partials/flash', compact('flash')); ?>
  <?php if (!empty($formError)) : ?>
    <div class="dg-flash dg-flash--error"><?= View::escape($formError) ?></div>
  <?php endif; ?>

  <form class="dg-form dg-website-editor__form" method="post" action="/app?page=website-formular-form" id="dg-website-form-editor">
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
    <input type="hidden" name="website_form_save" value="1">
    <?php if ($isEdit) : ?><input type="hidden" name="id" value="<?= (int) $websiteFormId ?>"><?php endif; ?>
    <textarea name="definition" id="dg-website-form-definition" hidden><?= View::escape($definitionJson) ?></textarea>

    <section class="dg-panel dg-website-editor__meta">
      <h2>Formular</h2>
      <div class="dg-form-grid">
        <label class="dg-field">
          <span>Titel *</span>
          <input name="title" value="<?= View::escape((string) ($form['title'] ?? '')) ?>" required<?= $readOnly ? ' readonly' : '' ?>>
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
      <?php if ($isEdit) : ?>
        <p class="dg-field-hint">Einbinden: Seiten-Block „Formular“ oder Shortcode <code>[dg-form id="<?= (int) $websiteFormId ?>"]</code></p>
      <?php endif; ?>
    </section>

    <div class="dg-website-builder dg-website-form-builder" id="dg-website-form-builder"<?= $readOnly ? ' data-readonly="1"' : '' ?>
         data-user-emails="<?= View::escape(json_encode($formUserEmails, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) ?>"
         data-articles="<?= View::escape(json_encode($formArticles, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) ?>">
      <?php if (!$readOnly) : ?>
        <aside class="dg-panel dg-website-builder__palette" aria-label="Felder">
          <h2>Felder</h2>
          <p class="dg-field-hint">Klicken zum Anhängen. Markiertes Feld: Plus darüber/darunter.</p>
          <div class="dg-website-builder__palette-list">
            <button type="button" class="dg-website-tool" data-add-field="text">Text</button>
            <button type="button" class="dg-website-tool" data-add-field="email">E-Mail</button>
            <button type="button" class="dg-website-tool" data-add-field="tel">Telefon</button>
            <button type="button" class="dg-website-tool" data-add-field="textarea">Textarea</button>
            <button type="button" class="dg-website-tool" data-add-field="select">Dropdown</button>
            <button type="button" class="dg-website-tool" data-add-field="intent">Anliegen (Termin/Artikel)</button>
            <button type="button" class="dg-website-tool" data-add-field="article">Artikel / DL</button>
            <button type="button" class="dg-website-tool" data-add-field="appointment">Buchungsnummer</button>
            <button type="button" class="dg-website-tool" data-add-field="checkbox">Checkboxen</button>
            <button type="button" class="dg-website-tool" data-add-field="radio">Radio</button>
            <button type="button" class="dg-website-tool" data-add-field="file">Datei-Upload</button>
            <button type="button" class="dg-website-tool" data-add-field="consent">Datenschutz</button>
            <button type="button" class="dg-website-tool" data-add-field="heading">Überschrift</button>
            <button type="button" class="dg-website-tool" data-add-field="paragraph">Hinweistext</button>
            <button type="button" class="dg-website-tool" data-add-field="submit">Absenden-Button</button>
          </div>
        </aside>
      <?php endif; ?>

      <section class="dg-website-builder__canvas-wrap" aria-label="Formularvorschau">
        <div class="dg-website-builder__canvas" id="dg-website-form-canvas"></div>
      </section>

      <?php if (!$readOnly) : ?>
        <aside class="dg-panel dg-website-builder__inspector" aria-label="Einstellungen">
          <h2>Einstellungen</h2>
          <div id="dg-website-form-inspector">
            <p class="dg-field-hint">Feld anklicken oder Formular-Einstellungen bearbeiten.</p>
          </div>
        </aside>
      <?php endif; ?>
    </div>

    <?php if (!$readOnly) : ?>
      <div class="dg-form-actions">
        <button type="submit" class="dg-button dg-button--primary">Formular speichern</button>
        <a class="dg-button" href="/app?page=website-formulare">Abbrechen</a>
        <?php if ($isEdit) : ?>
          <button type="submit" name="website_form_delete" value="1" class="dg-button dg-button--danger" onclick="return confirm('Dieses Formular und alle Eingänge wirklich löschen?');">Löschen</button>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </form>
</div>
