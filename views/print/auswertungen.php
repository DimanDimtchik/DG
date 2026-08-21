<?php
/** @var callable $fmt */
/** @var string $reportType */
/** @var array<string, mixed> $balanceSheet */
/** @var array<string, mixed> $profitLoss */
/** @var string $periodLabel */
?>
<p><strong>Zeitraum:</strong> <?= View::escape($periodLabel ?? '') ?></p>
<?php if (($reportType ?? 'guv') === 'bilanz') : ?>
  <h2>Bilanz</h2>
  <table>
    <thead><tr><th>Konto</th><th>Bezeichnung</th><th class="num">Saldo</th></tr></thead>
    <tbody>
      <?php foreach ($balanceSheet['aktiva'] ?? [] as $row) : ?>
        <tr>
          <td><?= View::escape((string) ($row['account_number'] ?? '')) ?></td>
          <td><?= View::escape((string) ($row['name'] ?? '')) ?></td>
          <td class="num"><?= $fmt((float) ($row['balance'] ?? 0)) ?> €</td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php else : ?>
  <h2>Gewinn- und Verlustrechnung</h2>
  <table>
    <thead><tr><th>Konto</th><th>Bezeichnung</th><th class="num">Betrag</th></tr></thead>
    <tbody>
      <?php foreach ($profitLoss['income'] ?? [] as $row) : ?>
        <tr>
          <td><?= View::escape((string) ($row['account_number'] ?? '')) ?></td>
          <td><?= View::escape((string) ($row['name'] ?? '')) ?></td>
          <td class="num"><?= $fmt((float) ($row['pl_amount'] ?? 0)) ?> €</td>
        </tr>
      <?php endforeach; ?>
      <?php foreach ($profitLoss['expense'] ?? [] as $row) : ?>
        <tr>
          <td><?= View::escape((string) ($row['account_number'] ?? '')) ?></td>
          <td><?= View::escape((string) ($row['name'] ?? '')) ?></td>
          <td class="num"><?= $fmt((float) ($row['pl_amount'] ?? 0)) ?> €</td>
        </tr>
      <?php endforeach; ?>
      <tr class="total">
        <td colspan="2">Ergebnis</td>
        <td class="num"><?= $fmt((float) ($profitLoss['totals']['result'] ?? 0)) ?> €</td>
      </tr>
    </tbody>
  </table>
<?php endif; ?>
