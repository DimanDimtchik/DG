<?php
/**
 * Geschenkgutscheine — Stamm / Restwerte.
 *
 * @var list<array<string, mixed>> $giftVouchers
 * @var string|null $formError
 * @var array{code?: string, title?: string, face_value_gross?: string, valid_until?: string, notes?: string, contact_id?: string}|null $giftForm
 */
$giftVouchers = is_array($giftVouchers ?? null) ? $giftVouchers : [];
$giftForm = is_array($giftForm ?? null) ? $giftForm : [];
$formError = isset($formError) ? (string) $formError : '';
?>
<div class="dg-page">
  <header class="dg-page-header">
    <div>
      <h1 class="dg-page-title">Gutscheine</h1>
      <p class="dg-lead">Geschenkgutscheine mit Code und Restwert. Auf Rechnungen (Einnahmen) unter den Positionen einlösen.</p>
    </div>
    <p class="dg-form-actions">
      <a class="dg-button" href="/app?page=buchhaltung-belege">Zu den Belegen</a>
    </p>
  </header>

  <?php if ($formError !== '') : ?>
    <p class="dg-flash dg-flash--error" role="alert"><?= View::escape($formError) ?></p>
  <?php endif; ?>

  <section class="dg-form-section">
    <div class="dg-form-section__head">
      <h2 class="dg-subsection-title">Neuer Gutschein</h2>
    </div>
    <form method="post" action="/app?page=buchhaltung-gutscheine" class="dg-form">
      <?= Csrf::field() ?>
      <input type="hidden" name="gift_voucher_create" value="1">
      <div class="dg-form-grid">
        <label class="dg-field">
          <span>Code</span>
          <input name="code" maxlength="64" value="<?= View::escape((string) ($giftForm['code'] ?? '')) ?>" placeholder="leer = automatisch (GS-…)">
        </label>
        <label class="dg-field">
          <span>Bezeichnung</span>
          <input name="title" maxlength="191" value="<?= View::escape((string) ($giftForm['title'] ?? 'Geschenkgutschein')) ?>" required>
        </label>
        <label class="dg-field">
          <span>Nennwert brutto €</span>
          <input name="face_value_gross" inputmode="decimal" value="<?= View::escape((string) ($giftForm['face_value_gross'] ?? '')) ?>" placeholder="50,00" required>
        </label>
        <label class="dg-field">
          <span>Gültig bis</span>
          <input type="date" name="valid_until" value="<?= View::escape((string) ($giftForm['valid_until'] ?? '')) ?>">
        </label>
        <label class="dg-field dg-field--wide">
          <span>Notiz</span>
          <input name="notes" maxlength="500" value="<?= View::escape((string) ($giftForm['notes'] ?? '')) ?>">
        </label>
      </div>
      <p class="dg-form-actions">
        <button type="submit" class="dg-button dg-button--primary">Gutschein anlegen</button>
      </p>
    </form>
  </section>

  <section class="dg-form-section">
    <div class="dg-form-section__head">
      <h2 class="dg-subsection-title">Bestand</h2>
    </div>
    <?php if ($giftVouchers === []) : ?>
      <p class="dg-muted">Noch keine Gutscheine angelegt.</p>
    <?php else : ?>
      <div class="dg-table-wrap">
        <table class="dg-table">
          <thead>
            <tr>
              <th>Code</th>
              <th>Bezeichnung</th>
              <th>Nennwert</th>
              <th>Restwert</th>
              <th>Status</th>
              <th>Gültig bis</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($giftVouchers as $gv) : ?>
              <tr>
                <td><?= View::escape((string) ($gv['code'] ?? '')) ?></td>
                <td><?= View::escape((string) ($gv['title'] ?? '')) ?></td>
                <td><?= View::escape(number_format((float) ($gv['face_value_gross'] ?? 0), 2, ',', '.')) ?> €</td>
                <td><?= View::escape(number_format((float) ($gv['remaining_gross'] ?? 0), 2, ',', '.')) ?> €</td>
                <td><?= View::escape((string) ($gv['status'] ?? '')) ?></td>
                <td><?= View::escape((string) ($gv['valid_until'] ?? '—')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>
