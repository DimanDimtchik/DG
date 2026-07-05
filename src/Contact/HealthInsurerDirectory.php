<?php
declare(strict_types=1);

/** Gesetzliche (GKV) und private (PKV) Krankenversicherer — Vorschlagsliste beim Tippen. */
final class HealthInsurerDirectory
{
    /** @var list<array{name: string, type: string, code?: string}>|null */
    private static ?array $insurers = null;

    /** @return list<array{name: string, type: string, code?: string}> */
    public static function insurers(): array
    {
        if (self::$insurers !== null) {
            return self::$insurers;
        }

        $path = DG_ROOT . '/assets/data/german-health-insurers.json';
        if (!is_readable($path)) {
            self::$insurers = [];

            return self::$insurers;
        }

        $raw = json_decode((string) file_get_contents($path), true);
        self::$insurers = is_array($raw) ? $raw : [];

        return self::$insurers;
    }

    /** @return list<array{name: string, type: string, code?: string}> */
    public static function searchByName(string $input): array
    {
        $query = mb_strtolower(trim($input));
        if (mb_strlen($query) < 2) {
            return [];
        }

        $matches = [];
        foreach (self::insurers() as $insurer) {
            $name = mb_strtolower($insurer['name'] ?? '');
            $code = (string) ($insurer['code'] ?? '');
            if (
                str_contains($name, $query)
                || preg_match('/\b' . preg_quote($query, '/') . '/u', $name)
                || ($code !== '' && str_contains($code, $query))
            ) {
                $matches[] = $insurer;
            }
            if (count($matches) >= 10) {
                break;
            }
        }

        return $matches;
    }

    /** @return array<string, mixed> */
    public static function suggestResponse(string $value): array
    {
        return ['matches' => self::searchByName($value)];
    }
}
