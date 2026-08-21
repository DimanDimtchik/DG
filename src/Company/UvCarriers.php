<?php
declare(strict_types=1);

/** Unfallversicherungsträger (BG + Unfallkassen). */
final class UvCarriers {

    /**
     * Liefert alle UV-Träger.
     *
     * @return array<string, array{name: string, short: string, street: string, zip: string, city: string, type: string}>
     */
    public static function all() {
        static $carriers = null;
        if (null !== $carriers) {
            return $carriers;
        }

        $carriers = array(
            'bg_bau' => array(
                'name'   => 'Berufsgenossenschaft der Bauwirtschaft',
                'short'  => 'BG BAU',
                'street' => 'Franz-Kleibrink-Str. 2',
                'zip'    => '30659',
                'city'   => 'Hannover',
                'type'   => 'BG',
            ),
            'bg_etem' => array(
                'name'   => 'Berufsgenossenschaft Energie Textil Elektro Medienerzeugnisse',
                'short'  => 'BG ETEM',
                'street' => 'Gustav-Heinemann-Ufer 130',
                'zip'    => '50968',
                'city'   => 'Köln',
                'type'   => 'BG',
            ),
            'bghm' => array(
                'name'   => 'Berufsgenossenschaft Holz und Metall',
                'short'  => 'BGHM',
                'street' => 'Robert-Schumann-Str. 10',
                'zip'    => '63179',
                'city'   => 'Obertshausen',
                'type'   => 'BG',
            ),
            'bgn' => array(
                'name'   => 'Berufsgenossenschaft Nahrungsmittel und Gastgewerbe',
                'short'  => 'BGN',
                'street' => 'Deutschherrenstr. 127',
                'zip'    => '53127',
                'city'   => 'Bonn',
                'type'   => 'BG',
            ),
            'bg_rci' => array(
                'name'   => 'Berufsgenossenschaft Rohstoffe und chemische Industrie',
                'short'  => 'BG RCI',
                'street' => 'Kurfürsten-Anlage 38',
                'zip'    => '69115',
                'city'   => 'Heidelberg',
                'type'   => 'BG',
            ),
            'bgw' => array(
                'name'   => 'Berufsgenossenschaft Gesundheitsdienst und Wohlfahrtspflege',
                'short'  => 'BGW',
                'street' => 'Ottenser Hauptstr. 54',
                'zip'    => '22765',
                'city'   => 'Hamburg',
                'type'   => 'BG',
            ),
            'bg_verkehr' => array(
                'name'   => 'Berufsgenossenschaft Verkehrswirtschaft Post-Logistik Telekommunikation',
                'short'  => 'BG Verkehr',
                'street' => 'Ottenser Hauptstr. 54',
                'zip'    => '22765',
                'city'   => 'Hamburg',
                'type'   => 'BG',
            ),
            'bghw' => array(
                'name'   => 'Berufsgenossenschaft Handel und Warenlogistik',
                'short'  => 'BGHW',
                'street' => 'Friedrichstraße 16',
                'zip'    => '10969',
                'city'   => 'Berlin',
                'type'   => 'BG',
            ),
            'vbg' => array(
                'name'   => 'Verwaltungs-Berufsgenossenschaft',
                'short'  => 'VBG',
                'street' => 'Nordkanalstr. 14',
                'zip'    => '20097',
                'city'   => 'Hamburg',
                'type'   => 'BG',
            ),
            'svlfg' => array(
                'name'   => 'Sozialversicherung für Landwirtschaft, Forsten und Gartenbau',
                'short'  => 'SVLFG',
                'street' => 'Karl-Schwarzschild-Str. 3',
                'zip'    => '93049',
                'city'   => 'Regensburg',
                'type'   => 'BG',
            ),
            'uk_bb' => array(
                'name'   => 'Unfallkasse Bund und Bahn',
                'short'  => 'UK Bund und Bahn',
                'street' => 'Glinkastr. 9',
                'zip'    => '10117',
                'city'   => 'Berlin',
                'type'   => 'UK',
            ),
            'uk_berlin' => array(
                'name'   => 'Unfallkasse Berlin',
                'short'  => 'UK Berlin',
                'street' => 'Friedrichstr. 16',
                'zip'    => '10969',
                'city'   => 'Berlin',
                'type'   => 'UK',
            ),
            'uk_bremen' => array(
                'name'   => 'Unfallkasse Freie Hansestadt Bremen',
                'short'  => 'UK Bremen',
                'street' => 'Contrescarpe 72',
                'zip'    => '28195',
                'city'   => 'Bremen',
                'type'   => 'UK',
            ),
            'uk_hamburg' => array(
                'name'   => 'Unfallkasse Hamburg',
                'short'  => 'UK Hamburg',
                'street' => 'Ottenser Hauptstr. 54',
                'zip'    => '22765',
                'city'   => 'Hamburg',
                'type'   => 'UK',
            ),
            'uk_bayern' => array(
                'name'   => 'Bayerische Landesunfallkasse',
                'short'  => 'BLUK',
                'street' => 'Flößergasse 2',
                'zip'    => '81369',
                'city'   => 'München',
                'type'   => 'UK',
            ),
            'uk_nord' => array(
                'name'   => 'Unfallkasse Nord',
                'short'  => 'UKN',
                'street' => 'Hindenburgstr. 104',
                'zip'    => '24114',
                'city'   => 'Kiel',
                'type'   => 'UK',
            ),
            'uk_sachsen' => array(
                'name'   => 'Unfallkasse Sachsen',
                'short'  => 'UKS',
                'street' => 'Stauffenbergallee 12',
                'zip'    => '01099',
                'city'   => 'Dresden',
                'type'   => 'UK',
            ),
            'uk_thueringen' => array(
                'name'   => 'Unfallkasse Thüringen',
                'short'  => 'UKT',
                'street' => 'Jorge-Semprún-Platz 4',
                'zip'    => '99096',
                'city'   => 'Erfurt',
                'type'   => 'UK',
            ),
            'uk_saarland' => array(
                'name'   => 'Unfallkasse Saarland',
                'short'  => 'UK Saarland',
                'street' => 'Franz-Josef-Röder-Str. 17',
                'zip'    => '66119',
                'city'   => 'Saarbrücken',
                'type'   => 'UK',
            ),
            'uk_rlp' => array(
                'name'   => 'Unfallkasse Rheinland-Pfalz',
                'short'  => 'UK RLP',
                'street' => 'Kaiser-Friedrich-Str. 7',
                'zip'    => '55116',
                'city'   => 'Mainz',
                'type'   => 'UK',
            ),
            'uk_hessen' => array(
                'name'   => 'Unfallkasse Hessen',
                'short'  => 'UK Hessen',
                'street' => 'Wilhelmshöher Allee 247-249',
                'zip'    => '34131',
                'city'   => 'Kassel',
                'type'   => 'UK',
            ),
            'uk_baden_wuerttemberg' => array(
                'name'   => 'Unfallkasse Baden-Württemberg',
                'short'  => 'UK BW',
                'street' => 'Durlacher Allee 100',
                'zip'    => '76137',
                'city'   => 'Karlsruhe',
                'type'   => 'UK',
            ),
            'uk_niedersachsen' => array(
                'name'   => 'Unfallkasse Niedersachsen',
                'short'  => 'UK Niedersachsen',
                'street' => 'Am Hilgenholz 1',
                'zip'    => '30659',
                'city'   => 'Hannover',
                'type'   => 'UK',
            ),
            'uk_nrw' => array(
                'name'   => 'Kommunale Unfallversicherung Westfalen-Lippe (UK NRW)',
                'short'  => 'UK NRW',
                'street' => 'Karlstr. 35-37',
                'zip'    => '44135',
                'city'   => 'Dortmund',
                'type'   => 'UK',
            ),
            'uk_mv' => array(
                'name'   => 'Unfallkasse Mecklenburg-Vorpommern',
                'short'  => 'UK MV',
                'street' => 'Lübecker Str. 200',
                'zip'    => '19059',
                'city'   => 'Schwerin',
                'type'   => 'UK',
            ),
            'uk_brandenburg' => array(
                'name'   => 'Unfallkasse Brandenburg',
                'short'  => 'UK Brandenburg',
                'street' => 'Heinrich-Mann-Allee 107',
                'zip'    => '14473',
                'city'   => 'Potsdam',
                'type'   => 'UK',
            ),
        );

        uasort(
            $carriers,
            static function ($a, $b) {
                return strcasecmp($a['short'], $b['short']);
            }
        );

        return $carriers;
    }

    /**
     * Liefert einen UV-Träger anhand des Schlüssels.
     *
     * @param string $key Träger-Schlüssel
     * @return array<string, mixed>|null
     */
    public static function get($key) {
        $all = self::all();
        return $all[$key] ?? null;
    }

    /**
     * Liefert UV-Träger gruppiert für Select-Felder.
     *
     * @return array{BG: array<string, string>, UK: array<string, string>}
     */
    public static function select_options() {
        $options = array(
            'BG' => array(),
            'UK' => array(),
        );
        foreach (self::all() as $key => $carrier) {
            $label = $carrier['short'] . ' – ' . $carrier['city'];
            $options[$carrier['type']][$key] = $label;
        }
        return $options;
    }

    /**
     * Branche → wahrscheinlicher UV-Träger
     *
     * @param string $industry_key
     * @return string|null
     */
    public static function suggest_for_industry($industry_key) {
        $map = array(
            'agrar'              => 'svlfg',
            'forst'              => 'svlfg',
            'fischerei'          => 'svlfg',
            'produktion'         => 'bghm',
            'metall_elektro'     => 'bg_etem',
            'bau'                => 'bg_bau',
            'handwerk_allgemein' => 'bghm',
            'elektro_handwerk'   => 'bg_etem',
            'pv_solar'           => 'bg_etem',
            'handel'             => 'bghw',
            'logistik'           => 'bg_verkehr',
            'gastronomie'        => 'bgn',
            'it_software'        => 'vbg',
            'beratung'           => 'vbg',
            'steuer_recht'       => 'vbg',
            'architektur_ingenieur'=> 'vbg',
            'werbung'            => 'vbg',
            'reinigung'          => 'bgn',
            'gesundheit'         => 'bgw',
            'bildung'            => 'vbg',
            'freiberufler'       => 'vbg',
            'arzt_zahnarzt'      => 'bgw',
            'design_foto'        => 'vbg',
            'sonstiges'          => 'bghw',
        );

        return $map[$industry_key] ?? null;
    }

    /**
     * Hinweise wenn noch keine Unternehmensnummer beim UV-Träger
     *
     * @return array<string, mixed>
     */
    public static function get_registration_guidance() {
        return array(
            'headline' => 'Noch keine Unternehmensnummer beim UV-Träger?',
            'summary'  => 'Jeder Betrieb muss sich bei der zuständigen Berufsgenossenschaft oder Unfallkasse anmelden. Die 15-stellige Unternehmensnummer erhalten Sie nach der Anmeldung mit dem Beitrags- oder Zuständigkeitsbescheid.',
            'steps'    => array(
                array(
                    'title' => 'Zuständigen UV-Träger ermitteln',
                    'text'  => 'Richtet sich nach Branche und Tätigkeit (BG) bzw. bei öffentlichen/ bestimmten Arbeitgebern nach dem Bundesland (Unfallkasse). Vorschlag aus gewählter Branche beachten.',
                ),
                array(
                    'title' => 'Anmeldung des Betriebs',
                    'text'  => 'Mit Beginn der Tätigkeit bzw. spätestens bei Beschäftigung erster Mitarbeiter beim UV-Träger anmelden (Mitteilung über Betrieb, Tätigkeit, Inhaber).',
                ),
                array(
                    'title' => 'Unternehmensnummer notieren',
                    'text'  => 'Die 15-stellige Unternehmensnummer steht im Beitragsbescheid – sie ist für Unfallanzeigen, DGUV-Meldungen und Korrespondenz erforderlich.',
                ),
                array(
                    'title' => 'Beiträge und Gefährdung',
                    'text'  => 'UV-Beiträge werden nach Entgelt und Gefahrklasse erhoben. Gefährdungsbeurteilung und Unterweisungen sind Pflicht (DGUV Vorschrift 1).',
                ),
            ),
            'deadlines' => array(
                'title' => 'Wichtige Fristen',
                'items' => array(
                    'Anmeldung beim UV-Träger: unverzüglich mit Aufnahme der Tätigkeit / Beschäftigungsbeginn (§ 193 SGB VII)',
                    'Unfallanzeige: innerhalb von 3 Tagen ab Kenntnis (Arbeitsunfall mit Arbeitsunfähigkeit > 3 Tage)',
                    'Erste Gefährdungsbeurteilung: vor Aufnahme der Tätigkeit bzw. bei neuen Arbeitsplätzen',
                    'UV-Beitragszahlung: gemäß Beitragsbescheid (vierteljährlich üblich)',
                ),
            ),
            'links' => array(
                array(
                    'label' => 'DGUV – Unfallversicherung',
                    'url'   => 'https://www.dguv.de/',
                ),
                array(
                    'label' => 'BG BAU – Zuständigkeit prüfen',
                    'url'   => 'https://www.bg-bau.de/',
                ),
            ),
        );
    }
}
