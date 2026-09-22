<?php
/**
 * Arbeitsstunden-Import aus Excel/CSV (Shiftbase, Crewmeister, …).
 *
 * @var array{type: string, message: string}|null $flash
 * @var list<string> $timeHoursImportErrors
 */
$errors = is_array($timeHoursImportErrors ?? null) ? $timeHoursImportErrors : [];
$sources = TimeHoursImportPresets::all();
$accept = InstallImportSourcePresets::tabularAcceptAttribute();
?>
<div class="dg-wrap dg-zeiterfassung-stundenimport">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Arbeitsstunden importieren</h1>
      <p class="dg-lead">Excel/CSV aus Shiftbase, Crewmeister oder eigener Tabelle → Stempelzeiten + Tagesaggregation</p>
    </div>
    <div class="dg-toolbar">
      <a class="dg-button" href="/app?page=zeiterfassung-monat">Monatsblatt</a>
      <a class="dg-button" href="/app?page=zeiterfassung-team">Team heute</a>
      <a class="dg-button" href="/app?page=zeiterfassung-lohnexport">Lohn-Export</a>
      <a class="dg-button" href="/app?page=kontakte">Kontakte</a>
    </div>
  </header>

  <section class="dg-panel">
    <form method="post" action="/app?page=zeiterfassung-stundenimport" enctype="multipart/form-data" class="dg-form">
      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
      <input type="hidden" name="time_hours_import" value="1">

      <label>
        <span>Quellsystem</span>
        <select name="import_source" required>
          <?php foreach ($sources as $key => $meta) : ?>
            <option value="<?= View::escape($key) ?>"<?= $key === 'shiftbase' ? ' selected' : '' ?>>
              <?= View::escape((string) ($meta['label'] ?? $key)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <p class="dg-muted" id="dg-hours-import-hint">
        <?= View::escape((string) ($sources['shiftbase']['hint'] ?? '')) ?>
      </p>

      <label>
        <span>Datei</span>
        <input type="file" name="time_hours_file" accept="<?= View::escape($accept) ?>" required>
      </label>

      <label>
        <span>Wenn für den Tag schon Stempelzeiten existieren</span>
        <select name="on_conflict">
          <option value="skip" selected>Überspringen (bestehende Daten behalten)</option>
          <option value="replace">Ersetzen (Tag komplett neu aus der Datei)</option>
        </select>
      </label>

      <p class="dg-muted">
        Zuordnung über E-Mail, Login/Personalnummer oder exakten Anzeigenamen — nur Mitarbeiter-Kontakte.
        Ohne Beginn/Ende werden Stunden ab 08:00 synthetisiert. Max. 5 MB.
        <a href="/app?page=zeiterfassung-stundenimport&amp;action=template">Beispiel-Vorlage herunterladen</a>
      </p>

      <button type="submit" class="dg-button dg-button--primary">Stunden importieren</button>
    </form>
  </section>

  <?php if ($errors !== []) : ?>
    <section class="dg-panel dg-panel--warning">
      <h2 class="dg-subsection-title">Hinweise aus dem letzten Import</h2>
      <ul class="dg-list">
        <?php foreach ($errors as $err) : ?>
          <li><?= View::escape((string) $err) ?></li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <section class="dg-panel">
    <h2 class="dg-subsection-title">Erwartete Spalten</h2>
    <p class="dg-muted">
      Mindestens: Mitarbeiter-Kennung (E-Mail / Login / Name) + Datum + (Beginn und Ende <em>oder</em> Stunden).
      Optional: Pause in Minuten. Shiftbase-/Crewmeister-Exportspalten werden über Aliasse erkannt.
    </p>
  </section>
</div>
<script>
(function () {
  var hints = <?= json_encode(array_map(static fn ($m) => (string) ($m['hint'] ?? ''), $sources), JSON_UNESCAPED_UNICODE) ?>;
  var sel = document.querySelector('select[name="import_source"]');
  var out = document.getElementById('dg-hours-import-hint');
  if (!sel || !out) return;
  sel.addEventListener('change', function () {
    out.textContent = hints[sel.value] || '';
  });
})();
</script>
