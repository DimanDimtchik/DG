<?php
/** @var callable $fmt */
/** @var array<string, mixed> $report */
?>
<p><strong>Zeitraum:</strong> <?= View::escape((string) ($report['period_label'] ?? '')) ?></p>
<table>
  <thead><tr><th>Konto</th><th>Bezeichnung</th><th class="num">Anfang</th><th class="num">Soll</th><th class="num">Haben</th><th class="num">Saldo</th></tr></thead>
  <tbody>
    <?php foreach ($report['accounts'] ?? [] as $row) : ?>
      <tr>
        <td><?= View::escape((string) ($row['account_number'] ?? '')) ?></td>
        <td><?= View::escape((string) ($row['name'] ?? '')) ?></td>
        <td class="num"><?= $fmt((float) ($row['opening'] ?? 0)) ?> €</td>
        <td class="num"><?= $fmt((float) ($row['debit'] ?? 0)) ?> €</td>
        <td class="num"><?= $fmt((float) ($row['credit'] ?? 0)) ?> €</td>
        <td class="num"><?= $fmt((float) ($row['balance'] ?? 0)) ?> €</td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
