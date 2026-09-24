<?php
/**
 * Anschaffungsrechner — Barkauf / Ratenkauf / Leasing / Miete.
 *
 * @var array<string, mixed> $acqInput
 * @var array<string, mixed>|null $acqResult
 * @var array<string, mixed> $acqCompany
 * @var array<string, string> $acqAreas
 * @var list<array<string, mixed>> $acqPresets
 * @var array{type: string, message: string}|null $flash
 */
$input = is_array($acqInput ?? null) ? $acqInput : [];
$result = is_array($acqResult ?? null) ? $acqResult : null;
$company = is_array($acqCompany ?? null) ? $acqCompany : [];
$areas = is_array($acqAreas ?? null) ? $acqAreas : AfaCatalog::areas();
$presets = is_array($acqPresets ?? null) ? $acqPresets : AfaCatalog::presets();
$fmt = static fn (float $v): string => number_format($v, 2, ',', '.') . ' €';
$fmtPct = static fn (float $v): string => number_format($v * 100, 2, ',', '.') . ' %';
$meta = is_array($result['meta'] ?? null) ? $result['meta'] : [];
$models = is_array($result['models'] ?? null) ? $result['models'] : [];
?>
<div class="dg-wrap dg-buchhaltung-anschaffungsrechner">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header">
    <div>
      <h1 class="dg-page-title">Anschaffungsrechner</h1>
      <p class="dg-lead">Barkauf, Ratenkauf, Leasing und Miete vergleichen — inkl. vereinfachter USt-, GewSt- und KSt-/ESt-Wirkung aus den Firmendaten.</p>
    </div>
  </header>

  <p class="dg-alert dg-alert--info">
    Orientierungshilfe, keine Steuerberatung. Anlagetypen und AfA-Jahre angelehnt an Lexoffice/Lexware Office.
    IT-Hardware: 1 Jahr / Sofortabschreibung (seit 2021) möglich. GWG bis <?= View::escape(number_format(AfaCatalog::GWG_NETTO_LIMIT, 0, ',', '.')) ?> € Netto Sofortabschreibung.
    Steuersätze unter Einstellungen → Firma (Hebesatz, KSt, SolZ, ESt-Grenzsatz).
  </p>

  <form class="dg-panel" method="get" action="/app" id="dg-acq-form">
    <input type="hidden" name="page" value="buchhaltung-anschaffungsrechner">
    <input type="hidden" name="calc" value="1">

    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Gegenstandsbereich (Anlagetyp)</span>
        <select name="area" id="dg-acq-area">
          <?php
            $areaHints = AfaCatalog::areaHints();
            foreach ($areas as $areaId => $areaLabel) :
                ?>
            <option
              value="<?= View::escape($areaId) ?>"
              data-hint="<?= View::escape((string) ($areaHints[$areaId] ?? '')) ?>"
              <?= ($input['area'] ?? 'it') === $areaId ? ' selected' : '' ?>
            ><?= View::escape($areaLabel) ?></option>
          <?php endforeach; ?>
        </select>
        <small class="dg-field-hint" id="dg-acq-area-hint"><?= View::escape((string) ($areaHints[$input['area'] ?? 'it'] ?? $areaHints['it'] ?? '')) ?></small>
      </label>
      <label class="dg-field">
        <span>Gerät / Preset</span>
        <select name="preset" id="dg-acq-preset">
          <?php foreach ($presets as $p) : ?>
            <option
              value="<?= View::escape((string) $p['id']) ?>"
              data-area="<?= View::escape((string) $p['area']) ?>"
              data-net="<?= View::escape((string) $p['default_net']) ?>"
              data-life="<?= View::escape((string) $p['useful_life_years']) ?>"
              data-hint="<?= View::escape((string) ($p['hint'] ?? '')) ?>"
              <?= ($input['preset'] ?? 'pc') === $p['id'] ? ' selected' : '' ?>
            ><?= View::escape((string) $p['label']) ?> (<?= (int) $p['useful_life_years'] ?> J.)</option>
          <?php endforeach; ?>
        </select>
        <small class="dg-field-hint" id="dg-acq-preset-hint"></small>
      </label>
      <label class="dg-field">
        <span>Netto (€)</span>
        <input type="number" name="net" id="dg-acq-net" min="0" step="0.01" value="<?= View::escape((string) ($input['net'] ?? '1200')) ?>">
      </label>
      <label class="dg-field">
        <span>USt-Satz (%)</span>
        <select name="vat_rate">
          <?php foreach ([19.0, 7.0, 0.0] as $vr) : ?>
            <option value="<?= $vr ?>"<?= (float) ($input['vat_rate'] ?? 19) === $vr ? ' selected' : '' ?>><?= number_format($vr, 0) ?> %</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field">
        <span>Nutzungsdauer (Jahre)</span>
        <input type="number" name="useful_life_years" id="dg-acq-life" min="1" max="50" value="<?= View::escape((string) ($input['useful_life_years'] ?? '1')) ?>">
        <small class="dg-field-hint">Gebäude bis 50 Jahre; IT typisch 1 Jahr.</small>
      </label>
      <label class="dg-field">
        <span>Nicht abziehbarer Anteil (0–1)</span>
        <input type="number" name="non_deductible_share" min="0" max="1" step="0.05" value="<?= View::escape((string) ($input['non_deductible_share'] ?? '0')) ?>">
        <small class="dg-field-hint">z. B. Privatanteil Pkw — keine 1%-Regelung.</small>
      </label>
      <label class="dg-field">
        <span>Anzahlung Ratenkauf (€)</span>
        <input type="number" name="down_payment" min="0" step="0.01" value="<?= View::escape((string) ($input['down_payment'] ?? '0')) ?>">
      </label>
      <label class="dg-field">
        <span>Laufzeit (Monate)</span>
        <input type="number" name="term_months" min="1" max="120" value="<?= View::escape((string) ($input['term_months'] ?? '36')) ?>">
      </label>
      <label class="dg-field">
        <span>Zins Ratenkauf p.a. (%)</span>
        <input type="number" name="interest_pa" min="0" max="30" step="0.1" value="<?= View::escape((string) ($input['interest_pa'] ?? '6')) ?>">
      </label>
      <label class="dg-field">
        <span>Leasingrate / Monat (€ brutto)</span>
        <input type="number" name="lease_rate" min="0" step="0.01" value="<?= View::escape((string) ($input['lease_rate'] ?? '0')) ?>">
        <small class="dg-field-hint">0 = Schätzwert aus Netto/Laufzeit.</small>
      </label>
      <label class="dg-field">
        <span>Mietrate / Monat (€ brutto)</span>
        <input type="number" name="rent_rate" min="0" step="0.01" value="<?= View::escape((string) ($input['rent_rate'] ?? '0')) ?>">
      </label>
      <label class="dg-field">
        <span>Leasing-Restwert (€)</span>
        <input type="number" name="residual" min="0" step="0.01" value="<?= View::escape((string) ($input['residual'] ?? '0')) ?>">
      </label>
    </div>

    <div class="dg-form-actions">
      <button type="submit" class="dg-button dg-button--primary">Vergleich berechnen</button>
    </div>
  </form>

  <?php if ($result !== null) : ?>
    <section class="dg-panel">
      <h2 class="dg-subsection-title">Firmendaten &amp; Steuersätze</h2>
      <p>
        Rechtsform: <strong><?= View::escape((string) ($company['company_type_label'] ?? $meta['company_type'] ?: '—')) ?></strong>
        · Regime: <?= View::escape((string) ($meta['tax_regime_label'] ?? '')) ?>
        · Vorsteuer: <?= !empty($meta['vat_deductible']) ? 'ja' : 'nein (§19)' ?>
        <?php if (!empty($meta['is_gwg'])) : ?> · <strong>GWG</strong><?php endif; ?>
      </p>
      <p class="dg-field-hint">
        Effektiver Satz gesamt <?= $fmtPct((float) ($meta['effective']['total'] ?? 0)) ?>
        (GewSt <?= $fmtPct((float) ($meta['effective']['gewst'] ?? 0)) ?>,
        Ertrag <?= $fmtPct((float) ($meta['effective']['income_tax'] ?? 0)) ?>)
        · Horizont <?= (int) ($meta['horizon_years'] ?? 0) ?> Jahre
        · Brutto <?= $fmt((float) ($meta['gross'] ?? 0)) ?>
      </p>
    </section>

    <section class="dg-acq-cards">
      <?php foreach (['barkauf', 'ratenkauf', 'leasing', 'miete'] as $key) :
          $m = is_array($models[$key] ?? null) ? $models[$key] : null;
          if ($m === null) {
              continue;
          }
          $t = is_array($m['totals'] ?? null) ? $m['totals'] : [];
          ?>
        <article class="dg-panel dg-acq-card">
          <h3 class="dg-subsection-title"><?= View::escape((string) ($m['label'] ?? $key)) ?></h3>
          <?php if (!empty($m['note'])) : ?>
            <p class="dg-field-hint"><?= View::escape((string) $m['note']) ?></p>
          <?php endif; ?>
          <dl class="dg-acq-dl">
            <div><dt>Cash (Summe)</dt><dd><?= $fmt((float) ($t['cash'] ?? 0)) ?></dd></div>
            <div><dt>Vorsteuer / VSt-Effekt</dt><dd><?= $fmt((float) ($t['vat_cash'] ?? 0)) ?></dd></div>
            <div><dt>Betriebsaufwand</dt><dd><?= $fmt((float) ($t['expense'] ?? 0)) ?></dd></div>
            <div><dt>Steuerentlastung GewSt</dt><dd><?= $fmt((float) ($t['tax_gewst'] ?? 0)) ?></dd></div>
            <div><dt>Steuerentlastung KSt/ESt</dt><dd><?= $fmt((float) ($t['tax_income'] ?? 0)) ?></dd></div>
            <div><dt>Steuerentlastung gesamt</dt><dd><?= $fmt((float) ($t['tax_relief'] ?? 0)) ?></dd></div>
            <div class="dg-acq-dl__total"><dt>Nettobelastung nach Steuer</dt><dd><?= $fmt((float) ($t['net_burden'] ?? 0)) ?></dd></div>
          </dl>
          <?php if (isset($m['monthly_rate'])) : ?>
            <p class="dg-field-hint">Monatsrate ca. <?= $fmt((float) $m['monthly_rate']) ?></p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </section>

    <?php
    $firstModel = $models['barkauf'] ?? reset($models);
    $yearRows = is_array($firstModel['years'] ?? null) ? $firstModel['years'] : [];
    ?>
    <?php if ($yearRows !== []) : ?>
      <section class="dg-panel">
        <h2 class="dg-subsection-title">Jahresübersicht (Nettobelastung)</h2>
        <div class="dg-table-wrap">
          <table class="dg-table">
            <thead>
              <tr>
                <th>Jahr</th>
                <?php foreach (['barkauf' => 'Barkauf', 'ratenkauf' => 'Ratenkauf', 'leasing' => 'Leasing', 'miete' => 'Miete'] as $k => $lab) : ?>
                  <th class="dg-table__num"><?= View::escape($lab) ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($yearRows as $idx => $_) : ?>
                <tr>
                  <td><?= (int) ($idx + 1) ?></td>
                  <?php foreach (['barkauf', 'ratenkauf', 'leasing', 'miete'] as $k) :
                      $yr = is_array($models[$k]['years'][$idx] ?? null) ? $models[$k]['years'][$idx] : [];
                      ?>
                    <td class="dg-table__num"><?= $fmt((float) ($yr['net_burden'] ?? 0)) ?></td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
              <tr class="dg-table__total">
                <td>Summe</td>
                <?php foreach (['barkauf', 'ratenkauf', 'leasing', 'miete'] as $k) : ?>
                  <td class="dg-table__num"><?= $fmt((float) ($models[$k]['totals']['net_burden'] ?? 0)) ?></td>
                <?php endforeach; ?>
              </tr>
            </tbody>
          </table>
        </div>
        <p class="dg-field-hint"><?= View::escape((string) ($meta['disclaimer'] ?? '')) ?></p>
      </section>
    <?php endif; ?>
  <?php endif; ?>
</div>

<style>
.dg-acq-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
  margin: 16px 0;
}
.dg-acq-dl {
  margin: 0;
  display: grid;
  gap: 8px;
}
.dg-acq-dl > div {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  font-size: 14px;
}
.dg-acq-dl dt { color: #64748b; margin: 0; }
.dg-acq-dl dd { margin: 0; font-weight: 600; color: #134e4a; text-align: right; }
.dg-acq-dl__total { border-top: 1px solid #d1e4e0; padding-top: 8px; margin-top: 4px; }
.dg-acq-dl__total dd { font-size: 16px; }
</style>
<script>
(function () {
  var area = document.getElementById('dg-acq-area');
  var preset = document.getElementById('dg-acq-preset');
  var net = document.getElementById('dg-acq-net');
  var life = document.getElementById('dg-acq-life');
  var areaHint = document.getElementById('dg-acq-area-hint');
  var presetHint = document.getElementById('dg-acq-preset-hint');
  if (!area || !preset) return;

  function updateAreaHint() {
    if (!areaHint) return;
    var o = area.selectedOptions[0];
    areaHint.textContent = o ? (o.getAttribute('data-hint') || '') : '';
  }

  function filterPresets() {
    var a = area.value;
    var opts = preset.querySelectorAll('option');
    var firstVisible = null;
    opts.forEach(function (o) {
      var show = o.getAttribute('data-area') === a;
      o.hidden = !show;
      o.disabled = !show;
      if (show && !firstVisible) firstVisible = o;
    });
    updateAreaHint();
    if (preset.selectedOptions[0] && preset.selectedOptions[0].hidden && firstVisible) {
      firstVisible.selected = true;
      applyPreset();
    } else {
      applyPresetHintsOnly();
    }
  }

  function applyPresetHintsOnly() {
    var o = preset.selectedOptions[0];
    if (presetHint) {
      presetHint.textContent = o ? (o.getAttribute('data-hint') || '') : '';
    }
  }

  function applyPreset() {
    var o = preset.selectedOptions[0];
    if (!o) return;
    if (net) net.value = o.getAttribute('data-net') || net.value;
    if (life) life.value = o.getAttribute('data-life') || life.value;
    applyPresetHintsOnly();
  }

  area.addEventListener('change', filterPresets);
  preset.addEventListener('change', applyPreset);
  filterPresets();
})();
</script>
