<?php
/** @var array{total: int, active: int, revenue: float} $stats */
/** @var list<array<string, mixed>> $customers */
$stats = $stats ?? ['total' => 0, 'active' => 0, 'revenue' => 0.0];
$customers = $customers ?? [];
$recent = array_slice($customers, 0, 10);
$licUrl = KdvConfig::licenseServerUrl();
$licTokenSaved = KdvConfig::licenseAdminToken();
$supportEmail = KdvConfig::supportEmail();
$shopPublicUrl = KdvConfig::shopPublicUrl();
?>
<div class="dg-wrap">
  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">SaaS-Kunden (KDV)</h1>
      <p class="dg-lead">Ihre CRM-Instanzen auf Domains – nicht die Kontakte im Endkunden-CRM</p>
    </div>
    <div class="dg-toolbar">
      <a class="dg-button dg-button--primary" href="/app?page=kdv-kunden&amp;action=new">Neuer SaaS-Kunde</a>
    </div>
  </header>

  <div class="dg-stats-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-bottom:24px;">
    <div class="dg-panel" style="text-align:center; padding:20px;">
      <div style="font-size:2rem; font-weight:700; color:var(--dg-primary, #6e6258);"><?= $stats['total'] ?></div>
      <div style="font-size:0.9rem; color:#888;">SaaS-Kunden gesamt</div>
    </div>
    <div class="dg-panel" style="text-align:center; padding:20px;">
      <div style="font-size:2rem; font-weight:700; color:#28a745;"><?= $stats['active'] ?></div>
      <div style="font-size:0.9rem; color:#888;">Aktive Instanzen</div>
    </div>
    <div class="dg-panel" style="text-align:center; padding:20px;">
      <div style="font-size:2rem; font-weight:700; color:var(--dg-primary, #6e6258);"><?= number_format($stats['revenue'], 2, ',', '.') ?> €</div>
      <div style="font-size:0.9rem; color:#888;">Monatl. Umsatz</div>
    </div>
  </div>

  <div class="dg-panel" style="margin-bottom:24px;">
    <h2>API & Automatisierung</h2>
    <p style="margin:8px 0;">Der Lizenz-/SaaS-Shop (shop.ganz-soft.de) nutzt diesen Endpoint nach Kaufabschluss:</p>
    <code style="display:block;padding:8px 12px;background:#f5f5f5;border-radius:4px;margin:8px 0;font-size:0.9em;">POST <?= View::escape(App::publicBaseUrl()) ?>/api/kdv/provision</code>
    <p style="margin:8px 0;font-size:0.9em;color:#666;">Shop-Konto (Status / Entsperr-Bitte): <code>/api/kdv/account/*</code></p>
    <?php if (KdvProvisionApi::hasApiKey()): ?>
      <p style="color:#28a745;font-weight:600;">✓ API-Schlüssel konfiguriert</p>
      <details style="margin-top:8px;">
        <summary style="cursor:pointer;color:#888;">Schlüssel anzeigen</summary>
        <code style="display:block;padding:8px 12px;background:#fff8e1;border-radius:4px;margin-top:4px;word-break:break-all;"><?= View::escape(KdvProvisionApi::getApiKey()) ?></code>
      </details>
    <?php else: ?>
      <p style="color:#c60;">⚠ Kein API-Schlüssel vorhanden.</p>
    <?php endif; ?>
    <form method="post" action="/app?page=kdv-dashboard" style="margin-top:8px;">
      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
      <button type="submit" name="kdv_generate_api_key" value="1" class="dg-button"
              onclick="return confirm('<?= KdvProvisionApi::hasApiKey() ? 'Neuen Schlüssel generieren? Der alte wird ungültig!' : 'API-Schlüssel generieren?' ?>')">
        <?= KdvProvisionApi::hasApiKey() ? 'Neu generieren' : 'API-Schlüssel erstellen' ?>
      </button>
    </form>

    <h3 style="margin-top:20px;">KAS-Zugangsdaten</h3>
    <p class="dg-field-hint">Werden für die automatische Bereitstellung (Domain, DB, Mailbox) benötigt.</p>
    <?php
      $kasLogin = KdvConfig::get('kas_login', '');
      $kasPassSaved = KdvConfig::get('kas_pass', '');
    ?>
    <?php if ($kasLogin !== '' && $kasPassSaved !== ''): ?>
      <p style="color:#28a745;font-weight:600;">✓ KAS-Login: <?= View::escape($kasLogin) ?></p>
    <?php else: ?>
      <p style="color:#c60;">⚠ KAS-Zugangsdaten nicht hinterlegt.</p>
    <?php endif; ?>
    <form method="post" action="/app?page=kdv-dashboard" style="margin-top:8px;">
      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
      <div class="dg-form-grid" style="margin-bottom:8px;">
        <label class="dg-label">KAS-Login
          <input class="dg-input" type="text" name="kdv_kas_login" placeholder="w0217246" value="<?= View::escape($kasLogin) ?>">
        </label>
        <label class="dg-label">KAS-Passwort
          <input class="dg-input" type="password" name="kdv_kas_pass" placeholder="<?= $kasPassSaved !== '' ? '••••••••' : '' ?>">
          <?php if ($kasPassSaved !== ''): ?><small class="dg-field-hint">Leer lassen = bestehendes Passwort behalten</small><?php endif; ?>
        </label>
      </div>
      <button type="submit" name="kdv_save_kas" value="1" class="dg-button">KAS-Daten speichern</button>
    </form>

    <h3 style="margin-top:20px;">Lizenzserver</h3>
    <p class="dg-field-hint">Admin-API für Key anlegen / sperren / entsperren. Stripe-Rechnungen folgen später; Sperrgrund „unbezahlte Rechnung“ ist schon vorbereitet.</p>
    <?php if ($licTokenSaved !== ''): ?>
      <p style="color:#28a745;font-weight:600;">✓ Admin-Token hinterlegt · <?= View::escape($licUrl) ?></p>
    <?php else: ?>
      <p style="color:#c60;">⚠ Admin-Token fehlt – Lizenz-Aktionen am Server sind deaktiviert.</p>
    <?php endif; ?>
    <form method="post" action="/app?page=kdv-dashboard" style="margin-top:8px;">
      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
      <div class="dg-form-grid" style="margin-bottom:8px;">
        <label class="dg-label">Lizenzserver-URL
          <input class="dg-input" type="url" name="kdv_license_server_url" value="<?= View::escape($licUrl) ?>">
        </label>
        <label class="dg-label">Admin-Token
          <input class="dg-input" type="password" name="kdv_license_admin_token" placeholder="<?= $licTokenSaved !== '' ? '••••••••' : '' ?>" autocomplete="off">
          <?php if ($licTokenSaved !== ''): ?><small class="dg-field-hint">Leer lassen = Token behalten</small><?php endif; ?>
        </label>
        <label class="dg-label">Support-E-Mail (Entsperr-Bitten)
          <input class="dg-input" type="email" name="kdv_support_email" value="<?= View::escape($supportEmail) ?>">
        </label>
        <label class="dg-label">Shop-URL (Passwort-Reset-Links)
          <input class="dg-input" type="url" name="kdv_shop_public_url" value="<?= View::escape($shopPublicUrl) ?>" placeholder="https://shop.ganz-soft.de">
        </label>
      </div>
      <button type="submit" name="kdv_save_license_server" value="1" class="dg-button">Lizenzserver speichern</button>
    </form>
  </div>

  <?php if (!empty($recent)): ?>
  <div class="dg-panel">
    <h2 style="margin-bottom:12px;">Letzte SaaS-Kunden</h2>
    <div class="dg-table-wrap">
      <table class="dg-table">
        <thead>
          <tr>
            <th>Firma</th>
            <th>Domain</th>
            <th>Lizenz</th>
            <th>Status</th>
            <th>Version</th>
            <th>Letzter Heartbeat</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent as $c): ?>
          <tr>
            <td><a href="/app?page=kdv-kunden&amp;action=edit&amp;id=<?= (int) $c['id'] ?>"><?= View::escape($c['company_name']) ?></a></td>
            <td><a href="https://<?= View::escape($c['domain']) ?>" target="_blank" rel="noopener"><?= View::escape($c['domain']) ?></a></td>
            <td><code style="font-size:0.85em;"><?= View::escape(KdvCustomerRepository::maskLicense((string) ($c['license_key'] ?? ''))) ?: '–' ?></code></td>
            <td>
              <?php
              $statusClass = match ($c['status'] ?? '') {
                  'aktiv' => 'color:#28a745;',
                  'gesperrt', 'gekuendigt' => 'color:#dc3545;',
                  'dns_pending', 'installiert' => 'color:#ffc107;',
                  default => 'color:#888;',
              };
              ?>
              <span style="<?= $statusClass ?> font-weight:600;"><?= View::escape(KdvCustomerRepository::STATUSES[$c['status']] ?? $c['status']) ?></span>
              <?php if (($c['status'] ?? '') === 'gesperrt' && !empty($c['block_reason'])): ?>
                <div style="font-size:0.8em;color:#888;"><?= View::escape(KdvBlockReasons::label((string) $c['block_reason'])) ?></div>
              <?php endif; ?>
            </td>
            <td><?= View::escape($c['crm_version'] ?? '–') ?></td>
            <td><?= $c['last_heartbeat'] ? View::escape($c['last_heartbeat']) : '<span style="color:#888;">–</span>' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php else: ?>
  <div class="dg-panel" style="text-align:center; padding:40px;">
    <p>Noch keine SaaS-Kunden vorhanden.</p>
    <a class="dg-button dg-button--primary" href="/app?page=kdv-kunden&amp;action=new" style="margin-top:12px;">Ersten SaaS-Kunden anlegen</a>
  </div>
  <?php endif; ?>
</div>
