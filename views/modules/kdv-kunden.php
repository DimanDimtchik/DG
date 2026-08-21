<?php
/** @var list<array<string, mixed>> $customers */
$customers = $customers ?? [];
?>
<div class="dg-wrap">
  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">SaaS-Kunden</h1>
      <p class="dg-lead"><?= count($customers) ?> CRM-Instanzen (Ihre Hosting-Kunden – nicht CRM-Kontakte)</p>
    </div>
    <div class="dg-toolbar">
      <a class="dg-button dg-button--primary" href="/app?page=kdv-kunden&amp;action=new">Neuer SaaS-Kunde</a>
    </div>
  </header>

  <?php if (!empty($customers)): ?>
  <div class="dg-panel">
    <div class="dg-table-wrap">
      <table class="dg-table">
        <thead>
          <tr>
            <th>Firma</th>
            <th>Domain</th>
            <th>Ansprechpartner</th>
            <th>Tarif</th>
            <th>Lizenz</th>
            <th>Preis/Monat</th>
            <th>Status</th>
            <th>Version</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($customers as $c): ?>
          <tr>
            <td><a href="/app?page=kdv-kunden&amp;action=edit&amp;id=<?= (int) $c['id'] ?>"><?= View::escape($c['company_name']) ?></a></td>
            <td><a href="https://<?= View::escape($c['domain']) ?>" target="_blank" rel="noopener"><?= View::escape($c['domain']) ?></a></td>
            <td><?= View::escape($c['contact_name'] ?? '–') ?></td>
            <td><?= View::escape(KdvCustomerRepository::TARIFFS[$c['tariff']] ?? $c['tariff']) ?></td>
            <td><code style="font-size:0.85em;"><?= View::escape(KdvCustomerRepository::maskLicense((string) ($c['license_key'] ?? ''))) ?: '–' ?></code></td>
            <td><?= number_format((float) $c['monthly_price'], 2, ',', '.') ?> €</td>
            <td>
              <?php
              $statusClass = match ($c['status'] ?? '') {
                  'aktiv' => 'color:#28a745;',
                  'gesperrt', 'gekuendigt' => 'color:#dc3545;',
                  default => 'color:#888;',
              };
              ?>
              <span style="<?= $statusClass ?> font-weight:600;"><?= View::escape(KdvCustomerRepository::STATUSES[$c['status']] ?? $c['status']) ?></span>
              <?php if (($c['status'] ?? '') === 'gesperrt' && !empty($c['block_reason'])): ?>
                <div style="font-size:0.8em;color:#888;"><?= View::escape(KdvBlockReasons::label((string) $c['block_reason'])) ?></div>
              <?php endif; ?>
              <?php if (in_array($c['status'], ['neu', 'dns_pending'], true)): ?>
                <a href="/app?page=kdv-provision&amp;id=<?= (int) $c['id'] ?>" style="font-size:0.85em; margin-left:6px;">bereitstellen →</a>
              <?php endif; ?>
            </td>
            <td><?= View::escape($c['crm_version'] ?? '–') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php else: ?>
  <div class="dg-panel" style="text-align:center; padding:40px;">
    <p>Noch keine SaaS-Kunden vorhanden.</p>
    <a class="dg-button dg-button--primary" href="/app?page=kdv-kunden&amp;action=new">Ersten SaaS-Kunden anlegen</a>
  </div>
  <?php endif; ?>
</div>
