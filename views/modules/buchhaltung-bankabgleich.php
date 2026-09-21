<?php
/**
 * @var list<array<string, mixed>> $bankTransactionsOpen
 * @var list<array<string, mixed>> $bankTransactionsGhosts
 * @var list<array<string, mixed>> $bankTransactionsMatched
 * @var bool $canEdit
 * @var bool $dbConnected
 * @var array{type: string, message: string}|null $flash
 */
$open = $bankTransactionsOpen ?? [];
$ghosts = $bankTransactionsGhosts ?? [];
$matched = $bankTransactionsMatched ?? [];
$csrf = Csrf::token();
?>
<div class="dg-wrap dg-buchhaltung-bankabgleich">
  <?php View::render('partials/flash', compact('flash')); ?>

  <header class="dg-page-header">
    <h1 class="dg-page-title">Bankabgleich</h1>
    <p class="dg-lead">CAMT.053 oder MT940 importieren und offene Belege automatisch zuordnen. Geisterumsätze werden nie automatisch ausgeblendet — Ausblenden oder Verknüpfen nur manuell.</p>
  </header>

  <?php if ($canEdit && $dbConnected) : ?>
  <section class="dg-panel">
    <h2 class="dg-subsection-title">CAMT.053 importieren</h2>
    <form method="post" action="/app?page=buchhaltung-bankabgleich" enctype="multipart/form-data" class="dg-form">
      <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
      <input type="hidden" name="camt_import" value="1">
      <label class="dg-field dg-field--wide">
        <span>SEPA-Kontoauszug (XML)</span>
        <input type="file" name="camt_file" accept=".xml,text/xml,application/xml" required>
      </label>
      <button type="submit" class="dg-button dg-button--primary">Importieren &amp; abgleichen</button>
    </form>
  </section>
  <section class="dg-panel">
    <h2 class="dg-subsection-title">MT940 importieren</h2>
    <form method="post" action="/app?page=buchhaltung-bankabgleich" enctype="multipart/form-data" class="dg-form">
      <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
      <input type="hidden" name="mt940_import" value="1">
      <label class="dg-field dg-field--wide">
        <span>SWIFT MT940 Kontoauszug</span>
        <input type="file" name="mt940_file" accept=".sta,.940,.txt,text/plain" required>
      </label>
      <button type="submit" class="dg-button">Importieren &amp; abgleichen</button>
    </form>
  </section>
  <?php endif; ?>

  <?php if ($ghosts !== []) : ?>
  <section class="dg-panel dg-panel--notice">
    <h2 class="dg-subsection-title">Geisterumsätze (<?= count($ghosts) ?>)</h2>
    <?php if ($canEdit) : ?>
      <form method="post" action="/app?page=buchhaltung-bankabgleich" class="dg-form dg-form--inline" style="margin-bottom: 1rem;">
        <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
        <button type="submit" name="bank_ghost_hide_all" value="1" class="dg-button dg-button--small">Alle ausblenden</button>
      </form>
    <?php endif; ?>
    <p class="dg-field-hint">
      Diese Umsätze stammen von der Bank, sind aber bereits verbucht oder doppelt importiert.
      Geisterumsätze werden nie automatisch ausgeblendet — bitte prüfen und manuell
      Verknüpfen (ohne Doppelbuchung) oder Ausblenden wählen.
    </p>
    <div class="dg-table-wrap">
      <table class="dg-table">
        <thead>
          <tr><th>Datum</th><th>Text</th><th>Gegenseite</th><th class="dg-table__num">Betrag</th><th>Hinweis</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($ghosts as $tx) : ?>
            <tr>
              <td><?= View::escape((string) ($tx['transaction_date'] ?? '')) ?></td>
              <td><?= View::escape((string) ($tx['reference_text'] ?? '')) ?></td>
              <td><?= View::escape((string) ($tx['counterparty_name'] ?? '')) ?></td>
              <td class="dg-table__num"><?= View::escape((string) ($tx['amount_display'] ?? '')) ?></td>
              <td>
                <span class="dg-badge dg-badge--pending"><?= View::escape((string) ($tx['ghost_label'] ?? '')) ?></span>
                <div class="dg-muted dg-ghost-detail"><?= View::escape((string) ($tx['ghost_detail'] ?? '')) ?></div>
                <?php if (!empty($tx['ghost_voucher_id'])) : ?>
                  <a href="/app?page=buchhaltung-beleg-form&action=edit&id=<?= (int) $tx['ghost_voucher_id'] ?>">
                    Beleg #<?= (int) $tx['ghost_voucher_id'] ?> öffnen
                  </a>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($canEdit) : ?>
                  <form method="post" action="/app?page=buchhaltung-bankabgleich" class="dg-inline-form">
                    <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
                    <input type="hidden" name="bank_tx_id" value="<?= (int) ($tx['id'] ?? 0) ?>">
                    <?php if (!empty($tx['ghost_voucher_id']) || !empty($tx['ghost_payment_id'])) : ?>
                      <button type="submit" name="bank_ghost_link" value="1" class="dg-button dg-button--small">Verknüpfen</button>
                    <?php endif; ?>
                    <button type="submit" name="bank_ghost_hide" value="1" class="dg-button dg-button--small">Ausblenden</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
  <?php endif; ?>

  <section class="dg-panel">
    <h2 class="dg-subsection-title">Offene Umsätze (<?= count($open) ?>)</h2>
    <?php if ($open === []) : ?>
      <p class="dg-muted">Keine offenen Bankumsätze<?= $ghosts !== [] ? ' (ohne Geisterumsätze)' : '' ?>.</p>
    <?php else : ?>
      <div class="dg-table-wrap">
        <table class="dg-table">
          <thead>
            <tr><th>Datum</th><th>Text</th><th>Gegenseite</th><th class="dg-table__num">Betrag</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($open as $tx) : ?>
              <tr>
                <td><?= View::escape((string) ($tx['transaction_date'] ?? '')) ?></td>
                <td><?= View::escape((string) ($tx['reference_text'] ?? '')) ?></td>
                <td><?= View::escape((string) ($tx['counterparty_name'] ?? '')) ?></td>
                <td class="dg-table__num"><?= View::escape((string) ($tx['amount_display'] ?? '')) ?></td>
                <td>
                  <?php if ($canEdit) : ?>
                    <form method="post" action="/app?page=buchhaltung-bankabgleich" class="dg-inline-form dg-bank-match-form">
                      <input type="hidden" name="_csrf" value="<?= View::escape($csrf) ?>">
                      <input type="hidden" name="bank_tx_id" value="<?= (int) ($tx['id'] ?? 0) ?>">
                      <div class="dg-bank-match-picker" data-bank-match-picker>
                        <input type="hidden" name="bank_match_voucher_id" value="">
                        <input
                          type="search"
                          class="dg-bank-match-search"
                          placeholder="Kunde oder Rechnungsnr. tippen…"
                          autocomplete="off"
                          aria-autocomplete="list"
                          aria-label="Offenen Beleg suchen"
                        >
                        <div class="dg-bank-match-results" role="listbox" hidden></div>
                      </div>
                      <button type="submit" name="bank_match_manual" value="1" class="dg-button dg-button--small">Zuordnen</button>
                      <button type="submit" name="bank_tx_ignore" value="1" class="dg-button dg-button--small">Ignorieren</button>
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

  <section class="dg-panel">
    <h2 class="dg-subsection-title">Zugeordnet (<?= count($matched) ?>)</h2>
    <?php if ($matched === []) : ?>
      <p class="dg-muted">Noch keine Zuordnungen.</p>
    <?php else : ?>
      <div class="dg-table-wrap">
        <table class="dg-table">
          <thead><tr><th>Datum</th><th>Betrag</th><th>Beleg</th></tr></thead>
          <tbody>
            <?php foreach ($matched as $tx) : ?>
              <tr>
                <td><?= View::escape((string) ($tx['transaction_date'] ?? '')) ?></td>
                <td class="dg-table__num"><?= View::escape((string) ($tx['amount_display'] ?? '')) ?></td>
                <td>
                  <?php if (!empty($tx['matched_voucher_id'])) : ?>
                    <a href="/app?page=buchhaltung-beleg-form&action=edit&id=<?= (int) $tx['matched_voucher_id'] ?>">
                      #<?= (int) $tx['matched_voucher_id'] ?> <?= View::escape((string) ($tx['invoice_number'] ?? '')) ?>
                    </a>
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
<?php if ($canEdit) : ?>
<script>
  window.dgBankMatchConfig = {
    apiUrl: '/api/bank-match-suggest'
  };
</script>
<script src="<?= View::escape(Asset::url('/assets/js/bankabgleich-match.js')) ?>" defer></script>
<?php endif; ?>
