<?php
/** @var array<string, string> $form */
/** @var int|null $bookingId */
/** @var string|null $formError */
/** @var list<array{id: int, title: string, work_minutes: int, area_id: int, uses_employees: bool}> $bookingArticleOptions */
$isEdit = ($bookingId ?? 0) > 0;
$slotDate = $form['slot_date'] ?? '';
$slotTime = $form['slot_time'] ?? '';
if ($slotDate === '' && ($form['slot_datetime'] ?? '') !== '') {
    $ts = strtotime((string) $form['slot_datetime']);
    if ($ts) {
        $slotDate = date('Y-m-d', $ts);
        $slotTime = date('H:i', $ts);
    }
}
$selectedArticleId = (int) ($form['article_id'] ?? 0);
$selectedEmployeeId = (int) ($form['employee_id'] ?? 0);
$bookingArticleOptions = $bookingArticleOptions ?? [];
?>
<div class="dg-wrap">
  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <?php
        $bookingBackHref = $isEdit ? '/app?page=terminkalender&action=view&id=' . (int) $bookingId : '/app?page=terminkalender';
        $bookingBackLabel = $isEdit ? 'Zurück zum Termin' : 'Zurück zur Liste';
        View::partial('partials/back-nav', [
            'href' => $bookingBackHref,
            'label' => $bookingBackLabel,
        ]);
      ?>
      <h1 class="dg-page-title"><?= $isEdit ? 'Termin bearbeiten' : 'Neuer Termin' ?></h1>
      <p class="dg-lead">Leistung, optional Mitarbeiter und freie Termine nach Arbeitszeiten und Dauer.</p>
    </div>
  </header>

  <?php if ($formError ?? '') : ?>
    <div class="dg-flash dg-flash--error"><?= View::escape($formError) ?></div>
  <?php endif; ?>

  <form class="dg-form dg-panel" method="post" action="/app?page=terminkalender" id="dg-booking-form">
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
    <input type="hidden" name="booking_save" value="1">
    <input type="hidden" name="slot_datetime" id="dg-booking-slot-datetime" value="<?= View::escape($form['slot_datetime'] ?? '') ?>">
    <?php if ($isEdit) : ?><input type="hidden" name="id" value="<?= (int) $bookingId ?>"><?php endif; ?>

    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Leistung</span>
        <select name="article_id" id="dg-booking-article">
          <option value="0"<?= $selectedArticleId === 0 ? ' selected' : '' ?>>— Keine Leistung (15 Min.) —</option>
          <?php foreach ($bookingArticleOptions as $article) : ?>
            <option
              value="<?= (int) $article['id'] ?>"
              data-minutes="<?= (int) $article['work_minutes'] ?>"
              data-area="<?= (int) $article['area_id'] ?>"
              data-uses-employees="<?= !empty($article['uses_employees']) ? '1' : '0' ?>"
              <?= $selectedArticleId === (int) $article['id'] ? ' selected' : '' ?>
            ><?= View::escape($article['title']) ?> (<?= View::escape(CalendarArticleRepository::formatDuration((int) $article['work_minutes'])) ?><?= (float) ($article['price_gross'] ?? 0) > 0 ? ' · ' . View::escape(CalendarArticleValidator::formatPrice((float) $article['price_gross'])) : '' ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field" id="dg-booking-employee-field">
        <span>Mitarbeiter</span>
        <select name="employee_id" id="dg-booking-employee">
          <option value="0">— Beliebig / nicht zugeordnet —</option>
        </select>
        <small class="dg-field-hint" id="dg-booking-employee-hint">Bei Leistungen mit Bereich: optional einen Mitarbeiter wählen.</small>
      </label>
      <label class="dg-field">
        <span>Datum *</span>
        <input type="date" name="slot_date" id="dg-booking-slot-date" value="<?= View::escape($slotDate) ?>" required>
      </label>
      <label class="dg-field">
        <span>Uhrzeit *</span>
        <select name="slot_time" id="dg-booking-slot-time" required>
          <option value="">Bitte Datum wählen …</option>
          <?php if ($slotTime !== '') : ?>
            <option value="<?= View::escape($slotTime) ?>" selected><?= View::escape($slotTime) ?></option>
          <?php endif; ?>
        </select>
        <small class="dg-field-hint" id="dg-booking-slot-hint">Zeitraster: <?= (int) BookingSlotService::slotStepMinutes() ?> Minuten</small>
      </label>
      <label class="dg-field">
        <span>Status</span>
        <select name="status" id="dg-booking-status">
          <?php foreach (['gebucht', 'bestätigt', 'storniert', 'abgeschlossen'] as $st) : ?>
            <option value="<?= $st ?>"<?= ($form['status'] ?? '') === $st ? ' selected' : '' ?>><?= ucfirst($st) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="dg-field"><span>Kunde *</span><input name="customer_name" value="<?= View::escape($form['customer_name']) ?>" required></label>
      <label class="dg-field"><span>E-Mail</span><input type="email" name="customer_email" value="<?= View::escape($form['customer_email']) ?>"></label>
      <label class="dg-field"><span>Telefon</span><input name="customer_phone" value="<?= View::escape($form['customer_phone']) ?>"></label>
      <label class="dg-field dg-field--wide"><span>Notizen</span><textarea name="admin_notes" rows="3"><?= View::escape($form['admin_notes']) ?></textarea></label>
    </div>

    <div class="dg-form-actions">
      <button type="submit" class="dg-button dg-button--primary">Speichern</button>
      <a class="dg-button" href="<?= View::escape($bookingBackHref) ?>">Abbrechen</a>
    </div>
  </form>
</div>
