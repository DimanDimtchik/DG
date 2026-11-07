<?php
/**
 * Registerkasse — unveränderbares Bon-Journal (K16a).
 *
 * @var list<array<string, mixed>> $cashJournalReceipts
 * @var list<array<string, mixed>> $cashRegisterOptions
 * @var int $cashJournalRegisterId
 * @var string $cashJournalDate
 * @var string $cashJournalDateFrom
 * @var string $cashJournalDateTo
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$rows = $cashJournalReceipts ?? [];
$registers = $cashRegisterOptions ?? [];
$filterReg = (int) ($cashJournalRegisterId ?? 0);
$filterDate = (string) ($cashJournalDate ?? date('Y-m-d'));
$dateFrom = (string) ($cashJournalDateFrom ?? $filterDate);
$dateTo = (string) ($cashJournalDateTo ?? $filterDate);
$typeLabels = [
    'sale' => 'Verkauf',
    'storno' => 'Storno',
];
$methodLabels = CashPaymentRepository::methodLabels();
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Bon-Journal</h1>
      <p class="dg-lead">Festgeschriebene Bons — unveränderbar. Filter nach Kasse und Geschäftstag.</p>
    </div>
    <div class="dg-page-header__actions">
      <a class="dg-button" href="/app?page=registerkasse-bons">Zu den Arbeits-Bons</a>
    </div>
  </header>

  <div class="dg-flash dg-flash--info">
    Hier erscheinen nur festgeschriebene Bons. Entwürfe und laufende Zahlungen stehen unter
    <a class="dg-button dg-button--small" href="/app?page=registerkasse-bons">Bons</a>.
    Änderungen nur über Storno-Bon.
  </div>

  <div class="dg-flash dg-flash--info">
    Abgleich mit der Buchhaltung: Das FiBu-Kassenbuch führt Bar-Ein- und Ausgänge getrennt
    (inkl. Baranteile aus Registerkassen-Bons). Dieses Journal zeigt alle Zahlarten der Ladenkasse —
    nicht denselben Tagesabschluss wie das Kassenbuch.
    <span class="dg-btn-row">
      <a class="dg-button dg-button--small" href="/app?page=buchhaltung-kassenbuch">Zum FiBu-Kassenbuch</a>
      <a class="dg-button dg-button--small" href="/app?page=registerkasse-tagesabschluss">Zum Register-Tagesabschluss</a>
    </span>
  </div>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Datenbank nicht verbunden.
      <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Einstellungen → Datenbank</a>
    </div>
  <?php endif; ?>

  <form class="dg-panel dg-form" method="get" action="/app">
    <input type="hidden" name="page" value="registerkasse-journal">
    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Kasse</span>
        <select name="register_id">
          <option value="0"<?= $filterReg === 0 ? ' selected' : '' ?>>Alle Kassen</option>
          <?php foreach ($registers as $reg) : ?>
            <?php $rid = (int) ($reg['id'] ?? 0); ?>
            <option value="<?= $rid ?>"<?= $filterReg === $rid ? ' selected' : '' ?>>
              <?= View::escape(trim((string) ($reg['code'] ?? '') . ' — ' . (string) ($reg['name'] ?? ''))) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field">
        <span>Geschäftstag</span>
        <input type="date" name="business_date" value="<?= View::escape($filterDate) ?>">
      </label>
      <div class="dg-field dg-field--actions">
        <button type="submit" class="dg-button dg-button--primary">Anzeigen</button>
      </div>
    </div>
  </form>

  <form class="dg-panel dg-form" method="get" action="/app">
    <input type="hidden" name="page" value="registerkasse-journal">
    <input type="hidden" name="export" value="csv">
    <input type="hidden" name="business_date" value="<?= View::escape($filterDate) ?>">
    <h2 class="dg-subsection-title">CSV-Export Zeitraum</h2>
    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Kasse</span>
        <select name="register_id"<?= !$dbConnected ? ' disabled' : '' ?>>
          <option value="0"<?= $filterReg === 0 ? ' selected' : '' ?>>Alle Kassen</option>
          <?php foreach ($registers as $reg) : ?>
            <?php $rid = (int) ($reg['id'] ?? 0); ?>
            <option value="<?= $rid ?>"<?= $filterReg === $rid ? ' selected' : '' ?>>
              <?= View::escape(trim((string) ($reg['code'] ?? '') . ' — ' . (string) ($reg['name'] ?? ''))) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field">
        <span>Von</span>
        <input type="date" name="date_from" value="<?= View::escape($dateFrom) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
      </label>
      <label class="dg-field">
        <span>Bis</span>
        <input type="date" name="date_to" value="<?= View::escape($dateTo) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
      </label>
      <div class="dg-field dg-field--actions">
        <button type="submit" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>>CSV herunterladen</button>
      </div>
    </div>
    <p class="dg-field-hint">Festgeschriebene Bons im Zeitraum, Semikolon-getrennt, UTF-8 mit BOM (Excel).</p>
  </form>

  <form class="dg-panel dg-form" method="get" action="/app">
    <input type="hidden" name="page" value="registerkasse-journal">
    <input type="hidden" name="export" value="dsfinvk">
    <input type="hidden" name="business_date" value="<?= View::escape($filterDate) ?>">
    <h2 class="dg-subsection-title">DSFinV-K Struktur (Stub)</h2>
    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Von</span>
        <input type="date" name="date_from" value="<?= View::escape($dateFrom) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
      </label>
      <label class="dg-field">
        <span>Bis</span>
        <input type="date" name="date_to" value="<?= View::escape($dateTo) ?>"<?= !$dbConnected ? ' disabled' : '' ?>>
      </label>
      <div class="dg-field dg-field--actions">
        <button type="submit" class="dg-button"<?= !$dbConnected ? ' disabled' : '' ?>>ZIP herunterladen</button>
      </div>
    </div>
    <p class="dg-field-hint">
      Leeres Pflichtpaket (20 CSV nur mit Kopfzeilen, index.xml, DTD) für die Prüfschnittstelle —
      noch ohne Bon-Daten. Befüllung folgt später.
    </p>
  </form>

  <div class="dg-panel">
    <div class="dg-table-wrap">
      <table class="dg-table">
        <thead>
          <tr>
            <th>Bon</th>
            <th>Typ</th>
            <th>Kasse</th>
            <th>Geschäftstag</th>
            <th>Fertig</th>
            <th>Zahlarten</th>
            <th class="dg-table__num">Brutto</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($rows === []) : ?>
            <tr>
              <td colspan="8" class="dg-table__empty">Keine festgeschriebenen Bons für diesen Filter.</td>
            </tr>
          <?php else : ?>
            <?php foreach ($rows as $row) : ?>
              <?php
                $id = (int) ($row['id'] ?? 0);
                $type = (string) ($row['receipt_type'] ?? 'sale');
                $regLabel = trim((string) ($row['register_code'] ?? ''));
                if ($regLabel !== '' && trim((string) ($row['register_name'] ?? '')) !== '') {
                    $regLabel .= ' — ' . trim((string) $row['register_name']);
                }
                $display = trim((string) ($row['receipt_number_display'] ?? ''));
                if ($display === '') {
                    $display = (string) (int) ($row['receipt_number'] ?? 0);
                }
                $payParts = [];
                $payJson = $row['payment_summary_json'] ?? null;
                $payData = is_string($payJson) ? json_decode($payJson, true) : $payJson;
                if (is_array($payData)) {
                    foreach ($payData as $payRow) {
                        if (!is_array($payRow)) {
                            continue;
                        }
                        $m = (string) ($payRow['method'] ?? '');
                        $a = round((float) ($payRow['amount'] ?? 0), 2);
                        $label = $methodLabels[$m] ?? $m;
                        $payParts[] = $label . ' ' . number_format($a, 2, ',', '.') . ' €';
                    }
                }
                $typeBadge = $type === 'storno' ? 'dg-badge--warning' : 'dg-badge--ok';
              ?>
              <tr>
                <td>
                  <strong><?= View::escape($display) ?></strong>
                  <?php if (!empty($row['tse_outage'])) : ?>
                    <span class="dg-badge dg-badge--error" title="TSE-Ausfall">TSE-Ausfall</span>
                  <?php endif; ?>
                </td>
                <td><span class="dg-badge <?= $typeBadge ?>"><?= View::escape($typeLabels[$type] ?? $type) ?></span></td>
                <td><?= View::escape($regLabel !== '' ? $regLabel : '—') ?></td>
                <td><?= View::escape((string) ($row['business_date'] ?? '—')) ?></td>
                <td><?= View::escape((string) ($row['finished_at'] ?? '—')) ?></td>
                <td><?= View::escape($payParts !== [] ? implode(' · ', $payParts) : '—') ?></td>
                <td class="dg-table__num"><?= View::escape(number_format((float) ($row['total_gross'] ?? 0), 2, ',', '.')) ?> €</td>
                <td class="dg-table__actions">
                  <div class="dg-table__actions-group">
                    <?php if ($id > 0) : ?>
                      <a class="dg-button dg-button--small" href="/app?page=registerkasse-bon&amp;id=<?= $id ?>">Anzeigen</a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
