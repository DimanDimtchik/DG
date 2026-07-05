<?php
declare(strict_types=1);

/** CRM- und Kontakt-Rollen (kompatibel mit dg-user-plugin). */
final class CrmRole
{
    /** @return array<string, string> slug => Label */
    public static function options(): array
    {
        return [
            'administrator' => 'Administrator',
            'dg_eigenmitarbeiter' => 'Mitarbeiter',
            'dg_kunde' => 'Kunde',
        ];
    }

    public static function label(?string $slug): string
    {
        $slug = self::normalize($slug);
        if ($slug === '') {
            return '—';
        }

        return self::options()[$slug] ?? ucfirst($slug);
    }

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

    public static function isValid(string $slug): bool
    {
        return isset(self::options()[self::normalize($slug)]);
    }

    /** Mitarbeiterdaten für diese Kontakt-Rollen (nicht für Kunde). */
    public static function hasEmployeeProfile(string $slug): bool
    {
        $slug = self::normalize($slug);

        return in_array($slug, ['dg_eigenmitarbeiter', 'administrator'], true);
    }
}
