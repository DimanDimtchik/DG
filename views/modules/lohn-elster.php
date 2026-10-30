<?php
/**
 * Lohn L10b — Lohnsteuer ELSTER (Vorschau / Test, kein Live).
 *
 * @var string $payrollElsterMonth YYYY-MM
 * @var array<string, mixed>|null $payrollElsterLsta
 * @var array<string, mixed>|null $payrollElsterLstb
 * @var string $payrollElsterTab lsta|lstb
 * @var string|null $payrollElsterError
 * @var list<array<string, mixed>> $payrollElsterSubmissions
 * @var list<string> $payrollElsterChecklist
 * @var list<string> $payrollElsterGateChecklist
 * @var bool $payrollElsterCanTest
 * @var bool $payrollElsterEricReady
 * @var bool $payrollElsterServerOk
 * @var bool $payrollElsterLstaLive
 * @var array{type: string, message: string}|null $flash
 * @var User|null $user
 */
$month = preg_match('/^\d{4}-\d{2}$/', (string) ($payrollElsterMonth ?? ''))
    ? (string) $payrollElsterMonth
    : date('Y-m');
[$y, $m] = array_map('intval', explode('-', $month));
$tab = (string) ($payrollElsterTab ?? 'lsta');
if (!in_array($tab, ['lsta', 'lstb'], true)) {
    $tab = 'lsta';
}
$lsta = is_array($payrollElsterLsta ?? null) ? $payrollElsterLsta : null;
$lstb = is_array($payrollElsterLstb ?? null) ? $payrollElsterLstb : null;
$error = trim((string) ($payrollElsterError ?? ''));
$subs = is_array($payrollElsterSubmissions ?? null) ? $payrollElsterSubmissions : [];
$checklist = is_array($payrollElsterChecklist ?? null) ? $payrollElsterChecklist : PayrollElsterService::auditorChecklist();
$gateChecklist = is_array($payrollElsterGateChecklist ?? null)
    ? $payrollElsterGateChecklist
    : PayrollElsterService::productionGateChecklist();
$canTest = !empty($payrollElsterCanTest);
$ericReady = !empty($payrollElsterEricReady);
$serverOk = !empty($payrollElsterServerOk);
$lstaLive = !empty($payrollElsterLstaLive);
$money = static fn (float $v): string => number_format($v, 2, ',', '.');
$base = '/app?page=lohn-elster&month=' . rawurlencode($month);
?>
<div class="dg-wrap dg-lohn-elster">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Lohnsteuer ELSTER</h1>
      <p class="dg-lead">
        Lohnsteuer-Anmeldung und -bescheinigung aus gesperrten Läufen.
        <strong>Kein UStVA</strong> — eigene Payloads. Live-Senden erst nach Gate G2 / L10c.
      </p>
    </div>
    <div class="dg-toolbar">
      <a class="dg-button" href="/app?page=lohn-monatslauf&amp;month=<?= View::escape($month) ?>">Monatslauf</a>
      <a class="dg-button" href="/app?page=lohn-auswertungen&amp;month=<?= View::escape($month) ?>">SV-Portal</a>
      <a class="dg-button" href="<?= View::escape(SettingsRegistry::tabUrl('elster')) ?>">ELSTER-Einstellungen</a>
    </div>
  </header>

  <div class="dg-flash dg-flash--warning">
    L10b-Testmodus: Kontroll-CSV und lokale Vorbereitungspayloads.
    Amtliches ELSTER-LStA-Schema / Worker-Anbindung folgen mit ERiC auf Hetzner.
    <?php if (!$serverOk) : ?>
      · Server unterstützt ERiC derzeit nicht (Kasserver).
    <?php endif; ?>
  </div>

  <section class="dg-panel">
    <form method="get" action="/app" class="dg-form" style="display:flex;flex-wrap:wrap;gap:1rem;align-items:end">
      <input type="hidden" name="page" value="lohn-elster">
      <label class="dg-field">
        <span>Monat (LStA)</span>
        <input type="month" name="month" value="<?= View::escape($month) ?>" required>
      </label>
      <button type="submit" class="dg-button dg-button--primary">Anzeigen</button>
    </form>
    <p class="dg-field-hint">
      ERiC-Test bereit: <?= $ericReady ? 'ja' : 'nein' ?> ·
      payroll_lsta_live: <?= $lstaLive ? 'an' : 'aus' ?>
    </p>
  </section>

  <?php if ($error !== '') : ?>
    <div class="dg-flash dg-flash--warning"><?= View::escape($error) ?></div>
  <?php endif; ?>

  <nav class="dg-toolbar" style="margin:1rem 0">
    <a class="dg-button<?= $tab === 'lsta' ? ' dg-button--primary' : '' ?>" href="<?= View::escape($base) ?>&amp;tab=lsta">LSt-Anmeldung</a>
    <a class="dg-button<?= $tab === 'lstb' ? ' dg-button--primary' : '' ?>" href="<?= View::escape($base) ?>&amp;tab=lstb">Bescheinigung <?= $y ?></a>
  </nav>

  <?php if ($tab === 'lsta' && $lsta !== null) : ?>
    <?php
      $a = is_array($lsta['amounts'] ?? null) ? $lsta['amounts'] : [];
      $warn = is_array($lsta['warnings'] ?? null) ? $lsta['warnings'] : [];
    ?>
    <?php if ($warn !== []) : ?>
      <div class="dg-flash dg-flash--warning">
        <ul><?php foreach ($warn as $w) : ?><li><?= View::escape((string) $w) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>
    <div class="dg-table-wrap">
      <table class="dg-table dg-table--compact">
        <tbody>
          <tr><th>Steuernummer</th><td><?= View::escape((string) ($lsta['tax_number_est'] ?? '')) ?></td></tr>
          <tr><th>Finanzamt</th><td><?= View::escape((string) ($lsta['finanzamt_name'] ?? '')) ?> <?= View::escape((string) ($lsta['finanzamt_bufo'] ?? '')) ?></td></tr>
          <tr><th>Runs</th><td><?= View::escape(implode(', ', is_array($lsta['source_run_ids'] ?? null) ? $lsta['source_run_ids'] : [])) ?></td></tr>
          <tr><th>LSt</th><td class="num"><?= View::escape($money((float) ($a['lst'] ?? 0))) ?> €</td></tr>
          <tr><th>SolZ</th><td class="num"><?= View::escape($money((float) ($a['solz'] ?? 0))) ?> €</td></tr>
          <tr><th>KiSt</th><td class="num"><?= View::escape($money((float) ($a['kst'] ?? 0))) ?> €</td></tr>
          <tr><th>Flat-LSt (getrennt)</th><td class="num"><?= View::escape($money((float) ($a['flat_lst'] ?? 0))) ?> €</td></tr>
          <tr><th>MA ok</th><td class="num"><?= (int) ($a['employee_count'] ?? 0) ?></td></tr>
        </tbody>
      </table>
    </div>
    <div class="dg-toolbar" style="margin:1rem 0;flex-wrap:wrap;gap:.5rem">
      <a class="dg-button dg-button--primary" href="<?= View::escape($base) ?>&amp;tab=lsta&amp;download=csv">Kontroll-CSV</a>
      <?php if ($canTest) : ?>
        <form method="post" action="<?= View::escape($base) ?>&amp;tab=lsta" class="dg-form" style="display:flex;gap:.5rem;align-items:end">
          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
          <input type="hidden" name="elster_action" value="lsta_test">
          <input type="hidden" name="month" value="<?= View::escape($month) ?>">
          <label class="dg-field">
            <span>Begründung (≥10)</span>
            <input type="text" name="reason" required minlength="10" placeholder="Test-Payload LStA geprüft" style="min-width:14rem">
          </label>
          <button type="submit" class="dg-button"<?= !empty($lsta['can_send_structurally']) ? '' : ' disabled' ?>>
            Test-Payload speichern
          </button>
        </form>
      <?php else : ?>
        <p class="dg-field-hint">Test-Payload speichern: Recht lohn=full erforderlich.</p>
      <?php endif; ?>
      <button type="button" class="dg-button" disabled title="L10c / Gate G2">Live an Finanzamt (gesperrt)</button>
    </div>
  <?php endif; ?>

  <?php if ($tab === 'lstb' && $lstb !== null) : ?>
    <?php
      $rows = is_array($lstb['rows'] ?? null) ? $lstb['rows'] : [];
      $bw = is_array($lstb['warnings'] ?? null) ? $lstb['warnings'] : [];
    ?>
    <?php if ($bw !== []) : ?>
      <div class="dg-flash dg-flash--warning">
        <ul><?php foreach (array_slice($bw, 0, 20) as $w) : ?><li><?= View::escape((string) $w) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>
    <p class="dg-field-hint">
      Eligibel: <?= (int) ($lstb['eligible_count'] ?? 0) ?> ·
      Bescheinigungs-Versand folgt nach LStA/ERiC (Vorschau + CSV in L10b).
    </p>
    <div class="dg-toolbar" style="margin-bottom:1rem">
      <a class="dg-button dg-button--primary" href="<?= View::escape($base) ?>&amp;tab=lstb&amp;download=csv">Bescheinigungs-CSV <?= $y ?></a>
    </div>
    <div class="dg-table-wrap">
      <table class="dg-table dg-table--compact">
        <thead>
          <tr><th>MA</th><th>IdNr</th><th>LSt</th><th>SolZ</th><th>KiSt</th><th>Eligibel</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row) : ?>
            <tr>
              <td><?= View::escape((string) ($row['label'] ?? '')) ?></td>
              <td><code><?= View::escape((string) ($row['tax_id'] ?? '')) ?></code></td>
              <td class="num"><?= View::escape($money((float) ($row['lst'] ?? 0))) ?></td>
              <td class="num"><?= View::escape($money((float) ($row['solz'] ?? 0))) ?></td>
              <td class="num"><?= View::escape($money((float) ($row['kst'] ?? 0))) ?></td>
              <td><?= !empty($row['eligible']) ? 'ja' : 'nein' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <details class="dg-panel" style="margin:1rem 0">
    <summary><strong>Betriebsprüfer-Stichprobe</strong></summary>
    <ol>
      <?php foreach ($checklist as $item) : ?>
        <li><?= View::escape((string) $item) ?></li>
      <?php endforeach; ?>
    </ol>
  </details>

  <details class="dg-panel" style="margin:1rem 0">
    <summary><strong>L10c Produktiv-Gate (Live noch gesperrt)</strong></summary>
    <p class="dg-field-hint">
      Live-Senden bleibt deaktiviert, bis alle Punkte erfüllt sind und ein Chat mit explizitem
      Nutzerbefehl den Unlock ausführt. Flag <code>payroll_lsta_live</code> allein reicht nicht.
    </p>
    <ol>
      <?php foreach ($gateChecklist as $item) : ?>
        <li><?= View::escape((string) $item) ?></li>
      <?php endforeach; ?>
    </ol>
  </details>

  <?php if ($subs !== []) : ?>
    <h3 class="dg-subsection-title">Lohn-ELSTER Abgaben / Tests</h3>
    <div class="dg-table-wrap">
      <table class="dg-table dg-table--compact">
        <thead>
          <tr><th>ID</th><th>Typ</th><th>Periode</th><th>Status</th><th>Test</th><th>Hinweis</th></tr>
        </thead>
        <tbody>
          <?php foreach ($subs as $s) : ?>
            <tr>
              <td>#<?= (int) ($s['id'] ?? 0) ?></td>
              <td><?= View::escape((string) ($s['form_type'] ?? '')) ?></td>
              <td><?= (int) ($s['period_year'] ?? 0) ?>-<?= sprintf('%02d', (int) ($s['period_month'] ?? 0)) ?></td>
              <td><?= View::escape((string) ($s['status'] ?? '')) ?></td>
              <td><?= !empty($s['test_mode']) ? 'ja' : 'nein' ?></td>
              <td><?= View::escape(mb_substr((string) ($s['response_summary'] ?? ''), 0, 120)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
