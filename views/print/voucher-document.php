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
/** @var bool $customerFacing */
/** @var array{name: string, lines: list<string>} $companyBlock */
/** @var array{name: string, lines: list<string>} $customerBlock */
/** @var string $legalNotice */
/** @var string $footerNotice */
/** @var array{holder: string, iban: string, bank: string, bic: string}|null $primaryBank */
/** @var string $documentStatusLabel */
/** @var array{net: float, tax: float, gross: float, by_rate: list<array{rate: int, net: float, tax: float, gross: float}>}|null $totalsBreakdown */
/** @var string $introTextResolved */
/** @var string $footerTextResolved */
/** @var string $validUntil */
/** @var string $internalNotes */
$customer = trim((string) ($voucher['supplier_name'] ?? ''));
$number = trim((string) ($voucher['invoice_number'] ?? ''));
$date = (string) ($voucher['voucher_date'] ?? '');
$delivery = (string) ($voucher['delivery_date'] ?? '');
$description = trim((string) ($voucher['description'] ?? ''));
$customerFacing = !empty($customerFacing);
$introText = trim((string) ($introTextResolved ?? ($voucher['document_intro_text'] ?? '')));
$footerText = trim((string) ($footerTextResolved ?? ($voucher['document_footer_text'] ?? '')));
$notes = trim((string) ($internalNotes ?? ''));
/** @var list<array{key: string, label: string, text: string}> $legalClauseBlocks */
$legalClauseBlocks = is_array($legalClauseBlocks ?? null) ? $legalClauseBlocks : [];
$company = $companyBlock ?? ['name' => '', 'lines' => [], 'owner' => ''];
$customerBox = $customerBlock ?? ['name' => $customer, 'lines' => []];
$logoUrl = (string) ($logoUrl ?? '');
$logoAlt = (string) ($logoAlt ?? '');
$logoShapeClass = (string) ($logoShapeClass ?? 'wide');
/** @var list<string> $mandatoryLines */
$mandatoryLines = is_array($mandatoryLines ?? null) ? $mandatoryLines : [];
$totals = is_array($totalsBreakdown ?? null) ? $totalsBreakdown : [
    'net' => 0.0,
    'tax' => 0.0,
    'gross' => (float) ($voucher['gross_amount'] ?? 0),
    'by_rate' => [],
];
$isOffer = ($kind ?? '') === VoucherDocumentKind::OFFER;
$validUntilRaw = trim((string) ($validUntil ?? ''));
?>
<div class="vd-letterhead"<?= !empty($forEmail) ? ' style="display:table;width:100%;margin-bottom:16px;"' : '' ?>>
  <div class="vd-letterhead__col"<?= !empty($forEmail) ? ' style="display:table-cell;vertical-align:top;width:50%;"' : '' ?>>
    <?php if ($logoUrl !== '') : ?>
      <div class="vd-logo vd-logo--<?= View::escape($logoShapeClass) ?>">
        <img src="<?= View::escape($logoUrl) ?>" alt="<?= View::escape($logoAlt) ?>" class="vd-logo__img">
      </div>
    <?php endif; ?>
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
  <?php if ($isOffer && $validUntilRaw !== '') : ?>
    · gültig bis <?= View::escape(date('d.m.Y', strtotime($validUntilRaw) ?: time())) ?>
  <?php elseif ($delivery !== '' && $delivery !== $date && !$isOffer) : ?>
    · Lieferdatum <?= View::escape(date('d.m.Y', strtotime($delivery) ?: time())) ?>
  <?php endif; ?>
  <?php if (!$customerFacing && (string) ($documentStatusLabel ?? '') !== '') : ?>
    · Status <?= View::escape((string) $documentStatusLabel) ?>
  <?php endif; ?>
</p>

<?php if ($description !== '') : ?>
  <p><strong>Betreff:</strong> <?= View::escape($description) ?></p>
<?php endif; ?>

<?php if (!$customerFacing && ($legalNotice ?? '') !== '') : ?>
  <div class="vd-notice no-print<?= !$books ? ' vd-notice--warn' : '' ?>"<?= !empty($forEmail) ? ' style="background:#f4f6f9;padding:8px 12px;margin:12px 0;font-size:12px;"' : '' ?>>
    <?= View::escape((string) $legalNotice) ?>
    <span class="vd-notice__hint"> (nur intern, nicht druckbar)</span>
  </div>
<?php endif; ?>

<?php if (!empty($showChain) && ($chain['documents'] ?? []) !== []) : ?>
  <div class="vd-chain no-print">
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
  </div>
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
  <table class="vd-positions">
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
    </tbody>
  </table>
<?php endif; ?>

<table class="vd-totals">
  <tbody>
    <tr>
      <td>Zwischensumme (netto)</td>
      <td class="num"><?= $fmt((float) ($totals['net'] ?? 0)) ?> €</td>
    </tr>
    <?php foreach (($totals['by_rate'] ?? []) as $taxRow) : ?>
      <?php
        $rate = (int) ($taxRow['rate'] ?? 0);
        $taxAmt = (float) ($taxRow['tax'] ?? 0);
        $label = $rate === 0
          ? 'Umsatzsteuer 0 %'
          : 'zzgl. ' . $rate . ' % USt.';
      ?>
      <tr>
        <td><?= View::escape($label) ?></td>
        <td class="num"><?= $taxAmt == 0.0 && $rate === 0 ? '—' : $fmt($taxAmt) . ' €' ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (($totals['by_rate'] ?? []) === [] && (float) ($totals['tax'] ?? 0) > 0) : ?>
      <tr>
        <td>zzgl. Umsatzsteuer</td>
        <td class="num"><?= $fmt((float) $totals['tax']) ?> €</td>
      </tr>
    <?php endif; ?>
    <tr class="total">
      <td>Gesamtbetrag</td>
      <td class="num"><?= $fmt((float) ($totals['gross'] ?? 0)) ?> €</td>
    </tr>
  </tbody>
</table>

<?php if (is_array($depositBlock ?? null) && ($depositBlock['label'] ?? '') !== '') : ?>
  <div class="vd-deposit"<?= !empty($forEmail) ? ' style="margin-top:12px;font-size:12px;padding:8px 12px;background:#fafbfc;border:1px solid #e5e8ee;"' : '' ?>>
    <strong><?= View::escape((string) $depositBlock['label']) ?></strong>
    <?php if (($depositBlock['amount_label'] ?? '') !== '') : ?>
      — <?= View::escape((string) $depositBlock['amount_label']) ?>
    <?php endif; ?>
    <?php if (($depositBlock['text'] ?? '') !== '') : ?>
      <div style="margin-top:2mm;"><?= nl2br(View::escape((string) $depositBlock['text'])) ?></div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if (trim((string) ($kleinunternehmerHint ?? '')) !== '') : ?>
  <div class="vd-kleinunternehmer"<?= !empty($forEmail) ? ' style="margin-top:12px;font-size:12px;padding:8px 12px;background:#f8f9fb;border-left:3px solid #b8942f;"' : '' ?>>
    <?= nl2br(View::escape(trim((string) $kleinunternehmerHint))) ?>
  </div>
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

<?php
$paymentTermsText = trim((string) ($paymentTermsText ?? ''));
?>
<?php if ($paymentTermsText !== '') : ?>
  <div class="vd-payment-terms"<?= !empty($forEmail) ? ' style="margin-top:12px;font-size:12px;"' : ' style="margin-top:4mm;font-size:12px;"' ?>>
    <?= nl2br(View::escape($paymentTermsText)) ?>
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

<?php if (!$customerFacing && $notes !== '') : ?>
  <p class="no-print"><strong>Interne Notizen:</strong><br><?= nl2br(View::escape($notes)) ?></p>
<?php endif; ?>

<?php if (!$customerFacing && ($footerNotice ?? '') !== '') : ?>
  <div class="vd-footer no-print"<?= !empty($forEmail) ? ' style="margin-top:16px;font-size:11px;color:#5c6678;"' : '' ?>>
    <?= View::escape((string) $footerNotice) ?>
  </div>
<?php endif; ?>

<?php if ($mandatoryLines !== []) : ?>
  <div class="vd-mandatory"<?= !empty($forEmail) ? ' style="margin-top:12px;font-size:10px;color:#5c6678;"' : '' ?>>
    <?php foreach ($mandatoryLines as $line) : ?>
      <div><?= View::escape((string) $line) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (is_array($provenanceBlock ?? null) && ($provenanceBlock['lines'] ?? []) !== []) : ?>
  <div class="vd-provenance"<?= !empty($forEmail) ? ' style="margin-top:12px;font-size:10px;color:#5c6678;"' : '' ?>>
    <?php foreach ($provenanceBlock['lines'] as $pline) : ?>
      <div><?= View::escape((string) $pline) ?></div>
    <?php endforeach; ?>
    <?php if (($provenanceBlock['link_url'] ?? '') !== '' && ($provenanceBlock['link_label'] ?? '') !== '') : ?>
      <div><a href="<?= View::escape((string) $provenanceBlock['link_url']) ?>"><?= View::escape((string) $provenanceBlock['link_label']) ?></a></div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if (is_array($signatureBlock ?? null)) : ?>
  <?php
    $sigPlace = trim((string) ($signatureBlock['place'] ?? ''));
    $sigDate = trim((string) ($signatureBlock['date_label'] ?? ''));
    $sigPlaceDate = trim(($sigPlace !== '' ? $sigPlace : 'Ort') . ', den ' . ($sigDate !== '' ? $sigDate : '…………'));
    $sigClient = trim((string) ($signatureBlock['client_name'] ?? ''));
    $sigContractor = trim((string) ($signatureBlock['contractor_name'] ?? ''));
  ?>
  <div class="vd-signatures"<?= !empty($forEmail) ? ' style="margin-top:24px;font-size:12px;"' : '' ?>>
    <p class="vd-signatures__place"><?= View::escape($sigPlaceDate) ?></p>
    <div class="vd-signatures__cols"<?= !empty($forEmail) ? ' style="display:table;width:100%;margin-top:16px;"' : '' ?>>
      <div class="vd-signatures__col"<?= !empty($forEmail) ? ' style="display:table-cell;width:48%;vertical-align:top;padding-right:4%;"' : '' ?>>
        <strong>Auftraggeber</strong>
        <?php if ($sigClient !== '') : ?>
          <div class="vd-signatures__name"><?= View::escape($sigClient) ?></div>
        <?php endif; ?>
        <div class="vd-signatures__line"></div>
        <div class="vd-signatures__caption">Unterschrift</div>
      </div>
      <div class="vd-signatures__col"<?= !empty($forEmail) ? ' style="display:table-cell;width:48%;vertical-align:top;"' : '' ?>>
        <strong>Auftragnehmer</strong>
        <?php if ($sigContractor !== '') : ?>
          <div class="vd-signatures__name"><?= View::escape($sigContractor) ?></div>
        <?php endif; ?>
        <div class="vd-signatures__line"></div>
        <div class="vd-signatures__caption">Unterschrift</div>
      </div>
    </div>
  </div>
<?php endif; ?>
