<?php
/** @var array<string, mixed> $voucher */
/** @var string $kindLabel */
/** @var list<array<string, mixed>> $items */
/** @var array{documents: list<array<string, mixed>>} $chain */
/** @var array<string, mixed>|null $finalSummary */
/** @var bool $books */
$customer = trim((string) ($voucher['supplier_name'] ?? ''));
$number = trim((string) ($voucher['invoice_number'] ?? ''));
$date = (string) ($voucher['voucher_date'] ?? '');
$delivery = (string) ($voucher['delivery_date'] ?? '');
?>
<p><strong><?= View::escape($kindLabel) ?></strong>
  <?php if ($number !== '') : ?> · Nr. <?= View::escape($number) ?><?php endif; ?>
  <?php if ($date !== '') : ?> · Datum <?= View::escape(date('d.m.Y', strtotime($date) ?: time())) ?><?php endif; ?>
</p>
<?php if ($customer !== '') : ?>
  <p><strong>Kunde:</strong> <?= View::escape($customer) ?></p>
<?php endif; ?>
<?php if ($delivery !== '' && $delivery !== $date) : ?>
  <p><strong>Lieferdatum:</strong> <?= View::escape(date('d.m.Y', strtotime($delivery) ?: time())) ?></p>
<?php endif; ?>
<?php if (!$books) : ?>
  <p class="dg-muted" style="color:#8a6410;">Unverbindlich — keine Buchung / keine UStVA-Meldung.</p>
<?php endif; ?>

<?php if (($chain['documents'] ?? []) !== []) : ?>
  <h2 style="font-size:12pt;margin:6mm 0 2mm;">Belegkette</h2>
  <table>
    <thead>
      <tr>
        <th>Dokument</th>
        <th>Nr.</th>
        <th>Datum</th>
        <th class="num">Betrag</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($chain['documents'] as $doc) : ?>
        <tr<?= !empty($doc['is_current']) ? ' style="font-weight:700;"' : '' ?>>
          <td><?= View::escape((string) ($doc['document_label'] ?? '')) ?><?= !empty($doc['is_current']) ? ' (dieses Dokument)' : '' ?></td>
          <td><?= View::escape((string) ($doc['invoice_number'] ?? '')) ?></td>
          <td><?= View::escape((string) ($doc['voucher_date'] ?? '')) ?></td>
          <td class="num"><?= View::escape((string) ($doc['gross_display'] ?? '')) ?> €</td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php if (is_array($finalSummary) && ($finalSummary['partials'] ?? []) !== []) : ?>
  <h2 style="font-size:12pt;margin:6mm 0 2mm;">Abzug Abschlagsrechnungen</h2>
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

<h2 style="font-size:12pt;margin:6mm 0 2mm;">Positionen</h2>
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

<?php if (trim((string) ($voucher['description'] ?? '')) !== '') : ?>
  <p><strong>Betreff:</strong> <?= View::escape((string) $voucher['description']) ?></p>
<?php endif; ?>
<?php if (trim((string) ($voucher['notes'] ?? '')) !== '') : ?>
  <p><strong>Notizen:</strong><br><?= nl2br(View::escape((string) $voucher['notes'])) ?></p>
<?php endif; ?>
