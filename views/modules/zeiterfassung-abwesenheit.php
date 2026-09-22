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
 * @var array<int, list<array{id: int, original_name: string, mime: string, size_bytes: int}>> $timeAbsAttachments
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
$attMap = is_array($timeAbsAttachments ?? null) ? $timeAbsAttachments : [];
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

$renderAtts = static function (int $absenceId) use ($attMap): void {
    $atts = is_array($attMap[$absenceId] ?? null) ? $attMap[$absenceId] : [];
    if ($atts === []) {
        echo '<span class="dg-muted">—</span>';

        return;
    }
    echo '<ul class="dg-list" style="margin:0;padding-left:1.1rem">';
    foreach ($atts as $att) {
        $aid = (int) ($att['id'] ?? 0);
        $name = (string) ($att['original_name'] ?? 'Nachweis');
        echo '<li><a href="/app?page=zeiterfassung-abwesenheit&amp;download_attachment=' . $aid . '">'
            . View::escape($name) . '</a></li>';
    }
    echo '</ul>';
};
?>
<div class="dg-wrap dg-zeiterfassung-abwesenheit">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Abwesenheit</h1>
      <p class="dg-lead">Z4d — Urlaub / Krankheit / Sonstiges<?= $canTeam ? ' · Team-Kalender' : '' ?></p>
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
      <form method="post" action="/app?page=zeiterfassung-abwesenheit" class="dg-form" enctype="multipart/form-data">
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
          <span class="dg-field-label">Nachweis (optional) — Foto/Scan/PDF</span>
          <input type="file" name="evidence[]" accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf" multiple>
        </label>
        <label class="dg-field">
          <span class="dg-field-label">Attest-Hinweis (optional)</span>
          <input type="text" name="document_ref" maxlength="255" placeholder="z. B. medical_certificates:0 oder Aktennotiz">
        </label>
        <p class="dg-field-hint">
          Status zunächst „beantragt“ — HR bestätigt. JPG/PNG/WebP/PDF, max. 5 Dateien à 10&nbsp;MB.
        </p>
        <button type="submit" class="dg-button dg-button--primary">Krankmeldung senden</button>
      </form>
    </section>

    <section class="dg-panel">
      <h2 class="dg-subsection-title">Meine Abwesenheiten (ohne Urlaub)</h2>
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
                <th>Nachweis</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($ownNonVac as $row) : ?>
                <?php $rowId = (int) ($row['id'] ?? 0); ?>
                <tr>
                  <td><?= View::escape(TimeAbsenceService::typeLabel((string) ($row['type'] ?? ''))) ?></td>
                  <td>
                    <?= View::escape((string) ($row['date_from'] ?? '')) ?>
                    – <?= View::escape((string) ($row['date_to'] ?? '')) ?>
                  </td>
                  <td class="dg-table__num"><?= View::escape(number_format((float) ($row['days_count'] ?? 0), 1, ',', '')) ?></td>
                  <td><?= View::escape(TimeVacationService::statusLabel((string) ($row['status'] ?? ''))) ?></td>
                  <td>
                    <?php $renderAtts($rowId); ?>
                    <?php if (trim((string) ($row['document_ref'] ?? '')) !== '') : ?>
                      <div class="dg-muted"><?= View::escape((string) $row['document_ref']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ((string) ($row['status'] ?? '') === 'requested') : ?>
                      <form method="post" action="/app?page=zeiterfassung-abwesenheit" style="display:inline" onsubmit="return confirm('Zurückziehen?');">
                        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                        <input type="hidden" name="absence_action" value="cancel">
                        <input type="hidden" name="absence_id" value="<?= $rowId ?>">
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
    <div class="dg-flash dg-flash--warning">Kein Mitarbeiter-Kontakt verknüpft — eigene Krankmeldung nicht möglich. Als HR können Sie unten Abwesenheit für Mitarbeiter erfassen.</div>
  <?php endif; ?>

  <?php if ($canTeam) : ?>
    <section class="dg-panel">
      <h2 class="dg-subsection-title">HR: Abwesenheit für Mitarbeiter erfassen</h2>
      <form method="post" action="/app?page=zeiterfassung-abwesenheit" class="dg-form" enctype="multipart/form-data">
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
          <select name="type" id="abs-hr-type" required>
            <option value="vacation">Urlaub</option>
            <option value="sick">Krankheit</option>
            <option value="ot_comp">Überstundenabbau</option>
            <option value="unpaid_leave">Unbezahlter Urlaub</option>
            <option value="special_leave">Sonderurlaub</option>
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
        <label class="dg-field" id="abs-hr-halfday-wrap">
          <span class="dg-field-label">
            <input type="checkbox" name="half_day" value="1"> Halber Tag (nur Urlaub, gleiches Von/Bis)
          </span>
        </label>
        <label class="dg-field">
          <span class="dg-field-label">Grund</span>
          <textarea name="reason" rows="2" required maxlength="500"></textarea>
        </label>
        <label class="dg-field" id="abs-hr-evidence-wrap">
          <span class="dg-field-label">Nachweis (optional) — Foto/Scan/PDF</span>
          <input type="file" name="evidence[]" accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf" multiple>
        </label>
        <label class="dg-field" id="abs-hr-doc-wrap">
          <span class="dg-field-label">Attest-Ref (optional, Textnotiz)</span>
          <input type="text" name="document_ref" maxlength="255" placeholder="Kontaktakte medical_certificates:Index oder Notiz">
        </label>
        <p class="dg-field-hint">Wird sofort als genehmigt gespeichert (HR-Erfassung). Nachweis-Upload bei Krankheit und Sonderurlaub. Überstundenabbau schreibt Soll-Stunden vom Zeitkonto ab und gutschreibt sie als Ist.</p>
        <button type="submit" class="dg-button dg-button--primary">Erfassen</button>
      </form>
      <script>
        (function () {
          var typeEl = document.getElementById('abs-hr-type');
          var halfWrap = document.getElementById('abs-hr-halfday-wrap');
          var docWrap = document.getElementById('abs-hr-doc-wrap');
          var evWrap = document.getElementById('abs-hr-evidence-wrap');
          if (!typeEl || !halfWrap || !docWrap || !evWrap) return;
          function sync() {
            var t = typeEl.value;
            var isVac = t === 'vacation';
            var showDoc = t === 'sick' || t === 'other' || t === 'special_leave';
            var showEv = t === 'sick' || t === 'special_leave';
            halfWrap.style.display = isVac ? '' : 'none';
            docWrap.style.display = showDoc ? '' : 'none';
            evWrap.style.display = showEv ? '' : 'none';
            if (!isVac) {
              var cb = halfWrap.querySelector('input[type=checkbox]');
              if (cb) cb.checked = false;
            }
            if (!showEv) {
              var fi = evWrap.querySelector('input[type=file]');
              if (fi) fi.value = '';
            }
          }
          typeEl.addEventListener('change', sync);
          sync();
        })();
      </script>
    </section>

    <section class="dg-panel">
      <h2 class="dg-subsection-title">Offene Abwesenheits-Bestätigungen</h2>
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
                <th>Nachweis</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pending as $row) : ?>
                <?php
                  $cid = (int) ($row['contact_id'] ?? 0);
                  $rowId = (int) ($row['id'] ?? 0);
                ?>
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
                  <td>
                    <?php $renderAtts($rowId); ?>
                    <?php if (trim((string) ($row['document_ref'] ?? '')) !== '') : ?>
                      <div class="dg-muted"><?= View::escape((string) $row['document_ref']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <form method="post" action="/app?page=zeiterfassung-abwesenheit" style="display:inline">
                      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                      <input type="hidden" name="absence_action" value="approve">
                      <input type="hidden" name="absence_id" value="<?= $rowId ?>">
                      <button type="submit" class="dg-button dg-button--small dg-button--primary">Bestätigen</button>
                    </form>
                    <form method="post" action="/app?page=zeiterfassung-abwesenheit" style="display:inline-block;margin-top:0.35rem">
                      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                      <input type="hidden" name="absence_action" value="reject">
                      <input type="hidden" name="absence_id" value="<?= $rowId ?>">
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
      <p class="dg-field-hint">U = Urlaub · K = Krankheit · Ü = Überstundenabbau · N = Unbezahlt · So = Sonderurlaub · S = Sonstiges</p>
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
