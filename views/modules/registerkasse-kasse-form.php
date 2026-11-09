<?php
/**
 * Registerkasse — Kasse anlegen/bearbeiten (K5b/K10a Typ inkl. mobile).
 *
 * @var array<string, mixed> $cashRegisterForm
 * @var int $cashRegisterId
 * @var list<array<string, mixed>> $cashLocationOptions
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$form = $cashRegisterForm ?? [];
$id = (int) ($cashRegisterId ?? 0);
$locations = $cashLocationOptions ?? [];
$isEdit = $id > 0;
$disabled = !$canEdit || !$dbConnected;
$typeLabels = CashRegisterRepository::typeLabels();
$csrf = Csrf::token();
$backLocation = (int) ($form['location_id'] ?? 0);
$backHref = '/app?page=registerkasse-kassen' . ($backLocation > 0 ? '&location_id=' . $backLocation : '');
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <a class="dg-button dg-button--back" href="<?= View::escape($backHref) ?>">← Zurück zur Liste</a>

  <header class="dg-page-header">
    <h1 class="dg-page-title"><?= $isEdit ? 'Kasse bearbeiten' : 'Kasse anlegen' ?></h1>
    <p class="dg-lead">Kassen-ID, Filiale und Bon-Nummernkreis — Ladenkasse, Schalter oder mobile Kasse.</p>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Datenbank nicht verbunden.
      <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Einstellungen → Datenbank</a>
    </div>
  <?php elseif ($locations === []) : ?>
    <div class="dg-flash dg-flash--warning">
      Bitte zuerst eine Filiale anlegen.
      <a href="/app?page=registerkasse-filiale-form">Filiale anlegen</a>
    </div>
  <?php endif; ?>

  <form class="dg-form dg-panel" method="post" action="/app?page=registerkasse-kasse-form<?= $isEdit ? '&amp;id=' . $id : '' ?>">
    <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
    <input type="hidden" name="cash_register_save" value="1">
    <?php if ($isEdit) : ?>
      <input type="hidden" name="cash_register_id" value="<?= $id ?>">
    <?php endif; ?>

    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Filiale *</span>
        <select name="location_id" required <?= $disabled || $locations === [] ? ' disabled' : '' ?>>
          <option value="">— bitte wählen —</option>
          <?php foreach ($locations as $loc) : ?>
            <?php
              $lid = (int) ($loc['id'] ?? 0);
              $sel = (int) ($form['location_id'] ?? 0) === $lid;
            ?>
            <option value="<?= $lid ?>"<?= $sel ? ' selected' : '' ?>>
              <?= View::escape(trim((string) ($loc['code'] ?? '') . ' — ' . (string) ($loc['name'] ?? ''))) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="dg-field">
        <span>Kassen-ID *</span>
        <input type="text" name="code" maxlength="32" required
               value="<?= View::escape((string) ($form['code'] ?? '')) ?>"
               <?= $disabled ? ' disabled' : '' ?><?= $isEdit ? ' readonly' : '' ?>
               placeholder="KASSE-01">
        <?php if ($isEdit) : ?>
          <small class="dg-field-hint">Kassen-ID bleibt nach dem Anlegen fest.</small>
        <?php else : ?>
          <small class="dg-field-hint">Eindeutig in der Instanz; später auch TSE-Client-Serial.</small>
        <?php endif; ?>
      </label>

      <label class="dg-field">
        <span>Name *</span>
        <input type="text" name="name" maxlength="255" required
               value="<?= View::escape((string) ($form['name'] ?? '')) ?>"
               <?= $disabled ? ' disabled' : '' ?>
               placeholder="Ladenkasse 1">
      </label>

      <label class="dg-field">
        <span>Typ</span>
        <select name="register_type" <?= $disabled ? ' disabled' : '' ?>>
          <?php foreach ($typeLabels as $typeKey => $typeLabel) : ?>
            <option value="<?= View::escape($typeKey) ?>"<?= ((string) ($form['register_type'] ?? 'store') === $typeKey) ? ' selected' : '' ?>>
              <?= View::escape($typeLabel) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <small class="dg-field-hint">Mobile Kasse = eigener Nummernkreis und eigene TSE-Zuordnung, nicht an einen Ladentisch gebunden.</small>
      </label>

      <label class="dg-field">
        <span>Nächste Bonnummer *</span>
        <input type="number" name="next_receipt_number" min="1" required
               value="<?= View::escape((string) ($form['next_receipt_number'] ?? '1')) ?>"
               <?= $disabled ? ' disabled' : '' ?>>
      </label>

      <label class="dg-field">
        <span>Bonnummer-Präfix</span>
        <input type="text" name="receipt_number_prefix" maxlength="16"
               value="<?= View::escape((string) ($form['receipt_number_prefix'] ?? '')) ?>"
               <?= $disabled ? ' disabled' : '' ?>
               placeholder="optional">
      </label>

      <label class="dg-field dg-field--full">
        <span>Notiz</span>
        <textarea name="notes" rows="3" maxlength="500" <?= $disabled ? ' disabled' : '' ?>><?= View::escape((string) ($form['notes'] ?? '')) ?></textarea>
      </label>

      <label class="dg-field dg-field--checkbox">
        <input type="checkbox" name="is_active" value="1"
               <?= !empty($form['is_active']) || (!$isEdit && !array_key_exists('is_active', $form)) ? ' checked' : '' ?>
               <?= $disabled ? ' disabled' : '' ?>>
        <span>Aktiv</span>
      </label>
    </div>

    <?php if ($canEdit && $dbConnected && $locations !== []) : ?>
      <div class="dg-form-actions">
        <button type="submit" class="dg-button dg-button--primary">Speichern</button>
        <a class="dg-button" href="<?= View::escape($backHref) ?>">Abbrechen</a>
      </div>
    <?php endif; ?>
  </form>
</div>
