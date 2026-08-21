<?php
/** @var callable $fmt */
/** @var array<string, mixed> $statement */
/** @var string $periodLabel */
$st = $statement ?? ['account' => [], 'opening' => 0.0, 'rows' => [], 'closing' => 0.0];
?>
<p><strong>Konto:</strong> <?= View::escape((string) ($st['account']['account_number'] ?? '')) ?> — <?= View::escape((string) ($st['account']['name'] ?? '')) ?></p>
<p><strong>Zeitraum:</strong> <?= View::escape($periodLabel ?? '') ?></p>
<table>
  <thead><tr><th>Datum</th><th>Text</th><th class="num">Soll</th><th class="num">Haben</th><th class="num">Saldo</th></tr></thead>
  <tbody>
    <tr><td colspan="4">Anfangssaldo</td><td class="num"><?= $fmt((float) ($st['opening'] ?? 0)) ?> €</td></tr>
    <?php foreach ($st['rows'] ?? [] as $row) : ?>
      <tr>
        <td><?= View::escape((string) ($row['date'] ?? '')) ?></td>
        <td><?= View::escape((string) ($row['description'] ?? '')) ?></td>
        <td class="num"><?= $fmt((float) ($row['debit'] ?? 0)) ?> €</td>
        <td class="num"><?= $fmt((float) ($row['credit'] ?? 0)) ?> €</td>
        <td class="num"><?= $fmt((float) ($row['balance'] ?? 0)) ?> €</td>
      </tr>
    <?php endforeach; ?>
    <tr class="total"><td colspan="4">Endsaldo</td><td class="num"><?= $fmt((float) ($st['closing'] ?? 0)) ?> €</td></tr>
  </tbody>
</table>
