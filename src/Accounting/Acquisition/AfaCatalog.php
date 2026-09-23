<?php
declare(strict_types=1);

/**
 * Statischer AfA-/Anlagen-Katalog für den Anschaffungsrechner (Orientierung, keine Inventarführung).
 * Nutzungsdauern angelehnt an typische AfA-Tabellen / lexoffice-ähnliche Geräteklassen.
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
            'it' => 'IT / EDV',
            'buero' => 'Büroeinrichtung',
            'fuhrpark' => 'Fuhrpark',
            'sonstige' => 'Sonstige Betriebsausstattung',
        ];
    }

    /**
     * Geräte-Presets.
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
            [
                'id' => 'pc',
                'area' => 'it',
                'label' => 'PC / Notebook',
                'useful_life_years' => 3,
                'default_net' => 1200.0,
                'hint' => 'Typische AfA EDV 3 Jahre (linear).',
            ],
            [
                'id' => 'server',
                'area' => 'it',
                'label' => 'Server',
                'useful_life_years' => 5,
                'default_net' => 4500.0,
                'hint' => 'Server oft 3–5 Jahre; hier 5 Jahre als Vorsatz.',
            ],
            [
                'id' => 'drucker',
                'area' => 'it',
                'label' => 'Drucker / Multifunktionsgerät',
                'useful_life_years' => 6,
                'default_net' => 800.0,
                'hint' => 'Büromaschinen oft 6 Jahre.',
            ],
            [
                'id' => 'monitor',
                'area' => 'it',
                'label' => 'Monitor / Peripherie',
                'useful_life_years' => 3,
                'default_net' => 350.0,
                'hint' => 'Oft GWG-fähig bei Netto ≤ 800 €.',
            ],
            [
                'id' => 'buero_moebel',
                'area' => 'buero',
                'label' => 'Büromöbel',
                'useful_life_years' => 13,
                'default_net' => 2500.0,
                'hint' => 'Büroeinrichtung typisch 13 Jahre.',
            ],
            [
                'id' => 'buero_lampe',
                'area' => 'buero',
                'label' => 'Beleuchtung / Technik Büro',
                'useful_life_years' => 10,
                'default_net' => 600.0,
                'hint' => 'Je nach Gerät; ggf. GWG.',
            ],
            [
                'id' => 'pkw',
                'area' => 'fuhrpark',
                'label' => 'Firmenwagen (Pkw)',
                'useful_life_years' => 6,
                'default_net' => 35000.0,
                'hint' => 'AfA Pkw 6 Jahre. Privatanteil / 1 %-Regelung nicht modelliert — Faktor „nicht abziehbar“ nutzen.',
            ],
            [
                'id' => 'transporter',
                'area' => 'fuhrpark',
                'label' => 'Transporter / Nutzfahrzeug',
                'useful_life_years' => 9,
                'default_net' => 42000.0,
                'hint' => 'Nutzfahrzeuge oft länger als Pkw.',
            ],
            [
                'id' => 'werkzeug',
                'area' => 'sonstige',
                'label' => 'Werkzeug / Gerät',
                'useful_life_years' => 5,
                'default_net' => 1500.0,
                'hint' => 'Sonstige Betriebsausstattung — Lebensdauer anpassen.',
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
}
