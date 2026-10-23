<?php
/**
 * Dokumentenverfasser D2 — Neu/Bearbeiten + Briefpapier-Rahmen.
 *
 * @var array<string, mixed> $writerForm
 * @var int|null $writerDocumentId
 * @var list<array<string, mixed>> $writerVersions
 * @var array<string, bool> $writerExportFormats
 * @var array{contact: ?Contact, voucher: ?array<string, mixed>} $writerLinkContext
 * @var bool $writerCanLinkVoucher
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
$linkCtx = $writerLinkContext ?? ['contact' => null, 'voucher' => null];
$linkedContact = $linkCtx['contact'] ?? null;
$linkedVoucher = $linkCtx['voucher'] ?? null;
$canLinkVoucher = !empty($writerCanLinkVoucher);
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

        <fieldset class="dg-fieldset" id="dg-writer-recipient-fieldset"<?= $disabled ?>>
          <legend>Empfänger (Anschriftenfeld)</legend>
          <input type="hidden" name="contact_id" id="dg-writer-contact-id" value="<?= (int) ($form['contact_id'] ?? 0) ?>">
          <?php if (!$readOnly) : ?>
            <label class="dg-field dg-field--wide">
              <span>Kontakt suchen</span>
              <input
                type="search"
                id="dg-writer-contact-search"
                autocomplete="off"
                placeholder="Name, Firma, E-Mail, Kd.-Nr. …"
                value="<?= $linkedContact instanceof Contact ? View::escape($linkedContact->listLabel()) : '' ?>"
              >
              <span class="dg-field-hint">Auswahl füllt die Adresse und verknüpft den Kontakt. Manuelle Adresse bleibt möglich.</span>
              <div id="dg-writer-contact-results" class="dg-voucher-contact-results" hidden></div>
            </label>
            <p class="dg-form-actions" style="margin:0 0 8px" id="dg-writer-contact-actions"<?= ($linkedContact instanceof Contact || (int) ($form['contact_id'] ?? 0) > 0) ? '' : ' hidden' ?>>
              <?php if ($linkedContact instanceof Contact) : ?>
                <a class="dg-button dg-button--small" id="dg-writer-contact-open" href="/app?page=kontakte&amp;action=edit&amp;id=<?= (int) $linkedContact->id ?>">Kontakt öffnen</a>
              <?php else : ?>
                <a class="dg-button dg-button--small" id="dg-writer-contact-open" href="#" hidden>Kontakt öffnen</a>
              <?php endif; ?>
              <button type="button" class="dg-button dg-button--small" id="dg-writer-contact-clear">Kontakt lösen</button>
              <button type="button" class="dg-button dg-button--small" id="dg-writer-contact-refill">Adresse erneut übernehmen</button>
            </p>
          <?php elseif ($linkedContact instanceof Contact) : ?>
            <p class="dg-field-hint">
              Kontakt:
              <a href="/app?page=kontakte&amp;action=edit&amp;id=<?= (int) $linkedContact->id ?>"><?= View::escape($linkedContact->listLabel()) ?></a>
            </p>
          <?php endif; ?>
          <label class="dg-field">
            <span>Zeilen (eine pro Zeile)</span>
            <textarea name="recipient_lines" id="dg-writer-recipient-lines" rows="5" spellcheck="true" lang="de"<?= $disabled ?>><?= View::escape($recipientText) ?></textarea>
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
            <label class="dg-field dg-field--wide">
              <span>Anrede</span>
              <select class="dg-select" data-dg-phrase-fill="dg-writer-greeting"<?= $disabled ?>>
                <option value="">Vorlage übernehmen …</option>
                <?php foreach (WriterDocument::greetingPresets() as $text => $lbl) : ?>
                  <option value="<?= View::escape($text) ?>"><?= View::escape($lbl) ?></option>
                <?php endforeach; ?>
              </select>
              <input name="greeting" id="dg-writer-greeting" maxlength="255" value="<?= View::escape((string) ($form['greeting'] ?? '')) ?>" spellcheck="true" lang="de"<?= $disabled ?>>
            </label>
            <label class="dg-field dg-field--wide">
              <span>Grußformel</span>
              <select class="dg-select" data-dg-phrase-fill="dg-writer-closing"<?= $disabled ?>>
                <option value="">Vorlage übernehmen …</option>
                <?php foreach (WriterDocument::closingPresets() as $text => $lbl) : ?>
                  <option value="<?= View::escape($text) ?>"><?= View::escape($lbl) ?></option>
                <?php endforeach; ?>
              </select>
              <textarea name="closing" id="dg-writer-closing" rows="3" maxlength="255" spellcheck="true" lang="de"<?= $disabled ?>><?= View::escape((string) ($form['closing'] ?? '')) ?></textarea>
              <span class="dg-field-hint">Standard aus Briefpapier-Einstellungen · Mehrzeilig möglich</span>
            </label>
            <label class="dg-field">
              <span>Unterschrift / Name</span>
              <input name="signature" maxlength="255" value="<?= View::escape((string) ($form['signature'] ?? '')) ?>" placeholder="Leer = Firmenname">
            </label>
          </div>
        </fieldset>

        <fieldset class="dg-fieldset">
          <legend>Text (TipTap)</legend>
          <p class="dg-field-hint">Absätze mit Enter · Tabellen, Emoji, Icon und Mediathek über die Leiste. Rechtschreibung: Browser (DE). Klartext/LanguageTool: Assist darunter (nie Pflicht).</p>
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
              <button type="button" class="dg-button" id="dg-writer-cmd-lucide" title="Einfaches Symbol einfügen">Icon</button>
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

      <?php if ($isEdit && $canEdit) : ?>
        <section class="dg-panel dg-writer-crm-links" style="margin-top:16px">
          <h2 class="dg-subsection-title">CRM-Verknüpfung</h2>
          <p class="dg-field-hint">Kontakt und Beleg für die Nachvollziehbarkeit — Verknüpfungen werden auditiert (auch bei finalen Dokumenten).</p>

          <div class="dg-writer-crm-links__block">
            <h3 class="dg-subsection-title" style="font-size:1rem">Kontakt</h3>
            <?php if ($linkedContact instanceof Contact) : ?>
              <p>
                Verknüpft:
                <a href="/app?page=kontakte&amp;action=edit&amp;id=<?= (int) $linkedContact->id ?>"><?= View::escape($linkedContact->listLabel()) ?></a>
              </p>
              <form method="post" action="/app?page=dokumentenverfasser-form" class="dg-inline-form">
                <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                <input type="hidden" name="id" value="<?= (int) $writerDocumentId ?>">
                <button type="submit" name="writer_unlink_contact" value="1" class="dg-button">Verknüpfung lösen</button>
                <a class="dg-button" href="/app?page=dokumentenverfasser&amp;contact=<?= (int) $linkedContact->id ?>">Dokumente dieses Kontakts</a>
              </form>
            <?php else : ?>
              <p class="dg-field-hint">Kontakt bevorzugt oben unter Empfänger suchen. Alternativ per ID/E-Mail:</p>
              <form method="post" action="/app?page=dokumentenverfasser-form" class="dg-form">
                <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                <input type="hidden" name="id" value="<?= (int) $writerDocumentId ?>">
                <label class="dg-field dg-field--wide">
                  <span>Kontakt-ID oder E-Mail</span>
                  <input type="text" name="contact_ref" required placeholder="123 oder name@firma.de">
                </label>
                <p class="dg-field-hint">Ist das Anschriftenfeld leer, werden Name und Adresse des Kontakts übernommen (nur Entwurf).</p>
                <div class="dg-form-actions">
                  <button type="submit" name="writer_link_contact" value="1" class="dg-button dg-button--primary">Kontakt verknüpfen</button>
                </div>
              </form>
            <?php endif; ?>
          </div>

          <?php if ($canLinkVoucher) : ?>
            <div class="dg-writer-crm-links__block" style="margin-top:16px">
              <h3 class="dg-subsection-title" style="font-size:1rem">Beleg</h3>
              <?php if (is_array($linkedVoucher) && (int) ($linkedVoucher['id'] ?? 0) > 0) : ?>
                <p>
                  Verknüpft:
                  <a href="/app?page=buchhaltung-beleg-form&amp;action=edit&amp;id=<?= (int) $linkedVoucher['id'] ?>">
                    <?= View::escape((string) ($linkedVoucher['type_label'] ?? 'Beleg')) ?>
                    <?= View::escape(trim((string) ($linkedVoucher['invoice_number'] ?? '')) !== ''
                        ? (string) $linkedVoucher['invoice_number']
                        : '#' . (int) $linkedVoucher['id']) ?>
                  </a>
                </p>
                <form method="post" action="/app?page=dokumentenverfasser-form" class="dg-inline-form">
                  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                  <input type="hidden" name="id" value="<?= (int) $writerDocumentId ?>">
                  <button type="submit" name="writer_unlink_voucher" value="1" class="dg-button">Verknüpfung lösen</button>
                </form>
              <?php else : ?>
                <form method="post" action="/app?page=dokumentenverfasser-form" class="dg-form">
                  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                  <input type="hidden" name="id" value="<?= (int) $writerDocumentId ?>">
                  <label class="dg-field dg-field--wide">
                    <span>Beleg-ID oder Belegnummer</span>
                    <input type="text" name="voucher_ref" required placeholder="z. B. 42 oder RE-2026-001">
                  </label>
                  <div class="dg-form-actions">
                    <button type="submit" name="writer_link_voucher" value="1" class="dg-button">Beleg verknüpfen</button>
                  </div>
                </form>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </section>
      <?php elseif ($isEdit && !$canEdit) : ?>
        <?php if ($linkedContact instanceof Contact || (is_array($linkedVoucher) && (int) ($linkedVoucher['id'] ?? 0) > 0)) : ?>
          <section class="dg-panel" style="margin-top:16px">
            <h2 class="dg-subsection-title">CRM-Verknüpfung</h2>
            <?php if ($linkedContact instanceof Contact) : ?>
              <p>Kontakt: <a href="/app?page=kontakte&amp;action=edit&amp;id=<?= (int) $linkedContact->id ?>"><?= View::escape($linkedContact->listLabel()) ?></a></p>
            <?php endif; ?>
            <?php if (is_array($linkedVoucher) && (int) ($linkedVoucher['id'] ?? 0) > 0) : ?>
              <p>Beleg: <a href="/app?page=buchhaltung-beleg-form&amp;action=edit&amp;id=<?= (int) $linkedVoucher['id'] ?>"><?= View::escape((string) ($linkedVoucher['invoice_number'] ?? ('#' . $linkedVoucher['id']))) ?></a></p>
            <?php endif; ?>
          </section>
        <?php endif; ?>
      <?php endif; ?>

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
  <script>
  (function () {
    document.querySelectorAll('[data-dg-phrase-fill]').forEach(function (sel) {
      sel.addEventListener('change', function () {
        var id = sel.getAttribute('data-dg-phrase-fill');
        var target = id ? document.getElementById(id) : null;
        if (!target || !sel.value) return;
        target.value = sel.value;
        sel.selectedIndex = 0;
        target.dispatchEvent(new Event('input', { bubbles: true }));
      });
    });
  })();
  </script>
<?php endif; ?>
