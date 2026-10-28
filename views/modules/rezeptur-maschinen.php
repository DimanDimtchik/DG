<?php
/**
 * @var list<array<string, mixed>> $workCenterList
 * @var list<array<string, mixed>> $machineCapacityProfiles
 * @var list<array<string, mixed>> $machineMaintenancePlans
 * @var list<array<string, mixed>> $machineMaintenanceLogs
 * @var list<array<string, mixed>> $machineBookingWindows
 * @var list<array<string, mixed>> $machineRecipeOptions
 * @var int $machineBookingFilterWorkCenterId
 * @var string $machineBookingFilterFrom
 * @var string $machineBookingFilterTo
 * @var array{electricity_eur_per_kwh: string, room_eur_per_m2_h: string, wage_eur_per_h: string} $recipeCostRatesForm
 * @var array{electricity_eur_per_kwh: float, room_eur_per_m2_h: float, wage_eur_per_h: float} $recipeCostRates
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$centers = $workCenterList ?? [];
$capacityProfiles = $machineCapacityProfiles ?? [];
$maintenancePlans = $machineMaintenancePlans ?? [];
$maintenanceLogs = $machineMaintenanceLogs ?? [];
$bookingWindows = $machineBookingWindows ?? [];
$recipeOptions = $machineRecipeOptions ?? [];
$bookingFilterWorkCenterId = (int) ($machineBookingFilterWorkCenterId ?? 0);
$bookingFilterFrom = (string) ($machineBookingFilterFrom ?? '');
$bookingFilterTo = (string) ($machineBookingFilterTo ?? '');
$bookingQuickBase = ['page' => 'rezeptur-maschinen'];
if ($bookingFilterWorkCenterId > 0) {
    $bookingQuickBase['booking_work_center_id'] = (string) $bookingFilterWorkCenterId;
}
$bookingToday = new DateTimeImmutable('today');
$bookingQuickRanges = [
    [
        'label' => 'Heute',
        'from' => $bookingToday->format('Y-m-d'),
        'to' => $bookingToday->format('Y-m-d'),
    ],
    [
        'label' => 'Nächste 7 Tage',
        'from' => $bookingToday->format('Y-m-d'),
        'to' => $bookingToday->modify('+6 days')->format('Y-m-d'),
    ],
    [
        'label' => 'Dieser Monat',
        'from' => $bookingToday->modify('first day of this month')->format('Y-m-d'),
        'to' => $bookingToday->modify('last day of this month')->format('Y-m-d'),
    ],
];
$bookingQuickHref = static function (array $range) use ($bookingQuickBase): string {
    $query = $bookingQuickBase + [
        'booking_from' => (string) $range['from'],
        'booking_to' => (string) $range['to'],
    ];

    return '/app?' . http_build_query($query) . '#booking';
};
$ratesForm = $recipeCostRatesForm ?? RecipeCostSettings::forForm();
$rates = $recipeCostRates ?? RecipeCostSettings::get();
$centerNames = [];
$centerHourlyCosts = [];
foreach ($centers as $center) {
    $centerId = (int) ($center['id'] ?? 0);
    $centerNames[$centerId] = (string) ($center['name'] ?? '');
    $centerHourlyCosts[$centerId] = RecipeCostService::breakdown($center, $rates);
}
$maintenancePlanNames = [];
$maintenancePlanEstimatedCosts = [];
foreach ($maintenancePlans as $plan) {
    $planId = (int) ($plan['id'] ?? 0);
    $maintenancePlanNames[$planId] = (string) ($plan['title'] ?? '');
    $maintenancePlanEstimatedCosts[$planId] = (float) ($plan['estimated_material_cost'] ?? 0) + (float) ($plan['estimated_service_cost'] ?? 0);
}
$recipeNames = [];
foreach ($recipeOptions as $recipe) {
    $recipeNames[(int) ($recipe['id'] ?? 0)] = (string) ($recipe['title'] ?? '');
}
$bookingTypeLabels = [
    'production' => 'Produktion',
    'maintenance' => 'Wartung',
    'blocked' => 'Sperrzeit',
    'other' => 'Sonstiges',
];
$bookingStatusLabels = [
    'planned' => 'geplant',
    'done' => 'erledigt',
    'cancelled' => 'storniert',
];
$bookingSummary = [
    'total_hours' => 0.0,
    'production_hours' => 0.0,
    'maintenance_hours' => 0.0,
    'blocked_hours' => 0.0,
    'other_hours' => 0.0,
    'estimated_maintenance_cost' => 0.0,
    'machine_cost' => 0.0,
    'loaded_cost' => 0.0,
    'production_machine_cost' => 0.0,
    'maintenance_machine_cost' => 0.0,
    'blocked_machine_cost' => 0.0,
    'other_machine_cost' => 0.0,
];
foreach ($bookingWindows as $booking) {
    if (($booking['booking_status'] ?? '') === 'cancelled') {
        continue;
    }
    $bookingStart = strtotime((string) ($booking['starts_at'] ?? ''));
    $bookingEnd = strtotime((string) ($booking['ends_at'] ?? ''));
    if ($bookingStart === false || $bookingEnd === false || $bookingEnd <= $bookingStart) {
        continue;
    }
    $hours = ($bookingEnd - $bookingStart) / 3600;
    $bookingSummary['total_hours'] += $hours;
    $machineCostForBooking = 0.0;
    $hourlyCosts = $centerHourlyCosts[(int) ($booking['work_center_id'] ?? 0)] ?? null;
    if (is_array($hourlyCosts)) {
        $machineCostForBooking = $hours * (float) ($hourlyCosts['machine_per_h'] ?? 0);
        $bookingSummary['machine_cost'] += $machineCostForBooking;
        $bookingSummary['loaded_cost'] += $hours * (float) ($hourlyCosts['loaded_per_h'] ?? 0);
    }
    $type = (string) ($booking['booking_type'] ?? 'other');
    if ($type === 'production') {
        $bookingSummary['production_hours'] += $hours;
        $bookingSummary['production_machine_cost'] += $machineCostForBooking;
    } elseif ($type === 'maintenance') {
        $bookingSummary['maintenance_hours'] += $hours;
        $bookingSummary['maintenance_machine_cost'] += $machineCostForBooking;
    } elseif ($type === 'blocked') {
        $bookingSummary['blocked_hours'] += $hours;
        $bookingSummary['blocked_machine_cost'] += $machineCostForBooking;
    } else {
        $bookingSummary['other_hours'] += $hours;
        $bookingSummary['other_machine_cost'] += $machineCostForBooking;
    }
    $planId = (int) ($booking['maintenance_plan_id'] ?? 0);
    if ($planId > 0) {
        $bookingSummary['estimated_maintenance_cost'] += $maintenancePlanEstimatedCosts[$planId] ?? 0.0;
    }
}
$bookingConflictIds = [];
foreach ($bookingWindows as $leftIndex => $leftBooking) {
    if (($leftBooking['booking_status'] ?? '') === 'cancelled') {
        continue;
    }
    $leftId = (int) ($leftBooking['id'] ?? 0);
    $leftWcId = (int) ($leftBooking['work_center_id'] ?? 0);
    $leftStart = strtotime((string) ($leftBooking['starts_at'] ?? ''));
    $leftEnd = strtotime((string) ($leftBooking['ends_at'] ?? ''));
    if ($leftId <= 0 || $leftWcId <= 0 || $leftStart === false || $leftEnd === false) {
        continue;
    }
    foreach ($bookingWindows as $rightIndex => $rightBooking) {
        if ($rightIndex <= $leftIndex || ($rightBooking['booking_status'] ?? '') === 'cancelled') {
            continue;
        }
        $rightId = (int) ($rightBooking['id'] ?? 0);
        if ($rightId <= 0 || (int) ($rightBooking['work_center_id'] ?? 0) !== $leftWcId) {
            continue;
        }
        $rightStart = strtotime((string) ($rightBooking['starts_at'] ?? ''));
        $rightEnd = strtotime((string) ($rightBooking['ends_at'] ?? ''));
        if ($rightStart === false || $rightEnd === false) {
            continue;
        }
        if ($leftStart < $rightEnd && $leftEnd > $rightStart) {
            $bookingConflictIds[$leftId] = true;
            $bookingConflictIds[$rightId] = true;
        }
    }
}
$intervalLabels = [
    'days' => 'Tage',
    'runtime_hours' => 'Laufstunden',
    'cycles' => 'Zyklen',
];
$formatHelperMaterials = static function (mixed $json): string {
    if (!is_string($json) || trim($json) === '') {
        return '';
    }
    $decoded = json_decode($json, true);
    if (is_array($decoded)) {
        $items = array_values(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            $decoded
        )));
        return implode(', ', $items);
    }
    return trim($json);
};
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

  <section class="dg-card" id="capacity" style="margin-top:20px;padding:16px;">
    <h2 class="dg-subsection-title" style="margin-top:0;">Kapazitätsprofile (M1a)</h2>
    <p class="dg-field-hint">
      Erfasst nur technische Leistung: Rohstoff-Input pro Stunde, Produkt-Output pro Stunde und Ausbeute.
      Keine Belegung, keine Wartung, keine Buchung.
    </p>

    <?php if ($canEdit && $dbConnected && $centers !== []) : ?>
      <form method="post" action="/app?page=rezeptur-maschinen#capacity" class="dg-form" style="margin-bottom:16px;">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <div class="dg-form-grid">
          <label class="dg-field">
            <span>Maschine</span>
            <select name="work_center_id" required>
              <?php foreach ($centers as $center) : ?>
                <?php $cid = (int) ($center['id'] ?? 0); ?>
                <option value="<?= $cid ?>"><?= View::escape((string) ($center['name'] ?? '')) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="dg-field">
            <span>Rohstoff / Input</span>
            <input name="input_material_label" maxlength="191" placeholder="z. B. Rohmasse, Granulat, Teig">
          </label>
          <label class="dg-field">
            <span>Input pro Stunde</span>
            <input name="input_qty_per_hour" inputmode="decimal" value="0">
          </label>
          <label class="dg-field">
            <span>Produkt / Output</span>
            <input name="output_product_label" maxlength="191" placeholder="z. B. fertige Stücke, kg Produkt">
          </label>
          <label class="dg-field">
            <span>Output pro Stunde</span>
            <input name="output_qty_per_hour" inputmode="decimal" value="0">
          </label>
          <label class="dg-field">
            <span>Einheit</span>
            <input name="unit" maxlength="50" placeholder="kg, Stk, l">
          </label>
          <label class="dg-field">
            <span>Ausbeute %</span>
            <input name="yield_percent" inputmode="decimal" value="100">
          </label>
          <label class="dg-field">
            <span>Gültig ab</span>
            <input type="date" name="valid_from" value="<?= View::escape(date('Y-m-d')) ?>">
          </label>
          <label class="dg-field dg-field--wide">
            <span>Notiz</span>
            <textarea name="notes" rows="2" maxlength="1000" placeholder="z. B. abhängig von Rohstofffeuchte oder Verpackungsgröße"></textarea>
          </label>
        </div>
        <input type="hidden" name="is_active" value="1">
        <div class="dg-form-actions">
          <button type="submit" class="dg-button dg-button--primary" name="machine_capacity_save" value="1">Kapazitätsprofil speichern</button>
        </div>
      </form>
    <?php elseif ($centers === []) : ?>
      <p class="dg-muted">Erst eine Maschine anlegen, dann Kapazitätsprofile erfassen.</p>
    <?php endif; ?>

    <div class="dg-table-wrap">
      <table class="dg-table">
        <thead>
          <tr>
            <th>Maschine</th>
            <th>Input / h</th>
            <th>Output / h</th>
            <th>Ausbeute</th>
            <th>Gültig ab</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($capacityProfiles === []) : ?>
            <tr>
              <td colspan="7" class="dg-table__empty">Noch keine Kapazitätsprofile.</td>
            </tr>
          <?php else : ?>
            <?php foreach ($capacityProfiles as $profile) : ?>
              <?php
                $profileId = (int) ($profile['id'] ?? 0);
                $profileActive = !empty($profile['is_active']);
                $wcId = (int) ($profile['work_center_id'] ?? 0);
                $unit = trim((string) ($profile['unit'] ?? ''));
              ?>
              <tr<?= $profileActive ? '' : ' style="opacity:0.55;"' ?>>
                <td><?= View::escape($centerNames[$wcId] ?? ('#' . $wcId)) ?></td>
                <td>
                  <?= View::escape((string) ($profile['input_qty_per_hour'] ?? '0')) ?>
                  <?= View::escape($unit) ?>
                  <?php if (($profile['input_material_label'] ?? '') !== '') : ?>
                    <small class="dg-muted">· <?= View::escape((string) $profile['input_material_label']) ?></small>
                  <?php endif; ?>
                </td>
                <td>
                  <?= View::escape((string) ($profile['output_qty_per_hour'] ?? '0')) ?>
                  <?= View::escape($unit) ?>
                  <?php if (($profile['output_product_label'] ?? '') !== '') : ?>
                    <small class="dg-muted">· <?= View::escape((string) $profile['output_product_label']) ?></small>
                  <?php endif; ?>
                </td>
                <td><?= View::escape((string) ($profile['yield_percent'] ?? '100')) ?> %</td>
                <td><?= View::escape((string) ($profile['valid_from'] ?? '')) ?></td>
                <td>
                  <span class="dg-badge <?= $profileActive ? 'dg-badge--ok' : 'dg-badge--muted' ?>">
                    <?= $profileActive ? 'aktiv' : 'inaktiv' ?>
                  </span>
                </td>
                <td class="dg-table__actions">
                  <?php if ($canEdit && $dbConnected && $profileActive) : ?>
                    <form method="post" action="/app?page=rezeptur-maschinen#capacity" style="display:inline;">
                      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                      <input type="hidden" name="capacity_profile_id" value="<?= $profileId ?>">
                      <button type="submit" class="dg-button dg-button--small" name="machine_capacity_deactivate" value="1">Deaktivieren</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="dg-card" id="booking" style="margin-top:20px;padding:16px;">
    <h2 class="dg-subsection-title" style="margin-top:0;">Belegungsfenster (M3b)</h2>
    <p class="dg-field-hint">
      Einfache Liste fuer geplante Produktion, Wartung und Sperrzeiten. Noch kein Drag-and-drop-Kalender und keine automatische Konfliktentscheidung.
    </p>
    <?php if ($bookingConflictIds !== []) : ?>
      <div class="dg-flash dg-flash--warning" style="margin-bottom:12px;">
        Es gibt überlappende Belegungsfenster. Betroffene Zeilen sind mit „Konflikt“ markiert.
      </div>
    <?php endif; ?>

    <form method="get" action="/app#booking" class="dg-form" style="margin-bottom:16px;">
      <input type="hidden" name="page" value="rezeptur-maschinen">
      <div class="dg-form-grid">
        <label class="dg-field">
          <span>Maschine filtern</span>
          <select name="booking_work_center_id">
            <option value="0">Alle Maschinen</option>
            <?php foreach ($centers as $center) : ?>
              <?php $cid = (int) ($center['id'] ?? 0); ?>
              <option value="<?= $cid ?>"<?= $bookingFilterWorkCenterId === $cid ? ' selected' : '' ?>><?= View::escape((string) ($center['name'] ?? '')) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dg-field">
          <span>Von</span>
          <input type="date" name="booking_from" value="<?= View::escape($bookingFilterFrom) ?>">
        </label>
        <label class="dg-field">
          <span>Bis</span>
          <input type="date" name="booking_to" value="<?= View::escape($bookingFilterTo) ?>">
        </label>
      </div>
      <div class="dg-form-actions">
        <button type="submit" class="dg-button">Belegung filtern</button>
        <a class="dg-button" href="/app?page=rezeptur-maschinen#booking">Filter zurücksetzen</a>
        <?php foreach ($bookingQuickRanges as $range) : ?>
          <a class="dg-button" href="<?= View::escape($bookingQuickHref($range)) ?>"><?= View::escape((string) $range['label']) ?></a>
        <?php endforeach; ?>
      </div>
    </form>

    <div class="dg-card" style="padding:12px;margin-bottom:16px;background:color-mix(in srgb, var(--dg-primary,#336) 5%, #fff);">
      <h3 class="dg-subsection-title" style="margin:0 0 8px;font-size:1rem;">Kalkulationsgrundlage aus aktueller Belegung (M4a)</h3>
      <p class="dg-field-hint" style="margin-top:0;">Nur Hinweiswerte aus der gefilterten Liste; keine FiBu-Buchung und keine automatische Umlage.</p>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;font-size:0.95rem;">
        <div><span class="dg-muted">Gesamt</span><br><strong><?= View::escape(number_format($bookingSummary['total_hours'], 2, ',', '.')) ?> h</strong></div>
        <div><span class="dg-muted">Produktion</span><br><strong><?= View::escape(number_format($bookingSummary['production_hours'], 2, ',', '.')) ?> h</strong></div>
        <div><span class="dg-muted">Wartung</span><br><strong><?= View::escape(number_format($bookingSummary['maintenance_hours'], 2, ',', '.')) ?> h</strong></div>
        <div><span class="dg-muted">Sperrzeit</span><br><strong><?= View::escape(number_format($bookingSummary['blocked_hours'], 2, ',', '.')) ?> h</strong></div>
        <div><span class="dg-muted">Sonstiges</span><br><strong><?= View::escape(number_format($bookingSummary['other_hours'], 2, ',', '.')) ?> h</strong></div>
        <div><span class="dg-muted">Wartungskosten geschätzt</span><br><strong><?= View::escape(RecipeCostService::formatEur($bookingSummary['estimated_maintenance_cost'], 2)) ?></strong></div>
        <div><span class="dg-muted">Maschinenkosten Vorschlag</span><br><strong><?= View::escape(RecipeCostService::formatEur($bookingSummary['machine_cost'], 2)) ?></strong></div>
        <div><span class="dg-muted">inkl. Lohn Vorschlag</span><br><strong><?= View::escape(RecipeCostService::formatEur($bookingSummary['loaded_cost'], 2)) ?></strong></div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;font-size:0.9rem;border-top:1px dashed var(--dg-border,#ddd);margin-top:10px;padding-top:10px;">
        <div><span class="dg-muted">Kosten Produktion</span><br><strong><?= View::escape(RecipeCostService::formatEur($bookingSummary['production_machine_cost'], 2)) ?></strong></div>
        <div><span class="dg-muted">Kosten Wartung</span><br><strong><?= View::escape(RecipeCostService::formatEur($bookingSummary['maintenance_machine_cost'], 2)) ?></strong></div>
        <div><span class="dg-muted">Kosten Sperrzeit</span><br><strong><?= View::escape(RecipeCostService::formatEur($bookingSummary['blocked_machine_cost'], 2)) ?></strong></div>
        <div><span class="dg-muted">Kosten Sonstiges</span><br><strong><?= View::escape(RecipeCostService::formatEur($bookingSummary['other_machine_cost'], 2)) ?></strong></div>
      </div>
    </div>

    <?php if ($canEdit && $dbConnected && $centers !== []) : ?>
      <form method="post" action="/app?page=rezeptur-maschinen#booking" class="dg-form" style="margin-bottom:16px;">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <div class="dg-form-grid">
          <label class="dg-field">
            <span>Maschine</span>
            <select name="work_center_id" required>
              <?php foreach ($centers as $center) : ?>
                <?php $cid = (int) ($center['id'] ?? 0); ?>
                <option value="<?= $cid ?>"><?= View::escape((string) ($center['name'] ?? '')) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="dg-field">
            <span>Typ</span>
            <select name="booking_type">
              <?php foreach ($bookingTypeLabels as $value => $label) : ?>
                <option value="<?= View::escape($value) ?>"><?= View::escape($label) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="dg-field">
            <span>Titel</span>
            <input name="title" required maxlength="191" placeholder="z. B. Charge Creme A">
          </label>
          <label class="dg-field">
            <span>Rezeptur (optional)</span>
            <select name="recipe_id">
              <option value="0">— keine —</option>
              <?php foreach ($recipeOptions as $recipe) : ?>
                <?php $rid = (int) ($recipe['id'] ?? 0); ?>
                <option value="<?= $rid ?>"><?= View::escape((string) ($recipe['title'] ?? '')) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="dg-field">
            <span>Wartungsplan (optional)</span>
            <select name="maintenance_plan_id">
              <option value="0">— keiner —</option>
              <?php foreach ($maintenancePlans as $plan) : ?>
                <?php if (empty($plan['is_active'])) { continue; } ?>
                <?php $pid = (int) ($plan['id'] ?? 0); ?>
                <option value="<?= $pid ?>">
                  <?= View::escape(($centerNames[(int) ($plan['work_center_id'] ?? 0)] ?? ('#' . (int) ($plan['work_center_id'] ?? 0))) . ' · ' . (string) ($plan['title'] ?? '')) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="dg-field">
            <span>Start</span>
            <input type="datetime-local" name="starts_at" required>
          </label>
          <label class="dg-field">
            <span>Ende</span>
            <input type="datetime-local" name="ends_at" required>
          </label>
          <label class="dg-field">
            <span>Geplanter Output</span>
            <input name="planned_output_qty" inputmode="decimal" value="0">
          </label>
          <label class="dg-field">
            <span>Einheit</span>
            <input name="unit" maxlength="50" placeholder="kg, Stk, l">
          </label>
          <label class="dg-field">
            <span>Status</span>
            <select name="booking_status">
              <option value="planned">geplant</option>
              <option value="done">erledigt</option>
            </select>
          </label>
          <label class="dg-field dg-field--wide">
            <span>Notiz</span>
            <textarea name="notes" rows="2" maxlength="1000" placeholder="z. B. Rohstoff bereitstellen, Reinigung vorher einplanen"></textarea>
          </label>
        </div>
        <div class="dg-form-actions">
          <button type="submit" class="dg-button dg-button--primary" name="machine_booking_save" value="1">Belegungsfenster speichern</button>
        </div>
      </form>
    <?php elseif ($centers === []) : ?>
      <p class="dg-muted">Erst eine Maschine anlegen, dann Belegungsfenster erfassen.</p>
    <?php endif; ?>

    <div class="dg-table-wrap">
      <table class="dg-table">
        <thead>
          <tr>
            <th>Maschine</th>
            <th>Typ</th>
            <th>Titel</th>
            <th>Zeitraum</th>
            <th>Bezug</th>
            <th>Output</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($bookingWindows === []) : ?>
            <tr>
              <td colspan="8" class="dg-table__empty">Noch keine Belegungsfenster.</td>
            </tr>
          <?php else : ?>
            <?php foreach ($bookingWindows as $booking) : ?>
              <?php
                $bookingId = (int) ($booking['id'] ?? 0);
                $bookingWcId = (int) ($booking['work_center_id'] ?? 0);
                $bookingType = (string) ($booking['booking_type'] ?? 'other');
                $bookingStatus = (string) ($booking['booking_status'] ?? 'planned');
                $bookingRecipeId = (int) ($booking['recipe_id'] ?? 0);
                $bookingPlanId = (int) ($booking['maintenance_plan_id'] ?? 0);
                $bookingUnit = trim((string) ($booking['unit'] ?? ''));
                $hasBookingConflict = !empty($bookingConflictIds[$bookingId]);
              ?>
              <tr<?= $bookingStatus === 'cancelled' ? ' style="opacity:0.55;"' : '' ?>>
                <td><?= View::escape($centerNames[$bookingWcId] ?? ('#' . $bookingWcId)) ?></td>
                <td><?= View::escape($bookingTypeLabels[$bookingType] ?? 'Sonstiges') ?></td>
                <td>
                  <strong><?= View::escape((string) ($booking['title'] ?? '')) ?></strong>
                  <?php if ($hasBookingConflict) : ?>
                    <div style="margin-top:4px;"><span class="dg-badge dg-badge--warning">Konflikt</span></div>
                  <?php endif; ?>
                </td>
                <td>
                  <?= View::escape((string) ($booking['starts_at'] ?? '')) ?><br>
                  <span class="dg-muted">bis <?= View::escape((string) ($booking['ends_at'] ?? '')) ?></span>
                </td>
                <td>
                  <?php if ($bookingRecipeId > 0) : ?>
                    <div>Rezept: <?= View::escape($recipeNames[$bookingRecipeId] ?? ('#' . $bookingRecipeId)) ?></div>
                  <?php endif; ?>
                  <?php if ($bookingPlanId > 0) : ?>
                    <div>Wartung: <?= View::escape($maintenancePlanNames[$bookingPlanId] ?? ('#' . $bookingPlanId)) ?></div>
                  <?php endif; ?>
                  <?php if ($bookingRecipeId <= 0 && $bookingPlanId <= 0) : ?>
                    <span class="dg-muted">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?= View::escape((string) ($booking['planned_output_qty'] ?? '0')) ?>
                  <?= View::escape($bookingUnit) ?>
                </td>
                <td>
                  <span class="dg-badge <?= $bookingStatus === 'cancelled' ? 'dg-badge--muted' : 'dg-badge--ok' ?>">
                    <?= View::escape($bookingStatusLabels[$bookingStatus] ?? $bookingStatus) ?>
                  </span>
                </td>
                <td class="dg-table__actions">
                  <?php if ($canEdit && $dbConnected && $bookingStatus !== 'cancelled') : ?>
                    <form method="post" action="/app?page=rezeptur-maschinen#booking" style="display:inline;">
                      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                      <input type="hidden" name="booking_window_id" value="<?= $bookingId ?>">
                      <button type="submit" class="dg-button dg-button--small" name="machine_booking_cancel" value="1">Stornieren</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="dg-card" id="maintenance" style="margin-top:20px;padding:16px;">
    <h2 class="dg-subsection-title" style="margin-top:0;">Wartungspläne (M2a)</h2>
    <p class="dg-field-hint">
      Erfasst den geplanten Turnus, erwartete Ausfallzeit, Hilfsmittel und Kosten.
      Keine Wartungsprotokolle, keine Kalenderbelegung und keine FiBu-Buchung.
    </p>

    <?php if ($canEdit && $dbConnected && $centers !== []) : ?>
      <form method="post" action="/app?page=rezeptur-maschinen#maintenance" class="dg-form" style="margin-bottom:16px;">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <div class="dg-form-grid">
          <label class="dg-field">
            <span>Maschine</span>
            <select name="work_center_id" required>
              <?php foreach ($centers as $center) : ?>
                <?php $cid = (int) ($center['id'] ?? 0); ?>
                <option value="<?= $cid ?>"><?= View::escape((string) ($center['name'] ?? '')) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="dg-field">
            <span>Titel</span>
            <input name="title" required maxlength="191" placeholder="z. B. Monatswartung Mischer">
          </label>
          <label class="dg-field">
            <span>Turnus nach</span>
            <select name="interval_type">
              <?php foreach ($intervalLabels as $value => $label) : ?>
                <option value="<?= View::escape($value) ?>"><?= View::escape($label) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="dg-field">
            <span>Intervallwert</span>
            <input name="interval_value" inputmode="decimal" value="30">
          </label>
          <label class="dg-field">
            <span>Ausfallzeit (Min.)</span>
            <input name="planned_downtime_minutes" type="number" min="0" step="1" value="0">
          </label>
          <label class="dg-field">
            <span>Materialkosten geschätzt</span>
            <input name="estimated_material_cost" inputmode="decimal" value="0">
          </label>
          <label class="dg-field">
            <span>Servicekosten geschätzt</span>
            <input name="estimated_service_cost" inputmode="decimal" value="0">
          </label>
          <label class="dg-field">
            <span>Nächster Termin</span>
            <input type="date" name="next_due_date">
          </label>
          <label class="dg-field dg-field--wide">
            <span>Hilfsmittel / Ersatzteile</span>
            <textarea name="helper_materials_text" rows="2" maxlength="1000" placeholder="Ein Eintrag pro Zeile, z. B. Schmiermittel&#10;Dichtung"></textarea>
          </label>
        </div>
        <div class="dg-form-actions">
          <button type="submit" class="dg-button dg-button--primary" name="machine_maintenance_plan_save" value="1">Wartungsplan speichern</button>
        </div>
      </form>
    <?php elseif ($centers === []) : ?>
      <p class="dg-muted">Erst eine Maschine anlegen, dann Wartungspläne erfassen.</p>
    <?php endif; ?>

    <div class="dg-table-wrap">
      <table class="dg-table">
        <thead>
          <tr>
            <th>Maschine</th>
            <th>Wartung</th>
            <th>Turnus</th>
            <th>Nächster Termin</th>
            <th>Ausfall</th>
            <th>Kosten</th>
            <th>Hilfsmittel</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($maintenancePlans === []) : ?>
            <tr>
              <td colspan="9" class="dg-table__empty">Noch keine Wartungspläne.</td>
            </tr>
          <?php else : ?>
            <?php foreach ($maintenancePlans as $plan) : ?>
              <?php
                $planId = (int) ($plan['id'] ?? 0);
                $planActive = !empty($plan['is_active']);
                $wcId = (int) ($plan['work_center_id'] ?? 0);
                $intervalType = (string) ($plan['interval_type'] ?? 'days');
                $estimatedCost = (float) ($plan['estimated_material_cost'] ?? 0) + (float) ($plan['estimated_service_cost'] ?? 0);
                $helpers = $formatHelperMaterials($plan['helper_materials_json'] ?? null);
              ?>
              <tr<?= $planActive ? '' : ' style="opacity:0.55;"' ?>>
                <td><?= View::escape($centerNames[$wcId] ?? ('#' . $wcId)) ?></td>
                <td><strong><?= View::escape((string) ($plan['title'] ?? '')) ?></strong></td>
                <td>
                  <?= View::escape((string) ($plan['interval_value'] ?? '0')) ?>
                  <?= View::escape($intervalLabels[$intervalType] ?? $intervalType) ?>
                </td>
                <td><?= View::escape((string) ($plan['next_due_date'] ?? '')) ?></td>
                <td><?= (int) ($plan['planned_downtime_minutes'] ?? 0) ?> Min.</td>
                <td><?= View::escape(RecipeCostService::formatEur($estimatedCost, 2)) ?></td>
                <td><?= $helpers !== '' ? View::escape($helpers) : '<span class="dg-muted">—</span>' ?></td>
                <td>
                  <span class="dg-badge <?= $planActive ? 'dg-badge--ok' : 'dg-badge--muted' ?>">
                    <?= $planActive ? 'aktiv' : 'inaktiv' ?>
                  </span>
                </td>
                <td class="dg-table__actions">
                  <?php if ($canEdit && $dbConnected && $planActive) : ?>
                    <form method="post" action="/app?page=rezeptur-maschinen#maintenance" style="display:inline;">
                      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                      <input type="hidden" name="maintenance_plan_id" value="<?= $planId ?>">
                      <button type="submit" class="dg-button dg-button--small" name="machine_maintenance_plan_deactivate" value="1">Deaktivieren</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div id="maintenance-log" style="margin-top:22px;border-top:1px solid var(--dg-border,#ddd);padding-top:16px;">
      <h3 class="dg-subsection-title" style="margin-top:0;">Durchgeführte Wartung protokollieren (M2b)</h3>
      <p class="dg-field-hint">Dokumentiert Ist-Zeitpunkt, Laufstundenstand und Ist-Kosten. Keine automatische Buchung.</p>

      <?php if ($canEdit && $dbConnected && $centers !== []) : ?>
        <form method="post" action="/app?page=rezeptur-maschinen#maintenance-log" class="dg-form" style="margin-bottom:16px;">
          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
          <div class="dg-form-grid">
            <label class="dg-field">
              <span>Maschine</span>
              <select name="work_center_id" required>
                <?php foreach ($centers as $center) : ?>
                  <?php $cid = (int) ($center['id'] ?? 0); ?>
                  <option value="<?= $cid ?>"><?= View::escape((string) ($center['name'] ?? '')) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="dg-field">
              <span>Wartungsplan (optional)</span>
              <select name="maintenance_plan_id">
                <option value="0">— ohne Plan —</option>
                <?php foreach ($maintenancePlans as $plan) : ?>
                  <?php if (empty($plan['is_active'])) { continue; } ?>
                  <?php $pid = (int) ($plan['id'] ?? 0); ?>
                  <option value="<?= $pid ?>">
                    <?= View::escape(($centerNames[(int) ($plan['work_center_id'] ?? 0)] ?? ('#' . (int) ($plan['work_center_id'] ?? 0))) . ' · ' . (string) ($plan['title'] ?? '')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="dg-field">
              <span>Durchgeführt am</span>
              <input type="datetime-local" name="performed_at">
            </label>
            <label class="dg-field">
              <span>Laufstundenstand</span>
              <input name="runtime_hours_at_service" inputmode="decimal" placeholder="optional">
            </label>
            <label class="dg-field">
              <span>Materialkosten Ist</span>
              <input name="actual_material_cost" inputmode="decimal" value="0">
            </label>
            <label class="dg-field">
              <span>Servicekosten Ist</span>
              <input name="actual_service_cost" inputmode="decimal" value="0">
            </label>
            <label class="dg-field dg-field--wide">
              <span>Notiz</span>
              <textarea name="notes" rows="2" maxlength="1000" placeholder="z. B. Filter gewechselt, Dichtung geprüft"></textarea>
            </label>
          </div>
          <div class="dg-form-actions">
            <button type="submit" class="dg-button dg-button--primary" name="machine_maintenance_log_save" value="1">Wartung protokollieren</button>
          </div>
        </form>
      <?php endif; ?>

      <div class="dg-table-wrap">
        <table class="dg-table">
          <thead>
            <tr>
              <th>Maschine</th>
              <th>Plan</th>
              <th>Durchgeführt</th>
              <th>Laufstunden</th>
              <th>Ist-Kosten</th>
              <th>Notiz</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($maintenanceLogs === []) : ?>
              <tr>
                <td colspan="6" class="dg-table__empty">Noch keine Wartungen protokolliert.</td>
              </tr>
            <?php else : ?>
              <?php foreach ($maintenanceLogs as $log) : ?>
                <?php
                  $logWcId = (int) ($log['work_center_id'] ?? 0);
                  $logPlanId = (int) ($log['maintenance_plan_id'] ?? 0);
                  $actualCost = (float) ($log['actual_material_cost'] ?? 0) + (float) ($log['actual_service_cost'] ?? 0);
                ?>
                <tr>
                  <td><?= View::escape($centerNames[$logWcId] ?? ('#' . $logWcId)) ?></td>
                  <td><?= $logPlanId > 0 ? View::escape($maintenancePlanNames[$logPlanId] ?? ('#' . $logPlanId)) : '<span class="dg-muted">—</span>' ?></td>
                  <td><?= View::escape((string) ($log['performed_at'] ?? '')) ?></td>
                  <td><?= ($log['runtime_hours_at_service'] ?? null) !== null ? View::escape((string) $log['runtime_hours_at_service']) : '<span class="dg-muted">—</span>' ?></td>
                  <td><?= View::escape(RecipeCostService::formatEur($actualCost, 2)) ?></td>
                  <td><?= View::escape((string) ($log['notes'] ?? '')) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</div>
