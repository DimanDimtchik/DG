<?php
/** @var User $user */
/** @var array{items: list<Contact>, total: int, page: int, per_page: int, total_pages: int} $contactList */
/** @var string $contactSearch */
/** @var array{type: string, message: string}|null $flash */
/** @var list<string> $contactImportErrors */
$search = $contactSearch ?? '';
$list = $contactList ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 20, 'total_pages' => 1];
$importErrors = is_array($contactImportErrors ?? null) ? $contactImportErrors : [];
$baseUrl = '/app?page=kontakte';
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Kontakte</h1>
      <p class="dg-lead">Benutzer, Kunden, Lieferanten und Firmen – <?= (int) $list['total'] ?> Einträge</p>
    </div>
    <div class="dg-toolbar">
      <?php if (ContactExportService::isExportAllowed($user)) : ?>
        <form method="post" action="/app?page=kontakte" class="dg-toolbar__inline-form">
          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
          <input type="hidden" name="contact_org_export" value="1">
          <input type="hidden" name="s" value="<?= View::escape($search) ?>">
          <button type="submit" class="dg-button" title="JSON-Download für Import auf einer Org-Schwesterinstanz">
            Für Org-Schwester exportieren
          </button>
        </form>
      <?php endif; ?>
      <?php if (ContactAccessResolver::canEditContact($user)) : ?>
        <a class="dg-button dg-button--primary" href="/app?page=kontakte&amp;action=new">Neuer Kontakt</a>
      <?php endif; ?>
    </div>
  </header>

  <form class="dg-search" method="get" action="/app">
    <input type="hidden" name="page" value="kontakte">
    <label class="dg-search__label" for="kontakte-search">Suchen</label>
    <input
      class="dg-search__input"
      type="search"
      id="kontakte-search"
      name="s"
      value="<?= View::escape($search) ?>"
      placeholder="Name, Firma, E-Mail, Kunden-/Lieferantennummer, Bemerkung/IP …"
    >
    <button class="dg-button dg-button--primary" type="submit">Suchen</button>
    <?php if ($search !== '') : ?>
      <a class="dg-button" href="<?= View::escape($baseUrl) ?>">Zurücksetzen</a>
    <?php endif; ?>
  </form>

  <?php if (ContactFileImportService::isAllowed($user)) : ?>
    <?php
      $contactImportSources = InstallImportSourcePresets::all();
      $contactImportAreas = Database::isConfigured() ? CalendarStaffRepository::getAreas(true) : [];
      $tabularAccept = InstallImportSourcePresets::tabularAcceptAttribute();
    ?>
    <details class="dg-contact-import">
      <summary>Kontakte aus Datei importieren (Excel/CSV)</summary>
      <form method="post" action="/app?page=kontakte" enctype="multipart/form-data" class="dg-contact-import__form">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <input type="hidden" name="contact_file_import" value="1">
        <p class="dg-muted">
          Dieselbe Engine wie bei der Installation. Quellsystem wählen (z. B. ShiftBase), Datei hochladen.
          <a href="/app?page=kontakte&amp;action=import-csv-template">Beispiel-Vorlage herunterladen</a>
        </p>
        <label class="dg-contact-import__file">
          <span>Quellsystem</span>
          <select name="import_source" required>
            <?php foreach ($contactImportSources as $srcKey => $srcMeta) : ?>
              <option value="<?= View::escape($srcKey) ?>"<?= $srcKey === 'shiftbase' ? ' selected' : '' ?>>
                <?= View::escape((string) ($srcMeta['label'] ?? $srcKey)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dg-contact-import__file">
          <span>Datei</span>
          <input type="file" name="contact_import_file" accept="<?= View::escape($tabularAccept) ?>" required>
        </label>
        <label class="dg-contact-import__file">
          <span>Standard-Rolle (wenn Spalte Rolle leer)</span>
          <select name="default_role">
            <option value="mitarbeiter">Mitarbeiter</option>
            <option value="kunde">Kunde</option>
            <option value="lieferant">Lieferant</option>
          </select>
        </label>
        <label class="dg-contact-import__file">
          <span>Bei bestehendem Kontakt (E-Mail / Login / KdNr / LiefNr)</span>
          <select name="on_duplicate">
            <option value="skip">Überspringen (keine Doppelgänger)</option>
            <option value="update_empty" selected>Aktualisieren — nur leere Felder füllen</option>
            <option value="update_all">Aktualisieren — Felder überschreiben</option>
          </select>
        </label>
        <label class="dg-contact-import__check">
          <input type="checkbox" name="force_role" value="1" checked>
          <span>Rolle aus Auswahl für alle Zeilen erzwingen (Spalte Rolle ignorieren)</span>
        </label>
        <label class="dg-contact-import__check">
          <input type="checkbox" name="create_calendar" value="1">
          <span>Mitarbeiter zusätzlich als Kalender-Ressource anlegen</span>
        </label>
        <?php if ($contactImportAreas !== []) : ?>
          <label class="dg-contact-import__file">
            <span>Kalender-Bereich (bei Kalender-Anlage)</span>
            <select name="calendar_area_id">
              <?php foreach ($contactImportAreas as $area) : ?>
                <option value="<?= (int) $area['id'] ?>"><?= View::escape((string) $area['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        <?php endif; ?>
        <button type="submit" class="dg-button dg-button--primary">Datei importieren</button>
        <p class="dg-muted">Max. 5 MB. Abgleich über E-Mail, Login/Personalnummer, Kunden- oder Lieferantennummer. Ohne Treffer: Neu-Anlage.</p>
      </form>
    </details>
  <?php endif; ?>

  <?php if (ContactImportService::isImportAllowed($user)) : ?>
    <details class="dg-contact-import">
      <summary>Kontakte von Org-Schwester importieren (JSON)</summary>
      <form method="post" action="/app?page=kontakte" enctype="multipart/form-data" class="dg-contact-import__form">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <input type="hidden" name="contact_org_import" value="1">
        <label class="dg-contact-import__file">
          <span>Export-Datei</span>
          <input type="file" name="contact_export_file" accept="application/json,.json" required>
        </label>
        <label class="dg-contact-import__check">
          <input type="checkbox" name="overwrite_fields" value="1">
          <span>Bestehende Felder überschreiben (sonst nur leere Felder füllen)</span>
        </label>
        <label class="dg-contact-import__check">
          <input type="checkbox" name="confirm_warnings" value="1">
          <span>Warnungen bestätigen (Selbst-Import, Flag aus, Paket &gt; 90 Tage)</span>
        </label>
        <button type="submit" class="dg-button dg-button--primary">Import starten</button>
        <p class="dg-muted">Herkunft wird in <code>origin_firm_note</code> gesetzt, wenn dort noch nichts steht. Kein Live-Sync.</p>
      </form>
    </details>
  <?php endif; ?>

  <?php if ($importErrors !== []) : ?>
    <section class="dg-panel dg-panel--warning" style="margin-bottom:1rem">
      <h2 class="dg-subsection-title">Hinweise aus dem letzten Import</h2>
      <ul class="dg-list">
        <?php foreach ($importErrors as $err) : ?>
          <li><?= View::escape((string) $err) ?></li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <div class="dg-table-wrap">
    <table class="dg-table">
      <thead>
        <tr>
          <th>Benutzername</th>
          <th>Anzeigename</th>
          <th>Firmenname</th>
          <th>Kundennummer</th>
          <th>E-Mail</th>
          <th>Rolle</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($list['items'] === []) : ?>
          <tr>
            <td colspan="7" class="dg-table__empty">Keine Kontakte gefunden.</td>
          </tr>
        <?php else : ?>
          <?php foreach ($list['items'] as $contact) : ?>
            <tr>
              <td><strong><?= View::escape($contact->login) ?></strong></td>
              <td><?= View::escape($contact->listLabel()) ?></td>
              <td><?= View::escape($contact->companyName) ?></td>
              <td><?= View::escape($contact->customerNumber) ?></td>
              <td>
                <?php if ($contact->email !== '') : ?>
                  <a href="mailto:<?= View::escape($contact->email) ?>"><?= View::escape($contact->email) ?></a>
                <?php else : ?>
                  —
                <?php endif; ?>
              </td>
              <td><?= View::escape($contact->roleLabel()) ?></td>
              <td class="dg-table__actions">
                <?php if (ContactAccessResolver::canEditContact($user, $contact)) : ?>
                  <a href="/app?page=kontakte&amp;action=edit&amp;id=<?= $contact->id ?>">Bearbeiten</a>
                <?php else : ?>
                  <a href="/app?page=kontakte&amp;id=<?= $contact->id ?>">Anzeigen</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($list['total_pages'] > 1) : ?>
    <nav class="dg-pagination" aria-label="Seiten">
      <?php if ($list['page'] > 1) : ?>
        <a href="<?= View::escape($baseUrl . ($search !== '' ? '&s=' . rawurlencode($search) : '') . '&paged=' . ($list['page'] - 1)) ?>">&laquo; Zurück</a>
      <?php endif; ?>
      <span>Seite <?= (int) $list['page'] ?> von <?= (int) $list['total_pages'] ?></span>
      <?php if ($list['page'] < $list['total_pages']) : ?>
        <a href="<?= View::escape($baseUrl . ($search !== '' ? '&s=' . rawurlencode($search) : '') . '&paged=' . ($list['page'] + 1)) ?>">Weiter &raquo;</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
</div>
