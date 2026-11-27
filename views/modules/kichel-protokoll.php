<?php
/** @var list<array<string, mixed>> $kichelLogRows */
/** @var array{type: string, message: string}|null $flash */
/** @var string $kichelQualityFilter */
/** @var array<string, mixed>|null $kichelEditRow */
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
$kichelQualityFilter = (string) ($kichelQualityFilter ?? 'all');
$kichelEditRow = is_array($kichelEditRow ?? null) ? $kichelEditRow : null;
$filterExtra = $kichelQualityFilter !== 'all' ? ['quality' => $kichelQualityFilter] : [];
$filterLinks = [
    'all' => 'Alle',
    'bad' => 'Schlecht',
    'queued' => 'Warteschlange',
    'done' => 'Erledigt',
    'open' => 'Unmarkiert',
];

$buildProtokollUrl = static function (array $extra = []) use ($kichelQualityFilter): string {
    $query = ['page' => 'kichel-protokoll'];
    if ($kichelQualityFilter !== 'all') {
        $query['quality'] = $kichelQualityFilter;
    }
    foreach ($extra as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $query[$key] = $value;
    }

    return '/app?' . http_build_query($query);
};
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header">
    <div>
      <h1 class="dg-page-title">Kichel-Protokoll</h1>
      <p class="dg-lead">Letzte Anfragen an den CRM-Helfer — schlecht markieren und in die Hilfe-Wissensbasis der Instanz übernehmen. Hier sehen Sie die vollständigen Details; Mitarbeitende erhalten in der Hilfe nur Antwort und verständliche Quellen.</p>
    </div>
  </header>

  <div class="dg-stack">
    <div class="dg-panel">
      <p class="dg-muted">Filter:</p>
      <div class="dg-btn-row">
        <?php foreach ($filterLinks as $key => $label) : ?>
          <?php
            $href = '/app?page=kichel-protokoll';
            if ($key !== 'all') {
                $href .= '&amp;quality=' . rawurlencode($key);
            }
            $isActive = $kichelQualityFilter === $key;
          ?>
          <a class="dg-button dg-button--small<?= $isActive ? ' dg-button--primary' : '' ?>" href="<?= $href ?>"><?= View::escape($label) ?></a>
        <?php endforeach; ?>
      </div>
    </div>

  <?php if ($rows === []) : ?>
    <div class="dg-panel">
      <p>Keine Einträge für diesen Filter — stellen Sie Kichel im CRM eine Frage oder wählen Sie einen anderen Filter.</p>
    </div>
  <?php else : ?>
    <div class="dg-panel">
      <?php
        View::partial('partials/list-per-page', [
            'total' => $kichelTotal,
            'perPageKey' => $kichelPerPageKey,
            'listPage' => 'kichel-protokoll',
            'extraQuery' => $filterExtra,
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
            <th>Qualität</th>
            <th>Antwort (Auszug)</th>
            <th>Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row) : ?>
            <?php
              $rowId = (int) ($row['id'] ?? 0);
              $protoMeta = KichelProtocolRepository::metaFromResponseJson(
                  isset($row['response_json']) ? (string) $row['response_json'] : null
              );
              $sourceRefs = $protoMeta['source_refs'];
              $sourceLabel = $sourceRefs !== []
                  ? implode(', ', array_slice($sourceRefs, 0, 4)) . (count($sourceRefs) > 4 ? ' …' : '')
                  : '—';
              $qStatus = isset($row['quality_status']) ? (string) $row['quality_status'] : '';
              $editHref = $buildProtokollUrl(['edit' => $rowId]);
            ?>
            <tr>
              <td><?= View::escape((string) ($row['created_at'] ?? '')) ?></td>
              <td><?= View::escape((string) ($row['display_name'] ?? $row['username'] ?? '—')) ?></td>
              <td><?= View::escape((string) ($row['query_text'] ?? '')) ?></td>
              <td><?= $protoMeta['rag_used'] ? 'ja' : 'nein' ?></td>
              <td><?= View::escape($sourceLabel) ?></td>
              <td>
                <?php if ($qStatus === KichelQualityService::STATUS_BAD) : ?>
                  <span class="dg-badge dg-badge--error"><?= View::escape(KichelQualityService::statusLabel($qStatus)) ?></span>
                <?php elseif ($qStatus === KichelQualityService::STATUS_QUEUED) : ?>
                  <span class="dg-badge dg-badge--pending"><?= View::escape(KichelQualityService::statusLabel($qStatus)) ?></span>
                <?php elseif ($qStatus === KichelQualityService::STATUS_DONE) : ?>
                  <span class="dg-badge dg-badge--ok"><?= View::escape(KichelQualityService::statusLabel($qStatus)) ?></span>
                <?php else : ?>
                  <span class="dg-muted">—</span>
                <?php endif; ?>
              </td>
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
              <td class="dg-table__actions">
                <div class="dg-table__actions-group">
                  <a class="dg-button dg-button--small" href="<?= View::escape($editHref) ?>">Bearbeiten</a>
                </div>
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
            'extraQuery' => $filterExtra,
        ]);
      ?>
    </div>
  <?php endif; ?>

  <?php if ($kichelEditRow !== null) : ?>
    <?php
      $editId = (int) ($kichelEditRow['id'] ?? 0);
      $draft = KichelQualityService::draftFromLog($kichelEditRow);
      $kwText = implode(', ', $draft['keywords']);
      $formAction = $buildProtokollUrl(['edit' => $editId]);
    ?>
    <div class="dg-panel" id="kichel-quality-edit">
      <h2 class="dg-section-title">Knowledge-Entwurf #<?= $editId ?></h2>
      <p class="dg-muted">Frage: <?= View::escape((string) ($kichelEditRow['query_text'] ?? '')) ?></p>
      <form class="dg-form" method="post" action="<?= View::escape($formAction) ?>">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <input type="hidden" name="kichel_quality_id" value="<?= $editId ?>">
        <div class="dg-form-grid">
          <div class="dg-field">
            <label for="kichel-title">Titel</label>
            <input id="kichel-title" type="text" name="title" maxlength="120" value="<?= View::escape($draft['title']) ?>">
          </div>
          <div class="dg-field">
            <label for="kichel-keywords">Stichworte (Komma)</label>
            <input id="kichel-keywords" type="text" name="keywords" maxlength="500" value="<?= View::escape($kwText) ?>">
          </div>
          <div class="dg-field">
            <label for="kichel-href">Link im CRM (optional)</label>
            <input id="kichel-href" type="text" name="href" maxlength="500" value="<?= View::escape($draft['href']) ?>" placeholder="/app?page=…">
          </div>
          <div class="dg-field">
            <label for="kichel-answer">Antworttext</label>
            <textarea id="kichel-answer" name="answer" rows="5" maxlength="65000"><?= View::escape($draft['answer']) ?></textarea>
          </div>
        </div>
        <div class="dg-form-actions">
          <button type="submit" class="dg-button" name="kichel_quality_action" value="bad">Als schlecht markieren</button>
          <button type="submit" class="dg-button" name="kichel_quality_action" value="queued">In Warteschlange</button>
          <button type="submit" class="dg-button dg-button--primary" name="kichel_quality_action" value="apply">In Hilfe übernehmen</button>
          <button type="submit" class="dg-button dg-button--ghost" name="kichel_quality_action" value="done">Erledigt</button>
        </div>
      </form>
    </div>
  <?php endif; ?>
  </div>
</div>
