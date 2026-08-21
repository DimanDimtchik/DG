<?php
/** @var array<string, mixed> $plan */
/** @var array<string, string> $form */
/** @var list<string> $errors */
/** @var array<string, mixed>|null $preview */
/** @var array{ok?: bool, domain?: string, status?: string, message?: string, blocks?: bool}|null $domainCheck */
$isYearly = ($form['billing_cycle'] ?? '') === 'jaehrlich';
$net = $isYearly ? (float) $plan['yearly_net'] : (float) $plan['monthly_net'];
$gross = $isYearly ? (float) $plan['yearly_gross'] : (float) $plan['monthly_gross'];
$domainValue = (string) ($form['domain_raw'] ?? $form['domain'] ?? '');
$domainCheck = $domainCheck ?? null;
?>
<section class="shop-section shop-section--tight">
  <h1>Bestellung</h1>
  <p class="shop-lead">
    Tarif <strong><?= ShopView::escape((string) $plan['name']) ?></strong>
    — <?= ShopView::escape(ShopPlans::formatMoney($net)) ?>
    <?= $isYearly ? 'pro Jahr netto' : 'pro Monat netto' ?>
    (<?= ShopView::escape(ShopPlans::formatMoneyExact($gross)) ?> inkl. MwSt.)
  </p>
  <p class="shop-vat">Felder mit <span class="shop-req" aria-hidden="true">*</span> sind Pflichtfelder. Die Wunsch-Domain ist freiwillig.</p>

  <?php if ($errors !== []) : ?>
    <div class="shop-alert" role="alert">
      <ul>
        <?php foreach ($errors as $e) : ?>
          <li><?= ShopView::escape($e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($preview !== null) : ?>
    <div class="shop-alert shop-alert--ok">
      <p><strong>Angaben geprüft.</strong> <?= ShopView::escape((string) $preview['note']) ?></p>
      <?php if (($preview['kdv_payload']['domain'] ?? '') !== '') : ?>
        <p>Domain / Subdomain: <strong><?= ShopView::escape((string) $preview['kdv_payload']['domain']) ?></strong></p>
      <?php else : ?>
        <p>Keine Domain angegeben – wir richten bei Bedarf eine Subdomain auf unserem Server ein.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (is_array($domainCheck) && !empty($domainCheck['message']) && empty($domainCheck['blocks'])) : ?>
    <div class="shop-alert shop-alert--ok" role="status">
      <p><?= ShopView::escape((string) $domainCheck['message']) ?></p>
    </div>
  <?php endif; ?>

  <form class="shop-form" method="post" action="/checkout" novalidate>
    <input type="hidden" name="plan" value="<?= ShopView::escape((string) $form['plan']) ?>">

    <fieldset>
      <legend>Laufzeit <span class="shop-req">*</span></legend>
      <label class="shop-radio">
        <input type="radio" name="billing_cycle" value="monatlich"<?= !$isYearly ? ' checked' : '' ?>>
        Monatlich — <?= ShopView::escape(ShopPlans::formatMoney((float) $plan['monthly_net'])) ?>/Monat netto
      </label>
      <label class="shop-radio">
        <input type="radio" name="billing_cycle" value="jaehrlich"<?= $isYearly ? ' checked' : '' ?>>
        Jährlich — <?= ShopView::escape(ShopPlans::formatMoney((float) $plan['yearly_net'])) ?>/Jahr netto
        <span class="shop-badge">1 Monat gratis</span>
      </label>
    </fieldset>

    <label>
      <span>Firmenname <span class="shop-req">*</span></span>
      <input type="text" name="company_name" required value="<?= ShopView::escape($form['company_name']) ?>" autocomplete="organization">
    </label>

    <div class="shop-field-block">
      <label>
        <span>Wunsch-Domain <span class="shop-optional">(optional)</span></span>
        <input type="text" name="domain" placeholder="leer lassen, oder z. B. crm.meine-firma.de" value="<?= ShopView::escape($domainValue) ?>" autocomplete="off" autocapitalize="off" spellcheck="false">
      </label>
      <div class="shop-help">
        <p><strong>Wann ausfüllen – und wann nicht?</strong></p>
        <ul>
          <li><strong>Feld leer lassen:</strong> Wenn Ihr CRM bei uns auf unserem Server läuft und Sie (noch) keine eigene Domain dafür brauchen. Wir können dann eine Adresse bei uns einrichten.</li>
          <li><strong>Nur eine Subdomain eintragen</strong> (Beispiel: <code>crm.ihre-firma.de</code>): Wenn Sie schon eine Domain haben, die online bleibt wie bisher, und das CRM nur unter einer Unteradresse laufen soll. Dann tragen Sie genau diese Subdomain ein – nicht die Hauptseite.</li>
          <li><strong>Vollständige Wunsch-Domain eintragen</strong> (Beispiel: <code>meine-firma.de</code>): Wenn Sie mit DG CRM auch Ihre Website bei uns erstellen und betreiben möchten und diese Adresse dafür nutzen wollen.</li>
        </ul>
        <p><strong>Schreibweisen:</strong> <code>firma.de</code>, <code>https://firma.de</code> oder <code>crm.firma.de</code> – alles ist in Ordnung. Wir speichern nur die reine Adresse ohne http/https.</p>
        <p>Nach dem Klick auf „Angaben prüfen“ prüfen wir die Domain sofort. Ist sie bereits vergeben, kommen Sie nicht weiter und erhalten einen Hinweis, eine andere Domain (oder Subdomain) einzutragen.</p>
      </div>
    </div>

    <label>
      <span>Ansprechpartner <span class="shop-req">*</span></span>
      <input type="text" name="contact_name" required value="<?= ShopView::escape($form['contact_name']) ?>" autocomplete="name">
    </label>
    <label>
      <span>E-Mail <span class="shop-req">*</span></span>
      <input type="email" name="contact_email" required value="<?= ShopView::escape($form['contact_email']) ?>" autocomplete="email">
    </label>
    <label>
      <span>Telefon <span class="shop-optional">(optional)</span></span>
      <input type="tel" name="contact_phone" value="<?= ShopView::escape($form['contact_phone']) ?>" autocomplete="tel">
    </label>

    <label class="shop-check">
      <input type="checkbox" name="privacy" value="1" required<?= ($form['privacy'] ?? '') === '1' ? ' checked' : '' ?>>
      <span>Ich habe die <a href="/datenschutz" target="_blank" rel="noopener">Datenschutzerklärung</a> gelesen und bin damit einverstanden. <span class="shop-req">*</span></span>
    </label>

    <p class="shop-vat"><?= ShopView::escape(ShopPlans::vatNote()) ?> Die Bezahlung mit Stripe folgt in einem nächsten Schritt.</p>

    <div class="shop-form__actions">
      <button type="submit" class="shop-btn shop-btn--primary">Angaben prüfen</button>
      <a class="shop-btn shop-btn--ghost" href="/preise">Zurück zu den Preisen</a>
    </div>
  </form>
</section>
