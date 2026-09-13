<?php
/** @var bool $dbConnected */
/** @var string $lagerStrukturTab */
/** @var list<array<string, mixed>> $stockLocations */
/** @var list<array<string, mixed>> $stockHalls */
/** @var list<array<string, mixed>> $stockShelves */
/** @var list<array{id: int, label: string, code: string}> $stockLocationOptions */

$lagerStrukturTab = $lagerStrukturTab ?? 'orte';
$stockLocations = $stockLocations ?? [];
$stockHalls = $stockHalls ?? [];
$stockShelves = $stockShelves ?? [];
$stockLocationOptions = $stockLocationOptions ?? StockStructureRepository::locationOptions();

$editLocationId = $lagerStrukturTab === 'orte' ? (int) ($_GET['edit'] ?? 0) : 0;
$editHallId = $lagerStrukturTab === 'hallen' ? (int) ($_GET['edit'] ?? 0) : 0;
$editShelfId = $lagerStrukturTab === 'regale' ? (int) ($_GET['edit'] ?? 0) : 0;

$editLocation = $editLocationId > 0 ? StockStructureRepository::findLocation($editLocationId) : null;
$editHall = $editHallId > 0 ? StockStructureRepository::findHall($editHallId) : null;
$editShelf = $editShelfId > 0 ? StockStructureRepository::findShelf($editShelfId) : null;
$shelfPlaces = $editShelf ? StockStructureRepository::placesForShelf($editShelfId) : [];

$filterLocationId = (int) ($_GET['location_id'] ?? 0);
$filterHallId = (int) ($_GET['hall_id'] ?? 0);

$tabBase = SettingsRegistry::tabUrl('lager-struktur');
?>
<div class="dg-lager-struktur">
  <p class="dg-lead">
    Lagerorte, Hallen und Regale/Stellplätze als Stammdaten. Der <strong>Positionscode</strong> setzt sich aus
    Ortkode-Hallenkode-Regalkode-Platz zusammen. Plätze können <strong>fest</strong> (Zwang bei Ein- und Ausgang) oder
    <strong>flexibel</strong> (Platz wird bei Einnahme zugewiesen, freie Plätze werden vorgeschlagen) sein.
  </p>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Zum Speichern ist eine funktionierende <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Datenbankverbindung</a> erforderlich.
    </div>
  <?php endif; ?>

  <nav class="dg-subtabs" aria-label="Lagerstruktur">
    <a href="<?= View::escape($tabBase . '&amp;ltab=orte') ?>" class="dg-subtabs__link<?= $lagerStrukturTab === 'orte' ? ' is-active' : '' ?>">Lagerorte</a>
    <a href="<?= View::escape($tabBase . '&amp;ltab=hallen') ?>" class="dg-subtabs__link<?= $lagerStrukturTab === 'hallen' ? ' is-active' : '' ?>">Hallen</a>
    <a href="<?= View::escape($tabBase . '&amp;ltab=regale') ?>" class="dg-subtabs__link<?= $lagerStrukturTab === 'regale' ? ' is-active' : '' ?>">Regale &amp; Stellplätze</a>
  </nav>

  <?php if ($lagerStrukturTab === 'orte') : ?>
    <details class="dg-notify-section"<?= $editLocation ? ' open' : '' ?>>
      <summary class="dg-notify-section__summary">
        <strong><?= $editLocation ? 'Lagerort bearbeiten' : 'Lagerort anlegen' ?></strong>
        <span class="dg-muted">Ortkode, Adresse, Funktion</span>
      </summary>
      <div class="dg-notify-section__body">
        <form class="dg-form" method="post" action="<?= View::escape($tabBase . '&ltab=orte') ?>">
          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
          <input type="hidden" name="stock_location_save" value="1">
          <?php if ($editLocation) : ?>
            <input type="hidden" name="id" value="<?= (int) $editLocation['id'] ?>">
          <?php endif; ?>
          <div class="dg-form-grid">
            <label class="dg-field">
              <span>Ortkode *</span>
              <input name="code" required maxlength="32" value="<?= View::escape((string) ($editLocation['code'] ?? '')) ?>" placeholder="z. B. WH1"<?= !$dbConnected ? ' disabled' : '' ?>>
            </label>
            <label class="dg-field">
              <span>Bezeichnung</span>
              <input name="name" maxlength="255" value="<?= View::escape((string) ($editLocation['name'] ?? '')) ?>" placeholder="z. B. Hauptlager"<?= !$dbConnected ? ' disabled' : '' ?>>
            </label>
            <label class="dg-field dg-field--wide">
              <span>Adresse</span>
              <textarea name="address" rows="2" placeholder="Straße, PLZ Ort"<?= !$dbConnected ? ' disabled' : '' ?>><?= View::escape((string) ($editLocation['address'] ?? '')) ?></textarea>
            </label>
            <label class="dg-field dg-field--wide">
              <span>Funktion / Nutzung</span>
              <input name="function_text" maxlength="500" value="<?= View::escape((string) ($editLocation['function_text'] ?? '')) ?>" placeholder="z. B. Baustofflager, Kühlhalle"<?= !$dbConnected ? ' disabled' : '' ?>>
            </label>
            <label class="dg-field dg-field--wide">
              <span>Besonderheiten</span>
              <textarea name="notes" rows="2"<?= !$dbConnected ? ' disabled' : '' ?>><?= View::escape((string) ($editLocation['notes'] ?? '')) ?></textarea>
            </label>
            <label class="dg-field">
              <span>Sortierung</span>
              <input type="number" name="sort_order" value="<?= (int) ($editLocation['sort_order'] ?? 0) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
            </label>
            <label class="dg-field">
              <span><input type="checkbox" name="is_active" value="1"<?= !$editLocation || !empty($editLocation['is_active']) ? ' checked' : '' ?><?= !$dbConnected ? ' disabled' : '' ?>> Aktiv</span>
            </label>
          </div>
          <div class="dg-form-actions">
            <button type="submit" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>><?= $editLocation ? 'Speichern' : 'Anlegen' ?></button>
            <?php if ($editLocation) : ?>
              <a class="dg-button" href="<?= View::escape($tabBase . '&ltab=orte') ?>">Abbrechen</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </details>

    <section class="dg-panel">
      <h3 class="dg-subsection-title">Lagerorte</h3>
      <?php if ($stockLocations === []) : ?>
        <p class="dg-muted">Noch keine Lagerorte angelegt.</p>
      <?php else : ?>
        <div class="dg-table-wrap">
          <table class="dg-table dg-table--compact">
            <thead>
              <tr>
                <th>Ortkode</th>
                <th>Bezeichnung / Funktion</th>
                <th>Hallen</th>
                <th>Regale</th>
                <th>Stellplatz-Gr.</th>
                <th>Plätze</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($stockLocations as $loc) : ?>
                <tr>
                  <td><strong><?= View::escape((string) $loc['code']) ?></strong></td>
                  <td>
                    <?= View::escape((string) ($loc['display_label'] ?? $loc['code'])) ?>
                    <?php if (trim((string) ($loc['function_text'] ?? '')) !== '') : ?>
                      <br><span class="dg-muted"><?= View::escape((string) $loc['function_text']) ?></span>
                    <?php endif; ?>
                  </td>
                  <td><?= (int) ($loc['hall_count'] ?? 0) ?></td>
                  <td><?= (int) ($loc['shelf_count'] ?? 0) ?></td>
                  <td><?= (int) ($loc['floor_slot_group_count'] ?? 0) ?></td>
                  <td><?= (int) ($loc['place_count'] ?? 0) ?></td>
                  <td class="dg-table__actions">
                    <a class="dg-button dg-button--small" href="<?= View::escape($tabBase . '&ltab=orte&amp;edit=' . (int) $loc['id']) ?>">Bearbeiten</a>
                    <form method="post" action="<?= View::escape($tabBase . '&ltab=orte') ?>" class="dg-inline-form" onsubmit="return confirm('Lagerort wirklich löschen?');">
                      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                      <input type="hidden" name="stock_location_delete" value="1">
                      <input type="hidden" name="id" value="<?= (int) $loc['id'] ?>">
                      <button type="submit" class="dg-button dg-button--small dg-button--danger"<?= !$dbConnected ? ' disabled' : '' ?>>Löschen</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

  <?php elseif ($lagerStrukturTab === 'hallen') : ?>
    <form class="dg-form dg-form--inline-filter" method="get" action="/app">
      <input type="hidden" name="page" value="einstellungen">
      <input type="hidden" name="tab" value="lager-struktur">
      <input type="hidden" name="ltab" value="hallen">
      <label class="dg-field">
        <span>Filter Lagerort</span>
        <select name="location_id" onchange="this.form.submit()">
          <option value="0">— alle —</option>
          <?php foreach ($stockLocationOptions as $opt) : ?>
            <option value="<?= (int) $opt['id'] ?>"<?= $filterLocationId === (int) $opt['id'] ? ' selected' : '' ?>><?= View::escape($opt['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </form>

    <details class="dg-notify-section"<?= $editHall ? ' open' : '' ?>>
      <summary class="dg-notify-section__summary">
        <strong><?= $editHall ? 'Halle bearbeiten' : 'Halle anlegen' ?></strong>
        <span class="dg-muted">Hallenkode, Nutzung, Besonderheiten</span>
      </summary>
      <div class="dg-notify-section__body">
        <form class="dg-form" method="post" action="<?= View::escape($tabBase . '&ltab=hallen') ?>">
          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
          <input type="hidden" name="stock_hall_save" value="1">
          <?php if ($editHall) : ?>
            <input type="hidden" name="id" value="<?= (int) $editHall['id'] ?>">
          <?php endif; ?>
          <div class="dg-form-grid">
            <label class="dg-field">
              <span>Lagerort *</span>
              <select name="location_id" required<?= !$dbConnected ? ' disabled' : '' ?>>
                <option value="">— wählen —</option>
                <?php foreach ($stockLocationOptions as $opt) : ?>
                  <option value="<?= (int) $opt['id'] ?>"<?= (int) ($editHall['location_id'] ?? $filterLocationId) === (int) $opt['id'] ? ' selected' : '' ?>><?= View::escape($opt['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="dg-field">
              <span>Hallenkode *</span>
              <input name="code" required maxlength="32" value="<?= View::escape((string) ($editHall['code'] ?? '')) ?>" placeholder="z. B. H02"<?= !$dbConnected ? ' disabled' : '' ?>>
            </label>
            <label class="dg-field dg-field--wide">
              <span>Nutzung</span>
              <input name="usage_text" maxlength="255" value="<?= View::escape((string) ($editHall['usage_text'] ?? '')) ?>" placeholder="z. B. Trockenlager, Katzenfutter, Tiernahrung"<?= !$dbConnected ? ' disabled' : '' ?>>
            </label>
            <label class="dg-field dg-field--wide">
              <span>Besonderheiten</span>
              <textarea name="notes" rows="2"<?= !$dbConnected ? ' disabled' : '' ?>><?= View::escape((string) ($editHall['notes'] ?? '')) ?></textarea>
            </label>
            <label class="dg-field">
              <span>Sortierung</span>
              <input type="number" name="sort_order" value="<?= (int) ($editHall['sort_order'] ?? 0) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
            </label>
            <label class="dg-field">
              <span><input type="checkbox" name="is_active" value="1"<?= !$editHall || !empty($editHall['is_active']) ? ' checked' : '' ?><?= !$dbConnected ? ' disabled' : '' ?>> Aktiv</span>
            </label>
          </div>
          <div class="dg-form-actions">
            <button type="submit" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>><?= $editHall ? 'Speichern' : 'Anlegen' ?></button>
            <?php if ($editHall) : ?>
              <a class="dg-button" href="<?= View::escape($tabBase . '&ltab=hallen') ?>">Abbrechen</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </details>

    <section class="dg-panel">
      <h3 class="dg-subsection-title">Hallen</h3>
      <?php
        $hallRows = $filterLocationId > 0
            ? array_values(array_filter($stockHalls, static fn ($h) => (int) ($h['location_id'] ?? 0) === $filterLocationId))
            : $stockHalls;
      ?>
      <?php if ($hallRows === []) : ?>
        <p class="dg-muted">Noch keine Hallen angelegt.</p>
      <?php else : ?>
        <div class="dg-table-wrap">
          <table class="dg-table dg-table--compact">
            <thead>
              <tr>
                <th>Ort</th>
                <th>Hallenkode</th>
                <th>Nutzung</th>
                <th>Regale</th>
                <th>Stellplatz-Gr.</th>
                <th>Plätze</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($hallRows as $hall) : ?>
                <tr>
                  <td><?= View::escape((string) ($hall['location_code'] ?? '')) ?></td>
                  <td><strong><?= View::escape((string) $hall['code']) ?></strong></td>
                  <td><?= View::escape((string) ($hall['usage_text'] ?? '—')) ?></td>
                  <td><?= (int) ($hall['shelf_count'] ?? 0) ?></td>
                  <td><?= (int) ($hall['floor_slot_group_count'] ?? 0) ?></td>
                  <td><?= (int) ($hall['place_count'] ?? 0) ?></td>
                  <td class="dg-table__actions">
                    <a class="dg-button dg-button--small" href="<?= View::escape($tabBase . '&ltab=hallen&amp;edit=' . (int) $hall['id']) ?>">Bearbeiten</a>
                    <form method="post" action="<?= View::escape($tabBase . '&ltab=hallen') ?>" class="dg-inline-form" onsubmit="return confirm('Halle wirklich löschen?');">
                      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                      <input type="hidden" name="stock_hall_delete" value="1">
                      <input type="hidden" name="id" value="<?= (int) $hall['id'] ?>">
                      <button type="submit" class="dg-button dg-button--small dg-button--danger"<?= !$dbConnected ? ' disabled' : '' ?>>Löschen</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

  <?php else : ?>
    <form class="dg-form dg-form--inline-filter" method="get" action="/app">
      <input type="hidden" name="page" value="einstellungen">
      <input type="hidden" name="tab" value="lager-struktur">
      <input type="hidden" name="ltab" value="regale">
      <label class="dg-field">
        <span>Lagerort</span>
        <select name="location_id" id="dg_lager_filter_location" onchange="this.form.submit()">
          <option value="0">— alle —</option>
          <?php foreach ($stockLocationOptions as $opt) : ?>
            <option value="<?= (int) $opt['id'] ?>"<?= $filterLocationId === (int) $opt['id'] ? ' selected' : '' ?>><?= View::escape($opt['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field">
        <span>Halle</span>
        <select name="hall_id" onchange="this.form.submit()">
          <option value="0">— alle —</option>
          <?php foreach ($stockHalls as $hall) : ?>
            <?php if ($filterLocationId > 0 && (int) ($hall['location_id'] ?? 0) !== $filterLocationId) { continue; } ?>
            <option value="<?= (int) $hall['id'] ?>"<?= $filterHallId === (int) $hall['id'] ? ' selected' : '' ?>><?= View::escape((string) ($hall['location_code'] ?? '') . ' / ' . (string) $hall['code']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </form>

    <details class="dg-notify-section"<?= $editShelf ? ' open' : '' ?>>
      <summary class="dg-notify-section__summary">
        <strong><?= $editShelf ? 'Regal bearbeiten' : 'Regal / Stellplätze anlegen' ?></strong>
        <span class="dg-muted">Regalkode, Kapazität, Anzahl Plätze</span>
      </summary>
      <div class="dg-notify-section__body">
        <form class="dg-form" method="post" action="<?= View::escape($tabBase . '&ltab=regale') ?>">
          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
          <input type="hidden" name="stock_shelf_save" value="1">
          <?php if ($editShelf) : ?>
            <input type="hidden" name="id" value="<?= (int) $editShelf['id'] ?>">
          <?php endif; ?>
          <div class="dg-form-grid">
            <label class="dg-field">
              <span>Lagerort *</span>
              <select name="location_id" id="dg_shelf_location" required<?= !$dbConnected ? ' disabled' : '' ?>>
                <option value="">— wählen —</option>
                <?php foreach ($stockLocationOptions as $opt) : ?>
                  <option value="<?= (int) $opt['id'] ?>"<?= (int) ($editShelf['location_id'] ?? $filterLocationId) === (int) $opt['id'] ? ' selected' : '' ?>><?= View::escape($opt['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="dg-field">
              <span>Halle *</span>
              <select name="hall_id" id="dg_shelf_hall" required<?= !$dbConnected ? ' disabled' : '' ?>>
                <option value="">— wählen —</option>
                <?php foreach ($stockHalls as $hall) : ?>
                  <option value="<?= (int) $hall['id'] ?>" data-location-id="<?= (int) $hall['location_id'] ?>"<?= (int) ($editShelf['hall_id'] ?? $filterHallId) === (int) $hall['id'] ? ' selected' : '' ?>><?= View::escape((string) ($hall['location_code'] ?? '') . ' / ' . (string) $hall['code']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="dg-field">
              <span>Regalkode *</span>
              <input name="code" required maxlength="32" value="<?= View::escape((string) ($editShelf['code'] ?? '')) ?>" placeholder="z. B. R03 oder PAL-A"<?= !$dbConnected ? ' disabled' : '' ?>>
            </label>
            <label class="dg-field">
              <span>Art *</span>
              <select name="shelf_type" required<?= !$dbConnected ? ' disabled' : '' ?>>
                <option value="shelf"<?= ($editShelf['shelf_type'] ?? 'shelf') === 'shelf' ? ' selected' : '' ?>>Regal</option>
                <option value="floor_slots"<?= ($editShelf['shelf_type'] ?? '') === 'floor_slots' ? ' selected' : '' ?>>Stellplätze (freie Palettenplätze)</option>
              </select>
            </label>
            <label class="dg-field">
              <span>Kapazität (Paletten/Kartons/Einheiten)</span>
              <input name="capacity_units" inputmode="decimal" value="<?= View::escape((string) ($editShelf['capacity_units'] ?? '0')) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
            </label>
            <label class="dg-field">
              <span>Einheit (optional)</span>
              <input name="capacity_label" maxlength="64" value="<?= View::escape((string) ($editShelf['capacity_label'] ?? '')) ?>" placeholder="Paletten, Kartons …"<?= !$dbConnected ? ' disabled' : '' ?>>
            </label>
            <label class="dg-field">
              <span>Anzahl Stellplätze *</span>
              <input type="number" name="slot_count" min="1" required value="<?= (int) ($editShelf['slot_count'] ?? 1) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
              <small class="dg-field-hint">Pro Platz wird automatisch P01, P02 … angelegt.</small>
            </label>
            <label class="dg-field">
              <span>Sortierung</span>
              <input type="number" name="sort_order" value="<?= (int) ($editShelf['sort_order'] ?? 0) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
            </label>
            <label class="dg-field">
              <span><input type="checkbox" name="is_active" value="1"<?= !$editShelf || !empty($editShelf['is_active']) ? ' checked' : '' ?><?= !$dbConnected ? ' disabled' : '' ?>> Aktiv</span>
            </label>
          </div>
          <div class="dg-form-actions">
            <button type="submit" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>><?= $editShelf ? 'Speichern' : 'Anlegen' ?></button>
            <?php if ($editShelf) : ?>
              <a class="dg-button" href="<?= View::escape($tabBase . '&ltab=regale') ?>">Abbrechen</a>
            <?php endif; ?>
          </div>
        </form>

        <?php if ($editShelf && $shelfPlaces !== []) : ?>
          <h4 class="dg-subsection-title">Stellplätze — fest oder flexibel</h4>
          <form class="dg-form" method="post" action="<?= View::escape($tabBase . '&ltab=regale&amp;edit=' . $editShelfId) ?>">
            <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
            <input type="hidden" name="stock_places_save" value="1">
            <input type="hidden" name="shelf_id" value="<?= $editShelfId ?>">
            <div class="dg-table-wrap">
              <table class="dg-table dg-table--compact">
                <thead>
                  <tr>
                    <th>Platz</th>
                    <th>Positionscode</th>
                    <th>Modus</th>
                    <th>Fester Artikel (ID)</th>
                    <th>Belegt</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($shelfPlaces as $pi => $place) : ?>
                    <tr>
                      <td>
                        <?= View::escape((string) $place['code']) ?>
                        <input type="hidden" name="places[<?= (int) $pi ?>][id]" value="<?= (int) $place['id'] ?>">
                      </td>
                      <td><?= View::escape((string) ($place['position_code'] ?? '')) ?></td>
                      <td>
                        <select name="places[<?= (int) $pi ?>][place_mode]"<?= !$dbConnected ? ' disabled' : '' ?>>
                          <option value="flexible"<?= ($place['place_mode'] ?? '') === 'flexible' ? ' selected' : '' ?>>flexibel</option>
                          <option value="fixed"<?= ($place['place_mode'] ?? '') === 'fixed' ? ' selected' : '' ?>>fest</option>
                        </select>
                      </td>
                      <td>
                        <input type="number" name="places[<?= (int) $pi ?>][fixed_article_id]" min="0" value="<?= (int) ($place['fixed_article_id'] ?? 0) ?>" placeholder="Artikel-ID"<?= !$dbConnected ? ' disabled' : '' ?>>
                      </td>
                      <td><?= !empty($place['is_occupied']) ? 'ja' : 'nein' ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div class="dg-form-actions">
              <button type="submit" class="dg-button"<?= !$dbConnected ? ' disabled' : '' ?>>Platz-Modi speichern</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </details>

    <section class="dg-panel">
      <h3 class="dg-subsection-title">Regale &amp; Stellplätze</h3>
      <?php
        $shelfRows = $stockShelves;
        if ($filterHallId > 0) {
            $shelfRows = array_values(array_filter($shelfRows, static fn ($s) => (int) ($s['hall_id'] ?? 0) === $filterHallId));
        } elseif ($filterLocationId > 0) {
            $shelfRows = array_values(array_filter($shelfRows, static fn ($s) => (int) ($s['location_id'] ?? 0) === $filterLocationId));
        }
      ?>
      <?php if ($shelfRows === []) : ?>
        <p class="dg-muted">Noch keine Regale oder Stellplatz-Gruppen angelegt.</p>
      <?php else : ?>
        <div class="dg-table-wrap">
          <table class="dg-table dg-table--compact">
            <thead>
              <tr>
                <th>Ort / Halle</th>
                <th>Code</th>
                <th>Art</th>
                <th>Kapazität</th>
                <th>Plätze</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($shelfRows as $shelf) : ?>
                <tr>
                  <td><?= View::escape((string) ($shelf['location_code'] ?? '') . ' / ' . (string) ($shelf['hall_code'] ?? '')) ?></td>
                  <td><strong><?= View::escape((string) $shelf['code']) ?></strong></td>
                  <td><?= View::escape((string) ($shelf['type_label'] ?? 'Regal')) ?></td>
                  <td><?php
                    $cap = (float) ($shelf['capacity_units'] ?? 0);
                    echo $cap > 0
                        ? View::escape(rtrim(rtrim(number_format($cap, 3, ',', '.'), '0'), ',') . ' ' . (string) ($shelf['capacity_label'] ?? ''))
                        : '—';
                  ?></td>
                  <td><?= (int) ($shelf['place_count'] ?? 0) ?></td>
                  <td class="dg-table__actions">
                    <a class="dg-button dg-button--small" href="<?= View::escape($tabBase . '&ltab=regale&amp;edit=' . (int) $shelf['id']) ?>">Bearbeiten</a>
                    <form method="post" action="<?= View::escape($tabBase . '&ltab=regale') ?>" class="dg-inline-form" onsubmit="return confirm('Regal wirklich löschen?');">
                      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                      <input type="hidden" name="stock_shelf_delete" value="1">
                      <input type="hidden" name="id" value="<?= (int) $shelf['id'] ?>">
                      <button type="submit" class="dg-button dg-button--small dg-button--danger"<?= !$dbConnected ? ' disabled' : '' ?>>Löschen</button>
                    </form>
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
