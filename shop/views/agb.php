<?php
/** @var array<string, mixed> $legal */
$legal = $legal ?? [];
$email = (string) ($legal['email'] ?? ShopApp::config('contact_email'));
$brand = (string) ($legal['brand'] ?? ShopApp::config('name'));
$company = (string) ($legal['company_name'] ?? '');
?>
<section class="shop-section shop-section--tight shop-legal">
  <h1>Allgemeine Geschäftsbedingungen (AGB)</h1>
  <p class="shop-lead">für den Erwerb und die Nutzung von <?= ShopView::escape($brand) ?> (SaaS) über shop.ganz-soft.de</p>
  <p class="shop-vat">Stand: <?= ShopView::escape(date('d.m.Y')) ?>. Entwurf zur rechtlichen Prüfung — kein Ersatz für anwaltliche Beratung.</p>

  <h2>§ 1 Anbieter und Geltungsbereich</h2>
  <p>(1) Anbieter ist <?= ShopView::escape($company) ?> (nachfolgend „Anbieter“), erreichbar unter
    <a href="mailto:<?= ShopView::escape($email) ?>"><?= ShopView::escape($email) ?></a>
    und den Angaben im <a href="/impressum">Impressum</a>.</p>
  <p>(2) Diese AGB gelten für Verträge über die Nutzung der Software <?= ShopView::escape($brand) ?> als Software-as-a-Service (Hosting, Updates, Support im vereinbarten Umfang) sowie zugehörige Leistungen (z. B. Domain, E-Mail, SSL), die über diesen Shop angeboten werden.</p>
  <p>(3) Entgegenstehende Bedingungen des Kunden gelten nur, wenn der Anbieter ihnen ausdrücklich schriftlich zustimmt.</p>

  <h2>§ 2 Vertragsschluss</h2>
  <p>(1) Die Darstellung der Tarife stellt kein verbindliches Angebot dar. Mit Absenden der Bestellung und Abschluss der Zahlung gibt der Kunde ein verbindliches Angebot ab.</p>
  <p>(2) Der Vertrag kommt zustande, wenn der Anbieter die Bestellung annimmt — insbesondere durch Zahlungsbestätigung und/oder Freischaltung bzw. Zusendung der Installations-/Zugangsdaten.</p>
  <p>(3) Der Vertragstext wird vom Anbieter gespeichert. Die wesentlichen Vertragsdaten erhält der Kunde in der Bestellbestätigung per E-Mail.</p>

  <h2>§ 3 Leistungsumfang</h2>
  <p>(1) Der konkrete Umfang ergibt sich aus dem gewählten Tarif (Starter / Business / Premium) und der Preisseite. Typischerweise enthalten: CRM-Instanz, Hosting, SSL, Backups, Updates und Support im tariflichen Rahmen; je nach Tarif Domain und E-Mail-Postfächer.</p>
  <p>(2) Der Anbieter ist berechtigt, die Software weiterzuentwickeln, Funktionen anzupassen oder aus Sicherheitsgründen vorübergehend einzuschränken, sofern der Vertragszweck erhalten bleibt.</p>
  <p>(3) Eine Verfügbarkeit von 100 % wird nicht geschuldet. Geplante Wartungen werden nach Möglichkeit angekündigt.</p>

  <h2>§ 4 Pflichten des Kunden</h2>
  <p>(1) Der Kunde stellt korrekte Firmendaten und eine erreichbare E-Mail-Adresse bereit und hält Zugangsdaten geheim.</p>
  <p>(2) Der Kunde darf die Leistung nicht missbräuchlich nutzen (rechtswidrige Inhalte, Spam, Angriffe auf Systeme Dritter).</p>
  <p>(3) Für Inhalte, die der Kunde in seiner CRM-/Website-Instanz speichert oder veröffentlicht, ist ausschließlich der Kunde verantwortlich. Ein Auftragsverarbeitungsvertrag (AVV) wird bei Bedarf gesondert geschlossen.</p>

  <h2>§ 5 Preise, Laufzeit, Kündigung</h2>
  <p>(1) Es gelten die zum Bestellzeitpunkt angegebenen Preise. Alle Preise verstehen sich zzgl. der gesetzlichen MwSt. (derzeit 19 % in Deutschland), sofern nicht anders ausgewiesen.</p>
  <p>(2) Abrechnung erfolgt monatlich oder jährlich (Jahrespreis = 11 × Monatspreis). Die Zahlung wird über den Zahlungsdienstleister Stripe abgewickelt.</p>
  <p>(3) Das Abo verlängert sich automatisch um die gewählte Periode, wenn es nicht fristgerecht gekündigt wird. Kündigung ist zum Ende der jeweiligen Laufzeit möglich; Details auch über das Stripe-Kundenportal, sobald freigeschaltet.</p>
  <p>(4) Bei Zahlungsverzug darf der Anbieter den Zugang nach Mahnung sperren.</p>

  <h2>§ 6 Widerrufsrecht</h2>
  <p>Verbrauchern steht ein gesetzliches Widerrufsrecht zu. Einzelheiten enthält die
    <a href="/widerruf">Widerrufsbelehrung</a>. Bei digitalen Leistungen / SaaS kann das Widerrufsrecht unter den gesetzlichen Voraussetzungen erlöschen, wenn die Ausführung mit ausdrücklicher Zustimmung des Verbrauchers vor Fristablauf begonnen hat.</p>

  <h2>§ 7 Haftung</h2>
  <p>(1) Der Anbieter haftet unbeschränkt bei Vorsatz und grober Fahrlässigkeit sowie bei Verletzung von Leben, Körper oder Gesundheit.</p>
  <p>(2) Bei leichter Fahrlässigkeit haftet der Anbieter nur bei Verletzung wesentlicher Vertragspflichten und begrenzt auf den vorhersehbaren, vertragstypischen Schaden.</p>
  <p>(3) Die Haftung für Datenverlust ist auf den typischen Wiederherstellungsaufwand bei üblicher Datensicherung des Kunden beschränkt, soweit gesetzlich zulässig.</p>

  <h2>§ 8 Schlussbestimmungen</h2>
  <p>(1) Es gilt das Recht der Bundesrepublik Deutschland unter Ausschluss des UN-Kaufrechts. Gegenüber Verbrauchern bleiben zwingende Schutzvorschriften des Wohnsitzstaates unberührt.</p>
  <p>(2) Gerichtsstand für Kaufleute ist der Sitz des Anbieters, soweit gesetzlich zulässig.</p>
  <p>(3) Sollten einzelne Bestimmungen unwirksam sein, bleibt der Vertrag im Übrigen wirksam.</p>

  <p>Kontakt: <a href="mailto:<?= ShopView::escape($email) ?>"><?= ShopView::escape($email) ?></a>
    · <a href="/impressum">Impressum</a>
    · <a href="/datenschutz">Datenschutz</a>
    · <a href="/widerruf">Widerruf</a></p>
</section>
