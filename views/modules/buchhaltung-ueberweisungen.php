<?php
/**
 * @var list<array<string, mixed>> $transfersPrepared
 * @var list<array<string, mixed>> $transfersExecuted
 * @var int $openTransferId
 * @var bool $dbConnected
 * @var bool $canEdit
 * @var array{type: string, message: string}|null $flash
 */
$transfersPrepared = $transfersPrepared ?? [];
$transfersExecuted = $transfersExecuted ?? [];
$openId = (int) ($openTransferId ?? 0);
$csrf = Csrf::token();

$formatDate = static function (?string $value): string {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    $ts = strtotime($value);

    return $ts !== false ? date('d.m.Y', $ts) : $value;
};

$renderItem = static function (array $t) use ($openId, $csrf, $canEdit, $formatDate): void {
    $id = (int) ($t['id'] ?? 0);
    $status = (string) ($t['status'] ?? 'prepared');
    $isExecuted = $status === 'executed';
    $isPayable = !empty($t['is_payable']);
    $open = $openId === $id;
    ?>
    <details class="dg-transfer" id="transfer-<?= $id ?>"<?= $open ? ' open' : '' ?>>
      <summary class="dg-transfer__summary">
        <span class="dg-transfer__recipient"><?= View::escape((string) ($t['recipient_name'] ?? '—')) ?></span>
        <span class="dg-transfer__amount"><?= View::escape((string) ($t['amount_display'] ?? '')) ?></span>
        <span class="dg-transfer__meta">
          <?php if (($t['invoice_number'] ?? '') !== '') : ?>
            Rg. <?= View::escape((string) $t['invoice_number']) ?>
          <?php endif; ?>
        </span>
        <span class="dg-badge <?= $isExecuted ? 'dg-badge--muted' : 'dg-badge--pending' ?>">
          <?= $isExecuted ? 'Ausgeführt' : 'Vorbereitet' ?>
        </span>
      </summary>

      <div class="dg-transfer__body">
        <div class="dg-transfer__details">
          <dl class="dg-transfer__dl">
            <dt>Empfänger</dt><dd><?= View::escape((string) ($t['recipient_name'] ?? '')) ?: '—' ?></dd>
            <dt>IBAN</dt><dd><?= View::escape((string) ($t['iban_display'] ?? '')) ?: '<span class="dg-muted">fehlt</span>' ?></dd>
            <dt>BIC</dt><dd><?= View::escape((string) ($t['recipient_bic'] ?? '')) ?: '<span class="dg-muted">—</span>' ?></dd>
            <dt>Betrag</dt><dd><strong><?= View::escape((string) ($t['amount_display'] ?? '')) ?></strong></dd>
            <dt>Verwendungszweck</dt><dd><?= View::escape((string) ($t['purpose'] ?? '')) ?: '—' ?></dd>
            <?php if (($t['executed_at'] ?? null)) : ?>
              <dt>Ausgeführt am</dt><dd><?= View::escape($formatDate((string) $t['executed_at'])) ?></dd>
            <?php endif; ?>
          </dl>

          <div class="dg-transfer__actions">
            <?php if ($canEdit) : ?>
              <?php if (!$isExecuted) : ?>
                <form method="post" action="/app?page=buchhaltung-ueberweisungen">
                  <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
                  <input type="hidden" name="transfer_id" value="<?= $id ?>">
                  <button type="submit" name="transfer_mark_executed" value="1" class="dg-button dg-button--primary dg-button--small">Als ausgeführt markieren</button>
                </form>
              <?php else : ?>
                <form method="post" action="/app?page=buchhaltung-ueberweisungen">
                  <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
                  <input type="hidden" name="transfer_id" value="<?= $id ?>">
                  <button type="submit" name="transfer_mark_prepared" value="1" class="dg-button dg-button--small">Zurück auf „vorbereitet“</button>
                </form>
              <?php endif; ?>
              <?php if (($t['voucher_id'] ?? 0)) : ?>
                <a class="dg-button dg-button--small" href="/app?page=buchhaltung-beleg-form&action=edit&id=<?= (int) $t['voucher_id'] ?>">Beleg öffnen</a>
              <?php endif; ?>
              <form method="post" action="/app?page=buchhaltung-ueberweisungen" onsubmit="return confirm('Diese Überweisung wirklich löschen?');">
                <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
                <input type="hidden" name="transfer_id" value="<?= $id ?>">
                <button type="submit" name="transfer_delete" value="1" class="dg-button dg-button--danger dg-button--small">Löschen</button>
              </form>
            <?php endif; ?>
          </div>
        </div>

        <div class="dg-transfer__qrbox">
          <?php if ($isPayable) : ?>
            <div class="dg-transfer-qr"
                 data-qr-payload="<?= View::escape((string) ($t['qr_payload'] ?? '')) ?>"
                 data-recipient="<?= View::escape((string) ($t['recipient_name'] ?? '')) ?>"
                 data-iban="<?= View::escape((string) ($t['iban_display'] ?? '')) ?>"
                 data-bic="<?= View::escape((string) ($t['recipient_bic'] ?? '')) ?>"
                 data-amount="<?= View::escape((string) ($t['amount_display'] ?? '')) ?>"
                 data-purpose="<?= View::escape((string) ($t['purpose'] ?? '')) ?>"
                 aria-label="GiroCode / QR-Code"></div>
            <p class="dg-muted dg-transfer__qrhint">GiroCode — in der Banking-App scannen.</p>
            <div class="dg-transfer__qractions">
              <a class="dg-button dg-button--small" data-qr-download download="ueberweisung-<?= $id ?>.png">QR als PNG</a>
              <button type="button" class="dg-button dg-button--small" data-transfer-print="<?= $id ?>">Fotovorlage drucken</button>
            </div>
          <?php else : ?>
            <div class="dg-flash dg-flash--warning dg-transfer__qrwarn">
              Keine gültige IBAN hinterlegt. Bitte beim Lieferanten-Kontakt eine Bankverbindung erfassen und die Überweisung erneut vorbereiten.
            </div>
          <?php endif; ?>
        </div>
      </div>
    </details>
    <?php
};
?>
<div class="dg-wrap dg-buchhaltung-ueberweisungen">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header">
    <div>
      <h1 class="dg-page-title">Überweisungen</h1>
      <p class="dg-lead">Vorbereitete Überweisungen mit QR-Code (GiroCode) und Fotovorlage. Überweisungen entstehen aus offenen Lieferantenbelegen über „Überweisung vorbereiten“.</p>
    </div>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Datenbank nicht verbunden. Überweisungen können erst nach Konfiguration unter
      <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Einstellungen → Datenbank</a> genutzt werden.
    </div>
  <?php endif; ?>

  <section class="dg-panel dg-transfer-section">
    <h2 class="dg-subsection-title">Vorbereitet <span class="dg-count">(<?= count($transfersPrepared) ?>)</span></h2>
    <?php if ($transfersPrepared === []) : ?>
      <p class="dg-muted">Keine vorbereiteten Überweisungen. Öffnen Sie einen offenen Lieferantenbeleg und klicken Sie auf „Überweisung vorbereiten“.</p>
    <?php else : ?>
      <div class="dg-transfer-list">
        <?php foreach ($transfersPrepared as $t) : $renderItem($t); endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="dg-panel dg-transfer-section">
    <h2 class="dg-subsection-title">Ausgeführt <span class="dg-count">(<?= count($transfersExecuted) ?>)</span></h2>
    <?php if ($transfersExecuted === []) : ?>
      <p class="dg-muted">Noch keine ausgeführten Überweisungen.</p>
    <?php else : ?>
      <div class="dg-transfer-list">
        <?php foreach ($transfersExecuted as $t) : $renderItem($t); endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
