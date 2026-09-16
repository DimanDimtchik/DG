<?php
/**
 * @var bool $show_expected
 * @var int|null $inventory_id
 * @var string $inventory_date
 * @var string $inventory_note
 * @var string $filter_label
 * @var list<array{key: string, label: string, rows: list<array<string, mixed>>}> $groups
 * @var int $row_count
 * @var callable $fmt
 */
$fmtQty = static function (float $v): string {
    return rtrim(rtrim(number_format($v, 3, ',', '.'), '0'), ',');
};
$dateLabel = $inventory_date !== ''
    ? date('d.m.Y', strtotime($inventory_date) ?: time())
    : date('d.m.Y');
?>
<style>
  .inv-meta { margin: 0 0 5mm; font-size: 9.5pt; color: #1c2330; }
  .inv-meta span { margin-right: 4mm; white-space: nowrap; }
  .inv-hint { margin: 0 0 5mm; padding: 2.5mm 3mm; background: #f4f6f9; border-left: 3px solid #b8942f; font-size: 9pt; color: #5c6678; }
  .inv-group { margin-top: 6mm; page-break-inside: avoid; }
  .inv-group + .inv-group { page-break-before: always; }
  .inv-group h2 { font-size: 12pt; margin: 0 0 2mm; border-bottom: 2px solid #b8942f; padding-bottom: 1mm; }
  .inv-sign { width: 28mm; min-height: 8mm; border-bottom: 1px solid #9aa3b2; }
  .inv-empty { width: 22mm; min-height: 7mm; border-bottom: 1px solid #9aa3b2; }
  .inv-pos { font-family: Consolas, "Courier New", monospace; font-size: 9pt; white-space: nowrap; }
  .inv-footer { margin-top: 8mm; font-size: 8.5pt; color: #5c6678; }
  .inv-signoff { margin-top: 8mm; display: table; width: 100%; }
  .inv-signoff__col { display: table-cell; width: 48%; vertical-align: top; padding-right: 4%; }
  .inv-signoff__line { margin-top: 10mm; border-bottom: 1px solid #9aa3b2; height: 8mm; }
</style>

<p class="inv-meta">
  <span><strong>Stichtag:</strong> <?= View::escape($dateLabel) ?></span>
  <?php if ($inventory_id !== null) : ?>
    <span><strong>Inventur:</strong> #<?= (int) $inventory_id ?></span>
  <?php endif; ?>
  <span><strong>Bereich:</strong> <?= View::escape($filter_label) ?></span>
  <span><strong>Positionen:</strong> <?= (int) $row_count ?></span>
</p>
<?php if ($inventory_note !== '') : ?>
  <p class="inv-meta"><strong>Notiz:</strong> <?= View::escape($inventory_note) ?></p>
<?php endif; ?>

<p class="inv-hint">
  Bitte physisch zählen und in „Gezählt“ eintragen.
  <?php if ($show_expected) : ?>
    Spalte „Soll“ = Buchbestand zum Stichtag (nur zur Kontrolle — nicht sichtbar für den Zähler auflegen, falls Blindzählung gewünscht: leere Liste drucken).
  <?php else : ?>
    Leere Zählliste ohne Sollbestand (Blindzählung).
  <?php endif; ?>
  Nach der Zählung Werte im CRM unter Lager → Inventur erfassen.
</p>

<?php if ($groups === []) : ?>
  <p>Keine Artikel mit Lagerführung für diesen Filter.</p>
<?php else : ?>
  <?php foreach ($groups as $group) : ?>
    <section class="inv-group">
      <h2><?= View::escape((string) ($group['label'] ?? '')) ?></h2>
      <table>
        <thead>
          <tr>
            <th>Pos.</th>
            <th>Art.-Nr.</th>
            <th>Bezeichnung</th>
            <th>Einheit</th>
            <?php if ($show_expected) : ?>
              <th class="num">Soll</th>
            <?php endif; ?>
            <th>Gezählt</th>
            <th>Diff.</th>
            <th>Visum</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($group['rows'] as $row) : ?>
            <tr>
              <td class="inv-pos"><?= View::escape((string) ($row['stock_position_code'] ?? '')) ?></td>
              <td><?= View::escape((string) ($row['article_number'] ?? '')) ?></td>
              <td><?= View::escape((string) ($row['title'] ?? '')) ?></td>
              <td><?= View::escape((string) ($row['unit'] ?? '')) ?></td>
              <?php if ($show_expected) : ?>
                <td class="num"><?= View::escape($fmtQty((float) ($row['book_quantity'] ?? 0))) ?></td>
              <?php endif; ?>
              <td><div class="inv-empty"></div></td>
              <td><div class="inv-empty"></div></td>
              <td><div class="inv-sign"></div></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<div class="inv-signoff">
  <div class="inv-signoff__col">
    <strong>Gezählt von</strong>
    <div class="inv-signoff__line"></div>
    <p class="inv-footer">Name, Datum, Unterschrift</p>
  </div>
  <div class="inv-signoff__col">
    <strong>Kontrolliert von</strong>
    <div class="inv-signoff__line"></div>
    <p class="inv-footer">Name, Datum, Unterschrift</p>
  </div>
</div>

<p class="inv-footer">
  Hinweis (GoBD): Die Papierliste ist Arbeitshilfe. Maßgeblich ist die digitale Inventur im CRM inkl. Differenzenbuchung und CSV-Export.
</p>
