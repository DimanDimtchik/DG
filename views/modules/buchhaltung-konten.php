<?php
/**
 * @var array{skr_type: string, account_digits: int} $chartOfAccountsConfig
 * @var bool $dbConnected
 * @var int $chartAccountCount
 * @var int $chartCatalogCount
 * @var int $chartHintCount
 * @var array{type: string, message: string}|null $flash
 */
$skrLabel = ChartOfAccountsSettings::skrTypeOptions()[$chartOfAccountsConfig['skr_type'] ?? 'skr03'] ?? 'SKR03';
?>
<div class="dg-wrap dg-buchhaltung-konten">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header">
    <h1 class="dg-page-title">Konten</h1>
    <p class="dg-lead">
      Kontenrahmen <?= View::escape($skrLabel) ?> — Kontonummer eingeben oder nach Kontonamen suchen.
    </p>
    <p class="dg-buchhaltung-konten__meta">
      <strong><?= (int) $chartAccountCount ?></strong> Konten geladen
      (Katalog <?= (int) $chartCatalogCount ?> aus DATEV <?= $chartOfAccountsConfig['skr_type'] === 'skr04' ? 'SKR04' : 'SKR03' ?> 2026, davon <?= (int) $chartHintCount ?> mit ausführlichen Hinweisen).
    </p>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">
      Datenbank nicht verbunden. Konten können erst nach Konfiguration unter
      <a href="<?= View::escape(SettingsRegistry::tabUrl('datenbank')) ?>">Einstellungen → Datenbank</a> geladen werden.
    </div>
  <?php elseif ($chartAccountCount === 0) : ?>
    <div class="dg-flash dg-flash--warning">
      Es sind noch keine Konten im gewählten Kontenrahmen vorhanden.
      Bitte unter <a href="<?= View::escape(SettingsRegistry::tabUrl('chart-of-accounts')) ?>">Einstellungen → Kontenrahmen</a>
      speichern oder die Datenbank-Migration ausführen.
    </div>
  <?php endif; ?>

  <div class="dg-buchhaltung-konten__layout">
    <section class="dg-panel dg-buchhaltung-konten__search">
      <h2 class="dg-subsection-title">Konto suchen</h2>

      <label class="dg-field dg-field--wide">
        <span>Kontonummer</span>
        <input
          type="text"
          id="dg-account-number"
          inputmode="numeric"
          pattern="[0-9]*"
          maxlength="8"
          placeholder="z. B. 8400"
          autocomplete="off"
          <?= !$dbConnected ? ' disabled' : '' ?>
        >
        <small class="dg-field-hint"><?= (int) ($chartOfAccountsConfig['account_digits'] ?? 4) ?>-stellige Kontonummer</small>
      </label>

      <label class="dg-field dg-field--wide">
        <span>Kontonamen durchsuchen</span>
        <input
          type="search"
          id="dg-account-search"
          placeholder="z. B. Fahrzeug, Erlöse, Vorsteuer …"
          autocomplete="off"
          <?= !$dbConnected ? ' disabled' : '' ?>
        >
        <small class="dg-field-hint">Durchsucht alle <?= (int) $chartAccountCount ?> Konten des gewählten Rahmens.</small>
      </label>

      <div id="dg-account-search-results" class="dg-account-search-results" hidden></div>
      <p id="dg-account-status" class="dg-account-status" role="status" aria-live="polite" hidden></p>
    </section>

    <div class="dg-buchhaltung-konten__detail">
    <section id="dg-account-hint-panel" class="dg-panel dg-account-hint" hidden aria-live="polite">
      <header class="dg-account-hint__header">
        <div>
          <p class="dg-account-hint__number" id="dg-account-hint-number"></p>
          <h2 class="dg-account-hint__title" id="dg-account-hint-name"></h2>
          <p class="dg-account-hint__meta" id="dg-account-hint-meta"></p>
        </div>
      </header>

      <div class="dg-account-hint__summary" id="dg-account-hint-summary"></div>

      <section class="dg-account-hint__block">
        <h3 class="dg-account-hint__block-title">Kontenziffern</h3>
        <div id="dg-account-hint-digits" class="dg-account-hint__digits"></div>
      </section>

      <section class="dg-account-hint__block" id="dg-account-hint-features-wrap" hidden>
        <h3 class="dg-account-hint__block-title">Merkmale</h3>
        <div id="dg-account-hint-features" class="dg-account-hint__tags"></div>
      </section>

      <section class="dg-account-hint__block" id="dg-account-hint-classification-wrap" hidden>
        <h3 class="dg-account-hint__block-title">Einordnung</h3>
        <div id="dg-account-hint-classification" class="dg-account-hint__tags"></div>
      </section>

      <section class="dg-account-hint__block" id="dg-account-hint-tax-wrap" hidden>
        <h3 class="dg-account-hint__block-title">Steuerliche Wirkung</h3>
        <dl id="dg-account-hint-tax" class="dg-dl dg-dl--compact"></dl>
      </section>

      <section class="dg-account-hint__block" id="dg-account-hint-examples-wrap" hidden>
        <h3 class="dg-account-hint__block-title">Beispiele</h3>
        <ul id="dg-account-hint-examples" class="dg-account-hint__list"></ul>
      </section>

      <section class="dg-account-hint__block" id="dg-account-hint-edge-wrap" hidden>
        <h3 class="dg-account-hint__block-title">Besonderheiten</h3>
        <ul id="dg-account-hint-edge" class="dg-account-hint__list"></ul>
      </section>

      <section class="dg-account-hint__block" id="dg-account-hint-deps-wrap" hidden>
        <h3 class="dg-account-hint__block-title">Abhängigkeiten</h3>
        <ul id="dg-account-hint-deps" class="dg-account-hint__list"></ul>
      </section>
    </section>

    <section id="dg-account-empty" class="dg-panel dg-panel--notice dg-buchhaltung-konten__empty">
      <p>Geben Sie eine Kontonummer ein oder suchen Sie nach einem Kontonamen, um Hinweise anzuzeigen.</p>
    </section>
    </div>
  </div>
</div>
