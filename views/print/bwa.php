<?php
/** @var callable $fmt */
/** @var array<string, mixed> $report */
?>
<p><strong>Zeitraum:</strong> <?= View::escape((string) ($report['period_label'] ?? '')) ?></p>
<table>
  <thead><tr><th>Position</th><th class="num">Betrag</th></tr></thead>
  <tbody>
    <?php foreach ($report['lines'] ?? [] as $line) : ?>
      <tr>
        <td><?= str_repeat('· ', (int) ($line['level'] ?? 0)) . View::escape((string) ($line['label'] ?? '')) ?></td>
        <td class="num"><?= $fmt((float) ($line['amount'] ?? 0)) ?> €</td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
