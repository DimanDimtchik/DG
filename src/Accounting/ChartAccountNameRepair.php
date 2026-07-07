<?php
declare(strict_types=1);

/**
 * Heuristische Korrektur typischer DATEV-PDF-Import-Artefakte in Kontonamen.
 */
final class ChartAccountNameRepair
{
    public const VERSION = '2026-07-06-artifacts';

    public static function repair(string $name): string
    {
        $name = str_replace("\u{00AD}", '', $name);
        $name = preg_replace('/\s+/u', ' ', trim($name)) ?? $name;
        $name = trim($name, " -");

        $rules = [
            '/\bHilfsund\b/u' => 'Hilfs- und',
            '/\bf\s+ür\b/ui' => 'für',
            '/\bo\s+hne\b/ui' => 'ohne',
            '/\bVorsteue\s+r\b/ui' => 'Vorsteuer',
            '/\bAn\s+dere\b/u' => 'Andere',
            '/^B\s+estandsveränderungen/ui' => 'Bestandsveränderungen',
            '/^Bestandsveränderungen\s*-\s*/ui' => 'Bestandsveränderungen ',
            '/Vorsteuerabzug\d+\)/ui' => 'Vorsteuerabzug',
            '/\s+\d{1,2}\)\s*$/u' => '',
            '/\begenständen\b/ui' => 'Gegenständen',
            '/\bWar en\b/u' => 'Waren',
            '/aus tungsverbindlichkeiten/ui' => 'aus Käufen von Finanzanlagen bei Leistungsverbindlichkeiten',
            '/in Ausführung befindlicher$/ui' => 'in Ausführung befindliche Bauaufträge',
            '/Gesamthand\d+\)/ui' => 'Gesamthand',
            '/\bG K\b/u' => '§ 7g EStG',
            '/ in Entwicklung Sachanlagen\b/ui' => ' in Entwicklung',
            '/ in Entwicklung Geschäfts\b/ui' => ' in Entwicklung',
            '/\s+HGB Sonstige Vermögensgegenstände\b.*$/ui' => '',
            '/nach und ähnliche\b/ui' => 'nach § 231 Abs. 2 Satz 2 HGB Zinsen und ähnliche Erträge',
            '/(ohne|mit)\s+sässigen Unternehmers\s+(ohne|mit)\s+/ui' => '$1 ',
            '/\s+gen$/ui' => '',
            '/RohHilfs-/ui' => 'Roh-, Hilfs-',
            '/Forderungen nach § 11 Abs\. 1 Forderungen/ui' => 'Forderungen nach § 11 Abs. 1 EStG',
            '/verbundenen\/$/ui' => 'verbundenen Unternehmen',
            '/\s+und Leistungen\s+und Leistungen/ui' => ' und Leistungen',
            '/\s+für Investitionen\s+für\b/ui' => ' für Investitionen',
            '/\s+(und|oder|für|mit|nach|aus|an|auf|in|der|die|das|zum|zur|ohne|gegenüber|vom)$/ui' => '',
        ];

        foreach ($rules as $pattern => $replacement) {
            $name = preg_replace($pattern, $replacement, $name) ?? $name;
        }

        foreach (['Erlösschmälerungen', 'Unentgeltliche Zuwendung'] as $prefix) {
            $escaped = preg_quote($prefix, '/');
            if (preg_match('/^(' . $escaped . '.+?)(?:\s+' . $escaped . ')/ui', $name, $match) === 1) {
                $name = trim($match[1]);
            }
        }

        if (preg_match('/ansässigen Unternehmers/ui', $name) === 1) {
            $parts = preg_split('/\s+(?=ansässigen Unternehmers)/ui', $name, 2);
            if (is_array($parts) && count($parts) === 2 && preg_match('/\d\s*%/u', $parts[0]) === 1) {
                $name = trim($parts[0]);
            }
        }

        $bleedMarkers = [
            '/\s+mögensgegenstände\b.*$/ui',
            '/\s+bindlichkeiten\b.*$/ui',
            '/\s+gensgegenstände\b.*$/ui',
            '/\s+gen-stände\b.*$/ui',
            '/\s+genstände\b.*$/ui',
            '/\s+oder Sonstige\b.*$/ui',
            '/\s+oder Andere\b.*$/ui',
            '/\s+oder Verbindlichkeiten\b.*$/ui',
            '/\s+oder Forderungen\b.*$/ui',
            '/\s+nisse und\b.*$/ui',
            '/\s+In Arbeit be-.*$/ui',
        ];

        foreach ($bleedMarkers as $pattern) {
            $name = preg_replace($pattern, '', $name) ?? $name;
        }

        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name, " -");
    }
}
