<?php
/**
 * @var array{documents: list<array<string, mixed>>, current_id: int} $voucherChain
 * @var list<string> $followUpKinds
 * @var array<string, mixed>|null $chainSummary
 * @var int $voucherId
 * @var bool $canEdit
 */
$documents = $voucherChain['documents'] ?? [];
$followUps = $followUpKinds ?? [];
?>
<?php if ($documents !== []) : ?>
  <section class="dg-panel dg-voucher-chain" style="margin-bottom: 20px;">
    <header class="dg-panel__toolbar dg-panel__toolbar--lead">
      <div>
        <h2 class="dg-subsection-title">Belegkette</h2>
        <p class="dg-field-hint">Alle verknüpften Dokumente — von Angebot bis Schlussrechnung.</p>
      </div>
      <?php if ($voucherId > 0) : ?>
        <a class="dg-button dg-button--small" href="/app?page=buchhaltung-beleg-form&amp;action=edit&amp;id=<?= (int) $voucherId ?>&amp;download=print" target="_blank" rel="noopener">Drucken / PDF</a>
      <?php endif; ?>
    </header>
    <div class="dg-table-wrap">
      <table class="dg-table dg-voucher-chain__table">
        <thead>
          <tr>
            <th>Dokument</th>
            <th>Nummer</th>
            <th>Datum</th>
            <th class="dg-table__num">Betrag</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($documents as $doc) : ?>
            <tr<?= !empty($doc['is_current']) ? ' class="dg-voucher-chain__current"' : '' ?>>
              <td>
                <?= View::escape((string) ($doc['document_label'] ?? '')) ?>
                <?php if (!empty($doc['is_current'])) : ?>
                  <span class="dg-badge dg-badge--ok">aktuell</span>
                <?php endif; ?>
                <?php if (empty($doc['books'])) : ?>
                  <span class="dg-badge dg-badge--muted">ohne Buchung</span>
                <?php endif; ?>
              </td>
              <td><?= View::escape((string) ($doc['invoice_number'] ?? '—')) ?></td>
              <td><?= View::escape((string) ($doc['voucher_date'] ?? '')) ?></td>
              <td class="dg-table__num"><?= View::escape((string) ($doc['gross_display'] ?? '0,00')) ?> €</td>
              <td>
                <?php if (empty($doc['is_current'])) : ?>
                  <a href="<?= View::escape((string) ($doc['url'] ?? '#')) ?>">Öffnen</a>
                <?php else : ?>
                  —
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>

<?php if (is_array($chainSummary) && ($chainSummary['partials'] ?? []) !== []) : ?>
  <section class="dg-panel dg-voucher-chain-summary" style="margin-bottom: 20px;">
    <h2 class="dg-subsection-title">Schlussrechnung — Abschlagsabzug</h2>
    <div class="dg-table-wrap">
      <table class="dg-table">
        <thead>
          <tr><th>Abschlagsrechnung</th><th>Datum</th><th class="dg-table__num">Betrag</th></tr>
        </thead>
        <tbody>
          <?php foreach ($chainSummary['partials'] as $partial) : ?>
            <tr>
              <td>
                <a href="<?= View::escape((string) ($partial['url'] ?? '#')) ?>">
                  <?= View::escape((string) ($partial['invoice_number'] !== '' ? $partial['invoice_number'] : ('#' . (int) ($partial['id'] ?? 0)))) ?>
                </a>
              </td>
              <td><?= View::escape((string) ($partial['voucher_date'] ?? '')) ?></td>
              <td class="dg-table__num">− <?= View::escape((string) ($partial['gross_display'] ?? '')) ?> €</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="2"><strong>Auftragssumme</strong></td>
            <td class="dg-table__num"><?= View::escape((string) ($chainSummary['order_total_display'] ?? '')) ?> €</td>
          </tr>
          <tr>
            <td colspan="2"><strong>Bereits abgerechnet</strong></td>
            <td class="dg-table__num">− <?= View::escape((string) ($chainSummary['partial_total_display'] ?? '')) ?> €</td>
          </tr>
          <tr class="dg-table__total">
            <td colspan="2"><strong>Rest (Schlussrechnung)</strong></td>
            <td class="dg-table__num"><strong><?= View::escape((string) ($chainSummary['remaining_display'] ?? '')) ?> €</strong></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </section>
<?php endif; ?>

<?php if ($canEdit && $voucherId > 0 && $followUps !== []) : ?>
  <section class="dg-panel dg-voucher-chain-actions" style="margin-bottom: 20px;">
    <h2 class="dg-subsection-title">Folgebeleg erstellen</h2>
    <p class="dg-field-hint">Positionen und Kunde werden vom aktuellen Beleg übernommen.</p>
    <p class="dg-form-actions">
      <?php foreach ($followUps as $kind) : ?>
        <a
          class="dg-button"
          href="/app?page=buchhaltung-beleg-form&amp;action=new&amp;follow_from=<?= (int) $voucherId ?>&amp;document_kind=<?= View::escape($kind) ?>"
        ><?= View::escape(VoucherDocumentKind::label($kind)) ?></a>
      <?php endforeach; ?>
    </p>
  </section>
<?php endif; ?>
