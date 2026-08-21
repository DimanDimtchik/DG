<?php
declare(strict_types=1);

/** ISO-3166-1 alpha-2 für Nummernkreise (Finanzamt / ELSTER / EU). */
final class CountryCodes
{
    /**
     * Liefert alle Einträge.
     *
     * @return array<string, string>
     */
        public static function all(): array
    {
        $codes = [
            'DE' => 'Deutschland',
            'AT' => 'Österreich',
            'BE' => 'Belgien',
            'BG' => 'Bulgarien',
            'CH' => 'Schweiz',
            'CY' => 'Zypern',
            'CZ' => 'Tschechien',
            'DK' => 'Dänemark',
            'EE' => 'Estland',
            'ES' => 'Spanien',
            'FI' => 'Finnland',
            'FR' => 'Frankreich',
            'GB' => 'Vereinigtes Königreich',
            'GR' => 'Griechenland',
            'HR' => 'Kroatien',
            'HU' => 'Ungarn',
            'IE' => 'Irland',
            'IS' => 'Island',
            'IT' => 'Italien',
            'LI' => 'Liechtenstein',
            'LT' => 'Litauen',
            'LU' => 'Luxemburg',
            'LV' => 'Lettland',
            'MT' => 'Malta',
            'NL' => 'Niederlande',
            'NO' => 'Norwegen',
            'PL' => 'Polen',
            'PT' => 'Portugal',
            'RO' => 'Rumänien',
            'SE' => 'Schweden',
            'SI' => 'Slowenien',
            'SK' => 'Slowakei',
            'US' => 'USA',
            'CA' => 'Kanada',
            'AU' => 'Australien',
            'JP' => 'Japan',
            'CN' => 'China',
            'IN' => 'Indien',
            'BR' => 'Brasilien',
            'RU' => 'Russland',
            'TR' => 'Türkei',
            'UA' => 'Ukraine',
            'RS' => 'Serbien',
            'BA' => 'Bosnien und Herzegowina',
            'MK' => 'Nordmazedonien',
            'AL' => 'Albanien',
            'ME' => 'Montenegro',
            'XK' => 'Kosovo',
            'AD' => 'Andorra',
            'MC' => 'Monaco',
            'SM' => 'San Marino',
            'VA' => 'Vatikanstadt',
            'MD' => 'Moldau',
            'BY' => 'Belarus',
            'GE' => 'Georgien',
            'AZ' => 'Aserbaidschan',
            'AM' => 'Armenien',
            'IL' => 'Israel',
            'AE' => 'Vereinigte Arabische Emirate',
            'SA' => 'Saudi-Arabien',
            'ZA' => 'Südafrika',
            'MX' => 'Mexiko',
            'KR' => 'Südkorea',
            'SG' => 'Singapur',
            'HK' => 'Hongkong',
            'NZ' => 'Neuseeland',
        ];

        asort($codes, SORT_LOCALE_STRING);

        return $codes;
    }
}
