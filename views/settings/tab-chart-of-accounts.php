<?php
/**
 * @var array{skr_type: string, account_digits: int} $chartOfAccountsConfig
 * @var bool $dbConnected
 */
$skrOptions = ChartOfAccountsSettings::skrTypeOptions();
$activeSkr = (string) ($chartOfAccountsConfig['skr_type'] ?? 'skr03');
$accountDigits = (int) ($chartOfAccountsConfig['account_digits'] ?? 4);
?>
<form class="dg-form" method="post" action="<?= View::escape(SettingsRegistry::tabUrl('chart-of-accounts')) ?>">
  <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">

  <p class="dg-lead">
    Wählen Sie den Kontenrahmen für Ihre Buchhaltung. Die Kontenhinweise auf der Seite
    <a href="/app?page=buchhaltung-konten">Konten</a> beziehen sich auf den hier gewählten Rahmen.
  </p>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Zum Speichern ist eine funktionierende <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Datenbankverbindung</a> erforderlich.
    </div>
  <?php endif; ?>

  <div class="dg-form-grid">
    <label class="dg-field">
      <span>Kontenrahmen</span>
      <select name="skr_type"<?= !$dbConnected ? ' disabled' : '' ?>>
        <?php foreach ($skrOptions as $value => $label) : ?>
          <option value="<?= View::escape($value) ?>"<?= $activeSkr === $value ? ' selected' : '' ?>>
            <?= View::escape($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <small class="dg-field-hint">SKR03 ist der Standard für die meisten KMU in Deutschland.</small>
    </label>

    <label class="dg-field">
      <span>Kontonummern-Stellen</span>
      <input
        type="number"
        name="account_digits"
        value="<?= (int) $accountDigits ?>"
        min="4"
        max="8"
        step="1"
        readonly
        class="dg-input--readonly"
      >
      <small class="dg-field-hint">Vierstellige Kontonummern (Standard SKR).</small>
    </label>
  </div>

  <p class="dg-form-actions">
    <button type="submit" name="chart_of_accounts_save" value="1" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>>
      Kontenrahmen speichern
    </button>
  </p>

  <hr class="dg-form-divider">

  <h3 class="dg-subsection-title">DATEV-Export</h3>
  <p class="dg-field-hint">Berater- und Mandantennummer für den EXTF-Buchungsstapel-Export an die Steuerkanzlei.</p>
  <?php
  $datevExportSettings = $datevExportSettings ?? DatevExportSettings::forForm();
  ?>
  <div class="dg-form-grid">
    <label class="dg-field">
      <span>Beraternummer</span>
      <input type="text" name="datev_consultant_number" maxlength="7" pattern="\d*"
             value="<?= View::escape((string) ($datevExportSettings['consultant_number'] ?? '')) ?>"
             placeholder="z. B. 1001"<?= !$dbConnected ? ' disabled' : '' ?>>
    </label>
    <label class="dg-field">
      <span>Mandantennummer</span>
      <input type="text" name="datev_client_number" maxlength="7" pattern="\d*"
             value="<?= View::escape((string) ($datevExportSettings['client_number'] ?? '')) ?>"
             placeholder="z. B. 101"<?= !$dbConnected ? ' disabled' : '' ?>>
    </label>
  </div>
</form>

<section class="dg-panel" style="margin-top: 20px;">
  <header class="dg-panel__toolbar dg-panel__toolbar--lead">
    <div>
      <h3 class="dg-subsection-title">Aktiver Rahmen: <?= View::escape($skrOptions[$activeSkr] ?? strtoupper($activeSkr)) ?></h3>
      <p class="dg-field-hint">
        Katalog: <?= (int) ChartAccountCatalog::catalogCount($activeSkr) ?> Konten
        (<?= $activeSkr === 'skr04' ? 'DATEV SKR04 PDF 2026' : 'DATEV SKR03 PDF 2026' ?>).
        <?= (int) ChartAccountSeedData::seedCount($activeSkr) ?> Konten mit ausführlichen Hinweisen.
      </p>
    </div>
  </header>
  <dl class="dg-dl">
    <dt>Kontenklasse (1. Ziffer)</dt>
    <dd>
      <?php foreach (SkrDigitLegend::forSkr($activeSkr) as $digit => $meaning) : ?>
        <span class="dg-account-hint__tag"><?= (int) $digit ?>: <?= View::escape($meaning) ?></span>
      <?php endforeach; ?>
    </dd>
  </dl>
</section>
