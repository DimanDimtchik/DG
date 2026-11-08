<?php
/**
 * Rezeptur R4: Wizard „Maschine hinzufügen“ (Alltagssprache).
 *
 * @var array<string, mixed> $workCenterForm
 * @var int|null $workCenterId
 * @var array{electricity_eur_per_kwh: float, room_eur_per_m2_h: float, wage_eur_per_h: float} $recipeCostRates
 * @var list<array<string, mixed>> $machineKnowledgeList
 * @var string $machineKnowledgeErrorFilter
 * @var string|null $formError
 * @var bool $canEdit
 * @var array{type: string, message: string}|null $flash
 */
$isEdit = ($workCenterId ?? 0) > 0;
$readOnly = !($canEdit ?? false);
$form = $workCenterForm ?? WorkCenterRepository::emptyForm();
$rates = $recipeCostRates ?? RecipeCostSettings::get();
$breakdown = RecipeCostService::breakdown($form, $rates);
$knowledgeList = $machineKnowledgeList ?? [];
$knowledgeErrorFilter = trim((string) ($machineKnowledgeErrorFilter ?? ''));
$knowledgeTypes = MachineKnowledgeRepository::contentTypes();
$typeLabels = [
    MachineKnowledgeRepository::CONTENT_MANUAL_PDF => 'Handbuch (PDF)',
    MachineKnowledgeRepository::CONTENT_ACADEMY_MODULE => 'Akademie-Video',
    MachineKnowledgeRepository::CONTENT_EXTERNAL_URL => 'Externe URL',
];
$kw = (float) str_replace(',', '.', (string) ($form['kw'] ?? '0'));
$wattDisplay = $kw > 0 ? rtrim(rtrim(sprintf('%.0f', $kw * 1000), '0'), '.') : '';
if ($wattDisplay === '') {
    $wattDisplay = '0';
}
$steps = [
    1 => ['title' => 'Name', 'hint' => 'Wie heißt die Maschine oder der Arbeitsplatz?'],
    2 => ['title' => 'Preis & Lebensdauer', 'hint' => 'Was hat sie gekostet — und wie lange nutzen Sie sie?'],
    3 => ['title' => 'Strom', 'hint' => 'Wie viel Leistung braucht sie ungefähr?'],
    4 => ['title' => 'Platz & Personal', 'hint' => 'Wie viel Fläche und wie viele Personen?'],
    5 => ['title' => 'Fertig', 'hint' => 'Kurz prüfen — der Stundensatz ist schon berechnet.'],
];
?>
<div class="dg-wrap">
  <?php
    View::partial('partials/back-nav', [
        'href' => '/app?page=rezeptur-maschinen',
        'label' => 'Zurück zu Maschinen',
    ]);
  ?>

  <header class="dg-page-header">
    <h1 class="dg-page-title"><?= $isEdit ? 'Maschine bearbeiten' : 'Maschine hinzufügen' ?></h1>
    <p class="dg-lead">Schritt für Schritt — ohne Fachchinesisch. Den Stundensatz sehen Sie rechts mit.</p>
  </header>

  <?php View::render('partials/flash', compact('flash')); ?>
  <?php if (!empty($formError)) : ?>
    <div class="dg-flash dg-flash--error"><?= View::escape($formError) ?></div>
  <?php endif; ?>

  <div style="display:grid;gap:20px;grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);">
    <form method="post" action="/app?page=rezeptur-maschine-form" class="dg-form" id="dg-workcenter-form" data-wizard="<?= $readOnly ? '0' : '1' ?>">
      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
      <?php if ($isEdit) : ?>
        <input type="hidden" name="id" value="<?= (int) $workCenterId ?>">
      <?php endif; ?>

      <?php if (!$readOnly) : ?>
        <nav class="dg-wc-wizard-steps dg-toolbar dg-mb-4" aria-label="Schritte">
          <?php foreach ($steps as $num => $meta) : ?>
            <button type="button" class="dg-button<?= $num === 1 ? ' dg-button--primary' : '' ?>" data-wizard-goto="<?= (int) $num ?>" style="font-size:0.85rem;">
              <?= (int) $num ?>. <?= View::escape($meta['title']) ?>
            </button>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>

      <div class="dg-wc-wizard-panel" data-wizard-step="1" <?= $readOnly ? '' : '' ?>>
        <h2 class="dg-subsection-title dg-mt-0"><?= View::escape($steps[1]['title']) ?></h2>
        <p class="dg-field-hint"><?= View::escape($steps[1]['hint']) ?></p>
        <label class="dg-field">
          <span>Name der Maschine</span>
          <input name="name" required maxlength="191" value="<?= View::escape((string) ($form['name'] ?? '')) ?>" placeholder="z. B. Rührwerk in der Backstube" <?= $readOnly ? 'readonly' : '' ?>>
        </label>
        <label class="dg-field">
          <span>Notiz (optional)</span>
          <textarea name="notes" rows="2" maxlength="1000" <?= $readOnly ? 'readonly' : '' ?>><?= View::escape((string) ($form['notes'] ?? '')) ?></textarea>
        </label>
      </div>

      <div class="dg-wc-wizard-panel" data-wizard-step="2" <?= $readOnly ? '' : 'hidden' ?>>
        <h2 class="dg-subsection-title dg-mt-0"><?= View::escape($steps[2]['title']) ?></h2>
        <p class="dg-field-hint"><?= View::escape($steps[2]['hint']) ?></p>
        <label class="dg-field">
          <span>Kaufpreis (€)</span>
          <input name="purchase_price" inputmode="decimal" value="<?= View::escape((string) ($form['purchase_price'] ?? '0')) ?>" data-wc-input <?= $readOnly ? 'readonly' : '' ?>>
        </label>
        <label class="dg-field">
          <span>Geplante Laufzeit insgesamt (Stunden)</span>
          <input name="life_hours" id="dg-wc-life-hours" inputmode="decimal" value="<?= View::escape((string) ($form['life_hours'] ?? '10000')) ?>" data-wc-input <?= $readOnly ? 'readonly' : '' ?>>
        </label>
        <?php if (!$readOnly) : ?>
          <div class="dg-card dg-mt-2 dg-mb-3" style="padding:12px;background:color-mix(in srgb, var(--dg-primary, #336) 6%, #fff);">
            <p class="dg-field-hint dg-mt-0 dg-mb-2">Hilfe: lieber in Jahren denken?</p>
            <label class="dg-field">
              <span>Nutzungsdauer in Jahren</span>
              <input type="number" min="0.5" step="0.5" id="dg-wc-life-years" value="5" inputmode="decimal">
            </label>
            <label class="dg-field">
              <span>Stunden pro Jahr (Betrieb)</span>
              <input type="number" min="1" step="1" id="dg-wc-hours-per-year" value="2000" inputmode="decimal">
            </label>
            <button type="button" class="dg-button" id="dg-wc-apply-years">Stunden aus Jahren übernehmen</button>
            <p class="dg-field-hint dg-mt-2">Beispiel: 5 Jahre × 2.000 h = 10.000 Stunden.</p>
          </div>
        <?php endif; ?>
      </div>

      <div class="dg-wc-wizard-panel" data-wizard-step="3" <?= $readOnly ? '' : 'hidden' ?>>
        <h2 class="dg-subsection-title dg-mt-0"><?= View::escape($steps[3]['title']) ?></h2>
        <p class="dg-field-hint"><?= View::escape($steps[3]['hint']) ?> Typenschild oft in Watt — wir rechnen intern in kW.</p>
        <label class="dg-field">
          <span>Leistung in Watt</span>
          <input type="number" min="0" step="1" id="dg-wc-watt" value="<?= View::escape($wattDisplay) ?>" inputmode="decimal" <?= $readOnly ? 'readonly' : '' ?>>
        </label>
        <input type="hidden" name="kw" id="dg-wc-kw" value="<?= View::escape((string) ($form['kw'] ?? '0')) ?>" data-wc-input>
        <p class="dg-field-hint" id="dg-wc-kw-hint">= <?= View::escape((string) ($form['kw'] ?? '0')) ?> kW</p>
      </div>

      <div class="dg-wc-wizard-panel" data-wizard-step="4" <?= $readOnly ? '' : 'hidden' ?>>
        <h2 class="dg-subsection-title dg-mt-0"><?= View::escape($steps[4]['title']) ?></h2>
        <p class="dg-field-hint"><?= View::escape($steps[4]['hint']) ?></p>
        <label class="dg-field">
          <span>Stellfläche (Quadratmeter)</span>
          <input name="space_m2" inputmode="decimal" value="<?= View::escape((string) ($form['space_m2'] ?? '0')) ?>" data-wc-input <?= $readOnly ? 'readonly' : '' ?>>
        </label>
        <label class="dg-field">
          <span>Wie viele Personen bedienen die Maschine?</span>
          <input name="operators" inputmode="decimal" value="<?= View::escape((string) ($form['operators'] ?? '1')) ?>" data-wc-input <?= $readOnly ? 'readonly' : '' ?>>
        </label>
        <label class="dg-field dg-field--checkbox dg-field--inline">
          <span>
            <input type="checkbox" name="is_active" value="1"<?= !empty($form['is_active']) ? ' checked' : '' ?> <?= $readOnly ? 'disabled' : '' ?>> In der Auswahl aktiv (für Rezepte nutzbar)
          </span>
        </label>
      </div>

      <div class="dg-wc-wizard-panel" data-wizard-step="5" <?= $readOnly ? '' : 'hidden' ?>>
        <h2 class="dg-subsection-title dg-mt-0"><?= View::escape($steps[5]['title']) ?></h2>
        <p class="dg-field-hint"><?= View::escape($steps[5]['hint']) ?></p>
        <dl id="dg-wc-summary" class="dg-mt-0 dg-mb-0 dg-gap-2" style="display:grid;">
          <div><dt style="font-size:12px;color:#666;">Name</dt><dd data-sum="name" class="dg-mt-0 dg-mb-0" style="font-weight:600;">—</dd></div>
          <div><dt style="font-size:12px;color:#666;">Kaufpreis</dt><dd data-sum="price" class="dg-mt-0 dg-mb-0">—</dd></div>
          <div><dt style="font-size:12px;color:#666;">Laufzeit</dt><dd data-sum="life" class="dg-mt-0 dg-mb-0">—</dd></div>
          <div><dt style="font-size:12px;color:#666;">Leistung</dt><dd data-sum="power" class="dg-mt-0 dg-mb-0">—</dd></div>
          <div><dt style="font-size:12px;color:#666;">Fläche / Personal</dt><dd data-sum="space" class="dg-mt-0 dg-mb-0">—</dd></div>
        </dl>
        <?php if ($readOnly) : ?>
          <p class="dg-field-hint dg-mt-3">Nur Ansicht — keine Bearbeitung.</p>
        <?php endif; ?>
      </div>

      <?php if (!$readOnly) : ?>
        <div class="dg-form-actions">
          <button type="button" class="dg-button" data-wizard-prev hidden>Zurück</button>
          <button type="button" class="dg-button dg-button--primary" data-wizard-next>Weiter</button>
          <button type="submit" class="dg-button dg-button--primary" name="work_center_save" value="1" data-wizard-save hidden>Maschine speichern</button>
          <?php if ($isEdit) : ?>
            <button type="submit" class="dg-button dg-button--danger" name="work_center_delete" value="1">Löschen</button>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </form>

    <aside class="dg-card" style="padding:16px;align-self:start;" id="dg-wc-rate-card"
      data-elec="<?= View::escape((string) $rates['electricity_eur_per_kwh']) ?>"
      data-room="<?= View::escape((string) $rates['room_eur_per_m2_h']) ?>"
      data-wage="<?= View::escape((string) $rates['wage_eur_per_h']) ?>">
      <h2 class="dg-subsection-title dg-mt-0">Ihr Stundensatz</h2>
      <p class="dg-field-hint">Wird live mitgerechnet — Speichern erst am Schluss nötig.</p>
      <dl class="dg-mt-0 dg-mb-0 dg-gap-2" style="display:grid;">
        <div><dt style="font-size:12px;color:#666;">Abnutzung / Stunde</dt><dd id="dg-wc-dep" class="dg-mt-0 dg-mb-0" style="font-weight:600;"><?= View::escape(RecipeCostService::formatEur($breakdown['depreciation_per_h'])) ?></dd></div>
        <div><dt style="font-size:12px;color:#666;">Strom / Stunde</dt><dd id="dg-wc-energy" class="dg-mt-0 dg-mb-0" style="font-weight:600;"><?= View::escape(RecipeCostService::formatEur($breakdown['energy_per_h'])) ?></dd></div>
        <div><dt style="font-size:12px;color:#666;">Raum / Stunde</dt><dd id="dg-wc-room" class="dg-mt-0 dg-mb-0" style="font-weight:600;"><?= View::escape(RecipeCostService::formatEur($breakdown['room_per_h'])) ?></dd></div>
        <div style="border-top:1px solid var(--dg-border,#ddd);padding-top:8px;"><dt style="font-size:12px;color:#666;">Maschinenstunde</dt><dd id="dg-wc-machine" class="dg-mt-0 dg-mb-0" style="font-size:1.25rem;font-weight:700;"><?= View::escape(RecipeCostService::formatEur($breakdown['machine_per_h'])) ?></dd></div>
        <div><dt style="font-size:12px;color:#666;">Personal / Stunde</dt><dd id="dg-wc-labor" class="dg-mt-0 dg-mb-0" style="font-weight:600;"><?= View::escape(RecipeCostService::formatEur($breakdown['labor_per_h'])) ?></dd></div>
        <div><dt style="font-size:12px;color:#666;">Maschine + Personal</dt><dd id="dg-wc-loaded" class="dg-mt-0 dg-mb-0" style="font-weight:700;"><?= View::escape(RecipeCostService::formatEur($breakdown['loaded_per_h'])) ?></dd></div>
      </dl>
    </aside>
  </div>

  <?php if ($isEdit) : ?>
    <section class="dg-panel dg-mt-5" id="dg-machine-knowledge">
      <h2 class="dg-subsection-title dg-mt-0">Wissen & Störungen</h2>
      <p class="dg-field-hint">
        Handbuch, kurzes Akademie-Video oder Link zu einer Störung.
        PDF nur unter <code>storage/</code>. Videos kurz halten (wenige Minuten), mit Untertiteln.
      </p>

      <form method="get" action="/app" class="dg-form dg-mb-4">
        <input type="hidden" name="page" value="rezeptur-maschine-form">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" value="<?= (int) $workCenterId ?>">
        <label class="dg-field">
          <span>Fehlercode suchen</span>
          <input type="text" name="knowledge_error" value="<?= View::escape($knowledgeErrorFilter) ?>" placeholder="z. B. ERR_HYDRAULIK_404">
        </label>
        <div class="dg-form-actions">
          <button type="submit" class="dg-button dg-button--small">Suchen</button>
          <?php if ($knowledgeErrorFilter !== '') : ?>
            <a class="dg-button dg-button--small dg-button--secondary" href="/app?page=rezeptur-maschine-form&amp;action=edit&amp;id=<?= (int) $workCenterId ?>#dg-machine-knowledge">Zurücksetzen</a>
          <?php endif; ?>
        </div>
      </form>

      <div class="dg-table-wrap dg-mb-4">
        <table class="dg-table dg-table--compact">
          <thead>
            <tr>
              <th>Titel</th>
              <th>Art</th>
              <th>Fehlercodes</th>
              <th>Aktiv</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($knowledgeList === []) : ?>
              <tr><td colspan="5" class="dg-muted"><?= $knowledgeErrorFilter !== '' ? 'Keine Treffer für diesen Fehlercode.' : 'Noch keine Einträge.' ?></td></tr>
            <?php else : ?>
              <?php foreach ($knowledgeList as $kRow) : ?>
                <?php
                  $kType = (string) ($kRow['content_type'] ?? '');
                  $kCodes = is_array($kRow['error_codes'] ?? null) ? $kRow['error_codes'] : [];
                  $kPath = trim((string) ($kRow['storage_path'] ?? ''));
                ?>
                <tr>
                  <td>
                    <?= View::escape((string) ($kRow['title'] ?? '')) ?>
                    <?php if ($kPath !== '') : ?>
                      <br><small class="dg-muted"><?= View::escape($kPath) ?></small>
                    <?php endif; ?>
                    <?php if (!empty($kRow['academy_module_id'])) : ?>
                      <br><small class="dg-muted">Modul #<?= (int) $kRow['academy_module_id'] ?></small>
                    <?php endif; ?>
                  </td>
                  <td><?= View::escape($typeLabels[$kType] ?? $kType) ?></td>
                  <td><?= View::escape($kCodes === [] ? '—' : implode(', ', $kCodes)) ?></td>
                  <td><?= !empty($kRow['is_active']) ? 'ja' : 'nein' ?></td>
                  <td class="dg-table__actions">
                    <?php if (!$readOnly && !empty($kRow['is_active'])) : ?>
                      <div class="dg-table__actions-group">
                        <form method="post" action="/app?page=rezeptur-maschine-form" class="dg-inline-form">
                          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                          <input type="hidden" name="work_center_id" value="<?= (int) $workCenterId ?>">
                          <input type="hidden" name="machine_knowledge_id" value="<?= (int) ($kRow['id'] ?? 0) ?>">
                          <button type="submit" name="machine_knowledge_deactivate" value="1" class="dg-button dg-button--small">Deaktivieren</button>
                        </form>
                      </div>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if (!$readOnly) : ?>
        <h3 class="dg-subsection-title">Eintrag hinzufügen</h3>
        <form method="post" action="/app?page=rezeptur-maschine-form" class="dg-form" id="dg-machine-knowledge-form">
          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
          <input type="hidden" name="work_center_id" value="<?= (int) $workCenterId ?>">
          <input type="hidden" name="is_active" value="1">
          <label class="dg-field dg-field--wide">
            <span>Titel</span>
            <input type="text" name="title" required maxlength="191" placeholder="z. B. Hydraulik-Druckabfall">
          </label>
          <label class="dg-field">
            <span>Art</span>
            <select name="content_type" id="dg-knowledge-content-type" required>
              <?php foreach ($knowledgeTypes as $typeKey) : ?>
                <option value="<?= View::escape($typeKey) ?>"><?= View::escape($typeLabels[$typeKey] ?? $typeKey) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="dg-field dg-field--wide" data-knowledge-field="path">
            <span>PDF-Pfad oder URL</span>
            <input type="text" name="storage_path" maxlength="500" placeholder="storage/… oder https://…">
            <span class="dg-field-hint">Handbuch: Pfad muss mit <code>storage/</code> beginnen.</span>
          </label>
          <label class="dg-field" data-knowledge-field="academy">
            <span>Akademie-Modul-ID</span>
            <input type="number" name="academy_module_id" min="1" step="1" placeholder="ID">
          </label>
          <label class="dg-field">
            <span>Videolänge (Sekunden, optional)</span>
            <input type="number" name="video_duration_seconds" min="0" step="1" placeholder="z. B. 180">
          </label>
          <label class="dg-field dg-field--wide">
            <span>Fehlercodes (Komma getrennt)</span>
            <input type="text" name="error_codes" placeholder="ERR_HYDRAULIK_404, E42">
          </label>
          <div class="dg-form-actions">
            <button type="submit" class="dg-button dg-button--primary" name="machine_knowledge_save" value="1">Wissen speichern</button>
          </div>
        </form>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>

<?php if (!$readOnly) : ?>
<script>
(function () {
  var form = document.getElementById('dg-workcenter-form');
  var card = document.getElementById('dg-wc-rate-card');
  if (!form || !card || form.getAttribute('data-wizard') !== '1') return;

  var elec = parseFloat(card.getAttribute('data-elec') || '0') || 0;
  var room = parseFloat(card.getAttribute('data-room') || '0') || 0;
  var wage = parseFloat(card.getAttribute('data-wage') || '0') || 0;
  var step = 1;
  var maxStep = 5;

  var wattEl = document.getElementById('dg-wc-watt');
  var kwEl = document.getElementById('dg-wc-kw');
  var kwHint = document.getElementById('dg-wc-kw-hint');
  var lifeHours = document.getElementById('dg-wc-life-hours');
  var lifeYears = document.getElementById('dg-wc-life-years');
  var hoursPerYear = document.getElementById('dg-wc-hours-per-year');
  var applyYears = document.getElementById('dg-wc-apply-years');

  function num(name) {
    var el = form.querySelector('[name="' + name + '"]');
    if (!el) return 0;
    var v = String(el.value || '').replace(',', '.').trim();
    var n = parseFloat(v);
    return isFinite(n) ? n : 0;
  }
  function fmt(n) {
    return n.toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 4 }) + ' €';
  }
  function syncWattToKw() {
    if (!wattEl || !kwEl) return;
    var watt = parseFloat(String(wattEl.value || '0').replace(',', '.')) || 0;
    var kw = watt / 1000;
    kwEl.value = String(Math.round(kw * 1000) / 1000);
    if (kwHint) kwHint.textContent = '= ' + kwEl.value + ' kW';
  }
  function recalc() {
    syncWattToKw();
    var purchase = num('purchase_price');
    var life = num('life_hours');
    if (life <= 0) life = 1;
    var kw = num('kw');
    var space = num('space_m2');
    var operators = num('operators');
    var dep = purchase / life;
    var energy = kw * elec;
    var roomCost = space * room;
    var machine = dep + energy + roomCost;
    var labor = operators * wage;
    document.getElementById('dg-wc-dep').textContent = fmt(dep);
    document.getElementById('dg-wc-energy').textContent = fmt(energy);
    document.getElementById('dg-wc-room').textContent = fmt(roomCost);
    document.getElementById('dg-wc-machine').textContent = fmt(machine);
    document.getElementById('dg-wc-labor').textContent = fmt(labor);
    document.getElementById('dg-wc-loaded').textContent = fmt(machine + labor);
    updateSummary();
  }
  function updateSummary() {
    var set = function (key, val) {
      var el = form.querySelector('[data-sum="' + key + '"]');
      if (el) el.textContent = val;
    };
    var name = (form.querySelector('[name="name"]') || {}).value || '—';
    set('name', name.trim() || '—');
    set('price', fmt(num('purchase_price')));
    set('life', (num('life_hours') || 0).toLocaleString('de-DE') + ' Stunden');
    var watt = parseFloat(String((wattEl && wattEl.value) || '0').replace(',', '.')) || 0;
    set('power', watt.toLocaleString('de-DE') + ' W (' + (num('kw')).toLocaleString('de-DE') + ' kW)');
    set('space', (num('space_m2') || 0).toLocaleString('de-DE') + ' m² · ' + (num('operators') || 0).toLocaleString('de-DE') + ' Person(en)');
  }
  function showStep(n) {
    step = Math.max(1, Math.min(maxStep, n));
    form.querySelectorAll('[data-wizard-step]').forEach(function (panel) {
      var s = parseInt(panel.getAttribute('data-wizard-step'), 10);
      panel.hidden = s !== step;
    });
    form.querySelectorAll('[data-wizard-goto]').forEach(function (btn) {
      var s = parseInt(btn.getAttribute('data-wizard-goto'), 10);
      btn.classList.toggle('dg-button--primary', s === step);
    });
    var prev = form.querySelector('[data-wizard-prev]');
    var next = form.querySelector('[data-wizard-next]');
    var save = form.querySelector('[data-wizard-save]');
    if (prev) prev.hidden = step <= 1;
    if (next) next.hidden = step >= maxStep;
    if (save) save.hidden = step < maxStep;
    updateSummary();
  }

  if (applyYears && lifeHours && lifeYears && hoursPerYear) {
    applyYears.addEventListener('click', function () {
      var y = parseFloat(String(lifeYears.value || '0').replace(',', '.')) || 0;
      var h = parseFloat(String(hoursPerYear.value || '0').replace(',', '.')) || 0;
      if (y > 0 && h > 0) {
        lifeHours.value = String(Math.round(y * h * 100) / 100);
        recalc();
      }
    });
  }
  if (wattEl) wattEl.addEventListener('input', recalc);
  form.addEventListener('input', function (e) {
    if (e.target && (e.target.matches('[data-wc-input]') || e.target.name === 'name')) recalc();
  });
  form.querySelectorAll('[data-wizard-goto]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      showStep(parseInt(btn.getAttribute('data-wizard-goto'), 10) || 1);
    });
  });
  var prevBtn = form.querySelector('[data-wizard-prev]');
  var nextBtn = form.querySelector('[data-wizard-next]');
  if (prevBtn) prevBtn.addEventListener('click', function () { showStep(step - 1); });
  if (nextBtn) nextBtn.addEventListener('click', function () {
    if (step === 1) {
      var name = (form.querySelector('[name="name"]') || {}).value || '';
      if (!String(name).trim()) {
        window.alert('Bitte zuerst einen Namen eingeben.');
        return;
      }
    }
    showStep(step + 1);
  });
  form.addEventListener('submit', function (e) {
    syncWattToKw();
    var del = e.submitter && e.submitter.name === 'work_center_delete';
    if (del && !window.confirm('Maschine / Arbeitsplatz wirklich löschen?')) {
      e.preventDefault();
    }
  });

  showStep(1);
  recalc();
})();
</script>
<?php endif; ?>

<?php if ($isEdit && !$readOnly) : ?>
<script>
(function () {
  var typeEl = document.getElementById('dg-knowledge-content-type');
  if (!typeEl) return;
  function syncKnowledgeFields() {
    var t = String(typeEl.value || '');
    document.querySelectorAll('[data-knowledge-field="path"]').forEach(function (el) {
      el.hidden = (t === 'academy_module');
    });
    document.querySelectorAll('[data-knowledge-field="academy"]').forEach(function (el) {
      el.hidden = (t !== 'academy_module');
    });
  }
  typeEl.addEventListener('change', syncKnowledgeFields);
  syncKnowledgeFields();
})();
</script>
<?php endif; ?>
