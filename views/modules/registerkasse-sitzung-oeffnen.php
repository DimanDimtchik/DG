<?php
/**
 * Registerkasse — Kasse öffnen (K11a): Bediener + Anfangsbestand.
 *
 * @var array<string, mixed>|null $cashSessionRegister
 * @var list<array<string, mixed>> $cashRegisterOptions
 * @var list<User> $cashSessionOperators
 * @var int $cashSessionRegisterId
 * @var int $cashSessionOpenedBy
 * @var string $cashSessionOpeningBalance
 * @var string $cashSessionBusinessDate
 * @var string $cashSessionNotes
 * @var array<string, mixed>|null $cashSessionOpen
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$register = $cashSessionRegister ?? null;
$registers = $cashRegisterOptions ?? [];
$operators = $cashSessionOperators ?? [];
$registerId = (int) ($cashSessionRegisterId ?? 0);
$openedBy = (int) ($cashSessionOpenedBy ?? 0);
$openingBalance = (string) ($cashSessionOpeningBalance ?? '0,00');
$businessDate = (string) ($cashSessionBusinessDate ?? date('Y-m-d'));
$notes = (string) ($cashSessionNotes ?? '');
$openSession = $cashSessionOpen ?? null;
$disabled = !$canEdit || !$dbConnected || $openSession !== null;
$csrf = Csrf::token();
$backHref = '/app?page=registerkasse-kassen';
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <a class="dg-button dg-button--back" href="<?= View::escape($backHref) ?>">← Zurück zu den Kassen</a>

  <header class="dg-page-header">
    <h1 class="dg-page-title">Kasse öffnen</h1>
    <p class="dg-lead">Kassensitzung mit Bediener und Anfangsbestand (Wechselgeld) starten.</p>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Datenbank nicht verbunden.
      <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Einstellungen → Datenbank</a>
    </div>
  <?php endif; ?>

  <?php if ($openSession !== null) : ?>
    <div class="dg-flash dg-flash--info">
      Diese Kasse hat bereits eine offene Sitzung
      (Geschäftstag <?= View::escape((string) ($openSession['business_date'] ?? '')) ?>,
      Anfang <?= View::escape(number_format((float) ($openSession['opening_balance'] ?? 0), 2, ',', '.')) ?> €).
      <a class="dg-button dg-button--small" href="/app?page=registerkasse-sitzung-schliessen&amp;register_id=<?= (int) $registerId ?>">Schließen</a>
    </div>
  <?php endif; ?>

  <form class="dg-form dg-panel" method="post" action="/app?page=registerkasse-sitzung-oeffnen<?= $registerId > 0 ? '&amp;register_id=' . $registerId : '' ?>">
    <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
    <input type="hidden" name="cash_session_open" value="1">

    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Kasse *</span>
        <select name="register_id" required <?= $disabled || $registers === [] ? ' disabled' : '' ?>>
          <option value="">— bitte wählen —</option>
          <?php foreach ($registers as $reg) : ?>
            <?php
              $rid = (int) ($reg['id'] ?? 0);
              $label = trim((string) ($reg['code'] ?? ''));
              if (trim((string) ($reg['name'] ?? '')) !== '') {
                  $label .= ($label !== '' ? ' — ' : '') . trim((string) $reg['name']);
              }
              $loc = trim((string) ($reg['location_code'] ?? ''));
              if ($loc !== '') {
                  $label .= ' (' . $loc . ')';
              }
            ?>
            <option value="<?= $rid ?>"<?= $registerId === $rid ? ' selected' : '' ?>>
              <?= View::escape($label !== '' ? $label : '#' . $rid) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if ($register !== null) : ?>
          <?php
            $regLoc = trim((string) ($register['location_code'] ?? ''));
            if (trim((string) ($register['location_name'] ?? '')) !== '') {
                $regLoc .= ($regLoc !== '' ? ' — ' : '') . trim((string) $register['location_name']);
            }
          ?>
          <?php if ($regLoc !== '') : ?>
            <small class="dg-field-hint">Filiale: <?= View::escape($regLoc) ?></small>
          <?php endif; ?>
        <?php endif; ?>
      </label>

      <label class="dg-field">
        <span>Bediener *</span>
        <select name="opened_by" required <?= $disabled ? ' disabled' : '' ?>>
          <option value="">— bitte wählen —</option>
          <?php foreach ($operators as $op) : ?>
            <?php
              $oid = (int) $op->id;
              $opLabel = trim($op->displayName) !== '' ? $op->displayName : $op->username;
              if (trim($op->username) !== '' && trim($op->displayName) !== '') {
                  $opLabel .= ' (' . $op->username . ')';
              }
            ?>
            <option value="<?= $oid ?>"<?= $openedBy === $oid ? ' selected' : '' ?>>
              <?= View::escape($opLabel) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="dg-field">
        <span>Anfangsbestand (EUR) *</span>
        <input type="text" name="opening_balance" required inputmode="decimal"
               value="<?= View::escape($openingBalance) ?>"
               <?= $disabled ? ' disabled' : '' ?>
               placeholder="0,00">
        <small class="dg-field-hint">Wechselgeld beim Öffnen — z. B. 50,00</small>
      </label>

      <label class="dg-field">
        <span>Geschäftstag *</span>
        <input type="date" name="business_date" required
               value="<?= View::escape($businessDate) ?>"
               <?= $disabled ? ' disabled' : '' ?>>
      </label>

      <label class="dg-field dg-field--full">
        <span>Notiz</span>
        <input type="text" name="notes" maxlength="500"
               value="<?= View::escape($notes) ?>"
               <?= $disabled ? ' disabled' : '' ?>
               placeholder="optional">
      </label>
    </div>

    <div class="dg-form-actions">
      <?php if ($canEdit && $dbConnected && $openSession === null) : ?>
        <button type="submit" class="dg-button dg-button--primary">Kasse öffnen</button>
      <?php endif; ?>
      <a class="dg-button" href="<?= View::escape($backHref) ?>">Abbrechen</a>
    </div>
  </form>
</div>
