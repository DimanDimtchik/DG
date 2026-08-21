<?php
/** @var callable $fmt */
/** @var array<string, mixed> $report */
?>
<p><strong>Zeitraum:</strong> <?= View::escape((string) ($report['period_label'] ?? '')) ?></p>
<table>
  <thead><tr><th>KZ</th><th>Bezeichnung</th><th class="num">Netto</th><th class="num">Steuer</th></tr></thead>
  <tbody>
    <?php foreach ($report['positions'] ?? [] as $row) : ?>
      <tr>
        <td><?= View::escape((string) ($row['kz'] ?? '')) ?></td>
        <td><?= View::escape((string) ($row['label'] ?? '')) ?></td>
        <td class="num"><?= $fmt((float) ($row['net'] ?? 0)) ?> €</td>
        <td class="num"><?= $fmt((float) ($row['tax'] ?? 0)) ?> €</td>
      </tr>
    <?php endforeach; ?>
    <tr class="total">
      <td colspan="3">Verbleibende Umsatzsteuer-Vorauszahlung</td>
      <td class="num"><?= $fmt((float) ($report['payable'] ?? 0)) ?> €</td>
    </tr>
  </tbody>
</table>
