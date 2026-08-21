<?php
declare(strict_types=1);

/**
 * Quellsysteme für den Installationsimport — mit einfachen Export-Hinweisen
 * und zusätzlichen Spalten-Aliassen (kein CSV-Umwandeln nötig).
 */
final class InstallImportSourcePresets
{
    public const SOURCE_EXCEL = 'excel';
    public const SOURCE_OUTLOOK = 'outlook';
    public const SOURCE_GOOGLE = 'google';
    public const SOURCE_DATEV = 'datev';
    public const SOURCE_LEXWARE = 'lexware';
    public const SOURCE_SEVDESK = 'sevdesk';
    public const SOURCE_SHIFTBASE = 'shiftbase';
    public const SOURCE_OTHER = 'other';

    /** @return array<string, array{label: string, hint: string, formats: string}> */
    public static function all(): array
    {
        return [
            self::SOURCE_EXCEL => [
                'label' => 'Excel / LibreOffice (Datei einfach hochladen)',
                'hint' => 'Öffnen Sie Ihre Tabelle in Excel und laden Sie die .xlsx-Datei hier hoch — kein Export nötig, wenn Sie die Originaldatei haben.',
                'formats' => 'Excel (.xlsx), CSV',
            ],
            self::SOURCE_OUTLOOK => [
                'label' => 'Microsoft Outlook (Kontakte)',
                'hint' => 'Outlook → Datei → Öffnen und Exportieren → Import/Export → In Datei exportieren → CSV. Die Datei hier hochladen.',
                'formats' => 'CSV, Excel',
            ],
            self::SOURCE_GOOGLE => [
                'label' => 'Google Kontakte',
                'hint' => 'Google Kontakte → Exportieren → Google CSV. Die heruntergeladene Datei hier hochladen.',
                'formats' => 'CSV',
            ],
            self::SOURCE_DATEV => [
                'label' => 'DATEV (Unternehmen online / Rechnungswesen)',
                'hint' => 'Adressen/Stammdaten als Excel- oder CSV-Export aus DATEV exportieren und hier hochladen. Buchungsstapel (EXTF) für Belege folgen später.',
                'formats' => 'Excel (.xlsx), CSV',
            ],
            self::SOURCE_LEXWARE => [
                'label' => 'Lexware (office / financial office)',
                'hint' => 'In Lexware: Adressen oder Artikel als Excel-Liste exportieren (Bericht / Export) und die Datei hier hochladen.',
                'formats' => 'Excel (.xlsx), CSV',
            ],
            self::SOURCE_SEVDESK => [
                'label' => 'sevDesk / Lexoffice',
                'hint' => 'Kontakte oder Produkte exportieren (Einstellungen → Export) und die Excel- oder CSV-Datei hier hochladen.',
                'formats' => 'Excel (.xlsx), CSV',
            ],
            self::SOURCE_SHIFTBASE => [
                'label' => 'ShiftBase (Personal / Dienstplan)',
                'hint' => 'Mitarbeiter oder Schichten in ShiftBase exportieren (Berichte → Export als Excel/CSV) und die Datei hier hochladen.',
                'formats' => 'Excel (.xlsx), CSV',
            ],
            self::SOURCE_OTHER => [
                'label' => 'Anderes Programm / ich bin unsicher',
                'hint' => 'Laden Sie einfach die Datei hoch, die Ihr bisheriges Programm beim Export erzeugt hat (Excel oder CSV). Wir erkennen die Spalten automatisch.',
                'formats' => 'Excel (.xlsx), CSV, XML, JSON',
            ],
        ];
    }

    public static function label(string $source): string
    {
        return self::all()[$source]['label'] ?? self::all()[self::SOURCE_OTHER]['label'];
    }

    public static function normalize(string $source): string
    {
        return array_key_exists($source, self::all()) ? $source : self::SOURCE_OTHER;
    }

    /**
     * Zusätzliche Spalten-Aliasse je Quellsystem und Datentyp.
     *
     * @return array<string, list<string>>
     */
    public static function contactAliases(string $source): array
    {
        $base = [];
        $source = self::normalize($source);

        return match ($source) {
            self::SOURCE_OUTLOOK => array_merge($base, [
                'first_name' => ['given_name', 'givenname'],
                'last_name' => ['family_name', 'surname', 'familyname'],
                'email' => ['e_mail_address', 'email_address'],
                'phone_1' => ['business_phone', 'mobile_phone', 'home_phone', 'primary_phone'],
                'company_name' => ['company', 'organization'],
                'street' => ['business_street', 'home_street', 'business_address'],
                'postal' => ['business_postal_code', 'postal_code', 'zip'],
                'city' => ['business_city', 'locality'],
            ]),
            self::SOURCE_GOOGLE => array_merge($base, [
                'first_name' => ['given_name'],
                'last_name' => ['family_name'],
                'company_name' => ['organization_1', 'organization_name'],
                'phone_1' => ['phone_1__label_', 'phone_1_value'],
            ]),
            self::SOURCE_DATEV => array_merge($base, [
                'company_name' => ['name', 'kurzbezeichnung', 'kurzbez'],
                'customer_number' => ['kunden_nr', 'kunden_nr_', 'debitor'],
                'supplier_number' => ['kreditor', 'lieferanten_nr'],
                'street' => ['strasse', 'straße_hausnummer', 'strasse_hausnr'],
                'vat_id' => ['ust_id', 'ustidnr'],
                'tax_number' => ['steuernr'],
            ]),
            self::SOURCE_LEXWARE => array_merge($base, [
                'company_name' => ['name_1', 'name1', 'firma'],
                'first_name' => ['vorname'],
                'last_name' => ['nachname'],
                'customer_number' => ['kunden_nr', 'kundennr'],
                'street' => ['strasse', 'straße'],
            ]),
            self::SOURCE_SEVDESK => array_merge($base, [
                'company_name' => ['name', 'company'],
                'customer_number' => ['customer_number', 'kundennummer'],
                'email' => ['email', 'e_mail'],
            ]),
            default => $base,
        };
    }

    /**
     * @return array<string, list<string>>
     */
    public static function employeeAliases(string $source): array
    {
        $source = self::normalize($source);

        return match ($source) {
            self::SOURCE_SHIFTBASE => [
                'name' => ['mitarbeiter', 'employee', 'full_name', 'vollständiger_name'],
                'area' => ['standort', 'location', 'abteilung', 'team', 'bereich'],
                'email' => ['e_mail', 'mail', 'geschäftliche_e_mail'],
                'active' => ['status', 'aktiv', 'active'],
            ],
            self::SOURCE_DATEV, self::SOURCE_LEXWARE => [
                'name' => ['name', 'mitarbeiter', 'nachname_vorname', 'ansprechpartner'],
                'area' => ['kostenstelle', 'abteilung', 'bereich'],
            ],
            default => [],
        };
    }

    /**
     * @return array<string, list<string>>
     */
    public static function bookingAliases(string $source): array
    {
        $source = self::normalize($source);

        return match ($source) {
            self::SOURCE_SHIFTBASE => [
                'slot_datetime' => ['start', 'startzeit', 'beginn', 'von', 'shift_start'],
                'customer_name' => ['kunde', 'client', 'kontakt'],
                'employee_name' => ['mitarbeiter', 'employee', 'zugewiesen_an'],
                'status' => ['status', 'schichtstatus'],
            ],
            default => [
                'slot_datetime' => ['startzeit', 'beginn', 'datum_uhrzeit', 'terminbeginn'],
            ],
        };
    }

    /**
     * @return array<string, list<string>>
     */
    public static function articleAliases(string $source): array
    {
        $source = self::normalize($source);

        return match ($source) {
            self::SOURCE_LEXWARE => [
                'title' => ['artikel', 'bezeichnung_1', 'artikelbezeichnung'],
                'article_number' => ['artikel_nr', 'artnr'],
                'price_gross' => ['vk_preis', 'verkaufspreis_brutto'],
            ],
            self::SOURCE_SEVDESK => [
                'title' => ['name', 'produktname', 'bezeichnung'],
                'article_number' => ['artikelnummer', 'sku'],
                'price_gross' => ['preis', 'verkaufspreis'],
            ],
            self::SOURCE_DATEV => [
                'title' => ['bezeichnung', 'artikeltext'],
                'article_number' => ['artikel_nr'],
            ],
            default => [],
        };
    }

    /** Dateitypen, die tabellarische Importe akzeptieren. */
    public static function tabularAcceptAttribute(): string
    {
        return '.csv,.txt,.xlsx,.xml,.json';
    }

    /** @return list<string> */
    public static function tabularExtensions(): array
    {
        return ['csv', 'txt', 'xlsx', 'xml', 'json'];
    }
}
