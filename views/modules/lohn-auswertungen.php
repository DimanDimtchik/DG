<?php
/**
 * Lohn L9b — SV-Portal Kontrolllisten / Auswertungen.
 *
 * @var string $payrollSvMonth YYYY-MM
 * @var list<array<string, mixed>> $payrollSvRuns
 * @var array<string, mixed>|null $payrollSvRun
 * @var string $payrollSvTab beitrag|stamm
 * @var array<string, mixed>|null $payrollSvBeitrag
 * @var array<string, mixed>|null $payrollSvStamm
 * @var string|null $payrollSvError
 * @var list<array<string, mixed>> $payrollSvExports
 * @var list<string> $payrollSvChecklist
 * @var array{type: string, message: string}|null $flash
 */
$month = preg_match('/^\d{4}-\d{2}$/', (string) ($payrollSvMonth ?? ''))
    ? (string) $payrollSvMonth
    : date('Y-m');
$runs = is_array($payrollSvRuns ?? null) ? $payrollSvRuns : [];
$run = is_array($payrollSvRun ?? null) ? $payrollSvRun : null;
$runId = (int) ($run['id'] ?? 0);
$tab = (string) ($payrollSvTab ?? 'beitrag');
if (!in_array($tab, ['beitrag', 'stamm'], true)) {
    $tab = 'beitrag';
}
$beitrag = is_array($payrollSvBeitrag ?? null) ? $payrollSvBeitrag : null;
$stamm = is_array($payrollSvStamm ?? null) ? $payrollSvStamm : null;
$error = trim((string) ($payrollSvError ?? ''));
$exports = is_array($payrollSvExports ?? null) ? $payrollSvExports : [];
$checklist = is_array($payrollSvChecklist ?? null) ? $payrollSvChecklist : PayrollSvPortalService::auditorChecklist();
$kindLabels = PayrollSvPortalService::kindLabels();
$money = static fn (float $v): string => number_format($v, 2, ',', '.');
$base = '/app?page=lohn-auswertungen&month=' . rawurlencode($month)
    . ($runId > 0 ? '&run=' . $runId : '');
?>
<div class="dg-wrap dg-lohn-auswertungen">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">SV-Portal / Auswertungen</h1>
      <p class="dg-lead">
        Kontrolllisten für das manuelle SV-Meldeportal (Beitragsnachweis-Vorbereitung + Stammkontrolle).
        Kein ITSG-Versand, keine DEÜV-XML. SV-Beiträge nicht über Lohn-SEPA.
      </p>
    </div>
    <div class="dg-toolbar">
      <a class="dg-button" href="/app?page=lohn-monatslauf&amp;month=<?= View::escape($month) ?>">Monatslauf</a>
      <a class="dg-button" href="/app?page=lohn-lohnkonto">Lohnkonto</a>
    </div>
  </header>

  <section class="dg-panel">
    <form method="get" action="/app" class="dg-form" style="display:flex;flex-wrap:wrap;gap:1rem;align-items:end">
      <input type="hidden" name="page" value="lohn-auswertungen">
      <label class="dg-field">
        <span>Monat</span>
        <input type="month" name="month" value="<?= View::escape($month) ?>" required>
      </label>
      <label class="dg-field">
        <span>Gesperrter Lauf</span>
        <select name="run">
          <option value="">— wählen —</option>
          <?php foreach ($runs as $r) : ?>
            <?php
              $rid = (int) ($r['id'] ?? 0);
              $st = (string) ($r['status'] ?? '');
              $kd = (string) ($r['kind'] ?? '');
              if ($st !== PayrollRunRepository::STATUS_LOCKED || !empty($r['is_superseded'])) {
                  continue;
              }
            ?>
            <option value="<?= $rid ?>"<?= $runId === $rid ? ' selected' : '' ?>>
              #<?= $rid ?> · <?= View::escape(PayrollRunRepository::kindLabel($kd)) ?>
              <?= !empty($r['is_superseded']) ? ' (ersetzt)' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <button type="submit" class="dg-button dg-button--primary">Anzeigen</button>
    </form>
  </section>

  <?php if ($error !== '') : ?>
    <div class="dg-flash dg-flash--warning"><?= View::escape($error) ?></div>
  <?php endif; ?>

  <?php if ($runId <= 0) : ?>
    <p class="dg-lead">Bitte Monat und gesperrten Lauf wählen (Status locked, nicht ersetzt).</p>
  <?php else : ?>
    <p class="dg-field-hint">
      Lauf #<?= $runId ?> ·
      <?= View::escape(PayrollRunRepository::kindLabel((string) ($run['kind'] ?? ''))) ?> ·
      <?= View::escape(sprintf('%02d/%04d', (int) ($run['period_month'] ?? 0), (int) ($run['period_year'] ?? 0))) ?>
      · <a href="/app?page=lohn-monatslauf&amp;month=<?= View::escape($month) ?>&amp;run=<?= $runId ?>">Zum Monatslauf</a>
    </p>

    <nav class="dg-toolbar" style="margin:1rem 0">
      <a class="dg-button<?= $tab === 'beitrag' ? ' dg-button--primary' : '' ?>" href="<?= View::escape($base) ?>&amp;tab=beitrag">Beitragsnachweis</a>
      <a class="dg-button<?= $tab === 'stamm' ? ' dg-button--primary' : '' ?>" href="<?= View::escape($base) ?>&amp;tab=stamm">Stammkontrolle</a>
    </nav>

    <div class="dg-toolbar" style="margin-bottom:1rem;flex-wrap:wrap;gap:.5rem">
      <a class="dg-button dg-button--primary" href="<?= View::escape($base) ?>&amp;tab=<?= View::escape($tab) ?>&amp;download=csv&amp;kind=<?= View::escape(PayrollSvPortalService::KIND_COMBINED) ?>">CSV kombiniert</a>
      <a class="dg-button" href="<?= View::escape($base) ?>&amp;tab=beitrag&amp;download=csv&amp;kind=<?= View::escape(PayrollSvPortalService::KIND_BEITRAG) ?>">CSV Beitragsnachweis</a>
      <a class="dg-button" href="<?= View::escape($base) ?>&amp;tab=stamm&amp;download=csv&amp;kind=<?= View::escape(PayrollSvPortalService::KIND_STAMM) ?>">CSV Stammkontrolle</a>
    </div>

    <?php if ($tab === 'beitrag' && $beitrag !== null) : ?>
      <?php
        $bw = is_array($beitrag['warnings'] ?? null) ? $beitrag['warnings'] : [];
        $groups = is_array($beitrag['groups'] ?? null) ? $beitrag['groups'] : [];
        $totals = is_array($beitrag['totals'] ?? null) ? $beitrag['totals'] : [];
      ?>
      <?php if (!empty($beitrag['blocked'])) : ?>
        <div class="dg-flash dg-flash--warning">Storno-Run: keine Beitragsnachweis-Summen.</div>
      <?php endif; ?>
      <?php if ($bw !== []) : ?>
        <div class="dg-flash dg-flash--warning">
          <ul><?php foreach ($bw as $w) : ?><li><?= View::escape((string) $w) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>
      <div class="dg-table-wrap">
        <table class="dg-table dg-table--compact">
          <thead>
            <tr>
              <th>KK-BN</th><th>KK</th><th>MA</th>
              <th>KV</th><th>RV</th><th>AV</th><th>PV</th>
              <th>SV-AN</th><th>SV-AG</th>
              <th>U1</th><th>U2</th><th>U3</th><th>Flat</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($groups as $g) : ?>
              <tr>
                <td><code><?= View::escape((string) ($g['kk_bn'] ?? '')) ?></code></td>
                <td><?= View::escape((string) ($g['kk_name'] ?? '')) ?></td>
                <td class="num"><?= (int) ($g['employee_count'] ?? 0) ?></td>
                <td class="num"><?= View::escape($money((float) ($g['kv'] ?? 0))) ?></td>
                <td class="num"><?= View::escape($money((float) ($g['rv'] ?? 0))) ?></td>
                <td class="num"><?= View::escape($money((float) ($g['av'] ?? 0))) ?></td>
                <td class="num"><?= View::escape($money((float) ($g['pv'] ?? 0))) ?></td>
                <td class="num"><?= View::escape($money((float) ($g['sv_an'] ?? 0))) ?></td>
                <td class="num"><?= View::escape($money((float) ($g['sv_ag'] ?? 0))) ?></td>
                <td class="num"><?= View::escape($money((float) ($g['u1'] ?? 0))) ?></td>
                <td class="num"><?= View::escape($money((float) ($g['u2'] ?? 0))) ?></td>
                <td class="num"><?= View::escape($money((float) ($g['u3'] ?? 0))) ?></td>
                <td class="num"><?= View::escape($money((float) ($g['flat'] ?? 0))) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if ($groups !== []) : ?>
              <tr>
                <td><strong>TOTAL</strong></td>
                <td></td>
                <td class="num"><?= (int) ($totals['employee_count'] ?? 0) ?></td>
                <td class="num"><?= View::escape($money((float) ($totals['kv'] ?? 0))) ?></td>
                <td class="num"><?= View::escape($money((float) ($totals['rv'] ?? 0))) ?></td>
                <td class="num"><?= View::escape($money((float) ($totals['av'] ?? 0))) ?></td>
                <td class="num"><?= View::escape($money((float) ($totals['pv'] ?? 0))) ?></td>
                <td class="num"><?= View::escape($money((float) ($totals['sv_an'] ?? 0))) ?></td>
                <td class="num"><?= View::escape($money((float) ($totals['sv_ag'] ?? 0))) ?></td>
                <td class="num"><?= View::escape($money((float) ($totals['u1'] ?? 0))) ?></td>
                <td class="num"><?= View::escape($money((float) ($totals['u2'] ?? 0))) ?></td>
                <td class="num"><?= View::escape($money((float) ($totals['u3'] ?? 0))) ?></td>
                <td class="num"><?= View::escape($money((float) ($totals['flat'] ?? 0))) ?></td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if ($groups === [] && empty($beitrag['blocked'])) : ?>
        <p class="dg-lead">Keine ok-MA mit result_json.</p>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($tab === 'stamm' && $stamm !== null) : ?>
      <?php
        $sgaps = is_array($stamm['gaps'] ?? null) ? $stamm['gaps'] : [];
        $srows = is_array($stamm['rows'] ?? null) ? $stamm['rows'] : [];
      ?>
      <?php if ($sgaps !== []) : ?>
        <div class="dg-flash dg-flash--warning">
          <strong>Lücken:</strong>
          <ul><?php foreach ($sgaps as $g) : ?><li><?= View::escape((string) $g) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>
      <div class="dg-table-wrap">
        <table class="dg-table dg-table--compact">
          <thead>
            <tr>
              <th>Ampel</th><th>MA</th><th>Art</th><th>Pers.-Gr.</th><th>Beitr.-Gr.</th>
              <th>KK</th><th>KK-BN</th><th>SV-Nr.</th><th>Hinweis</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($srows as $row) : ?>
              <?php
                $ampel = (string) ($row['ampel'] ?? '');
                $ampelLabel = $ampel === 'ok' ? 'ok' : ($ampel === 'warn' ? 'warn' : 'fail');
              ?>
              <tr>
                <td><?= View::escape($ampelLabel) ?></td>
                <td>
                  <a href="/app?page=kontakte&amp;action=view&amp;id=<?= (int) ($row['contact_id'] ?? 0) ?>">
                    <?= View::escape((string) ($row['label'] ?? '')) ?>
                  </a>
                </td>
                <td><?= View::escape((string) ($row['employment_type'] ?? '')) ?></td>
                <td><?= View::escape((string) ($row['person_group_key'] ?? '')) ?></td>
                <td><?= View::escape((string) ($row['contribution_group_key'] ?? '')) ?></td>
                <td><?= View::escape((string) ($row['health_insurance'] ?? '')) ?></td>
                <td><code><?= View::escape((string) ($row['health_insurance_company_number'] ?? '')) ?></code></td>
                <td><code><?= View::escape((string) ($row['social_security_number_masked'] ?? '')) ?></code></td>
                <td><?= View::escape((string) ($row['meldehinweis'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($srows === []) : ?>
        <p class="dg-lead">Keine ok-MA.</p>
      <?php endif; ?>
    <?php endif; ?>

    <details class="dg-panel" style="margin:1rem 0">
      <summary><strong>Betriebsprüfer-Stichprobe</strong></summary>
      <ol>
        <?php foreach ($checklist as $item) : ?>
          <li><?= View::escape((string) $item) ?></li>
        <?php endforeach; ?>
      </ol>
    </details>

    <?php if ($exports !== []) : ?>
      <h3 class="dg-subsection-title">Bisherige Exporte</h3>
      <div class="dg-table-wrap">
        <table class="dg-table dg-table--compact">
          <thead>
            <tr><th>ID</th><th>Zeit</th><th>Art</th><th>Datei</th></tr>
          </thead>
          <tbody>
            <?php foreach ($exports as $ex) : ?>
              <tr>
                <td>#<?= (int) ($ex['id'] ?? 0) ?></td>
                <td><?= View::escape((string) ($ex['created_at'] ?? '')) ?></td>
                <td><?= View::escape((string) ($kindLabels[(string) ($ex['kind'] ?? '')] ?? $ex['kind'] ?? '')) ?></td>
                <td>
                  <a href="<?= View::escape($base) ?>&amp;sv_dl=<?= (int) ($ex['id'] ?? 0) ?>">
                    <?= View::escape((string) ($ex['filename'] ?? 'download.csv')) ?>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
