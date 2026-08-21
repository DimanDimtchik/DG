<?php
declare(strict_types=1);

/** CRM- und Kontakt-Rollen (kompatibel mit dg-user-plugin). */
final class CrmRole
{
    /**
     * Liefert Auswahloptionen.
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'administrator' => 'Administrator',
            'dg_eigenmitarbeiter' => 'Mitarbeiter',
            'dg_kunde' => 'Kunde',
        ];
    }

    /**
     * Liefert die Anzeigebezeichnung.
     * @param string|null $slug
     * @return string
     */
    public static function label(?string $slug): string
    {
        $slug = self::normalize($slug);
        if ($slug === '') {
            return '—';
        }

        return self::options()[$slug] ?? ucfirst($slug);
    }

    /**
     * Normalisiert den Eingabewert.
     * @param string|null $slug
     * @return string
     */
    public static function normalize(?string $slug): string
    {
        $slug = trim((string) $slug);
        if ($slug === '') {
            return 'dg_kunde';
        }

        return match ($slug) {
            'mitarbeiter', 'employee' => 'dg_eigenmitarbeiter',
            'kunde', 'lieferant', 'customer' => 'dg_kunde',
            'admin', 'administrator' => 'administrator',
            default => $slug,
        };
    }

    /**
     * Prüft, ob der Wert gültig ist.
     * @param string $slug
     * @return bool
     */
    public static function isValid(string $slug): bool
    {
        return isset(self::options()[self::normalize($slug)]);
    }

    /**
     * Prüft, ob die Rolle Mitarbeiterdaten erfordert.
     * @param string $slug
     * @return bool
     */
    public static function hasEmployeeProfile(string $slug): bool
    {
        $slug = self::normalize($slug);

        return in_array($slug, ['dg_eigenmitarbeiter', 'administrator'], true);
    }
}
