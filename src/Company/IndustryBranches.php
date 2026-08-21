<?php
declare(strict_types=1);

/** Branchen nach WZ 2008 (Finanzamt) und Berufsgenossenschaften. */
final class IndustryBranches
{
    /**
     * Liefert Branchengruppen.
     *
     * @return array<string, array<string, array{label: string, wz: string, bg: string}>>
     */
        public static function groups(): array
    {
        return [
            'Land- und Forstwirtschaft, Fischerei' => [
                'agrar' => ['label' => 'Landwirtschaft, Jagd', 'wz' => '01', 'bg' => 'BGN'],
                'forst' => ['label' => 'Forstwirtschaft', 'wz' => '02', 'bg' => 'BGHW'],
                'fischerei' => ['label' => 'Fischerei und Aquakultur', 'wz' => '03', 'bg' => 'BGN'],
            ],
            'Industrie und Handwerk' => [
                'produktion' => ['label' => 'Verarbeitendes Gewerbe', 'wz' => '10-33', 'bg' => 'BGHW'],
                'metall_elektro' => ['label' => 'Metall-, Maschinen- und Elektroindustrie', 'wz' => '25-27', 'bg' => 'BGHW'],
                'bau' => ['label' => 'Baugewerbe', 'wz' => '41-43', 'bg' => 'BG BAU'],
                'handwerk_allgemein' => ['label' => 'Handwerk (allgemein)', 'wz' => '43', 'bg' => 'BGHW'],
                'elektro_handwerk' => ['label' => 'Elektroinstallation / Elektrotechnik', 'wz' => '43.21', 'bg' => 'BGHW'],
                'pv_solar' => ['label' => 'Photovoltaik / Solartechnik', 'wz' => '43.21 / 35.11', 'bg' => 'BGHW'],
            ],
            'Handel, Logistik, Gastgewerbe' => [
                'handel' => ['label' => 'Handel (Einzel- und Großhandel)', 'wz' => '45-47', 'bg' => 'BGHW'],
                'logistik' => ['label' => 'Verkehr und Lagerei', 'wz' => '49-53', 'bg' => 'BG Verkehr'],
                'gastronomie' => ['label' => 'Gastronomie und Beherbergung', 'wz' => '55-56', 'bg' => 'BGN'],
            ],
            'Dienstleistungen' => [
                'it_software' => ['label' => 'IT, Software, Telekommunikation', 'wz' => '62-63', 'bg' => 'VBG'],
                'beratung' => ['label' => 'Unternehmensberatung', 'wz' => '70.22', 'bg' => 'VBG'],
                'steuer_recht' => ['label' => 'Steuerberatung, Rechtsberatung, Wirtschaftsprüfung', 'wz' => '69 / 70.22', 'bg' => 'VBG'],
                'architektur_ingenieur' => ['label' => 'Architektur- und Ingenieurbüros', 'wz' => '71', 'bg' => 'VBG'],
                'werbung' => ['label' => 'Werbung und Marktforschung', 'wz' => '73', 'bg' => 'VBG'],
                'reinigung' => ['label' => 'Gebäudereinigung', 'wz' => '81.21', 'bg' => 'BGN'],
                'gesundheit' => ['label' => 'Gesundheits- und Sozialwesen', 'wz' => '86-88', 'bg' => 'BGW'],
                'bildung' => ['label' => 'Erziehung und Unterricht', 'wz' => '85', 'bg' => 'VBG'],
            ],
            'Freie Berufe und Kreativwirtschaft' => [
                'freiberufler' => ['label' => 'Freiberufler (allgemein)', 'wz' => '—', 'bg' => 'VBG'],
                'arzt_zahnarzt' => ['label' => 'Arzt / Zahnarzt / Heilberuf', 'wz' => '86.2', 'bg' => 'BGW'],
                'design_foto' => ['label' => 'Design, Fotografie, Medien', 'wz' => '74', 'bg' => 'VBG'],
            ],
            'Sonstiges' => [
                'sonstiges' => ['label' => 'Sonstige Branche', 'wz' => '—', 'bg' => '—'],
            ],
        ];
    }

    /**
     * Liefert Branchen-zu-UV-Träger-Zuordnung.
     *
     * @return array<string, string>
     */
        public static function industryUvMap(): array
    {
        $map = [];
        foreach (self::groups() as $items) {
            foreach (array_keys($items) as $key) {
                $carrier = UvCarriers::suggest_for_industry($key);
                if ($carrier !== null && $carrier !== '') {
                    $map[$key] = $carrier;
                }
            }
        }

        return $map;
    }
}
