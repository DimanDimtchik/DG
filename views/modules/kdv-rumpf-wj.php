<?php
/**
 * @var array<string, mixed>|null $rumpfOrg
 * @var list<array<string, mixed>> $rumpfFirms
 * @var list<array{vorgaenger: array<string, mixed>, nachfolger: array<string, mixed>, stichtag: string}> $rumpfPairs
 * @var list<array{id: int, label: string}> $kdvOrgOptions
 * @var int $rumpfOrgId
 */
$org = $rumpfOrg ?? null;
$firms = $rumpfFirms ?? [];
$pairs = $rumpfPairs ?? [];
$orgId = (int) ($rumpfOrgId ?? 0);
$opts = $kdvOrgOptions ?? [];
?>
<div class="dg-wrap">
  <?php
    View::partial('partials/back-nav', [
        'href' => '/app?page=kdv-kunden',
        'label' => 'Zurück zu SaaS-Kunden',
    ]);
  ?>
  <header class="dg-page-header">
    <h1 class="dg-page-title">Rumpf-WJ / Umfirmierung</h1>
    <p class="dg-lead">Übersicht Stichtage und Slot-Status je Organisation — informationspflichtig, ohne Konsolidierung.</p>
  </header>

  <form method="get" action="/app" class="dg-form" style="margin-bottom:16px;">
    <input type="hidden" name="page" value="kdv-rumpf-wj">
    <label class="dg-label">Organisation
      <select class="dg-input" name="org_id" onchange="this.form.submit()">
        <option value="0">— wählen —</option>
        <?php foreach ($opts as $opt) : ?>
          <option value="<?= (int) $opt['id'] ?>"<?= $orgId === (int) $opt['id'] ? ' selected' : '' ?>><?= View::escape((string) $opt['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </form>

  <?php if ($orgId < 1) : ?>
    <p class="dg-field-hint">Organisation wählen, um Rumpfwirtschaftsjahre und Umfirmierungspaare zu sehen.</p>
  <?php elseif ($org === null) : ?>
    <div class="dg-alert dg-alert--danger">Organisation nicht gefunden.</div>
  <?php else : ?>
    <div class="dg-panel">
      <h2><?= View::escape((string) ($org['name'] ?? '')) ?></h2>
      <?php if (KdvOrgRepository::shareContactsColumnReady()) : ?>
        <p class="dg-field-hint">
          Shared Contacts:
          <strong><?= !empty($org['share_contacts']) ? 'gewünscht' : 'aus' ?></strong>
          (kein Cross-DB-Sync in MF4 — nur Kennzeichnung).
        </p>
        <form method="post" action="/app?page=kdv-rumpf-wj&amp;org_id=<?= $orgId ?>" style="margin-bottom:12px;">
          <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
          <label class="dg-field" style="display:flex;gap:8px;align-items:center;">
            <input type="checkbox" name="share_contacts" value="1"<?= !empty($org['share_contacts']) ? ' checked' : '' ?>>
            <span>Shared Contacts für diese Org markieren</span>
          </label>
          <button type="submit" class="dg-button" name="mf_share_contacts_save" value="1">Speichern</button>
        </form>
      <?php endif; ?>
    </div>

    <?php if ($pairs !== []) : ?>
      <div class="dg-panel">
        <h2>Umfirmierungspaare</h2>
        <ul>
          <?php foreach ($pairs as $pair) : ?>
            <?php
              $v = $pair['vorgaenger'];
              $n = $pair['nachfolger'];
              $st = (string) ($pair['stichtag'] ?? '');
              $stLabel = $st !== '' ? date('d.m.Y', strtotime($st)) : '—';
            ?>
            <li style="margin-bottom:10px;">
              Stichtag <strong><?= View::escape($stLabel) ?></strong>:
              <a href="/app?page=kdv-kunden&amp;action=edit&amp;id=<?= (int) ($v['id'] ?? 0) ?>">
                <?= View::escape((string) ($v['company_name'] ?? '')) ?></a>
              (<?= View::escape(RumpfWjReportService::periodLabel($v)) ?>, Archiv)
              →
              <a href="/app?page=kdv-kunden&amp;action=edit&amp;id=<?= (int) ($n['id'] ?? 0) ?>">
                <?= View::escape((string) ($n['company_name'] ?? '')) ?></a>
              (<?= View::escape(RumpfWjReportService::periodLabel($n)) ?>)
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="dg-panel">
      <h2>Alle Firmen-Slots</h2>
      <div class="dg-table-wrap">
        <table class="dg-table">
          <thead>
            <tr>
              <th>Firma</th>
              <th>Domain</th>
              <th>Beziehung</th>
              <th>Slot</th>
              <th>Zeitraum</th>
              <th>Gewinnermittlung</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($firms as $f) : ?>
              <tr>
                <td>
                  <a href="/app?page=kdv-kunden&amp;action=edit&amp;id=<?= (int) ($f['id'] ?? 0) ?>">
                    <?= View::escape((string) ($f['company_name'] ?? '')) ?>
                  </a>
                </td>
                <td><?= View::escape((string) ($f['domain'] ?? '')) ?></td>
                <td><?= View::escape(KdvCustomerRepository::FIRM_RELATIONS[(string) ($f['firm_relation'] ?? '')] ?? (string) ($f['firm_relation'] ?? '')) ?></td>
                <td><?= View::escape(KdvCustomerRepository::FIRM_SLOT_STATUSES[(string) ($f['firm_slot_status'] ?? '')] ?? (string) ($f['firm_slot_status'] ?? '')) ?></td>
                <td><?= View::escape(RumpfWjReportService::periodLabel($f)) ?></td>
                <td><?= View::escape(UmfirmierungService::GEWINNERMITTLUNG[(string) ($f['gewinnermittlung'] ?? '')] ?? '—') ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if ($firms === []) : ?>
              <tr><td colspan="6">Keine Firmen in dieser Organisation.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>
