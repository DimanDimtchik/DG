<?php
/**
 * Dokumentenverfasser D2 — Neu/Bearbeiten + Briefpapier-Rahmen.
 *
 * @var array<string, mixed> $writerForm
 * @var int|null $writerDocumentId
 * @var list<array<string, mixed>> $writerVersions
 * @var array<string, bool> $writerExportFormats
 * @var string|null $formError
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$isEdit = ($writerDocumentId ?? 0) > 0;
$form = $writerForm ?? WriterDocument::empty();
$status = WriterDocument::normalizeStatus((string) ($form['status'] ?? WriterDocument::STATUS_DRAFT));
$isDraft = $status === WriterDocument::STATUS_DRAFT;
$readOnly = !($canEdit ?? false) || !$isDraft;
$paragraphs = WriterDocument::paragraphTexts($form);
$info = is_array($form['info'] ?? null) ? $form['info'] : WriterDocument::emptyInfo();
$infoFields = LetterheadSettings::infoFieldsSorted(null, true);
$recipientText = implode("\n", array_map('strval', is_array($form['recipient_lines'] ?? null) ? $form['recipient_lines'] : ['']));
$versions = $writerVersions ?? [];
$exportFormats = $writerExportFormats ?? ['pdf' => true, 'txt' => true, 'md' => false, 'docx' => false];
$previewUrl = $isEdit
    ? '/app?page=dokumentenverfasser-form&writer_preview=1&id=' . (int) $writerDocumentId
    : '/app?page=dokumentenverfasser-form&writer_preview=1&sample=1';
$disabled = $readOnly ? ' disabled' : '';
?>
<div class="dg-wrap">
  <?php
    View::partial('partials/back-nav', [
        'href' => '/app?page=dokumentenverfasser',
        'label' => 'Zurück zur Liste',
    ]);
  ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title"><?= $isEdit ? 'Dokument bearbeiten' : 'Neues Dokument' ?></h1>
      <p class="dg-lead">
        Briefpapier aus den Einstellungen · Status:
        <strong><?= View::escape(WriterDocument::statusOptions()[$status] ?? $status) ?></strong>
        <?php if ($isEdit) : ?> · Version v<?= (int) ($form['version'] ?? 1) ?><?php endif; ?>
      </p>
    </div>
    <div class="dg-toolbar">
      <a class="dg-button" href="<?= View::escape(SettingsRegistry::tabUrl('briefpapier')) ?>">Briefpapier</a>
      <?php if ($isEdit) : ?>
        <a class="dg-button" href="<?= View::escape($previewUrl) ?>" target="_blank" rel="noopener">Vorschau neuem Tab</a>
      <?php endif; ?>
    </div>
  </header>

  <?php View::render('partials/flash', compact('flash')); ?>
  <?php if (!empty($formError)) : ?>
    <div class="dg-flash dg-flash--error"><?= View::escape($formError) ?></div>
  <?php endif; ?>
  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Datenbank nicht verbunden — Speichern nicht möglich.</div>
  <?php endif; ?>
  <?php if ($isEdit && !$isDraft) : ?>
    <div class="dg-flash dg-flash--warning">
      Dieses Dokument ist <?= $status === 'archived' ? 'archiviert' : 'final' ?>.
      Inhalt ist schreibgeschützt (GoBD). Zum Ändern bitte erneut als Entwurf öffnen.
    </div>
  <?php endif; ?>

  <div class="dg-writer-layout">
    <div class="dg-writer-layout__form">
      <form method="post" action="/app?page=dokumentenverfasser-form" class="dg-form" id="dg-writer-form">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <?php if ($isEdit) : ?>
          <input type="hidden" name="id" value="<?= (int) $writerDocumentId ?>">
        <?php endif; ?>

        <fieldset class="dg-fieldset"<?= $disabled ?>>
          <legend>Kopf</legend>
          <label class="dg-field">
            <span>Listentitel (optional)</span>
            <input name="title" maxlength="191" value="<?= View::escape((string) ($form['title'] ?? '')) ?>" placeholder="Leer = Betreff">
          </label>
          <label class="dg-field">
            <span>Betreff</span>
            <input name="subject" maxlength="500" value="<?= View::escape((string) ($form['subject'] ?? '')) ?>" required spellcheck="true" lang="de">
          </label>
        </fieldset>

        <fieldset class="dg-fieldset"<?= $disabled ?>>
          <legend>Empfänger (Anschriftenfeld)</legend>
          <label class="dg-field">
            <span>Zeilen (eine pro Zeile)</span>
            <textarea name="recipient_lines" rows="5" spellcheck="true" lang="de"><?= View::escape($recipientText) ?></textarea>
          </label>
        </fieldset>

        <fieldset class="dg-fieldset"<?= $disabled ?>>
          <legend>Infoblock</legend>
          <div class="dg-form-grid">
            <?php foreach ($infoFields as $field) : ?>
              <?php $key = (string) ($field['key'] ?? ''); ?>
              <label class="dg-field">
                <span><?= View::escape((string) ($field['label'] ?? $key)) ?></span>
                <input name="info[<?= View::escape($key) ?>]" maxlength="200" value="<?= View::escape((string) ($info[$key] ?? '')) ?>">
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <fieldset class="dg-fieldset"<?= $disabled ?>>
          <legend>Anrede &amp; Gruß</legend>
          <div class="dg-form-grid">
            <label class="dg-field">
              <span>Anrede</span>
              <input name="greeting" id="dg-writer-greeting" maxlength="255" list="dg-writer-greeting-presets" value="<?= View::escape((string) ($form['greeting'] ?? '')) ?>" spellcheck="true" lang="de">
              <datalist id="dg-writer-greeting-presets">
                <?php foreach (WriterDocument::greetingPresets() as $text => $lbl) : ?>
                  <option value="<?= View::escape($text) ?>"><?= View::escape($lbl) ?></option>
                <?php endforeach; ?>
              </datalist>
            </label>
            <label class="dg-field">
              <span>Grußformel</span>
              <input name="closing" id="dg-writer-closing" maxlength="255" list="dg-writer-closing-presets" value="<?= View::escape((string) ($form['closing'] ?? '')) ?>" spellcheck="true" lang="de">
              <datalist id="dg-writer-closing-presets">
                <?php foreach (WriterDocument::closingPresets() as $text => $lbl) : ?>
                  <option value="<?= View::escape($text) ?>"><?= View::escape($lbl) ?></option>
                <?php endforeach; ?>
              </datalist>
            </label>
            <label class="dg-field">
              <span>Unterschrift / Name</span>
              <input name="signature" maxlength="255" value="<?= View::escape((string) ($form['signature'] ?? '')) ?>" placeholder="Leer = Firmenname">
            </label>
          </div>
        </fieldset>

        <fieldset class="dg-fieldset">
          <legend>Text (TipTap)</legend>
          <p class="dg-field-hint">Absätze mit Enter · Tabellen, Emoji, Lucide und Mediathek über die Leiste. Rechtschreibung: Browser (DE). Klartext/LanguageTool: Assist darunter (nie Pflicht).</p>
          <?php
            $tipTapDoc = WriterDocument::tipTapDocument($form);
            $tipTapJson = json_encode($tipTapDoc, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
            $bodyHtmlCache = (string) ($form['body_html_cache'] ?? '');
          ?>
          <input type="hidden" name="body_tiptap" id="dg-writer-body-tiptap" value="<?= View::escape((string) $tipTapJson) ?>">
          <input type="hidden" name="body_html_cache" id="dg-writer-body-html" value="<?= View::escape($bodyHtmlCache) ?>">
          <div id="dg-writer-paragraphs-sync" hidden>
            <?php foreach ($paragraphs as $text) : ?>
              <textarea name="paragraphs[]"><?= View::escape($text) ?></textarea>
            <?php endforeach; ?>
          </div>
          <?php if (!$readOnly) : ?>
            <div class="dg-writer-toolbar" role="toolbar" aria-label="Textformatierung">
              <button type="button" class="dg-button" id="dg-writer-cmd-bold" title="Fett">Fett</button>
              <button type="button" class="dg-button" id="dg-writer-cmd-italic" title="Kursiv">Kursiv</button>
              <button type="button" class="dg-button" id="dg-writer-cmd-paragraph" title="Absatz">Absatz</button>
              <button type="button" class="dg-button" id="dg-writer-cmd-bullet" title="Liste">• Liste</button>
              <button type="button" class="dg-button" id="dg-writer-cmd-ordered" title="Nummerierung">1. Liste</button>
              <button type="button" class="dg-button" id="dg-writer-cmd-table" title="Tabelle einfügen">Tabelle</button>
              <button type="button" class="dg-button" id="dg-writer-cmd-table-add-row" title="Zeile">+ Zeile</button>
              <button type="button" class="dg-button" id="dg-writer-cmd-table-add-col" title="Spalte">+ Spalte</button>
              <button type="button" class="dg-button" id="dg-writer-cmd-table-delete" title="Tabelle löschen">Tabelle weg</button>
              <button type="button" class="dg-button" id="dg-writer-cmd-emoji" title="Emoji">Emoji</button>
              <button type="button" class="dg-button" id="dg-writer-cmd-lucide" title="Lucide-Icon">Lucide</button>
              <button type="button" class="dg-button" id="dg-writer-cmd-media" title="Bild aus Mediathek">Mediathek</button>
            </div>
            <div id="dg-writer-emoji-panel" class="dg-writer-picker" hidden></div>
            <div id="dg-writer-lucide-panel" class="dg-writer-picker" hidden></div>
            <div id="dg-writer-media-panel" class="dg-writer-picker" hidden></div>
          <?php endif; ?>
          <div id="dg-writer-editor" class="dg-writer-editor<?= $readOnly ? ' dg-writer-editor--readonly' : '' ?>"></div>
          <?php
            $assistCfg = WriterAssistSettings::config();
            $assistKlartext = !empty($assistCfg['klartext_enabled']);
            $assistLt = WriterAssistSettings::languageToolReady($assistCfg);
          ?>
          <?php if (!$readOnly && ($assistKlartext || $assistLt)) : ?>
            <div class="dg-writer-assist" id="dg-writer-assist">
              <h3 class="dg-subsection-title">Text-Assist</h3>
              <p class="dg-field-hint">Hinweise sind optional. Speichern und Finalisieren gehen immer ohne Assist.</p>
              <p class="dg-form-actions">
                <?php if ($assistKlartext) : ?>
                  <button type="button" class="dg-button" id="dg-writer-assist-klartext">Klartext prüfen</button>
                <?php endif; ?>
                <?php if ($assistLt) : ?>
                  <button type="button" class="dg-button" id="dg-writer-assist-lt">Rechtschreibung (LanguageTool)</button>
                <?php endif; ?>
              </p>
              <div id="dg-writer-assist-result" class="dg-writer-assist__result" hidden></div>
            </div>
          <?php elseif (!$readOnly) : ?>
            <p class="dg-field-hint">Text-Assist ist aus. Admin: Einstellungen → Briefpapier → Text-Assist.</p>
          <?php endif; ?>
        </fieldset>

        <div class="dg-form-actions">
          <?php if ($canEdit && $dbConnected && $isDraft) : ?>
            <button type="submit" name="writer_save" value="1" class="dg-button dg-button--primary">Entwurf speichern</button>
            <?php if ($isEdit) : ?>
              <button type="submit" name="writer_finalize" value="1" class="dg-button" onclick="return confirm('Dokument finalisieren? Danach keine stillen Änderungen mehr.');">Finalisieren</button>
            <?php endif; ?>
          <?php endif; ?>
          <?php if ($canEdit && $dbConnected && $isEdit && !$isDraft) : ?>
            <button type="submit" name="writer_reopen" value="1" class="dg-button dg-button--primary" onclick="return confirm('Als neuen Entwurf öffnen? Die finale Fassung bleibt in der Versionshistorie.');">Als Entwurf öffnen</button>
          <?php endif; ?>
          <?php if ($canEdit && $dbConnected && $isEdit && $status !== WriterDocument::STATUS_ARCHIVED) : ?>
            <button type="submit" name="writer_archive" value="1" class="dg-button" onclick="return confirm('Dokument archivieren?');">Archivieren</button>
          <?php endif; ?>
        </div>

        <fieldset class="dg-fieldset">
          <legend>Export</legend>
          <p class="dg-field-hint">
            Formate aus Einstellungen → Briefpapier.
            <?php if (!$isEdit) : ?>
              Bitte zuerst speichern — Export braucht eine Dokument-ID.
            <?php else : ?>
              PDF öffnet die Druckvorlage (Browser → „Als PDF speichern“, wie Belege). TXT/MD/DOCX starten einen Download. Export wird protokolliert.
            <?php endif; ?>
          </p>
          <p class="dg-form-actions">
            <?php foreach (WriterExportService::formatLabels() as $key => $lbl) : ?>
              <?php if (empty($exportFormats[$key])) {
                  continue;
              } ?>
              <?php if ($isEdit) : ?>
                <a class="dg-button" href="/app?page=dokumentenverfasser-form&amp;writer_export=1&amp;id=<?= (int) $writerDocumentId ?>&amp;format=<?= View::escape($key) ?>"<?= $key === 'pdf' ? ' target="_blank" rel="noopener"' : '' ?>><?= View::escape($lbl) ?></a>
              <?php else : ?>
                <button type="button" class="dg-button" disabled title="Zuerst speichern"><?= View::escape($lbl) ?></button>
              <?php endif; ?>
            <?php endforeach; ?>
            <?php if (isset($user) && $user instanceof User && SettingsRegistry::canAccess($user)) : ?>
              <a class="dg-button" href="<?= View::escape(SettingsRegistry::tabUrl('briefpapier')) ?>">Formate einstellen</a>
            <?php endif; ?>
          </p>
        </fieldset>
      </form>

      <?php if ($isEdit && $versions !== []) : ?>
        <section class="dg-panel" style="margin-top:16px">
          <h2 class="dg-subsection-title">Versionshistorie</h2>
          <div class="dg-table-wrap">
            <table class="dg-table">
              <thead>
                <tr>
                  <th>Version</th>
                  <th>Status</th>
                  <th>Notiz</th>
                  <th>Wann</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($versions as $ver) : ?>
                  <tr>
                    <td>v<?= (int) ($ver['version'] ?? 0) ?></td>
                    <td><?= View::escape(WriterDocument::statusOptions()[WriterDocument::normalizeStatus((string) ($ver['status'] ?? ''))] ?? '') ?></td>
                    <td><?= View::escape((string) ($ver['note'] ?? '')) ?></td>
                    <td><?= View::escape((string) ($ver['created_at'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php endif; ?>
    </div>

    <aside class="dg-writer-layout__preview">
      <h2 class="dg-subsection-title">Briefpapier-Rahmen</h2>
      <p class="dg-field-hint">A4-Vorschau aus gespeichertem Stand<?= $isEdit ? '' : ' (Muster bis zum ersten Speichern)' ?>.</p>
      <div class="dg-letterhead-preview__frame-wrap dg-writer-preview-wrap">
        <iframe
          class="dg-letterhead-preview__frame dg-writer-preview-frame"
          name="dg-writer-preview-frame"
          title="Dokument-Vorschau A4"
          src="<?= View::escape($previewUrl) ?>"
        ></iframe>
      </div>
      <?php if ($isEdit && $isDraft && $canEdit) : ?>
        <p class="dg-field-hint" style="margin-top:8px">Nach „Entwurf speichern“ wird die Vorschau aktualisiert.</p>
      <?php endif; ?>
    </aside>
  </div>
</div>

<?php if (!$readOnly) : ?>
  <script type="application/json" id="dg-menu-icon-catalog"><?= json_encode([
      'featured' => WebsiteMenuIcons::pickerFeaturedIds(),
      'special' => [],
      'icons' => WebsiteMenuIcons::pickerCatalog(),
  ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?></script>
<?php endif; ?>
