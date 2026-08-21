<?php
/**
 * @var int $manualYear
 * @var list<int> $manualYears
 * @var list<array<string, mixed>> $manualBatches
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$year = (int) ($manualYear ?? (int) date('Y'));
$years = $manualYears ?? [(int) date('Y')];
$batches = $manualBatches ?? [];
$csrf = Csrf::token();
?>
<div class="dg-wrap dg-buchhaltung-manuelle-buchung">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header">
    <h1 class="dg-page-title">Manuelle Buchungen</h1>
    <p class="dg-lead">Freie Journalbuchungen ohne Beleg — Soll und Haben müssen ausgeglichen sein.</p>
  </header>

  <form class="dg-panel dg-ledger-filters" method="get" action="/app">
    <input type="hidden" name="page" value="buchhaltung-manuelle-buchung">
    <label class="dg-field">
      <span>Geschäftsjahr</span>
      <select name="year">
        <?php foreach ($years as $y) : ?>
          <option value="<?= (int) $y ?>"<?= $year === (int) $y ? ' selected' : '' ?>><?= (int) $y ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button type="submit" class="dg-button">Anzeigen</button>
  </form>

  <?php if ($canEdit && $dbConnected) : ?>
  <section class="dg-panel">
    <h2 class="dg-subsection-title">Neue Buchung</h2>
    <form method="post" action="/app?page=buchhaltung-manuelle-buchung&year=<?= (int) $year ?>" class="dg-form">
      <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
      <input type="hidden" name="manual_journal_save" value="1">
      <div class="dg-form-grid">
        <label class="dg-field">
          <span>Datum</span>
          <input type="date" name="batch_date" value="<?= View::escape(date('Y-m-d')) ?>" required>
        </label>
        <label class="dg-field dg-field--wide">
          <span>Beschreibung</span>
          <input type="text" name="batch_description" maxlength="500" placeholder="z. B. Umbuchung, Korrektur …">
        </label>
      </div>
      <div class="dg-table-wrap">
        <table class="dg-table" id="dg-manual-lines">
          <thead>
            <tr>
              <th>Konto</th>
              <th>Gegenkonto</th>
              <th>S/H</th>
              <th>Betrag</th>
              <th>BU</th>
              <th>Text</th>
            </tr>
          </thead>
          <tbody>
            <?php for ($i = 0; $i < 4; $i++) : ?>
            <tr>
              <td><input type="text" name="lines[<?= $i ?>][account_number]" inputmode="numeric" pattern="[0-9]*" placeholder="1200"></td>
              <td><input type="text" name="lines[<?= $i ?>][contra_account]" inputmode="numeric" pattern="[0-9]*" placeholder="9000"></td>
              <td>
                <select name="lines[<?= $i ?>][side]">
                  <option value="debit">Soll</option>
                  <option value="credit">Haben</option>
                </select>
              </td>
              <td><input type="text" name="lines[<?= $i ?>][amount]" inputmode="decimal" placeholder="0,00"></td>
              <td><input type="text" name="lines[<?= $i ?>][tax_key]" maxlength="3" placeholder="9"></td>
              <td><input type="text" name="lines[<?= $i ?>][description]" maxlength="120"></td>
            </tr>
            <?php endfor; ?>
          </tbody>
        </table>
      </div>
      <p class="dg-form-actions">
        <button type="submit" class="dg-button dg-button--primary">Buchung speichern</button>
      </p>
    </form>
  </section>
  <?php endif; ?>

  <section class="dg-panel">
    <h2 class="dg-subsection-title">Buchungen <?= (int) $year ?></h2>
    <?php if ($batches === []) : ?>
      <p class="dg-muted">Keine manuellen Buchungen.</p>
    <?php else : ?>
      <div class="dg-table-wrap">
        <table class="dg-table">
          <thead>
            <tr><th>Datum</th><th>Beschreibung</th><th>Zeilen</th><th class="dg-table__num">Soll</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($batches as $batch) : ?>
              <tr>
                <td><?= View::escape((string) ($batch['batch_date'] ?? '')) ?></td>
                <td><?= View::escape((string) ($batch['description'] ?? '')) ?></td>
                <td><?= (int) ($batch['line_count'] ?? 0) ?></td>
                <td class="dg-table__num"><?= number_format((float) ($batch['total_debit'] ?? 0), 2, ',', '.') ?> €</td>
                <td>
                  <?php if ($canEdit) : ?>
                    <form method="post" action="/app?page=buchhaltung-manuelle-buchung&year=<?= (int) $year ?>" onsubmit="return confirm('Buchung löschen?');">
                      <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
                      <input type="hidden" name="manual_batch_id" value="<?= (int) ($batch['id'] ?? 0) ?>">
                      <button type="submit" name="manual_journal_delete" value="1" class="dg-linklike dg-linklike--danger">Löschen</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>
