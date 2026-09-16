<?php
/**
 * @var array{multi_product_enabled: bool, product_groups: list<array{key: string, label: string}>} $legalProductsConfig
 * @var bool $dbConnected
 */
$cfg = $legalProductsConfig ?? LegalProductSettings::config();
$groups = $cfg['product_groups'] ?? [];
if ($groups === []) {
    $groups = [['key' => '', 'label' => '']];
}
$statusOptions = LegalProductSettings::statusOptions();
?>
<form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('agb')) ?>">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

  <div class="dg-panel dg-panel--notice">
    <p>
      Pflichtseiten (<strong>Impressum</strong>, <strong>Datenschutz</strong>, <strong>AGB</strong>, <strong>Widerruf</strong>)
      werden bei Installation automatisch angelegt. Ohne Mehrprodukt-Modus gilt ein gemeinsamer Text.
    </p>
    <p>
      Mit <strong>Mehrprodukt-Modus</strong> pflegen Sie pro Produkt oder Produktgruppe eigene Tabs auf denselben URLs —
      Status wie bei Website-Seiten: <em>Online</em>, <em>Entwurf</em>, <em>Offline</em>.
    </p>
  </div>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Datenbank nicht verbunden.</div>
  <?php endif; ?>

  <fieldset class="dg-form-grid"<?= !$dbConnected ? ' disabled' : '' ?>>
    <legend>Mehrprodukt-Rechtstexte</legend>
    <label class="dg-field dg-field--wide">
      <span>
        <input type="checkbox" name="legal_multi_product" value="1"<?= !empty($cfg['multi_product_enabled']) ? ' checked' : '' ?>>
        Mehrprodukt-Modus aktivieren (Tabs je Produkt/Gruppe)
      </span>
      <small class="dg-field-hint">Kann jederzeit nachträglich aktiviert werden — Tab „Allgemein“ bleibt erhalten.</small>
    </label>
  </fieldset>

  <section class="dg-panel">
    <h3 class="dg-subsection-title">Produktgruppen</h3>
    <p class="dg-lead">Schlüssel nur Kleinbuchstaben/Ziffern (z. B. <code>klarwin</code>, <code>hp-laserjet</code>).</p>
    <div id="dg-legal-product-groups">
      <?php foreach ($groups as $i => $group) : ?>
        <div class="dg-form-grid dg-legal-product-row" style="margin-bottom:12px">
          <label class="dg-field">
            <span>Schlüssel</span>
            <input name="legal_product_key[]" value="<?= View::escape((string) ($group['key'] ?? '')) ?>" placeholder="klarwin">
          </label>
          <label class="dg-field">
            <span>Bezeichnung (Tab)</span>
            <input name="legal_product_label[]" value="<?= View::escape((string) ($group['label'] ?? '')) ?>" placeholder="KlarWin">
          </label>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="dg-form-actions dg-form-actions--inline">
      <button type="button" class="dg-button" id="dg-legal-product-add">+ Produktgruppe</button>
    </p>
  </section>

  <?php if (!empty($cfg['multi_product_enabled'])) : ?>
    <section class="dg-panel">
      <h3 class="dg-subsection-title">Rechtstexte bearbeiten</h3>
      <table class="dg-table">
        <thead>
          <tr>
            <th>Seite</th>
            <?php foreach (LegalProductSettings::allProductTabs() as $tab) : ?>
              <th><?= View::escape($tab['label']) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach (LegalProductSettings::LEGAL_PAGES as $slug => $title) : ?>
            <tr>
              <td><strong><?= View::escape($title) ?></strong><br><code>/<?= View::escape($slug) ?></code></td>
              <?php foreach (LegalProductSettings::allProductTabs() as $tab) : ?>
                <?php
                  $variant = WebsiteLegalVariantRepository::find($slug, $tab['key']);
                  $status = $variant['status'] ?? WebsitePageRepository::STATUS_DRAFT;
                  $statusLabel = $statusOptions[$status] ?? $status;
                ?>
                <td>
                  <span class="dg-badge dg-badge--<?= $status === WebsitePageRepository::STATUS_PUBLISHED ? 'ok' : 'pending' ?>">
                    <?= View::escape($statusLabel) ?>
                  </span>
                  <br>
                  <a href="/app?page=website-recht&amp;slug=<?= rawurlencode($slug) ?>&amp;product=<?= rawurlencode($tab['key']) ?>">Bearbeiten</a>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  <?php endif; ?>

  <p class="dg-form-actions">
    <button type="submit" name="legal_products_save" value="1" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>>Speichern</button>
  </p>
</form>

<template id="dg-legal-product-row-template">
  <div class="dg-form-grid dg-legal-product-row" style="margin-bottom:12px">
    <label class="dg-field">
      <span>Schlüssel</span>
      <input name="legal_product_key[]" value="" placeholder="produkt-key">
    </label>
    <label class="dg-field">
      <span>Bezeichnung (Tab)</span>
      <input name="legal_product_label[]" value="" placeholder="Produktname">
    </label>
  </div>
</template>
<script>
document.getElementById('dg-legal-product-add')?.addEventListener('click', function () {
  const tpl = document.getElementById('dg-legal-product-row-template');
  const host = document.getElementById('dg-legal-product-groups');
  if (!tpl || !host) return;
  host.appendChild(tpl.content.cloneNode(true));
});
</script>
