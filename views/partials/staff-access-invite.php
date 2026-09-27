<?php
/**
 * Zugänge & Zeiterfassung — Einladungen Link/Manuell.
 *
 * @var int|null $contactId
 * @var bool $showEmployeeFields
 * @var bool $isEdit
 * @var array<string, array{kind: string, channel: string, created_at: string, detail: ?string}> $staffAccessStatus
 * @var bool $staffAccessHasMailbox
 */
if (empty($showEmployeeFields)) {
    return;
}
$cid = (int) ($contactId ?? 0);
$status = is_array($staffAccessStatus ?? null) ? $staffAccessStatus : [];
$hasMailbox = !empty($staffAccessHasMailbox);
$dsgvo = StaffAccessInviteService::DSGVO_HINT;

$statusLabel = static function (string $kind) use ($status): string {
    $row = $status[$kind] ?? null;
    if (!is_array($row) || ($row['created_at'] ?? '') === '') {
        return 'Noch offen';
    }
    $ch = ($row['channel'] ?? '') === 'manual' ? 'manuell' : 'Link';
    $at = (string) $row['created_at'];

    return $ch . ' · ' . $at;
};
?>
<section class="dg-form-section" id="staff-access-invite">
  <h2 class="dg-subsection-title">Zugänge &amp; Zeiterfassung</h2>
  <p class="dg-field-hint">
    Getrennte Einladungen: Mitarbeiter vergeben Passwort/PIN selbst per Link — oder Sie tragen manuell ein (dann DSGVO-Bestätigung).
    Keine Geheimnisse in der E-Mail.
  </p>

  <?php if (!$isEdit) : ?>
    <input type="hidden" name="invite_app_seen" value="1">
    <label class="dg-field dg-field--checkbox">
      <span>
        <input type="checkbox" name="invite_app" value="1" checked>
        Zur Mitarbeiter-App einladen (E-Mail mit Download + Verbinden-Link)
      </span>
    </label>
    <label class="dg-field dg-field--checkbox">
      <span>
        <input type="checkbox" name="invite_crm_link" value="1">
        CRM-Passwort-Einladung senden
      </span>
    </label>
    <label class="dg-field dg-field--checkbox">
      <span>
        <input type="checkbox" name="invite_pin_link" value="1">
        Stempeluhr-PIN-Einladung senden
      </span>
    </label>
    <label class="dg-field dg-field--checkbox">
      <span>
        <input type="checkbox" name="invite_mailbox_link" value="1">
        Postfach-Passwort-Einladung senden (nur wenn Postfach angelegt wird/existiert)
      </span>
    </label>
    <p class="dg-field-hint">Einladungen gehen nach dem Speichern an die Kontakt-E-Mail.</p>
  <?php else : ?>
    <?php if ($cid < 1) : ?>
      <p class="dg-muted">Bitte zuerst speichern.</p>
    <?php else : ?>
      <div class="dg-form-grid" style="gap:1.25rem">
        <div class="dg-panel" style="padding:1rem">
          <h3 class="dg-subsection-title" style="margin-top:0">CRM-Zugang</h3>
          <p class="dg-muted" style="margin:0 0 .75rem"><?= View::escape($statusLabel(StaffAccessEventRepository::KIND_CRM)) ?></p>
          <fieldset class="dg-field">
            <legend class="dg-field-label">Modus</legend>
            <label><input type="radio" name="staff_crm_mode" value="link" checked> Link senden</label>
            <label style="margin-left:1rem"><input type="radio" name="staff_crm_mode" value="manual"> Manuell eingeben</label>
          </fieldset>
          <div data-staff-mode-panel="crm-link">
            <form method="post" action="/app?page=kontakte" class="dg-form" style="margin-top:.5rem">
              <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
              <input type="hidden" name="id" value="<?= $cid ?>">
              <input type="hidden" name="staff_access_action" value="crm_invite">
              <button type="submit" class="dg-button dg-button--primary dg-button--small">Einladung senden / erneut</button>
            </form>
          </div>
          <div data-staff-mode-panel="crm-manual" hidden>
            <form method="post" action="/app?page=kontakte" class="dg-form" style="margin-top:.5rem">
              <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
              <input type="hidden" name="id" value="<?= $cid ?>">
              <input type="hidden" name="staff_access_action" value="crm_manual">
              <label class="dg-field"><span>Neues Passwort</span><input type="password" name="password" autocomplete="new-password" required></label>
              <label class="dg-field"><span>Wiederholen</span><input type="password" name="password_confirm" autocomplete="new-password" required></label>
              <label class="dg-field dg-field--checkbox">
                <span><input type="checkbox" name="dsgvo_confirm" value="1" required> <?= View::escape($dsgvo) ?></span>
              </label>
              <button type="submit" class="dg-button dg-button--small">Passwort speichern</button>
            </form>
          </div>
        </div>

        <div class="dg-panel" style="padding:1rem">
          <h3 class="dg-subsection-title" style="margin-top:0">Stempeluhr-PIN</h3>
          <p class="dg-muted" style="margin:0 0 .75rem"><?= View::escape($statusLabel(StaffAccessEventRepository::KIND_PIN)) ?></p>
          <fieldset class="dg-field">
            <legend class="dg-field-label">Modus</legend>
            <label><input type="radio" name="staff_pin_mode" value="link" checked> Link senden</label>
            <label style="margin-left:1rem"><input type="radio" name="staff_pin_mode" value="manual"> Manuell eingeben</label>
          </fieldset>
          <div data-staff-mode-panel="pin-link">
            <form method="post" action="/app?page=kontakte" class="dg-form" style="margin-top:.5rem">
              <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
              <input type="hidden" name="id" value="<?= $cid ?>">
              <input type="hidden" name="staff_access_action" value="pin_invite">
              <button type="submit" class="dg-button dg-button--primary dg-button--small">Einladung senden / erneut</button>
            </form>
          </div>
          <div data-staff-mode-panel="pin-manual" hidden>
            <form method="post" action="/app?page=kontakte" class="dg-form" style="margin-top:.5rem">
              <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
              <input type="hidden" name="id" value="<?= $cid ?>">
              <input type="hidden" name="staff_access_action" value="pin_manual">
              <label class="dg-field"><span>PIN (<?= (int) TimeKioskService::MIN_PIN_LEN ?>–<?= (int) TimeKioskService::MAX_PIN_LEN ?> Ziffern)</span>
                <input type="password" name="pin" inputmode="numeric" pattern="[0-9]{<?= (int) TimeKioskService::MIN_PIN_LEN ?>,<?= (int) TimeKioskService::MAX_PIN_LEN ?>}" required>
              </label>
              <label class="dg-field dg-field--checkbox">
                <span><input type="checkbox" name="dsgvo_confirm" value="1" required> <?= View::escape($dsgvo) ?></span>
              </label>
              <button type="submit" class="dg-button dg-button--small">PIN speichern</button>
            </form>
          </div>
        </div>

        <div class="dg-panel" style="padding:1rem">
          <h3 class="dg-subsection-title" style="margin-top:0">Geschäfts-Postfach</h3>
          <p class="dg-muted" style="margin:0 0 .75rem"><?= View::escape($statusLabel(StaffAccessEventRepository::KIND_MAILBOX)) ?></p>
          <?php if (!$hasMailbox) : ?>
            <p class="dg-flash dg-flash--warning">Kein privates Postfach verknüpft — zuerst anlegen (Einstellungen → Postfächer oder Auto-Anlage).</p>
          <?php else : ?>
            <fieldset class="dg-field">
              <legend class="dg-field-label">Modus</legend>
              <label><input type="radio" name="staff_mailbox_mode" value="link" checked> Link senden</label>
              <label style="margin-left:1rem"><input type="radio" name="staff_mailbox_mode" value="manual"> Manuell eingeben</label>
            </fieldset>
            <div data-staff-mode-panel="mailbox-link">
              <form method="post" action="/app?page=kontakte" class="dg-form" style="margin-top:.5rem">
                <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                <input type="hidden" name="id" value="<?= $cid ?>">
                <input type="hidden" name="staff_access_action" value="mailbox_invite">
                <button type="submit" class="dg-button dg-button--primary dg-button--small">Einladung senden / erneut</button>
              </form>
            </div>
            <div data-staff-mode-panel="mailbox-manual" hidden>
              <form method="post" action="/app?page=kontakte" class="dg-form" style="margin-top:.5rem">
                <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                <input type="hidden" name="id" value="<?= $cid ?>">
                <input type="hidden" name="staff_access_action" value="mailbox_manual">
                <label class="dg-field"><span>Neues Postfach-Passwort</span><input type="password" name="mailbox_password" autocomplete="new-password" required minlength="8"></label>
                <label class="dg-field"><span>Wiederholen</span><input type="password" name="mailbox_password_confirm" autocomplete="new-password" required minlength="8"></label>
                <label class="dg-field dg-field--checkbox">
                  <span><input type="checkbox" name="dsgvo_confirm" value="1" required> <?= View::escape($dsgvo) ?></span>
                </label>
                <button type="submit" class="dg-button dg-button--small">Passwort speichern</button>
              </form>
            </div>
          <?php endif; ?>
        </div>

        <div class="dg-panel" style="padding:1rem">
          <h3 class="dg-subsection-title" style="margin-top:0">Mitarbeiter-App</h3>
          <p class="dg-muted" style="margin:0 0 .75rem"><?= View::escape($statusLabel(StaffAccessEventRepository::KIND_APP)) ?></p>
          <form method="post" action="/app?page=kontakte" class="dg-form">
            <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
            <input type="hidden" name="id" value="<?= $cid ?>">
            <input type="hidden" name="staff_access_action" value="app_invite">
            <button type="submit" class="dg-button dg-button--primary dg-button--small">App-Einladung senden / erneut</button>
          </form>
        </div>
      </div>
      <script>
        (function () {
          function bind(name, linkKey, manualKey) {
            var radios = document.querySelectorAll('input[name="' + name + '"]');
            var link = document.querySelector('[data-staff-mode-panel="' + linkKey + '"]');
            var manual = document.querySelector('[data-staff-mode-panel="' + manualKey + '"]');
            if (!radios.length || !link || !manual) return;
            function sync() {
              var v = 'link';
              radios.forEach(function (r) { if (r.checked) v = r.value; });
              link.hidden = v !== 'link';
              manual.hidden = v !== 'manual';
            }
            radios.forEach(function (r) { r.addEventListener('change', sync); });
            sync();
          }
          bind('staff_crm_mode', 'crm-link', 'crm-manual');
          bind('staff_pin_mode', 'pin-link', 'pin-manual');
          bind('staff_mailbox_mode', 'mailbox-link', 'mailbox-manual');
        })();
      </script>
    <?php endif; ?>
  <?php endif; ?>
</section>
