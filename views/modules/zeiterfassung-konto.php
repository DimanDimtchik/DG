<?php
/**
 * @var int|null $timeKontoContactId
 * @var string $timeKontoContactLabel
 * @var int $timeKontoBalanceMinutes
 * @var list<array<string, mixed>> $timeKontoLots
 * @var list<array<string, mixed>> $timeKontoCorrections
 * @var list<array<string, mixed>> $timeKontoReductions
 * @var list<array{id: int, label: string}> $timeKontoStaffOptions
 * @var array{type: string, message: string}|null $flash
 */
$contactId = (int) ($timeKontoContactId ?? 0);
$label = (string) ($timeKontoContactLabel ?? '');
$balance = (int) ($timeKontoBalanceMinutes ?? 0);
$lots = is_array($timeKontoLots ?? null) ? $timeKontoLots : [];
$corrections = is_array($timeKontoCorrections ?? null) ? $timeKontoCorrections : [];
$reductions = is_array($timeKontoReductions ?? null) ? $timeKontoReductions : [];
$staffOptions = is_array($timeKontoStaffOptions ?? null) ? $timeKontoStaffOptions : [];
?>
<div class="dg-wrap dg-zeiterfassung-konto">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Korrektur &amp; Überstundenkonto</h1>
      <p class="dg-lead">Z2e — Stempel-Originale unverändert · nur HR/Admin</p>
    </div>
    <div class="dg-toolbar">
      <a class="dg-button" href="/app?page=zeiterfassung">Stempeluhr</a>
      <a class="dg-button" href="/app?page=zeiterfassung-monat">Monatsblatt</a>
      <a class="dg-button" href="/app?page=zeiterfassung-team">Team heute</a>
    </div>
  </header>

  <section class="dg-panel">
    <form method="get" action="/app" class="dg-form dg-form--inline">
      <input type="hidden" name="page" value="zeiterfassung-konto">
      <label class="dg-field">
        <span class="dg-field-label">Mitarbeiter</span>
        <select name="contact_id" required>
          <?php foreach ($staffOptions as $opt) : ?>
            <option value="<?= (int) $opt['id'] ?>"<?= (int) $opt['id'] === $contactId ? ' selected' : '' ?>>
              <?= View::escape((string) $opt['label']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <button type="submit" class="dg-button">Anzeigen</button>
    </form>
  </section>

  <?php if ($contactId < 1) : ?>
    <div class="dg-flash dg-flash--warning">Bitte Mitarbeiter wählen.</div>
  <?php else : ?>
    <section class="dg-panel">
      <h2 class="dg-subsection-title"><?= View::escape($label) ?></h2>
      <p><strong>Überstunden-Saldo:</strong> <?= View::escape(TimeClockService::formatMinutes($balance)) ?> h</p>
    </section>

    <section class="dg-panel">
      <h2 class="dg-subsection-title">Stempel-Korrektur</h2>
      <p class="dg-field-hint">Passt nur die Ist-Minuten an (±). Originale Stempel bleiben; Audit-Eintrag + Begründung Pflicht.</p>
      <form method="post" action="/app?page=zeiterfassung-konto" class="dg-form">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <input type="hidden" name="time_konto_action" value="correction">
        <input type="hidden" name="contact_id" value="<?= $contactId ?>">
        <label class="dg-field">
          <span class="dg-field-label">Datum</span>
          <input type="date" name="work_date" value="<?= View::escape(date('Y-m-d')) ?>" required>
        </label>
        <label class="dg-field">
          <span class="dg-field-label">Delta Minuten (+/−)</span>
          <input type="number" name="delta_minutes" required step="1" min="-960" max="960" placeholder="z. B. 15 oder -30">
        </label>
        <label class="dg-field">
          <span class="dg-field-label">Begründung</span>
          <input type="text" name="reason" required maxlength="500" minlength="5" placeholder="z. B. vergessen auszustempeln, Nachweis …">
        </label>
        <button type="submit" class="dg-button dg-button--primary">Korrektur buchen</button>
      </form>
    </section>

    <section class="dg-panel">
      <h2 class="dg-subsection-title">Überstunden abbauen</h2>
      <p class="dg-field-hint">FIFO nach Ausgleichsfrist. Minijob / ohne overtime_allowed: gesperrt.</p>
      <form method="post" action="/app?page=zeiterfassung-konto" class="dg-form">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <input type="hidden" name="time_konto_action" value="reduce">
        <input type="hidden" name="contact_id" value="<?= $contactId ?>">
        <label class="dg-field">
          <span class="dg-field-label">Minuten abbauen</span>
          <input type="number" name="minutes" required min="1" max="6000" step="1">
        </label>
        <label class="dg-field">
          <span class="dg-field-label">Begründung</span>
          <input type="text" name="reason" required maxlength="500" minlength="5">
        </label>
        <button type="submit" class="dg-button dg-button--primary">Abbau buchen</button>
      </form>
    </section>

    <?php if ($lots !== []) : ?>
      <section class="dg-panel">
        <h2 class="dg-subsection-title">Offene Lots</h2>
        <div class="dg-table-wrap">
          <table class="dg-table">
            <thead>
              <tr>
                <th>Angesammelt</th>
                <th class="dg-table__num">Rest</th>
                <th>Frist</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($lots as $lot) : ?>
                <tr>
                  <td><?= View::escape((string) ($lot['accrued_date'] ?? '')) ?></td>
                  <td class="dg-table__num"><?= View::escape((string) ($lot['remaining_display'] ?? '')) ?></td>
                  <td><?= View::escape((string) ($lot['expires_display'] ?? '')) ?><?= !empty($lot['is_overdue']) ? ' (überfällig)' : '' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($corrections !== []) : ?>
      <section class="dg-panel">
        <h2 class="dg-subsection-title">Letzte Korrekturen</h2>
        <ul class="dg-list">
          <?php foreach ($corrections as $c) : ?>
            <li>
              <?= View::escape((string) ($c['work_date'] ?? '')) ?>:
              <?= View::escape(TimeClockService::formatSignedCorrection((int) ($c['delta_worked_minutes'] ?? 0))) ?> min
              — <?= View::escape((string) ($c['reason'] ?? '')) ?>
              <span class="dg-muted">(<?= View::escape((string) ($c['created_at'] ?? '')) ?>)</span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <?php if ($reductions !== []) : ?>
      <section class="dg-panel">
        <h2 class="dg-subsection-title">Letzte Abbau-Buchungen</h2>
        <ul class="dg-list">
          <?php foreach ($reductions as $r) : ?>
            <li>
              <?= View::escape(TimeClockService::formatMinutes((int) ($r['minutes'] ?? 0))) ?> h
              — <?= View::escape((string) ($r['reason'] ?? '')) ?>
              <span class="dg-muted">(<?= View::escape((string) ($r['created_at'] ?? '')) ?>)</span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>
  <?php endif; ?>
</div>
