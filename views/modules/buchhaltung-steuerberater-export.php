<?php
/**
 * @var int $datevExportYear
 * @var list<int> $datevExportYears
 * @var array{consultant_number: string, client_number: string} $datevExportSettings
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$year = (int) ($datevExportYear ?? (int) date('Y'));
$years = $datevExportYears ?? [(int) date('Y')];
$settings = $datevExportSettings ?? DatevExportSettings::forForm();
$configured = DatevExportSettings::isConfigured();
$base = '/app?page=buchhaltung-steuerberater-export&year=' . (int) $year;
?>
<div class="dg-wrap dg-buchhaltung-steuerberater-export">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header">
    <h1 class="dg-page-title">Steuerberater-Export</h1>
    <p class="dg-lead">DATEV, Agenda, Addison — Buchungsstapel, Stammdaten und Belege.</p>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Datenbank nicht verbunden.</div>
  <?php endif; ?>

  <?php if (!$configured) : ?>
    <div class="dg-flash dg-flash--warning">
      Berater- und Mandantennummer fehlen.
      <a href="<?= View::escape(SettingsRegistry::tabUrl('chart-of-accounts')) ?>">In den Einstellungen pflegen</a>
    </div>
  <?php endif; ?>

  <form class="dg-panel dg-ledger-filters" method="get" action="/app">
    <input type="hidden" name="page" value="buchhaltung-steuerberater-export">
    <label class="dg-field">
      <span>Geschäftsjahr</span>
      <select name="year" onchange="this.form.submit()">
        <?php foreach ($years as $y) : ?>
          <option value="<?= (int) $y ?>"<?= $year === (int) $y ? ' selected' : '' ?>><?= (int) $y ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </form>

  <section class="dg-panel">
    <h2 class="dg-subsection-title">Buchungsstapel</h2>
    <p class="dg-field-hint">
      Berater-Nr.: <?= View::escape((string) ($settings['consultant_number'] ?? '')) ?: '—' ?>
      &middot; Mandanten-Nr.: <?= View::escape((string) ($settings['client_number'] ?? '')) ?: '—' ?>
      &middot; Inkl. Belege, manuelle Buchungen und GuV-Abschluss
    </p>
    <div class="dg-form-actions">
      <a class="dg-button dg-button--primary" href="<?= View::escape($base . '&download=datev') ?>"<?= !$configured ? ' aria-disabled="true"' : '' ?>>DATEV EXTF (700/21)</a>
      <a class="dg-button" href="<?= View::escape($base . '&download=agenda') ?>">Agenda CSV</a>
      <a class="dg-button" href="<?= View::escape($base . '&download=addison') ?>"<?= !$configured ? ' aria-disabled="true"' : '' ?>>Addison (DATEV-kompatibel)</a>
    </div>
  </section>

  <section class="dg-panel">
    <h2 class="dg-subsection-title">Stammdaten &amp; Belege</h2>
    <div class="dg-form-actions">
      <a class="dg-button" href="<?= View::escape($base . '&download=stammdaten') ?>"<?= !$configured ? ' aria-disabled="true"' : '' ?>>Kontenbeschriftungen (EXTF)</a>
      <a class="dg-button" href="<?= View::escape($base . '&download=personen') ?>">Personenkonten CSV</a>
      <a class="dg-button" href="<?= View::escape($base . '&download=belege') ?>"<?= !$configured ? ' aria-disabled="true"' : '' ?>>ZIP mit Belegbildern</a>
      <a class="dg-button dg-button--primary" href="<?= View::escape($base . '&download=paket') ?>"<?= !$configured ? ' aria-disabled="true"' : '' ?>>Komplett-Paket (ZIP)</a>
    </div>
    <p class="dg-field-hint">Agenda und Addison importieren über Transfer → DATEV-Import-Assistent in der Kanzlei-Software.</p>
  </section>
</div>
