<?php
/** @var array<string, mixed> $legal */
$legal = $legal ?? [];
$email = (string) ($legal['email'] ?? ShopApp::config('contact_email'));
$company = (string) ($legal['company_name'] ?? '');
$addressLines = [];
if (trim((string) ($legal['street'] ?? '')) !== '') {
    $addressLines[] = (string) $legal['street'];
}
$cityLine = trim(($legal['postal'] ?? '') . ' ' . ($legal['city'] ?? ''));
if ($cityLine !== '') {
    $addressLines[] = $cityLine;
}
if (trim((string) ($legal['country'] ?? '')) !== '') {
    $addressLines[] = (string) $legal['country'];
}
?>
<section class="shop-section shop-section--tight shop-legal">
  <h1>Widerrufsbelehrung</h1>
  <p class="shop-lead">für Verbraucher beim Fernabsatz über shop.ganz-soft.de</p>
  <p class="shop-vat">Stand: <?= ShopView::escape(date('d.m.Y')) ?>. Musterorientiert — bitte juristisch prüfen.</p>

  <h2>Widerrufsrecht</h2>
  <p>Sie haben das Recht, binnen vierzehn Tagen ohne Angabe von Gründen diesen Vertrag zu widerrufen.</p>
  <p>Die Widerrufsfrist beträgt vierzehn Tage ab dem Tag des Vertragsabschlusses.</p>
  <p>Um Ihr Widerrufsrecht auszuüben, müssen Sie uns
    (<?= ShopView::escape($company) ?><?php if ($addressLines !== []) : ?>,
      <?= ShopView::escape(implode(', ', $addressLines)) ?><?php endif; ?>,
      E-Mail: <a href="mailto:<?= ShopView::escape($email) ?>"><?= ShopView::escape($email) ?></a>)
    mittels einer eindeutigen Erklärung (z. B. per E-Mail) über Ihren Entschluss, diesen Vertrag zu widerrufen, informieren.
    Sie können dafür das untenstehende Muster-Widerrufsformular verwenden, das jedoch nicht vorgeschrieben ist.</p>
  <p>Zur Wahrung der Widerrufsfrist reicht es aus, dass Sie die Mitteilung über die Ausübung des Widerrufsrechts vor Ablauf der Widerrufsfrist absenden.</p>

  <h2>Folgen des Widerrufs</h2>
  <p>Wenn Sie diesen Vertrag widerrufen, haben wir Ihnen alle Zahlungen, die wir von Ihnen erhalten haben, unverzüglich und spätestens binnen vierzehn Tagen ab dem Tag zurückzuzahlen, an dem die Mitteilung über Ihren Widerruf dieses Vertrags bei uns eingegangen ist.
    Für diese Rückzahlung verwenden wir dasselbe Zahlungsmittel, das Sie bei der ursprünglichen Transaktion eingesetzt haben, es sei denn, mit Ihnen wurde ausdrücklich etwas anderes vereinbart; in keinem Fall werden Ihnen wegen dieser Rückzahlung Entgelte berechnet.</p>

  <h2>Besondere Hinweise zu digitalen Inhalten / SaaS</h2>
  <p>Das Widerrufsrecht erlischt bei einem Vertrag über die Lieferung von nicht auf einem körperlichen Datenträger bereitgestellten digitalen Inhalten bzw. bei Dienstleistungen vorzeitig, wenn der Unternehmer mit der Ausführung des Vertrags begonnen hat, nachdem der Verbraucher
    ausdrücklich zugestimmt hat, dass der Unternehmer mit der Ausführung des Vertrags vor Ablauf der Widerrufsfrist beginnt, und seine Kenntnis davon bestätigt hat, dass er durch seine Zustimmung mit Beginn der Ausführung des Vertrags sein Widerrufsrecht verliert
    (vgl. § 356 Abs. 5 BGB bzw. § 356 Abs. 4 BGB je nach Vertragsart).</p>
  <p>Im Bestellprozess weisen wir Sie darauf hin und holen die erforderliche Zustimmung gesondert ein, sobald die Zahlung freigeschaltet ist.</p>

  <h2>Muster-Widerrufsformular</h2>
  <p>(Wenn Sie den Vertrag widerrufen wollen, dann füllen Sie bitte dieses Formular aus und senden Sie es zurück.)</p>
  <div class="shop-legal-box">
    <p>An <?= ShopView::escape($company) ?>,
      E-Mail: <?= ShopView::escape($email) ?></p>
    <p>Hiermit widerrufe(n) ich/wir (*) den von mir/uns (*) abgeschlossenen Vertrag über den Kauf der folgenden Waren (*)/die Erbringung der folgenden Dienstleistung (*)</p>
    <p>Bestellt am (*)/erhalten am (*)</p>
    <p>Name des/der Verbraucher(s)</p>
    <p>Anschrift des/der Verbraucher(s)</p>
    <p>Unterschrift des/der Verbraucher(s) (nur bei Mitteilung auf Papier)</p>
    <p>Datum</p>
    <p>(*) Unzutreffendes streichen.</p>
  </div>

  <p><a href="/agb">AGB</a> · <a href="/impressum">Impressum</a> · <a href="/datenschutz">Datenschutz</a></p>
</section>
