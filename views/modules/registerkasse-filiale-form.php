<?php
/**
 * Registerkasse — Filiale anlegen/bearbeiten (K4b Formular; Speichern = K4c).
 *
 * @var array<string, mixed> $cashLocationForm
 * @var int $cashLocationId
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$form = $cashLocationForm ?? [];
$id = (int) ($cashLocationId ?? 0);
$isEdit = $id > 0;
$disabled = !$canEdit || !$dbConnected;
$csrf = Csrf::token();
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <a class="dg-button dg-button--back" href="/app?page=registerkasse-filialen">← Zurück zur Liste</a>

  <header class="dg-page-header">
    <h1 class="dg-page-title"><?= $isEdit ? 'Filiale bearbeiten' : 'Filiale anlegen' ?></h1>
    <p class="dg-lead">Betriebsstätte mit Adresse — Grundlage für Kassen und TSE.</p>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Datenbank nicht verbunden.
      <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Einstellungen → Datenbank</a>
    </div>
  <?php endif; ?>

  <form class="dg-form dg-panel" method="post" action="/app?page=registerkasse-filiale-form<?= $isEdit ? '&amp;id=' . $id : '' ?>">
    <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
    <input type="hidden" name="cash_location_save" value="1">
    <?php if ($isEdit) : ?>
      <input type="hidden" name="cash_location_id" value="<?= $id ?>">
    <?php endif; ?>

    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Kürzel *</span>
        <input type="text" name="code" maxlength="32" required
               value="<?= View::escape((string) ($form['code'] ?? '')) ?>"
               <?= $disabled ? ' disabled' : '' ?><?= $isEdit ? ' readonly' : '' ?>
               placeholder="HAUPT">
        <?php if ($isEdit) : ?>
          <small class="dg-field-hint">Kürzel bleibt nach dem Anlegen fest.</small>
        <?php endif; ?>
      </label>

      <label class="dg-field">
        <span>Name *</span>
        <input type="text" name="name" maxlength="255" required
               value="<?= View::escape((string) ($form['name'] ?? '')) ?>"
               <?= $disabled ? ' disabled' : '' ?>
               placeholder="Hauptfiliale">
      </label>

      <label class="dg-field dg-field--full">
        <span>Straße und Nr.</span>
        <input type="text" name="street" maxlength="255"
               value="<?= View::escape((string) ($form['street'] ?? '')) ?>"
               <?= $disabled ? ' disabled' : '' ?>>
      </label>

      <label class="dg-field">
        <span>PLZ</span>
        <input type="text" name="zip" maxlength="16"
               value="<?= View::escape((string) ($form['zip'] ?? '')) ?>"
               <?= $disabled ? ' disabled' : '' ?>>
      </label>

      <label class="dg-field">
        <span>Ort</span>
        <input type="text" name="city" maxlength="128"
               value="<?= View::escape((string) ($form['city'] ?? '')) ?>"
               <?= $disabled ? ' disabled' : '' ?>>
      </label>

      <label class="dg-field">
        <span>Land</span>
        <input type="text" name="country" maxlength="2"
               value="<?= View::escape((string) ($form['country'] ?? 'DE')) ?>"
               <?= $disabled ? ' disabled' : '' ?>
               placeholder="DE">
      </label>

      <label class="dg-field dg-field--full">
        <span>Notiz</span>
        <textarea name="notes" rows="3" maxlength="500" <?= $disabled ? ' disabled' : '' ?>><?= View::escape((string) ($form['notes'] ?? '')) ?></textarea>
      </label>

      <label class="dg-field dg-field--checkbox">
        <input type="checkbox" name="is_active" value="1"
               <?= !empty($form['is_active']) || (!$isEdit && !array_key_exists('is_active', $form)) ? ' checked' : '' ?>
               <?= $disabled ? ' disabled' : '' ?>>
        <span>Aktiv (für neue Kassen nutzbar)</span>
      </label>
    </div>

    <?php if ($canEdit && $dbConnected) : ?>
      <div class="dg-form-actions">
        <button type="submit" class="dg-button dg-button--primary">Speichern</button>
        <a class="dg-button" href="/app?page=registerkasse-filialen">Abbrechen</a>
      </div>
    <?php endif; ?>
  </form>
</div>
