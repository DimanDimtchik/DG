<?php
declare(strict_types=1);

/**
 * Statischer AfA-/Anlagen-Katalog (Orientierung, keine Inventarführung).
 * Gegenstandsbereiche und Nutzungsdauern angelehnt an Lexware Office / Lexoffice-Standard-Anlagetypen.
 *
 * Quelle (Orientierung): Computerhardware 1 J. (seit 2021 Sofortabschreibung möglich),
 * Software 3 J., Pkw 6 J., Lkw 9 J., Büromöbel 13 J., Werkzeuge 3–5 J., Maschinen 8–14 J.,
 * Betriebsvorrichtungen 5–10 J., Gebäude 33–50 J., GWG Sofort bis 800 € netto.
 */
final class AfaCatalog
{
    public const GWG_NETTO_LIMIT = 800.0;

    /**
     * @return array<string, string> bereich_id => Label
     */
    public static function areas(): array
    {
        return [
            'it' => 'Computerhardware & IT',
            'software' => 'Software & Lizenzen',
            'pkw' => 'Pkw / Kraftfahrzeuge',
            'lkw' => 'Lkw & Transportfahrzeuge',
            'buero' => 'Büromöbel & Ausstattung',
            'werkzeug' => 'Werkzeuge & Kleingeräte',
            'maschinen' => 'Maschinen & Anlagen',
            'betriebsvorrichtung' => 'Betriebsvorrichtungen',
            'gebaeude' => 'Gebäude & Immobilien',
            'gwg' => 'Geringwertige Wirtschaftsgüter (GWG)',
        ];
    }

    /**
     * Kurzhinweise je Bereich (für UI).
     *
     * @return array<string, string>
     */
    public static function areaHints(): array
    {
        return [
            'it' => 'Regulär 1 Jahr — seit 2021 Sofortabschreibung von Computerhardware unabhängig vom Wert möglich.',
            'software' => 'Regulär 3 Jahre (Betriebssysteme, ERP/CRM, Standard-Software).',
            'pkw' => 'Regulär 6 Jahre. Sonderregeln bei Leasing/Privatanteil beachten.',
            'lkw' => 'Regulär 9 Jahre (Lieferwagen, Lkw, schwere Anhänger).',
            'buero' => 'Regulär 13 Jahre (Schreibtische, Stühle, Schränke, Küchenzeilen).',
            'werkzeug' => 'Typisch 3–5 Jahre (Handwerkzeuge, Messgeräte) — Lebensdauer anpassen.',
            'maschinen' => 'Typisch 8–14 Jahre je AfA-Tabelle — Lebensdauer anpassen.',
            'betriebsvorrichtung' => 'Typisch 5–10 Jahre (Klima, Alarmanlage, Wallbox — sofern nicht Gebäudebestandteil).',
            'gebaeude' => '33–50 Jahre nur Gebäudeanteil; Grund und Boden nicht abschreibbar.',
            'gwg' => 'Selbstständig nutzbar bis 800 € netto: Sofortabschreibung im Anschaffungsjahr.',
        ];
    }

    /**
     * Geräte-Presets je Lexoffice-naher Kategorie.
     *
     * @return list<array{
     *   id: string,
     *   area: string,
     *   label: string,
     *   useful_life_years: int,
     *   default_net: float,
     *   hint: string
     * }>
     */
    public static function presets(): array
    {
        return [
            // —— Computerhardware & IT (1 Jahr / Sofortabschreibung seit 2021) ——
            [
                'id' => 'laptop',
                'area' => 'it',
                'label' => 'Laptop / Notebook',
                'useful_life_years' => 1,
                'default_net' => 1400.0,
                'hint' => 'IT-Hardware: 1 Jahr / Sofortabschreibung (seit 2021) möglich.',
            ],
            [
                'id' => 'pc',
                'area' => 'it',
                'label' => 'PC / Desktop',
                'useful_life_years' => 1,
                'default_net' => 1200.0,
                'hint' => 'IT-Hardware: 1 Jahr / Sofortabschreibung (seit 2021) möglich.',
            ],
            [
                'id' => 'monitor',
                'area' => 'it',
                'label' => 'Monitor',
                'useful_life_years' => 1,
                'default_net' => 350.0,
                'hint' => 'IT-Hardware: 1 Jahr. Unter 800 € netto oft auch als GWG.',
            ],
            [
                'id' => 'drucker',
                'area' => 'it',
                'label' => 'Drucker / Multifunktionsgerät',
                'useful_life_years' => 1,
                'default_net' => 450.0,
                'hint' => 'IT-Hardware (Lexoffice): 1 Jahr / Sofortabschreibung möglich.',
            ],
            [
                'id' => 'server',
                'area' => 'it',
                'label' => 'Server',
                'useful_life_years' => 1,
                'default_net' => 4500.0,
                'hint' => 'Server als Computerhardware: 1 Jahr / Sofortabschreibung (seit 2021) möglich.',
            ],
            [
                'id' => 'smartphone',
                'area' => 'it',
                'label' => 'Smartphone / Tablet (IT)',
                'useful_life_years' => 1,
                'default_net' => 900.0,
                'hint' => 'IT-Endgerät. Unter 800 € netto: Kategorie GWG wählen.',
            ],

            // —— Software & Lizenzen (3 Jahre) ——
            [
                'id' => 'software_standard',
                'area' => 'software',
                'label' => 'Standard-Software / Lizenz',
                'useful_life_years' => 3,
                'default_net' => 800.0,
                'hint' => 'Betriebssysteme, Office, Standardlizenzen — AfA 3 Jahre.',
            ],
            [
                'id' => 'software_erp',
                'area' => 'software',
                'label' => 'ERP / CRM-Lizenz',
                'useful_life_years' => 3,
                'default_net' => 5000.0,
                'hint' => 'Kauf-Lizenz (nicht laufendes Abo) — AfA 3 Jahre.',
            ],
            [
                'id' => 'software_website',
                'area' => 'software',
                'label' => 'Website / Webshop (Kauf)',
                'useful_life_years' => 3,
                'default_net' => 3500.0,
                'hint' => 'Standard-Website als Anschaffung — AfA 3 Jahre.',
            ],

            // —— Pkw (6 Jahre) ——
            [
                'id' => 'pkw',
                'area' => 'pkw',
                'label' => 'Geschäftswagen (Pkw)',
                'useful_life_years' => 6,
                'default_net' => 35000.0,
                'hint' => 'AfA 6 Jahre. Privatanteil / 1 %-Regelung: Faktor „nicht abziehbar“ nutzen.',
            ],
            [
                'id' => 'pkw_elektro',
                'area' => 'pkw',
                'label' => 'Elektro-Pkw',
                'useful_life_years' => 6,
                'default_net' => 42000.0,
                'hint' => 'AfA 6 Jahre; Sonderregeln (z. B. Sonder-AfA) hier nicht modelliert.',
            ],
            [
                'id' => 'pkw_pool',
                'area' => 'pkw',
                'label' => 'Poolfahrzeug',
                'useful_life_years' => 6,
                'default_net' => 28000.0,
                'hint' => 'AfA 6 Jahre.',
            ],

            // —— Lkw & Transport (9 Jahre) ——
            [
                'id' => 'lieferwagen',
                'area' => 'lkw',
                'label' => 'Lieferwagen / Kastenwagen',
                'useful_life_years' => 9,
                'default_net' => 38000.0,
                'hint' => 'Transportfahrzeuge — AfA 9 Jahre.',
            ],
            [
                'id' => 'lkw',
                'area' => 'lkw',
                'label' => 'Lastkraftwagen',
                'useful_life_years' => 9,
                'default_net' => 65000.0,
                'hint' => 'Lkw — AfA 9 Jahre.',
            ],
            [
                'id' => 'anhaenger',
                'area' => 'lkw',
                'label' => 'Schwerer Anhänger',
                'useful_life_years' => 9,
                'default_net' => 12000.0,
                'hint' => 'Schwere Anhänger — AfA 9 Jahre.',
            ],

            // —— Büromöbel (13 Jahre) ——
            [
                'id' => 'schreibtisch',
                'area' => 'buero',
                'label' => 'Schreibtisch',
                'useful_life_years' => 13,
                'default_net' => 600.0,
                'hint' => 'Büromöbel — AfA 13 Jahre. Unter 800 €: ggf. GWG.',
            ],
            [
                'id' => 'buerostuhl',
                'area' => 'buero',
                'label' => 'Ergonomischer Bürostuhl',
                'useful_life_years' => 13,
                'default_net' => 450.0,
                'hint' => 'Büromöbel — AfA 13 Jahre. Unter 800 €: ggf. GWG.',
            ],
            [
                'id' => 'aktenschrank',
                'area' => 'buero',
                'label' => 'Aktenschrank',
                'useful_life_years' => 13,
                'default_net' => 900.0,
                'hint' => 'Büromöbel — AfA 13 Jahre.',
            ],
            [
                'id' => 'konferenz',
                'area' => 'buero',
                'label' => 'Konferenztisch / -möbel',
                'useful_life_years' => 13,
                'default_net' => 2500.0,
                'hint' => 'Büroausstattung — AfA 13 Jahre.',
            ],
            [
                'id' => 'buerokueche',
                'area' => 'buero',
                'label' => 'Küchenzeile (Büro)',
                'useful_life_years' => 13,
                'default_net' => 4000.0,
                'hint' => 'Büroausstattung — AfA 13 Jahre.',
            ],

            // —— Werkzeuge 3–5 Jahre ——
            [
                'id' => 'handwerkzeug',
                'area' => 'werkzeug',
                'label' => 'Handwerkzeug-Set',
                'useful_life_years' => 3,
                'default_net' => 400.0,
                'hint' => 'Werkzeuge typisch 3–5 Jahre; hier 3 Jahre. Unter 800 €: ggf. GWG.',
            ],
            [
                'id' => 'akkuschrauber',
                'area' => 'werkzeug',
                'label' => 'Akkuschrauber / Bohrmaschine',
                'useful_life_years' => 4,
                'default_net' => 350.0,
                'hint' => 'Kleingeräte — Mittelwert 4 Jahre.',
            ],
            [
                'id' => 'messgeraet',
                'area' => 'werkzeug',
                'label' => 'Messgerät',
                'useful_life_years' => 5,
                'default_net' => 1200.0,
                'hint' => 'Werkzeuge / Messgeräte — bis 5 Jahre.',
            ],

            // —— Maschinen 8–14 Jahre ——
            [
                'id' => 'produktionsmaschine',
                'area' => 'maschinen',
                'label' => 'Produktionsmaschine',
                'useful_life_years' => 10,
                'default_net' => 25000.0,
                'hint' => 'Maschinen oft 8–14 Jahre; hier 10 Jahre — bitte AfA-Tabelle prüfen.',
            ],
            [
                'id' => 'fraese',
                'area' => 'maschinen',
                'label' => 'Fräse / Bearbeitung',
                'useful_life_years' => 12,
                'default_net' => 18000.0,
                'hint' => 'Maschinen & Anlagen — Lebensdauer an AfA-Tabelle anpassen.',
            ],
            [
                'id' => 'verpackung',
                'area' => 'maschinen',
                'label' => 'Verpackungsanlage',
                'useful_life_years' => 10,
                'default_net' => 32000.0,
                'hint' => 'Maschinen & Anlagen — typisch 8–14 Jahre.',
            ],

            // —— Betriebsvorrichtungen 5–10 Jahre ——
            [
                'id' => 'klimaanlage',
                'area' => 'betriebsvorrichtung',
                'label' => 'Klimaanlage (nicht Gebäude)',
                'useful_life_years' => 8,
                'default_net' => 5500.0,
                'hint' => 'Betriebsvorrichtung 5–10 Jahre — sofern nicht fest im Gebäude.',
            ],
            [
                'id' => 'alarmanlage',
                'area' => 'betriebsvorrichtung',
                'label' => 'Alarmanlage',
                'useful_life_years' => 8,
                'default_net' => 2800.0,
                'hint' => 'Betriebsvorrichtung — typisch 5–10 Jahre.',
            ],
            [
                'id' => 'wallbox',
                'area' => 'betriebsvorrichtung',
                'label' => 'Ladestation / Wallbox',
                'useful_life_years' => 8,
                'default_net' => 2000.0,
                'hint' => 'E-Ladestation als Betriebsvorrichtung (nicht Gebäudebestandteil).',
            ],

            // —— Gebäude 33–50 Jahre ——
            [
                'id' => 'buero_gebaeude',
                'area' => 'gebaeude',
                'label' => 'Bürogebäude (Gebäudeanteil)',
                'useful_life_years' => 33,
                'default_net' => 250000.0,
                'hint' => 'Nur Gebäudeanteil (nicht Grundstück). AfA oft 33 Jahre (3 %).',
            ],
            [
                'id' => 'lagerhalle',
                'area' => 'gebaeude',
                'label' => 'Lagerhalle (Gebäudeanteil)',
                'useful_life_years' => 33,
                'default_net' => 180000.0,
                'hint' => 'Gebäudeanteil; Grund und Boden nicht abschreiben. Nutzungsdauer 33–50 J.',
            ],

            // —— GWG Sofort ——
            [
                'id' => 'gwg_smartphone',
                'area' => 'gwg',
                'label' => 'Smartphone (GWG)',
                'useful_life_years' => 1,
                'default_net' => 700.0,
                'hint' => 'GWG bis 800 € netto — Sofortabschreibung.',
            ],
            [
                'id' => 'gwg_stuhl',
                'area' => 'gwg',
                'label' => 'Bürostuhl (GWG)',
                'useful_life_years' => 1,
                'default_net' => 399.0,
                'hint' => 'GWG bis 800 € netto — Sofortabschreibung.',
            ],
            [
                'id' => 'gwg_sonstig',
                'area' => 'gwg',
                'label' => 'Sonstiges GWG',
                'useful_life_years' => 1,
                'default_net' => 500.0,
                'hint' => 'Selbstständig nutzbar, Netto ≤ 800 € — Sofortabschreibung.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function presetById(string $id): ?array
    {
        foreach (self::presets() as $p) {
            if ($p['id'] === $id) {
                return $p;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function presetsForArea(string $area): array
    {
        return array_values(array_filter(
            self::presets(),
            static fn (array $p): bool => $p['area'] === $area
        ));
    }

    /** Standard-Preset-ID für einen Bereich (erstes Preset). */
    public static function defaultPresetIdForArea(string $area): string
    {
        $list = self::presetsForArea($area);
        if ($list === []) {
            return 'pc';
        }

        return (string) $list[0]['id'];
    }
}
