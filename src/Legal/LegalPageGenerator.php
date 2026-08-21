<?php
declare(strict_types=1);

/**
 * Generates legally required pages (Impressum, AGB, Datenschutzerklärung)
 * based on company settings, legal form, and business type.
 *
 * German law references:
 * - Impressum: § 5 TMG / § 18 MStV
 * - Datenschutz: Art. 13/14 DSGVO
 * - AGB: §§ 305–310 BGB
 * - Widerruf: § 312g BGB, Art. 246a § 1 EGBGB
 */
final class LegalPageGenerator
{
    // ── Public API ──────────────────────────────────────────────────

    /**
     * Generiert das Impressum als HTML.
     *
     * @return string HTML fragment.
     */
    public static function impressum(): string
    {
        $c = self::companyData();
        $ext = self::extendedData();
        $html = '<h1>Impressum</h1>';
        $html .= '<p>Angaben gemäß § 5 TMG / § 18 MStV</p>';

        // Company name and address
        $html .= '<h2>Anbieter</h2>';
        $html .= '<p>';
        $html .= self::esc($ext['legal_name'] ?: $c['name']);
        if ($ext['company_type'] !== '') {
            $types = CompanyTypes::labels();
            $html .= '<br>' . self::esc($types[$ext['company_type']] ?? $ext['company_type']);
        }
        $html .= '<br>' . self::esc($c['street']);
        $html .= '<br>' . self::esc($c['postal']) . ' ' . self::esc($c['city']);
        if (($c['country'] ?? 'DE') !== 'DE') {
            $html .= '<br>' . self::esc($c['country']);
        }
        $html .= '</p>';

        // Representatives
        $owners = $ext['owners'] ?? [];
        if (!empty($owners)) {
            $label = self::representativeLabel($ext['company_type']);
            $html .= '<h2>' . self::esc($label) . '</h2><p>';
            foreach ($owners as $o) {
                $name = trim((string) ($o['name'] ?? ''));
                if ($name === '') continue;
                $html .= self::esc($name);
                $role = trim((string) ($o['share_percent'] ?? $o['role'] ?? ''));
                if ($role !== '') $html .= ' (' . self::esc($role) . ')';
                $html .= '<br>';
            }
            $html .= '</p>';
        }

        // Contact
        $html .= '<h2>Kontakt</h2><p>';
        if ($c['phone'] !== '') $html .= 'Telefon: ' . self::esc($c['phone']) . '<br>';
        $html .= 'E-Mail: ' . self::esc($c['email']);
        if ($c['website'] !== '') $html .= '<br>Website: ' . self::esc($c['website']);
        $html .= '</p>';

        // Tax info
        $taxNumbers = $ext['tax_numbers'] ?? [];
        $taxNumber = trim((string) ($taxNumbers['est'] ?? $c['tax_number'] ?? ''));
        $vatId = trim((string) ($taxNumbers['ust'] ?? $c['vat_id'] ?? ''));
        if ($taxNumber !== '' || $vatId !== '') {
            $html .= '<h2>Steuerliche Angaben</h2><p>';
            if ($taxNumber !== '') $html .= 'Steuernummer: ' . self::esc($taxNumber) . '<br>';
            if ($vatId !== '') $html .= 'USt-IdNr.: ' . self::esc($vatId);
            $html .= '</p>';
        }

        // Trade register
        $reg = $ext['trade_register'] ?? [];
        $court = trim((string) ($reg['court'] ?? ''));
        $number = trim((string) ($reg['number'] ?? ''));
        if ($court !== '' || $number !== '') {
            $html .= '<h2>Registereintrag</h2><p>';
            if ($court !== '') $html .= 'Registergericht: ' . self::esc($court) . '<br>';
            if ($number !== '') $html .= 'Registernummer: ' . self::esc($number);
            $html .= '</p>';
        }

        // Professional chambers / supervisory authority
        $chambers = $ext['professional_chambers'] ?? [];
        $needsAuthority = in_array($ext['company_type'], ['praxis', 'kanzlei', 'freiberufler'], true);
        if (!empty($chambers) || $needsAuthority) {
            $html .= '<h2>Berufsrechtliche Angaben</h2>';
            foreach ($chambers as $ch) {
                $name = trim((string) ($ch['name'] ?? ''));
                if ($name === '') continue;
                $html .= '<p>';
                $html .= 'Zuständige Kammer: ' . self::esc($name);
                $memberNo = trim((string) ($ch['member_no'] ?? ''));
                if ($memberNo !== '') $html .= '<br>Mitgliedsnummer: ' . self::esc($memberNo);
                $html .= '</p>';
            }

            $jobTitle = trim((string) ($ext['job_title'] ?? ''));
            $jobCountry = trim((string) ($ext['job_title_country'] ?? ''));
            if ($jobTitle !== '') {
                $html .= '<p>Berufsbezeichnung: ' . self::esc($jobTitle);
                if ($jobCountry !== '') $html .= '<br>Verliehen in: ' . self::esc($jobCountry);
                $html .= '</p>';
            }

            $chamberUrl = trim((string) ($ext['chamber_url'] ?? ''));
            if ($chamberUrl !== '') {
                $html .= '<p>Berufsrechtliche Regelungen: <a href="' . self::esc($chamberUrl) . '" target="_blank">' . self::esc($chamberUrl) . '</a></p>';
            }
        }

        // Dispute resolution (EU ODR)
        $html .= '<h2>Streitschlichtung</h2>';
        $html .= '<p>Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit: '
            . '<a href="https://ec.europa.eu/consumers/odr/" target="_blank" rel="noopener">https://ec.europa.eu/consumers/odr/</a></p>';
        $html .= '<p>Wir sind nicht bereit oder verpflichtet, an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.</p>';

        // Liability disclaimer
        $html .= '<h2>Haftung für Inhalte</h2>';
        $html .= '<p>Als Diensteanbieter sind wir gemäß § 7 Abs.1 TMG für eigene Inhalte auf diesen Seiten nach den allgemeinen Gesetzen verantwortlich. '
            . 'Nach §§ 8 bis 10 TMG sind wir als Diensteanbieter jedoch nicht verpflichtet, übermittelte oder gespeicherte fremde Informationen zu überwachen.</p>';

        $html .= '<h2>Haftung für Links</h2>';
        $html .= '<p>Unser Angebot enthält Links zu externen Websites Dritter, auf deren Inhalte wir keinen Einfluss haben. '
            . 'Für die Inhalte der verlinkten Seiten ist stets der jeweilige Anbieter oder Betreiber verantwortlich.</p>';

        $html .= '<h2>Urheberrecht</h2>';
        $html .= '<p>Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen dem deutschen Urheberrecht. '
            . 'Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art der Verwertung außerhalb der Grenzen des Urheberrechtes bedürfen der schriftlichen Zustimmung.</p>';

        return $html;
    }

    /**
     * Generiert die Datenschutzerklärung als HTML.
     *
     * @return string HTML fragment.
     */
    public static function datenschutz(): string
    {
        $c = self::companyData();
        $ext = self::extendedData();
        $kinds = self::businessKinds();

        $html = '<h1>Datenschutzerklärung</h1>';

        // Responsible party
        $html .= '<h2>1. Verantwortlicher</h2>';
        $html .= '<p>' . self::esc($ext['legal_name'] ?: $c['name']);
        $html .= '<br>' . self::esc($c['street']);
        $html .= '<br>' . self::esc($c['postal']) . ' ' . self::esc($c['city']);
        $html .= '<br>E-Mail: ' . self::esc($c['email']);
        if ($c['phone'] !== '') $html .= '<br>Telefon: ' . self::esc($c['phone']);
        $html .= '</p>';

        // Data protection officer
        $html .= '<h2>2. Datenschutzbeauftragter</h2>';
        $html .= '<p>Sofern gesetzlich vorgeschrieben (ab 20 Mitarbeitern, die regelmäßig personenbezogene Daten verarbeiten), '
            . 'haben wir einen Datenschutzbeauftragten bestellt. Kontakt über die oben genannte Adresse.</p>';

        // Rights
        $html .= '<h2>3. Ihre Rechte</h2>';
        $html .= '<p>Sie haben folgende Rechte bezüglich Ihrer personenbezogenen Daten:</p>';
        $html .= '<ul>';
        $html .= '<li>Recht auf Auskunft (Art. 15 DSGVO)</li>';
        $html .= '<li>Recht auf Berichtigung (Art. 16 DSGVO)</li>';
        $html .= '<li>Recht auf Löschung (Art. 17 DSGVO)</li>';
        $html .= '<li>Recht auf Einschränkung der Verarbeitung (Art. 18 DSGVO)</li>';
        $html .= '<li>Recht auf Datenübertragbarkeit (Art. 20 DSGVO)</li>';
        $html .= '<li>Widerspruchsrecht (Art. 21 DSGVO)</li>';
        $html .= '<li>Recht auf Beschwerde bei einer Aufsichtsbehörde (Art. 77 DSGVO)</li>';
        $html .= '</ul>';

        // Website visit
        $html .= '<h2>4. Datenerfassung auf dieser Website</h2>';
        $html .= '<h3>Server-Log-Dateien</h3>';
        $html .= '<p>Der Provider der Seiten erhebt und speichert automatisch Informationen in Server-Log-Dateien: '
            . 'Browsertyp und -version, verwendetes Betriebssystem, Referrer URL, Hostname des zugreifenden Rechners, '
            . 'Uhrzeit der Serveranfrage, IP-Adresse. Diese Daten werden nicht mit anderen Datenquellen zusammengeführt. '
            . 'Grundlage ist Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse).</p>';

        // Cookies
        $html .= '<h3>Cookies</h3>';
        $html .= '<p>Diese Website verwendet Cookies. Technisch notwendige Cookies werden auf Grundlage von Art. 6 Abs. 1 lit. f DSGVO gesetzt. '
            . 'Alle anderen Cookies werden nur mit Ihrer ausdrücklichen Einwilligung gemäß Art. 6 Abs. 1 lit. a DSGVO / § 25 TDDDG gesetzt. '
            . 'Details finden Sie in unserer Cookie-Richtlinie.</p>';

        // Contact forms
        $html .= '<h3>Kontaktformular</h3>';
        $html .= '<p>Wenn Sie uns per Kontaktformular Anfragen zukommen lassen, werden Ihre Angaben zur Bearbeitung der Anfrage '
            . 'und für den Fall von Anschlussfragen bei uns gespeichert. Diese Daten geben wir nicht ohne Ihre Einwilligung weiter. '
            . 'Grundlage ist Art. 6 Abs. 1 lit. b DSGVO (Vertragserfüllung) bzw. Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse).</p>';

        // Online shop / payments
        if (self::hasBusinessKind($kinds, ['products', 'both'])) {
            $html .= '<h2>5. Online-Shop / Bestellungen</h2>';
            $html .= '<p>Bei Bestellungen erheben wir folgende Daten: Name, Anschrift, E-Mail-Adresse, Telefonnummer, '
                . 'Zahlungsdaten. Diese Daten sind zur Vertragserfüllung erforderlich (Art. 6 Abs. 1 lit. b DSGVO). '
                . 'Wir speichern diese Daten bis zum Ablauf der gesetzlichen Aufbewahrungsfristen (Handels- und Steuerrecht: 6–10 Jahre).</p>';

            $html .= '<h3>Zahlungsdienstleister</h3>';
            $html .= '<p>Wir setzen ggf. externe Zahlungsdienstleister ein. Ihre Zahlungsdaten werden direkt an den '
                . 'jeweiligen Anbieter übermittelt. Bitte beachten Sie die Datenschutzerklärung des jeweiligen Anbieters.</p>';
        }

        // Services / appointments
        if (self::hasBusinessKind($kinds, ['services', 'both', 'medical', 'consulting', 'law'])) {
            $sectionNr = self::hasBusinessKind($kinds, ['products', 'both']) ? '6' : '5';
            $html .= '<h2>' . $sectionNr . '. Dienstleistungen / Termine</h2>';
            $html .= '<p>Für die Erbringung unserer Dienstleistungen verarbeiten wir die zur Vertragserfüllung '
                . 'erforderlichen personenbezogenen Daten (Name, Kontaktdaten, ggf. weitere vertragsbezogene Angaben). '
                . 'Grundlage ist Art. 6 Abs. 1 lit. b DSGVO.</p>';
        }

        // Medical
        if (self::hasBusinessKind($kinds, ['medical'])) {
            $html .= '<h3>Gesundheitsdaten</h3>';
            $html .= '<p>Als Praxis verarbeiten wir besondere Kategorien personenbezogener Daten (Gesundheitsdaten) gemäß Art. 9 Abs. 2 lit. h DSGVO '
                . 'in Verbindung mit § 22 Abs. 1 Nr. 1 lit. b BDSG. Diese Daten unterliegen der ärztlichen Schweigepflicht '
                . 'und werden nur im Rahmen der Behandlung verarbeitet.</p>';
        }

        // Law / Tax
        if (self::hasBusinessKind($kinds, ['law'])) {
            $html .= '<h3>Mandantendaten</h3>';
            $html .= '<p>Im Rahmen der Mandatsbearbeitung verarbeiten wir die zur Rechtsberatung oder Steuerberatung '
                . 'erforderlichen Daten. Es gilt die berufsrechtliche Verschwiegenheitspflicht. '
                . 'Grundlage ist Art. 6 Abs. 1 lit. b DSGVO (Vertragserfüllung) und Art. 6 Abs. 1 lit. c DSGVO (rechtliche Verpflichtung).</p>';
        }

        // Association
        if (self::hasBusinessKind($kinds, ['association'])) {
            $html .= '<h2>Mitgliederverwaltung</h2>';
            $html .= '<p>Wir verarbeiten personenbezogene Daten unserer Mitglieder (Name, Anschrift, E-Mail, Bankverbindung für Beiträge) '
                . 'zur Erfüllung unserer satzungsgemäßen Aufgaben. Grundlage ist Art. 6 Abs. 1 lit. b DSGVO (Mitgliedschaft als Vertrag).</p>';
        }

        // SSL
        $html .= '<h2>SSL-Verschlüsselung</h2>';
        $html .= '<p>Diese Seite nutzt aus Sicherheitsgründen eine SSL-Verschlüsselung. Eine verschlüsselte Verbindung '
            . 'erkennen Sie daran, dass die Adresszeile des Browsers von „http://" auf „https://" wechselt.</p>';

        // Third parties
        $html .= '<h2>Auftragsverarbeitung</h2>';
        $html .= '<p>Wir haben mit unseren Dienstleistern (Hosting, E-Mail, ggf. Zahlungsanbieter) '
            . 'Auftragsverarbeitungsverträge gemäß Art. 28 DSGVO geschlossen.</p>';

        // Retention
        $html .= '<h2>Speicherdauer</h2>';
        $html .= '<p>Personenbezogene Daten werden gelöscht, sobald der Zweck der Speicherung entfällt und keine gesetzlichen '
            . 'Aufbewahrungsfristen entgegenstehen (HGB: 6 Jahre, AO: 10 Jahre).</p>';

        return $html;
    }

    /**
     * Generiert die AGB als HTML.
     *
     * @return string HTML fragment.
     */
    public static function agb(): string
    {
        $c = self::companyData();
        $ext = self::extendedData();
        $kinds = self::businessKinds();

        $html = '<h1>Allgemeine Geschäftsbedingungen</h1>';
        $html .= '<p>Stand: ' . date('d.m.Y') . '</p>';

        // §1 Scope
        $html .= '<h2>§ 1 Geltungsbereich</h2>';
        $html .= '<p>Diese Allgemeinen Geschäftsbedingungen (AGB) gelten für alle Geschäftsbeziehungen zwischen '
            . self::esc($ext['legal_name'] ?: $c['name']) . ' (nachfolgend „Anbieter") und dem Kunden. '
            . 'Es gelten ausschließlich diese AGB; entgegenstehende Bedingungen des Kunden werden nicht anerkannt.</p>';

        // §2 Contract conclusion
        $html .= '<h2>§ 2 Vertragsschluss</h2>';
        if (self::hasBusinessKind($kinds, ['products', 'both'])) {
            $html .= '<p>Die Darstellung von Produkten im Online-Shop stellt kein verbindliches Angebot dar, '
                . 'sondern eine Aufforderung zur Abgabe einer Bestellung. Mit dem Absenden der Bestellung gibt der Kunde '
                . 'ein verbindliches Angebot ab. Der Vertrag kommt zustande, wenn der Anbieter die Bestellung bestätigt.</p>';
        } else {
            $html .= '<p>Angebote des Anbieters sind freibleibend. Der Vertrag kommt durch schriftliche Auftragsbestätigung '
                . 'oder durch Beginn der Leistungserbringung zustande.</p>';
        }

        // §3 Prices
        $html .= '<h2>§ 3 Preise und Zahlung</h2>';
        $html .= '<p>Alle Preise verstehen sich ';
        $vatId = trim((string) ($ext['tax_numbers']['ust'] ?? $c['vat_id'] ?? ''));
        if ($vatId !== '') {
            $html .= 'inklusive der gesetzlichen Mehrwertsteuer.';
        } else {
            $html .= 'als Endpreise. Sofern Umsatzsteuer anfällt, ist diese im Preis enthalten.';
        }
        $html .= ' Zahlungen sind, sofern nicht anders vereinbart, sofort nach Rechnungsstellung fällig.</p>';

        // §4 Delivery (products)
        if (self::hasBusinessKind($kinds, ['products', 'both'])) {
            $html .= '<h2>§ 4 Lieferung und Versand</h2>';
            $html .= '<p>Die Lieferung erfolgt an die vom Kunden angegebene Lieferadresse. '
                . 'Lieferzeiten sind unverbindlich, sofern nicht ausdrücklich als verbindlich zugesagt. '
                . 'Teillieferungen sind zulässig, soweit dem Kunden zumutbar.</p>';

            $html .= '<h2>§ 5 Eigentumsvorbehalt</h2>';
            $html .= '<p>Die gelieferte Ware bleibt bis zur vollständigen Bezahlung Eigentum des Anbieters.</p>';
        }

        // Widerrufsbelehrung (B2C, products/services)
        if (self::hasBusinessKind($kinds, ['products', 'both', 'services'])) {
            $section = self::hasBusinessKind($kinds, ['products', 'both']) ? '§ 6' : '§ 4';
            $html .= '<h2>' . $section . ' Widerrufsrecht</h2>';
            $html .= '<p><strong>Widerrufsbelehrung</strong></p>';
            $html .= '<p>Verbraucher haben ein vierzehntägiges Widerrufsrecht.</p>';
            $html .= '<h3>Widerrufsrecht</h3>';
            $html .= '<p>Sie haben das Recht, binnen vierzehn Tagen ohne Angabe von Gründen diesen Vertrag zu widerrufen. '
                . 'Die Widerrufsfrist beträgt vierzehn Tage ab ';

            if (self::hasBusinessKind($kinds, ['products', 'both'])) {
                $html .= 'dem Tag, an dem Sie oder ein von Ihnen benannter Dritter die Waren in Besitz genommen haben.';
            } else {
                $html .= 'dem Tag des Vertragsabschlusses.';
            }
            $html .= '</p>';

            $html .= '<p>Um Ihr Widerrufsrecht auszuüben, müssen Sie uns ('
                . self::esc($c['name']) . ', ' . self::esc($c['street']) . ', '
                . self::esc($c['postal']) . ' ' . self::esc($c['city']) . ', '
                . 'E-Mail: ' . self::esc($c['email'])
                . ') mittels einer eindeutigen Erklärung (z.B. ein mit der Post versandter Brief oder E-Mail) '
                . 'über Ihren Entschluss, diesen Vertrag zu widerrufen, informieren.</p>';

            $html .= '<h3>Folgen des Widerrufs</h3>';
            $html .= '<p>Wenn Sie diesen Vertrag widerrufen, haben wir Ihnen alle Zahlungen, die wir von Ihnen erhalten haben, '
                . 'unverzüglich und spätestens binnen vierzehn Tagen ab dem Tag zurückzuzahlen, an dem die Mitteilung über '
                . 'Ihren Widerruf bei uns eingegangen ist.</p>';
        }

        // Gewährleistung
        $html .= '<h2>Gewährleistung</h2>';
        if (self::hasBusinessKind($kinds, ['products', 'both'])) {
            $html .= '<p>Es gelten die gesetzlichen Gewährleistungsrechte. Bei gebrauchten Waren wird die Gewährleistungsfrist '
                . 'auf ein Jahr verkürzt, sofern der Kunde Unternehmer ist.</p>';
        } else {
            $html .= '<p>Es gelten die gesetzlichen Gewährleistungsrechte.</p>';
        }

        // Haftung
        $html .= '<h2>Haftung</h2>';
        $html .= '<p>Der Anbieter haftet unbeschränkt bei Vorsatz und grober Fahrlässigkeit. Bei leichter Fahrlässigkeit '
            . 'haftet der Anbieter nur bei Verletzung wesentlicher Vertragspflichten (Kardinalpflichten) und begrenzt auf den '
            . 'vertragstypisch vorhersehbaren Schaden. Die Haftung für Schäden aus der Verletzung des Lebens, des Körpers '
            . 'oder der Gesundheit bleibt unberührt.</p>';

        // Datenschutz reference
        $html .= '<h2>Datenschutz</h2>';
        $html .= '<p>Informationen zur Verarbeitung personenbezogener Daten finden Sie in unserer <a href="/datenschutz">Datenschutzerklärung</a>.</p>';

        // Dispute resolution
        $html .= '<h2>Streitbeilegung</h2>';
        $html .= '<p>Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung bereit: '
            . '<a href="https://ec.europa.eu/consumers/odr/" target="_blank" rel="noopener">https://ec.europa.eu/consumers/odr/</a></p>';

        // Applicable law
        $html .= '<h2>Schlussbestimmungen</h2>';
        $html .= '<p>Es gilt das Recht der Bundesrepublik Deutschland. Gerichtsstand ist, soweit gesetzlich zulässig, '
            . 'der Sitz des Anbieters.</p>';

        return $html;
    }

    /**
     * Generates all legal pages and saves them as published website pages.
     *
     * @return list<array{slug: string, title: string, id: int, action: string}>
     */
    public static function generateAndSave(?int $userId = null, bool $overwrite = true): array
    {
        $definitions = [
            ['slug' => 'impressum', 'title' => 'Impressum', 'content' => self::impressum()],
            ['slug' => 'datenschutz', 'title' => 'Datenschutzerklärung', 'content' => self::datenschutz()],
            ['slug' => 'agb', 'title' => 'Allgemeine Geschäftsbedingungen', 'content' => self::agb()],
        ];

        $saved = [];
        foreach ($definitions as $page) {
            $saved[] = WebsitePageRepository::upsertHtmlPage(
                $page['slug'],
                $page['title'],
                $page['content'],
                $userId,
                $overwrite
            );
        }

        return $saved;
    }

    // ── Data helpers ────────────────────────────────────────────────

    /** @return array<string, string> */
    private static function companyData(): array
    {
        return CompanySettings::config();
    }

    /** @return array<string, mixed> */
    private static function extendedData(): array
    {
        return CompanyExtendedSettings::config();
    }

    /** @return list<string> */
    private static function businessKinds(): array
    {
        $kinds = SettingsStore::get('install_business_kind', []);
        return is_array($kinds) ? $kinds : [];
    }

    /** @param list<string> $kinds @param list<string> $check */
    private static function hasBusinessKind(array $kinds, array $check): bool
    {
        return !empty(array_intersect($kinds, $check));
    }

    /** Label für Vertretungsberechtigte je Rechtsform. */
    private static function representativeLabel(string $companyType): string
    {
        return match ($companyType) {
            'gmbh', 'gmbh_igr', 'ug', 'ug_igr', 'ggmbh' => 'Geschäftsführer',
            'ag' => 'Vorstand',
            'ev' => 'Vorstand',
            'stiftung' => 'Vorstand',
            'kg', 'gmbh_co_kg' => 'Komplementär',
            'ohg', 'gbr' => 'Gesellschafter',
            'partg' => 'Partner',
            'koerperschaft' => 'Vertretungsberechtigte',
            default => 'Inhaber / Vertretungsberechtigte',
        };
    }

    /** HTML-Escaping für generierte Rechtstexte. */
    private static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
