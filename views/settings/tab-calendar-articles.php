<?php
/**
 * @var list<array<string, mixed>> $calendarArticles
 * @var list<array<string, mixed>> $calendarAreas
 * @var bool $dbConnected
 */
$staffMode = CalendarStaffRepository::hasActiveEmployees();
$presets = CalendarArticleRepository::WORK_MINUTE_PRESETS;
$taxTypes = CalendarArticleValidator::taxTypes();
$units = CalendarArticleValidator::units();
$catalogBaseUrl = $catalogBaseUrl ?? SettingsRegistry::tabUrl('leistungen');
$catalogFilter = $catalogFilter ?? 'all';
$catalogKinds = CalendarArticleCatalog::kinds();
$suggestedKind = $catalogFilter === CalendarArticleCatalog::KIND_PRODUCT
    ? CalendarArticleCatalog::KIND_PRODUCT
    : CalendarArticleCatalog::KIND_SERVICE;
$suggestedNumber = CalendarArticleRepository::suggestArticleNumber($suggestedKind);
$areaNames = [];
foreach ($calendarAreas as $area) {
    $areaNames[(int) $area['id']] = (string) $area['name'];
}
$importFormats = implode(', ', CalendarArticleImportReader::supportedExtensions());
?>
<div class="dg-form">
  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Zum Speichern ist eine funktionierende <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Datenbankverbindung</a> erforderlich.
    </div>
  <?php endif; ?>

  <p class="dg-lead">
    Artikel (Waren/Material) und Leistungen (buchbar im Kalender) mit Preis, Steuer und Dauer.
    Leistungen können einem <a href="<?= View::escape(SettingsRegistry::tabUrl('kalender-team')) ?>">Kalender-Bereich</a> zugeordnet werden.
    Nummern kommen aus den <a href="<?= View::escape(SettingsRegistry::tabUrl('nummernkreise')) ?>">Nummernkreisen</a> (Artikel / Leistung).
    Import: <?= View::escape($importFormats) ?>.
  </p>

  <nav class="dg-subtabs" aria-label="Katalogfilter">
    <?php foreach (['all' => 'Alle', 'service' => 'Leistungen', 'product' => 'Artikel'] as $kindKey => $kindLabel) : ?>
      <a
        href="<?= View::escape($catalogBaseUrl . ($kindKey !== 'all' ? '&kind=' . rawurlencode($kindKey) : '')) ?>"
        class="dg-subtabs__link<?= $catalogFilter === $kindKey ? ' is-active' : '' ?>"
        <?= $catalogFilter === $kindKey ? 'aria-current="page"' : '' ?>
      ><?= View::escape($kindLabel) ?></a>
    <?php endforeach; ?>
  </nav>

  <div class="dg-table-wrap">
    <table class="dg-table dg-table--compact">
      <thead>
        <tr>
          <th>Art</th>
          <th>Nr.</th>
          <th>Bezeichnung</th>
          <th>Einheit</th>
          <th>Steuer</th>
          <th>Preis (brutto)</th>
          <th>Dauer</th>
          <th>Bereich</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($calendarArticles === []) : ?>
          <tr><td colspan="9" class="dg-muted">Noch keine Einträge angelegt.</td></tr>
        <?php else : ?>
          <?php foreach ($calendarArticles as $article) : ?>
            <tr>
              <td><?= View::escape((string) ($article['kind_label'] ?? '')) ?></td>
              <td><?= View::escape((string) ($article['article_number'] ?? '')) ?></td>
              <td><?= View::escape((string) $article['title']) ?></td>
              <td><?= View::escape((string) ($article['unit'] ?? '')) ?></td>
              <td><?= View::escape((string) ($article['tax_label'] ?? '')) ?></td>
              <td><?= View::escape((string) ($article['price_label'] ?? '')) ?></td>
              <td><?= View::escape((string) ($article['duration_label'] ?? '')) ?></td>
              <td><?= View::escape($areaNames[(int) ($article['area_id'] ?? 0)] ?? '—') ?></td>
              <td class="dg-table__actions">
                <div class="dg-table__actions-group">
                <button
                  type="button"
                  class="dg-button dg-button--small dg-cal-edit-article"
                  data-article="<?= View::escape(json_encode([
                      'id' => (int) $article['id'],
                      'article_number' => (string) ($article['article_number'] ?? ''),
                      'catalog_kind' => (string) ($article['catalog_kind'] ?? CalendarArticleCatalog::KIND_SERVICE),
                      'gtin' => (string) ($article['gtin'] ?? ''),
                      'title' => (string) $article['title'],
                      'description' => (string) ($article['description'] ?? ''),
                      'note' => (string) ($article['note'] ?? ''),
                      'unit' => (string) ($article['unit'] ?? 'Stück'),
                      'tax_type' => (string) ($article['tax_type'] ?? 'ust19'),
                      'price_gross' => (float) ($article['price_gross'] ?? 0),
                      'work_minutes' => (int) $article['work_minutes'],
                      'area_id' => (int) $article['area_id'],
                      'sort_order' => (int) $article['sort_order'],
                      'is_active' => (int) $article['is_active'],
                  ], JSON_THROW_ON_ERROR)) ?>"
                >Bearbeiten</button>
                <form method="post" action="<?= View::escape($catalogBaseUrl) ?>" class="dg-inline-form">
                  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                  <input type="hidden" name="list_kind" value="<?= View::escape($catalogFilter) ?>">
                  <input type="hidden" name="article_id" value="<?= (int) $article['id'] ?>">
                  <button type="submit" name="articles_delete" value="1" class="dg-button dg-button--danger dg-button--small"<?= !$dbConnected ? ' disabled' : '' ?>>Löschen</button>
                </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <details class="dg-collapsible-form" id="dg-article-form-panel">
    <summary class="dg-subsection-title dg-collapsible-form__summary" id="dg-article-form-title">Neu anlegen</summary>
    <div class="dg-collapsible-form__body">
  <form class="dg-form" method="post" action="<?= View::escape($catalogBaseUrl) ?>" id="dg-calendar-article-form">
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
    <input type="hidden" name="list_kind" value="<?= View::escape($catalogFilter) ?>">
    <input type="hidden" name="article_id" id="dg_article_id" value="">

    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Art *</span>
        <select name="catalog_kind" id="dg_article_catalog_kind" required<?= !$dbConnected ? ' disabled' : '' ?>>
          <?php foreach ($catalogKinds as $kindKey => $kindLabel) : ?>
            <option value="<?= View::escape($kindKey) ?>"<?= $kindKey === $suggestedKind ? ' selected' : '' ?>><?= View::escape($kindLabel) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field">
        <span>Artikelnummer *</span>
        <input type="text" name="article_number" id="dg_article_number" value="<?= View::escape($suggestedNumber) ?>" required<?= !$dbConnected ? ' disabled' : '' ?>>
      </label>
      <label class="dg-field">
        <span>GTIN/EAN</span>
        <input type="text" name="gtin" id="dg_article_gtin" inputmode="numeric"<?= !$dbConnected ? ' disabled' : '' ?>>
      </label>
      <label class="dg-field dg-field--wide">
        <span>Bezeichnung *</span>
        <input type="text" name="title" id="dg_article_title" required<?= !$dbConnected ? ' disabled' : '' ?>>
      </label>
      <label class="dg-field">
        <span>Einheit *</span>
        <select name="unit" id="dg_article_unit" required<?= !$dbConnected ? ' disabled' : '' ?>>
          <?php foreach ($units as $unit) : ?>
            <option value="<?= View::escape($unit) ?>"<?= $unit === 'Stück' ? ' selected' : '' ?>><?= View::escape($unit) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field">
        <span>Steuer *</span>
        <select name="tax_type" id="dg_article_tax_type" required<?= !$dbConnected ? ' disabled' : '' ?>>
          <?php foreach ($taxTypes as $key => $label) : ?>
            <option value="<?= View::escape($key) ?>"<?= $key === 'ust19' ? ' selected' : '' ?>><?= View::escape($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field">
        <span>Preis (brutto) *</span>
        <input type="text" name="price_gross" id="dg_article_price" inputmode="decimal" placeholder="0,00" required<?= !$dbConnected ? ' disabled' : '' ?>>
      </label>
      <label class="dg-field">
        <span>Arbeitszeit *</span>
        <select name="work_minutes" id="dg_article_work_minutes" required<?= !$dbConnected ? ' disabled' : '' ?>>
          <?php foreach ($presets as $minutes) : ?>
            <option value="<?= (int) $minutes ?>"<?= $minutes === 30 ? ' selected' : '' ?>><?= View::escape(CalendarArticleRepository::formatDuration($minutes)) ?></option>
          <?php endforeach; ?>
          <option value="__custom__">— Eigene Dauer —</option>
        </select>
      </label>
      <label class="dg-field" id="dg_article_custom_minutes_wrap" hidden>
        <span>Eigene Dauer (Minuten)</span>
        <input type="number" name="custom_work_minutes" id="dg_article_custom_minutes" min="1" max="1440" step="1"<?= !$dbConnected ? ' disabled' : '' ?>>
      </label>
      <label class="dg-field">
        <span>Bereich<?= $staffMode ? ' *' : '' ?></span>
        <select name="area_id" id="dg_article_area"<?= $staffMode ? ' required' : '' ?><?= !$dbConnected ? ' disabled' : '' ?>>
          <?php if (!$staffMode) : ?>
            <option value="0">— Kein Bereich —</option>
          <?php else : ?>
            <option value="">— Bitte wählen —</option>
          <?php endif; ?>
          <?php foreach ($calendarAreas as $area) : ?>
            <?php if (empty($area['is_active'])) { continue; } ?>
            <option value="<?= (int) $area['id'] ?>"><?= View::escape((string) $area['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field">
        <span>Reihenfolge</span>
        <input type="number" name="sort_order" id="dg_article_sort" min="0" value="0"<?= !$dbConnected ? ' disabled' : '' ?>>
      </label>
      <label class="dg-field dg-field--wide">
        <span>Beschreibung</span>
        <textarea name="description" id="dg_article_description" rows="2"<?= !$dbConnected ? ' disabled' : '' ?>></textarea>
      </label>
      <label class="dg-field dg-field--wide">
        <span>Interne Notiz</span>
        <textarea name="note" id="dg_article_note" rows="2"<?= !$dbConnected ? ' disabled' : '' ?>></textarea>
      </label>
      <label class="dg-field">
        <span><input type="checkbox" name="is_active" id="dg_article_active" value="1" checked<?= !$dbConnected ? ' disabled' : '' ?>> Eintrag ist aktiv</span>
      </label>
    </div>

    <div class="dg-form-actions">
      <button type="submit" name="articles_save" value="1" class="dg-button dg-button--primary" id="dg-article-submit"<?= !$dbConnected ? ' disabled' : '' ?>>Speichern</button>
      <button type="button" class="dg-button" id="dg-article-cancel" hidden>Abbrechen</button>
    </div>
  </form>

  <h3 class="dg-subsection-title">Import aus Datei</h3>
  <form class="dg-form" method="post" action="<?= View::escape($catalogBaseUrl) ?>" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
    <input type="hidden" name="list_kind" value="<?= View::escape($catalogFilter) ?>">
    <p class="dg-field-hint">
      Leistungskatalog importieren: <?= View::escape($importFormats) ?>. Spalten werden flexibel erkannt
      (Artikelnummer, Bezeichnung, Einheit, Steuerart, VK brutto/netto, optional Arbeitszeit).
      Mit Artikelnummer: Aktualisierung bestehender Einträge. Ohne Nummer: Vergabe aus dem Nummernkreis (Artikel/Leistung).
      Bei PDF muss der Text tabellarisch lesbar sein — sonst bitte Excel oder CSV exportieren.
      <a href="/api/calendar-articles-template.csv">CSV-Vorlage</a> ·
      <a href="/api/calendar-articles-template.json">JSON-Vorlage</a>
    </p>
    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Datei *</span>
        <input type="file" name="import_file" accept=".csv,.txt,.xlsx,.xls,.xml,.json,.pdf,text/csv,application/json,application/xml,text/xml,application/pdf,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required<?= !$dbConnected ? ' disabled' : '' ?>>
      </label>
      <?php if ($staffMode && $calendarAreas !== []) : ?>
        <label class="dg-field">
          <span>Standard-Bereich (optional)</span>
          <select name="import_area_id"<?= !$dbConnected ? ' disabled' : '' ?>>
            <option value="0">— Kein Bereich —</option>
            <?php foreach ($calendarAreas as $area) : ?>
              <?php if (empty($area['is_active'])) { continue; } ?>
              <option value="<?= (int) $area['id'] ?>"><?= View::escape((string) $area['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <small class="dg-field-hint">Wird gesetzt, wenn in der Datei kein Bereich enthalten ist.</small>
        </label>
      <?php endif; ?>
    </div>
    <div class="dg-form-actions">
      <button type="submit" name="articles_import" value="1" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>>Import starten</button>
    </div>
  </form>
    </div>
  </details>
</div>
