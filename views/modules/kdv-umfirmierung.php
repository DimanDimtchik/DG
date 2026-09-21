<?php
/**
 * @var array<string, mixed> $predecessor
 * @var array<string, mixed> $umfirmForm
 * @var string|null $formError
 * @var list<array{key: string, label: string}> $umfirmChecklist
 */
$p = $predecessor ?? [];
$form = $umfirmForm ?? [];
$checklist = $umfirmChecklist ?? UmfirmierungService::checklist();
$predId = (int) ($p['id'] ?? 0);
?>
<div class="dg-wrap">
  <?php
    View::partial('partials/back-nav', [
        'href' => $predId > 0 ? '/app?page=kdv-kunden&action=edit&id=' . $predId : '/app?page=kdv-kunden',
        'label' => 'Zurück zum SaaS-Kunden',
    ]);
  ?>

  <header class="dg-page-header">
    <h1 class="dg-page-title">Umfirmierung starten</h1>
    <p class="dg-lead">
      Vorgänger: <strong><?= View::escape((string) ($p['company_name'] ?? '')) ?></strong>
      (<?= View::escape((string) ($p['domain'] ?? '')) ?>) —
      legt Nachfolger-Slot an und setzt den Vorgänger auf Archiv (0 €). Keine Buchungsübernahme, keine Auto-Provision.
    </p>
  </header>

  <?php if (!empty($formError)) : ?>
    <div class="dg-alert dg-alert--danger"><?= View::escape($formError) ?></div>
  <?php endif; ?>

  <form method="post" action="/app?page=kdv-umfirmierung&amp;from_id=<?= $predId ?>" class="dg-form">
    <input type="hidden" name="_csrf" value="<?= View::escape(Csrf::token()) ?>">
    <input type="hidden" name="mf_umfirmierung_start" value="1">

    <div class="dg-panel">
      <h2>Stichtag &amp; Nachfolger</h2>
      <div class="dg-form-grid">
        <label class="dg-label">Stichtag *
          <input class="dg-input" type="date" name="stichtag" required value="<?= View::escape((string) ($form['stichtag'] ?? '')) ?>">
        </label>
        <label class="dg-label">Neuer Firmenname *
          <input class="dg-input" type="text" name="company_name" required value="<?= View::escape((string) ($form['company_name'] ?? '')) ?>" placeholder="z. B. Muster GmbH">
        </label>
        <label class="dg-label">Neue Domain *
          <input class="dg-input" type="text" name="domain" required value="<?= View::escape((string) ($form['domain'] ?? '')) ?>" placeholder="muster-gmbh.de">
        </label>
        <label class="dg-label">Rechtsform
          <input class="dg-input" type="text" name="company_type" value="<?= View::escape((string) ($form['company_type'] ?? 'GmbH')) ?>" placeholder="GmbH, UG, …">
        </label>
        <label class="dg-label">Gewinnermittlung (Nachfolger)
          <select class="dg-input" name="gewinnermittlung">
            <?php foreach (UmfirmierungService::GEWINNERMITTLUNG as $key => $label) : ?>
              <?php if ($key === '') {
                  continue;
              } ?>
              <option value="<?= View::escape($key) ?>"<?= (($form['gewinnermittlung'] ?? 'bilanz') === $key) ? ' selected' : '' ?>><?= View::escape($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dg-label">Steuernummer / USt-Id (Hinweis)
          <input class="dg-input" type="text" name="tax_number_note" value="<?= View::escape((string) ($form['tax_number_note'] ?? '')) ?>" placeholder="sobald bekannt">
        </label>
        <label class="dg-label">Tarif Nachfolger
          <select class="dg-input" name="tariff">
            <?php foreach (KdvCustomerRepository::TARIFFS as $key => $label) : ?>
              <option value="<?= View::escape($key) ?>"<?= (($form['tariff'] ?? ($p['tariff'] ?? 'basic')) === $key) ? ' selected' : '' ?>><?= View::escape($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="dg-label">DB-Name (optional)
          <input class="dg-input" type="text" name="db_name" value="<?= View::escape((string) ($form['db_name'] ?? '')) ?>">
        </label>
        <label class="dg-label">Ansprechpartner
          <input class="dg-input" type="text" name="contact_name" value="<?= View::escape((string) ($form['contact_name'] ?? $p['contact_name'] ?? '')) ?>">
        </label>
        <label class="dg-label">E-Mail
          <input class="dg-input" type="email" name="contact_email" value="<?= View::escape((string) ($form['contact_email'] ?? $p['contact_email'] ?? '')) ?>">
        </label>
        <label class="dg-label">Telefon
          <input class="dg-input" type="tel" name="contact_phone" value="<?= View::escape((string) ($form['contact_phone'] ?? $p['contact_phone'] ?? '')) ?>">
        </label>
      </div>
    </div>

    <div class="dg-panel">
      <h2>Checkliste (nach Speichern im Nachfolger-Notizfeld)</h2>
      <ul class="dg-muted">
        <?php foreach ($checklist as $item) : ?>
          <li><?= View::escape((string) $item['label']) ?></li>
        <?php endforeach; ?>
      </ul>
      <p class="dg-field-hint">Nur Hinweise für die Übergabe — keine automatischen Buchungen.</p>
    </div>

    <div class="dg-form-actions">
      <button type="submit" class="dg-button dg-button--primary">Umfirmierung ausführen</button>
      <a class="dg-button" href="/app?page=kdv-kunden&amp;action=edit&amp;id=<?= $predId ?>">Abbrechen</a>
    </div>
  </form>
</div>
