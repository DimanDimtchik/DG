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
    <section class="dg-panel" id="dg-acq-results">
      <h2 class="dg-subsection-title">Vergleichsergebnis</h2>
      <p class="dg-field-hint">Netto <?= $fmt((float) ($meta['net'] ?? 0)) ?> · Brutto <?= $fmt((float) ($meta['gross'] ?? 0)) ?> · Horizont <?= (int) ($meta['horizon_years'] ?? 0) ?> Jahre</p>
    </section>
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
      <?php
        $isKapG = !empty($meta['is_kapg']);
        $gewstPct = $fmtPct((float) ($meta['effective']['gewst'] ?? 0));
        $incomePct = $fmtPct((float) ($meta['effective']['income_tax'] ?? 0));
        $incomeLabel = $isKapG ? 'Körperschaftsteuer inkl. Solidaritätszuschlag' : 'Einkommensteuer (Grenzsatz)';
        $acqHelp = [
            'barkauf' => [
                'title' => 'Was bedeuten die Zahlen beim Barkauf?',
                'paras' => [
                    'Cash (Summe): einmaliger Abfluss des Brutto-Kaufpreises (Netto + Umsatzsteuer) im Anschaffungsjahr.',
                    'Vorsteuer / VSt-Effekt: die gezahlte Umsatzsteuer, die Sie als Vorsteuer zurückholen bzw. verrechnen können (wenn Sie kein Kleinunternehmer sind). Das mindert die Belastung.',
                    'Betriebsaufwand: die Abschreibung (AfA) über die Nutzungsdauer — bei GWG der volle Nettobetrag im ersten Jahr. Optional gekürzt um den „nicht abziehbaren Anteil“ (z. B. Privatnutzung).',
                    'Steuerentlastung GewSt: geschätzte Ersparnis bei der Gewerbesteuer selbst (= Betriebsaufwand × GewSt-Satz ' . $gewstPct . '). Die Bemessungsgrundlage sinkt um den Aufwand; die Steuer sinkt um diesen Betrag.',
                    'Steuerentlastung KSt/ESt: geschätzte Ersparnis bei der ' . $incomeLabel . ' (= Betriebsaufwand × ' . $incomePct . ').',
                    'Steuerentlastung gesamt: Summe aus GewSt- und Ertragsteuer-Entlastung (vereinfacht addiert).',
                    'Nettobelastung nach Steuer: Cash + Vorsteuer-Effekt + Steuerentlastung gesamt. Negativ = Netto-Belastung über den Horizont.',
                ],
            ],
            'ratenkauf' => [
                'title' => 'Was bedeuten die Zahlen beim Ratenkauf?',
                'paras' => [
                    'Cash (Summe): Anzahlung plus alle Monatsraten über die Laufzeit. Enthält Zinsen — deshalb oft höher als beim Barkauf.',
                    'Vorsteuer / VSt-Effekt: Vorsteuer auf den Kaufpreis (typisch im Anschaffungsjahr), sofern vorsteuerabzugsberechtigt.',
                    'Betriebsaufwand: AfA auf den Netto-Kaufpreis plus vereinfachter Zinsanteil der Finanzierung.',
                    'Steuerentlastung GewSt / KSt/ESt: wie beim Barkauf — Aufwand × Steuersätze aus den Firmendaten. Höherer Aufwand (Zinsen) → höhere geschätzte Entlastung.',
                    'Monatsrate: Annuität aus finanziertem Betrag, Zinssatz und Laufzeit (vereinfachte Formel).',
                    'Nettobelastung: Cash + Vorsteuer-Effekt + Steuerentlastung. Orientierung, keine Bankkalkulation.',
                ],
            ],
            'leasing' => [
                'title' => 'Was bedeuten die Zahlen beim Leasing?',
                'paras' => [
                    'Cash (Summe): Summe aller Leasingraten (und ggf. Restwert am Ende). Kein Kaufpreis — Sie zahlen für die Nutzung.',
                    'Wenn die Monatsrate im Formular 0 war, schätzt das System eine Rate aus Netto und Laufzeit. Für den Vergleich echte Vertragswerte eintragen.',
                    'Vorsteuer / VSt-Effekt: Vorsteueranteil aus den Raten (wenn Raten brutto eingegeben und Vorsteuerabzug möglich).',
                    'Betriebsaufwand: abziehbarer Teil der Raten (vereinfacht Vollrate ohne Zins-/Tilgungssplit).',
                    'Steuerentlastung: geschätzte Steuerersparnis = Aufwand × GewSt- bzw. KSt/ESt-Satz. Bedeutet: die zu zahlende Steuer fällt um diesen Betrag niedriger aus als ohne diesen Aufwand.',
                    'Am Ende besitzen Sie das Fahrzeug in der Regel nicht (außer Kaufoption/Restwert) — deshalb kann Cash unter dem Barkauf liegen.',
                ],
            ],
            'miete' => [
                'title' => 'Was bedeuten die Zahlen bei der Miete?',
                'paras' => [
                    'Cash (Summe): Summe aller Mietraten über die Laufzeit. Wie Leasing: laufende Zahlung, kein Eigentumserwerb.',
                    'Bei Rate 0 im Formular verwendet das System einen Schätzwert (etwas höher als die Leasing-Schätzung). Besser: echte Mietrate eintragen.',
                    'Vorsteuer / VSt-Effekt und Betriebsaufwand: analog Leasing — Raten als Betriebsausgabe, Vorsteuer aus Brutto-Raten.',
                    'Steuerentlastung GewSt und KSt/ESt: Aufwand × Firmensätze. Das ist die geschätzte Ersparnis bei der Steuerzahlung, nicht die Minderung der Bemessungsgrundlage (die sinkt um den Aufwand).',
                    'Nettobelastung nach Steuer: Cash + Vorsteuer-Effekt + Steuerentlastung — zum groben Vergleich mit Kauf und Leasing.',
                ],
            ],
        ];
        foreach (['barkauf', 'ratenkauf', 'leasing', 'miete'] as $key) :
          $m = is_array($models[$key] ?? null) ? $models[$key] : null;
          if ($m === null) {
              continue;
          }
          $t = is_array($m['totals'] ?? null) ? $m['totals'] : [];
          $help = $acqHelp[$key] ?? null;
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
          <?php if (is_array($help)) : ?>
            <details class="dg-acq-help">
              <summary><?= View::escape((string) $help['title']) ?></summary>
              <div class="dg-acq-help__body">
                <?php foreach ($help['paras'] as $para) : ?>
                  <p><?= View::escape((string) $para) ?></p>
                <?php endforeach; ?>
                <p class="dg-acq-help__note">Nur Orientierung — keine Steuerberatung. Sätze und Wechselwirkungen sind vereinfacht.</p>
              </div>
            </details>
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
.dg-acq-help {
  margin-top: 14px;
  border-top: 1px solid #e2e8f0;
  padding-top: 10px;
}
.dg-acq-help summary {
  cursor: pointer;
  font-size: 13px;
  font-weight: 600;
  color: #0f766e;
  list-style-position: outside;
}
.dg-acq-help summary:hover {
  text-decoration: underline;
}
.dg-acq-help__body {
  margin-top: 10px;
  font-size: 13px;
  line-height: 1.45;
  color: #334155;
}
.dg-acq-help__body p {
  margin: 0 0 8px;
}
.dg-acq-help__note {
  color: #64748b;
  font-style: italic;
}
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

  function filterPresets(fromUserChange) {
    var a = area.value;
    var opts = preset.querySelectorAll('option');
    var firstVisible = null;
    opts.forEach(function (o) {
      var show = o.getAttribute('data-area') === a;
      o.hidden = !show;
      // nicht disabled: sonst fehlt preset im GET-Submit
      if (show && !firstVisible) firstVisible = o;
    });
    updateAreaHint();
    if (preset.selectedOptions[0] && preset.selectedOptions[0].hidden && firstVisible) {
      firstVisible.selected = true;
      if (fromUserChange) {
        applyPreset();
      } else {
        applyPresetHintsOnly();
      }
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

  area.addEventListener('change', function () { filterPresets(true); });
  preset.addEventListener('change', applyPreset);
  filterPresets(false);
  var results = document.getElementById('dg-acq-results');
  if (results) {
    results.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
})();
</script>
