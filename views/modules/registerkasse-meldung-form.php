<?php
/**
 * Registerkasse — Meldefelder einer Kasse (K19a).
 *
 * @var array<string, mixed> $cashErsForm
 * @var array<string, mixed> $cashErsRegister
 * @var int $cashErsRegisterId
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$form = $cashErsForm ?? [];
$register = $cashErsRegister ?? [];
$registerId = (int) ($cashErsRegisterId ?? 0);
$disabled = !$canEdit || !$dbConnected;
$csrf = Csrf::token();
$typeLabels = CashErsClientRepository::typeLabels();
$regLabel = trim((string) ($register['code'] ?? '') . ' — ' . (string) ($register['name'] ?? ''));
$complete = CashErsClientRepository::isCoreComplete($form);
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <a class="dg-button dg-button--back" href="/app?page=registerkasse-meldung">← Zurück zur Übersicht</a>

  <header class="dg-page-header">
    <h1 class="dg-page-title">Meldefelder Kasse</h1>
    <p class="dg-lead"><?= View::escape($regLabel !== ' — ' ? $regLabel : '#' . $registerId) ?></p>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Datenbank nicht verbunden.</div>
  <?php endif; ?>

  <div class="dg-flash dg-flash--info">
    Speichern legt die Angaben nur lokal ab. Es wird nichts an ELSTER oder den TSE-Anbieter gesendet.
    <?php if ($complete) : ?>
      Kernfelder für eine spätere Meldung sind gesetzt.
    <?php else : ?>
      Für die spätere Meldung fehlen noch Hersteller, Modell, Seriennummer, Software und Anschaffungs-/Inbetriebnahmedatum.
    <?php endif; ?>
  </div>

  <form class="dg-form dg-panel" method="post" action="/app?page=registerkasse-meldung-form&amp;register_id=<?= $registerId ?>">
    <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
    <input type="hidden" name="cash_ers_client_save" value="1">
    <input type="hidden" name="register_id" value="<?= $registerId ?>">

    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Systemtyp *</span>
        <select name="ers_type" required <?= $disabled ? ' disabled' : '' ?>>
          <?php foreach ($typeLabels as $key => $label) : ?>
            <option value="<?= View::escape($key) ?>"<?= ((string) ($form['ers_type'] ?? '1') === $key) ? ' selected' : '' ?>>
              <?= View::escape($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="dg-field">
        <span>Hersteller</span>
        <input type="text" name="manufacturer" maxlength="120"
               value="<?= View::escape((string) ($form['manufacturer'] ?? '')) ?>"
               <?= $disabled ? ' disabled' : '' ?>>
      </label>

      <label class="dg-field">
        <span>Modell</span>
        <input type="text" name="model" maxlength="120"
               value="<?= View::escape((string) ($form['model'] ?? '')) ?>"
               <?= $disabled ? ' disabled' : '' ?>>
      </label>

      <label class="dg-field">
        <span>Seriennummer (Aufzeichnungssystem)</span>
        <input type="text" name="serial_number" maxlength="120"
               value="<?= View::escape((string) ($form['serial_number'] ?? '')) ?>"
               <?= $disabled ? ' disabled' : '' ?>>
      </label>

      <label class="dg-field">
        <span>Software</span>
        <input type="text" name="software" maxlength="120"
               value="<?= View::escape((string) ($form['software'] ?? '')) ?>"
               <?= $disabled ? ' disabled' : '' ?>>
      </label>

      <label class="dg-field">
        <span>Software-Version</span>
        <input type="text" name="software_version" maxlength="64"
               value="<?= View::escape((string) ($form['software_version'] ?? '')) ?>"
               <?= $disabled ? ' disabled' : '' ?>>
      </label>

      <label class="dg-field">
        <span>Anschaffung</span>
        <input type="date" name="date_acquisition"
               value="<?= View::escape((string) ($form['date_acquisition'] ?? '')) ?>"
               <?= $disabled ? ' disabled' : '' ?>>
      </label>

      <label class="dg-field">
        <span>Inbetriebnahme Kasse</span>
        <input type="date" name="date_commissioning"
               value="<?= View::escape((string) ($form['date_commissioning'] ?? '')) ?>"
               <?= $disabled ? ' disabled' : '' ?>>
      </label>

      <label class="dg-field">
        <span>Inbetriebnahme TSE an dieser Kasse</span>
        <input type="date" name="date_tss_commissioning"
               value="<?= View::escape((string) ($form['date_tss_commissioning'] ?? '')) ?>"
               <?= $disabled ? ' disabled' : '' ?>>
      </label>

      <label class="dg-field">
        <span>Außerbetriebnahme</span>
        <input type="date" name="date_decommissioning"
               value="<?= View::escape((string) ($form['date_decommissioning'] ?? '')) ?>"
               <?= $disabled ? ' disabled' : '' ?>>
      </label>

      <label class="dg-field">
        <span>Grund Außerbetriebnahme</span>
        <input type="text" name="decommissioning_reason" maxlength="255"
               value="<?= View::escape((string) ($form['decommissioning_reason'] ?? '')) ?>"
               <?= $disabled ? ' disabled' : '' ?>>
      </label>

      <label class="dg-field">
        <span>Bemerkung (Meldung)</span>
        <input type="text" name="remarks" maxlength="500"
               value="<?= View::escape((string) ($form['remarks'] ?? '')) ?>"
               <?= $disabled ? ' disabled' : '' ?>>
      </label>

      <label class="dg-field">
        <span>Interne Notiz</span>
        <input type="text" name="notes" maxlength="500"
               value="<?= View::escape((string) ($form['notes'] ?? '')) ?>"
               <?= $disabled ? ' disabled' : '' ?>>
      </label>
    </div>

    <div class="dg-form-actions">
      <button type="submit" class="dg-button dg-button--primary"<?= $disabled ? ' disabled' : '' ?>>Speichern</button>
      <a class="dg-button" href="/app?page=registerkasse-meldung">Abbrechen</a>
    </div>
  </form>
</div>
