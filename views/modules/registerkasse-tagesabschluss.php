<?php
/**
 * Registerkasse — Tagesabschluss je Kasse (K12a) + Filialsumme (K12c).
 *
 * @var list<array<string, mixed>> $cashRegisterOptions
 * @var list<array<string, mixed>> $cashLocationOptions
 * @var list<array<string, mixed>> $cashDayClosings
 * @var array<string, mixed>|null $cashDayClosingPreview
 * @var array<string, mixed>|null $cashLocationDaySummary
 * @var int $cashDayClosingRegisterId
 * @var int $cashDayClosingLocationId
 * @var string $cashDayClosingDate
 * @var string $cashDayClosingCounted
 * @var string $cashDayClosingNotes
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$registers = $cashRegisterOptions ?? [];
$locations = $cashLocationOptions ?? [];
$closings = $cashDayClosings ?? [];
$preview = $cashDayClosingPreview ?? null;
$locationSummary = $cashLocationDaySummary ?? null;
$registerId = (int) ($cashDayClosingRegisterId ?? 0);
$locationId = (int) ($cashDayClosingLocationId ?? 0);
$businessDate = (string) ($cashDayClosingDate ?? date('Y-m-d'));
$counted = (string) ($cashDayClosingCounted ?? '');
$notes = (string) ($cashDayClosingNotes ?? '');
$csrf = Csrf::token();
$canSubmit = $canEdit && $dbConnected && $preview !== null
    && empty($preview['already_closed'])
    && empty($preview['open_session'])
    && (int) ($preview['unfinished_receipts'] ?? 0) === 0;
$fmt = static fn (float $v): string => number_format($v, 2, ',', '.');
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header">
    <h1 class="dg-page-title">Tagesabschluss</h1>
    <p class="dg-lead">Abschluss je Kasse; Filialübersicht als Summe der Kassen.</p>
  </header>

  <div class="dg-flash dg-flash--info">
    Das ist der Abschluss der Registerkasse (Ladenkasse). Das Kassenbuch in der Buchhaltung ist getrennt
    und speichert einen eigenen Tagesabschluss.
    <a class="dg-button dg-button--small" href="/app?page=buchhaltung-kassenbuch">Zum Kassenbuch</a>
  </div>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Datenbank nicht verbunden.
      <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Einstellungen → Datenbank</a>
    </div>
  <?php endif; ?>

  <form class="dg-panel dg-form" method="get" action="/app">
    <input type="hidden" name="page" value="registerkasse-tagesabschluss">
    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Filiale</span>
        <select name="location_id">
          <option value="0"<?= $locationId === 0 ? ' selected' : '' ?>>— optional —</option>
          <?php foreach ($locations as $loc) : ?>
            <?php $lid = (int) ($loc['id'] ?? 0); ?>
            <option value="<?= $lid ?>"<?= $locationId === $lid ? ' selected' : '' ?>>
              <?= View::escape(trim((string) ($loc['code'] ?? '') . ' — ' . (string) ($loc['name'] ?? ''))) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field">
        <span>Kasse</span>
        <select name="register_id">
          <option value="0"<?= $registerId === 0 ? ' selected' : '' ?>>— für Filialsumme leer —</option>
          <?php foreach ($registers as $reg) : ?>
            <?php
              $rid = (int) ($reg['id'] ?? 0);
              if ($locationId > 0 && (int) ($reg['location_id'] ?? 0) !== $locationId) {
                  continue;
              }
              $label = trim((string) ($reg['code'] ?? ''));
              if (trim((string) ($reg['name'] ?? '')) !== '') {
                  $label .= ($label !== '' ? ' — ' : '') . trim((string) $reg['name']);
              }
            ?>
            <option value="<?= $rid ?>"<?= $registerId === $rid ? ' selected' : '' ?>>
              <?= View::escape($label !== '' ? $label : '#' . $rid) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field">
        <span>Geschäftstag *</span>
        <input type="date" name="business_date" required value="<?= View::escape($businessDate) ?>">
      </label>
      <div class="dg-field dg-field--actions">
        <button type="submit" class="dg-button dg-button--primary">Anzeigen</button>
      </div>
    </div>
  </form>

  <?php if ($locationSummary !== null && $registerId <= 0) : ?>
    <?php if (!empty($locationSummary['complete'])) : ?>
      <div class="dg-flash dg-flash--info">
        Filialabschluss vollständig:
        <?= (int) $locationSummary['closed_count'] ?>/<?= (int) $locationSummary['register_count'] ?> Kassen abgeschlossen.
      </div>
    <?php else : ?>
      <div class="dg-flash dg-flash--warning">
        Filialabschluss unvollständig:
        <?= (int) $locationSummary['closed_count'] ?>/<?= (int) $locationSummary['register_count'] ?> Kassen abgeschlossen.
      </div>
    <?php endif; ?>

    <div class="dg-panel">
      <h2 class="dg-subsection-title">Filialsumme</h2>
      <dl class="dg-dl">
        <dt>Anfangsbestand</dt>
        <dd><?= View::escape($fmt((float) $locationSummary['opening_balance'])) ?> €</dd>
        <dt>Bar</dt>
        <dd><?= View::escape($fmt((float) $locationSummary['total_cash'])) ?> €</dd>
        <dt>EC</dt>
        <dd><?= View::escape($fmt((float) $locationSummary['total_ec'])) ?> €</dd>
        <dt>Karte</dt>
        <dd><?= View::escape($fmt((float) $locationSummary['total_card'])) ?> €</dd>
        <dt>Gutschein</dt>
        <dd><?= View::escape($fmt((float) $locationSummary['total_voucher'])) ?> €</dd>
        <dt>Bons</dt>
        <dd><?= (int) $locationSummary['receipt_count'] ?> · Brutto <?= View::escape($fmt((float) $locationSummary['total_gross'])) ?> €</dd>
        <dt>Soll-Bargeld</dt>
        <dd><?= View::escape($fmt((float) $locationSummary['expected_balance'])) ?> €</dd>
        <dt>Gezählt / Differenz</dt>
        <dd>
          <?= View::escape($fmt((float) $locationSummary['counted_balance'])) ?> €
          · Diff. <?= View::escape($fmt((float) $locationSummary['difference'])) ?> €
        </dd>
      </dl>
    </div>

    <div class="dg-panel">
      <h2 class="dg-subsection-title">Kassen der Filiale</h2>
      <div class="dg-table-wrap">
        <table class="dg-table">
          <thead>
            <tr>
              <th>Kasse</th>
              <th>Status</th>
              <th class="dg-table__num">Bar</th>
              <th class="dg-table__num">Brutto</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (($locationSummary['registers'] ?? []) === []) : ?>
              <tr>
                <td colspan="5" class="dg-table__empty">Keine aktiven Kassen in dieser Filiale.</td>
              </tr>
            <?php else : ?>
              <?php foreach ($locationSummary['registers'] as $regRow) : ?>
                <?php
                  $rid = (int) ($regRow['register_id'] ?? 0);
                  $rLabel = trim((string) ($regRow['code'] ?? ''));
                  if (trim((string) ($regRow['name'] ?? '')) !== '') {
                      $rLabel .= ($rLabel !== '' ? ' — ' : '') . trim((string) $regRow['name']);
                  }
                  $closing = is_array($regRow['closing'] ?? null) ? $regRow['closing'] : null;
                ?>
                <tr>
                  <td><?= View::escape($rLabel !== '' ? $rLabel : '#' . $rid) ?></td>
                  <td>
                    <span class="dg-badge <?= !empty($regRow['closed']) ? 'dg-badge--ok' : 'dg-badge--muted' ?>">
                      <?= !empty($regRow['closed']) ? 'Abgeschlossen' : 'Offen' ?>
                    </span>
                  </td>
                  <td class="dg-table__num">
                    <?= $closing !== null ? View::escape($fmt((float) ($closing['total_cash'] ?? 0))) : '—' ?>
                  </td>
                  <td class="dg-table__num">
                    <?= $closing !== null ? View::escape($fmt((float) ($closing['total_gross'] ?? 0))) : '—' ?>
                  </td>
                  <td class="dg-table__actions">
                    <div class="dg-table__actions-group">
                      <a class="dg-button dg-button--small"
                         href="/app?page=registerkasse-tagesabschluss&amp;register_id=<?= $rid ?>&amp;location_id=<?= $locationId ?>&amp;business_date=<?= View::escape(urlencode($businessDate)) ?>">
                        Zur Kasse
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($preview !== null) : ?>
    <?php if (!empty($preview['already_closed'])) : ?>
      <div class="dg-flash dg-flash--info">Dieser Tag ist für die Kasse bereits abgeschlossen.</div>
    <?php elseif (!empty($preview['open_session'])) : ?>
      <div class="dg-flash dg-flash--warning">
        Offene Kassensitzung — bitte zuerst schließen.
        <a class="dg-button dg-button--small" href="/app?page=registerkasse-sitzung-schliessen&amp;register_id=<?= $registerId ?>">Schließen</a>
      </div>
    <?php elseif ((int) ($preview['unfinished_receipts'] ?? 0) > 0) : ?>
      <div class="dg-flash dg-flash--warning">
        Noch <?= (int) $preview['unfinished_receipts'] ?> offene Bon(s) an diesem Tag.
        <a class="dg-button dg-button--small" href="/app?page=registerkasse-bons&amp;register_id=<?= $registerId ?>">Zu den Bons</a>
      </div>
    <?php endif; ?>

    <div class="dg-panel">
      <h2 class="dg-subsection-title">Kassensummen</h2>
      <dl class="dg-dl">
        <dt>Anfangsbestand</dt>
        <dd><?= View::escape($fmt((float) $preview['opening_balance'])) ?> €</dd>
        <dt>Bar</dt>
        <dd><?= View::escape($fmt((float) $preview['total_cash'])) ?> €</dd>
        <dt>EC</dt>
        <dd><?= View::escape($fmt((float) $preview['total_ec'])) ?> €</dd>
        <dt>Karte</dt>
        <dd><?= View::escape($fmt((float) $preview['total_card'])) ?> €</dd>
        <dt>Gutschein</dt>
        <dd><?= View::escape($fmt((float) $preview['total_voucher'])) ?> €</dd>
        <dt>Bons (festgeschrieben)</dt>
        <dd><?= (int) $preview['receipt_count'] ?> · Brutto <?= View::escape($fmt((float) $preview['total_gross'])) ?> €</dd>
        <dt>Soll-Bargeld</dt>
        <dd><?= View::escape($fmt((float) $preview['expected_balance'])) ?> €</dd>
      </dl>
    </div>

    <?php if ($canEdit && $dbConnected && empty($preview['already_closed'])) : ?>
      <form class="dg-form dg-panel" method="post" action="/app?page=registerkasse-tagesabschluss&amp;register_id=<?= $registerId ?>&amp;location_id=<?= $locationId ?>&amp;business_date=<?= View::escape(urlencode($businessDate)) ?>">
        <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
        <input type="hidden" name="cash_day_closing_save" value="1">
        <input type="hidden" name="register_id" value="<?= $registerId ?>">
        <input type="hidden" name="business_date" value="<?= View::escape($businessDate) ?>">
        <h2 class="dg-subsection-title">Abschluss speichern</h2>
        <div class="dg-form-grid">
          <label class="dg-field">
            <span>Gezähltes Bargeld (EUR)</span>
            <input type="text" name="counted_balance" inputmode="decimal"
                   value="<?= View::escape($counted !== '' ? $counted : $fmt((float) $preview['expected_balance'])) ?>"
                   <?= !$canSubmit ? ' disabled' : '' ?>
                   placeholder="0,00">
            <small class="dg-field-hint">Leer = Soll-Betrag. Differenz wird gespeichert.</small>
          </label>
          <label class="dg-field dg-field--full">
            <span>Notiz</span>
            <input type="text" name="notes" maxlength="500"
                   value="<?= View::escape($notes) ?>"
                   <?= !$canSubmit ? ' disabled' : '' ?>
                   placeholder="optional">
          </label>
        </div>
        <div class="dg-form-actions">
          <?php if ($canSubmit) : ?>
            <button type="submit" class="dg-button dg-button--primary">Tagesabschluss speichern</button>
          <?php endif; ?>
        </div>
      </form>
    <?php endif; ?>
  <?php endif; ?>

  <div class="dg-panel">
    <h2 class="dg-subsection-title">Letzte Abschlüsse</h2>
    <div class="dg-table-wrap">
      <table class="dg-table">
        <thead>
          <tr>
            <th>Tag</th>
            <th>Kasse</th>
            <th class="dg-table__num">Bar</th>
            <th class="dg-table__num">EC</th>
            <th class="dg-table__num">Karte</th>
            <th class="dg-table__num">Gutschein</th>
            <th class="dg-table__num">Bons</th>
            <th class="dg-table__num">Differenz</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($closings === []) : ?>
            <tr>
              <td colspan="8" class="dg-table__empty">Noch keine Abschlüsse.</td>
            </tr>
          <?php else : ?>
            <?php foreach ($closings as $row) : ?>
              <?php
                $rLabel = trim((string) ($row['register_code'] ?? ''));
                if (trim((string) ($row['register_name'] ?? '')) !== '') {
                    $rLabel .= ($rLabel !== '' ? ' — ' : '') . trim((string) $row['register_name']);
                }
              ?>
              <tr>
                <td><?= View::escape((string) ($row['business_date'] ?? '')) ?></td>
                <td><?= View::escape($rLabel !== '' ? $rLabel : '#' . (int) ($row['register_id'] ?? 0)) ?></td>
                <td class="dg-table__num"><?= View::escape($fmt((float) ($row['total_cash'] ?? 0))) ?></td>
                <td class="dg-table__num"><?= View::escape($fmt((float) ($row['total_ec'] ?? 0))) ?></td>
                <td class="dg-table__num"><?= View::escape($fmt((float) ($row['total_card'] ?? 0))) ?></td>
                <td class="dg-table__num"><?= View::escape($fmt((float) ($row['total_voucher'] ?? 0))) ?></td>
                <td class="dg-table__num"><?= (int) ($row['receipt_count'] ?? 0) ?></td>
                <td class="dg-table__num"><?= View::escape($fmt((float) ($row['difference'] ?? 0))) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
