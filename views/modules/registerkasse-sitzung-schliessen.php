<?php
/**
 * Registerkasse — Sitzung schließen (K11c); Zählung später (K12).
 *
 * @var array<string, mixed>|null $cashSessionRegister
 * @var array<string, mixed>|null $cashSessionOpen
 * @var int $cashSessionRegisterId
 * @var int $cashSessionUnfinishedCount
 * @var string $cashSessionNotes
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$register = $cashSessionRegister ?? null;
$openSession = $cashSessionOpen ?? null;
$registerId = (int) ($cashSessionRegisterId ?? 0);
$unfinished = (int) ($cashSessionUnfinishedCount ?? 0);
$notes = (string) ($cashSessionNotes ?? '');
$canClose = $canEdit && $dbConnected && $openSession !== null && $unfinished === 0;
$csrf = Csrf::token();
$backHref = '/app?page=registerkasse-kassen';
$regLabel = '';
if ($register !== null) {
    $regLabel = trim((string) ($register['code'] ?? ''));
    if (trim((string) ($register['name'] ?? '')) !== '') {
        $regLabel .= ($regLabel !== '' ? ' — ' : '') . trim((string) $register['name']);
    }
}
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <a class="dg-button dg-button--back" href="<?= View::escape($backHref) ?>">← Zurück zu den Kassen</a>

  <header class="dg-page-header">
    <h1 class="dg-page-title">Kasse schließen</h1>
    <p class="dg-lead">Kassensitzung beenden. Zählung und Tagesabschluss folgen später.</p>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Datenbank nicht verbunden.
      <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Einstellungen → Datenbank</a>
    </div>
  <?php elseif ($register === null) : ?>
    <div class="dg-flash dg-flash--warning">
      Bitte eine Kasse wählen.
      <a class="dg-button dg-button--small" href="<?= View::escape($backHref) ?>">Zu den Kassen</a>
    </div>
  <?php elseif ($openSession === null) : ?>
    <div class="dg-flash dg-flash--info">
      Für <?= View::escape($regLabel !== '' ? $regLabel : 'diese Kasse') ?> ist keine Sitzung offen.
      <a class="dg-button dg-button--small" href="/app?page=registerkasse-sitzung-oeffnen&amp;register_id=<?= $registerId ?>">Öffnen</a>
    </div>
  <?php else : ?>
    <?php if ($unfinished > 0) : ?>
      <div class="dg-flash dg-flash--warning">
        Noch <?= (int) $unfinished ?> offene Bon(s) (Entwurf oder Zahlend). Bitte zuerst fertigstellen oder abbrechen.
        <a class="dg-button dg-button--small" href="/app?page=registerkasse-bons&amp;register_id=<?= $registerId ?>">Zu den Bons</a>
      </div>
    <?php endif; ?>

    <div class="dg-panel">
      <h2 class="dg-subsection-title">Offene Sitzung</h2>
      <dl class="dg-dl">
        <dt>Kasse</dt>
        <dd><?= View::escape($regLabel !== '' ? $regLabel : '#' . $registerId) ?></dd>
        <dt>Geschäftstag</dt>
        <dd><?= View::escape((string) ($openSession['business_date'] ?? '')) ?></dd>
        <dt>Anfangsbestand</dt>
        <dd><?= View::escape(number_format((float) ($openSession['opening_balance'] ?? 0), 2, ',', '.')) ?> €</dd>
        <dt>Geöffnet</dt>
        <dd><?= View::escape((string) ($openSession['opened_at'] ?? '')) ?></dd>
      </dl>
    </div>

    <form class="dg-form dg-panel" method="post" action="/app?page=registerkasse-sitzung-schliessen&amp;register_id=<?= $registerId ?>">
      <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
      <input type="hidden" name="cash_session_close" value="1">
      <input type="hidden" name="register_id" value="<?= $registerId ?>">

      <div class="dg-form-grid">
        <label class="dg-field dg-field--full">
          <span>Notiz zum Schließen</span>
          <input type="text" name="notes" maxlength="500"
                 value="<?= View::escape($notes) ?>"
                 <?= !$canClose ? ' disabled' : '' ?>
                 placeholder="optional">
        </label>
      </div>

      <div class="dg-form-actions">
        <?php if ($canClose) : ?>
          <button type="submit" class="dg-button dg-button--primary">Sitzung schließen</button>
        <?php endif; ?>
        <a class="dg-button" href="<?= View::escape($backHref) ?>">Abbrechen</a>
      </div>
    </form>
  <?php endif; ?>
</div>
