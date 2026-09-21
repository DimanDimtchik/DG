<?php
/**
 * @var list<array<string, mixed>> $workCenterList
 * @var array{electricity_eur_per_kwh: string, room_eur_per_m2_h: string, wage_eur_per_h: string} $recipeCostRatesForm
 * @var array{electricity_eur_per_kwh: float, room_eur_per_m2_h: float, wage_eur_per_h: float} $recipeCostRates
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$centers = $workCenterList ?? [];
$ratesForm = $recipeCostRatesForm ?? RecipeCostSettings::forForm();
$rates = $recipeCostRates ?? RecipeCostSettings::get();
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <?php
    View::partial('partials/back-nav', [
        'href' => '/app?page=rezeptur',
        'label' => 'Zurück zur Rezeptur',
    ]);
  ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Maschinen &amp; Arbeitsplätze</h1>
      <p class="dg-lead">Stammdaten und berechneter Maschinenstundensatz. Neue Maschinen: geführter Assistent (Preis, Lebensdauer, Watt).</p>
    </div>
    <div class="dg-toolbar">
      <?php if ($canEdit && $dbConnected) : ?>
        <a class="dg-button dg-button--primary" href="/app?page=rezeptur-maschine-form&amp;action=new">Maschine hinzufügen</a>
      <?php endif; ?>
    </div>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Datenbank nicht verbunden.
      <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Einstellungen → Datenbank</a>
    </div>
  <?php endif; ?>

  <section class="dg-card" style="margin-bottom:20px;padding:16px;">
    <h2 class="dg-subsection-title" style="margin-top:0;">Kostensätze (global)</h2>
    <p class="dg-field-hint">Fließen in den Stundensatz: Strom €/kWh, Raum €/m²·h, Lohn €/h je Bediener.</p>
    <?php if ($canEdit && $dbConnected) : ?>
      <form method="post" action="/app?page=rezeptur-maschinen" class="dg-form">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <label class="dg-field">
          <span>Strom (€ / kWh)</span>
          <input name="electricity_eur_per_kwh" inputmode="decimal" value="<?= View::escape($ratesForm['electricity_eur_per_kwh']) ?>">
        </label>
        <label class="dg-field">
          <span>Raum (€ / m² · Stunde)</span>
          <input name="room_eur_per_m2_h" inputmode="decimal" value="<?= View::escape($ratesForm['room_eur_per_m2_h']) ?>">
        </label>
        <label class="dg-field">
          <span>Lohn (€ / Stunde · Bediener)</span>
          <input name="wage_eur_per_h" inputmode="decimal" value="<?= View::escape($ratesForm['wage_eur_per_h']) ?>">
        </label>
        <div class="dg-form-actions">
          <button type="submit" class="dg-button dg-button--primary" name="recipe_cost_rates_save" value="1">Kostensätze speichern</button>
        </div>
      </form>
    <?php else : ?>
      <p>
        Strom <?= View::escape(RecipeCostService::formatEur($rates['electricity_eur_per_kwh'])) ?>/kWh ·
        Raum <?= View::escape(RecipeCostService::formatEur($rates['room_eur_per_m2_h'])) ?>/m²·h ·
        Lohn <?= View::escape(RecipeCostService::formatEur($rates['wage_eur_per_h'], 2)) ?>/h
      </p>
    <?php endif; ?>
  </section>

  <div class="dg-table-wrap">
    <table class="dg-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Anschaffung</th>
          <th>Nutzung (h)</th>
          <th>kW</th>
          <th>m²</th>
          <th>Bediener</th>
          <th>K<sub>masch</sub> / h</th>
          <th>inkl. Lohn / h</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($centers === []) : ?>
          <tr>
            <td colspan="9" class="dg-table__empty">Noch keine Maschinen. Legen Sie einen Arbeitsplatz an, um den Stundensatz zu sehen.</td>
          </tr>
        <?php else : ?>
          <?php foreach ($centers as $row) : ?>
            <?php
              $wid = (int) ($row['id'] ?? 0);
              $bd = RecipeCostService::breakdown($row, $rates);
              $active = !empty($row['is_active']);
            ?>
            <tr<?= $active ? '' : ' style="opacity:0.55;"' ?>>
              <td>
                <strong><?= View::escape((string) ($row['name'] ?? '')) ?></strong>
                <?php if (!$active) : ?>
                  <span class="dg-badge dg-badge--muted">inaktiv</span>
                <?php endif; ?>
              </td>
              <td><?= View::escape(RecipeCostService::formatEur((float) ($row['purchase_price'] ?? 0), 2)) ?></td>
              <td><?= View::escape((string) ($row['life_hours'] ?? '')) ?></td>
              <td><?= View::escape((string) ($row['kw'] ?? '')) ?></td>
              <td><?= View::escape((string) ($row['space_m2'] ?? '')) ?></td>
              <td><?= View::escape((string) ($row['operators'] ?? '')) ?></td>
              <td><strong><?= View::escape(RecipeCostService::formatEur($bd['machine_per_h'])) ?></strong></td>
              <td><?= View::escape(RecipeCostService::formatEur($bd['loaded_per_h'])) ?></td>
              <td class="dg-table__actions">
                <a href="/app?page=rezeptur-maschine-form&amp;action=edit&amp;id=<?= $wid ?>"><?= $canEdit ? 'Bearbeiten' : 'Anzeigen' ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <p class="dg-field-hint" style="margin-top:12px;">
    Formel: K<sub>masch</sub> = (Anschaffung ÷ Nutzungsdauer) + (kW × Strom) + (m² × Raum).
    „inkl. Lohn“ = K<sub>masch</sub> + Bediener × Lohn.
  </p>
</div>
