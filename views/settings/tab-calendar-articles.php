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
$supplierContactOptions = $supplierContactOptions ?? [];
$stockLocationOptions = StockStructureRepository::locationOptions();
$catalogView = $catalogView ?? 'catalog'; // catalog | purchase | ordered | ignored
$purchaseListOpen = $purchaseListOpen ?? [];
$purchaseListOrdered = $purchaseListOrdered ?? [];
$purchaseListIgnored = $purchaseListIgnored ?? [];
$purchaseOrderArticleOptions = $purchaseOrderArticleOptions ?? [];
$openOrderUrl = $openOrderUrl ?? '';
$jsonEmbedFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;
$stockStructureJson = json_encode([
    'halls' => StockStructureRepository::allHalls(),
    'shelves' => StockStructureRepository::allShelves(),
    'places' => StockStructureRepository::placeOptions(),
], $jsonEmbedFlags);
$supplierOptionsJson = json_encode($supplierContactOptions, $jsonEmbedFlags);
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

  <nav class="dg-subtabs" aria-label="Artikel-Bereiche">
    <a
      href="<?= View::escape($catalogBaseUrl . ($catalogFilter !== 'all' ? '&kind=' . rawurlencode($catalogFilter) : '')) ?>"
      class="dg-subtabs__link<?= $catalogView === 'catalog' ? ' is-active' : '' ?>"
      <?= $catalogView === 'catalog' ? 'aria-current="page"' : '' ?>
    >Katalog</a>
    <a
      href="<?= View::escape($catalogBaseUrl . '&list=purchase') ?>"
      class="dg-subtabs__link<?= $catalogView === 'purchase' ? ' is-active' : '' ?>"
      <?= $catalogView === 'purchase' ? 'aria-current="page"' : '' ?>
    >Einkaufsliste</a>
    <a
      href="<?= View::escape($catalogBaseUrl . '&list=ordered') ?>"
      class="dg-subtabs__link<?= $catalogView === 'ordered' ? ' is-active' : '' ?>"
      <?= $catalogView === 'ordered' ? 'aria-current="page"' : '' ?>
    >Nachbestellt</a>
    <a
      href="<?= View::escape($catalogBaseUrl . '&list=ignored') ?>"
      class="dg-subtabs__link<?= $catalogView === 'ignored' ? ' is-active' : '' ?>"
      <?= $catalogView === 'ignored' ? 'aria-current="page"' : '' ?>
    >Ignoriert</a>
  </nav>

<?php if ($catalogView === 'purchase') : ?>
  <p class="dg-lead">Artikel mit Nachbestellbedarf (Ziel: Mindestmenge + 1, sonst 1 Stück im Lager). „Bestellen“ öffnet den Shop, wenn eine URL hinterlegt ist; sonst erscheint ein Hinweis mit Lieferant und Menge. Nachbestellte Mengen zählen unterwegs, bis „Erledigt“.</p>
  <?php if ($openOrderUrl !== '') : ?>
    <script>
      (function () {
        var u = <?= json_encode($openOrderUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        if (u) {
          window.open(u, '_blank', 'noopener');
        }
      })();
    </script>
  <?php endif; ?>
  <form method="post" action="<?= View::escape($catalogBaseUrl . '&list=purchase') ?>" class="dg-inline-form" style="margin-bottom:1rem">
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
    <button type="submit" name="purchase_list_rebuild" value="1" class="dg-button dg-button--small"<?= !$dbConnected ? ' disabled' : '' ?>>Liste aus Bestand aktualisieren</button>
  </form>
  <div class="dg-table-wrap">
    <table class="dg-table dg-table--compact">
      <thead>
        <tr>
          <th>Artikel</th>
          <th>Bestand / Verfügbar / Min</th>
          <th>Vorschlag</th>
          <th>Menge</th>
          <th>Grund</th>
          <th>Nachbestellen</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($purchaseListOpen === []) : ?>
          <tr><td colspan="7" class="dg-muted">Keine offenen Einträge.</td></tr>
        <?php else : ?>
          <?php foreach ($purchaseListOpen as $pli) : ?>
            <?php
              $ru = trim((string) ($pli['reorder_url'] ?? ''));
              $hint = (string) ($pli['manual_order_hint'] ?? '');
              $orderTitle = $ru !== ''
                ? ('Shop öffnen und als bestellt markieren' . (($pli['reorder_label'] ?? '') !== '' ? ': ' . $pli['reorder_label'] : ''))
                : $hint;
              $qtyDisplay = rtrim(rtrim(number_format((float) ($pli['suggested_qty'] ?? 0), 3, ',', '.'), '0'), ',');
            ?>
            <tr>
              <td>
                <?= View::escape((string) ($pli['article_number'] ?? '')) ?>
                — <?= View::escape((string) ($pli['article_title'] ?? '')) ?>
              </td>
              <td>
                <?= View::escape((string) ($pli['stock_label'] ?? '')) ?>
                / <?= View::escape((string) ($pli['available_label'] ?? '')) ?>
                / <?= View::escape((string) ($pli['min_label'] ?? '')) ?>
              </td>
              <td><?= View::escape((string) ($pli['suggested_label'] ?? '')) ?></td>
              <td>
                <form method="post" action="<?= View::escape($catalogBaseUrl . '&list=ordered') ?>" class="dg-inline-form"<?= $ru !== '' ? ' data-order-url="' . View::escape($ru) . '" onsubmit="var u=this.getAttribute(\'data-order-url\'); if(u){window.open(u,\'_blank\',\'noopener\');}"' : '' ?>>
                  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                  <input type="hidden" name="list" value="ordered">
                  <input type="hidden" name="purchase_list_id" value="<?= (int) ($pli['id'] ?? 0) ?>">
                  <label class="dg-field dg-field--compact">
                    <span class="dg-visually-hidden">Bestellmenge</span>
                    <input type="text" name="suggested_qty" inputmode="decimal" value="<?= View::escape($qtyDisplay) ?>" style="width:5.5rem" title="<?= View::escape($orderTitle) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
                  </label>
                  <button type="submit" name="purchase_list_ordered" value="1" class="dg-button dg-button--small" title="<?= View::escape($orderTitle) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>Bestellen</button>
                </form>
              </td>
              <td><?= View::escape((string) ($pli['reason_label'] ?? '')) ?></td>
              <td>
                <?php if ($ru !== '') : ?>
                  <a class="dg-button dg-button--small" href="<?= View::escape($ru) ?>" target="_blank" rel="noopener"><?= View::escape((string) ($pli['reorder_label'] !== '' ? $pli['reorder_label'] : 'Shop')) ?></a>
                <?php else : ?>
                  <span class="dg-muted" title="<?= View::escape($hint) ?>">—</span>
                <?php endif; ?>
              </td>
              <td class="dg-table__actions">
                <div class="dg-table__actions-group">
                  <form method="post" action="<?= View::escape($catalogBaseUrl . '&list=purchase') ?>" class="dg-inline-form">
                    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                    <input type="hidden" name="purchase_list_id" value="<?= (int) ($pli['id'] ?? 0) ?>">
                    <button type="submit" name="purchase_list_ignore" value="1" class="dg-button dg-button--small"<?= !$dbConnected ? ' disabled' : '' ?>>Ignorieren</button>
                  </form>
                  <form method="post" action="<?= View::escape($catalogBaseUrl . '&list=purchase') ?>" class="dg-inline-form">
                    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                    <input type="hidden" name="list" value="purchase">
                    <input type="hidden" name="purchase_list_id" value="<?= (int) ($pli['id'] ?? 0) ?>">
                    <button type="submit" name="purchase_list_done" value="1" class="dg-button dg-button--small" title="Ware eingetroffen — aus Nachbestellt entfernen"<?= !$dbConnected ? ' disabled' : '' ?>>Erledigt</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
<?php elseif ($catalogView === 'ordered') : ?>
  <p class="dg-lead">Bereits bestellt, Ware noch nicht eingetroffen — auch manuell erfassbar (z. B. Jahresvorrat / Sonderangebot), ohne dass der Artikel auf der Einkaufsliste stand. Zählt im Bestand als „Nachbestellt“, bis „Erledigt“.</p>
  <?php if ($openOrderUrl !== '') : ?>
    <script>
      (function () {
        var u = <?= json_encode($openOrderUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        if (u) {
          window.open(u, '_blank', 'noopener');
        }
      })();
    </script>
  <?php endif; ?>

  <section class="dg-panel" style="margin-bottom:1.25rem">
    <h3 class="dg-subsection-title">Nachbestellung manuell eintragen</h3>
    <form method="post" action="<?= View::escape($catalogBaseUrl . '&list=ordered') ?>" class="dg-form-grid dg-form-grid--compact">
      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
      <input type="hidden" name="list" value="ordered">
      <label class="dg-field dg-field--wide">
        <span>Artikel (mit Lagerführung)</span>
        <select name="manual_order_article_id" required<?= !$dbConnected || $purchaseOrderArticleOptions === [] ? ' disabled' : '' ?>>
          <option value="">— bitte wählen —</option>
          <?php foreach ($purchaseOrderArticleOptions as $opt) : ?>
            <option value="<?= (int) ($opt['id'] ?? 0) ?>"><?= View::escape((string) ($opt['label'] ?? '')) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field">
        <span>Bestellmenge</span>
        <input type="text" name="manual_order_qty" inputmode="decimal" required placeholder="z. B. 120"<?= !$dbConnected ? ' disabled' : '' ?>>
      </label>
      <label class="dg-field dg-field--wide">
        <span>Notiz (optional)</span>
        <input type="text" name="manual_order_note" maxlength="500" placeholder="z. B. Jahresvorrat Sonderangebot"<?= !$dbConnected ? ' disabled' : '' ?>>
      </label>
      <div class="dg-field dg-field--actions">
        <button type="submit" name="purchase_list_manual_order" value="1" class="dg-button dg-button--primary"<?= !$dbConnected || $purchaseOrderArticleOptions === [] ? ' disabled' : '' ?>>Als nachbestellt speichern</button>
      </div>
    </form>
    <?php if ($purchaseOrderArticleOptions === []) : ?>
      <p class="dg-muted">Keine Artikel mit Lagerführung — zuerst unter Katalog anlegen und „Lager führen“ aktivieren.</p>
    <?php endif; ?>
  </section>

  <div class="dg-table-wrap">
    <table class="dg-table dg-table--compact">
      <thead>
        <tr>
          <th>Artikel</th>
          <th>Bestand / Verfügbar / Min</th>
          <th>Nachbestellt</th>
          <th>Shop</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($purchaseListOrdered === []) : ?>
          <tr><td colspan="5" class="dg-muted">Keine nachbestellten Einträge.</td></tr>
        <?php else : ?>
          <?php foreach ($purchaseListOrdered as $pli) : ?>
            <?php
              $ru = trim((string) ($pli['reorder_url'] ?? ''));
              $qtyDisplay = rtrim(rtrim(number_format((float) ($pli['suggested_qty'] ?? 0), 3, ',', '.'), '0'), ',');
              $note = trim((string) ($pli['note'] ?? ''));
            ?>
            <tr>
              <td>
                <?= View::escape((string) ($pli['article_number'] ?? '')) ?>
                — <?= View::escape((string) ($pli['article_title'] ?? '')) ?>
                <?php if ($note !== '') : ?>
                  <br><small class="dg-muted"><?= View::escape($note) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <?= View::escape((string) ($pli['stock_label'] ?? '')) ?>
                / <?= View::escape((string) ($pli['available_label'] ?? '')) ?>
                / <?= View::escape((string) ($pli['min_label'] ?? '')) ?>
              </td>
              <td>
                <form method="post" action="<?= View::escape($catalogBaseUrl . '&list=ordered') ?>" class="dg-inline-form">
                  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                  <input type="hidden" name="list" value="ordered">
                  <input type="hidden" name="purchase_list_id" value="<?= (int) ($pli['id'] ?? 0) ?>">
                  <label class="dg-field dg-field--compact">
                    <span class="dg-visually-hidden">Nachbestellte Menge</span>
                    <input type="text" name="suggested_qty" inputmode="decimal" value="<?= View::escape($qtyDisplay) ?>" style="width:5.5rem"<?= !$dbConnected ? ' disabled' : '' ?>>
                    <span class="dg-muted"><?= View::escape((string) ($pli['unit'] ?? '')) ?></span>
                  </label>
                  <button type="submit" name="purchase_list_qty" value="1" class="dg-button dg-button--small"<?= !$dbConnected ? ' disabled' : '' ?>>Speichern</button>
                </form>
              </td>
              <td>
                <?php if ($ru !== '') : ?>
                  <a class="dg-button dg-button--small" href="<?= View::escape($ru) ?>" target="_blank" rel="noopener"><?= View::escape((string) ($pli['reorder_label'] !== '' ? $pli['reorder_label'] : 'Shop')) ?></a>
                <?php else : ?>
                  <span class="dg-muted">—</span>
                <?php endif; ?>
              </td>
              <td class="dg-table__actions">
                <form method="post" action="<?= View::escape($catalogBaseUrl . '&list=ordered') ?>" class="dg-inline-form">
                  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                  <input type="hidden" name="list" value="ordered">
                  <input type="hidden" name="purchase_list_id" value="<?= (int) ($pli['id'] ?? 0) ?>">
                  <button type="submit" name="purchase_list_done" value="1" class="dg-button dg-button--small" title="Ware eingetroffen"<?= !$dbConnected ? ' disabled' : '' ?>>Erledigt</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
<?php elseif ($catalogView === 'ignored') : ?>
  <p class="dg-lead">Ignorierte Artikel erscheinen nicht wieder auf der Einkaufsliste, bis sie reaktiviert werden.</p>
  <div class="dg-table-wrap">
    <table class="dg-table dg-table--compact">
      <thead>
        <tr>
          <th>Artikel</th>
          <th>Bestand / Verfügbar / Min</th>
          <th>Grund</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($purchaseListIgnored === []) : ?>
          <tr><td colspan="4" class="dg-muted">Keine ignorierten Einträge.</td></tr>
        <?php else : ?>
          <?php foreach ($purchaseListIgnored as $pli) : ?>
            <tr>
              <td>
                <?= View::escape((string) ($pli['article_number'] ?? '')) ?>
                — <?= View::escape((string) ($pli['article_title'] ?? '')) ?>
              </td>
              <td>
                <?= View::escape((string) ($pli['stock_label'] ?? '')) ?>
                / <?= View::escape((string) ($pli['available_label'] ?? '')) ?>
                / <?= View::escape((string) ($pli['min_label'] ?? '')) ?>
              </td>
              <td><?= View::escape((string) ($pli['reason_label'] ?? '')) ?></td>
              <td class="dg-table__actions">
                <form method="post" action="<?= View::escape($catalogBaseUrl . '&list=ignored') ?>" class="dg-inline-form">
                  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                  <input type="hidden" name="purchase_list_id" value="<?= (int) ($pli['id'] ?? 0) ?>">
                  <button type="submit" name="purchase_list_restore" value="1" class="dg-button dg-button--small"<?= !$dbConnected ? ' disabled' : '' ?>>Wieder aktivieren</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
<?php else : ?>

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
          <th>Bestand</th>
          <th>Positionscode</th>
          <th>Bereich</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($calendarArticles === []) : ?>
          <tr><td colspan="11" class="dg-muted">Noch keine Einträge angelegt.</td></tr>
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
              <td><?php if (!empty($article['track_stock'])) : ?>
                <?= View::escape((string) ($article['stock_label'] ?? '')) ?>
                <?php if ((float) ($article['reserved_qty'] ?? 0) > 0 || (float) ($article['in_transit_qty'] ?? 0) > 0 || (float) ($article['on_order_qty'] ?? 0) > 0) : ?>
                  <br><small class="dg-muted">Res. <?= View::escape((string) ($article['reserved_label'] ?? '0')) ?>
                  · Auslief. <?= View::escape((string) ($article['in_transit_label'] ?? '0')) ?>
                  · Nachbest. <?= View::escape((string) ($article['on_order_label'] ?? '0')) ?>
                  · Verf. <?= View::escape((string) ($article['available_label'] ?? '')) ?></small>
                <?php endif; ?>
                <?php if (!empty($article['is_low_stock'])) : ?> <span class="dg-badge dg-badge--warning">Min.</span><?php endif; ?>
                <?php
                  $reorderUrl = trim((string) ($article['reorder_url'] ?? ''));
                  $reorderLabel = trim((string) ($article['reorder_label'] ?? ''));
                  $hasSource = !empty($article['has_purchase_source']);
                  $onOrderQty = (float) ($article['on_order_qty'] ?? 0);
                  $isLowStock = !empty($article['is_low_stock']);
                ?>
                <?php if ($onOrderQty > 0.0005) : ?>
                  <br><a class="dg-button dg-button--small dg-button--secondary" href="<?= View::escape($catalogBaseUrl . '&list=ordered') ?>" title="Unterwegs — Menge unter Nachbestellt anpassen">Nachbestellt</a>
                <?php elseif ($isLowStock && $reorderUrl !== '') : ?>
                  <br><a class="dg-button dg-button--small" href="<?= View::escape($reorderUrl) ?>" target="_blank" rel="noopener" title="<?= View::escape($reorderLabel !== '' ? $reorderLabel : 'Shop') ?>">Nachbestellen</a>
                <?php elseif ($isLowStock && $hasSource) : ?>
                  <br><small class="dg-muted" title="Shop-URL optional — Chef recherchiert und bestellt manuell">Nachbestellen<?= $reorderLabel !== '' ? ': ' . View::escape($reorderLabel) : '' ?></small>
                <?php elseif ($isLowStock) : ?>
                  <br><small class="dg-muted">Nachbestellen (manuell)</small>
                <?php endif; ?>
              <?php else : ?>—<?php endif; ?></td>
              <td><?= !empty($article['stock_position_code']) ? View::escape((string) $article['stock_position_code']) : '—' ?></td>
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
                      'track_stock' => (int) ($article['track_stock'] ?? 0),
                      'min_stock' => (float) ($article['min_stock'] ?? 0),
                      'stock_location_id' => (int) ($article['stock_location_id'] ?? 0),
                      'stock_hall_id' => (int) ($article['stock_hall_id'] ?? 0),
                      'stock_shelf_id' => (int) ($article['stock_shelf_id'] ?? 0),
                      'stock_place_id' => (int) ($article['stock_place_id'] ?? 0),
                      'stock_place_mode' => (string) ($article['stock_place_mode'] ?? 'flexible'),
                      'stock_ort' => (string) ($article['stock_ort'] ?? ''),
                      'stock_halle' => (string) ($article['stock_halle'] ?? ''),
                      'stock_regal' => (string) ($article['stock_regal'] ?? ''),
                      'stock_platz' => (string) ($article['stock_platz'] ?? ''),
                      'purchase_sources' => $article['purchase_sources'] ?? [],
                      'reorder_url' => (string) ($article['reorder_url'] ?? ''),
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
        <span>EAN / Strichcode (GTIN)</span>
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
      <div class="dg-field dg-field--wide" id="dg_article_stock_fields" hidden>
        <fieldset class="dg-fieldset">
          <legend>Lager (nur Artikel)</legend>
          <label class="dg-field">
            <span><input type="checkbox" name="track_stock" id="dg_article_track_stock" value="1"<?= !$dbConnected ? ' disabled' : '' ?>> Lager führen</span>
          </label>
          <label class="dg-field" id="dg_article_initial_stock_wrap">
            <span>Anfangsbestand (nur neu)</span>
            <input type="text" name="initial_stock" id="dg_article_initial_stock" inputmode="decimal" placeholder="0"<?= !$dbConnected ? ' disabled' : '' ?>>
          </label>
          <label class="dg-field">
            <span>Mindestbestand</span>
            <input type="text" name="min_stock" id="dg_article_min_stock" inputmode="decimal" placeholder="0"<?= !$dbConnected ? ' disabled' : '' ?>>
          </label>
          <p class="dg-field-hint dg-field--wide" id="dg_article_stock_availability" hidden>
            Bestand / Reserviert / In Auslieferung / Nachbestellt / Verfügbar erscheinen nach dem Speichern in der Liste.
            Angebot &amp; AB reservieren ab Status <strong>Versendet</strong> oder <strong>Angenommen</strong> (ohne Bestand abzubuchen).
            „Bestellen“ auf der Einkaufsliste markiert Nachbestellt (unterwegs), bis die Ware mit „Erledigt“ bzw. Wareneingang ankommt.
          </p>
          <p class="dg-field-hint dg-field--wide">Stammdaten unter <a href="<?= View::escape(SettingsRegistry::tabUrl('lager-struktur')) ?>">Einstellungen → Lagerstruktur</a>.</p>
          <label class="dg-field">
            <span>Lagerort</span>
            <select name="stock_location_id" id="dg_article_stock_location"<?= !$dbConnected ? ' disabled' : '' ?>>
              <option value="">— optional —</option>
              <?php foreach ($stockLocationOptions as $opt) : ?>
                <option value="<?= (int) $opt['id'] ?>"><?= View::escape($opt['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="dg-field">
            <span>Halle</span>
            <select name="stock_hall_id" id="dg_article_stock_hall"<?= !$dbConnected ? ' disabled' : '' ?>>
              <option value="">— optional —</option>
            </select>
          </label>
          <label class="dg-field">
            <span>Regal / Stellplätze</span>
            <select name="stock_shelf_id" id="dg_article_stock_shelf"<?= !$dbConnected ? ' disabled' : '' ?>>
              <option value="">— optional —</option>
            </select>
          </label>
          <label class="dg-field">
            <span>Platz</span>
            <select name="stock_place_id" id="dg_article_stock_place"<?= !$dbConnected ? ' disabled' : '' ?>>
              <option value="">— optional —</option>
            </select>
          </label>
          <label class="dg-field">
            <span>Platz-Modus</span>
            <select name="stock_place_mode" id="dg_article_stock_place_mode"<?= !$dbConnected ? ' disabled' : '' ?>>
              <option value="flexible">flexibel (Platz bei Einnahme)</option>
              <option value="fixed">fest (Ein-/Ausgang nur hier)</option>
            </select>
          </label>
          <p class="dg-field dg-field--wide dg-field-hint" id="dg_article_stock_position_preview" hidden>
            Positionscode: <strong id="dg_article_stock_position_code">—</strong>
          </p>
          <p class="dg-field-hint">Positionscode = Ort-Halle-Regal-Platz. Feste Plätze erfordern einen konkreten Stellplatz. Bestandsänderungen aus Belegen unter <a href="/app?page=lager">Lager</a>.</p>
          <script type="application/json" id="dg-stock-structure-data"><?= $stockStructureJson ?></script>
        </fieldset>
      </div>

      <div class="dg-field dg-field--wide" id="dg_article_purchase_fields" hidden>
        <fieldset class="dg-fieldset">
          <legend>Einkaufsquellen (Lieferant / Shop)</legend>
          <p class="dg-field-hint">Mehrere Quellen möglich. Unter <strong>Firma / Lieferant</strong> erscheinen Kontakte mit Anrede „Firma“ bzw. Firmennamen (CRM-Rolle ist meist „Kunde“). Shop-URL ist optional — Chef kann recherchieren und manuell bestellen.</p>
          <div id="dg-purchase-sources-list" class="dg-purchase-sources"></div>
          <button type="button" class="dg-button dg-button--small" id="dg-purchase-source-add"<?= !$dbConnected ? ' disabled' : '' ?>>+ Einkaufsquelle</button>
          <script type="application/json" id="dg-supplier-options"><?= $supplierOptionsJson ?></script>
        </fieldset>
      </div>
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
<?php endif; ?>
</div>
