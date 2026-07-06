<?php
declare(strict_types=1);

/** Standard-Bedeutungen der Kontenziffern nach SKR. */
final class SkrDigitLegend
{
    /**
     * @return array<int, string>
     */
    public static function forSkr(string $skrType): array
    {
        return match (ChartOfAccountsSettings::sanitizeSkrType($skrType)) {
            'skr04' => self::skr04(),
            default => self::skr03(),
        };
    }

    /** @return array<int, string> */
    public static function skr03(): array
    {
        return [
            1 => 'Anlagevermögen / Finanz- und Privatkonten',
            2 => 'Umlaufvermögen (Forderungen, Geld, Vorsteuer)',
            3 => 'Verbindlichkeiten, Rückstellungen, Eigenkapital',
            4 => 'Betriebliche Aufwendungen (Personal, Material, sonstige)',
            5 => 'Weitere Aufwendungen (Abschreibungen, Steuern, Zinsen)',
            6 => 'Weitere Aufwendungen / neutrale Posten',
            7 => 'Bestandsveränderungen, aktivierte Eigenleistungen',
            8 => 'Erlöse und sonstige betriebliche Erträge',
            9 => 'Vortragskonten, statistische Konten',
        ];
    }

    /** @return array<int, string> */
    public static function skr04(): array
    {
        return [
            1 => 'Aktiva (Anlage- und Umlaufvermögen)',
            2 => 'Passiva (Eigenkapital, Rückstellungen, Verbindlichkeiten)',
            3 => 'Betriebliche Aufwendungen',
            4 => 'Betriebliche Aufwendungen (Fortsetzung)',
            5 => 'Betriebliche Aufwendungen (Abschreibungen, Steuern)',
            6 => 'Sonstige Aufwendungen / Zinsen',
            7 => 'Bestandsveränderungen',
            8 => 'Betriebliche Erträge',
            9 => 'Vortragskonten, statistische Konten',
        ];
    }

    /**
     * @param array<int, string> $overrides
     * @return array<int, string>
     */
    public static function mergeWithOverrides(string $skrType, array $overrides): array
    {
        $base = self::forSkr($skrType);
        foreach ($overrides as $digit => $text) {
            $digit = (int) $digit;
            if ($digit >= 1 && $digit <= 9 && trim((string) $text) !== '') {
                $base[$digit] = trim((string) $text);
            }
        }

        return $base;
    }
}
