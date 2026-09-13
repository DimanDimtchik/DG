<?php
/** @var list<array<string, mixed>> $websitePageList */
/** @var array<string, mixed> $websiteMaintenance */
/** @var bool $canEdit */
/** @var bool $dbConnected */
/** @var array{type: string, message: string}|null $flash */
$pages = $websitePageList ?? [];
$legalSlugs = ['impressum', 'datenschutz', 'agb', 'widerruf'];
$legalLabels = [
    'impressum' => 'Impressum',
    'datenschutz' => 'Datenschutzerklärung',
    'agb' => 'Allgemeine Geschäftsbedingungen',
    'widerruf' => 'Widerrufsbelehrung',
];
$legalBySlug = [];
foreach ($pages as $pageRow) {
    $slug = (string) ($pageRow['slug'] ?? '');
    if (in_array($slug, $legalSlugs, true)) {
        $legalBySlug[$slug] = $pageRow;
    }
}
$otherPages = array_values(array_filter(
    $pages,
    static fn(array $pageRow): bool => !in_array((string) ($pageRow['slug'] ?? ''), $legalSlugs, true)
));
$maintenance = $websiteMaintenance ?? WebsiteMaintenanceSettings::config();
$statusLabels = WebsitePageRepository::statusOptions();
$readOnly = !($canEdit ?? false);
$maintEnabled = !empty($maintenance['enabled']);
$previewImage = (string) ($maintenance['image_url'] ?? WebsiteMaintenanceSettings::DEFAULT_IMAGE);
if ($previewImage === '') {
    $previewImage = WebsiteMaintenanceSettings::DEFAULT_IMAGE;
}
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Seiten</h1>
      <p class="dg-lead">Öffentliche Website-Seiten — <?= count($pages) ?> Einträge</p>
    </div>
    <div class="dg-toolbar">
      <?php if ($canEdit && $dbConnected) : ?>
        <a class="dg-button dg-button--primary" href="/app?page=website-seite-form&amp;action=new">Neue Seite</a>
      <?php endif; ?>
    </div>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Datenbank nicht verbunden. Seiten können erst nach Konfiguration unter
      <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Einstellungen → Datenbank</a> angelegt werden.
    </div>
  <?php endif; ?>

  <section class="dg-panel" aria-labelledby="wb-heading">
    <h2 id="wb-heading" class="dg-website-maintenance__title">Pflichtseiten &amp; Startseite</h2>
    <p class="dg-lead dg-website-maintenance__lead">
      Legt Impressum, Datenschutz, AGB, eine branchenspezifische Startseite, Marketing-Seite Terminkalender,
      Kontaktformular mit Datenschutz-Hinweis
      und das Navigationsmenü an. Nutzt Ihre Firmendaten aus den Einstellungen.
    </p>
    <?php if ($canEdit && $dbConnected) : ?>
      <form class="dg-form" method="post" action="/app?page=website-seiten" onsubmit="return confirm('Pflichtseiten jetzt anlegen oder aktualisieren?');">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <input type="hidden" name="website_bootstrap_defaults" value="1">
        <label class="dg-field dg-field--checkbox">
          <span><input type="checkbox" name="bootstrap_overwrite" value="1"> Bestehende Seiten überschreiben (Startseite, Kontakt, Rechtstexte)</span>
        </label>
        <label class="dg-field dg-field--checkbox">
          <span><input type="checkbox" name="bootstrap_maintenance" value="1" checked> Wartungsmodus einschalten</span>
        </label>
        <div class="dg-form-actions">
          <button type="submit" class="dg-button dg-button--primary">Pflichtseiten jetzt anlegen</button>
        </div>
        <p class="dg-muted">Alternativ per SSH: <code>php bin/seed-website-defaults.php --overwrite</code></p>
      </form>
    <?php endif; ?>
  </section>

  <section class="dg-panel" aria-labelledby="legal-pages-heading">
    <h2 id="legal-pages-heading" class="dg-website-maintenance__title">Pflichtseiten</h2>
    <p class="dg-lead dg-website-maintenance__lead">
      Impressum, Datenschutz, AGB und Widerrufsbelehrung — fehlende Seiten können Sie direkt anlegen.
    </p>
    <div class="dg-table-wrap">
      <table class="dg-table">
        <thead>
          <tr>
            <th>Titel</th>
            <th>URL</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($legalSlugs as $legalSlug) : ?>
            <?php if (isset($legalBySlug[$legalSlug])) : ?>
              <?php
                $legalPage = $legalBySlug[$legalSlug];
                $legalStatus = (string) ($legalPage['status'] ?? 'draft');
                $legalPreviewHref = '/vorschau/' . rawurlencode($legalSlug);
              ?>
              <tr>
                <td><strong><?= View::escape((string) ($legalPage['title'] ?? ($legalLabels[$legalSlug] ?? $legalSlug))) ?></strong></td>
                <td class="dg-muted">/<?= View::escape($legalSlug) ?></td>
                <td>
                  <span class="dg-badge <?= $legalStatus === 'published' ? 'dg-badge--ok' : 'dg-badge--muted' ?>">
                    <?= View::escape($statusLabels[$legalStatus] ?? $legalStatus) ?>
                  </span>
                </td>
                <td class="dg-table__actions">
                  <a href="<?= View::escape($legalPreviewHref) ?>" target="_blank" rel="noopener">Vorschau</a>
                  <span class="dg-muted">·</span>
                  <a href="/app?page=website-seite-form&amp;action=edit&amp;id=<?= (int) ($legalPage['id'] ?? 0) ?>">
                    <?= $canEdit ? 'Bearbeiten' : 'Anzeigen' ?>
                  </a>
                </td>
              </tr>
            <?php else : ?>
              <tr>
                <td><strong><?= View::escape($legalLabels[$legalSlug] ?? $legalSlug) ?></strong></td>
                <td class="dg-muted">/<?= View::escape($legalSlug) ?></td>
                <td><span class="dg-badge dg-badge--pending">Noch nicht angelegt</span></td>
                <td class="dg-table__actions">
                  <?php if ($canEdit && $dbConnected) : ?>
                    <a href="/app?page=website-seiten&amp;legal_ensure=<?= View::escape($legalSlug) ?>">Anlegen</a>
                  <?php else : ?>
                    <span class="dg-muted">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endif; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="dg-panel dg-website-maintenance" aria-labelledby="wm-heading">
    <div class="dg-website-maintenance__head">
      <div>
        <h2 id="wm-heading" class="dg-website-maintenance__title">Wartungsmodus</h2>
        <p class="dg-lead dg-website-maintenance__lead">
          Wenn aktiv, sehen Besucher statt der Website eine Aufbau-Seite.
          Vorschau und CRM bleiben für Sie erreichbar.
        </p>
      </div>
      <?php if ($maintEnabled) : ?>
        <span class="dg-badge dg-badge--pending">Aktiv</span>
      <?php else : ?>
        <span class="dg-badge dg-badge--muted">Aus</span>
      <?php endif; ?>
    </div>

    <form class="dg-form" method="post" action="/app?page=website-seiten" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
      <input type="hidden" name="website_maintenance_save" value="1">

      <label class="dg-field dg-field--checkbox">
        <span>
          <input type="checkbox" name="enabled" value="1"<?= $maintEnabled ? ' checked' : '' ?><?= $readOnly ? ' disabled' : '' ?>>
          Wartungsmodus einschalten (öffentliche Website)
        </span>
      </label>

      <div class="dg-form-grid">
        <label class="dg-field dg-field--wide">
          <span>Überschrift</span>
          <input name="headline" value="<?= View::escape((string) ($maintenance['headline'] ?? '')) ?>"
                 maxlength="160"<?= $readOnly ? ' readonly' : '' ?>>
        </label>
        <label class="dg-field dg-field--wide">
          <span>Text</span>
          <textarea name="message" rows="3" maxlength="500"<?= $readOnly ? ' readonly' : '' ?>><?= View::escape((string) ($maintenance['message'] ?? '')) ?></textarea>
        </label>
        <label class="dg-field">
          <span>E-Mail für Fragen</span>
          <input type="email" name="email" value="<?= View::escape((string) ($maintenance['email'] ?? '')) ?>"
                 placeholder="info@ihre-domain.de"<?= $readOnly ? ' readonly' : '' ?>>
        </label>
        <label class="dg-field">
          <span>Hintergrundbild</span>
          <?php if (!$readOnly) : ?>
            <input type="file" name="maintenance_image" accept="image/*,.svg">
            <small class="dg-field-hint">Leer lassen = aktuelles Bild behalten. Standard: Aufbau-Grafik.</small>
          <?php else : ?>
            <small class="dg-field-hint">Nur Ansicht</small>
          <?php endif; ?>
        </label>
      </div>

      <div class="dg-website-maintenance__preview">
        <div class="dg-website-maintenance__preview-img" style="background-image:url('<?= View::escape(Asset::url($previewImage)) ?>')"></div>
        <p class="dg-muted">Vorschau des Hintergrunds</p>
      </div>

      <?php if (!$readOnly) : ?>
        <div class="dg-form-actions">
          <button type="submit" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>>Wartungsmodus speichern</button>
          <button type="submit" name="reset_image" value="1" class="dg-button"<?= !$dbConnected ? ' disabled' : '' ?>>Standardbild wiederherstellen</button>
          <?php if ($maintEnabled) : ?>
            <a class="dg-button" href="/" target="_blank" rel="noopener">Öffentliche Ansicht prüfen</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </form>
  </section>

  <div class="dg-table-wrap">
    <table class="dg-table">
      <thead>
        <tr>
          <th>Titel</th>
          <th>URL</th>
          <th>Status</th>
          <th>Geändert</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($otherPages === []) : ?>
          <tr>
            <td colspan="5" class="dg-table__empty">Noch keine weiteren Seiten. Legen Sie die Startseite an und gestalten Sie sie im Editor.</td>
          </tr>
        <?php else : ?>
          <?php foreach ($otherPages as $pageRow) : ?>
            <?php
              $status = (string) ($pageRow['status'] ?? 'draft');
              $updated = (string) ($pageRow['updated_at'] ?? '');
              $updatedLabel = $updated !== '' ? date('d.m.Y H:i', strtotime($updated)) : '—';
            ?>
            <tr>
              <td><strong><?= View::escape((string) ($pageRow['title'] ?? '')) ?></strong></td>
              <td class="dg-muted">/<?= View::escape((string) ($pageRow['slug'] ?? '')) ?></td>
              <td>
                <span class="dg-badge <?= $status === 'published' ? 'dg-badge--ok' : 'dg-badge--muted' ?>">
                  <?= View::escape($statusLabels[$status] ?? $status) ?>
                </span>
              </td>
              <td><?= View::escape($updatedLabel) ?></td>
              <td class="dg-table__actions">
                <?php
                  $slug = (string) ($pageRow['slug'] ?? '');
                  $previewHref = $slug !== '' ? '/vorschau/' . rawurlencode($slug) : '';
                ?>
                <?php if ($previewHref !== '') : ?>
                  <a href="<?= View::escape($previewHref) ?>" target="_blank" rel="noopener">Vorschau</a>
                  <span class="dg-muted">·</span>
                <?php endif; ?>
                <a href="/app?page=website-seite-form&amp;action=edit&amp;id=<?= (int) ($pageRow['id'] ?? 0) ?>">
                  <?= $canEdit ? 'Bearbeiten' : 'Anzeigen' ?>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
