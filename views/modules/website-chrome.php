<?php
/** @var array<string, string> $websiteChromeForm */
/** @var bool $canEdit */
/** @var bool $dbConnected */
/** @var array{type: string, message: string}|null $flash */
$form = $websiteChromeForm ?? WebsiteSettings::chromeDefaults();
$readOnly = !($canEdit ?? false);
?>
<div class="dg-wrap">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header">
    <h1 class="dg-page-title">Kopf &amp; Fuß</h1>
    <p class="dg-lead">Texte der Kopf- und Fußzeile sowie optionale Skripte (z. B. Statistik) für die öffentliche Website.</p>
  </header>

  <?php if (!$dbConnected) : ?>
    <div class="dg-flash dg-flash--warning">Zum Speichern ist eine Datenbankverbindung erforderlich.</div>
  <?php endif; ?>

  <form class="dg-form dg-panel" method="post" action="/app?page=website-chrome">
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
    <input type="hidden" name="website_chrome_save" value="1">

    <h2>Kopfzeile</h2>
    <div class="dg-form-grid">
      <label class="dg-field">
        <span>Titel</span>
        <input name="header_title" value="<?= View::escape((string) ($form['header_title'] ?? '')) ?>" placeholder="Firmenname"<?= $readOnly ? ' readonly' : '' ?>>
      </label>
      <label class="dg-field dg-field--wide">
        <span>Unterzeile</span>
        <input name="header_tagline" value="<?= View::escape((string) ($form['header_tagline'] ?? '')) ?>" placeholder="Kurzer Satz unter dem Titel"<?= $readOnly ? ' readonly' : '' ?>>
      </label>
    </div>

    <h2>Fußzeile</h2>
    <label class="dg-field dg-field--wide">
      <span>Text</span>
      <textarea name="footer_text" rows="3" placeholder="z. B. © Firma · Impressum"<?= $readOnly ? ' readonly' : '' ?>><?= View::escape((string) ($form['footer_text'] ?? '')) ?></textarea>
    </label>

    <div class="dg-panel dg-panel--nested">
      <h2>Google Analytics &amp; Tag Manager</h2>
      <p class="dg-field-hint">
        Werden nur geladen, wenn der Besucher im Cookie-Banner der Kategorie <strong>Statistik</strong> zustimmt.
        Bei Tag Manager und Analytics gleichzeitig wird nur der Tag Manager ausgegeben (GA dort konfigurieren).
      </p>
      <div class="dg-form-grid">
        <label class="dg-field">
          <span>Google Analytics 4 Mess-ID</span>
          <input name="ga_measurement_id" value="<?= View::escape((string) ($form['ga_measurement_id'] ?? '')) ?>" placeholder="G-XXXXXXXX"<?= $readOnly ? ' readonly' : '' ?>>
          <small class="dg-field-hint">Format: G-… (ohne Tag Manager verwenden)</small>
        </label>
        <label class="dg-field">
          <span>Google Tag Manager Container-ID</span>
          <input name="gtm_container_id" value="<?= View::escape((string) ($form['gtm_container_id'] ?? '')) ?>" placeholder="GTM-XXXXXXX"<?= $readOnly ? ' readonly' : '' ?>>
          <small class="dg-field-hint">Format: GTM-… — bevorzugt, wenn Sie Tags in GTM pflegen</small>
        </label>
      </div>
    </div>

    <div class="dg-panel dg-panel--nested">
      <h2>Weitere Skripte</h2>
      <p class="dg-field-hint">Freier Code (ohne Consent-Filter). Für Statistik bitte die Felder oben nutzen. Header = &lt;head&gt;, Footer = vor &lt;/body&gt;.</p>
      <label class="dg-field dg-field--wide">
        <span>JavaScript im Header</span>
        <textarea name="header_js" rows="6" class="dg-input--mono" spellcheck="false"<?= $readOnly ? ' readonly' : '' ?>><?= View::escape((string) ($form['header_js'] ?? '')) ?></textarea>
      </label>
      <label class="dg-field dg-field--wide">
        <span>JavaScript im Footer</span>
        <textarea name="footer_js" rows="6" class="dg-input--mono" spellcheck="false"<?= $readOnly ? ' readonly' : '' ?>><?= View::escape((string) ($form['footer_js'] ?? '')) ?></textarea>
      </label>
    </div>

    <?php if (!$readOnly) : ?>
      <div class="dg-form-actions">
        <button type="submit" class="dg-button dg-button--primary"<?= !$dbConnected ? ' disabled' : '' ?>>Kopf &amp; Fuß speichern</button>
      </div>
    <?php endif; ?>
  </form>
</div>
