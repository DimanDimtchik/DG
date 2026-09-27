<?php
/**
 * Z4c Urlaub: Antrag + eigene Liste; HR: Freigabe + Anspruch.
 *
 * @var int|null $timeVacContactId
 * @var string $timeVacContactLabel
 * @var int $timeVacYear
 * @var array<string, mixed> $timeVacBalance
 * @var list<array<string, mixed>> $timeVacOwnList
 * @var list<array<string, mixed>> $timeVacPending
 * @var list<array{id: int, label: string}> $timeVacStaffOptions
 * @var int|null $timeVacEntContactId
 * @var array<string, mixed>|null $timeVacEntBalance
 * @var bool $timeVacCanTeam
 * @var bool $timeVacCanApprove
 * @var list<array<string, mixed>> $timeVacPlanningStats
 * @var float $timeVacPlanningThreshold
 * @var array{type: string, message: string}|null $flash
 */
$contactId = (int) ($timeVacContactId ?? 0);
$label = (string) ($timeVacContactLabel ?? '');
$year = (int) ($timeVacYear ?? (int) date('Y'));
$balance = is_array($timeVacBalance ?? null) ? $timeVacBalance : [];
$ownList = is_array($timeVacOwnList ?? null) ? $timeVacOwnList : [];
$pending = is_array($timeVacPending ?? null) ? $timeVacPending : [];
$staff = is_array($timeVacStaffOptions ?? null) ? $timeVacStaffOptions : [];
$canTeam = !empty($timeVacCanTeam);
$canApprove = !empty($timeVacCanApprove);
$entCid = (int) ($timeVacEntContactId ?? 0);
$entBal = is_array($timeVacEntBalance ?? null) ? $timeVacEntBalance : null;
$planningStats = is_array($timeVacPlanningStats ?? null) ? $timeVacPlanningStats : [];
$planningThreshold = (float) ($timeVacPlanningThreshold ?? 60);
$belowCount = 0;
foreach ($planningStats as $ps) {
    if (!empty($ps['below_threshold'])) {
        $belowCount++;
    }
}

$staffLabel = static function (int $cid) use ($staff): string {
    foreach ($staff as $opt) {
        if ((int) ($opt['id'] ?? 0) === $cid) {
            return (string) ($opt['label'] ?? ('#' . $cid));
        }
    }

    return '#' . $cid;
};
?>
<div class="dg-wrap dg-zeiterfassung-urlaub">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Urlaub</h1>
      <p class="dg-lead">Z4c — Antrag, Restanspruch<?= $canTeam ? ', Freigabe' : '' ?></p>
    </div>
    <div class="dg-toolbar">
      <a class="dg-button" href="/app?page=zeiterfassung">Stempeluhr</a>
      <a class="dg-button" href="/app?page=zeiterfassung-monat">Monatsblatt</a>
      <a class="dg-button" href="/app?page=zeiterfassung-abwesenheit">Abwesenheit</a>
      <a class="dg-button" href="/app?page=zeiterfassung-rueckstellung">Rückstellungen</a>
      <a class="dg-button" href="/app?page=zeiterfassung-lohnexport">Lohn-Export</a>
      <?php if ($canTeam) : ?>
        <a class="dg-button" href="/app?page=zeiterfassung-team">Team heute</a>
      <?php endif; ?>
    </div>
  </header>

  <?php if ($contactId < 1) : ?>
    <div class="dg-flash dg-flash--warning">
      Kein Mitarbeiter-Kontakt verknüpft — Antrag nicht möglich.
      Bitte in <a href="/app?page=kontakte">Kontakte</a> Rolle Mitarbeiter und Login abstimmen.
    </div>
  <?php else : ?>
    <section class="dg-panel">
      <h2 class="dg-subsection-title">Restanspruch <?= (int) $year ?> — <?= View::escape($label !== '' ? $label : ('#' . $contactId)) ?></h2>
      <p>
        Anspruch: <strong><?= View::escape(number_format((float) ($balance['days_entitled'] ?? 0), 1, ',', '')) ?></strong>
        · Übertrag: <strong><?= View::escape(number_format((float) ($balance['days_carried'] ?? 0), 1, ',', '')) ?></strong>
        · Genommen: <strong><?= View::escape(number_format((float) ($balance['days_used'] ?? 0), 1, ',', '')) ?></strong>
        · Rest: <strong><?= View::escape(number_format((float) ($balance['days_rest'] ?? 0), 1, ',', '')) ?></strong>
      </p>
      <p class="dg-field-hint">Werktage Mo–Fr (ohne Feiertage). Halbe Tage möglich.</p>
    </section>

    <section class="dg-panel">
      <h2 class="dg-subsection-title">Urlaub beantragen</h2>
      <form method="post" action="/app?page=zeiterfassung-urlaub" class="dg-form">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <input type="hidden" name="vacation_action" value="request">
        <div class="dg-form-row">
          <label class="dg-field">
            <span class="dg-field-label">Von</span>
            <input type="date" name="date_from" required>
          </label>
          <label class="dg-field">
            <span class="dg-field-label">Bis</span>
            <input type="date" name="date_to" required>
          </label>
          <label class="dg-field">
            <span class="dg-field-label">Halber Tag</span>
            <input type="checkbox" name="half_day" value="1">
          </label>
        </div>
        <label class="dg-field">
          <span class="dg-field-label">Begründung</span>
          <textarea name="reason" rows="2" required maxlength="500" placeholder="z. B. Erholung / Familienurlaub"></textarea>
        </label>
        <button type="submit" class="dg-button dg-button--primary">Antrag senden</button>
      </form>
    </section>

    <section class="dg-panel">
      <h2 class="dg-subsection-title">Meine Anträge</h2>
      <?php if ($ownList === []) : ?>
        <p class="dg-muted">Noch keine Urlaubseinträge.</p>
      <?php else : ?>
        <div class="dg-table-wrap">
          <table class="dg-table">
            <thead>
              <tr>
                <th>Zeitraum</th>
                <th class="dg-table__num">Tage</th>
                <th>Status</th>
                <th>Grund</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($ownList as $row) : ?>
                <?php if ((string) ($row['type'] ?? '') !== 'vacation') {
                    continue;
                } ?>
                <tr>
                  <td>
                    <?= View::escape((string) ($row['date_from'] ?? '')) ?>
                    – <?= View::escape((string) ($row['date_to'] ?? '')) ?>
                  </td>
                  <td class="dg-table__num"><?= View::escape(number_format((float) ($row['days_count'] ?? 0), 1, ',', '')) ?></td>
                  <td><?= View::escape(TimeVacationService::statusLabel((string) ($row['status'] ?? ''))) ?></td>
                  <td><?= View::escape((string) ($row['reason'] ?? '')) ?></td>
                  <td>
                    <?php if ((string) ($row['status'] ?? '') === 'requested') : ?>
                      <form method="post" action="/app?page=zeiterfassung-urlaub" style="display:inline" onsubmit="return confirm('Antrag zurückziehen?');">
                        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                        <input type="hidden" name="vacation_action" value="cancel">
                        <input type="hidden" name="absence_id" value="<?= (int) ($row['id'] ?? 0) ?>">
                        <button type="submit" class="dg-button dg-button--small">Zurückziehen</button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($canApprove) : ?>
    <section class="dg-panel">
      <h2 class="dg-subsection-title">Offene Freigaben</h2>
      <?php if ($pending === []) : ?>
        <p class="dg-muted">Keine offenen Urlaubsanträge.</p>
      <?php else : ?>
        <div class="dg-table-wrap">
          <table class="dg-table">
            <thead>
              <tr>
                <th>Mitarbeiter</th>
                <th>Zeitraum</th>
                <th class="dg-table__num">Tage</th>
                <th>Grund</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pending as $row) : ?>
                <?php $cid = (int) ($row['contact_id'] ?? 0); ?>
                <tr>
                  <td><?= View::escape($staffLabel($cid)) ?></td>
                  <td>
                    <?= View::escape((string) ($row['date_from'] ?? '')) ?>
                    – <?= View::escape((string) ($row['date_to'] ?? '')) ?>
                  </td>
                  <td class="dg-table__num"><?= View::escape(number_format((float) ($row['days_count'] ?? 0), 1, ',', '')) ?></td>
                  <td><?= View::escape((string) ($row['reason'] ?? '')) ?></td>
                  <td>
                    <form method="post" action="/app?page=zeiterfassung-urlaub" style="display:inline">
                      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                      <input type="hidden" name="vacation_action" value="approve">
                      <input type="hidden" name="absence_id" value="<?= (int) ($row['id'] ?? 0) ?>">
                      <button type="submit" class="dg-button dg-button--small dg-button--primary">Genehmigen</button>
                    </form>
                    <form method="post" action="/app?page=zeiterfassung-urlaub" class="dg-form" style="display:inline-block;margin-top:0.35rem">
                      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                      <input type="hidden" name="vacation_action" value="reject">
                      <input type="hidden" name="absence_id" value="<?= (int) ($row['id'] ?? 0) ?>">
                      <input type="text" name="reason" required maxlength="500" placeholder="Ablehnungsgrund" style="min-width:10rem">
                      <button type="submit" class="dg-button dg-button--small">Ablehnen</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($canTeam) : ?>
    <section class="dg-panel">
      <h2 class="dg-subsection-title">Urlaubsplanung <?= (int) $year ?></h2>
      <p class="dg-field-hint">
        Geplant = beantragt + genehmigt. Schwelle:
        <?= View::escape(number_format($planningThreshold, 0, ',', '')) ?>&nbsp;%
        des Anspruchs inkl. Übertrag.
        <?php if ($belowCount > 0) : ?>
          · <strong><?= (int) $belowCount ?></strong> unter der Schwelle.
        <?php endif; ?>
      </p>
      <?php if ($planningStats === []) : ?>
        <p class="dg-muted">Keine Mitarbeiter-Kontakte.</p>
      <?php else : ?>
        <form method="post" action="/app?page=zeiterfassung-urlaub" class="dg-form dg-form--inline" style="margin-bottom:1rem">
          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
          <input type="hidden" name="vacation_action" value="send_planning_reminder">
          <input type="hidden" name="year" value="<?= (int) $year ?>">
          <button type="submit" class="dg-button dg-button--primary"<?= $belowCount < 1 ? ' disabled' : '' ?>
                  onclick="return confirm('Erinnerung an alle unter der Schwelle senden?');">
            Erinnerung an alle unter <?= View::escape(number_format($planningThreshold, 0, ',', '')) ?>&nbsp;% senden
          </button>
        </form>
        <div class="dg-table-wrap">
          <table class="dg-table">
            <thead>
              <tr>
                <th>Mitarbeiter</th>
                <th class="dg-table__num">Anspruch</th>
                <th class="dg-table__num">Geplant</th>
                <th class="dg-table__num">Davon genehmigt</th>
                <th class="dg-table__num">%</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($planningStats as $row) : ?>
                <?php
                  $cid = (int) ($row['contact_id'] ?? 0);
                  $pct = $row['percent'];
                  $below = !empty($row['below_threshold']);
                  $budget = (float) ($row['days_budget'] ?? 0);
                ?>
                <tr<?= $below ? ' class="dg-row--warn"' : '' ?>>
                  <td>
                    <?= View::escape((string) ($row['label'] ?? '')) ?>
                    <?php if ($cid > 0) : ?>
                      <a class="dg-muted" href="/app?page=kontakte&amp;id=<?= $cid ?>">Akte</a>
                    <?php endif; ?>
                  </td>
                  <td class="dg-table__num"><?= View::escape(number_format($budget, 1, ',', '')) ?></td>
                  <td class="dg-table__num"><?= View::escape(number_format((float) ($row['days_planned'] ?? 0), 1, ',', '')) ?></td>
                  <td class="dg-table__num"><?= View::escape(number_format((float) ($row['days_approved'] ?? 0), 1, ',', '')) ?></td>
                  <td class="dg-table__num">
                    <?= $pct === null
                        ? '—'
                        : View::escape(number_format((float) $pct, 1, ',', '')) . '&nbsp;%' ?>
                  </td>
                  <td>
                    <?php if ($budget <= 0) : ?>
                      <span class="dg-muted">Kein Anspruch</span>
                    <?php elseif ($below) : ?>
                      <span class="dg-badge dg-badge--pending">unter Schwelle</span>
                    <?php else : ?>
                      <span class="dg-badge">ok</span>
                    <?php endif; ?>
                    <?php if (!empty($row['last_reminder_sent_at'])) : ?>
                      <div class="dg-muted" style="font-size:.8rem">
                        Erinnerung: <?= View::escape((string) $row['last_reminder_sent_at']) ?>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($below && $budget > 0) : ?>
                      <form method="post" action="/app?page=zeiterfassung-urlaub" style="display:inline">
                        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                        <input type="hidden" name="vacation_action" value="send_planning_reminder">
                        <input type="hidden" name="year" value="<?= (int) $year ?>">
                        <input type="hidden" name="reminder_contact_id" value="<?= $cid ?>">
                        <button type="submit" class="dg-button dg-button--small">Erinnern</button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section class="dg-panel">
      <h2 class="dg-subsection-title">Jahresanspruch pflegen</h2>
      <form method="get" action="/app" class="dg-form dg-form--inline">
        <input type="hidden" name="page" value="zeiterfassung-urlaub">
        <label class="dg-field">
          <span class="dg-field-label">Mitarbeiter</span>
          <select name="ent_contact_id" onchange="this.form.submit()">
            <option value="">— wählen —</option>
            <?php foreach ($staff as $opt) : ?>
              <option value="<?= (int) ($opt['id'] ?? 0) ?>"<?= (int) ($opt['id'] ?? 0) === $entCid ? ' selected' : '' ?>>
                <?= View::escape((string) ($opt['label'] ?? '')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dg-field">
          <span class="dg-field-label">Jahr</span>
          <input type="number" name="year" value="<?= (int) $year ?>" min="2000" max="2100" onchange="this.form.submit()">
        </label>
      </form>
      <?php if ($entCid > 0 && $entBal !== null) : ?>
        <form method="post" action="/app?page=zeiterfassung-urlaub" class="dg-form" style="margin-top:1rem">
          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
          <input type="hidden" name="vacation_action" value="save_entitlement">
          <input type="hidden" name="contact_id" value="<?= $entCid ?>">
          <input type="hidden" name="year" value="<?= (int) $year ?>">
          <div class="dg-form-row">
            <label class="dg-field">
              <span class="dg-field-label">Anspruchstage</span>
              <input type="number" name="days_entitled" step="0.5" min="0" max="366"
                     value="<?= View::escape((string) ($entBal['days_entitled'] ?? '0')) ?>" required>
            </label>
            <label class="dg-field">
              <span class="dg-field-label">Übertrag</span>
              <input type="number" name="days_carried" step="0.5" min="0" max="366"
                     value="<?= View::escape((string) ($entBal['days_carried'] ?? '0')) ?>" required>
            </label>
          </div>
          <label class="dg-field">
            <span class="dg-field-label">Notiz</span>
            <input type="text" name="note" maxlength="255" value="<?= View::escape((string) ($entBal['note'] ?? '')) ?>">
          </label>
          <p class="dg-field-hint">
            Aktuell genommen: <?= View::escape(number_format((float) ($entBal['days_used'] ?? 0), 1, ',', '')) ?>
            · Rest nach Speichern: <?= View::escape(number_format((float) ($entBal['days_rest'] ?? 0), 1, ',', '')) ?>
            (Rest aktualisiert sich nach Speichern neu)
          </p>
          <button type="submit" class="dg-button dg-button--primary">Anspruch speichern</button>
        </form>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>
