<?php
/**
 * Rezeptur R4: Wizard „Maschine hinzufügen“ (Alltagssprache).
 *
 * @var array<string, mixed> $workCenterForm
 * @var int|null $workCenterId
 * @var array{electricity_eur_per_kwh: float, room_eur_per_m2_h: float, wage_eur_per_h: float} $recipeCostRates
 * @var string|null $formError
 * @var bool $canEdit
 * @var array{type: string, message: string}|null $flash
 */
$isEdit = ($workCenterId ?? 0) > 0;
$readOnly = !($canEdit ?? false);
$form = $workCenterForm ?? WorkCenterRepository::emptyForm();
$rates = $recipeCostRates ?? RecipeCostSettings::get();
$breakdown = RecipeCostService::breakdown($form, $rates);
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
        <nav class="dg-wc-wizard-steps" aria-label="Schritte" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;">
          <?php foreach ($steps as $num => $meta) : ?>
            <button type="button" class="dg-button<?= $num === 1 ? ' dg-button--primary' : '' ?>" data-wizard-goto="<?= (int) $num ?>" style="font-size:0.85rem;">
              <?= (int) $num ?>. <?= View::escape($meta['title']) ?>
            </button>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>

      <div class="dg-wc-wizard-panel" data-wizard-step="1" <?= $readOnly ? '' : '' ?>>
        <h2 class="dg-subsection-title" style="margin-top:0;"><?= View::escape($steps[1]['title']) ?></h2>
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
        <h2 class="dg-subsection-title" style="margin-top:0;"><?= View::escape($steps[2]['title']) ?></h2>
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
          <div class="dg-card" style="padding:12px;margin:8px 0 12px;background:color-mix(in srgb, var(--dg-primary, #336) 6%, #fff);">
            <p class="dg-field-hint" style="margin:0 0 8px;">Hilfe: lieber in Jahren denken?</p>
            <label class="dg-field">
              <span>Nutzungsdauer in Jahren</span>
              <input type="number" min="0.5" step="0.5" id="dg-wc-life-years" value="5" inputmode="decimal">
            </label>
            <label class="dg-field">
              <span>Stunden pro Jahr (Betrieb)</span>
              <input type="number" min="1" step="1" id="dg-wc-hours-per-year" value="2000" inputmode="decimal">
            </label>
            <button type="button" class="dg-button" id="dg-wc-apply-years">Stunden aus Jahren übernehmen</button>
            <p class="dg-field-hint" style="margin:8px 0 0;">Beispiel: 5 Jahre × 2.000 h = 10.000 Stunden.</p>
          </div>
        <?php endif; ?>
      </div>

      <div class="dg-wc-wizard-panel" data-wizard-step="3" <?= $readOnly ? '' : 'hidden' ?>>
        <h2 class="dg-subsection-title" style="margin-top:0;"><?= View::escape($steps[3]['title']) ?></h2>
        <p class="dg-field-hint"><?= View::escape($steps[3]['hint']) ?> Typenschild oft in Watt — wir rechnen intern in kW.</p>
        <label class="dg-field">
          <span>Leistung in Watt</span>
          <input type="number" min="0" step="1" id="dg-wc-watt" value="<?= View::escape($wattDisplay) ?>" inputmode="decimal" <?= $readOnly ? 'readonly' : '' ?>>
        </label>
        <input type="hidden" name="kw" id="dg-wc-kw" value="<?= View::escape((string) ($form['kw'] ?? '0')) ?>" data-wc-input>
        <p class="dg-field-hint" id="dg-wc-kw-hint">= <?= View::escape((string) ($form['kw'] ?? '0')) ?> kW</p>
      </div>

      <div class="dg-wc-wizard-panel" data-wizard-step="4" <?= $readOnly ? '' : 'hidden' ?>>
        <h2 class="dg-subsection-title" style="margin-top:0;"><?= View::escape($steps[4]['title']) ?></h2>
        <p class="dg-field-hint"><?= View::escape($steps[4]['hint']) ?></p>
        <label class="dg-field">
          <span>Stellfläche (Quadratmeter)</span>
          <input name="space_m2" inputmode="decimal" value="<?= View::escape((string) ($form['space_m2'] ?? '0')) ?>" data-wc-input <?= $readOnly ? 'readonly' : '' ?>>
        </label>
        <label class="dg-field">
          <span>Wie viele Personen bedienen die Maschine?</span>
          <input name="operators" inputmode="decimal" value="<?= View::escape((string) ($form['operators'] ?? '1')) ?>" data-wc-input <?= $readOnly ? 'readonly' : '' ?>>
        </label>
        <label class="dg-field dg-field--inline">
          <span>
            <input type="checkbox" name="is_active" value="1"<?= !empty($form['is_active']) ? ' checked' : '' ?> <?= $readOnly ? 'disabled' : '' ?>> In der Auswahl aktiv (für Rezepte nutzbar)
          </span>
        </label>
      </div>

      <div class="dg-wc-wizard-panel" data-wizard-step="5" <?= $readOnly ? '' : 'hidden' ?>>
        <h2 class="dg-subsection-title" style="margin-top:0;"><?= View::escape($steps[5]['title']) ?></h2>
        <p class="dg-field-hint"><?= View::escape($steps[5]['hint']) ?></p>
        <dl id="dg-wc-summary" style="margin:0;display:grid;gap:8px;">
          <div><dt style="font-size:12px;color:#666;">Name</dt><dd data-sum="name" style="margin:0;font-weight:600;">—</dd></div>
          <div><dt style="font-size:12px;color:#666;">Kaufpreis</dt><dd data-sum="price" style="margin:0;">—</dd></div>
          <div><dt style="font-size:12px;color:#666;">Laufzeit</dt><dd data-sum="life" style="margin:0;">—</dd></div>
          <div><dt style="font-size:12px;color:#666;">Leistung</dt><dd data-sum="power" style="margin:0;">—</dd></div>
          <div><dt style="font-size:12px;color:#666;">Fläche / Personal</dt><dd data-sum="space" style="margin:0;">—</dd></div>
        </dl>
        <?php if ($readOnly) : ?>
          <p class="dg-field-hint" style="margin-top:12px;">Nur Ansicht — keine Bearbeitung.</p>
        <?php endif; ?>
      </div>

      <?php if (!$readOnly) : ?>
        <div class="dg-form-actions" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
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
      <h2 class="dg-subsection-title" style="margin-top:0;">Ihr Stundensatz</h2>
      <p class="dg-field-hint">Wird live mitgerechnet — Speichern erst am Schluss nötig.</p>
      <dl style="margin:0;display:grid;gap:8px;">
        <div><dt style="font-size:12px;color:#666;">Abnutzung / Stunde</dt><dd id="dg-wc-dep" style="margin:0;font-weight:600;"><?= View::escape(RecipeCostService::formatEur($breakdown['depreciation_per_h'])) ?></dd></div>
        <div><dt style="font-size:12px;color:#666;">Strom / Stunde</dt><dd id="dg-wc-energy" style="margin:0;font-weight:600;"><?= View::escape(RecipeCostService::formatEur($breakdown['energy_per_h'])) ?></dd></div>
        <div><dt style="font-size:12px;color:#666;">Raum / Stunde</dt><dd id="dg-wc-room" style="margin:0;font-weight:600;"><?= View::escape(RecipeCostService::formatEur($breakdown['room_per_h'])) ?></dd></div>
        <div style="border-top:1px solid var(--dg-border,#ddd);padding-top:8px;"><dt style="font-size:12px;color:#666;">Maschinenstunde</dt><dd id="dg-wc-machine" style="margin:0;font-size:1.25rem;font-weight:700;"><?= View::escape(RecipeCostService::formatEur($breakdown['machine_per_h'])) ?></dd></div>
        <div><dt style="font-size:12px;color:#666;">Personal / Stunde</dt><dd id="dg-wc-labor" style="margin:0;font-weight:600;"><?= View::escape(RecipeCostService::formatEur($breakdown['labor_per_h'])) ?></dd></div>
        <div><dt style="font-size:12px;color:#666;">Maschine + Personal</dt><dd id="dg-wc-loaded" style="margin:0;font-weight:700;"><?= View::escape(RecipeCostService::formatEur($breakdown['loaded_per_h'])) ?></dd></div>
      </dl>
    </aside>
  </div>
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
