<?php
declare(strict_types=1);

/** Seed-Daten für Standard-Konten (SKR03). */
final class ChartAccountSeedData
{
        /**
     * account_number: string,
     * @param string $skrType Kontenrahmen (skr03/skr04)
     * @return list<array{
     */
    public static function accountsForSkr(string $skrType): array
    {
        $skrType = ChartOfAccountsSettings::sanitizeSkrType($skrType);

        return match ($skrType) {
            'skr04' => self::skr04Accounts(),
            default => self::skr03Accounts(),
        };
    }

    /**
     * seedCount
     * @param string $skrType Kontenrahmen (skr03/skr04)
     * @return int
     */
    public static function seedCount(string $skrType): int
    {
        return count(self::accountsForSkr($skrType));
    }

    /**
     * skr03Accounts.
     *
     * @return list<array{account_number: string, name: string, account_class: string, section: string, hints: array<string, mixed>}>
     */
        private static function skr03Accounts(): array
    {
        return [
            self::account('1000', 'Kasse', '0', 'aktiva', [
                'summary' => 'Bargeldbestand in der Kasse — liquide Mittel für kleine Barzahlungen.',
                'digit_explanations' => [
                    1 => 'Finanz- und Privatkonten (Klasse 1)',
                    2 => 'Kasse und Bank (Gruppe 00–19)',
                    3 => 'Kasse (Untergruppe 00)',
                    4 => 'Hauptkasse',
                ],
                'features' => ['keine_ust', 'bargeld'],
                'examples' => ['Barverkauf an Laufkunden', 'Porto in bar', 'Kleinbetragsrechnung bar'],
                'edge_cases' => ['Kassenfehlbetrag separat buchen', 'Privatentnahme nicht über Kasse'],
                'dependencies' => ['Tagesabschluss Kasse', 'Kassenbuch'],
                'classification' => ['balance_sheet' => true, 'guv' => false, 'eur' => true],
                'tax_effects' => ['ust' => 'neutral', 'gewst' => 'neutral', 'kst' => 'neutral', 'est' => 'neutral'],
            ]),
            self::account('0320', 'PKW', '0', 'aktiva', [
                'summary' => 'PKW und andere Fahrzeuge im Anlagevermögen (Anschaffung/ Herstellung).',
                'digit_explanations' => [
                    1 => 'Anlagevermögen (Klasse 0)',
                    2 => 'Bewegliche Wirtschaftsgüter',
                    3 => 'Fuhrpark / PKW',
                    4 => 'PKW',
                ],
                'features' => ['anlagevermoegen', 'fahrzeug', 'abschreibung'],
                'search_terms' => ['fahrzeug', 'pkw', 'auto', 'fuhrpark', 'kfz'],
                'examples' => ['Kauf Firmenwagen', 'Anzahlung Fahrzeug', 'Außerbetriebnahme und Verkauf'],
                'edge_cases' => ['1 %-Regelung vs. 50 %-Privatanteil', 'Leasing statt Kauf → Konto 4570'],
                'dependencies' => ['Anlagenbuchhaltung', 'AfA 6000'],
                'classification' => ['balance_sheet' => true, 'guv' => false, 'eur' => true],
                'tax_effects' => ['ust' => 'vorsteuer', 'gewst' => 'neutral', 'kst' => 'neutral', 'est' => 'neutral'],
            ]),
            self::account('1200', 'Bank', '0', 'aktiva', [
                'summary' => 'Girokonten und Bankguthaben des Unternehmens.',
                'digit_explanations' => [
                    1 => 'Finanz- und Privatkonten',
                    2 => 'Bankkonten (Gruppe 20)',
                    3 => 'Girokonto',
                    4 => 'Hauptbankkonto',
                ],
                'features' => ['keine_ust'],
                'examples' => ['Überweisungseingang von Kunden', 'Lastschrift an Lieferanten', 'Bankgebühren'],
                'edge_cases' => ['Fremdwährungskonto ggf. separates Unterkonto', 'PayPal als Bank oder Verrechnungskonto'],
                'dependencies' => ['Kontoauszug', 'Zahlungsabgleich'],
                'classification' => ['balance_sheet' => true, 'guv' => false, 'eur' => true],
                'tax_effects' => ['ust' => 'neutral', 'gewst' => 'neutral', 'kst' => 'neutral', 'est' => 'neutral'],
            ]),
            self::account('1400', 'Forderungen aus Lieferungen und Leistungen', '0', 'aktiva', [
                'summary' => 'Offene Debitorenforderungen gegenüber Kunden.',
                'digit_explanations' => [
                    1 => 'Finanz- und Privatkonten',
                    2 => 'Forderungen (Gruppe 40)',
                    3 => 'Forderungen aus L+L',
                    4 => 'Inland',
                ],
                'features' => ['keine_ust', 'debitoren'],
                'examples' => ['Rechnungsstellung 19 % USt', 'Teilzahlung Kunde', 'Mahngebühr'],
                'edge_cases' => ['Forderungsausfall → Einzelwertberichtigung', 'Skonto als Erlösminderung'],
                'dependencies' => ['Debitorenliste', 'OP-Verwaltung'],
                'classification' => ['balance_sheet' => true, 'guv' => false, 'eur' => true],
                'tax_effects' => ['ust' => 'indirekt', 'gewst' => 'neutral', 'kst' => 'neutral', 'est' => 'neutral'],
            ]),
            self::account('1576', 'Abziehbare Vorsteuer 19 %', '0', 'aktiva', [
                'summary' => 'Vorsteuer aus Eingangsrechnungen mit 19 % Umsatzsteuer.',
                'digit_explanations' => [
                    1 => 'Finanz- und Privatkonten',
                    2 => 'Vorsteuer (Gruppe 57)',
                    3 => 'Abziehbare Vorsteuer',
                    4 => 'Satz 19 %',
                ],
                'features' => ['vorsteuer', 'ust_19'],
                'examples' => ['Eingangsrechnung Büromaterial 19 %', 'Investition mit Vorsteuerabzug'],
                'edge_cases' => ['Gemischt genutzte Fahrzeuge — Vorsteuerkürzung', 'Rechnung ohne USt-Ausweis'],
                'dependencies' => ['USt-Voranmeldung', 'Eingangsrechnungen'],
                'classification' => ['balance_sheet' => true, 'guv' => false, 'eur' => true],
                'tax_effects' => ['ust' => 'abzug', 'gewst' => 'neutral', 'kst' => 'neutral', 'est' => 'neutral'],
            ]),
            self::account('1776', 'Umsatzsteuer 19 %', '0', 'passiva', [
                'summary' => 'Umsatzsteuerschuld aus steuerpflichtigen Umsätzen mit 19 %.',
                'digit_explanations' => [
                    1 => 'Finanz- und Privatkonten',
                    2 => 'Umsatzsteuer (Gruppe 77)',
                    3 => 'USt 19 %',
                    4 => 'Regelsteuersatz',
                ],
                'features' => ['umsatzsteuer', 'ust_19'],
                'examples' => ['Ausgangsrechnung 19 %', 'Innergemeinschaftliche Lieferung 0 %'],
                'edge_cases' => ['Dauerfristverlängerung', 'Kleinunternehmer — kein USt-Ausweis'],
                'dependencies' => ['USt-Voranmeldung', 'Ausgangsrechnungen'],
                'classification' => ['balance_sheet' => true, 'guv' => false, 'eur' => true],
                'tax_effects' => ['ust' => 'schuld', 'gewst' => 'neutral', 'kst' => 'neutral', 'est' => 'neutral'],
            ]),
            self::account('1600', 'Verbindlichkeiten aus Lieferungen und Leistungen', '0', 'passiva', [
                'summary' => 'Offene Kreditorenverbindlichkeiten gegenüber Lieferanten.',
                'digit_explanations' => [
                    1 => 'Finanz- und Privatkonten',
                    2 => 'Verbindlichkeiten (Gruppe 60)',
                    3 => 'Verbindlichkeiten aus L+L',
                    4 => 'Inland',
                ],
                'features' => ['keine_ust', 'kreditoren'],
                'examples' => ['Eingangsrechnung Lieferant', 'Skonto bei Zahlung'],
                'edge_cases' => ['Guthaben beim Lieferanten', 'Doppelzahlung'],
                'dependencies' => ['Kreditorenliste', 'OP-Verwaltung'],
                'classification' => ['balance_sheet' => true, 'guv' => false, 'eur' => true],
                'tax_effects' => ['ust' => 'neutral', 'gewst' => 'neutral', 'kst' => 'neutral', 'est' => 'neutral'],
            ]),
            self::account('2000', 'Gezeichnetes Kapital / Kapital', '0', 'passiva', [
                'summary' => 'Stammkapital bzw. Einlagen der Gesellschafter.',
                'digit_explanations' => [
                    2 => 'Eigenkapital (Gruppe 00)',
                    3 => 'Gezeichnetes Kapital',
                    4 => 'Kapital',
                ],
                'features' => ['keine_ust', 'eigenkapital'],
                'examples' => ['Gründungseinlage', 'Kapitalerhöhung'],
                'edge_cases' => ['GmbH vs. Einzelunternehmen — Kontenbezeichnung'],
                'dependencies' => ['Handelsregister', 'Gesellschafterliste'],
                'classification' => ['balance_sheet' => true, 'guv' => false, 'eur' => true],
                'tax_effects' => ['ust' => 'neutral', 'gewst' => 'neutral', 'kst' => 'neutral', 'est' => 'neutral'],
            ]),
            self::account('3000', 'Roh-, Hilfs- und Betriebsstoffe', '0', 'aufwand', [
                'summary' => 'Verbrauch und Bestand von Roh-, Hilfs- und Betriebsstoffen (SKR03 Materialbereich).',
                'digit_explanations' => [
                    3 => 'Materialaufwand / Wareneingang (Gruppe 30)',
                    4 => 'Wareneingang allgemein',
                ],
                'features' => ['wareneinkauf', 'material'],
                'search_terms' => ['material', 'rohstoff', 'hilfsstoff', 'betriebsstoff'],
                'examples' => ['Großhandelseinkauf', 'Liefereingang mit Lieferschein'],
                'edge_cases' => ['Bestandsveränderung am Periodenende', 'Skonto auf Eingangsrechnung'],
                'dependencies' => ['Lagerbestand', 'Eingangsrechnungen'],
                'classification' => ['balance_sheet' => false, 'guv' => true, 'eur' => true],
                'tax_effects' => ['ust' => 'vorsteuer', 'gewst' => 'minderung', 'kst' => 'minderung', 'est' => 'neutral'],
            ]),
            self::account('3200', 'Wareneingang', '0', 'aufwand', [
                'summary' => 'Eingang von Handelswaren und Waren für den Wiederverkauf (SKR03 Konto 3200).',
                'digit_explanations' => [
                    3 => 'Wareneingang (Gruppe 32)',
                    4 => 'Wareneingang',
                ],
                'features' => ['wareneinkauf', 'material', 'vorsteuer_moeglich'],
                'search_terms' => ['material', 'ware', 'wareneingang', 'einkauf', 'handelsware'],
                'examples' => ['Einkauf Handelsware', 'Liefereingang mit Lieferschein'],
                'edge_cases' => ['Bestandsveränderung am Periodenende', 'Innergemeinschaftlicher Erwerb'],
                'dependencies' => ['Lagerbestand', 'Eingangsrechnungen'],
                'classification' => ['balance_sheet' => false, 'guv' => true, 'eur' => true],
                'tax_effects' => ['ust' => 'vorsteuer', 'gewst' => 'minderung', 'kst' => 'minderung', 'est' => 'neutral'],
            ]),
            self::account('3300', 'Wareneingang 19 % Vorsteuer', '0', 'aufwand', [
                'summary' => 'Einkauf von Waren zum Wiederverkauf mit 19 % Vorsteuer.',
                'digit_explanations' => [
                    1 => 'Nicht verwendet in 4-stellig',
                    2 => 'Nicht verwendet',
                    3 => 'Materialaufwand (Gruppe 30)',
                    4 => 'Wareneingang 19 % Vorsteuer',
                ],
                'features' => ['vorsteuer', 'wareneinkauf', 'material'],
                'search_terms' => ['material', 'ware', 'wareneingang', 'handelsware'],
                'examples' => ['Einkauf Handelsware', 'Retoure an Lieferant'],
                'edge_cases' => ['Bestandsveränderung separat', 'Innergemeinschaftlicher Erwerb'],
                'dependencies' => ['Lagerbestand', 'Eingangsrechnungen'],
                'classification' => ['balance_sheet' => false, 'guv' => true, 'eur' => true],
                'tax_effects' => ['ust' => 'vorsteuer', 'gewst' => 'minderung', 'kst' => 'minderung', 'est' => 'neutral'],
            ]),
            self::account('3400', 'Bezugsnebenkosten', '0', 'aufwand', [
                'summary' => 'Fracht, Zoll und sonstige Bezugsnebenkosten zum Wareneinkauf.',
                'digit_explanations' => [
                    3 => 'Materialaufwand',
                    4 => 'Bezugsnebenkosten',
                ],
                'features' => ['vorsteuer_moeglich', 'material'],
                'search_terms' => ['material', 'fracht', 'spedition', 'bezug'],
                'examples' => ['Speditionskosten', 'Zollgebühren'],
                'edge_cases' => ['Einbeziehung in Anschaffungskosten vs. direkte Buchung'],
                'dependencies' => ['Wareneingang'],
                'classification' => ['balance_sheet' => false, 'guv' => true, 'eur' => true],
                'tax_effects' => ['ust' => 'vorsteuer', 'gewst' => 'minderung', 'kst' => 'minderung', 'est' => 'neutral'],
            ]),
            self::account('4100', 'Löhne', '0', 'aufwand', [
                'summary' => 'Bruttolöhne und -gehälter der Arbeitnehmer.',
                'digit_explanations' => [
                    4 => 'Personalaufwand (Gruppe 10)',
                    4 => 'Löhne',
                ],
                'features' => ['keine_ust', 'personal'],
                'examples' => ['Monatsgehalt', 'Stundenlohn', 'Überstunden'],
                'edge_cases' => ['Minijobber — separates Konto', 'Geschäftsführergehalt GmbH'],
                'dependencies' => ['Lohnabrechnung', 'Lohnsteuer'],
                'classification' => ['balance_sheet' => false, 'guv' => true, 'eur' => true],
                'tax_effects' => ['ust' => 'neutral', 'gewst' => 'minderung', 'kst' => 'minderung', 'est' => 'neutral'],
            ]),
            self::account('4200', 'Gesetzliche soziale Aufwendungen', '0', 'aufwand', [
                'summary' => 'Arbeitgeberanteile zur Sozialversicherung.',
                'digit_explanations' => [
                    4 => 'Personalaufwand',
                    4 => 'Sozialaufwand gesetzlich',
                ],
                'features' => ['keine_ust', 'personal'],
                'examples' => ['KV/RV/AV-AG-Anteil', 'Umlage U1/U2'],
                'edge_cases' => ['Beitragsnachweis vs. Schätzung'],
                'dependencies' => ['Lohnabrechnung', 'SV-Meldungen'],
                'classification' => ['balance_sheet' => false, 'guv' => true, 'eur' => true],
                'tax_effects' => ['ust' => 'neutral', 'gewst' => 'minderung', 'kst' => 'minderung', 'est' => 'neutral'],
            ]),
            self::account('4520', 'Kfz-Versicherungen', '0', 'aufwand', [
                'summary' => 'Versicherungsbeiträge für betriebliche Fahrzeuge (Haftpflicht, Vollkasko etc.).',
                'digit_explanations' => [
                    4 => 'Fahrzeugkosten (Gruppe 52)',
                    4 => 'Kfz-Versicherungen',
                ],
                'features' => ['fahrzeug', 'versicherung', 'keine_ust'],
                'search_terms' => ['fahrzeug', 'kfz', 'auto', 'pkw', 'versicherung', 'fuhrpark'],
                'examples' => ['Haftpflicht Firmenwagen', 'Flottenversicherung'],
                'edge_cases' => ['Privatanteil bei Firmenwagen', 'Versicherungssteuer nicht als Vorsteuer'],
                'dependencies' => ['Fuhrparkliste', 'Fahrtenbuch'],
                'classification' => ['balance_sheet' => false, 'guv' => true, 'eur' => true],
                'tax_effects' => ['ust' => 'neutral', 'gewst' => 'minderung', 'kst' => 'minderung', 'est' => 'neutral'],
            ]),
            self::account('4530', 'Laufende Fahrzeug-Betriebskosten', '0', 'aufwand', [
                'summary' => 'Kraftstoff, Wartung, Reparaturen und laufende Kosten betrieblicher Fahrzeuge.',
                'digit_explanations' => [
                    4 => 'Fahrzeugkosten (Gruppe 53)',
                    4 => 'Laufende Betriebskosten',
                ],
                'features' => ['fahrzeug', 'vorsteuer_moeglich'],
                'search_terms' => ['fahrzeug', 'kfz', 'auto', 'pkw', 'tanken', 'kraftstoff', 'wartung', 'fuhrpark'],
                'examples' => ['Tanken Firmenwagen', 'Ölwechsel', 'Reifenwechsel', 'TÜV'],
                'edge_cases' => ['Privatfahrten über 1 %-Regelung', 'Gemischte Nutzung — Vorsteuerkürzung'],
                'dependencies' => ['Fahrtenbuch', 'Fuhrpark'],
                'classification' => ['balance_sheet' => false, 'guv' => true, 'eur' => true],
                'tax_effects' => ['ust' => 'teilweise', 'gewst' => 'minderung', 'kst' => 'minderung', 'est' => 'neutral'],
            ]),
            self::account('4570', 'Kfz-Leasing', '0', 'aufwand', [
                'summary' => 'Leasingraten für betriebliche Fahrzeuge (operatives Leasing).',
                'digit_explanations' => [
                    4 => 'Fahrzeugkosten',
                    4 => 'Leasing Kfz',
                ],
                'features' => ['fahrzeug', 'leasing', 'vorsteuer_moeglich'],
                'search_terms' => ['fahrzeug', 'kfz', 'auto', 'leasing', 'miete', 'fuhrpark'],
                'examples' => ['Monatliche Leasingrate PKW', 'Leasing LKW'],
                'edge_cases' => ['Leasing vs. Kauf (0320)', 'Kilometerpauschale vs. Leasing'],
                'dependencies' => ['Leasingvertrag', 'Fahrtenbuch'],
                'classification' => ['balance_sheet' => false, 'guv' => true, 'eur' => true],
                'tax_effects' => ['ust' => 'vorsteuer', 'gewst' => 'minderung', 'kst' => 'minderung', 'est' => 'neutral'],
            ]),
            self::account('4510', 'Kfz-Steuern', '0', 'aufwand', [
                'summary' => 'Kraftfahrzeugsteuer für betrieblich genutzte Fahrzeuge (SKR03).',
                'digit_explanations' => [
                    4 => 'Steuern und Abgaben',
                    4 => 'Kfz-Steuer',
                ],
                'features' => ['fahrzeug', 'steuer', 'keine_ust'],
                'search_terms' => ['fahrzeug', 'kfz', 'auto', 'kfz-steuer', 'fuhrpark'],
                'examples' => ['Jährliche Kfz-Steuer Firmenwagen'],
                'edge_cases' => ['Privatanteil nicht abzugsfähig'],
                'dependencies' => ['Fuhrparkliste'],
                'classification' => ['balance_sheet' => false, 'guv' => true, 'eur' => true],
                'tax_effects' => ['ust' => 'neutral', 'gewst' => 'minderung', 'kst' => 'minderung', 'est' => 'neutral'],
            ]),
            self::account('4800', 'Bewirtungskosten', '0', 'aufwand', [
                'summary' => 'Bewirtung von Geschäftspartnern — nur teilweise abzugsfähig.',
                'digit_explanations' => [
                    4 => 'Sonstige betriebliche Aufwendungen',
                    4 => 'Bewirtung',
                ],
                'features' => ['vorsteuer_70', 'teilentnahme'],
                'examples' => ['Geschäftsessen mit Kunden', 'Catering bei Veranstaltung'],
                'edge_cases' => ['70 % Vorsteuerabzug', 'Nachweispflicht Teilnehmerliste'],
                'dependencies' => ['Bewirtungsbeleg'],
                'classification' => ['balance_sheet' => false, 'guv' => true, 'eur' => true],
                'tax_effects' => ['ust' => 'teilweise', 'gewst' => 'teilweise', 'kst' => 'teilweise', 'est' => 'neutral'],
            ]),
            self::account('4900', 'Reisekosten Arbeitnehmer', '0', 'aufwand', [
                'summary' => 'Fahrt-, Übernachtungs- und Verpflegungsmehraufwand von Mitarbeitern.',
                'digit_explanations' => [
                    4 => 'Sonstige betriebliche Aufwendungen',
                    4 => 'Reisekosten',
                ],
                'features' => ['vorsteuer_moeglich', 'pauschalen'],
                'examples' => ['Dienstreise Hotel', 'Kilometerpauschale', 'Verpflegungsmehraufwand'],
                'edge_cases' => ['Auslandsreisen — Tagespauschalen', 'Bewirtung vs. Reisekosten'],
                'dependencies' => ['Reisekostenabrechnung'],
                'classification' => ['balance_sheet' => false, 'guv' => true, 'eur' => true],
                'tax_effects' => ['ust' => 'teilweise', 'gewst' => 'minderung', 'kst' => 'minderung', 'est' => 'neutral'],
            ]),
            self::account('6000', 'Abschreibungen auf Sachanlagen', '0', 'aufwand', [
                'summary' => 'Planmäßige und außerplanmäßige Abschreibungen auf Anlagevermögen.',
                'digit_explanations' => [
                    6 => 'Abschreibungen',
                    4 => 'Sachanlagen',
                ],
                'features' => ['keine_ust', 'abschreibung'],
                'examples' => ['AfA Büroausstattung', 'GWG-Sofortabschreibung'],
                'edge_cases' => ['Sonder-AfA', 'Herabsetzung Nutzungsdauer'],
                'dependencies' => ['Anlagenbuchhaltung'],
                'classification' => ['balance_sheet' => false, 'guv' => true, 'eur' => true],
                'tax_effects' => ['ust' => 'neutral', 'gewst' => 'minderung', 'kst' => 'minderung', 'est' => 'neutral'],
            ]),
            self::account('6300', 'Sonstige Zinsen und ähnliche Erträge', '0', 'ertrag', [
                'summary' => 'Zinserträge aus Bankguthaben und Darlehensforderungen.',
                'digit_explanations' => [
                    6 => 'Sonstige betriebliche Erträge / Zinsen',
                    4 => 'Zinserträge',
                ],
                'features' => ['keine_ust', 'finanzertrag'],
                'examples' => ['Habenzinsen Bank', 'Verzugszinsen von Kunden'],
                'edge_cases' => ['Steuerfreie Zinserträge selten im Betrieb'],
                'dependencies' => ['Kontoauszug'],
                'classification' => ['balance_sheet' => false, 'guv' => true, 'eur' => true],
                'tax_effects' => ['ust' => 'neutral', 'gewst' => 'erhoehung', 'kst' => 'erhoehung', 'est' => 'neutral'],
            ]),
            self::account('8300', 'Erlöse 7 % USt', '0', 'ertrag', [
                'summary' => 'Umsatzerlöse mit ermäßigtem Steuersatz 7 %.',
                'digit_explanations' => [
                    8 => 'Erlöse (Gruppe 30)',
                    3 => 'Erlöse 7 %',
                    4 => 'Regelkonto',
                ],
                'features' => ['umsatzsteuer', 'ust_7'],
                'examples' => ['Lebensmittelverkauf', 'Bücher und Zeitungen'],
                'edge_cases' => ['Gemischter Satz im Warenkorb', 'Steuersatzänderung'],
                'dependencies' => ['Ausgangsrechnungen', 'Kassenbuch'],
                'classification' => ['balance_sheet' => false, 'guv' => true, 'eur' => true],
                'tax_effects' => ['ust' => 'schuld', 'gewst' => 'erhoehung', 'kst' => 'erhoehung', 'est' => 'neutral'],
            ]),
            self::account('8400', 'Erlöse 19 % USt', '0', 'ertrag', [
                'summary' => 'Umsatzerlöse mit Regelsteuersatz 19 %.',
                'digit_explanations' => [
                    8 => 'Erlöse (Gruppe 40)',
                    4 => 'Erlöse 19 %',
                ],
                'features' => ['umsatzsteuer', 'ust_19'],
                'examples' => ['Warenverkauf', 'Dienstleistungsrechnung', 'Anzahlung'],
                'edge_cases' => ['Reverse Charge beim Leistungsempfänger', 'Steuerfreie Umsätze'],
                'dependencies' => ['Ausgangsrechnungen', 'USt-Voranmeldung'],
                'classification' => ['balance_sheet' => false, 'guv' => true, 'eur' => true],
                'tax_effects' => ['ust' => 'schuld', 'gewst' => 'erhoehung', 'kst' => 'erhoehung', 'est' => 'neutral'],
            ]),
            self::account('8610', 'Sonstige betriebliche Erträge', '0', 'ertrag', [
                'summary' => 'Verschiedene betriebliche Erträge ohne Hauptumsatzcharakter.',
                'digit_explanations' => [
                    8 => 'Sonstige betriebliche Erträge',
                    6 => 'Diverse Erträge',
                ],
                'features' => ['keine_ust_oft'],
                'examples' => ['Versicherungsentschädigung', 'Erstattung Aufwendungen'],
                'edge_cases' => ['Steuerpflicht prüfen', 'Periodenabgrenzung'],
                'dependencies' => ['Belegprüfung'],
                'classification' => ['balance_sheet' => false, 'guv' => true, 'eur' => true],
                'tax_effects' => ['ust' => 'pruefen', 'gewst' => 'erhoehung', 'kst' => 'erhoehung', 'est' => 'neutral'],
            ]),
        ];
    }

    /**
     * skr04Accounts.
     *
     * @return list<array{account_number: string, name: string, account_class: string, section: string, hints: array<string, mixed>}>
     */
        private static function skr04Accounts(): array
    {
        $accounts = self::skr03Accounts();
        foreach ($accounts as &$account) {
            $account['account_number'] = self::mapToSkr04($account['account_number']);
        }
        unset($account);

        return $accounts;
    }

      /**
     * account
     * @param string $number
     * @param string $name Name
     * @param string $class
     * @param string $section Kontenabschnitt
     * @param array $hints Kontenhinweise
     * @return array{account_number: string, name: string, account_class: string, section: string, hints: array<string, mixed>}
     */
    private static function account(
        string $number,
        string $name,
        string $class,
        string $section,
        array $hints
    ): array {
        return [
            'account_number' => $number,
            'name' => $name,
            'account_class' => $class,
            'section' => $section,
            'hints' => $hints,
        ];
    }

    /**
     * mapToSkr04
     * @param string $skr03Number
     * @return string
     */
    private static function mapToSkr04(string $skr03Number): string
    {
        $first = (int) substr($skr03Number, 0, 1);
        $rest = substr($skr03Number, 1);

        return match ($first) {
            1, 2 => '1' . $rest,
            3 => '2' . $rest,
            4, 5, 6 => '3' . $rest,
            7, 8, 9 => '4' . $rest,
            default => $skr03Number,
        };
    }
}
