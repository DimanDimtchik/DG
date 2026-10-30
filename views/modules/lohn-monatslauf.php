<?php
/**
 * Lohn L5c — Monatslauf UI.
 *
 * @var string $payrollRunMonth YYYY-MM
 * @var list<array<string, mixed>> $payrollRuns
 * @var array<string, mixed>|null $payrollRun
 * @var list<array<string, mixed>> $payrollRunEmployees
 * @var list<array<string, mixed>> $payrollRunEvents
 * @var array<int, list<array<string, mixed>>> $payrollRunLinesByContact
 * @var list<array{id: int, label: string}> $payrollStaffOptions
 * @var bool $payrollRunCanEdit
 * @var bool $payrollRunCanApprove
 * @var array{type: string, message: string}|null $flash
 */
$month = preg_match('/^\d{4}-\d{2}$/', (string) ($payrollRunMonth ?? ''))
    ? (string) $payrollRunMonth
    : date('Y-m');
$runs = is_array($payrollRuns ?? null) ? $payrollRuns : [];
$run = is_array($payrollRun ?? null) ? $payrollRun : null;
$runId = (int) ($run['id'] ?? 0);
$employees = is_array($payrollRunEmployees ?? null) ? $payrollRunEmployees : [];
$events = is_array($payrollRunEvents ?? null) ? $payrollRunEvents : [];
$linesByContact = is_array($payrollRunLinesByContact ?? null) ? $payrollRunLinesByContact : [];
$staffOptions = is_array($payrollStaffOptions ?? null) ? $payrollStaffOptions : [];
$canEdit = !empty($payrollRunCanEdit);
$canApprove = !empty($payrollRunCanApprove);
$runStatus = (string) ($run['status'] ?? '');
$isDraft = $runStatus === PayrollRunRepository::STATUS_DRAFT;
$isCalculated = $runStatus === PayrollRunRepository::STATUS_CALCULATED;
$isApproved = $runStatus === PayrollRunRepository::STATUS_APPROVED;
$isLocked = $runStatus === PayrollRunRepository::STATUS_LOCKED;
$isSuperseded = !empty($run['is_superseded']);
?>
<div class="dg-wrap dg-lohn-monatslauf">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Monatslauf</h1>
      <p class="dg-lead">
        Entwurf → Berechnung → Freigabe → Sperre. Korrektur nur über Storno/Korrektur-Lauf.
        Kein SEPA und keine FiBu auf dieser Seite.
      </p>
    </div>
    <div class="dg-toolbar">
      <a class="dg-button" href="/app?page=lohn-lohnkonto">Lohnkonto</a>
      <a class="dg-button" href="/app?page=lohn-lohnarten">Lohnarten</a>
      <a class="dg-button" href="/app?page=lohn-beitragstabellen">Beitragstabellen</a>
    </div>
  </header>

  <section class="dg-panel">
    <form method="get" action="/app" class="dg-form" style="display:flex;gap:1rem;align-items:end;flex-wrap:wrap">
      <input type="hidden" name="page" value="lohn-monatslauf">
      <label class="dg-field">
        <span>Monat</span>
        <input type="month" name="month" value="<?= View::escape($month) ?>" required>
      </label>
      <button type="submit" class="dg-button">Anzeigen</button>
    </form>

    <?php if ($canEdit) : ?>
      <form method="post" action="/app?page=lohn-monatslauf&amp;month=<?= View::escape($month) ?>" class="dg-form" style="margin-top:1rem">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <input type="hidden" name="run_action" value="create">
        <input type="hidden" name="month" value="<?= View::escape($month) ?>">
        <button type="submit" class="dg-button dg-button--primary">Neuen Regular-Lauf anlegen</button>
      </form>
    <?php endif; ?>
  </section>

  <section class="dg-panel">
    <h2 class="dg-subsection-title">Läufe <?= View::escape($month) ?></h2>
    <?php if ($runs === []) : ?>
      <p class="dg-lead">Keine Läufe in diesem Monat.</p>
    <?php else : ?>
      <div class="dg-table-wrap">
        <table class="dg-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Art</th>
              <th>Status</th>
              <th>Label</th>
              <th>Ersetzt</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($runs as $row) : ?>
              <tr>
                <td><?= (int) ($row['id'] ?? 0) ?></td>
                <td><?= View::escape(PayrollRunRepository::kindLabel((string) ($row['kind'] ?? ''))) ?></td>
                <td><?= View::escape(PayrollRunRepository::statusLabel((string) ($row['status'] ?? ''))) ?></td>
                <td><?= View::escape((string) ($row['label'] ?? '')) ?></td>
                <td><?= !empty($row['is_superseded']) ? 'ja' : '—' ?></td>
                <td>
                  <a href="/app?page=lohn-monatslauf&amp;month=<?= View::escape($month) ?>&amp;run=<?= (int) ($row['id'] ?? 0) ?>">Öffnen</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <?php if ($runId > 0 && $run !== null) : ?>
    <section class="dg-panel">
      <h2 class="dg-subsection-title">
        Lauf #<?= $runId ?> —
        <?= View::escape(PayrollRunRepository::kindLabel((string) ($run['kind'] ?? ''))) ?> /
        <?= View::escape(PayrollRunRepository::statusLabel($runStatus)) ?>
        <?php if ($isSuperseded) : ?>
          <span class="dg-field-hint">(ersetzt)</span>
        <?php endif; ?>
      </h2>
      <dl class="dg-dl">
        <dt>Periode</dt>
        <dd><?= sprintf('%02d/%d', (int) ($run['period_month'] ?? 0), (int) ($run['period_year'] ?? 0)) ?></dd>
        <dt>Beitragstabelle</dt>
        <dd><?= (int) ($run['rate_set_id'] ?? 0) > 0 ? '#' . (int) $run['rate_set_id'] : '—' ?></dd>
        <dt>Vorgänger</dt>
        <dd><?= (int) ($run['supersedes_run_id'] ?? 0) > 0 ? '#' . (int) $run['supersedes_run_id'] : '—' ?></dd>
        <dt>Notiz</dt>
        <dd><?= View::escape((string) ($run['notes'] ?? '')) ?></dd>
      </dl>

      <div class="dg-toolbar" style="flex-wrap:wrap;gap:.5rem;margin:1rem 0">
        <?php if ($canEdit && $isDraft && !$isSuperseded) : ?>
          <form method="post" action="/app?page=lohn-monatslauf&amp;month=<?= View::escape($month) ?>&amp;run=<?= $runId ?>" style="display:inline">
            <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
            <input type="hidden" name="run_action" value="calculate">
            <input type="hidden" name="run_id" value="<?= $runId ?>">
            <button type="submit" class="dg-button dg-button--primary">Berechnen</button>
          </form>
          <form method="post" action="/app?page=lohn-monatslauf&amp;month=<?= View::escape($month) ?>&amp;run=<?= $runId ?>" style="display:inline" onsubmit="return confirm('Entwurf löschen?');">
            <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
            <input type="hidden" name="run_action" value="delete">
            <input type="hidden" name="run_id" value="<?= $runId ?>">
            <button type="submit" class="dg-button dg-button--ghost">Entwurf löschen</button>
          </form>
        <?php endif; ?>
        <?php if ($canEdit && $isCalculated && !$isSuperseded) : ?>
          <form method="post" action="/app?page=lohn-monatslauf&amp;month=<?= View::escape($month) ?>&amp;run=<?= $runId ?>" style="display:inline">
            <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
            <input type="hidden" name="run_action" value="reopen">
            <input type="hidden" name="run_id" value="<?= $runId ?>">
            <button type="submit" class="dg-button">Zurück auf Entwurf</button>
          </form>
        <?php endif; ?>
        <?php if ($canApprove && $isCalculated && !$isSuperseded) : ?>
          <form method="post" action="/app?page=lohn-monatslauf&amp;month=<?= View::escape($month) ?>&amp;run=<?= $runId ?>" class="dg-form" style="display:flex;gap:.5rem;align-items:end">
            <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
            <input type="hidden" name="run_action" value="approve">
            <input type="hidden" name="run_id" value="<?= $runId ?>">
            <label class="dg-field">
              <span>Begründung Freigabe</span>
              <input type="text" name="reason" required minlength="10" placeholder="mind. 10 Zeichen" style="min-width:14rem">
            </label>
            <button type="submit" class="dg-button dg-button--primary">Freigeben</button>
          </form>
        <?php endif; ?>
        <?php if ($canApprove && $isApproved && !$isSuperseded) : ?>
          <form method="post" action="/app?page=lohn-monatslauf&amp;month=<?= View::escape($month) ?>&amp;run=<?= $runId ?>" class="dg-form" style="display:flex;gap:.5rem;align-items:end">
            <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
            <input type="hidden" name="run_action" value="lock">
            <input type="hidden" name="run_id" value="<?= $runId ?>">
            <label class="dg-field">
              <span>Begründung Sperre</span>
              <input type="text" name="reason" required minlength="10" style="min-width:14rem">
            </label>
            <button type="submit" class="dg-button dg-button--primary">Sperren</button>
          </form>
        <?php endif; ?>
        <?php if ($canApprove && ($isApproved || $isLocked) && !$isSuperseded) : ?>
          <form method="post" action="/app?page=lohn-monatslauf&amp;month=<?= View::escape($month) ?>" class="dg-form" style="display:flex;gap:.5rem;align-items:end;flex-wrap:wrap">
            <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
            <input type="hidden" name="run_id" value="<?= $runId ?>">
            <input type="hidden" name="month" value="<?= View::escape($month) ?>">
            <label class="dg-field">
              <span>Begründung Storno/Korrektur</span>
              <input type="text" name="reason" required minlength="10" style="min-width:14rem">
            </label>
            <button type="submit" name="run_action" value="storno" class="dg-button">Storno-Lauf</button>
            <button type="submit" name="run_action" value="correction" class="dg-button">Korrektur-Lauf</button>
          </form>
        <?php endif; ?>
      </div>

      <?php if ($canEdit && $isDraft && !$isSuperseded) : ?>
        <h3 class="dg-subsection-title">Mitarbeiter hinzufügen</h3>
        <form method="post" action="/app?page=lohn-monatslauf&amp;month=<?= View::escape($month) ?>&amp;run=<?= $runId ?>" class="dg-form" style="display:flex;gap:.5rem;align-items:end;flex-wrap:wrap">
          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
          <input type="hidden" name="run_action" value="add_employee">
          <input type="hidden" name="run_id" value="<?= $runId ?>">
          <label class="dg-field">
            <span>Mitarbeiter</span>
            <select name="contact_id" required>
              <option value="">— wählen —</option>
              <?php
                $inRun = [];
                foreach ($employees as $e) {
                    $inRun[(int) ($e['contact_id'] ?? 0)] = true;
                }
              ?>
              <?php foreach ($staffOptions as $opt) : ?>
                <?php if (!empty($inRun[(int) $opt['id']])) {
                    continue;
                } ?>
                <option value="<?= (int) $opt['id'] ?>"><?= View::escape($opt['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="dg-field dg-field--checkbox">
            <span>
              <input type="checkbox" name="seed_assignments" value="1" checked>
              Lohnarten aus Stamm übernehmen
            </span>
          </label>
          <button type="submit" class="dg-button">Hinzufügen</button>
        </form>
      <?php endif; ?>

      <h3 class="dg-subsection-title">Mitarbeiter im Lauf</h3>
      <?php if ($employees === []) : ?>
        <p class="dg-lead">Noch keine Mitarbeiter.</p>
      <?php else : ?>
        <div class="dg-table-wrap">
          <table class="dg-table dg-table--compact">
            <thead>
              <tr>
                <th>Mitarbeiter</th>
                <th>Status</th>
                <th>Pfad</th>
                <th>Brutto</th>
                <th>Netto</th>
                <th>Positionen</th>
                <?php if ($canEdit && $isDraft) : ?><th></th><?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($employees as $emp) : ?>
                <?php
                  $cid = (int) ($emp['contact_id'] ?? 0);
                  $elines = $linesByContact[$cid] ?? [];
                ?>
                <tr>
                  <td><?= View::escape(PayrollRunRepository::contactLabel($cid)) ?></td>
                  <td><?= View::escape((string) ($emp['status'] ?? '')) ?></td>
                  <td><?= View::escape((string) ($emp['path'] ?? '')) ?></td>
                  <td><?= $emp['gross_total'] !== null ? View::escape(number_format((float) $emp['gross_total'], 2, ',', '.')) : '—' ?></td>
                  <td><?= $emp['net_pay'] !== null ? View::escape(number_format((float) $emp['net_pay'], 2, ',', '.')) : '—' ?></td>
                  <td>
                    <?php if ($elines === []) : ?>
                      —
                    <?php else : ?>
                      <ul style="margin:0;padding-left:1.1rem">
                        <?php foreach ($elines as $ln) : ?>
                          <li>
                            <?= View::escape((string) ($ln['code'] ?? '')) ?>
                            <?= View::escape(number_format((float) ($ln['amount'] ?? 0), 2, ',', '.')) ?>
                            <?php if (($ln['unit'] ?? '') === 'hour' && $ln['hours'] !== null) : ?>
                              × <?= View::escape((string) $ln['hours']) ?> h
                            <?php endif; ?>
                            <span class="dg-field-hint">(<?= View::escape((string) ($ln['source'] ?? '')) ?>)</span>
                          </li>
                        <?php endforeach; ?>
                      </ul>
                    <?php endif; ?>
                    <?php
                      $warnRaw = $emp['warnings_json'] ?? null;
                      $warns = is_string($warnRaw) ? json_decode($warnRaw, true) : null;
                      if (is_array($warns) && $warns !== []) :
                    ?>
                      <ul class="dg-field-hint">
                        <?php foreach ($warns as $w) : ?>
                          <li><?= View::escape((string) $w) ?></li>
                        <?php endforeach; ?>
                      </ul>
                    <?php endif; ?>
                  </td>
                  <?php if ($canEdit && $isDraft) : ?>
                    <td>
                      <form method="post" action="/app?page=lohn-monatslauf&amp;month=<?= View::escape($month) ?>&amp;run=<?= $runId ?>" style="display:inline">
                        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                        <input type="hidden" name="run_action" value="seed_assignments">
                        <input type="hidden" name="run_id" value="<?= $runId ?>">
                        <input type="hidden" name="contact_id" value="<?= $cid ?>">
                        <button type="submit" class="dg-button dg-button--small">Stamm laden</button>
                      </form>
                      <form method="post" action="/app?page=lohn-monatslauf&amp;month=<?= View::escape($month) ?>&amp;run=<?= $runId ?>" style="display:inline" onsubmit="return confirm('MA entfernen?');">
                        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                        <input type="hidden" name="run_action" value="remove_employee">
                        <input type="hidden" name="run_id" value="<?= $runId ?>">
                        <input type="hidden" name="contact_id" value="<?= $cid ?>">
                        <button type="submit" class="dg-button dg-button--ghost dg-button--small">Entfernen</button>
                      </form>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <h3 class="dg-subsection-title">Statushistorie</h3>
      <?php if ($events === []) : ?>
        <p class="dg-lead">Keine Events.</p>
      <?php else : ?>
        <div class="dg-table-wrap">
          <table class="dg-table dg-table--compact">
            <thead>
              <tr><th>Zeit</th><th>Von</th><th>Nach</th><th>Grund</th></tr>
            </thead>
            <tbody>
              <?php foreach ($events as $ev) : ?>
                <tr>
                  <td><?= View::escape((string) ($ev['created_at'] ?? '')) ?></td>
                  <td><?= View::escape((string) ($ev['from_status'] ?? '')) ?></td>
                  <td><?= View::escape((string) ($ev['to_status'] ?? '')) ?></td>
                  <td><?= View::escape((string) ($ev['reason'] ?? '')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>
