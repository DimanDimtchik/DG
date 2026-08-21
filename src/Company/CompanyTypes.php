<?php
declare(strict_types=1);

/**
 * Rechtsformen und Unternehmenstypen (Labels für Formulare).
 */
final class CompanyTypes
{
    /**
     * Liefert Bezeichnungen.
     *
     * @return array<string, string>
     */
        public static function labels(): array
    {
        return [
            'gewerbetreibender' => 'Gewerbetreibender',
            'ek' => 'Eingetragener Kaufmann (e.K.)',
            'eg' => 'Eingetragene Genossenschaft (e.G.)',
            'einzelunternehmen' => 'Einzelunternehmen',
            'freiberufler' => 'Freiberufler',
            'freier_mitarbeiter' => 'Freier Mitarbeiter',
            'gbr' => 'GbR',
            'ohg' => 'OHG',
            'partg' => 'PartG',
            'gmbh' => 'GmbH',
            'gmbh_igr' => 'GmbH i. Gr.',
            'gmbh_co_kg' => 'GmbH & Co. KG',
            'kg' => 'KG',
            'ug' => 'UG (haftungsbeschränkt)',
            'ug_igr' => 'UG i. Gr.',
            'ltd' => 'Ltd.',
            'stille_gesellschaft' => 'Stille Gesellschaft',
        ];
    }
}
