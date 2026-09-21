<?php
/**
 * @var array<string, mixed> $recipeForm
 * @var int|null $recipeId
 * @var list<array{id: int, title: string}> $recipeArticleOptions
 * @var list<array{id: int, name: string}> $recipeWorkCenterOptions
 * @var array<string, mixed>|null $recipeCalc
 * @var list<array<string, mixed>> $recipeSnapshots
 * @var array<int, array<string, mixed>> $recipeActuals
 * @var string|null $formError
 * @var bool $canEdit
 * @var array{type: string, message: string}|null $flash
 */
$isEdit = ($recipeId ?? 0) > 0;
$readOnly = !($canEdit ?? false);
$form = $recipeForm ?? RecipeRepository::emptyForm();
$bom = is_array($form['bom'] ?? null) ? $form['bom'] : [RecipeRepository::emptyBomLine()];
$routing = is_array($form['routing'] ?? null) ? $form['routing'] : [RecipeRepository::emptyRoutingLine()];
$statusOptions = RecipeRepository::statusOptions();
$articles = $recipeArticleOptions ?? [];
$workCenters = $recipeWorkCenterOptions ?? [];
$calc = is_array($recipeCalc ?? null) ? $recipeCalc : null;
$snapshots = $recipeSnapshots ?? [];
$actualsMap = $recipeActuals ?? [];
$plannedPreview = ($calc !== null)
    ? RecipeActualRepository::plannedFromRecipe($form, $routing, $calc)
    : null;
?>
<div class="dg-wrap">
  <?php
    View::partial('partials/back-nav', [
        'href' => '/app?page=rezeptur',
        'label' => 'Zurück zur Rezeptur',
    ]);
  ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title"><?= $isEdit ? 'Rezept bearbeiten' : 'Neues Rezept' ?></h1>
      <p class="dg-lead">Stückliste, Arbeitsplan, Vorkalkulation und Soll/Ist-Protokoll — Snapshot beim Speichern und beim Produktionslauf.</p>
    </div>
    <div class="dg-toolbar">
      <a class="dg-button" href="/app?page=rezeptur-maschinen">Maschinen</a>
    </div>
  </header>

  <?php View::render('partials/flash', compact('flash')); ?>
  <?php if (!empty($formError)) : ?>
    <div class="dg-flash dg-flash--error"><?= View::escape($formError) ?></div>
  <?php endif; ?>

  <div style="display:grid;gap:20px;grid-template-columns:minmax(0,1.5fr) minmax(260px,0.9fr);align-items:start;">
  <form method="post" action="/app?page=rezeptur-form" class="dg-form" id="dg-recipe-form">
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
    <?php if ($isEdit) : ?>
      <input type="hidden" name="id" value="<?= (int) $recipeId ?>">
    <?php endif; ?>

    <fieldset class="dg-fieldset" <?= $readOnly ? 'disabled' : '' ?>>
      <legend>Stammdaten</legend>
      <label class="dg-field">
        <span>Titel</span>
        <input name="title" required maxlength="191" value="<?= View::escape((string) ($form['title'] ?? '')) ?>">
      </label>
      <label class="dg-field">
        <span>Zielmenge</span>
        <input name="target_qty" inputmode="decimal" value="<?= View::escape((string) ($form['target_qty'] ?? '1')) ?>">
      </label>
      <label class="dg-field">
        <span>Zusatzzeit ohne Maschine (Min.)</span>
        <input name="labor_minutes" inputmode="decimal" value="<?= View::escape((string) ($form['labor_minutes'] ?? '0')) ?>">
      </label>
      <label class="dg-field">
        <span>Wunsch-Marge (%)</span>
        <input name="margin_pct" inputmode="decimal" value="<?= View::escape((string) ($form['margin_pct'] ?? '0')) ?>">
      </label>
      <label class="dg-field">
        <span>Status</span>
        <select name="status">
          <?php foreach ($statusOptions as $value => $label) : ?>
            <option value="<?= View::escape($value) ?>"<?= (($form['status'] ?? '') === $value) ? ' selected' : '' ?>><?= View::escape($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field">
        <span>Version</span>
        <input name="version" type="number" min="1" step="1" value="<?= (int) ($form['version'] ?? 1) ?>">
      </label>
      <label class="dg-field">
        <span>Notizen</span>
        <textarea name="notes" rows="3" maxlength="1000"><?= View::escape((string) ($form['notes'] ?? '')) ?></textarea>
      </label>
    </fieldset>

    <fieldset class="dg-fieldset" <?= $readOnly ? 'disabled' : '' ?>>
      <legend>Stückliste (BOM)</legend>
      <p class="dg-field-hint">EK: Preferred-Einkaufspreis des Artikels, oder manueller EK/Einheit.</p>
      <div class="dg-table-wrap">
        <table class="dg-table" id="dg-recipe-bom">
          <thead>
            <tr>
              <th>Material</th>
              <th>Artikel</th>
              <th>Menge</th>
              <th>Verschnitt %</th>
              <th>Einheit</th>
              <th>EK/Einh. (opt.)</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($bom as $i => $line) : ?>
              <tr class="dg-recipe-bom-row">
                <td><input name="bom[<?= (int) $i ?>][material_label]" value="<?= View::escape((string) ($line['material_label'] ?? '')) ?>" placeholder="Grundstoff"></td>
                <td>
                  <select name="bom[<?= (int) $i ?>][article_id]">
                    <option value="0">— keiner —</option>
                    <?php foreach ($articles as $art) : ?>
                      <option value="<?= (int) $art['id'] ?>"<?= ((int) ($line['article_id'] ?? 0) === (int) $art['id']) ? ' selected' : '' ?>><?= View::escape((string) $art['title']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td><input name="bom[<?= (int) $i ?>][qty]" inputmode="decimal" value="<?= View::escape((string) ($line['qty'] ?? '1')) ?>"></td>
                <td><input name="bom[<?= (int) $i ?>][scrap_pct]" inputmode="decimal" value="<?= View::escape((string) ($line['scrap_pct'] ?? '0')) ?>"></td>
                <td><input name="bom[<?= (int) $i ?>][unit]" value="<?= View::escape((string) ($line['unit'] ?? 'Stk')) ?>" maxlength="32"></td>
                <td><input name="bom[<?= (int) $i ?>][unit_cost]" inputmode="decimal" value="<?= View::escape((string) ($line['unit_cost'] ?? '')) ?>" placeholder="auto"></td>
                <td><?php if (!$readOnly) : ?><button type="button" class="dg-button dg-button--danger" data-bom-remove>×</button><?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if (!$readOnly) : ?>
        <button type="button" class="dg-button" id="dg-recipe-bom-add" style="margin-top:8px;">Material hinzufügen</button>
      <?php endif; ?>
    </fieldset>

    <fieldset class="dg-fieldset" <?= $readOnly ? 'disabled' : '' ?>>
      <legend>Arbeitsplan (Routing)</legend>
      <p class="dg-field-hint">Schritte mit Maschine, Rüst- und Laufzeit. Stundensatz aus Maschinen-Stammdaten.</p>
      <div class="dg-table-wrap">
        <table class="dg-table" id="dg-recipe-routing">
          <thead>
            <tr>
              <th>Schritt</th>
              <th>Maschine / Platz</th>
              <th>Rüst (Min.)</th>
              <th>Lauf (Min.)</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($routing as $i => $step) : ?>
              <tr class="dg-recipe-routing-row">
                <td><input name="routing[<?= (int) $i ?>][label]" value="<?= View::escape((string) ($step['label'] ?? '')) ?>" placeholder="z. B. Mischen"></td>
                <td>
                  <select name="routing[<?= (int) $i ?>][work_center_id]">
                    <option value="0">— wählen —</option>
                    <?php foreach ($workCenters as $wc) : ?>
                      <option value="<?= (int) $wc['id'] ?>"<?= ((int) ($step['work_center_id'] ?? 0) === (int) $wc['id']) ? ' selected' : '' ?>><?= View::escape((string) $wc['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td><input name="routing[<?= (int) $i ?>][setup_min]" inputmode="decimal" value="<?= View::escape((string) ($step['setup_min'] ?? '0')) ?>"></td>
                <td><input name="routing[<?= (int) $i ?>][run_min]" inputmode="decimal" value="<?= View::escape((string) ($step['run_min'] ?? '0')) ?>"></td>
                <td><?php if (!$readOnly) : ?><button type="button" class="dg-button dg-button--danger" data-routing-remove>×</button><?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if (!$readOnly) : ?>
        <button type="button" class="dg-button" id="dg-recipe-routing-add" style="margin-top:8px;">Schritt hinzufügen</button>
      <?php endif; ?>
      <?php if ($workCenters === []) : ?>
        <p class="dg-field-hint">Noch keine Maschinen — zuerst unter <a href="/app?page=rezeptur-maschinen">Maschinen &amp; Stundensatz</a> anlegen.</p>
      <?php endif; ?>
    </fieldset>

    <?php if (!$readOnly) : ?>
      <div class="dg-form-actions">
        <button type="submit" class="dg-button dg-button--primary" name="recipe_save" value="1">Speichern</button>
        <?php if ($isEdit) : ?>
          <button type="submit" class="dg-button dg-button--danger" name="recipe_delete" value="1">Löschen</button>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </form>

  <aside>
    <div class="dg-card" style="padding:16px;margin-bottom:16px;">
      <h2 class="dg-subsection-title" style="margin-top:0;">Vorkalkulation</h2>
      <?php if ($calc === null) : ?>
        <p class="dg-field-hint">Nach dem Speichern erscheint die Kalkulation. Speichern legt zusätzlich einen Snapshot an.</p>
      <?php else : ?>
        <?php if (!empty($calc['warnings']) && is_array($calc['warnings'])) : ?>
          <div class="dg-flash dg-flash--warning" style="margin-bottom:10px;" id="dg-recipe-calc-warnings">
            <?php foreach ($calc['warnings'] as $w) : ?>
              <div><?= View::escape((string) $w) ?></div>
            <?php endforeach; ?>
          </div>
        <?php else : ?>
          <div id="dg-recipe-calc-warnings" hidden></div>
        <?php endif; ?>
        <dl style="margin:0;display:grid;gap:6px;font-size:0.95rem;" id="dg-recipe-calc-dl">
          <div style="display:flex;justify-content:space-between;gap:8px;"><dt>K<sub>mat</sub></dt><dd style="margin:0;font-weight:600;" data-calc="k_mat"><?= View::escape(RecipeCostService::formatEur((float) ($calc['k_mat'] ?? 0), 2)) ?></dd></div>
          <div style="display:flex;justify-content:space-between;gap:8px;"><dt>K<sub>fert</sub></dt><dd style="margin:0;font-weight:600;" data-calc="k_fert"><?= View::escape(RecipeCostService::formatEur((float) ($calc['k_fert'] ?? 0), 2)) ?></dd></div>
          <div style="display:flex;justify-content:space-between;gap:8px;border-top:1px solid var(--dg-border,#ddd);padding-top:6px;"><dt>Selbstkosten</dt><dd style="margin:0;font-weight:700;" data-calc="self_cost"><?= View::escape(RecipeCostService::formatEur((float) ($calc['self_cost'] ?? 0), 2)) ?></dd></div>
          <div style="display:flex;justify-content:space-between;gap:8px;"><dt>VK (mit Marge)</dt><dd style="margin:0;font-weight:700;" data-calc="vk"><?= View::escape(RecipeCostService::formatEur((float) ($calc['vk'] ?? 0), 2)) ?></dd></div>
          <div style="display:flex;justify-content:space-between;gap:8px;"><dt>je Einheit (SK)</dt><dd style="margin:0;" data-calc="unit_self_cost"><?= View::escape(RecipeCostService::formatEur((float) ($calc['unit_self_cost'] ?? 0), 4)) ?></dd></div>
          <div style="display:flex;justify-content:space-between;gap:8px;"><dt>je Einheit (VK)</dt><dd style="margin:0;" data-calc="unit_vk"><?= View::escape(RecipeCostService::formatEur((float) ($calc['unit_vk'] ?? 0), 4)) ?></dd></div>
          <div style="display:flex;justify-content:space-between;gap:8px;border-top:1px dashed var(--dg-border,#ddd);padding-top:6px;"><dt>Δ Selbstkosten</dt><dd style="margin:0;" data-calc="delta_self_cost">0,00 €</dd></div>
          <div style="display:flex;justify-content:space-between;gap:8px;"><dt>Δ VK</dt><dd style="margin:0;" data-calc="delta_vk">0,00 €</dd></div>
        </dl>
      <?php endif; ?>

      <?php if ($calc !== null && $canEdit) : ?>
        <?php $baseElec = (float) (($calc['rates']['electricity_eur_per_kwh'] ?? RecipeCostSettings::get()['electricity_eur_per_kwh'] ?? 0.3)); ?>
        <div class="dg-card" style="padding:12px;margin-top:14px;background:color-mix(in srgb, var(--dg-primary,#336) 6%, #fff);" id="dg-recipe-whatif"
          data-csrf="<?= View::escape(Csrf::token()) ?>"
          data-recipe-id="<?= (int) ($recipeId ?? 0) ?>"
          data-base-elec="<?= View::escape((string) $baseElec) ?>">
          <h3 class="dg-subsection-title" style="margin:0 0 8px;font-size:1rem;">Was wäre wenn…</h3>
          <p class="dg-field-hint" style="margin-top:0;">Slider ändern nur die Anzeige. „Übernehmen“ schreibt in die Formularfelder (Strom zusätzlich in die Kostensätze).</p>
          <label class="dg-field">
            <span>Strompreis: <strong data-whatif-label="elec"><?= View::escape(number_format($baseElec, 2, ',', '.')) ?></strong> €/kWh</span>
            <input type="range" min="0.05" max="1.00" step="0.01" value="<?= View::escape((string) round($baseElec, 2)) ?>" data-whatif="elec">
          </label>
          <label class="dg-field">
            <span>Charge: <strong data-whatif-label="charge">1,00</strong>× (Menge &amp; Laufzeit)</span>
            <input type="range" min="0.25" max="4" step="0.05" value="1" data-whatif="charge">
          </label>
          <label class="dg-field">
            <span>Rüstzeit: <strong data-whatif-label="setup">1,00</strong>×</span>
            <input type="range" min="0.25" max="4" step="0.05" value="1" data-whatif="setup">
          </label>
          <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;">
            <button type="button" class="dg-button" data-whatif-reset>Zurücksetzen</button>
            <button type="button" class="dg-button dg-button--primary" data-whatif-apply>Übernehmen</button>
          </div>
          <p class="dg-field-hint" id="dg-whatif-status" style="margin-bottom:0;"></p>
        </div>
      <?php endif; ?>

      <?php if ($isEdit && $canEdit && $plannedPreview !== null) : ?>
        <form method="post" action="/app?page=rezeptur-form&amp;action=edit&amp;id=<?= (int) $recipeId ?>" style="margin-top:14px;" class="dg-form" id="dg-recipe-run-form">
          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
          <input type="hidden" name="id" value="<?= (int) $recipeId ?>">
          <h3 class="dg-subsection-title" style="margin:0 0 8px;font-size:1rem;">Produktionsdurchlauf</h3>
          <p class="dg-field-hint" style="margin-top:0;">Speichert unveränderlichen Soll-Snapshot und Ist-Werte. Keine Buchung.</p>
          <div style="display:grid;gap:8px;font-size:0.9rem;margin-bottom:10px;">
            <div style="display:flex;justify-content:space-between;gap:8px;"><span>Soll Menge</span><strong><?= View::escape(number_format((float) $plannedPreview['planned_qty'], 3, ',', '.')) ?></strong></div>
            <div style="display:flex;justify-content:space-between;gap:8px;"><span>Soll Rüst (Min.)</span><strong><?= View::escape(number_format((float) $plannedPreview['planned_setup_min'], 2, ',', '.')) ?></strong></div>
            <div style="display:flex;justify-content:space-between;gap:8px;"><span>Soll Lauf (Min.)</span><strong><?= View::escape(number_format((float) $plannedPreview['planned_run_min'], 2, ',', '.')) ?></strong></div>
            <div style="display:flex;justify-content:space-between;gap:8px;"><span>Soll SK</span><strong><?= View::escape(RecipeCostService::formatEur((float) $plannedPreview['planned_self_cost'], 2)) ?></strong></div>
          </div>
          <label class="dg-field">
            <span>Ist Menge</span>
            <input name="actual_qty" inputmode="decimal" value="<?= View::escape((string) $plannedPreview['planned_qty']) ?>">
          </label>
          <label class="dg-field">
            <span>Ist Rüstzeit (Min.)</span>
            <input name="actual_setup_min" inputmode="decimal" value="<?= View::escape((string) $plannedPreview['planned_setup_min']) ?>">
          </label>
          <label class="dg-field">
            <span>Ist Laufzeit (Min.)</span>
            <input name="actual_run_min" inputmode="decimal" value="<?= View::escape((string) $plannedPreview['planned_run_min']) ?>">
          </label>
          <label class="dg-field">
            <span>Ist Selbstkosten (€, optional)</span>
            <input name="actual_self_cost" inputmode="decimal" placeholder="leer = ohne">
          </label>
          <label class="dg-field">
            <span>Notiz</span>
            <textarea name="actual_note" rows="2" maxlength="1000"></textarea>
          </label>
          <button type="submit" class="dg-button dg-button--primary" name="recipe_run_snapshot" value="1">Produktionsdurchlauf protokollieren</button>
        </form>
      <?php elseif ($isEdit && $canEdit) : ?>
        <p class="dg-field-hint" style="margin-top:14px;">Nach Speichern / Kalkulation kann ein Produktionsdurchlauf protokolliert werden.</p>
      <?php endif; ?>
    </div>

    <?php if ($snapshots !== []) : ?>
      <div class="dg-card" style="padding:16px;">
        <h2 class="dg-subsection-title" style="margin-top:0;">Snapshots &amp; Soll/Ist</h2>
        <ul style="margin:0;padding-left:0;list-style:none;font-size:0.9rem;">
          <?php foreach ($snapshots as $snap) : ?>
            <?php
              $kind = (string) ($snap['kind'] ?? 'save');
              $when = (string) ($snap['created_at'] ?? '');
              $whenLabel = $when !== '' ? date('d.m.Y H:i', strtotime($when)) : '—';
              $res = is_array($snap['result'] ?? null) ? $snap['result'] : [];
              $snapId = (int) ($snap['id'] ?? 0);
              $act = $actualsMap[$snapId] ?? null;
            ?>
            <li style="margin-bottom:12px;padding-bottom:10px;border-bottom:1px solid var(--dg-border,#eee);">
              <div>
                <strong><?= $kind === 'run' ? 'Lauf' : 'Speichern' ?></strong>
                · <?= View::escape($whenLabel) ?>
                · SK <?= View::escape(RecipeCostService::formatEur((float) ($res['self_cost'] ?? 0), 2)) ?>
                · VK <?= View::escape(RecipeCostService::formatEur((float) ($res['vk'] ?? 0), 2)) ?>
              </div>
              <?php if (is_array($act)) : ?>
                <?php
                  $dQty = (float) ($act['actual_qty'] ?? 0) - (float) ($act['planned_qty'] ?? 0);
                  $dSetup = (float) ($act['actual_setup_min'] ?? 0) - (float) ($act['planned_setup_min'] ?? 0);
                  $dRun = (float) ($act['actual_run_min'] ?? 0) - (float) ($act['planned_run_min'] ?? 0);
                  $fmtDelta = static function (float $v, int $dec = 2): string {
                      $sign = $v > 0 ? '+' : '';
                      return $sign . number_format($v, $dec, ',', '.');
                  };
                ?>
                <div class="dg-field-hint" style="margin:6px 0;">
                  Soll/Ist Menge <?= View::escape(number_format((float) ($act['planned_qty'] ?? 0), 3, ',', '.')) ?>
                  → <?= View::escape(number_format((float) ($act['actual_qty'] ?? 0), 3, ',', '.')) ?>
                  (Δ <?= View::escape($fmtDelta($dQty, 3)) ?>)
                  · Rüst Δ <?= View::escape($fmtDelta($dSetup)) ?> Min
                  · Lauf Δ <?= View::escape($fmtDelta($dRun)) ?> Min
                  <?php if (($act['note'] ?? '') !== '') : ?>
                    · <?= View::escape((string) $act['note']) ?>
                  <?php endif; ?>
                </div>
                <?php if ($canEdit) : ?>
                  <details style="margin-top:4px;">
                    <summary style="cursor:pointer;">Ist anpassen</summary>
                    <form method="post" action="/app?page=rezeptur-form&amp;action=edit&amp;id=<?= (int) $recipeId ?>" class="dg-form" style="margin-top:8px;">
                      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                      <input type="hidden" name="id" value="<?= (int) $recipeId ?>">
                      <input type="hidden" name="actual_id" value="<?= (int) ($act['id'] ?? 0) ?>">
                      <label class="dg-field"><span>Ist Menge</span>
                        <input name="actual_qty" inputmode="decimal" value="<?= View::escape((string) ($act['actual_qty'] ?? '')) ?>">
                      </label>
                      <label class="dg-field"><span>Ist Rüst (Min.)</span>
                        <input name="actual_setup_min" inputmode="decimal" value="<?= View::escape((string) ($act['actual_setup_min'] ?? '')) ?>">
                      </label>
                      <label class="dg-field"><span>Ist Lauf (Min.)</span>
                        <input name="actual_run_min" inputmode="decimal" value="<?= View::escape((string) ($act['actual_run_min'] ?? '')) ?>">
                      </label>
                      <label class="dg-field"><span>Ist SK (€)</span>
                        <input name="actual_self_cost" inputmode="decimal" value="<?= View::escape($act['actual_self_cost'] !== null && $act['actual_self_cost'] !== '' ? (string) $act['actual_self_cost'] : '') ?>">
                      </label>
                      <label class="dg-field"><span>Notiz</span>
                        <textarea name="note" rows="2" maxlength="1000"><?= View::escape((string) ($act['note'] ?? '')) ?></textarea>
                      </label>
                      <button type="submit" class="dg-button" name="recipe_actual_update" value="1">Ist speichern</button>
                    </form>
                  </details>
                <?php endif; ?>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </aside>
  </div>

  <section class="dg-recipe-flow" id="dg-recipe-flow"
    data-wc-names="<?= View::escape(json_encode(array_reduce($workCenters, static function (array $acc, array $w): array {
        $acc[(string) (int) ($w['id'] ?? 0)] = (string) ($w['name'] ?? '');
        return $acc;
    }, []), JSON_UNESCAPED_UNICODE) ?: '{}') ?>">
    <h2 class="dg-recipe-flow__title">Ablaufgraph</h2>
    <p class="dg-field-hint dg-recipe-flow__hint">Live aus Stückliste und Arbeitsplan — nur Anzeige, keine Bearbeitung im Graphen.</p>
    <p class="dg-recipe-flow__empty" data-rf-empty>Material oder Arbeitsschritte eintragen, dann erscheint der Ablauf.</p>
    <div class="dg-recipe-flow__canvas" data-rf-canvas></div>
  </section>
</div>

<?php if (!$readOnly) : ?>
<script>
(function () {
  var articleOptions = <?= json_encode(array_map(static fn ($a) => ['id' => (int) $a['id'], 'title' => (string) $a['title']], $articles), JSON_UNESCAPED_UNICODE) ?>;
  var wcOptions = <?= json_encode(array_map(static fn ($w) => ['id' => (int) $w['id'], 'name' => (string) $w['name']], $workCenters), JSON_UNESCAPED_UNICODE) ?>;

  function wireTable(tableId, addId, removeAttr, prefix, buildRow) {
    var table = document.getElementById(tableId);
    var addBtn = document.getElementById(addId);
    if (!table || !addBtn) return;
    var tbody = table.querySelector('tbody');
    function reindex() {
      Array.prototype.forEach.call(tbody.querySelectorAll('tr'), function (tr, i) {
        tr.querySelectorAll('[name]').forEach(function (el) {
          el.name = el.name.replace(new RegExp(prefix + '\\[\\d+\\]'), prefix + '[' + i + ']');
        });
      });
    }
    addBtn.addEventListener('click', function () {
      tbody.appendChild(buildRow(tbody.querySelectorAll('tr').length));
      reindex();
    });
    tbody.addEventListener('click', function (e) {
      var btn = e.target.closest('[' + removeAttr + ']');
      if (!btn) return;
      var rows = tbody.querySelectorAll('tr');
      if (rows.length <= 1) {
        rows[0].querySelectorAll('input').forEach(function (inp) {
          if (/qty|run_min/.test(inp.name)) inp.value = '1';
          else if (/scrap|setup_min/.test(inp.name)) inp.value = '0';
          else if (/unit\]/.test(inp.name) && !/unit_cost/.test(inp.name)) inp.value = 'Stk';
          else inp.value = '';
        });
        rows[0].querySelectorAll('select').forEach(function (sel) { sel.value = '0'; });
        return;
      }
      btn.closest('tr').remove();
      reindex();
    });
  }

  wireTable('dg-recipe-bom', 'dg-recipe-bom-add', 'data-bom-remove', 'bom', function (index) {
    var opts = '<option value="0">— keiner —</option>';
    articleOptions.forEach(function (a) {
      opts += '<option value="' + a.id + '">' + String(a.title).replace(/</g, '&lt;') + '</option>';
    });
    var tr = document.createElement('tr');
    tr.innerHTML =
      '<td><input name="bom[' + index + '][material_label]" placeholder="Grundstoff"></td>' +
      '<td><select name="bom[' + index + '][article_id]">' + opts + '</select></td>' +
      '<td><input name="bom[' + index + '][qty]" value="1" inputmode="decimal"></td>' +
      '<td><input name="bom[' + index + '][scrap_pct]" value="0" inputmode="decimal"></td>' +
      '<td><input name="bom[' + index + '][unit]" value="Stk" maxlength="32"></td>' +
      '<td><input name="bom[' + index + '][unit_cost]" inputmode="decimal" placeholder="auto"></td>' +
      '<td><button type="button" class="dg-button dg-button--danger" data-bom-remove>×</button></td>';
    return tr;
  });

  wireTable('dg-recipe-routing', 'dg-recipe-routing-add', 'data-routing-remove', 'routing', function (index) {
    var opts = '<option value="0">— wählen —</option>';
    wcOptions.forEach(function (w) {
      opts += '<option value="' + w.id + '">' + String(w.name).replace(/</g, '&lt;') + '</option>';
    });
    var tr = document.createElement('tr');
    tr.innerHTML =
      '<td><input name="routing[' + index + '][label]" placeholder="Schritt"></td>' +
      '<td><select name="routing[' + index + '][work_center_id]">' + opts + '</select></td>' +
      '<td><input name="routing[' + index + '][setup_min]" value="0" inputmode="decimal"></td>' +
      '<td><input name="routing[' + index + '][run_min]" value="0" inputmode="decimal"></td>' +
      '<td><button type="button" class="dg-button dg-button--danger" data-routing-remove>×</button></td>';
    return tr;
  });

  var form = document.getElementById('dg-recipe-form');
  if (form) {
    form.addEventListener('submit', function (e) {
      if (e.submitter && e.submitter.name === 'recipe_delete' && !window.confirm('Rezept wirklich löschen?')) {
        e.preventDefault();
      }
    });
  }

  (function wireWhatIf() {
    var box = document.getElementById('dg-recipe-whatif');
    if (!box || !form) return;
    var csrf = box.getAttribute('data-csrf') || '';
    var statusEl = document.getElementById('dg-whatif-status');
    var timer = null;

    function fmtFactor(v) {
      return (Math.round(v * 100) / 100).toFixed(2).replace('.', ',');
    }
    function fmtElec(v) {
      return (Math.round(v * 100) / 100).toFixed(2).replace('.', ',');
    }
    function readNum(name) {
      var el = form.querySelector('[name="' + name + '"]');
      if (!el) return 0;
      return parseFloat(String(el.value).replace(',', '.')) || 0;
    }
    function collectPayload() {
      var bom = [];
      form.querySelectorAll('#dg-recipe-bom tbody tr').forEach(function (tr) {
        var get = function (n) {
          var el = tr.querySelector('[name*="[' + n + ']"]');
          return el ? el.value : '';
        };
        bom.push({
          material_label: get('material_label'),
          article_id: parseInt(get('article_id'), 10) || 0,
          qty: get('qty'),
          scrap_pct: get('scrap_pct'),
          unit: get('unit'),
          unit_cost: get('unit_cost')
        });
      });
      var routing = [];
      form.querySelectorAll('#dg-recipe-routing tbody tr').forEach(function (tr) {
        var get = function (n) {
          var el = tr.querySelector('[name*="[' + n + ']"]');
          return el ? el.value : '';
        };
        routing.push({
          label: get('label'),
          work_center_id: parseInt(get('work_center_id'), 10) || 0,
          setup_min: get('setup_min'),
          run_min: get('run_min')
        });
      });
      return {
        recipe: {
          title: (form.querySelector('[name="title"]') || {}).value || '',
          target_qty: readNum('target_qty'),
          labor_minutes: readNum('labor_minutes'),
          margin_pct: readNum('margin_pct'),
          status: (form.querySelector('[name="status"]') || {}).value || 'draft',
          version: readNum('version') || 1
        },
        bom: bom,
        routing: routing
      };
    }
    function sliderVals() {
      var elec = parseFloat((box.querySelector('[data-whatif="elec"]') || {}).value || '0') || 0;
      var charge = parseFloat((box.querySelector('[data-whatif="charge"]') || {}).value || '1') || 1;
      var setup = parseFloat((box.querySelector('[data-whatif="setup"]') || {}).value || '1') || 1;
      return { elec: elec, charge: charge, setup: setup };
    }
    function updateLabels() {
      var v = sliderVals();
      var le = box.querySelector('[data-whatif-label="elec"]');
      var lc = box.querySelector('[data-whatif-label="charge"]');
      var ls = box.querySelector('[data-whatif-label="setup"]');
      if (le) le.textContent = fmtElec(v.elec);
      if (lc) lc.textContent = fmtFactor(v.charge);
      if (ls) ls.textContent = fmtFactor(v.setup);
    }
    function paintCalc(data) {
      var fmt = data.formatted || {};
      Object.keys(fmt).forEach(function (key) {
        var el = document.querySelector('#dg-recipe-calc-dl [data-calc="' + key + '"]');
        if (el) el.textContent = fmt[key];
      });
      var warnBox = document.getElementById('dg-recipe-calc-warnings');
      if (warnBox) {
        var warnings = data.warnings || [];
        if (warnings.length) {
          warnBox.hidden = false;
          warnBox.className = 'dg-flash dg-flash--warning';
          warnBox.style.marginBottom = '10px';
          warnBox.innerHTML = warnings.map(function (w) {
            return '<div>' + String(w).replace(/</g, '&lt;') + '</div>';
          }).join('');
        } else {
          warnBox.hidden = true;
          warnBox.innerHTML = '';
        }
      }
    }
    function postWhatIf() {
      var v = sliderVals();
      var body = new FormData();
      body.append('_csrf', csrf);
      body.append('action', 'whatif');
      body.append('payload', JSON.stringify(collectPayload()));
      body.append('electricity_eur_per_kwh', String(v.elec));
      body.append('charge_factor', String(v.charge));
      body.append('setup_factor', String(v.setup));
      return fetch('/api/recipe-cost', { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          if (!json || !json.success) {
            throw new Error((json && json.message) || 'Kalkulation fehlgeschlagen');
          }
          paintCalc(json.data || {});
          if (statusEl) statusEl.textContent = '';
        })
        .catch(function (err) {
          if (statusEl) statusEl.textContent = err.message || 'Fehler';
        });
    }
    function schedule() {
      updateLabels();
      clearTimeout(timer);
      timer = setTimeout(postWhatIf, 180);
    }
    function scaleField(el, factor) {
      if (!el) return;
      var n = parseFloat(String(el.value).replace(',', '.'));
      if (!isFinite(n)) n = 0;
      var out = Math.round(n * factor * 10000) / 10000;
      el.value = String(out);
    }
    function applyToForm() {
      var v = sliderVals();
      if (Math.abs(v.charge - 1) > 0.0001) {
        scaleField(form.querySelector('[name="target_qty"]'), v.charge);
        form.querySelectorAll('#dg-recipe-bom tbody tr [name*="[qty]"]').forEach(function (el) {
          scaleField(el, v.charge);
        });
        form.querySelectorAll('#dg-recipe-routing tbody tr [name*="[run_min]"]').forEach(function (el) {
          scaleField(el, v.charge);
        });
      }
      if (Math.abs(v.setup - 1) > 0.0001) {
        form.querySelectorAll('#dg-recipe-routing tbody tr [name*="[setup_min]"]').forEach(function (el) {
          scaleField(el, v.setup);
        });
      }
      var body = new FormData();
      body.append('_csrf', csrf);
      body.append('action', 'apply_electricity');
      body.append('electricity_eur_per_kwh', String(v.elec));
      return fetch('/api/recipe-cost', { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          if (!json || !json.success) {
            throw new Error((json && json.message) || 'Strompreis nicht übernommen');
          }
          box.setAttribute('data-base-elec', String(v.elec));
          var chargeEl = box.querySelector('[data-whatif="charge"]');
          var setupEl = box.querySelector('[data-whatif="setup"]');
          if (chargeEl) chargeEl.value = '1';
          if (setupEl) setupEl.value = '1';
          updateLabels();
          if (statusEl) statusEl.textContent = 'Übernommen in Formular + Stromsatz. Bitte speichern, um das Rezept zu sichern.';
          return postWhatIf();
        })
        .catch(function (err) {
          if (statusEl) statusEl.textContent = err.message || 'Fehler';
        });
    }
    function reset() {
      var base = parseFloat(box.getAttribute('data-base-elec') || '0.3') || 0.3;
      var elecEl = box.querySelector('[data-whatif="elec"]');
      var chargeEl = box.querySelector('[data-whatif="charge"]');
      var setupEl = box.querySelector('[data-whatif="setup"]');
      if (elecEl) elecEl.value = String(Math.round(base * 100) / 100);
      if (chargeEl) chargeEl.value = '1';
      if (setupEl) setupEl.value = '1';
      if (statusEl) statusEl.textContent = '';
      schedule();
    }

    box.querySelectorAll('[data-whatif]').forEach(function (el) {
      el.addEventListener('input', schedule);
    });
    var resetBtn = box.querySelector('[data-whatif-reset]');
    var applyBtn = box.querySelector('[data-whatif-apply]');
    if (resetBtn) resetBtn.addEventListener('click', reset);
    if (applyBtn) applyBtn.addEventListener('click', function () {
      applyBtn.disabled = true;
      applyToForm().finally(function () { applyBtn.disabled = false; });
    });
    updateLabels();
  })();
})();
</script>
<?php endif; ?>
