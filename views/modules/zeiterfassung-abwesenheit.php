<?php
/**
 * Z4d Abwesenheit: Krank/Sonstiges + Team-Monatskalender.
 *
 * @var int|null $timeAbsContactId
 * @var string $timeAbsContactLabel
 * @var list<array<string, mixed>> $timeAbsOwnList
 * @var list<array<string, mixed>> $timeAbsPending
 * @var list<array{id: int, label: string}> $timeAbsStaffOptions
 * @var bool $timeAbsCanTeam
 * @var string $timeAbsYearMonth
 * @var array{year_month: string, days: list<array<string, mixed>>}|null $timeAbsCalendar
 * @var array{type: string, message: string}|null $flash
 */
$ownCid = (int) ($timeAbsContactId ?? 0);
$ownLabel = (string) ($timeAbsContactLabel ?? '');
$ownList = is_array($timeAbsOwnList ?? null) ? $timeAbsOwnList : [];
$pending = is_array($timeAbsPending ?? null) ? $timeAbsPending : [];
$staff = is_array($timeAbsStaffOptions ?? null) ? $timeAbsStaffOptions : [];
$canTeam = !empty($timeAbsCanTeam);
$ym = (string) ($timeAbsYearMonth ?? date('Y-m'));
$cal = is_array($timeAbsCalendar ?? null) ? $timeAbsCalendar : null;
$prev = (new DateTimeImmutable($ym . '-01'))->modify('-1 month')->format('Y-m');
$next = (new DateTimeImmutable($ym . '-01'))->modify('+1 month')->format('Y-m');

$staffLabel = static function (int $cid) use ($staff): string {
    foreach ($staff as $opt) {
        if ((int) ($opt['id'] ?? 0) === $cid) {
            return (string) ($opt['label'] ?? ('#' . $cid));
        }
    }

    return '#' . $cid;
};
?>
<div class="dg-wrap dg-zeiterfassung-abwesenheit">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Abwesenheit</h1>
      <p class="dg-lead">Z4d — Krankheit / Sonstiges<?= $canTeam ? ' · Team-Kalender' : '' ?></p>
    </div>
    <div class="dg-toolbar">
      <a class="dg-button" href="/app?page=zeiterfassung-urlaub">Urlaub</a>
      <a class="dg-button" href="/app?page=zeiterfassung">Stempeluhr</a>
      <?php if ($canTeam) : ?>
        <a class="dg-button" href="/app?page=zeiterfassung-team">Team heute</a>
      <?php endif; ?>
    </div>
  </header>

  <?php if ($ownCid > 0) : ?>
    <section class="dg-panel">
      <h2 class="dg-subsection-title">Eigene Krankmeldung — <?= View::escape($ownLabel !== '' ? $ownLabel : ('#' . $ownCid)) ?></h2>
      <form method="post" action="/app?page=zeiterfassung-abwesenheit" class="dg-form">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <input type="hidden" name="absence_action" value="request_sick">
        <div class="dg-form-row">
          <label class="dg-field">
            <span class="dg-field-label">Von</span>
            <input type="date" name="date_from" required>
          </label>
          <label class="dg-field">
            <span class="dg-field-label">Bis</span>
            <input type="date" name="date_to" required>
          </label>
        </div>
        <label class="dg-field">
          <span class="dg-field-label">Grund</span>
          <textarea name="reason" rows="2" required maxlength="500" placeholder="z. B. Erkrankung"></textarea>
        </label>
        <label class="dg-field">
          <span class="dg-field-label">Attest-Hinweis (optional)</span>
          <input type="text" name="document_ref" maxlength="255" placeholder="z. B. medical_certificates:0 oder Aktennotiz">
        </label>
        <p class="dg-field-hint">
          Status zunächst „beantragt“ — HR bestätigt.
          Atteste in <a href="/app?page=kontakte&amp;action=view&amp;id=<?= $ownCid ?>">Kontaktakte</a> ablegen und hier referenzieren.
        </p>
        <button type="submit" class="dg-button dg-button--primary">Krankmeldung senden</button>
      </form>
    </section>

    <section class="dg-panel">
      <h2 class="dg-subsection-title">Meine Krank-/Sonstiges-Einträge</h2>
      <?php
      $ownNonVac = array_values(array_filter(
          $ownList,
          static fn ($r): bool => is_array($r) && (string) ($r['type'] ?? '') !== 'vacation'
      ));
      ?>
      <?php if ($ownNonVac === []) : ?>
        <p class="dg-muted">Keine Einträge.</p>
      <?php else : ?>
        <div class="dg-table-wrap">
          <table class="dg-table">
            <thead>
              <tr>
                <th>Typ</th>
                <th>Zeitraum</th>
                <th class="dg-table__num">Tage</th>
                <th>Status</th>
                <th>Attest</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($ownNonVac as $row) : ?>
                <tr>
                  <td><?= View::escape(TimeAbsenceService::typeLabel((string) ($row['type'] ?? ''))) ?></td>
                  <td>
                    <?= View::escape((string) ($row['date_from'] ?? '')) ?>
                    – <?= View::escape((string) ($row['date_to'] ?? '')) ?>
                  </td>
                  <td class="dg-table__num"><?= View::escape(number_format((float) ($row['days_count'] ?? 0), 1, ',', '')) ?></td>
                  <td><?= View::escape(TimeVacationService::statusLabel((string) ($row['status'] ?? ''))) ?></td>
                  <td class="dg-muted"><?= View::escape((string) ($row['document_ref'] ?? '—')) ?></td>
                  <td>
                    <?php if ((string) ($row['status'] ?? '') === 'requested') : ?>
                      <form method="post" action="/app?page=zeiterfassung-abwesenheit" style="display:inline" onsubmit="return confirm('Zurückziehen?');">
                        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                        <input type="hidden" name="absence_action" value="cancel">
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
  <?php else : ?>
    <div class="dg-flash dg-flash--warning">Kein Mitarbeiter-Kontakt verknüpft — eigene Krankmeldung nicht möglich.</div>
  <?php endif; ?>

  <?php if ($canTeam) : ?>
    <section class="dg-panel">
      <h2 class="dg-subsection-title">HR: Krankheit / Sonstiges erfassen</h2>
      <form method="post" action="/app?page=zeiterfassung-abwesenheit" class="dg-form">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <input type="hidden" name="absence_action" value="record">
        <label class="dg-field">
          <span class="dg-field-label">Mitarbeiter</span>
          <select name="contact_id" required>
            <option value="">— wählen —</option>
            <?php foreach ($staff as $opt) : ?>
              <option value="<?= (int) ($opt['id'] ?? 0) ?>"><?= View::escape((string) ($opt['label'] ?? '')) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dg-field">
          <span class="dg-field-label">Typ</span>
          <select name="type" required>
            <option value="sick">Krankheit</option>
            <option value="other">Sonstiges</option>
          </select>
        </label>
        <div class="dg-form-row">
          <label class="dg-field">
            <span class="dg-field-label">Von</span>
            <input type="date" name="date_from" required>
          </label>
          <label class="dg-field">
            <span class="dg-field-label">Bis</span>
            <input type="date" name="date_to" required>
          </label>
        </div>
        <label class="dg-field">
          <span class="dg-field-label">Grund</span>
          <textarea name="reason" rows="2" required maxlength="500"></textarea>
        </label>
        <label class="dg-field">
          <span class="dg-field-label">Attest-Ref (optional)</span>
          <input type="text" name="document_ref" maxlength="255" placeholder="Kontaktakte medical_certificates:Index oder Notiz">
        </label>
        <p class="dg-field-hint">Wird sofort als genehmigt gespeichert (HR-Erfassung).</p>
        <button type="submit" class="dg-button dg-button--primary">Erfassen</button>
      </form>
    </section>

    <section class="dg-panel">
      <h2 class="dg-subsection-title">Offene Krank-/Sonstiges-Bestätigungen</h2>
      <?php if ($pending === []) : ?>
        <p class="dg-muted">Keine offenen Meldungen.</p>
      <?php else : ?>
        <div class="dg-table-wrap">
          <table class="dg-table">
            <thead>
              <tr>
                <th>Mitarbeiter</th>
                <th>Typ</th>
                <th>Zeitraum</th>
                <th>Attest</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pending as $row) : ?>
                <?php $cid = (int) ($row['contact_id'] ?? 0); ?>
                <tr>
                  <td>
                    <?= View::escape($staffLabel($cid)) ?>
                    <?php if ($cid > 0) : ?>
                      <a class="dg-muted" href="/app?page=kontakte&amp;action=view&amp;id=<?= $cid ?>">Akte</a>
                    <?php endif; ?>
                  </td>
                  <td><?= View::escape(TimeAbsenceService::typeLabel((string) ($row['type'] ?? ''))) ?></td>
                  <td>
                    <?= View::escape((string) ($row['date_from'] ?? '')) ?>
                    – <?= View::escape((string) ($row['date_to'] ?? '')) ?>
                  </td>
                  <td class="dg-muted"><?= View::escape((string) ($row['document_ref'] ?? '—')) ?></td>
                  <td>
                    <form method="post" action="/app?page=zeiterfassung-abwesenheit" style="display:inline">
                      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                      <input type="hidden" name="absence_action" value="approve">
                      <input type="hidden" name="absence_id" value="<?= (int) ($row['id'] ?? 0) ?>">
                      <button type="submit" class="dg-button dg-button--small dg-button--primary">Bestätigen</button>
                    </form>
                    <form method="post" action="/app?page=zeiterfassung-abwesenheit" style="display:inline-block;margin-top:0.35rem">
                      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                      <input type="hidden" name="absence_action" value="reject">
                      <input type="hidden" name="absence_id" value="<?= (int) ($row['id'] ?? 0) ?>">
                      <input type="text" name="reason" required maxlength="500" placeholder="Ablehnungsgrund">
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

    <section class="dg-panel">
      <h2 class="dg-subsection-title">Team-Kalender (genehmigt)</h2>
      <form method="get" action="/app" class="dg-form dg-form--inline">
        <input type="hidden" name="page" value="zeiterfassung-abwesenheit">
        <label class="dg-field">
          <span class="dg-field-label">Monat</span>
          <input type="month" name="month" value="<?= View::escape($ym) ?>">
        </label>
        <button type="submit" class="dg-button">Anzeigen</button>
        <a class="dg-button" href="/app?page=zeiterfassung-abwesenheit&amp;month=<?= View::escape($prev) ?>">←</a>
        <a class="dg-button" href="/app?page=zeiterfassung-abwesenheit&amp;month=<?= View::escape($next) ?>">→</a>
      </form>
      <p class="dg-field-hint">U = Urlaub · K = Krankheit · S = Sonstiges</p>
      <?php if ($cal === null || ($cal['days'] ?? []) === []) : ?>
        <p class="dg-muted">Kein Kalender.</p>
      <?php else : ?>
        <div class="dg-table-wrap">
          <table class="dg-table">
            <thead>
              <tr>
                <th>Tag</th>
                <th>Abwesend</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($cal['days'] as $day) : ?>
                <?php
                $items = is_array($day['items'] ?? null) ? $day['items'] : [];
                $isWeekend = in_array((string) ($day['weekday'] ?? ''), ['Sa', 'So'], true);
                ?>
                <tr<?= $isWeekend ? ' class="dg-muted"' : '' ?>>
                  <td>
                    <?= View::escape((string) ($day['weekday'] ?? '')) ?>
                    <?= View::escape((string) ($day['date_display'] ?? '')) ?>
                  </td>
                  <td>
                    <?php if ($items === []) : ?>
                      —
                    <?php else : ?>
                      <?php foreach ($items as $item) : ?>
                        <span class="dg-badge dg-badge--pending" title="<?= View::escape((string) ($item['type_label'] ?? '')) ?>">
                          <?= View::escape((string) ($item['type_short'] ?? '?')) ?>
                          <?= View::escape((string) ($item['label'] ?? '')) ?>
                        </span>
                        <?php if (!empty($item['document_ref'])) : ?>
                          <span class="dg-muted">(<?= View::escape((string) $item['document_ref']) ?>)</span>
                        <?php endif; ?>
                        <br>
                      <?php endforeach; ?>
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
</div>
