<?php
declare(strict_types=1);

/**
 * Globale Kostensätze für Rezeptur-Kalkulation (Strom, Raum, Lohn).
 */
final class RecipeCostSettings
{
    private const STORE_KEY = 'recipe_cost_rates';

    /**
     * @return array{electricity_eur_per_kwh: float, room_eur_per_m2_h: float, wage_eur_per_h: float}
     */
    public static function defaults(): array
    {
        return [
            'electricity_eur_per_kwh' => 0.30,
            'room_eur_per_m2_h' => 0.50,
            'wage_eur_per_h' => 25.00,
        ];
    }

    /**
     * @return array{electricity_eur_per_kwh: float, room_eur_per_m2_h: float, wage_eur_per_h: float}
     */
    public static function get(): array
    {
        $defaults = self::defaults();
        if (!Database::isConfigured()) {
            return $defaults;
        }
        $stored = SettingsStore::get(self::STORE_KEY, $defaults);

        return [
            'electricity_eur_per_kwh' => self::asFloat($stored['electricity_eur_per_kwh'] ?? $defaults['electricity_eur_per_kwh']),
            'room_eur_per_m2_h' => self::asFloat($stored['room_eur_per_m2_h'] ?? $defaults['room_eur_per_m2_h']),
            'wage_eur_per_h' => self::asFloat($stored['wage_eur_per_h'] ?? $defaults['wage_eur_per_h']),
        ];
    }

    /**
     * @return array{electricity_eur_per_kwh: string, room_eur_per_m2_h: string, wage_eur_per_h: string}
     */
    public static function forForm(): array
    {
        $r = self::get();

        return [
            'electricity_eur_per_kwh' => self::fmt($r['electricity_eur_per_kwh']),
            'room_eur_per_m2_h' => self::fmt($r['room_eur_per_m2_h']),
            'wage_eur_per_h' => self::fmt($r['wage_eur_per_h']),
        ];
    }

    /**
     * @param array<string, mixed> $post
     */
    public static function saveFromPost(array $post): void
    {
        $rates = [
            'electricity_eur_per_kwh' => self::parseNonNeg((string) ($post['electricity_eur_per_kwh'] ?? ''), 'Strompreis'),
            'room_eur_per_m2_h' => self::parseNonNeg((string) ($post['room_eur_per_m2_h'] ?? ''), 'Raumkosten'),
            'wage_eur_per_h' => self::parseNonNeg((string) ($post['wage_eur_per_h'] ?? ''), 'Lohn'),
        ];
        SettingsStore::set(self::STORE_KEY, $rates);
    }

    private static function asFloat(mixed $v): float
    {
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        $s = trim(str_replace([' ', ','], ['', '.'], (string) $v));

        return is_numeric($s) ? (float) $s : 0.0;
    }

    private static function parseNonNeg(string $raw, string $label): float
    {
        $raw = trim(str_replace([' ', ','], ['', '.'], $raw));
        if ($raw === '' || !is_numeric($raw)) {
            throw new InvalidArgumentException($label . ': ungültige Zahl.');
        }
        $n = (float) $raw;
        if ($n < 0) {
            throw new InvalidArgumentException($label . ' darf nicht negativ sein.');
        }

        return $n;
    }

    private static function fmt(float $n): string
    {
        $s = rtrim(rtrim(sprintf('%.4f', $n), '0'), '.');

        return $s === '' ? '0' : $s;
    }
}
