<?php
declare(strict_types=1);

final class CompanyAddressTypes
{
    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            'hauptsitz' => 'Zentrale / Hauptsitz',
            'niederlassung' => 'Niederlassung',
            'lager' => 'Lager',
            'buero' => 'Büro',
            'verkauf' => 'Verkaufsstelle / Ladengeschäft',
            'werkstatt' => 'Werkstatt / Produktion',
            'sonstiges' => 'Sonstiger Standort',
        ];
    }
}
