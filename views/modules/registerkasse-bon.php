<?php
/**
 * Registerkasse — Bon-Detail (K7a–K7c; Layout K10c).
 *
 * @var array<string, mixed> $cashReceipt
 * @var list<array<string, mixed>> $cashReceiptLines
 * @var list<array<string, mixed>> $cashReceiptPayments
 * @var array<string, mixed>|null $cashReceiptRegister
 * @var array<string, mixed>|null $cashReceiptEditLine
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$receipt = $cashReceipt ?? [];
$lines = $cashReceiptLines ?? [];
$payments = $cashReceiptPayments ?? [];
$register = $cashReceiptRegister ?? null;
$editLine = $cashReceiptEditLine ?? null;
$receiptId = (int) ($receipt['id'] ?? 0);
$status = (string) ($receipt['status'] ?? '');
$isDraft = $status === 'draft';
$isPaying = $status === 'paying';
$canChange = $canEdit && $dbConnected && $isDraft;
$canPay = $canEdit && $dbConnected && ($isDraft || $isPaying);
$csrf = Csrf::token();
$statusLabels = [
    'draft' => 'Entwurf',
    'paying' => 'Zahlend',
    'finalized' => 'Festgeschrieben',
    'cancelled' => 'Abgebrochen',
];
$methodLabels = CashPaymentRepository::methodLabels();
$display = trim((string) ($receipt['receipt_number_display'] ?? ''));
if ($display === '') {
    $display = (string) (int) ($receipt['receipt_number'] ?? 0);
}
$regLabel = '';
if (is_array($register)) {
    $regLabel = trim((string) ($register['code'] ?? '') . ' — ' . (string) ($register['name'] ?? ''));
}
$badge = $status === 'finalized' ? 'dg-badge--ok'
    : ($status === 'cancelled' ? 'dg-badge--error'
        : ($status === 'paying' ? 'dg-badge--pending' : 'dg-badge--muted'));
$editing = is_array($editLine) && (int) ($editLine['id'] ?? 0) > 0;
$formTitle = $editing ? (string) ($editLine['title'] ?? '') : '';
$formQty = $editing ? (string) ($editLine['quantity'] ?? '1') : '1';
$formPrice = $editing ? (string) ($editLine['unit_price_gross'] ?? '0') : '';
$formUnit = $editing ? (string) ($editLine['unit'] ?? 'Stück') : 'Stück';
$formTax = $editing ? (int) ($editLine['tax_rate'] ?? 19) : 19;
$formArticleNo = $editing ? (string) ($editLine['article_number'] ?? '') : '';
$bonUrl = '/app?page=registerkasse-bon&amp;id=' . $receiptId;
$totalGross = round((float) ($receipt['total_gross'] ?? 0), 2);
$paidTotal = 0.0;
foreach ($payments as $payRow) {
    $paidTotal += round((float) ($payRow['amount'] ?? 0), 2);
}
$paidTotal = round($paidTotal, 2);
$openAmount = round($totalGross - $paidTotal, 2);
$defaultPayAmount = $openAmount > 0 ? number_format($openAmount, 2, ',', '') : '';
?>
<div class="dg-wrap dg-cash-bon">
  <?php View::render('partials/flash', compact('flash')); ?>

  <a class="dg-button dg-button--back" href="/app?page=registerkasse-bons">← Zurück zur Bon-Liste</a>

  <header class="dg-page-header">
    <h1 class="dg-page-title">Bon <?= View::escape($display !== '' ? $display : '#' . $receiptId) ?></h1>
    <p class="dg-lead">
      <span class="dg-badge <?= $badge ?>"><?= View::escape($statusLabels[$status] ?? $status) ?></span>
      <?php if ($regLabel !== ' — ' && $regLabel !== '') : ?>
        · Kasse <?= View::escape($regLabel) ?>
      <?php endif; ?>
    </p>
  </header>

  <div class="dg-cash-bon__totals dg-panel" aria-live="polite">
    <div class="dg-btn-row">
      <span>Brutto <strong><?= View::escape(number_format($totalGross, 2, ',', '.')) ?> €</strong></span>
      <span>Bezahlt <strong><?= View::escape(number_format($paidTotal, 2, ',', '.')) ?> €</strong></span>
      <span>Offen <strong><?= View::escape(number_format($openAmount, 2, ',', '.')) ?> €</strong></span>
    </div>
  </div>

  <div class="dg-panel">
    <h2 class="dg-subsection-title">Kopf</h2>
    <dl class="dg-dl">
      <dt>Bonnummer</dt>
      <dd><?= View::escape($display !== '' ? $display : '—') ?></dd>
      <dt>Typ</dt>
      <dd><?= View::escape((string) ($receipt['receipt_type'] ?? 'sale')) ?></dd>
      <dt>Bediener-ID</dt>
      <dd><?= View::escape((string) ((int) ($receipt['operator_id'] ?? 0) ?: '—')) ?></dd>
      <dt>Sitzung</dt>
      <dd><?= View::escape((string) ((int) ($receipt['session_id'] ?? 0) ?: '—')) ?></dd>
      <dt>Gestartet</dt>
      <dd><?= View::escape((string) ($receipt['started_at'] ?? '—')) ?></dd>
      <dt>Brutto / Netto</dt>
      <dd>
        <?= View::escape(number_format($totalGross, 2, ',', '.')) ?> €
        /
        <?= View::escape(number_format((float) ($receipt['total_net'] ?? 0), 2, ',', '.')) ?> €
      </dd>
      <dt>Bezahlt / Offen</dt>
      <dd>
        <?= View::escape(number_format($paidTotal, 2, ',', '.')) ?> €
        /
        <?= View::escape(number_format($openAmount, 2, ',', '.')) ?> €
      </dd>
    </dl>
    <?php if ($isPaying) : ?>
      <p class="dg-field-hint">Zahlung läuft — Positionen sind gesperrt. Ohne Zahlungen wird der Bon wieder zum Entwurf.</p>
    <?php elseif (!$isDraft) : ?>
      <p class="dg-field-hint">Festgeschrieben oder abgebrochen — keine Änderungen mehr.</p>
    <?php endif; ?>
  </div>

  <div class="dg-panel">
    <h2 class="dg-subsection-title">Positionen</h2>
    <div class="dg-table-wrap">
      <table class="dg-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Artikel</th>
            <th class="dg-table__num">Menge</th>
            <th class="dg-table__num dg-cash-bon__col-unit">Einzel</th>
            <th class="dg-table__num">Summe</th>
            <th class="dg-table__num dg-cash-bon__col-tax">USt</th>
            <?php if ($canChange) : ?><th></th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if ($lines === []) : ?>
            <tr>
              <td colspan="<?= $canChange ? 7 : 6 ?>" class="dg-table__empty">Noch keine Positionen.</td>
            </tr>
          <?php else : ?>
            <?php foreach ($lines as $line) : ?>
              <?php $lid = (int) ($line['id'] ?? 0); ?>
              <tr>
                <td><?= (int) ($line['line_no'] ?? 0) ?></td>
                <td>
                  <?= View::escape((string) ($line['title'] ?? '')) ?>
                  <?php if (trim((string) ($line['article_number'] ?? '')) !== '') : ?>
                    <span class="dg-muted">(<?= View::escape((string) $line['article_number']) ?>)</span>
                  <?php endif; ?>
                </td>
                <td class="dg-table__num"><?= View::escape(rtrim(rtrim(number_format((float) ($line['quantity'] ?? 0), 3, ',', '.'), '0'), ',')) ?>
                  <?= View::escape((string) ($line['unit'] ?? '')) ?></td>
                <td class="dg-table__num dg-cash-bon__col-unit"><?= View::escape(number_format((float) ($line['unit_price_gross'] ?? 0), 2, ',', '.')) ?> €</td>
                <td class="dg-table__num"><?= View::escape(number_format((float) ($line['gross_amount'] ?? 0), 2, ',', '.')) ?> €</td>
                <td class="dg-table__num dg-cash-bon__col-tax"><?= (int) ($line['tax_rate'] ?? 0) ?></td>
                <?php if ($canChange) : ?>
                  <td class="dg-table__actions">
                    <div class="dg-table__actions-group">
                      <a class="dg-button dg-button--small" href="<?= $bonUrl ?>&amp;line_id=<?= $lid ?>">Ändern</a>
                      <form method="post" action="<?= $bonUrl ?>" class="dg-inline-form">
                        <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
                        <input type="hidden" name="cash_receipt_line_delete" value="1">
                        <input type="hidden" name="line_id" value="<?= $lid ?>">
                        <button type="submit" class="dg-button dg-button--small">Entfernen</button>
                      </form>
                    </div>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($canChange) : ?>
    <form class="dg-form dg-panel" method="post" action="<?= $bonUrl ?>">
      <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
      <?php if ($editing) : ?>
        <input type="hidden" name="cash_receipt_line_save" value="1">
        <input type="hidden" name="line_id" value="<?= (int) $editLine['id'] ?>">
        <h2 class="dg-subsection-title">Position ändern</h2>
      <?php else : ?>
        <input type="hidden" name="cash_receipt_line_add" value="1">
        <h2 class="dg-subsection-title">Position hinzufügen</h2>
      <?php endif; ?>
      <div class="dg-form-grid">
        <label class="dg-field dg-field--full">
          <span>Bezeichnung *</span>
          <input type="text" name="title" maxlength="255" required
                 value="<?= View::escape($formTitle) ?>"
                 placeholder="z. B. Kaffee">
        </label>
        <label class="dg-field">
          <span>Artikelnummer</span>
          <input type="text" name="article_number" maxlength="64"
                 value="<?= View::escape($formArticleNo) ?>">
        </label>
        <label class="dg-field">
          <span>Einheit</span>
          <input type="text" name="unit" maxlength="64"
                 value="<?= View::escape($formUnit) ?>">
        </label>
        <label class="dg-field">
          <span>Menge *</span>
          <input type="text" name="quantity" required inputmode="decimal"
                 value="<?= View::escape($formQty) ?>">
        </label>
        <label class="dg-field">
          <span>Einzelpreis brutto *</span>
          <input type="text" name="unit_price_gross" required inputmode="decimal"
                 value="<?= View::escape($formPrice) ?>"
                 placeholder="0,00">
        </label>
        <label class="dg-field">
          <span>USt %</span>
          <select name="tax_rate">
            <option value="19"<?= $formTax === 19 ? ' selected' : '' ?>>19</option>
            <option value="7"<?= $formTax === 7 ? ' selected' : '' ?>>7</option>
            <option value="0"<?= $formTax === 0 ? ' selected' : '' ?>>0</option>
          </select>
        </label>
      </div>
      <div class="dg-form-actions">
        <button type="submit" class="dg-button dg-button--primary">
          <?= $editing ? 'Position speichern' : 'Position hinzufügen' ?>
        </button>
        <?php if ($editing) : ?>
          <a class="dg-button" href="<?= $bonUrl ?>">Abbrechen</a>
        <?php endif; ?>
      </div>
    </form>
  <?php endif; ?>

  <div class="dg-panel">
    <h2 class="dg-subsection-title">Zahlungen</h2>
    <div class="dg-table-wrap">
      <table class="dg-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Art</th>
            <th>Betrag</th>
            <th>Referenz</th>
            <?php if ($canPay) : ?><th></th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if ($payments === []) : ?>
            <tr>
              <td colspan="<?= $canPay ? 5 : 4 ?>" class="dg-table__empty">Noch keine Zahlungen.</td>
            </tr>
          <?php else : ?>
            <?php foreach ($payments as $pay) : ?>
              <?php
                $pid = (int) ($pay['id'] ?? 0);
                $method = (string) ($pay['payment_method'] ?? '');
              ?>
              <tr>
                <td><?= (int) ($pay['line_no'] ?? 0) ?></td>
                <td><?= View::escape($methodLabels[$method] ?? $method) ?></td>
                <td><?= View::escape(number_format((float) ($pay['amount'] ?? 0), 2, ',', '.')) ?> €</td>
                <td><?= View::escape(trim((string) ($pay['external_ref'] ?? '')) !== '' ? (string) $pay['external_ref'] : '—') ?></td>
                <?php if ($canPay) : ?>
                  <td class="dg-table__actions">
                    <div class="dg-table__actions-group">
                      <form method="post" action="<?= $bonUrl ?>" class="dg-inline-form">
                        <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
                        <input type="hidden" name="cash_receipt_payment_delete" value="1">
                        <input type="hidden" name="payment_id" value="<?= $pid ?>">
                        <button type="submit" class="dg-button dg-button--small">Entfernen</button>
                      </form>
                    </div>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($canPay && $lines !== [] && $openAmount > 0) : ?>
    <form class="dg-form dg-panel" method="post" action="<?= $bonUrl ?>">
      <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
      <input type="hidden" name="cash_receipt_payment_add" value="1">
      <h2 class="dg-subsection-title">Zahlung erfassen</h2>
      <p class="dg-field-hint">Erste Zahlung setzt den Bon auf „Zahlend“ (Positionen gesperrt). Offen: <?= View::escape(number_format($openAmount, 2, ',', '.')) ?> €.</p>
      <div class="dg-form-grid">
        <label class="dg-field">
          <span>Zahlungsart *</span>
          <select name="payment_method" required>
            <?php foreach ($methodLabels as $code => $label) : ?>
              <option value="<?= View::escape($code) ?>"<?= $code === 'cash' ? ' selected' : '' ?>><?= View::escape($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dg-field">
          <span>Betrag *</span>
          <input type="text" name="amount" required inputmode="decimal"
                 value="<?= View::escape($defaultPayAmount) ?>">
        </label>
        <label class="dg-field dg-field--full">
          <span>Referenz (optional)</span>
          <input type="text" name="external_ref" maxlength="128"
                 placeholder="Terminal / Trace">
        </label>
      </div>
      <div class="dg-form-actions">
        <button type="submit" class="dg-button dg-button--primary">Zahlung speichern</button>
      </div>
    </form>
  <?php elseif ($canPay && $lines === []) : ?>
    <div class="dg-flash dg-flash--warning">Zuerst Positionen erfassen, dann zahlen.</div>
  <?php elseif ($canPay && $openAmount <= 0 && $payments !== []) : ?>
    <div class="dg-flash dg-flash--success">Vollständig bezahlt — Festschreiben folgt im nächsten Schritt.</div>
  <?php endif; ?>
</div>
