<?php
declare(strict_types=1);

/**
 * Korrigierte DATEV-Kontonamen (PDF-Import-Artefakte wie „nisse und“, „findliche“, „B“).
 *
 * Quelle: SKR03/SKR04 Standardbeschriftungen (DATEV, Haufe, buchungssatz.de).
 */
final class ChartAccountCatalogNameCorrections
{
    public const VERSION = '2026-07-06-artifacts';

    /** @var array<string, string> */
    private const SKR03 = [
        '0048' => 'Immaterielle Vermögensgegenstände in Entwicklung',
        '1353' => 'Vermögensgegenstände zur Erfüllung von mit der Altersversorgung vergleichbaren langfristigen Verpflichtungen nach § 246 Abs. 2 HGB',
        '1354' => 'Vermögensgegenstände zur Saldierung mit der Altersversorgung vergleichbaren langfristigen Verpflichtungen nach § 246 Abs. 2 HGB',
        '1511' => 'Geleistete Anzahlungen, 7 % Vorsteuer und 7 % Umsatzsteuer',
        '1450' => 'Forderungen nach § 11 Abs. 1 EStG',
        '1501' => 'Restlaufzeit größer 1 Jahr',
        '1577' => 'Abziehbare Vorsteuer aus innergemeinschaftlichem Erwerb',
        '1605' => 'Verbindlichkeiten aus Lieferungen und Leistungen zum allgemeinen Umsatzsteuersatz (EÜR)',
        '1606' => 'Verbindlichkeiten aus Lieferungen und Leistungen zum ermäßigten Umsatzsteuersatz (EÜR)',
        '1607' => 'Verbindlichkeiten aus Lieferungen und Leistungen ohne Vorsteuer (EÜR)',
        '1624' => 'Verbindlichkeiten aus Lieferungen und Leistungen ohne Kontokorrent',
        '1625' => 'Verbindlichkeiten aus Lieferungen und Leistungen ohne Kontokorrent – Restlaufzeit bis 1 Jahr',
        '1630' => 'Verbindlichkeiten aus Lieferungen und Leistungen ohne Kontokorrent – Restlaufzeit 1 bis 5 Jahre',
        '1640' => 'Verbindlichkeiten aus Lieferungen und Leistungen ohne Kontokorrent – Restlaufzeit größer 5 Jahre',
        '1650' => 'Verbindlichkeiten aus Lieferungen und Leistungen für Investitionen für § 4/3 EStG',
        '1695' => 'Verbindlichkeiten gegenüber stillen Gesellschaftern',
        '1757' => 'Verbindlichkeiten gegenüber Gesellschaft/Gesamthand',
        '1795' => 'Verbindlichkeiten im Rahmen der sozialen Sicherheit',
        '3108' => 'Fremdleistungen 7 % Vorsteuer und 7 % Umsatzsteuer',
        '3347' => 'Wareneingang 7 % Vorsteuer und 7 % Umsatzsteuer',
        '3714' => 'Nachlässe aus Einkauf Roh-, Hilfs- und Betriebsstoffe 7 % Vorsteuer und 7 % Umsatzsteuer',
        '3754' => 'Erhaltene Boni aus Einkauf Roh-, Hilfs- und Betriebsstoffe 7 % Vorsteuer und 7 % Umsatzsteuer',
        '3784' => 'Erhaltene Rabatte aus Einkauf Roh-, Hilfs- und Betriebsstoffe 7 % Vorsteuer und 7 % Umsatzsteuer',
        '2687' => 'Erträge aus Vermögensgegenständen zur Verrechnung nach § 231 Abs. 2 Satz 2 HGB Zinsen und ähnliche Erträge',
        '4165' => 'Aufwendungen für Altersversorgung',
        '4996' => 'Herstellungskosten (aktivierte Eigenleistungen)',
        '8704' => 'Erlösschmälerungen für sonstige steuerfreie Umsätze mit Vorsteuerabzug',
        '8719' => 'Erlösschmälerungen 0 % USt',
        '8906' => 'Verwendung von Gegenständen für Zwecke außerhalb des Unternehmens ohne USt',
        '8918' => 'Verwendung von Gegenständen für Zwecke außerhalb des Unternehmens 19 % USt',
        '8920' => 'Verwendung von Gegenständen für Zwecke außerhalb des Unternehmens 7 % USt',
        '8921' => 'Verwendung von Gegenständen für Zwecke außerhalb des Unternehmens ohne USt (§ 24 UStG)',
        '8922' => 'Verwendung von Gegenständen für Zwecke außerhalb des Unternehmens 19 % USt (§ 24 UStG)',
        '8924' => 'Verwendung von Gegenständen für Zwecke außerhalb des Unternehmens 7 % USt (§ 24 UStG)',
        '8930' => 'Verwendung von Gegenständen für Zwecke außerhalb des Unternehmens 19 % USt',
        '8931' => 'Verwendung von Gegenständen für Zwecke außerhalb des Unternehmens 7 % USt',
        '8939' => 'Unentgeltliche Zuwendung von Gegenständen ohne USt',
        '8947' => 'Unentgeltliche Zuwendung von Waren 7 % USt',
        '9208' => 'Sonstige betriebliche Erträge',
        '9243' => 'Investitionsverbindlichkeiten aus Käufen von Finanzanlagen bei Leistungsverbindlichkeiten',
        '9272' => 'Verbindlichkeiten aus der Begebung und Übertragung von Wechseln gegenüber verbundenen Unternehmen',
        '9273' => 'Verbindlichkeiten aus Bürgschaften, Wechsel- und Scheckbürgschaften',
        '9274' => 'Verbindlichkeiten aus Bürgschaften, Wechsel- und Scheckbürgschaften gegenüber verbundenen Unternehmen',
        '9278' => 'Haftung aus der Bestellung von Sicherheiten für fremde Verbindlichkeiten gegenüber verbundenen Unternehmen',
        '9907' => 'Gegenkonto zu steuerfreien Einnahmen und Entnahmen nach § 3 Nr. 72 EStG',
        '9971' => 'Investitionsabzugsbetrag § 7g EStG',
        // Bestandskonten Klasse 7
        '7000' => 'Unfertige Erzeugnisse, unfertige Leistungen (Bestand)',
        '7090' => 'In Ausführung befindliche Bauaufträge',
        '7095' => 'In Arbeit befindliche Aufträge',
        // Bestandsveränderungen Klasse 8
        '8975' => 'Bestandsveränderungen in Ausführung befindliche Bauaufträge',
        '8977' => 'Bestandsveränderungen in Arbeit befindliche Aufträge',
        '8980' => 'Bestandsveränderungen fertige Erzeugnisse',
        // Fremdleistungen / §13b (truncated or duplicated PDF text)
        '3113' => 'Sonstige Leistungen eines im anderen EU-Land ansässigen Unternehmers 7 % Vorsteuer und 7 % Umsatzsteuer',
        '3115' => 'Leistungen eines im Ausland ansässigen Unternehmers 7 % Vorsteuer und 7 % Umsatzsteuer',
        '3123' => 'Sonstige Leistungen eines im anderen EU-Land ansässigen Unternehmers 19 % Vorsteuer und 19 % Umsatzsteuer',
        '3130' => 'Bauleistungen eines im Inland ansässigen Unternehmers ohne Vorsteuer und 7 % Umsatzsteuer',
        '3133' => 'Sonstige Leistungen eines im anderen EU-Land ansässigen Unternehmers ohne Vorsteuer und 7 % Umsatzsteuer',
        '3135' => 'Leistungen eines im Ausland ansässigen Unternehmers ohne Vorsteuer und 19 % Umsatzsteuer',
        '3143' => 'Sonstige Leistungen eines im anderen EU-Land ansässigen Unternehmers ohne Vorsteuer und 19 % Umsatzsteuer',
        '3160' => 'Leistungen nach § 13b UStG mit Vorsteuer und 19 % Umsatzsteuer',
        '3165' => 'Leistungen nach § 13b UStG ohne Vorsteuer und 19 % Umsatzsteuer',
    ];

    /** @var array<string, string> */
    private const SKR04 = [
        '0148' => 'Immaterielle Vermögensgegenstände in Entwicklung',
        '1181' => 'Geleistete Anzahlungen 7 % Vorsteuer und 7 % Umsatzsteuer',
        '1382' => 'Vermögensgegenstände zur Erfüllung von mit der Altersversorgung vergleichbaren langfristigen Verpflichtungen nach § 246 Abs. 2 HGB',
        '3634' => 'Verbindlichkeiten gegenüber Gesellschaft/Gesamthand',
        '3796' => 'Verbindlichkeiten im Rahmen der sozialen Sicherheit',
        '4128' => 'Unentgeltliche Zuwendung von Gegenständen ohne USt',
        '4636' => 'Verwendung von Gegenständen für Zwecke außerhalb des Unternehmens ohne USt',
        '4637' => 'Verwendung von Gegenständen für Zwecke außerhalb des Unternehmens 19 % USt',
        '4638' => 'Verwendung von Gegenständen für Zwecke außerhalb des Unternehmens 7 % USt',
        '4639' => 'Verwendung von Gegenständen für Zwecke außerhalb des Unternehmens ohne USt (§ 24 UStG)',
        '4645' => 'Verwendung von Gegenständen für Zwecke außerhalb des Unternehmens 19 % USt',
        '4646' => 'Verwendung von Gegenständen für Zwecke außerhalb des Unternehmens 7 % USt',
        '4679' => 'Unentgeltliche Zuwendung von Waren ohne USt',
        '4704' => 'Erlösschmälerungen für sonstige steuerfreie Umsätze mit Vorsteuerabzug',
        '4816' => 'Bestandsveränderungen in Ausführung befindliche Bauaufträge',
        '4818' => 'Bestandsveränderungen in Arbeit befindliche Aufträge',
        '5347' => 'Wareneingang 7 % Vorsteuer und 7 % Umsatzsteuer',
        '5714' => 'Nachlässe aus Einkauf Roh-, Hilfs- und Betriebsstoffe 7 % Vorsteuer und 7 % Umsatzsteuer',
        '5754' => 'Erhaltene Boni aus Einkauf Roh-, Hilfs- und Betriebsstoffe 7 % Vorsteuer und 7 % Umsatzsteuer',
        '5908' => 'Fremdleistungen 7 % Vorsteuer und 7 % Umsatzsteuer',
        '4850' => 'Erlöse aus Verkäufen immaterieller Vermögensgegenstände (bei Buchverlust)',
        '6100' => 'Soziale Abgaben und Aufwendungen für Altersversorgung',
        '6140' => 'Aufwendungen für Altersversorgung',
        '6990' => 'Herstellungskosten (aktivierte Eigenleistungen)',
        '7145' => 'Erträge aus Vermögensgegenständen zur Verrechnung nach § 231 Abs. 2 Satz 2 HGB Zinsen und ähnliche Erträge',
        '9208' => 'Sonstige betriebliche Erträge',
        '9971' => 'Investitionsabzugsbetrag § 7g EStG',
        '5913' => 'Sonstige Leistungen eines im anderen EU-Land ansässigen Unternehmers 7 % Vorsteuer und 7 % Umsatzsteuer',
        '5915' => 'Leistungen eines im Ausland ansässigen Unternehmers 19 % Vorsteuer und 19 % Umsatzsteuer',
        '5923' => 'Sonstige Leistungen eines im anderen EU-Land ansässigen Unternehmers 19 % Vorsteuer und 19 % Umsatzsteuer',
        '5930' => 'Bauleistungen eines im Inland ansässigen Unternehmers ohne Vorsteuer und 7 % Umsatzsteuer',
        '5933' => 'Sonstige Leistungen eines im anderen EU-Land ansässigen Unternehmers ohne Vorsteuer und 7 % Umsatzsteuer',
        '5935' => 'Leistungen eines im Ausland ansässigen Unternehmers ohne Vorsteuer und 19 % Umsatzsteuer',
        '5943' => 'Sonstige Leistungen eines im anderen EU-Land ansässigen Unternehmers ohne Vorsteuer und 19 % Umsatzsteuer',
        '5960' => 'Leistungen nach § 13b UStG mit Vorsteuer und 19 % Umsatzsteuer',
        '5965' => 'Leistungen nach § 13b UStG ohne Vorsteuer und 19 % Umsatzsteuer',
    ];

    /** @return array<string, string> */
    public static function forSkr(string $skrType): array
    {
        return match (ChartOfAccountsSettings::sanitizeSkrType($skrType)) {
            'skr04' => self::SKR04,
            default => self::SKR03,
        };
    }

    public static function correctedName(string $skrType, string $accountNumber, string $currentName): string
    {
        $number = str_pad(preg_replace('/\D/', '', $accountNumber) ?? '', 4, '0', STR_PAD_LEFT);
        $map = self::forSkr($skrType);

        return $map[$number] ?? $currentName;
    }
}
