<?php
/**
 * Jahresabschluss: Geschäftsjahr abschließen und Salden ins Folgejahr übertragen.
 *
 * @var int $jaYear
 * @var list<int> $ledgerYears
 * @var array{income: float, expense: float, result: float} $jaPreview
 * @var array<int, array{year: int, status: string, closed_at: ?string, note: string}> $fiscalYears
 * @var string $jaYearStatus
 * @var bool $isAdmin
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$year = (int) ($jaYear ?? (int) date('Y'));
$years = $ledgerYears ?? [(int) date('Y')];
$preview = $jaPreview ?? ['income' => 0.0, 'expense' => 0.0, 'result' => 0.0];
$rows = $fiscalYears ?? [];
$status = (string) ($jaYearStatus ?? 'open');
$isAdmin = (bool) ($isAdmin ?? false);
$fmt = static fn (float $v): string => number_format($v, 2, ',', '.');
$baseUrl = '/app?page=buchhaltung-jahresabschluss';
?>
<div class="dg-wrap dg-buchhaltung-jahresabschluss">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Jahresabschluss</h1>
      <p class="dg-lead">Geschäftsjahr sperren und Saldenvortrag der Bestandskonten ins Folgejahr übertragen.</p>
    </div>
    <div class="dg-page-header__actions">
      <a class="dg-button" href="/app?page=buchhaltung-kontenuebersicht&amp;year=<?= (int) $year ?>">Kontenübersicht</a>
    </div>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Datenbank nicht verbunden — Jahresabschluss nicht verfügbar.</div>
  <?php endif; ?>

  <form class="dg-panel dg-ledger-filters" method="get" action="/app">
    <input type="hidden" name="page" value="buchhaltung-jahresabschluss">
    <div class="dg-form-grid dg-form-grid--compact">
      <label class="dg-field">
        <span>Geschäftsjahr</span>
        <select name="year" onchange="this.form.submit()">
          <?php foreach ($years as $y) : ?>
            <option value="<?= (int) $y ?>"<?= $year === (int) $y ? ' selected' : '' ?>><?= (int) $y ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
  </form>

  <section class="dg-panel dg-year-close">
    <h2 class="dg-subsection-title">
      Geschäftsjahr <?= (int) $year ?>
      <span class="dg-badge <?= $status === 'closed' ? 'dg-badge--muted' : 'dg-badge--ok' ?>"><?= $status === 'closed' ? 'abgeschlossen' : 'offen' ?></span>
    </h2>

    <dl class="dg-year-close__figures">
      <div><dt>Erträge</dt><dd class="dg-table__num"><?= View::escape($fmt((float) $preview['income'])) ?> €</dd></div>
      <div><dt>Aufwendungen</dt><dd class="dg-table__num"><?= View::escape($fmt((float) $preview['expense'])) ?> €</dd></div>
      <div class="dg-year-close__result">
        <dt><?= $preview['result'] >= 0 ? 'Überschuss' : 'Fehlbetrag' ?></dt>
        <dd class="dg-table__num"><strong><?= View::escape($fmt((float) $preview['result'])) ?> €</strong></dd>
      </div>
    </dl>

    <p class="dg-field-hint">
      Beim Abschluss werden die Salden der Bestandskonten (Aktiva/Passiva, z.&nbsp;B. Bank, Kasse, Forderungen,
      Verbindlichkeiten) als Saldenvortrag zum 01.01.<?= (int) $year + 1 ?> ins Folgejahr gebucht. Erfolgskonten
      (Aufwand/Ertrag) beginnen im neuen Jahr bei 0.
    </p>

    <?php if ($isAdmin && $dbConnected) : ?>
      <div class="dg-year-close__actions">
        <?php if ($status === 'closed') : ?>
          <form method="post" action="<?= View::escape($baseUrl) ?>" onsubmit="return confirm('Abschluss <?= (int) $year ?> zurücknehmen? Die Saldenvorträge in <?= (int) $year + 1 ?> werden gelöscht.');">
            <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
            <input type="hidden" name="year" value="<?= (int) $year ?>">
            <button type="submit" name="fiscal_year_reopen" value="1" class="dg-button">Abschluss zurücknehmen</button>
          </form>
        <?php else : ?>
          <form method="post" action="<?= View::escape($baseUrl) ?>" onsubmit="return confirm('Geschäftsjahr <?= (int) $year ?> abschließen und Salden nach <?= (int) $year + 1 ?> übertragen?');">
            <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
            <input type="hidden" name="year" value="<?= (int) $year ?>">
            <button type="submit" name="fiscal_year_close" value="1" class="dg-button dg-button--primary">Jahr abschließen</button>
          </form>
        <?php endif; ?>
      </div>
    <?php elseif (!$isAdmin) : ?>
      <p class="dg-muted">Nur Administratoren können den Jahresabschluss durchführen.</p>
    <?php endif; ?>
  </section>

  <?php if ($rows !== []) : ?>
    <section class="dg-panel">
      <h2 class="dg-subsection-title">Geschäftsjahre</h2>
      <div class="dg-table-wrap">
        <table class="dg-table">
          <thead>
            <tr><th>Jahr</th><th>Status</th><th>Abgeschlossen am</th></tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r) : ?>
              <tr>
                <td><a href="<?= View::escape($baseUrl . '&year=' . (int) $r['year']) ?>"><?= (int) $r['year'] ?></a></td>
                <td>
                  <span class="dg-badge <?= $r['status'] === 'closed' ? 'dg-badge--muted' : 'dg-badge--ok' ?>"><?= $r['status'] === 'closed' ? 'abgeschlossen' : 'offen' ?></span>
                </td>
                <td><?= $r['closed_at'] !== null ? View::escape(date('d.m.Y H:i', strtotime((string) $r['closed_at']))) : '—' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>
</div>
