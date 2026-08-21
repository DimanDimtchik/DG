<?php
/** @var array<string, mixed>|null $customer */
/** @var string|null $formError */
$c = $customer ?? [];
$isEdit = !empty($c['id']);
$action = $isEdit ? 'edit' : 'new';
$hasShopPw = !empty($c['shop_password_hash']);
$licenseConfigured = KdvLicenseClient::isConfigured();
?>
<div class="dg-wrap">
  <header class="dg-page-header">
    <h1 class="dg-page-title"><?= $isEdit ? 'SaaS-Kunde bearbeiten' : 'Neuer SaaS-Kunde' ?></h1>
    <p class="dg-lead"><?= $isEdit ? View::escape($c['company_name'] ?? '') : 'Akte für eine CRM-Instanz (Hosting-Kunde von Ganz Soft)' ?></p>
  </header>

  <?php if (!empty($formError)): ?>
    <div class="dg-alert dg-alert--danger"><?= View::escape($formError) ?></div>
  <?php endif; ?>

  <form method="post" action="/app?page=kdv-kunden&amp;action=<?= $action ?><?= $isEdit ? '&amp;id=' . (int) $c['id'] : '' ?>">
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

    <div class="dg-panel">
      <h2>Firmendaten</h2>
      <div class="dg-form-grid">
        <label class="dg-label">Firmenname *
          <input class="dg-input" type="text" name="company_name" value="<?= View::escape($c['company_name'] ?? '') ?>" required>
        </label>
        <label class="dg-label">Domain *
          <input class="dg-input" type="text" name="domain" placeholder="kunde.de" value="<?= View::escape($c['domain'] ?? '') ?>" required>
        </label>
        <label class="dg-label">Datenbank-Name
          <input class="dg-input" type="text" name="db_name" value="<?= View::escape($c['db_name'] ?? '') ?>">
        </label>
        <label class="dg-label">KAS-Login
          <input class="dg-input" type="text" name="kas_login" placeholder="w0123456" value="<?= View::escape($c['kas_login'] ?? '') ?>">
        </label>
      </div>
    </div>

    <div class="dg-panel">
      <h2>Ansprechpartner (Shop-Login = diese E-Mail)</h2>
      <div class="dg-form-grid">
        <label class="dg-label">Name
          <input class="dg-input" type="text" name="contact_name" value="<?= View::escape($c['contact_name'] ?? '') ?>">
        </label>
        <label class="dg-label">E-Mail
          <input class="dg-input" type="email" name="contact_email" value="<?= View::escape($c['contact_email'] ?? '') ?>">
        </label>
        <label class="dg-label">Telefon
          <input class="dg-input" type="tel" name="contact_phone" value="<?= View::escape($c['contact_phone'] ?? '') ?>">
        </label>
      </div>
    </div>

    <div class="dg-panel">
      <h2>Vertrag</h2>
      <div class="dg-form-grid">
        <label class="dg-label">Status
          <select class="dg-input" name="status">
            <?php foreach (KdvCustomerRepository::STATUSES as $key => $label): ?>
              <option value="<?= $key ?>" <?= ($c['status'] ?? 'neu') === $key ? 'selected' : '' ?>><?= View::escape($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dg-label">Tarif
          <select class="dg-input" name="tariff">
            <?php foreach (KdvCustomerRepository::TARIFFS as $key => $label): ?>
              <option value="<?= $key ?>" <?= ($c['tariff'] ?? 'basic') === $key ? 'selected' : '' ?>><?= View::escape($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dg-label">Monatspreis (€)
          <input class="dg-input" type="number" step="0.01" min="0" name="monthly_price" value="<?= number_format((float) ($c['monthly_price'] ?? 0), 2, '.', '') ?>">
        </label>
        <label class="dg-label">Abrechnungszyklus
          <select class="dg-input" name="billing_cycle">
            <option value="monatlich" <?= ($c['billing_cycle'] ?? 'monatlich') === 'monatlich' ? 'selected' : '' ?>>Monatlich</option>
            <option value="jaehrlich" <?= ($c['billing_cycle'] ?? '') === 'jaehrlich' ? 'selected' : '' ?>>Jährlich</option>
          </select>
        </label>
        <label class="dg-label">Vertragsbeginn
          <input class="dg-input" type="date" name="contract_start" value="<?= View::escape($c['contract_start'] ?? '') ?>">
        </label>
        <label class="dg-label">Vertragsende
          <input class="dg-input" type="date" name="contract_end" value="<?= View::escape($c['contract_end'] ?? '') ?>">
        </label>
      </div>
    </div>

    <div class="dg-panel">
      <h2>Shop-Konto-Passwort</h2>
      <p class="dg-field-hint"><?= View::escape(PasswordPolicy::hint()) ?> <?= $hasShopPw ? 'Es ist bereits ein Passwort gesetzt – leer lassen zum Behalten.' : 'Noch kein Shop-Login gesetzt.' ?></p>
      <div class="dg-form-grid">
        <label class="dg-label">Neues Passwort
          <input class="dg-input" type="password" name="shop_password" autocomplete="new-password">
        </label>
        <label class="dg-label">Passwort wiederholen
          <input class="dg-input" type="password" name="shop_password_confirm" autocomplete="new-password">
        </label>
      </div>
    </div>

    <div class="dg-panel">
      <h2>Notizen</h2>
      <textarea class="dg-input" name="notes" rows="4"><?= View::escape($c['notes'] ?? '') ?></textarea>
    </div>

    <?php if ($isEdit && (!empty($c['mailbox_email']) || !empty($c['mailbox_password']))): ?>
    <div class="dg-panel">
      <h2>Info-Postfach (KAS)</h2>
      <p class="dg-field-hint">Wird bei der automatischen Bereitstellung erzeugt und der Kundin per Install-Mail mitgeteilt.</p>
      <div class="dg-form-grid">
        <label class="dg-label">E-Mail
          <input class="dg-input" type="text" readonly value="<?= View::escape((string) ($c['mailbox_email'] ?? '')) ?>">
        </label>
        <label class="dg-label">Passwort
          <input class="dg-input" type="text" readonly value="<?= View::escape((string) ($c['mailbox_password'] ?? '')) ?>" style="font-family:monospace;">
        </label>
      </div>
      <?php if (!empty($c['mailbox_created_at'])): ?>
        <p class="dg-field-hint">Angelegt: <?= View::escape((string) $c['mailbox_created_at']) ?></p>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="dg-form-actions">
      <button class="dg-button dg-button--primary" type="submit">Speichern</button>
      <a class="dg-button" href="/app?page=kdv-kunden">Abbrechen</a>
      <?php if ($isEdit): ?>
        <?php if (in_array($c['status'] ?? '', ['neu', 'dns_pending'], true)): ?>
          <a class="dg-button" href="/app?page=kdv-provision&amp;id=<?= (int) $c['id'] ?>">CRM bereitstellen</a>
        <?php endif; ?>
        <a class="dg-button dg-button--danger" href="/app?page=kdv-kunden&amp;action=delete&amp;id=<?= (int) $c['id'] ?>&amp;_csrf=<?= View::escape(Csrf::token()) ?>"
           onclick="return confirm('SaaS-Kunde wirklich löschen?')">Löschen</a>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($isEdit): ?>
  <div class="dg-panel" style="margin-top:24px;">
    <h2>Lizenz</h2>
    <?php if (!$licenseConfigured): ?>
      <p class="dg-alert dg-alert--warning">Lizenzserver-Token fehlt. Bitte unter KDV-Übersicht hinterlegen. Zuweisen lokal ist möglich; Sperre am Server braucht den Token.</p>
    <?php endif; ?>
    <p>Aktueller Key: <code><?= View::escape((string) ($c['license_key'] ?? '')) ?: '– noch keiner –' ?></code></p>
    <?php if (($c['status'] ?? '') === 'gesperrt'): ?>
      <p style="color:#dc3545;font-weight:600;">
        Gesperrt: <?= View::escape(KdvBlockReasons::label((string) ($c['block_reason'] ?? ''))) ?>
        <?php if (!empty($c['block_note'])): ?> — <?= View::escape((string) $c['block_note']) ?><?php endif; ?>
      </p>
      <p class="dg-field-hint"><?= View::escape(KdvBlockReasons::customerMessage((string) ($c['block_reason'] ?? ''), (string) ($c['block_note'] ?? ''))) ?></p>
    <?php endif; ?>

    <form method="post" action="/app?page=kdv-kunden&amp;action=edit&amp;id=<?= (int) $c['id'] ?>" style="margin-top:12px;">
      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
      <input type="hidden" name="kdv_license_action" value="issue">
      <label class="dg-label">Gültig bis (optional)
        <input class="dg-input" type="date" name="valid_to" style="max-width:220px;">
      </label>
      <button class="dg-button dg-button--primary" type="submit" onclick="return confirm('Neuen Lizenzschlüssel am Server anlegen und zuweisen?')">Neuen Key erzeugen</button>
    </form>

    <form method="post" action="/app?page=kdv-kunden&amp;action=edit&amp;id=<?= (int) $c['id'] ?>" style="margin-top:16px;">
      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
      <input type="hidden" name="kdv_license_action" value="assign">
      <label class="dg-label">Bestehenden Key zuweisen
        <input class="dg-input" type="text" name="license_key" placeholder="GS-XXXX-XXXX-XXXX-XXXX" value="<?= View::escape((string) ($c['license_key'] ?? '')) ?>" style="max-width:320px;font-family:monospace;">
      </label>
      <button class="dg-button" type="submit">Key zuweisen</button>
    </form>

    <?php if (($c['status'] ?? '') !== 'gesperrt'): ?>
    <form method="post" action="/app?page=kdv-kunden&amp;action=edit&amp;id=<?= (int) $c['id'] ?>" style="margin-top:16px;border-top:1px solid #eee;padding-top:16px;">
      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
      <input type="hidden" name="kdv_license_action" value="suspend">
      <h3>Sperren</h3>
      <div class="dg-form-grid">
        <label class="dg-label">Sperrgrund *
          <select class="dg-input" name="block_reason" required>
            <?php foreach (KdvBlockReasons::all() as $code => $meta): ?>
              <option value="<?= View::escape($code) ?>"><?= View::escape($meta['label']) ?><?= $meta['auto_reject'] ? ' (kein Entsperr-Mail)' : '' ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dg-label">Interne Notiz (optional)
          <input class="dg-input" type="text" name="block_note">
        </label>
      </div>
      <label class="dg-label" style="display:block;margin:8px 0;">
        <input type="checkbox" name="skip_license_suspend" value="1"> Nur Akte sperren, Lizenz am Server nicht ändern
      </label>
      <button class="dg-button dg-button--danger" type="submit" onclick="return confirm('SaaS-Kunde wirklich sperren?')">Sperren</button>
    </form>
    <?php else: ?>
    <form method="post" action="/app?page=kdv-kunden&amp;action=edit&amp;id=<?= (int) $c['id'] ?>" style="margin-top:16px;border-top:1px solid #eee;padding-top:16px;">
      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
      <input type="hidden" name="kdv_license_action" value="unsuspend">
      <h3>Entsperren</h3>
      <label class="dg-label" style="display:block;margin:8px 0;">
        <input type="checkbox" name="skip_license_activate" value="1"> Nur Akte entsperren, Lizenz am Server nicht ändern
      </label>
      <button class="dg-button dg-button--primary" type="submit">Entsperren</button>
    </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
