<?php
/** @var list<array<string, mixed>> $kichelLogRows */
/** @var array{type: string, message: string}|null $flash */
$kichelLogPaged = is_array($kichelLogPaged ?? null) ? $kichelLogPaged : null;
if ($kichelLogPaged !== null && isset($kichelLogPaged['items'])) {
    $rows = $kichelLogPaged['items'];
    $kichelTotal = (int) ($kichelLogPaged['total'] ?? count($rows));
    $kichelPageNum = (int) ($kichelLogPaged['page'] ?? 1);
    $kichelTotalPages = (int) ($kichelLogPaged['total_pages'] ?? 1);
    $kichelPerPageKey = (string) ($kichelLogPaged['per_page_key'] ?? ListPagination::DEFAULT);
} else {
    $rows = $kichelLogRows ?? [];
    $kichelTotal = count($rows);
    $kichelPageNum = 1;
    $kichelTotalPages = 1;
    $kichelPerPageKey = ListPagination::DEFAULT;
}
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
      <?php
        View::partial('partials/list-per-page', [
            'total' => $kichelTotal,
            'perPageKey' => $kichelPerPageKey,
            'listPage' => 'kichel-protokoll',
            'extraQuery' => [],
        ]);
      ?>
      <div class="dg-table-wrap">
      <table class="dg-table">
        <thead>
          <tr>
            <th>Zeit</th>
            <th>Benutzer</th>
            <th>Frage</th>
            <th>RAG</th>
            <th>Quellen</th>
            <th>Antwort (Auszug)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row) : ?>
            <?php
              $protoMeta = KichelProtocolRepository::metaFromResponseJson(
                  isset($row['response_json']) ? (string) $row['response_json'] : null
              );
              $sourceRefs = $protoMeta['source_refs'];
              $sourceLabel = $sourceRefs !== []
                  ? implode(', ', array_slice($sourceRefs, 0, 4)) . (count($sourceRefs) > 4 ? ' …' : '')
                  : '—';
            ?>
            <tr>
              <td><?= View::escape((string) ($row['created_at'] ?? '')) ?></td>
              <td><?= View::escape((string) ($row['display_name'] ?? $row['username'] ?? '—')) ?></td>
              <td><?= View::escape((string) ($row['query_text'] ?? '')) ?></td>
              <td><?= $protoMeta['rag_used'] ? 'ja' : 'nein' ?></td>
              <td><?= View::escape($sourceLabel) ?></td>
              <td>
                <details>
                  <summary><?= View::escape(mb_substr((string) ($row['answer_text'] ?? ''), 0, 120)) ?><?= mb_strlen((string) ($row['answer_text'] ?? '')) > 120 ? '…' : '' ?></summary>
                  <pre class="dg-kichel-log-detail"><?= View::escape((string) ($row['answer_text'] ?? '')) ?></pre>
                  <?php if ($protoMeta['sources'] !== []) : ?>
                    <ul class="dg-kichel-sources">
                      <?php foreach ($protoMeta['sources'] as $source) : ?>
                        <li class="dg-kichel-source">
                          <?= View::escape((string) ($source['label'] ?? '')) ?>
                          <?php if (!empty($source['ref'])) : ?>
                            <span class="dg-kichel-source__ref">(<?= View::escape((string) $source['ref']) ?>)</span>
                          <?php endif; ?>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
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
      <?php
        View::partial('partials/list-pagination-nav', [
            'total' => $kichelTotal,
            'page' => $kichelPageNum,
            'totalPages' => $kichelTotalPages,
            'perPageKey' => $kichelPerPageKey,
            'listPage' => 'kichel-protokoll',
            'extraQuery' => [],
        ]);
      ?>
    </div>
  <?php endif; ?>
</div>
