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
?>
<div class="dg-wrap dg-buchhaltung-datev-export">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header">
    <h1 class="dg-page-title">DATEV-Export</h1>
    <p class="dg-lead">Buchungsstapel im EXTF-Format (700/21) für den Steuerberater.</p>
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

  <section class="dg-panel">
    <h2 class="dg-subsection-title">Buchungsstapel exportieren</h2>
    <form class="dg-form-grid dg-form-grid--compact" method="get" action="/app">
      <input type="hidden" name="page" value="buchhaltung-datev-export">
      <label class="dg-field">
        <span>Geschäftsjahr</span>
        <select name="year">
          <?php foreach ($years as $y) : ?>
            <option value="<?= (int) $y ?>"<?= $year === (int) $y ? ' selected' : '' ?>><?= (int) $y ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="dg-field dg-field--actions">
        <button type="submit" name="download" value="1" class="dg-button dg-button--primary"<?= !$configured || !$dbConnected ? ' disabled' : '' ?>>
          EXTF CSV herunterladen
        </button>
      </div>
    </form>
    <p class="dg-field-hint">
      Exportiert alle Journalbuchungen (Quelle: Belege) des gewählten Jahres.
      Berater-Nr.: <?= View::escape((string) ($settings['consultant_number'] ?? '')) ?: '—' ?>
      &middot; Mandanten-Nr.: <?= View::escape((string) ($settings['client_number'] ?? '')) ?: '—' ?>
    </p>
  </section>
</div>
