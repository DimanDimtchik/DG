<?php
/** @var array<string, mixed> $legal */
$legal = $legal ?? [];
$email = (string) ($legal['email'] ?? ShopApp::config('contact_email'));
?>
<section class="shop-section shop-section--tight shop-legal">
  <h1>Datenschutzerklärung</h1>
  <p class="shop-lead">Stand: <?= ShopView::escape(date('d.m.Y')) ?></p>

  <h2>1. Verantwortlicher</h2>
  <p>
    <?= ShopView::escape((string) ($legal['company_name'] ?? '')) ?><br>
    <?php if (trim((string) ($legal['street'] ?? '')) !== '') : ?>
      <?= ShopView::escape((string) $legal['street']) ?>,
      <?= ShopView::escape(trim(($legal['postal'] ?? '') . ' ' . ($legal['city'] ?? ''))) ?><br>
    <?php endif; ?>
    E-Mail: <a href="mailto:<?= ShopView::escape($email) ?>"><?= ShopView::escape($email) ?></a>
  </p>

  <h2>2. Welche Daten wir verarbeiten</h2>
  <p>Wenn Sie über diesen Shop ein DG-CRM-Paket bestellen, verarbeiten wir die von Ihnen eingegebenen Daten:</p>
  <ul>
    <li>Firmenname, Wunsch-Domain</li>
    <li>Name, E-Mail und ggf. Telefon des Ansprechpartners</li>
    <li>gewählter Tarif und Abrechnungszeitraum</li>
    <li>technische Protokolldaten (z. B. Zeitpunkt des Zugriffs) in dem für den Betrieb nötigen Umfang</li>
  </ul>
  <p>Die Zahlung erfolgt später über Stripe; Zahlungsdaten werden dann beim Zahlungsdienstleister verarbeitet.</p>

  <h2>3. Zwecke und Rechtsgrundlagen</h2>
  <ul>
    <li><strong>Vertragsanbahnung / Vertrag</strong> (Art. 6 Abs. 1 lit. b DSGVO): Bestellung, Einrichtung Ihres CRM, Support</li>
    <li><strong>Berechtigtes Interesse / Einwilligung</strong> (Art. 6 Abs. 1 lit. f bzw. a): technisch notwendige Cookies; Statistik nur mit Einwilligung</li>
    <li><strong>Rechtliche Verpflichtungen</strong> (Art. 6 Abs. 1 lit. c): z. B. steuerliche Aufbewahrung</li>
  </ul>

  <h2>4. Cookies</h2>
  <p>Technisch notwendige Cookies (z. B. Sitzung, Cookie-Einwilligung) sind für den Shop erforderlich.
    Optionale Statistik-Cookies setzen wir nur, wenn Sie im Cookie-Banner zustimmen. Sie können Ihre Wahl jederzeit ändern, indem Sie die Cookies in Ihrem Browser löschen und die Seite neu laden.</p>

  <h2>5. Weitergabe</h2>
  <p>Zur Einrichtung Ihres CRM können Bestelldaten an unsere interne Kundenverwaltung (KDV) und an unseren Hosting-Partner (All-Inkl) übermittelt werden – nur soweit für die Leistung nötig.
    Eine Weitergabe zu Werbezwecken an Dritte findet nicht statt.</p>

  <h2>6. Speicherdauer</h2>
  <p>Bestell- und Vertragsdaten speichern wir für die Dauer der Geschäftsbeziehung und den gesetzlichen Aufbewahrungsfristen (häufig 6–10 Jahre im Steuer-/Handelsrecht).
    Cookie-Einwilligungen speichern wir etwa ein Jahr.</p>

  <h2>7. Ihre Rechte</h2>
  <p>Sie haben Rechte auf Auskunft, Berichtigung, Löschung, Einschränkung, Datenübertragbarkeit und Widerspruch sowie das Recht, eine Einwilligung zu widerrufen.
    Außerdem können Sie sich bei einer Datenschutzaufsichtsbehörde beschweren.</p>

  <h2>8. Kontakt</h2>
  <p>Fragen zum Datenschutz: <a href="mailto:<?= ShopView::escape($email) ?>"><?= ShopView::escape($email) ?></a></p>
</section>
