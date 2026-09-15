<?php
/** @var list<array<string, mixed>> $kichelLogRows */
/** @var array{type: string, message: string}|null $flash */
$rows = $kichelLogRows ?? [];
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header">
    <div>
      <h1 class="dg-page-title">Kichel-Protokoll</h1>
      <p class="dg-lead">Letzte Anfragen an den CRM-Helfer — zum Prüfen und Verbessern der Antworten.</p>
    </div>
  </header>

  <?php if ($rows === []) : ?>
    <div class="dg-panel">
      <p>Noch keine Einträge — stellen Sie Kichel im CRM eine Frage.</p>
    </div>
  <?php else : ?>
    <div class="dg-panel">
      <table class="dg-table">
        <thead>
          <tr>
            <th>Zeit</th>
            <th>Benutzer</th>
            <th>Frage</th>
            <th>Antwort (Auszug)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row) : ?>
            <tr>
              <td><?= View::escape((string) ($row['created_at'] ?? '')) ?></td>
              <td><?= View::escape((string) ($row['display_name'] ?? $row['username'] ?? '—')) ?></td>
              <td><?= View::escape((string) ($row['query_text'] ?? '')) ?></td>
              <td>
                <details>
                  <summary><?= View::escape(mb_substr((string) ($row['answer_text'] ?? ''), 0, 120)) ?><?= mb_strlen((string) ($row['answer_text'] ?? '')) > 120 ? '…' : '' ?></summary>
                  <pre class="dg-kichel-log-detail"><?= View::escape((string) ($row['answer_text'] ?? '')) ?></pre>
                  <?php if (!empty($row['response_json'])) : ?>
                    <pre class="dg-kichel-log-detail dg-kichel-log-detail--json"><?= View::escape((string) $row['response_json']) ?></pre>
                  <?php endif; ?>
                </details>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
