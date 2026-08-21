<?php
/** @var list<array<string, mixed>> $kdvSupportSessions */
$sessions = $kdvSupportSessions ?? [];
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>
  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Support-Freigaben</h1>
      <p class="dg-lead">Aktive Kunden-Freigaben — CRM öffnen und bei Bedarf Bildschirm zuschauen.</p>
    </div>
  </header>

  <div class="dg-table-wrap">
    <table class="dg-table">
      <thead>
        <tr>
          <th>Kunde / Domain</th>
          <th>Gültig bis</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($sessions === []) : ?>
          <tr><td colspan="4" class="dg-table__empty">Keine aktiven Support-Freigaben.</td></tr>
        <?php else : ?>
          <?php foreach ($sessions as $row) : ?>
            <?php
              $domain = (string) ($row['domain'] ?? '');
              $token = (string) ($row['token'] ?? '');
              $href = $domain !== '' && $token !== ''
                ? 'https://' . $domain . '/support-zugang?token=' . rawurlencode($token)
                : '';
              $exp = (string) ($row['expires_at'] ?? '');
            ?>
            <tr>
              <td>
                <strong><?= View::escape((string) ($row['company_name'] ?: $domain)) ?></strong><br>
                <span class="dg-muted"><?= View::escape($domain) ?></span>
              </td>
              <td><?= $exp !== '' ? View::escape(date('d.m.Y H:i', strtotime($exp))) : '—' ?></td>
              <td><span class="dg-badge dg-badge--ok">Aktiv</span></td>
              <td class="dg-table__actions">
                <?php if ($href !== '') : ?>
                  <a class="dg-button dg-button--primary dg-button--small" href="<?= View::escape($href) ?>" target="_blank" rel="noopener">CRM öffnen</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
