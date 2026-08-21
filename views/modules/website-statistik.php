<?php
/** @var array{total: int, today: int, days7: int, days30: int} $websiteStatsSummary */
/** @var list<array{day: string, views: int}> $websiteStatsByDay */
/** @var list<array{path: string, views: int, page_id: ?int}> $websiteStatsTopPaths */
/** @var list<array{host: string, views: int}> $websiteStatsTopReferrers */
/** @var list<array{label: string, url: string, hint: string}> $websiteAnalyticsLinks */
/** @var int $websiteStatsDays */
/** @var bool $dbConnected */
/** @var array{type: string, message: string}|null $flash */
$summary = $websiteStatsSummary ?? ['total' => 0, 'today' => 0, 'days7' => 0, 'days30' => 0];
$byDay = $websiteStatsByDay ?? [];
$topPaths = $websiteStatsTopPaths ?? [];
$topRefs = $websiteStatsTopReferrers ?? [];
$gaLinks = $websiteAnalyticsLinks ?? [];
$days = (int) ($websiteStatsDays ?? 30);
$maxDay = 1;
foreach ($byDay as $row) {
    $maxDay = max($maxDay, (int) ($row['views'] ?? 0));
}
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Statistik</h1>
      <p class="dg-lead">Lokale Seitenaufrufe (nur mit Cookie-Zustimmung „Statistik“) und Links zu Google.</p>
    </div>
    <div class="dg-toolbar">
      <a class="dg-button<?= $days === 7 ? ' dg-button--primary' : '' ?>" href="/app?page=website-statistik&amp;days=7">7 Tage</a>
      <a class="dg-button<?= $days === 30 ? ' dg-button--primary' : '' ?>" href="/app?page=website-statistik&amp;days=30">30 Tage</a>
      <a class="dg-button<?= $days === 90 ? ' dg-button--primary' : '' ?>" href="/app?page=website-statistik&amp;days=90">90 Tage</a>
    </div>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Datenbank nicht verbunden.</div>
  <?php endif; ?>

  <section class="dg-panel" style="margin-bottom:16px;">
    <h2>Google Analytics &amp; Tag Manager</h2>
    <?php if ($gaLinks === []) : ?>
      <p class="dg-field-hint">Noch keine Mess-ID hinterlegt.
        <a href="/app?page=website-chrome">Unter Kopf &amp; Fuß eintragen</a> — detaillierte Auswertungen (Geräte, Kampagnen, …) finden Sie dort bei Google.</p>
    <?php else : ?>
      <p class="dg-field-hint">Für Geräte, Quellen und Conversion-Pfade die Google-Oberfläche nutzen. Hier nur Aufrufzahlen aus dem CRM.</p>
      <div class="dg-form-actions" style="margin:0;">
        <?php foreach ($gaLinks as $link) : ?>
          <a class="dg-button dg-button--primary" href="<?= View::escape($link['url']) ?>" target="_blank" rel="noopener">
            <?= View::escape($link['label']) ?>
          </a>
          <span class="dg-muted" style="align-self:center;"><?= View::escape($link['hint'] ?? '') ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <div class="dg-form-grid" style="margin-bottom:16px;">
    <div class="dg-panel">
      <p class="dg-muted" style="margin:0;">Heute</p>
      <p style="font-size:1.75rem;font-weight:700;margin:4px 0 0;"><?= (int) $summary['today'] ?></p>
    </div>
    <div class="dg-panel">
      <p class="dg-muted" style="margin:0;">7 Tage</p>
      <p style="font-size:1.75rem;font-weight:700;margin:4px 0 0;"><?= (int) $summary['days7'] ?></p>
    </div>
    <div class="dg-panel">
      <p class="dg-muted" style="margin:0;">30 Tage</p>
      <p style="font-size:1.75rem;font-weight:700;margin:4px 0 0;"><?= (int) $summary['days30'] ?></p>
    </div>
    <div class="dg-panel">
      <p class="dg-muted" style="margin:0;">Gesamt (gespeichert)</p>
      <p style="font-size:1.75rem;font-weight:700;margin:4px 0 0;"><?= (int) $summary['total'] ?></p>
    </div>
  </div>

  <section class="dg-panel" style="margin-bottom:16px;">
    <h2>Aufrufe der letzten <?= (int) $days ?> Tage</h2>
    <?php if ($byDay === []) : ?>
      <p class="dg-field-hint">Noch keine Daten. Sobald Besucher der Statistik zustimmen, erscheinen Aufrufe hier.</p>
    <?php else : ?>
      <div class="dg-stats-bars" role="img" aria-label="Aufrufe pro Tag">
        <?php foreach ($byDay as $row) :
            $v = (int) ($row['views'] ?? 0);
            $pct = $maxDay > 0 ? round(100 * $v / $maxDay) : 0;
            $label = date('d.m.', strtotime((string) $row['day']) ?: time());
            ?>
          <div class="dg-stats-bars__col" title="<?= View::escape($label . ': ' . $v) ?>">
            <div class="dg-stats-bars__bar" style="height:<?= max(2, $pct) ?>%;"></div>
            <span class="dg-stats-bars__label"><?= View::escape($label) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <div class="dg-form-grid">
    <section class="dg-panel">
      <h2>Meistbesuchte Pfade</h2>
      <div class="dg-table-wrap">
        <table class="dg-table dg-table--compact">
          <thead><tr><th>Pfad</th><th>Aufrufe</th></tr></thead>
          <tbody>
            <?php if ($topPaths === []) : ?>
              <tr><td colspan="2" class="dg-table__empty">Keine Daten</td></tr>
            <?php else : ?>
              <?php foreach ($topPaths as $row) : ?>
                <tr>
                  <td><code><?= View::escape((string) $row['path']) ?></code></td>
                  <td><?= (int) $row['views'] ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
    <section class="dg-panel">
      <h2>Externe Referrer</h2>
      <div class="dg-table-wrap">
        <table class="dg-table dg-table--compact">
          <thead><tr><th>Host</th><th>Aufrufe</th></tr></thead>
          <tbody>
            <?php if ($topRefs === []) : ?>
              <tr><td colspan="2" class="dg-table__empty">Keine externen Referrer</td></tr>
            <?php else : ?>
              <?php foreach ($topRefs as $row) : ?>
                <tr>
                  <td><?= View::escape((string) $row['host']) ?></td>
                  <td><?= (int) $row['views'] ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>

  <p class="dg-field-hint" style="margin-top:16px;">
    Gespeichert werden Pfad, Zeitpunkt und ggf. Referrer-Host — keine IP-Adressen.
    Erfassung nur bei Einwilligung „Statistik“. Vorschau-Aufrufe und Bots werden übersprungen.
  </p>
</div>
