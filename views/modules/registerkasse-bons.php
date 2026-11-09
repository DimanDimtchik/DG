<?php
/**
 * Registerkasse — Bon-Liste + Draft starten (K7a; Layout K10c; Guard K11b).
 *
 * @var list<array<string, mixed>> $cashReceipts
 * @var list<array<string, mixed>> $cashRegisterOptions
 * @var list<array<string, mixed>> $cashOpenRegisterOptions
 * @var int $cashReceiptRegisterId
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$cashReceiptsPaged = is_array($cashReceiptsPaged ?? null) ? $cashReceiptsPaged : null;
if ($cashReceiptsPaged !== null && isset($cashReceiptsPaged['items'])) {
    $rows = $cashReceiptsPaged['items'];
    $bonsTotal = (int) ($cashReceiptsPaged['total'] ?? count($rows));
    $bonsPageNum = (int) ($cashReceiptsPaged['page'] ?? 1);
    $bonsTotalPages = (int) ($cashReceiptsPaged['total_pages'] ?? 1);
    $bonsPerPageKey = (string) ($cashReceiptsPaged['per_page_key'] ?? ListPagination::DEFAULT);
} else {
    $rows = $cashReceipts ?? [];
    $bonsTotal = count($rows);
    $bonsPageNum = 1;
    $bonsTotalPages = 1;
    $bonsPerPageKey = ListPagination::DEFAULT;
}
$registers = $cashRegisterOptions ?? [];
$openRegisters = $cashOpenRegisterOptions ?? [];
$filterReg = (int) ($cashReceiptRegisterId ?? 0);
$bonsExtraQuery = ['register_id' => $filterReg > 0 ? $filterReg : null];
$csrf = Csrf::token();
$statusLabels = [
    'draft' => 'Entwurf',
    'paying' => 'Zahlend',
    'finalized' => 'Festgeschrieben',
    'cancelled' => 'Abgebrochen',
];
?>
<div class="dg-wrap dg-cash-bon">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Bons</h1>
      <p class="dg-lead">Verkaufsbons je Kasse — neuer Bon startet als Entwurf.</p>
    </div>
    <div class="dg-page-header__actions">
      <a class="dg-button" href="/app?page=registerkasse-journal">Zum Bon-Journal</a>
    </div>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Datenbank nicht verbunden.
      <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Einstellungen → Datenbank</a>
    </div>
  <?php endif; ?>

  <?php
    $cashTseOutages = $cashTseOutages ?? [];
    View::render('partials/cash-tse-outage-banner', compact('cashTseOutages'));
  ?>

  <?php if ($canEdit && $dbConnected && $openRegisters !== []) : ?>
    <form class="dg-form dg-panel" method="post" action="/app?page=registerkasse-bons">
      <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
      <input type="hidden" name="cash_receipt_start" value="1">
      <h2 class="dg-subsection-title">Bon starten</h2>
      <div class="dg-form-grid">
        <label class="dg-field">
          <span>Kasse *</span>
          <select name="register_id" required>
            <option value="">— bitte wählen —</option>
            <?php foreach ($openRegisters as $reg) : ?>
              <?php
                $rid = (int) ($reg['id'] ?? 0);
                $label = trim((string) ($reg['code'] ?? ''));
                if (trim((string) ($reg['name'] ?? '')) !== '') {
                    $label .= ($label !== '' ? ' — ' : '') . trim((string) $reg['name']);
                }
                $loc = trim((string) ($reg['location_code'] ?? ''));
                if ($loc !== '') {
                    $label .= ' (' . $loc . ')';
                }
              ?>
              <option value="<?= $rid ?>"<?= $filterReg === $rid ? ' selected' : '' ?>>
                <?= View::escape($label !== '' ? $label : '#' . $rid) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <small class="dg-field-hint">Nur Kassen mit offener Sitzung.</small>
        </label>
        <label class="dg-field">
          <span>Aus Beleg / Auftrag (optional)</span>
          <input type="number" name="voucher_id" min="1" step="1" inputmode="numeric"
                 placeholder="Beleg-ID"
                 value="">
          <small class="dg-field-hint">Wenn gesetzt: Positionen aus dem Ausgangsbeleg übernehmen (gleiche Kassen-Pipeline).</small>
        </label>
      </div>
      <div class="dg-form-actions">
        <button type="submit" class="dg-button dg-button--primary">Bon starten</button>
      </div>
    </form>
  <?php elseif ($canEdit && $dbConnected && $registers !== []) : ?>
    <div class="dg-flash dg-flash--warning">
      Keine offene Kassensitzung — bitte zuerst eine Kasse öffnen.
      <a class="dg-button dg-button--small" href="/app?page=registerkasse-kassen">Zu den Kassen</a>
    </div>
  <?php elseif ($canEdit && $dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Keine aktive Kasse.
      <a class="dg-button dg-button--small" href="/app?page=registerkasse-kassen">Zu den Kassen</a>
    </div>
  <?php endif; ?>

  <form class="dg-panel dg-form" method="get" action="/app">
    <input type="hidden" name="page" value="registerkasse-bons">
    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Filter Kasse</span>
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
    </div>
    <div class="dg-form-actions">
      <button type="submit" class="dg-button">Filtern</button>
    </div>
  </form>

  <div class="dg-panel">
    <?php
      View::partial('partials/list-per-page', [
          'total' => $bonsTotal,
          'perPageKey' => $bonsPerPageKey,
          'listPage' => 'registerkasse-bons',
          'extraQuery' => $bonsExtraQuery,
      ]);
    ?>
    <div class="dg-table-wrap">
      <table class="dg-table">
        <thead>
          <tr>
            <th>Bon</th>
            <th>Kasse</th>
            <th>Status</th>
            <th class="dg-cash-bon__col-started">Gestartet</th>
            <th class="dg-table__num">Brutto</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($rows === []) : ?>
            <tr>
              <td colspan="6" class="dg-table__empty">Noch keine Bons.</td>
            </tr>
          <?php else : ?>
            <?php foreach ($rows as $row) : ?>
              <?php
                $id = (int) ($row['id'] ?? 0);
                $status = (string) ($row['status'] ?? '');
                $statusLabel = $statusLabels[$status] ?? $status;
                $regLabel = trim((string) ($row['register_code'] ?? ''));
                if ($regLabel !== '' && trim((string) ($row['register_name'] ?? '')) !== '') {
                    $regLabel .= ' — ' . trim((string) $row['register_name']);
                }
                $display = trim((string) ($row['receipt_number_display'] ?? ''));
                if ($display === '') {
                    $display = (string) (int) ($row['receipt_number'] ?? 0);
                }
                $badge = $status === 'finalized' ? 'dg-badge--ok'
                    : ($status === 'cancelled' ? 'dg-badge--error'
                        : ($status === 'paying' ? 'dg-badge--pending' : 'dg-badge--muted'));
              ?>
              <tr>
                <td><strong><?= View::escape($display) ?></strong></td>
                <td><?= View::escape($regLabel !== '' ? $regLabel : '—') ?></td>
                <td><span class="dg-badge <?= $badge ?>"><?= View::escape($statusLabel) ?></span></td>
                <td class="dg-cash-bon__col-started"><?= View::escape((string) ($row['started_at'] ?? '—')) ?></td>
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
    <?php
      View::partial('partials/list-pagination-nav', [
          'total' => $bonsTotal,
          'page' => $bonsPageNum,
          'totalPages' => $bonsTotalPages,
          'perPageKey' => $bonsPerPageKey,
          'listPage' => 'registerkasse-bons',
          'extraQuery' => $bonsExtraQuery,
      ]);
    ?>
  </div>
</div>
