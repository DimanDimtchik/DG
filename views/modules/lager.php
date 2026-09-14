<?php
/**
 * @var list<array<string, mixed>> $stockItems
 * @var list<array<string, mixed>> $stockMovements
 * @var list<array<string, mixed>> $stockInventories
 * @var array<string, mixed>|null $activeInventory
 * @var list<array<string, mixed>> $activeInventoryLines
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var string $lagerView
 * @var list<array<string, mixed>> $stockOutboundVouchers
 * @var list<array<string, mixed>> $stockPlaces
 * @var list<array{id: int, label: string, code: string}> $stockLocationOptions
 * @var list<array<string, mixed>> $stockHalls
 * @var list<array<string, mixed>> $stockShelves
 * @var array{type: string, message: string}|null $flash
 */
$stockItems = $stockItems ?? [];
$stockMovements = $stockMovements ?? [];
$stockInventories = $stockInventories ?? [];
$stockOutboundVouchers = $stockOutboundVouchers ?? [];
$stockPlaces = $stockPlaces ?? [];
$stockLocationOptions = $stockLocationOptions ?? StockStructureRepository::locationOptions();
$stockHalls = $stockHalls ?? [];
$stockShelves = $stockShelves ?? [];
$activeInventory = $activeInventory ?? null;
$activeInventoryLines = $activeInventoryLines ?? [];
$lagerView = $lagerView ?? 'overview';
$csrf = Csrf::token();
$fmtQty = static fn (float $v): string => rtrim(rtrim(number_format($v, 3, ',', '.'), '0'), ',');
?>
<div class="dg-wrap dg-lager">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Lager</h1>
      <p class="dg-lead">Bestände, Bewegungen und Inventur — Artikel mit aktivierter Lagerführung und Positionscode aus der Lagerstruktur.</p>
    </div>
    <div class="dg-page-header__actions">
      <a class="dg-button" href="<?= View::escape(SettingsRegistry::tabUrl('lager-struktur')) ?>">Lagerstruktur</a>
      <a class="dg-button" href="/app?page=lager&amp;download=csv">Bestand CSV</a>
      <?php if ($activeInventory !== null) : ?>
        <a class="dg-button" href="/app?page=lager&amp;view=inventur&amp;download=inventory&amp;id=<?= (int) ($activeInventory['id'] ?? 0) ?>">Inventur CSV</a>
      <?php endif; ?>
    </div>
  </header>

  <nav class="dg-subtabs" aria-label="Lager-Bereiche">
    <a href="/app?page=lager&amp;view=overview" class="dg-subtabs__link<?= $lagerView === 'overview' ? ' is-active' : '' ?>">Bestandsübersicht</a>
    <a href="/app?page=lager&amp;view=wareneingang" class="dg-subtabs__link<?= $lagerView === 'wareneingang' ? ' is-active' : '' ?>">Wareneingang</a>
    <a href="/app?page=lager&amp;view=warenausgang" class="dg-subtabs__link<?= $lagerView === 'warenausgang' ? ' is-active' : '' ?>">Warenausgang</a>
    <a href="/app?page=lager&amp;view=platz-check" class="dg-subtabs__link<?= $lagerView === 'platz-check' ? ' is-active' : '' ?>">Platz-Check</a>
    <a href="/app?page=lager&amp;view=bewegungen" class="dg-subtabs__link<?= $lagerView === 'bewegungen' ? ' is-active' : '' ?>">Bewegungen</a>
    <a href="/app?page=lager&amp;view=inventur" class="dg-subtabs__link<?= $lagerView === 'inventur' ? ' is-active' : '' ?>">Inventur</a>
  </nav>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Datenbank nicht verbunden.</div>
  <?php else : ?>

  <?php if ($lagerView === 'overview') : ?>
  <section class="dg-panel">
    <h2 class="dg-subsection-title">Artikel mit Lagerführung</h2>
    <?php if ($stockItems === []) : ?>
      <p class="dg-muted">Noch keine Artikel mit Lagerführung.</p>
      <p><a class="dg-button dg-button--primary" href="/app?page=artikel-leistungen&amp;kind=product&amp;focus=stock">Artikel anlegen — Lager führen</a></p>
    <?php else : ?>
      <div class="dg-table-wrap">
        <table class="dg-table dg-table--compact">
          <thead>
            <tr>
              <th>Nr.</th>
              <th>Bezeichnung</th>
              <th>Positionscode</th>
              <th>Bestand</th>
              <th>Mindest</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($stockItems as $item) : ?>
              <tr<?= !empty($item['is_low']) ? ' class="dg-row--warning"' : '' ?>>
                <td><?= View::escape((string) ($item['article_number'] ?? '')) ?></td>
                <td><?= View::escape((string) ($item['title'] ?? '')) ?></td>
                <td><?= ($item['stock_position_code'] ?? '') !== '' ? View::escape((string) $item['stock_position_code']) : '—' ?></td>
                <td><?= View::escape((string) ($item['stock_label'] ?? '')) ?></td>
                <td><?= (float) ($item['min_stock'] ?? 0) > 0 ? View::escape($fmtQty((float) $item['min_stock']) . ' ' . ($item['unit'] ?? '')) : '—' ?></td>
                <td class="dg-table__actions">
                  <?php if ($canEdit) : ?>
                    <button type="button" class="dg-button dg-button--small dg-stock-adjust-btn"
                      data-article-id="<?= (int) ($item['id'] ?? 0) ?>"
                      data-article-title="<?= View::escape((string) ($item['title'] ?? '')) ?>">Korrigieren</button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <?php if ($canEdit) : ?>
  <section class="dg-panel" id="dg-stock-adjust-panel" hidden>
    <h2 class="dg-subsection-title">Manuelle Korrektur</h2>
    <form method="post" action="/app?page=lager" class="dg-form-grid">
      <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
      <input type="hidden" name="stock_adjust" value="1">
      <input type="hidden" name="article_id" id="dg-stock-adjust-article-id" value="">
      <label class="dg-field dg-field--wide">
        <span>Artikel</span>
        <input type="text" id="dg-stock-adjust-article-label" readonly>
      </label>
      <label class="dg-field">
        <span>Menge (+ Zugang / − Abgang) *</span>
        <input type="text" name="quantity_delta" inputmode="decimal" placeholder="z. B. -2 oder 5,5" required>
        <small class="dg-field-hint">Negative Zahl = Abgang, positive = Zugang.</small>
      </label>
      <label class="dg-field dg-field--wide">
        <span>Grund *</span>
        <input type="text" name="adjust_note" maxlength="500" placeholder="z. B. Schwund, Retoure ohne Beleg" required>
      </label>
      <div class="dg-field dg-field--actions">
        <button type="submit" class="dg-button dg-button--primary">Buchen</button>
      </div>
    </form>
  </section>
  <?php endif; ?>

  <?php elseif ($lagerView === 'wareneingang') : ?>
  <section class="dg-panel">
    <h2 class="dg-subsection-title">Wareneingang</h2>
    <p class="dg-field-hint">Strichcode scannen: Palette/Platz, Karton oder Artikel (EAN/GTIN). Optional Karton mit eigenem Code anlegen.</p>
    <?php if (!$canEdit) : ?>
      <p class="dg-muted">Keine Berechtigung zum Buchen.</p>
    <?php else : ?>
      <form class="dg-form-grid dg-form-grid--compact" data-lager-scan="receipt" autocomplete="off">
        <label class="dg-field dg-field--wide">
          <span>Strichcode scannen</span>
          <input type="text" data-scan-input inputmode="numeric" autofocus placeholder="Scanner oder Eingabe + Enter">
        </label>
        <div class="dg-field dg-field--wide">
          <div data-scan-message class="dg-scan-result" hidden></div>
        </div>
      </form>

      <form method="post" action="/app?page=lager&amp;view=wareneingang" class="dg-form" id="dg-receipt-form">
        <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
        <input type="hidden" name="view" value="wareneingang">
        <input type="hidden" name="stock_receipt" value="1">
        <div class="dg-table-wrap" id="dg-receipt-lines">
          <table class="dg-table dg-table--compact">
            <thead>
              <tr><th>Artikel</th><th>Menge</th><th>Platz</th><th>Karton</th><th>Karton-Code</th><th></th></tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
        <label class="dg-field dg-field--wide">
          <span>Notiz</span>
          <input type="text" name="note" maxlength="500" placeholder="Optional">
        </label>
        <div class="dg-form-actions">
          <button type="submit" class="dg-button dg-button--primary">Wareneingang buchen</button>
        </div>
      </form>
    <?php endif; ?>
  </section>

  <?php elseif ($lagerView === 'warenausgang') : ?>
  <section class="dg-panel">
    <h2 class="dg-subsection-title">Warenausgang</h2>
    <p class="dg-field-hint">Verknüpfung mit Lieferschein oder Auftrag — oder manuell per Strichcode (Artikel/Karton).</p>
    <?php if (!$canEdit) : ?>
      <p class="dg-muted">Keine Berechtigung zum Buchen.</p>
    <?php else : ?>
      <form class="dg-form-grid dg-form-grid--compact" data-lager-scan="issue" autocomplete="off">
        <label class="dg-field dg-field--wide">
          <span>Strichcode scannen</span>
          <input type="text" data-scan-input inputmode="numeric" placeholder="Artikel, Karton oder Palette">
        </label>
        <div class="dg-field dg-field--wide">
          <div data-scan-message class="dg-scan-result" hidden></div>
        </div>
      </form>

      <form method="post" action="/app?page=lager&amp;view=warenausgang" class="dg-form">
        <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
        <input type="hidden" name="view" value="warenausgang">
        <input type="hidden" name="stock_issue" value="1">
        <div class="dg-form-grid dg-form-grid--compact">
          <label class="dg-field dg-field--wide">
            <span>Beleg (Lieferschein / Auftrag)</span>
            <select name="voucher_id" id="dg-issue-voucher">
              <option value="">— manuell / Scan —</option>
              <?php foreach ($stockOutboundVouchers as $voucher) : ?>
                <option value="<?= (int) ($voucher['id'] ?? 0) ?>"><?= View::escape((string) ($voucher['label'] ?? '')) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="dg-field dg-field--wide">
            <span>Notiz</span>
            <input type="text" name="note" maxlength="500" placeholder="Optional">
          </label>
        </div>
        <div class="dg-table-wrap" id="dg-issue-lines">
          <table class="dg-table dg-table--compact">
            <thead>
              <tr><th>Artikel</th><th>Menge</th><th>Karton-Scan</th><th></th></tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
        <div class="dg-form-actions">
          <button type="submit" class="dg-button dg-button--primary">Warenausgang buchen</button>
        </div>
      </form>
    <?php endif; ?>
  </section>

  <?php elseif ($lagerView === 'platz-check') : ?>
  <section class="dg-panel">
    <h2 class="dg-subsection-title">Platz-Check (Mini-Audit)</h2>
    <p class="dg-field-hint">Strichcode scannen <strong>oder</strong> Lagerstruktur manuell wählen — Anzeige von Belegung, Reservierung (fest/flexibel) und letzten Bewegungen. Scan per Handscanner, Tastatureingabe oder <strong>Kamera</strong> (Tablet/Smartphone, HTTPS).</p>
    <form class="dg-form-grid dg-form-grid--compact" id="dg-place-audit-form" autocomplete="off">
      <label class="dg-field dg-field--wide">
        <span>Strichcode scannen</span>
        <input type="text" data-scan-input inputmode="numeric" autofocus placeholder="Platz, Regal, Halle, Karton oder Artikel">
        <button type="button" class="dg-button dg-button--small dg-camera-scan-btn" data-camera-scan-trigger>Kamera</button>
      </label>
    </form>

    <form class="dg-form-grid dg-form-grid--compact dg-audit-manual" id="dg-place-audit-manual-form" autocomplete="off">
      <h3 class="dg-subsection-title dg-field--wide">Manuell auswählen</h3>
      <label class="dg-field">
        <span>Ebene</span>
        <select id="dg-audit-level" data-audit-level>
          <?php foreach (StockLabelService::levelOptions() as $levelKey => $levelLabel) : ?>
            <option value="<?= View::escape($levelKey) ?>"<?= $levelKey === StockLabelService::LEVEL_PLACE ? ' selected' : '' ?>><?= View::escape($levelLabel) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field" data-audit-field="location">
        <span>Lagerort</span>
        <select id="dg-audit-location" data-audit-location>
          <option value="">— wählen —</option>
          <?php foreach ($stockLocationOptions as $opt) : ?>
            <option value="<?= (int) $opt['id'] ?>"><?= View::escape($opt['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field" data-audit-field="hall">
        <span>Halle</span>
        <select id="dg-audit-hall" data-audit-hall>
          <option value="">— wählen —</option>
          <?php foreach ($stockHalls as $hall) : ?>
            <option value="<?= (int) $hall['id'] ?>" data-location-id="<?= (int) ($hall['location_id'] ?? 0) ?>"><?= View::escape((string) ($hall['location_code'] ?? '') . ' / ' . (string) $hall['code']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field" data-audit-field="shelf">
        <span>Regal</span>
        <select id="dg-audit-shelf" data-audit-shelf>
          <option value="">— wählen —</option>
          <?php foreach ($stockShelves as $shelf) : ?>
            <option value="<?= (int) $shelf['id'] ?>" data-hall-id="<?= (int) ($shelf['hall_id'] ?? 0) ?>" data-location-id="<?= (int) ($shelf['location_id'] ?? 0) ?>"><?= View::escape((string) ($shelf['position_prefix'] ?? $shelf['code'])) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field" data-audit-field="place">
        <span>Stellplatz</span>
        <select id="dg-audit-place" data-audit-place>
          <option value="">— wählen —</option>
          <?php foreach ($stockPlaces as $place) : ?>
            <option value="<?= (int) $place['id'] ?>" data-shelf-id="<?= (int) ($place['shelf_id'] ?? 0) ?>" data-hall-id="<?= (int) ($place['hall_id'] ?? 0) ?>" data-location-id="<?= (int) ($place['location_id'] ?? 0) ?>"><?= View::escape((string) ($place['position_code'] ?? '')) ?> · <?= View::escape((string) ($place['kind_label'] ?? '')) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="dg-field dg-field--wide dg-audit-manual__actions">
        <button type="submit" class="dg-button dg-button--primary">Prüfen</button>
      </div>
    </form>

    <div class="dg-place-audit-output">
      <div id="dg-place-audit-message" class="dg-scan-result" hidden></div>
      <div id="dg-place-audit-panel" class="dg-panel dg-panel--nested" hidden></div>
    </div>
  </section>

  <?php elseif ($lagerView === 'bewegungen') : ?>
  <section class="dg-panel">
    <h2 class="dg-subsection-title">Letzte Bewegungen</h2>
    <?php if ($stockMovements === []) : ?>
      <p class="dg-muted">Noch keine Lagerbewegungen.</p>
    <?php else : ?>
      <div class="dg-table-wrap">
        <table class="dg-table dg-table--compact">
          <thead>
            <tr><th>Datum</th><th>Artikel</th><th>Menge</th><th>Art</th><th>Notiz</th></tr>
          </thead>
          <tbody>
            <?php foreach ($stockMovements as $mov) : ?>
              <tr>
                <td><?= View::escape((string) ($mov['movement_date'] ?? '')) ?></td>
                <td><?= View::escape((string) ($mov['article_number'] ?? '')) ?> — <?= View::escape((string) ($mov['title'] ?? '')) ?></td>
                <td><?= View::escape($fmtQty((float) ($mov['quantity'] ?? 0))) ?> <?= View::escape((string) ($mov['unit'] ?? '')) ?></td>
                <td><?= View::escape(StockMovementRepository::reasonLabel((string) ($mov['reason'] ?? ''))) ?></td>
                <td><?= View::escape((string) ($mov['note'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <?php elseif ($lagerView === 'inventur') : ?>
  <section class="dg-panel">
    <h2 class="dg-subsection-title">Inventur</h2>
    <?php if ($activeInventory === null && $canEdit) : ?>
      <form method="post" action="/app?page=lager&amp;view=inventur" class="dg-form-grid dg-form-grid--compact">
        <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
        <input type="hidden" name="inventory_start" value="1">
        <label class="dg-field">
          <span>Stichtag *</span>
          <input type="date" name="inventory_date" value="<?= View::escape(date('Y-m-d')) ?>" required>
        </label>
        <label class="dg-field dg-field--wide">
          <span>Notiz</span>
          <input type="text" name="inventory_note" maxlength="500" placeholder="Optional">
        </label>
        <div class="dg-field dg-field--actions">
          <button type="submit" class="dg-button dg-button--primary">Inventur starten</button>
        </div>
      </form>
      <p class="dg-field-hint">Es kann jeweils nur eine offene Inventur existieren. Alle Artikel mit Lagerführung werden übernommen.</p>
    <?php elseif ($activeInventory === null) : ?>
      <p class="dg-muted">Keine offene Inventur.</p>
    <?php else : ?>
      <p class="dg-field-hint">
        Offene Inventur #<?= (int) ($activeInventory['id'] ?? 0) ?>
        · Stichtag <?= View::escape((string) ($activeInventory['inventory_date'] ?? '')) ?>
        <?php if (($activeInventory['note'] ?? '') !== '') : ?>
          · <?= View::escape((string) $activeInventory['note']) ?>
        <?php endif; ?>
      </p>
      <?php if ($canEdit) : ?>
      <form method="post" action="/app?page=lager&amp;view=inventur" class="dg-form">
        <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
        <input type="hidden" name="inventory_save" value="1">
        <input type="hidden" name="inventory_id" value="<?= (int) ($activeInventory['id'] ?? 0) ?>">
        <div class="dg-table-wrap">
          <table class="dg-table dg-table--compact">
            <thead>
              <tr><th>Nr.</th><th>Bezeichnung</th><th>Buchbestand</th><th>Gezählt</th><th>Differenz</th></tr>
            </thead>
            <tbody>
              <?php foreach ($activeInventoryLines as $line) : ?>
                <?php
                  $book = (float) ($line['book_quantity'] ?? 0);
                  $counted = (float) ($line['counted_quantity'] ?? 0);
                  $diff = round($counted - $book, 3);
                ?>
                <tr>
                  <td><?= View::escape((string) ($line['article_number'] ?? '')) ?></td>
                  <td><?= View::escape((string) ($line['title'] ?? '')) ?></td>
                  <td><?= View::escape($fmtQty($book)) ?> <?= View::escape((string) ($line['unit'] ?? '')) ?></td>
                  <td>
                    <input type="text" name="counted[<?= (int) ($line['article_id'] ?? 0) ?>]"
                      value="<?= $counted != 0.0 ? View::escape($fmtQty($counted)) : '' ?>"
                      inputmode="decimal" class="dg-input--compact" placeholder="0">
                  </td>
                  <td><?= View::escape($fmtQty($diff)) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="dg-form-actions">
          <button type="submit" class="dg-button">Zählung speichern</button>
        </div>
      </form>
      <form method="post" action="/app?page=lager&amp;view=inventur" class="dg-form" style="margin-top:1rem;"
        onsubmit="return confirm('Inventur abschließen und Differenzen ins Lager buchen?');">
        <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
        <input type="hidden" name="inventory_close" value="1">
        <input type="hidden" name="inventory_id" value="<?= (int) ($activeInventory['id'] ?? 0) ?>">
        <button type="submit" class="dg-button dg-button--primary">Inventur abschließen</button>
      </form>
      <?php endif; ?>
    <?php endif; ?>
  </section>

  <?php if ($stockInventories !== []) : ?>
  <section class="dg-panel">
    <h2 class="dg-subsection-title">Abgeschlossene Inventuren</h2>
    <table class="dg-table dg-table--compact">
      <thead><tr><th>ID</th><th>Stichtag</th><th>Abgeschlossen</th><th>Notiz</th></tr></thead>
      <tbody>
        <?php foreach ($stockInventories as $inv) : ?>
          <?php if (($inv['status'] ?? '') !== 'closed') { continue; } ?>
          <tr>
            <td><?= (int) ($inv['id'] ?? 0) ?></td>
            <td><?= View::escape((string) ($inv['inventory_date'] ?? '')) ?></td>
            <td><?= View::escape((string) ($inv['closed_at'] ?? '')) ?></td>
            <td><?= View::escape((string) ($inv['note'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <?php endif; ?>

  <?php endif; ?>
  <?php endif; ?>
</div>
<?php if (in_array($lagerView, ['wareneingang', 'warenausgang', 'platz-check'], true)) : ?>
<script>
window.dgLagerScanConfig = {
  scanApiUrl: '/api/stock-scan',
  csrf: <?= json_encode($csrf, JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="<?= View::escape(Asset::url('/assets/js/vendor/html5-qrcode.min.js')) ?>" defer></script>
<script src="<?= View::escape(Asset::url('/assets/js/lager-camera-scan.js')) ?>" defer></script>
<?php if (in_array($lagerView, ['wareneingang', 'warenausgang'], true) && $canEdit) : ?>
<script src="<?= View::escape(Asset::url('/assets/js/lager-scan.js')) ?>" defer></script>
<?php endif; ?>
<?php if ($lagerView === 'platz-check') : ?>
<script src="<?= View::escape(Asset::url('/assets/js/lager-audit.js')) ?>" defer></script>
<?php endif; ?>
<?php endif; ?>
<script>
(function () {
  document.querySelectorAll('.dg-stock-adjust-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var panel = document.getElementById('dg-stock-adjust-panel');
      var idInput = document.getElementById('dg-stock-adjust-article-id');
      var labelInput = document.getElementById('dg-stock-adjust-article-label');
      if (!panel || !idInput || !labelInput) return;
      idInput.value = btn.getAttribute('data-article-id') || '';
      labelInput.value = btn.getAttribute('data-article-title') || '';
      panel.hidden = false;
      panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
  });
})();
</script>
