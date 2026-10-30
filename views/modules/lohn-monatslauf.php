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
 * @var bool $payrollRunCanPostFibu
 * @var bool $payrollRunCanExportSepa
 * @var bool $payrollFibuLive
 * @var array<string, mixed>|null $payrollFibuMeta
 * @var array<string, mixed>|null $payrollFibuPreview
 * @var string|null $payrollFibuPreviewError
 * @var bool $payrollFibuShow
 * @var array<string, string> $payrollFibuRoleLabels
 * @var list<string> $payrollFibuChecklist
 * @var bool $payrollSepaLive
 * @var list<array<string, mixed>> $payrollSepaExports
 * @var array<string, mixed>|null $payrollSepaPreview
 * @var string|null $payrollSepaPreviewError
 * @var bool $payrollSepaShow
 * @var list<string> $payrollSepaChecklist
 * @var array{type: string, message: string}|null $flash
 * @var User|null $user
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
$canPostFibu = !empty($payrollRunCanPostFibu);
$canExportSepa = !empty($payrollRunCanExportSepa);
$fibuLive = !empty($payrollFibuLive);
$fibuMeta = is_array($payrollFibuMeta ?? null) ? $payrollFibuMeta : null;
$fibuPreview = is_array($payrollFibuPreview ?? null) ? $payrollFibuPreview : null;
$fibuPreviewError = trim((string) ($payrollFibuPreviewError ?? ''));
$fibuShow = !empty($payrollFibuShow);
$fibuRoleLabels = is_array($payrollFibuRoleLabels ?? null) ? $payrollFibuRoleLabels : PayrollFibuService::roleLabels();
$fibuChecklist = is_array($payrollFibuChecklist ?? null) ? $payrollFibuChecklist : PayrollFibuService::auditorChecklist();
$sepaLive = !empty($payrollSepaLive);
$sepaExports = is_array($payrollSepaExports ?? null) ? $payrollSepaExports : [];
$sepaPreview = is_array($payrollSepaPreview ?? null) ? $payrollSepaPreview : null;
$sepaPreviewError = trim((string) ($payrollSepaPreviewError ?? ''));
$sepaShow = !empty($payrollSepaShow);
$sepaChecklist = is_array($payrollSepaChecklist ?? null) ? $payrollSepaChecklist : PayrollSepaService::auditorChecklist();
$isAdminUser = isset($user) && $user instanceof User && RoleResolver::isAdmin($user);
$runKind = (string) ($run['kind'] ?? PayrollRunRepository::KIND_REGULAR);
$sepaKindOk = in_array($runKind, [
    PayrollRunRepository::KIND_REGULAR,
    PayrollRunRepository::KIND_CORRECTION,
    '',
], true);
$runStatus = (string) ($run['status'] ?? '');
$isDraft = $runStatus === PayrollRunRepository::STATUS_DRAFT;
$isCalculated = $runStatus === PayrollRunRepository::STATUS_CALCULATED;
$isApproved = $runStatus === PayrollRunRepository::STATUS_APPROVED;
$isLocked = $runStatus === PayrollRunRepository::STATUS_LOCKED;
$isSuperseded = !empty($run['is_superseded']);
$money = static fn (float $v): string => number_format($v, 2, ',', '.');
?>
<div class="dg-wrap dg-lohn-monatslauf">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Monatslauf</h1>
      <p class="dg-lead">
        Entwurf → Berechnung → Freigabe → Sperre → optional FiBu → optional SEPA-XML.
        Korrektur nur über Storno/Korrektur-Lauf. Storno-Run ohne Auszahlungs-SEPA.
      </p>
    </div>
    <div class="dg-toolbar">
      <a class="dg-button" href="/app?page=lohn-lohnkonto">Lohnkonto</a>
      <a class="dg-button" href="/app?page=lohn-auswertungen&amp;month=<?= View::escape($month) ?><?= $runId > 0 ? '&amp;run=' . $runId : '' ?>">SV-Portal</a>
      <a class="dg-button" href="/app?page=lohn-elster&amp;month=<?= View::escape($month) ?>">LSt ELSTER</a>
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
                <td class="dg-table__actions">
                  <div class="dg-table__actions-group">
                    <a class="dg-button dg-button--small" href="/app?page=lohn-monatslauf&amp;month=<?= View::escape($month) ?>&amp;run=<?= (int) ($row['id'] ?? 0) ?>">Öffnen</a>
                  </div>
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

      <?php if ($runId > 0 && ($isApproved || $isLocked) && !$isSuperseded) : ?>
        <h3 class="dg-subsection-title">FiBu-Übernahme</h3>
        <p class="dg-lead">
          Sammelbuchung laut L7 (Personalaufwand, FA, SV, Nettolöhne, AG-Sozialaufwand).
          Live-Flag:
          <?php if ($fibuLive) : ?>
            <strong>an</strong>
          <?php else : ?>
            <strong>aus</strong> — unter
            <a href="<?= View::escape(SettingsRegistry::tabUrl('lohn')) ?>">Einstellungen → Lohn</a>
            freischalten (Gate G1). Admin darf Probe buchen.
          <?php endif; ?>
          <?php if (!$fibuShow) : ?>
            · <a href="/app?page=lohn-monatslauf&amp;month=<?= View::escape($month) ?>&amp;run=<?= $runId ?>&amp;fibu=1">Vorschau anzeigen</a>
          <?php endif; ?>
        </p>

        <?php if ($fibuMeta !== null) : ?>
          <div class="dg-flash dg-flash--success">
            Bereits gebucht: Meta #<?= (int) ($fibuMeta['id'] ?? 0) ?>,
            Journal-Batch #<?= (int) ($fibuMeta['manual_batch_id'] ?? 0) ?>,
            Datum <?= View::escape((string) ($fibuMeta['posting_date'] ?? '')) ?>,
            Art <?= View::escape((string) ($fibuMeta['kind'] ?? '')) ?>.
            Erneutes Buchen ist idempotent (keine Doppelbuchung).
          </div>
        <?php endif; ?>

        <?php if ($fibuPreviewError !== '') : ?>
          <div class="dg-flash dg-flash--warning"><?= View::escape($fibuPreviewError) ?></div>
        <?php endif; ?>

        <?php if ($fibuShow || $fibuMeta !== null) : ?>
          <?php if ($fibuPreview !== null) : ?>
            <?php
              $prevAccounts = is_array($fibuPreview['accounts'] ?? null) ? $fibuPreview['accounts'] : [];
              $prevLines = is_array($fibuPreview['lines'] ?? null) ? $fibuPreview['lines'] : [];
              $prevAmounts = is_array($fibuPreview['amounts'] ?? null) ? $fibuPreview['amounts'] : [];
              $prevWarnings = is_array($fibuPreview['warnings'] ?? null) ? $fibuPreview['warnings'] : [];
            ?>
            <?php if ($prevWarnings !== []) : ?>
              <div class="dg-flash dg-flash--warning">
                <ul>
                  <?php foreach ($prevWarnings as $pw) : ?>
                    <li><?= View::escape((string) $pw) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>
            <div class="dg-table-wrap">
              <p class="dg-field-hint">
                Geplantes Datum: <?= View::escape((string) ($fibuPreview['posting_date'] ?? '')) ?>
                · <?= View::escape((string) ($fibuPreview['description'] ?? '')) ?>
                · Art <?= View::escape((string) ($fibuPreview['fibu_kind'] ?? '')) ?>
              </p>
              <table class="dg-table dg-table--compact">
                <thead>
                  <tr><th>Rolle / Konto</th><th>Nummer</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($fibuRoleLabels as $role => $label) : ?>
                    <tr>
                      <td><?= View::escape($label) ?></td>
                      <td><?= View::escape((string) ($prevAccounts[$role] ?? '—')) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
              <table class="dg-table dg-table--compact" style="margin-top:1rem">
                <thead>
                  <tr><th>Konto</th><th>Seite</th><th>Betrag</th><th>Text</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($prevLines as $pl) : ?>
                    <tr>
                      <td><?= View::escape((string) ($pl['account_number'] ?? '')) ?></td>
                      <td><?= View::escape((string) ($pl['side'] ?? '')) ?></td>
                      <td class="num"><?= View::escape($money((float) ($pl['amount'] ?? 0))) ?></td>
                      <td><?= View::escape((string) ($pl['description'] ?? '')) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
              <?php if ($prevAmounts !== [] && empty($prevAmounts['note'])) : ?>
                <p class="dg-field-hint">
                  MA ok: <?= (int) ($prevAmounts['employee_count'] ?? 0) ?> ·
                  Brutto <?= View::escape($money((float) ($prevAmounts['gross_total'] ?? 0))) ?> ·
                  Netto <?= View::escape($money((float) ($prevAmounts['net_pay'] ?? 0))) ?> ·
                  FA <?= View::escape($money((float) ($prevAmounts['fa'] ?? 0))) ?> ·
                  SV-AN <?= View::escape($money((float) ($prevAmounts['sv_an'] ?? 0))) ?>
                </p>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <details class="dg-panel" style="margin:1rem 0">
            <summary><strong>Betriebsprüfer-Stichprobe</strong></summary>
            <ol>
              <?php foreach ($fibuChecklist as $item) : ?>
                <li><?= View::escape((string) $item) ?></li>
              <?php endforeach; ?>
            </ol>
          </details>

          <?php if ($canPostFibu && $isLocked && $fibuMeta === null) : ?>
            <form method="post" action="/app?page=lohn-monatslauf&amp;month=<?= View::escape($month) ?>&amp;run=<?= $runId ?>&amp;fibu=1" class="dg-form" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:end">
              <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
              <input type="hidden" name="run_action" value="fibu_book">
              <input type="hidden" name="run_id" value="<?= $runId ?>">
              <input type="hidden" name="month" value="<?= View::escape($month) ?>">
              <label class="dg-field">
                <span>Buchungsdatum</span>
                <input type="date" name="posting_date" value="<?= View::escape((string) ($fibuPreview['posting_date'] ?? '')) ?>">
              </label>
              <label class="dg-field">
                <span>Begründung (≥10)</span>
                <input type="text" name="reason" required minlength="10" placeholder="z. B. Monat übernommen nach Prüfung" style="min-width:16rem">
              </label>
              <?php if (!$fibuLive && $isAdminUser) : ?>
                <label class="dg-field dg-field--checkbox">
                  <span>
                    <input type="checkbox" name="force_probe" value="1">
                    Admin-Probe ohne Live-Flag
                  </span>
                </label>
              <?php endif; ?>
              <button type="submit" class="dg-button dg-button--primary"<?= ($fibuLive || $isAdminUser) ? '' : ' disabled' ?>>
                In FiBu übernehmen
              </button>
            </form>
            <?php if (!$fibuLive && !$isAdminUser) : ?>
              <p class="dg-field-hint">Buchung gesperrt: Live-Flag aus und kein Admin-Probe.</p>
            <?php endif; ?>
          <?php elseif ($isApproved && !$isLocked) : ?>
            <p class="dg-field-hint">Vorschau möglich — verbindliche Übernahme erst nach Sperre.</p>
          <?php elseif ((string) ($run['kind'] ?? '') === PayrollRunRepository::KIND_CORRECTION && $fibuMeta === null) : ?>
            <p class="dg-field-hint">Korrektur-Lauf: prüfen, ob der Vorgänger per Storno-FiBu ausgeglichen wurde.</p>
          <?php endif; ?>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($runId > 0 && $isLocked && !$isSuperseded) : ?>
        <p class="dg-field-hint" style="margin:1rem 0">
          <a href="/app?page=lohn-auswertungen&amp;month=<?= View::escape($month) ?>&amp;run=<?= $runId ?>">
            SV-Portal-Vorbereitung
          </a>
          — Beitragsnachweis-/Stamm-Kontrolllisten (kein Versand).
          ·
          <a href="/app?page=lohn-elster&amp;month=<?= View::escape($month) ?>">
            LSt-Anmeldung Periode
          </a>
          (ELSTER-Vorschau, kein Live).
        </p>
      <?php endif; ?>

      <?php if ($runId > 0 && $isLocked && !$isSuperseded && $sepaKindOk) : ?>
        <h3 class="dg-subsection-title">SEPA-Auszahlung</h3>
        <p class="dg-lead">
          pain.001.001.03 Sammler (Netto) für Bank-Upload — kein automatischer Versand.
          Live-Flag:
          <?php if ($sepaLive) : ?>
            <strong>an</strong>
          <?php else : ?>
            <strong>aus</strong> — Dateien mit
            <code>-PROBE</code> kennzeichnen; freischalten unter
            <a href="<?= View::escape(SettingsRegistry::tabUrl('lohn')) ?>">Einstellungen → Lohn</a>
            (Gate G3).
          <?php endif; ?>
          Empfohlen: zuerst FiBu, dann SEPA.
          <?php if (!$sepaShow) : ?>
            · <a href="/app?page=lohn-monatslauf&amp;month=<?= View::escape($month) ?>&amp;run=<?= $runId ?>&amp;sepa=1">Vorschau anzeigen</a>
          <?php endif; ?>
        </p>

        <?php if ($sepaPreviewError !== '') : ?>
          <div class="dg-flash dg-flash--warning"><?= View::escape($sepaPreviewError) ?></div>
        <?php endif; ?>

        <?php if ($sepaShow || $sepaExports !== []) : ?>
          <?php if ($sepaPreview !== null) : ?>
            <?php
              $sepaTx = is_array($sepaPreview['transactions'] ?? null) ? $sepaPreview['transactions'] : [];
              $sepaWarn = is_array($sepaPreview['warnings'] ?? null) ? $sepaPreview['warnings'] : [];
            ?>
            <?php if ($sepaWarn !== []) : ?>
              <div class="dg-flash dg-flash--warning">
                <ul>
                  <?php foreach ($sepaWarn as $sw) : ?>
                    <li><?= View::escape((string) $sw) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>
            <p class="dg-field-hint">
              Absender <?= View::escape(PayrollSepaService::maskIban((string) ($sepaPreview['debtor_iban'] ?? ''))) ?>
              · <?= (int) ($sepaPreview['tx_count'] ?? 0) ?> Tx
              · CtrlSum <?= View::escape($money((float) ($sepaPreview['ctrl_sum'] ?? 0))) ?> €
              · Ausführung <?= View::escape((string) ($sepaPreview['reqd_exec_date'] ?? '')) ?>
              <?php if (!empty($sepaPreview['probe']) || !$sepaLive) : ?>
                · <strong>PROBE</strong>
              <?php endif; ?>
            </p>
            <?php if ($sepaTx !== []) : ?>
              <div class="dg-table-wrap">
                <table class="dg-table dg-table--compact">
                  <thead>
                    <tr><th>MA</th><th>IBAN</th><th>Betrag</th><th>EndToEnd</th></tr>
                  </thead>
                  <tbody>
                    <?php foreach ($sepaTx as $tx) : ?>
                      <tr>
                        <td><?= View::escape((string) ($tx['name'] ?? '')) ?></td>
                        <td><?= View::escape(PayrollSepaService::maskIban((string) ($tx['iban'] ?? ''))) ?></td>
                        <td class="num"><?= View::escape($money((float) ($tx['amount'] ?? 0))) ?></td>
                        <td><code><?= View::escape((string) ($tx['end_to_end_id'] ?? '')) ?></code></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          <?php endif; ?>

          <details class="dg-panel" style="margin:1rem 0">
            <summary><strong>Betriebsprüfer-Stichprobe SEPA</strong></summary>
            <ol>
              <?php foreach ($sepaChecklist as $item) : ?>
                <li><?= View::escape((string) $item) ?></li>
              <?php endforeach; ?>
            </ol>
          </details>

          <?php if ($canExportSepa) : ?>
            <form method="post" action="/app?page=lohn-monatslauf&amp;month=<?= View::escape($month) ?>&amp;run=<?= $runId ?>&amp;sepa=1" class="dg-form" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:end">
              <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
              <input type="hidden" name="run_action" value="sepa_export">
              <input type="hidden" name="run_id" value="<?= $runId ?>">
              <input type="hidden" name="month" value="<?= View::escape($month) ?>">
              <label class="dg-field">
                <span>Ausführungsdatum</span>
                <input type="date" name="exec_date" value="<?= View::escape((string) ($sepaPreview['reqd_exec_date'] ?? PayrollSepaService::nextBusinessDay())) ?>" required>
              </label>
              <label class="dg-field">
                <span>Begründung (≥10)</span>
                <input type="text" name="reason" required minlength="10" placeholder="z. B. Nettoauszahlung nach Sperre geprüft" style="min-width:16rem">
              </label>
              <button type="submit" class="dg-button dg-button--primary">
                <?= $sepaLive ? 'SEPA-XML erzeugen' : 'PROBE-XML erzeugen' ?>
              </button>
            </form>
            <?php if (!$sepaLive) : ?>
              <p class="dg-field-hint">Ohne Live-Flag: Dateiname endet auf <code>-PROBE.xml</code>, InitgPty mit „(PROBE)“ — nicht an die Bank senden.</p>
            <?php endif; ?>
          <?php else : ?>
            <p class="dg-field-hint">SEPA-Export nur mit Modulrecht lohn = full.</p>
          <?php endif; ?>

          <?php if ($sepaExports !== []) : ?>
            <h4 class="dg-subsection-title" style="font-size:1rem;margin-top:1.25rem">Bisherige Exporte</h4>
            <div class="dg-table-wrap">
              <table class="dg-table dg-table--compact">
                <thead>
                  <tr><th>ID</th><th>Zeit</th><th>Tx</th><th>Summe</th><th>Art</th><th>Datei</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($sepaExports as $ex) : ?>
                    <tr>
                      <td>#<?= (int) ($ex['id'] ?? 0) ?></td>
                      <td><?= View::escape((string) ($ex['created_at'] ?? '')) ?></td>
                      <td class="num"><?= (int) ($ex['tx_count'] ?? 0) ?></td>
                      <td class="num"><?= View::escape($money((float) ($ex['ctrl_sum'] ?? 0))) ?></td>
                      <td><?= !empty($ex['is_probe']) ? 'PROBE' : 'live' ?></td>
                      <td>
                        <?php if ($canExportSepa) : ?>
                          <a href="/app?page=lohn-monatslauf&amp;month=<?= View::escape($month) ?>&amp;run=<?= $runId ?>&amp;sepa=1&amp;sepa_dl=<?= (int) ($ex['id'] ?? 0) ?>">
                            <?= View::escape((string) ($ex['filename'] ?? 'download.xml')) ?>
                          </a>
                        <?php else : ?>
                          <?= View::escape((string) ($ex['filename'] ?? '')) ?>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      <?php elseif ($runId > 0 && $isLocked && !$isSuperseded && $runKind === PayrollRunRepository::KIND_STORNO) : ?>
        <h3 class="dg-subsection-title">SEPA-Auszahlung</h3>
        <p class="dg-lead">Storno-Run erzeugt keine Auszahlungs-SEPA (L8a).</p>
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
