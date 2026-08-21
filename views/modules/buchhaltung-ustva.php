<?php
/**
 * Umsatzsteuer-Voranmeldung (UStVA) — für Kunden ohne Steuerberater (DIY).
 *
 * @var int $ustvaYear
 * @var int $ustvaMonth
 * @var list<int> $ustvaYears
 * @var array<string, mixed> $ustvaReport
 * @var bool $isDiyMode
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$year = (int) ($ustvaYear ?? (int) date('Y'));
$month = (int) ($ustvaMonth ?? (int) date('n'));
$years = $ustvaYears ?? [(int) date('Y')];
$report = $ustvaReport ?? ['positions' => [], 'payable' => 0.0, 'period_label' => ''];
$diy = (bool) ($isDiyMode ?? true);
$fmt = static fn (float $v): string => number_format($v, 2, ',', '.');
$baseUrl = '/app?page=buchhaltung-ustva';
?>
<div class="dg-wrap dg-buchhaltung-ustva">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Umsatzsteuer-Voranmeldung</h1>
      <p class="dg-lead">
        Kennziffern für <?= View::escape((string) ($report['period_label'] ?? '')) ?>
        <?php if ($diy) : ?>
          — <strong>DIY-Modus</strong>: ohne Steuerberater selbst in ELSTER übertragen.
        <?php endif; ?>
      </p>
    </div>
    <div class="dg-page-header__actions">
      <?php if ($dbConnected) : ?>
        <a class="dg-button dg-button--primary" href="<?= View::escape($baseUrl . '&year=' . $year . '&month=' . $month . '&download=ustva') ?>">ELSTER-CSV</a>
        <a class="dg-button" href="<?= View::escape(ElsterExportService::elsterPortalUrl()) ?>" target="_blank" rel="noopener">ELSTER öffnen</a>
      <?php endif; ?>
    </div>
  </header>

  <?php if ($diy) : ?>
    <div class="dg-panel dg-panel--notice">
      <p>
        Sie haben keine Steuerkanzlei hinterlegt — wir lassen Sie nicht im Stich.
        Die Kennziffern werden aus Ihren Belegen berechnet. Übertragen Sie sie manuell in
        <a href="<?= View::escape(ElsterExportService::elsterPortalUrl()) ?>" target="_blank" rel="noopener">ELSTER</a>
        oder geben Sie die CSV an Ihren Steuerberater weiter.
      </p>
    </div>
  <?php endif; ?>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Datenbank nicht verbunden.</div>
  <?php endif; ?>

  <form class="dg-panel dg-ledger-filters" method="get" action="/app">
    <input type="hidden" name="page" value="buchhaltung-ustva">
    <div class="dg-form-grid dg-form-grid--compact">
      <label class="dg-field">
        <span>Jahr</span>
        <select name="year">
          <?php foreach ($years as $y) : ?>
            <option value="<?= (int) $y ?>"<?= $year === (int) $y ? ' selected' : '' ?>><?= (int) $y ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field">
        <span>Zeitraum</span>
        <select name="month">
          <option value="0"<?= $month === 0 ? ' selected' : '' ?>>Ganzes Jahr</option>
          <?php
            $monthNames = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
            for ($m = 1; $m <= 12; $m++) :
          ?>
            <option value="<?= $m ?>"<?= $month === $m ? ' selected' : '' ?>><?= $monthNames[$m] ?></option>
          <?php endfor; ?>
        </select>
      </label>
      <div class="dg-field dg-field--actions">
        <button type="submit" class="dg-button dg-button--primary">Anzeigen</button>
      </div>
    </div>
  </form>

  <section class="dg-panel">
    <h2 class="dg-subsection-title">Kennziffern</h2>
    <?php if (($report['positions'] ?? []) === []) : ?>
      <p class="dg-muted">Keine steuerrelevanten Belege im gewählten Zeitraum.</p>
    <?php else : ?>
      <div class="dg-table-wrap">
        <table class="dg-table">
          <thead>
            <tr>
              <th>KZ</th>
              <th>Bezeichnung</th>
              <th class="dg-table__num">Netto</th>
              <th class="dg-table__num">Steuer</th>
              <th class="dg-table__num">Betrag (ELSTER)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($report['positions'] as $pos) : ?>
              <tr>
                <td><strong><?= View::escape((string) $pos['kz']) ?></strong></td>
                <td><?= View::escape((string) $pos['label']) ?></td>
                <td class="dg-table__num"><?= (float) ($pos['net'] ?? 0) != 0.0 ? View::escape($fmt((float) $pos['net'])) . ' €' : '—' ?></td>
                <td class="dg-table__num"><?= (float) ($pos['tax'] ?? 0) != 0.0 ? View::escape($fmt((float) $pos['tax'])) . ' €' : '—' ?></td>
                <td class="dg-table__num"><strong><?= View::escape($fmt((float) ($pos['amount'] ?? 0))) ?> €</strong></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="4"><strong>Verbleibende USt-Vorauszahlung (KZ 37)</strong></td>
              <td class="dg-table__num"><strong><?= View::escape($fmt((float) ($report['payable'] ?? 0))) ?> €</strong></td>
            </tr>
          </tfoot>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <section class="dg-panel">
    <h2 class="dg-subsection-title">Weitere Schritte</h2>
    <ul>
      <li><a href="/app?page=buchhaltung-auswertungen&year=<?= (int) $year ?>&type=guv">GuV prüfen</a></li>
      <li><a href="/app?page=buchhaltung-jahresabschluss&year=<?= (int) $year ?>">Jahresabschluss-Assistent</a></li>
      <li><a href="/app?page=buchhaltung-steuerberater-export&year=<?= (int) $year ?>">Steuerberater-Export (DATEV/Agenda)</a></li>
      <li><a href="<?= View::escape($baseUrl . '&year=' . $year . '&month=0&download=euer') ?>">EÜR-CSV für ELSTER</a></li>
    </ul>
  </section>
</div>
