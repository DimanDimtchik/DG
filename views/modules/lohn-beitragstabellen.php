<?php
/**
 * Lohn L4b — Beitragstabellen (Import / Versionierung).
 *
 * @var list<array<string, mixed>> $payrollRateSets
 * @var array<string, mixed>|null $payrollRateSetView
 * @var array<string, string> $payrollRateParams
 * @var bool $payrollRateCanEdit
 * @var array{type: string, message: string}|null $flash
 */
$sets = is_array($payrollRateSets ?? null) ? $payrollRateSets : [];
$view = is_array($payrollRateSetView ?? null) ? $payrollRateSetView : null;
$viewId = (int) ($view['id'] ?? 0);
$params = is_array($payrollRateParams ?? null) ? $payrollRateParams : [];
$canEditRates = !empty($payrollRateCanEdit);
?>
<div class="dg-wrap dg-lohn-beitragstabellen">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header dg-page-header--toolbar">
    <div>
      <h1 class="dg-page-title">Beitragstabellen</h1>
      <p class="dg-lead">
        Versionierte Sätze (`gültig_ab`) für den Brutto→Netto-Kern. Fixture-Seed ist nur für Tests —
        produktive Werte per CSV-Import. Kein Monatslauf auf dieser Seite.
      </p>
    </div>
    <div class="dg-toolbar">
      <a class="dg-button" href="/app?page=lohn-lohnarten">Lohnarten</a>
      <a class="dg-button" href="/app?page=lohn-beitragstabellen&amp;download=example">Beispiel-CSV</a>
    </div>
  </header>

  <?php if ($canEditRates) : ?>
    <section class="dg-panel">
      <h2 class="dg-subsection-title">CSV importieren</h2>
      <p class="dg-lead">
        Format: Meta-Zeilen <code>valid_from,JJJJ-MM-TT</code> / <code>label,…</code> und
        Parameter <code>param,key,value</code>. Unbekannte Keys werden abgelehnt (Allowlist).
      </p>
      <form method="post" action="/app?page=lohn-beitragstabellen" enctype="multipart/form-data" class="dg-form">
        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
        <input type="hidden" name="rate_action" value="import">
        <div class="dg-form-grid">
          <label class="dg-field dg-field--wide">
            <span>CSV-Datei</span>
            <input type="file" name="rate_csv" accept=".csv,text/csv,text/plain" required>
          </label>
          <label class="dg-field dg-field--wide">
            <span>oder CSV-Text einfügen</span>
            <textarea name="rate_csv_text" rows="8" placeholder="valid_from,2026-01-01&#10;label,MEIN-IMPORT&#10;param,lst_fixture_factor,0.14"></textarea>
          </label>
        </div>
        <div class="dg-form-actions">
          <button type="submit" class="dg-button dg-button--primary">Importieren</button>
        </div>
      </form>
    </section>
  <?php endif; ?>

  <?php if ($viewId > 0 && $view !== null) : ?>
    <section class="dg-panel">
      <h2 class="dg-subsection-title">Satz #<?= $viewId ?> — <?= View::escape((string) ($view['label'] ?? '')) ?></h2>
      <dl class="dg-dl">
        <dt>gültig ab</dt><dd><?= View::escape((string) ($view['valid_from'] ?? '')) ?></dd>
        <dt>gültig bis</dt><dd><?php
          $vt = $view['valid_to'] ?? null;
          echo View::escape($vt !== null && (string) $vt !== '' ? (string) $vt : 'offen');
        ?></dd>
        <dt>Quelle</dt><dd><?= View::escape((string) ($view['source_note'] ?? '')) ?></dd>
        <dt>Checksum</dt><dd><code><?= View::escape((string) ($view['checksum'] ?? '')) ?></code></dd>
        <dt>Aktiv</dt><dd><?= !empty($view['is_active']) ? 'ja' : 'nein' ?></dd>
      </dl>
      <div class="dg-table-wrap">
        <table class="dg-table dg-table--compact">
          <thead><tr><th>param_key</th><th>Wert</th><?php if ($canEditRates && !empty($view['is_active'])) : ?><th></th><?php endif; ?></tr></thead>
          <tbody>
            <?php foreach ($params as $pk => $pv) : ?>
              <tr>
                <td><code><?= View::escape((string) $pk) ?></code></td>
                <td><?= View::escape((string) $pv) ?></td>
                <?php if ($canEditRates && !empty($view['is_active'])) : ?>
                  <td>
                    <form method="post" action="/app?page=lohn-beitragstabellen&amp;view=<?= $viewId ?>" class="dg-form dg-btn-row">
                      <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                      <input type="hidden" name="rate_action" value="patch">
                      <input type="hidden" name="rate_set_id" value="<?= $viewId ?>">
                      <input type="hidden" name="param_key" value="<?= View::escape((string) $pk) ?>">
                      <input type="text" name="param_value" value="<?= View::escape((string) $pv) ?>" style="width:8rem">
                      <button type="submit" class="dg-button dg-button--small">Korrigieren</button>
                    </form>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p><a class="dg-button" href="/app?page=lohn-beitragstabellen">Zur Liste</a></p>
    </section>
  <?php endif; ?>

  <section class="dg-panel">
    <h2 class="dg-subsection-title">Vorhandene Sätze</h2>
    <?php if ($sets === []) : ?>
      <p class="dg-lead">Noch keine Sätze — Fixture wird beim ersten Aufruf geseedet.</p>
    <?php else : ?>
      <div class="dg-table-wrap">
        <table class="dg-table">
          <thead>
            <tr>
              <th>Label</th>
              <th>gültig ab</th>
              <th>bis</th>
              <th>Aktiv</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($sets as $row) : ?>
              <tr>
                <td><?= View::escape((string) ($row['label'] ?? '')) ?></td>
                <td><?= View::escape((string) ($row['valid_from'] ?? '')) ?></td>
                <td><?= View::escape((string) (($row['valid_to'] ?? '') ?: '—')) ?></td>
                <td><?= !empty($row['is_active']) ? 'ja' : 'nein' ?></td>
                <td class="dg-table__actions">
                  <div class="dg-table__actions-group">
                    <a class="dg-button dg-button--small" href="/app?page=lohn-beitragstabellen&amp;view=<?= (int) ($row['id'] ?? 0) ?>">Anzeigen</a>
                    <?php if ($canEditRates && !empty($row['is_active'])) : ?>
                      <form method="post" action="/app?page=lohn-beitragstabellen" onsubmit="return confirm('Satz ablösen (deaktivieren)?');">
                        <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
                        <input type="hidden" name="rate_action" value="supersede">
                        <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">
                        <button type="submit" class="dg-button dg-button--small">Ablösen</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>
