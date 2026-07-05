<?php
/** @var list<array<string, mixed>> $historyRows */
/** @var bool $showTypeColumn */
$historyRows = $historyRows ?? [];
$showTypeColumn = $showTypeColumn ?? true;
?>
<?php if ($historyRows === []) : ?>
  <p class="dg-muted">Keine Einträge.</p>
<?php else : ?>
  <div class="dg-table-wrap">
    <table class="dg-table dg-table--compact dg-number-range-history-table">
      <thead>
        <tr>
          <?php if ($showTypeColumn) : ?><th>Typ</th><?php endif; ?>
          <th>Formel</th>
          <th>Von</th>
          <th>Bis</th>
          <th>Zähler-Darstellung ({NR})</th>
          <th>Mindestlänge</th>
          <th>Zähler</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($historyRows as $historyRow) : ?>
          <tr<?= !empty($historyRow['is_active']) ? ' class="is-active"' : '' ?>>
            <?php if ($showTypeColumn) : ?>
              <td><?= View::escape((string) ($historyRow['type_label'] ?? '')) ?></td>
            <?php endif; ?>
            <td><code class="dg-number-range-formula"><?= View::escape((string) ($historyRow['formula'] ?? $historyRow['formula_label'] ?? '')) ?></code></td>
            <td><?= View::escape(NumberRangeHistory::formatDateTime((string) $historyRow['used_from'])) ?></td>
            <td><?= !empty($historyRow['is_active']) ? 'aktiv' : View::escape(NumberRangeHistory::formatDateTime($historyRow['used_until'])) ?></td>
            <td><?= View::escape((string) ($historyRow['number_display_label'] ?? 'Dezimal (10)')) ?></td>
            <td><?= View::escape(NumberRangeHistory::formatPadLabel((int) ($historyRow['number_pad'] ?? 0))) ?></td>
            <td>
              <?php
                $from = (int) ($historyRow['counter_from'] ?? 0);
                $to = $historyRow['counter_to'] ?? null;
                if ($to === null && !empty($historyRow['is_active'])) {
                    echo View::escape('ab ' . $from);
                } elseif ($to === null) {
                    echo View::escape((string) $from);
                } else {
                    echo View::escape($from . ' – ' . $to);
                }
              ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
