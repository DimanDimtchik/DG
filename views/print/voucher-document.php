<?php
/** @var array<string, mixed> $voucher */
/** @var string $kind */
/** @var string $kindLabel */
/** @var list<array<string, mixed>> $items */
/** @var array{documents: list<array<string, mixed>>} $chain */
/** @var array<string, mixed>|null $finalSummary */
/** @var bool $books */
/** @var bool $forEmail */
/** @var bool $showChain */
/** @var array{name: string, lines: list<string>} $companyBlock */
/** @var array{name: string, lines: list<string>} $customerBlock */
/** @var string $legalNotice */
/** @var string $footerNotice */
/** @var array{holder: string, iban: string, bank: string, bic: string}|null $primaryBank */
/** @var string $documentStatusLabel */
$customer = trim((string) ($voucher['supplier_name'] ?? ''));
$number = trim((string) ($voucher['invoice_number'] ?? ''));
$date = (string) ($voucher['voucher_date'] ?? '');
$delivery = (string) ($voucher['delivery_date'] ?? '');
$description = trim((string) ($voucher['description'] ?? ''));
$notes = trim((string) ($voucher['notes'] ?? ''));
/** @var list<array{key: string, label: string, text: string}> $legalClauseBlocks */
$introText = trim((string) ($voucher['document_intro_text'] ?? ''));
$footerText = trim((string) ($voucher['document_footer_text'] ?? ''));
$legalClauseBlocks = is_array($legalClauseBlocks ?? null) ? $legalClauseBlocks : [];
$company = $companyBlock ?? ['name' => '', 'lines' => []];
$customerBox = $customerBlock ?? ['name' => $customer, 'lines' => []];
?>
<div class="vd-letterhead"<?= !empty($forEmail) ? ' style="display:table;width:100%;margin-bottom:16px;"' : '' ?>>
  <div class="vd-letterhead__col"<?= !empty($forEmail) ? ' style="display:table-cell;vertical-align:top;width:50%;"' : '' ?>>
    <strong><?= View::escape((string) ($company['name'] ?? '')) ?></strong><br>
    <?php foreach (($company['lines'] ?? []) as $line) : ?>
      <?= View::escape((string) $line) ?><br>
    <?php endforeach; ?>
  </div>
  <div class="vd-letterhead__col vd-letterhead__col--right"<?= !empty($forEmail) ? ' style="display:table-cell;vertical-align:top;width:50%;text-align:right;"' : '' ?>>
    <?php if ((string) ($customerBox['name'] ?? '') !== '') : ?>
      <strong><?= View::escape((string) $customerBox['name']) ?></strong><br>
    <?php endif; ?>
    <?php foreach (($customerBox['lines'] ?? []) as $line) : ?>
      <?= View::escape((string) $line) ?><br>
    <?php endforeach; ?>
  </div>
</div>

<p class="vd-doc-title"<?= !empty($forEmail) ? ' style="font-size:20px;font-weight:700;margin:0 0 8px;"' : '' ?>><?= View::escape($kindLabel) ?></p>
<p class="vd-doc-meta"<?= !empty($forEmail) ? ' style="color:#5c6678;font-size:12px;margin:0 0 16px;"' : '' ?>>
  <?php if ($number !== '') : ?>Nr. <?= View::escape($number) ?> · <?php endif; ?>
  <?php if ($date !== '') : ?>Datum <?= View::escape(date('d.m.Y', strtotime($date) ?: time())) ?><?php endif; ?>
  <?php if ($delivery !== '' && $delivery !== $date) : ?>
    · Lieferdatum <?= View::escape(date('d.m.Y', strtotime($delivery) ?: time())) ?>
  <?php endif; ?>
  <?php if ((string) ($documentStatusLabel ?? '') !== '') : ?>
    · Status <?= View::escape((string) $documentStatusLabel) ?>
  <?php endif; ?>
</p>

<?php if ($description !== '') : ?>
  <p><strong>Betreff:</strong> <?= View::escape($description) ?></p>
<?php endif; ?>

<?php if (($legalNotice ?? '') !== '') : ?>
  <div class="vd-notice<?= !$books ? ' vd-notice--warn' : '' ?>"<?= !empty($forEmail) ? ' style="background:#f4f6f9;padding:8px 12px;margin:12px 0;font-size:12px;"' : '' ?>>
    <?= View::escape((string) $legalNotice) ?>
  </div>
<?php endif; ?>

<?php if (!empty($showChain) && ($chain['documents'] ?? []) !== []) : ?>
  <h2>Belegkette (intern)</h2>
  <table>
    <thead>
      <tr>
        <th>Dokument</th>
        <th>Status</th>
        <th>Nr.</th>
        <th>Datum</th>
        <th class="num">Betrag</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($chain['documents'] as $doc) : ?>
        <tr<?= !empty($doc['is_current']) ? ' style="font-weight:700;"' : '' ?>>
          <td><?= View::escape((string) ($doc['document_label'] ?? '')) ?><?= !empty($doc['is_current']) ? ' (dieses Dokument)' : '' ?></td>
          <td><?= View::escape((string) ($doc['document_status_label'] ?? '—')) ?></td>
          <td><?= View::escape((string) ($doc['invoice_number'] ?? '')) ?></td>
          <td><?= View::escape((string) ($doc['voucher_date'] ?? '')) ?></td>
          <td class="num"><?= View::escape((string) ($doc['gross_display'] ?? '')) ?> €</td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php if (is_array($finalSummary) && ($finalSummary['partials'] ?? []) !== []) : ?>
  <h2>Abzug Abschlagsrechnungen</h2>
  <table>
    <thead>
      <tr><th>Rechnung</th><th>Datum</th><th class="num">Betrag</th></tr>
    </thead>
    <tbody>
      <?php foreach ($finalSummary['partials'] as $partial) : ?>
        <tr>
          <td><?= View::escape((string) ($partial['invoice_number'] ?? '')) ?></td>
          <td><?= View::escape((string) ($partial['voucher_date'] ?? '')) ?></td>
          <td class="num">− <?= View::escape((string) ($partial['gross_display'] ?? '')) ?> €</td>
        </tr>
      <?php endforeach; ?>
      <tr class="total">
        <td colspan="2">Auftragssumme / Rest</td>
        <td class="num"><?= View::escape((string) ($finalSummary['order_total_display'] ?? '')) ?> / <?= View::escape((string) ($finalSummary['remaining_display'] ?? '')) ?> €</td>
      </tr>
    </tbody>
  </table>
<?php endif; ?>

<?php if ($introText !== '') : ?>
  <div class="vd-intro"<?= !empty($forEmail) ? ' style="margin:12px 0;font-size:14px;"' : '' ?>>
    <?= nl2br(View::escape($introText)) ?>
  </div>
<?php endif; ?>

<h2>Positionen</h2>
<?php if ($items === []) : ?>
  <p>Keine Positionen erfasst.</p>
<?php else : ?>
  <table>
    <thead>
      <tr>
        <th>Bezeichnung</th>
        <th class="num">Menge</th>
        <th class="num">Einzelpreis</th>
        <th class="num">USt %</th>
        <th class="num">Brutto</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $item) : ?>
        <?php
          $title = trim((string) ($item['title'] ?? ''));
          if ($title === '') {
              continue;
          }
        ?>
        <tr>
          <td><?= View::escape($title) ?></td>
          <td class="num"><?= View::escape((string) ($item['quantity'] ?? '1')) ?> <?= View::escape((string) ($item['unit'] ?? '')) ?></td>
          <td class="num"><?= $fmt((float) ($item['unit_price_gross'] ?? 0)) ?> €</td>
          <td class="num"><?= (int) ($item['tax_rate'] ?? 19) ?> %</td>
          <td class="num"><?= $fmt((float) ($item['gross_amount'] ?? 0)) ?> €</td>
        </tr>
      <?php endforeach; ?>
      <tr class="total">
        <td colspan="4">Gesamt (brutto)</td>
        <td class="num"><?= $fmt((float) ($voucher['gross_amount'] ?? 0)) ?> €</td>
      </tr>
    </tbody>
  </table>
<?php endif; ?>

<?php if ($books && $primaryBank !== null) : ?>
  <div class="vd-bank"<?= !empty($forEmail) ? ' style="margin-top:16px;font-size:12px;"' : '' ?>>
    <strong>Zahlungsinformationen</strong><br>
    <?php if (($primaryBank['holder'] ?? '') !== '') : ?>
      <?= View::escape((string) $primaryBank['holder']) ?><br>
    <?php endif; ?>
    IBAN <?= View::escape((string) ($primaryBank['iban'] ?? '')) ?>
    <?php if (($primaryBank['bank'] ?? '') !== '') : ?>
      · <?= View::escape((string) $primaryBank['bank']) ?>
    <?php endif; ?>
    <?php if (($primaryBank['bic'] ?? '') !== '') : ?>
      · BIC <?= View::escape((string) $primaryBank['bic']) ?>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($footerText !== '') : ?>
  <div class="vd-document-footer"<?= !empty($forEmail) ? ' style="margin-top:16px;font-size:12px;"' : ' style="margin-top:6mm;"' ?>>
    <?= nl2br(View::escape($footerText)) ?>
  </div>
<?php endif; ?>

<?php if ($legalClauseBlocks !== []) : ?>
  <div class="vd-legal-clauses"<?= !empty($forEmail) ? ' style="margin-top:12px;font-size:12px;"' : ' style="margin-top:4mm;"' ?>>
    <?php foreach ($legalClauseBlocks as $clause) : ?>
      <p class="vd-legal-clause" style="margin:0 0 3mm;padding:2mm 3mm;background:#f8f9fb;border-left:3px solid #b8942f;">
        <strong><?= View::escape((string) ($clause['label'] ?? '')) ?>:</strong>
        <?= View::escape((string) ($clause['text'] ?? '')) ?>
      </p>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($notes !== '') : ?>
  <p><strong>Notizen:</strong><br><?= nl2br(View::escape($notes)) ?></p>
<?php endif; ?>

<?php if (($footerNotice ?? '') !== '') : ?>
  <div class="vd-footer"<?= !empty($forEmail) ? ' style="margin-top:16px;font-size:11px;color:#5c6678;"' : '' ?>>
    <?= View::escape((string) $footerNotice) ?>
  </div>
<?php endif; ?>
