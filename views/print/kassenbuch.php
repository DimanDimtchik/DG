<?php
/** @var callable $fmt */
/** @var list<array<string, mixed>> $entries */
/** @var array{in: float, out: float, balance: float} $totals */
/** @var string $periodLabel */
?>
<p><strong>Zeitraum:</strong> <?= View::escape($periodLabel ?? '') ?></p>
<p>Ein: <?= $fmt((float) ($totals['in'] ?? 0)) ?> € · Aus: <?= $fmt((float) ($totals['out'] ?? 0)) ?> € · Saldo: <?= $fmt((float) ($totals['balance'] ?? 0)) ?> €</p>
<table>
  <thead><tr><th>Datum</th><th>Art</th><th>Text</th><th class="num">Betrag</th></tr></thead>
  <tbody>
    <?php foreach ($entries as $entry) : ?>
      <tr>
        <td><?= View::escape((string) ($entry['entry_date'] ?? '')) ?></td>
        <td><?= View::escape((string) ($entry['side_label'] ?? '')) ?></td>
        <td><?= View::escape((string) ($entry['description'] ?? '')) ?></td>
        <td class="num"><?= View::escape((string) ($entry['amount_display'] ?? '')) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
