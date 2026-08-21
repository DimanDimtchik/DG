<?php
/** @var array<string, mixed> $customer */
/** @var array{success: bool, steps: list<array{step: string, ok: bool, detail: string}>, install_url?: string}|null $result */
$c = $customer ?? [];
$result = $result ?? null;
?>
<div class="dg-wrap">
  <header class="dg-page-header">
    <h1 class="dg-page-title">CRM bereitstellen</h1>
    <p class="dg-lead"><?= View::escape($c['company_name'] ?? '') ?> – <?= View::escape($c['domain'] ?? '') ?></p>
  </header>

  <?php if ($result !== null): ?>
  <div class="dg-panel">
    <h2><?= $result['success'] ? 'Bereitstellung erfolgreich' : 'Bereitstellung mit Fehlern' ?></h2>
    <table class="dg-table" style="margin-top:12px;">
      <thead><tr><th>Schritt</th><th>Status</th><th>Details</th></tr></thead>
      <tbody>
        <?php foreach ($result['steps'] as $s): ?>
        <tr>
          <td><?= View::escape($s['step']) ?></td>
          <td style="color:<?= $s['ok'] ? '#28a745' : '#dc3545' ?>; font-weight:600;"><?= $s['ok'] ? '✓' : '✗' ?></td>
          <td><?= View::escape($s['detail']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (!empty($result['install_url'])): ?>
    <div style="margin-top:16px; padding:16px; background:#f0f7ff; border:1px solid #cde; border-radius:6px;">
      <strong>Install-URL:</strong>
      <a href="<?= View::escape($result['install_url']) ?>" target="_blank" rel="noopener"><?= View::escape($result['install_url']) ?></a>
    </div>
    <?php endif; ?>
    <?php if (!empty($result['mailbox_email']) && !empty($result['mailbox_password'])): ?>
    <div style="margin-top:16px; padding:16px; background:#fff8e1; border:1px solid #f0d78c; border-radius:6px;">
      <strong>Info-Postfach (bitte sicher notieren / an Kundin weitergeben):</strong>
      <p style="margin:8px 0 0;">E-Mail: <code><?= View::escape((string) $result['mailbox_email']) ?></code></p>
      <p style="margin:4px 0 0;">Passwort: <code><?= View::escape((string) $result['mailbox_password']) ?></code></p>
      <p style="margin:8px 0 0;font-size:0.9em;color:#666;">Wurde auch in der Installations-E-Mail mitgeschickt und in der SaaS-Akte gespeichert.</p>
    </div>
    <?php endif; ?>
    <div style="margin-top:16px;">
      <a class="dg-button" href="/app?page=kdv-kunden">← Zurück zur Kundenliste</a>
    </div>
  </div>

  <?php else: ?>
  <div class="dg-panel">
    <h2>Automatische Bereitstellung</h2>
    <p>Folgende Schritte werden automatisch durchgeführt:</p>
    <ol style="margin:12px 0 12px 20px; line-height:1.8;">
      <li>Domain <strong><?= View::escape($c['domain'] ?? '') ?></strong> bei All-Inkl anlegen</li>
      <li>Datenbank erstellen</li>
      <li>E-Mail-Postfach <strong>info@<?= View::escape($c['domain'] ?? '') ?></strong> erstellen und Passwort an Kundin senden</li>
      <li>CRM-Dateien auf den Server übertragen</li>
      <li>Konfiguration schreiben</li>
      <li>Installations-Link an <strong><?= View::escape($c['contact_email'] ?? '') ?></strong> senden</li>
    </ol>

    <form method="post" action="/app?page=kdv-provision&amp;id=<?= (int) $c['id'] ?>">
      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

      <div class="dg-form-grid" style="margin-bottom:16px;">
        <label class="dg-label">KAS-Login *
          <input class="dg-input" type="text" name="kas_login" value="<?= View::escape($c['kas_login'] ?? '') ?>" required>
        </label>
        <label class="dg-label">KAS-Passwort *
          <input class="dg-input" type="password" name="kas_pass" required>
        </label>
      </div>

      <div class="dg-form-actions">
        <button class="dg-button dg-button--primary" type="submit"
                onclick="return confirm('CRM für <?= View::escape($c['domain'] ?? '') ?> bereitstellen? Dieser Vorgang kann einige Minuten dauern.')">
          Bereitstellung starten
        </button>
        <a class="dg-button" href="/app?page=kdv-kunden&amp;action=edit&amp;id=<?= (int) $c['id'] ?>">Abbrechen</a>
      </div>
    </form>
  </div>
  <?php endif; ?>
</div>
