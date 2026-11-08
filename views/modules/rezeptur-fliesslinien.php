<?php
/**
 * Fließlinien Liste + Detail (Stationen/Puffer) — Katalog-UI.
 *
 * @var list<array<string, mixed>> $productionLineList
 * @var array{line: array<string, mixed>, stations: list<array<string, mixed>>}|null $productionLineDetail
 * @var list<array<string, mixed>> $productionLineBuffers
 * @var array<string, mixed>|null $productionLineStatus
 * @var list<array<string, mixed>> $workCenterOptions
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 * @var string|null $formError
 */
$lines = $productionLineList ?? [];
$detail = $productionLineDetail ?? null;
$buffers = $productionLineBuffers ?? [];
$lineStatusEval = $productionLineStatus ?? null;
$wcOptions = $workCenterOptions ?? [];
$canEdit = !empty($canEdit);
$dbConnected = !empty($dbConnected);
$formError = isset($formError) ? (string) $formError : '';
$detailId = is_array($detail) ? (int) ($detail['line']['id'] ?? 0) : 0;
$stations = is_array($detail) ? ($detail['stations'] ?? []) : [];
$bufByOrder = [];
foreach ($buffers as $b) {
    $bufByOrder[(int) ($b['after_station_order'] ?? 0)] = $b;
}
$wcNameById = [];
foreach ($wcOptions as $wc) {
    $wcNameById[(int) ($wc['id'] ?? 0)] = (string) ($wc['name'] ?? '');
}
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <?php
    View::partial('partials/back-nav', [
        'href' => '/app?page=rezeptur-maschinen',
        'label' => 'Zurück zu Maschinen',
    ]);
  ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Fließlinien</h1>
      <p class="dg-lead">Maschinenkette mit Zwischenpuffern — Planung und Status, keine Maschinensteuerung.</p>
    </div>
  </header>

  <?php if ($formError !== '') : ?>
    <div class="dg-flash dg-flash--error"><?= View::escape($formError) ?></div>
  <?php endif; ?>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Datenbank nicht verbunden.
      <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Einstellungen → Datenbank</a>
    </div>
  <?php endif; ?>

  <section class="dg-panel dg-mb-4">
    <h2 class="dg-subsection-title">Linien</h2>
    <div class="dg-table-wrap">
      <table class="dg-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Code</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($lines === []) : ?>
            <tr>
              <td colspan="4" class="dg-muted">Noch keine Fließlinie angelegt.</td>
            </tr>
          <?php else : ?>
            <?php foreach ($lines as $row) :
                $lid = (int) ($row['id'] ?? 0);
                $active = !empty($row['is_active']);
                ?>
              <tr>
                <td><?= View::escape((string) ($row['name'] ?? '')) ?></td>
                <td><?= View::escape((string) ($row['code'] ?? '')) ?></td>
                <td>
                  <span class="dg-badge <?= $active ? 'dg-badge--ok' : 'dg-badge--muted' ?>">
                    <?= $active ? 'aktiv' : 'inaktiv' ?>
                  </span>
                </td>
                <td class="dg-table__actions">
                  <div class="dg-table__actions-group">
                    <a class="dg-button dg-button--small" href="/app?page=rezeptur-fliesslinien&amp;id=<?= $lid ?>">Öffnen</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <?php if ($canEdit && $dbConnected) : ?>
    <section class="dg-panel dg-mb-4">
      <h2 class="dg-subsection-title">Neue Linie</h2>
      <form method="post" action="/app?page=rezeptur-fliesslinien" class="dg-form">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <div class="dg-form-grid">
          <label class="dg-field">
            <span>Name</span>
            <input type="text" name="name" required maxlength="191" value="">
          </label>
          <label class="dg-field">
            <span>Kurzcode</span>
            <input type="text" name="code" maxlength="64" value="">
          </label>
          <label class="dg-field">
            <span>Notiz</span>
            <input type="text" name="notes" maxlength="1000" value="">
          </label>
        </div>
        <div class="dg-form-actions">
          <button type="submit" class="dg-button dg-button--primary" name="production_line_save" value="1">Linie anlegen</button>
        </div>
      </form>
    </section>
  <?php endif; ?>

  <?php if ($detailId > 0 && is_array($detail)) :
      $line = $detail['line'];
      ?>
    <section class="dg-panel dg-mb-4">
      <h2 class="dg-subsection-title">Linie bearbeiten</h2>
      <?php if ($canEdit && $dbConnected) : ?>
        <form method="post" action="/app?page=rezeptur-fliesslinien&amp;id=<?= $detailId ?>" class="dg-form">
          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
          <input type="hidden" name="id" value="<?= $detailId ?>">
          <div class="dg-form-grid">
            <label class="dg-field">
              <span>Name</span>
              <input type="text" name="name" required maxlength="191" value="<?= View::escape((string) ($line['name'] ?? '')) ?>">
            </label>
            <label class="dg-field">
              <span>Kurzcode</span>
              <input type="text" name="code" maxlength="64" value="<?= View::escape((string) ($line['code'] ?? '')) ?>">
            </label>
            <label class="dg-field dg-field--checkbox">
              <input type="checkbox" name="is_active" value="1" <?= !empty($line['is_active']) ? 'checked' : '' ?>>
              <span>Aktiv</span>
            </label>
            <label class="dg-field">
              <span>Notiz</span>
              <input type="text" name="notes" maxlength="1000" value="<?= View::escape((string) ($line['notes'] ?? '')) ?>">
            </label>
          </div>
          <p class="dg-field-hint">Deaktivieren statt Löschen — Historie und Nachvollziehbarkeit bleiben erhalten.</p>
          <div class="dg-form-actions">
            <button type="submit" class="dg-button dg-button--primary" name="production_line_save" value="1">Linie speichern</button>
          </div>
        </form>
      <?php else : ?>
        <p><?= View::escape((string) ($line['name'] ?? '')) ?></p>
      <?php endif; ?>
    </section>

    <?php if (is_array($lineStatusEval) && ($stations !== [])) :
        $lsCode = (string) ($lineStatusEval['line_status'] ?? '');
        $statusStations = is_array($lineStatusEval['stations'] ?? null) ? $lineStatusEval['stations'] : [];
        $rawHint = (string) (($lineStatusEval['raw_material']['hint'] ?? '') ?: '');
        $auxHint = (string) (($lineStatusEval['auxiliary_material']['hint'] ?? '') ?: '');
        $bn = is_array($lineStatusEval['bottleneck'] ?? null) ? $lineStatusEval['bottleneck'] : null;
        $tp = is_array($lineStatusEval['line_throughput'] ?? null) ? $lineStatusEval['line_throughput'] : null;
        ?>
      <section class="dg-panel dg-mb-4">
        <h2 class="dg-subsection-title">Aktueller Status</h2>
        <p class="dg-field-hint">Aus Pufferständen und Störungen berechnet — keine Maschinensteuerung.</p>
        <p>
          <span class="dg-badge <?= View::escape(ProductionLineStatusService::lineStatusBadgeClass($lsCode)) ?>">
            <?= View::escape(ProductionLineStatusService::lineStatusLabel($lsCode)) ?>
          </span>
        </p>
        <?php if ($rawHint !== '' && empty($lineStatusEval['raw_material']['ok'])) : ?>
          <p class="dg-muted"><?= View::escape($rawHint) ?></p>
        <?php endif; ?>
        <?php if ($auxHint !== '' && empty($lineStatusEval['auxiliary_material']['ok'])) : ?>
          <p class="dg-muted"><?= View::escape($auxHint) ?></p>
        <?php endif; ?>
        <div class="dg-table-wrap">
          <table class="dg-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Maschine</th>
                <th>Status</th>
                <th>Hinweise</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($statusStations as $sst) :
                  $stCode = (string) ($sst['status'] ?? '');
                  $wcId = (int) ($sst['work_center_id'] ?? 0);
                  $name = $wcNameById[$wcId] ?? ((string) ($sst['label'] ?? '') !== '' ? (string) $sst['label'] : ('Maschine #' . $wcId));
                  $hints = ProductionLineStatusService::reasonLabels(
                      is_array($sst['reasons'] ?? null) ? $sst['reasons'] : []
                  );
                  if (!empty($sst['block_risk']) && !in_array('Risiko: Nachfolger außer Betrieb', $hints, true)) {
                      $hints[] = 'Risiko: Nachfolger außer Betrieb';
                  }
                  ?>
                <tr>
                  <td><?= (int) ($sst['station_order'] ?? 0) ?></td>
                  <td><?= View::escape($name) ?></td>
                  <td>
                    <span class="dg-badge <?= View::escape(ProductionLineStatusService::stationStatusBadgeClass($stCode)) ?>">
                      <?= View::escape(ProductionLineStatusService::stationStatusLabel($stCode)) ?>
                    </span>
                  </td>
                  <td class="dg-muted"><?= View::escape($hints === [] ? '—' : implode('; ', $hints)) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if (is_array($bn) && trim((string) ($bn['hint'] ?? '')) !== '') : ?>
          <p class="dg-field-hint dg-mt-3"><?= View::escape((string) $bn['hint']) ?></p>
        <?php endif; ?>
        <?php if (is_array($tp) && trim((string) ($tp['hint'] ?? '')) !== '') : ?>
          <p class="dg-field-hint"><?= View::escape((string) $tp['hint']) ?></p>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <section class="dg-panel dg-mb-4">
      <h2 class="dg-subsection-title">Stationen (Reihenfolge)</h2>
      <p class="dg-field-hint">Jede Maschine nur einmal. Reihenfolge von oben nach unten = 1…n.</p>
      <?php if ($canEdit && $dbConnected) : ?>
        <form method="post" action="/app?page=rezeptur-fliesslinien&amp;id=<?= $detailId ?>" class="dg-form">
          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
          <input type="hidden" name="id" value="<?= $detailId ?>">
          <?php
            $rows = $stations;
            while (count($rows) < 3) {
                $rows[] = ['work_center_id' => 0, 'label' => ''];
            }
            foreach ($rows as $i => $st) :
                ?>
            <div class="dg-form-grid">
              <label class="dg-field">
                <span>Station <?= (int) $i + 1 ?> — Maschine</span>
                <select name="work_center_id[]">
                  <option value="0">— optional leer —</option>
                  <?php foreach ($wcOptions as $wc) :
                      $wid = (int) ($wc['id'] ?? 0);
                      $sel = $wid === (int) ($st['work_center_id'] ?? 0);
                      ?>
                    <option value="<?= $wid ?>" <?= $sel ? 'selected' : '' ?>>
                      <?= View::escape((string) ($wc['name'] ?? ('#' . $wid))) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="dg-field">
                <span>Bezeichnung</span>
                <input type="text" name="station_label[]" maxlength="191" value="<?= View::escape((string) ($st['label'] ?? '')) ?>">
              </label>
            </div>
          <?php endforeach; ?>
          <div class="dg-form-actions">
            <button type="submit" class="dg-button dg-button--primary" name="production_line_stations_save" value="1">Stationen speichern</button>
          </div>
        </form>
      <?php else : ?>
        <div class="dg-table-wrap">
          <table class="dg-table">
            <thead>
              <tr><th>#</th><th>Maschine</th><th>Label</th></tr>
            </thead>
            <tbody>
              <?php foreach ($stations as $st) : ?>
                <tr>
                  <td><?= (int) ($st['station_order'] ?? 0) ?></td>
                  <td><?= (int) ($st['work_center_id'] ?? 0) ?></td>
                  <td><?= View::escape((string) ($st['label'] ?? '')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <?php if (count($stations) >= 2) : ?>
      <section class="dg-panel">
        <h2 class="dg-subsection-title">Zwischenpuffer</h2>
        <p class="dg-field-hint">Puffer nach jeder Station außer der letzten. Kapazität muss größer als 0 sein.</p>
        <?php if ($canEdit && $dbConnected) : ?>
          <form method="post" action="/app?page=rezeptur-fliesslinien&amp;id=<?= $detailId ?>" class="dg-form">
            <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
            <input type="hidden" name="id" value="<?= $detailId ?>">
            <?php
              $nSt = count($stations);
            for ($o = 1; $o < $nSt; $o++) :
                $b = $bufByOrder[$o] ?? [];
                ?>
              <input type="hidden" name="after_station_order[]" value="<?= $o ?>">
              <div class="dg-form-grid">
                <p class="dg-muted">Nach Station <?= $o ?></p>
                <label class="dg-field">
                  <span>Kapazität</span>
                  <input type="text" name="capacity_qty[]" inputmode="decimal" required value="<?= View::escape((string) ($b['capacity_qty'] ?? '1')) ?>">
                </label>
                <label class="dg-field">
                  <span>Mindestmenge</span>
                  <input type="text" name="min_qty[]" inputmode="decimal" value="<?= View::escape((string) ($b['min_qty'] ?? '0')) ?>">
                </label>
                <label class="dg-field">
                  <span>Ist-Menge</span>
                  <input type="text" name="current_qty[]" inputmode="decimal" value="<?= View::escape((string) ($b['current_qty'] ?? '0')) ?>">
                </label>
                <label class="dg-field">
                  <span>Einheit</span>
                  <input type="text" name="unit[]" maxlength="32" value="<?= View::escape((string) ($b['unit'] ?? 'Stk')) ?>">
                </label>
              </div>
            <?php endfor; ?>
            <div class="dg-form-actions">
              <button type="submit" class="dg-button dg-button--primary" name="production_line_buffers_save" value="1">Puffer speichern</button>
            </div>
          </form>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  <?php endif; ?>
</div>
