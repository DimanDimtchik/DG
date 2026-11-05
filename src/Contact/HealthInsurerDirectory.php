<?php
declare(strict_types=1);

/** Gesetzliche (GKV) und private (PKV) Krankenversicherer — Vorschlagsliste beim Tippen. */
final class HealthInsurerDirectory
{
    /** @var list<array{name: string, type: string, code?: string, website?: string, appointment_url?: string}>|null */
    private static ?array $insurers = null;

    /**
     * insurers.
     *
     * @return list<array{name: string, type: string, code?: string, website?: string, appointment_url?: string}>
     */
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

    /**
     * searchByName
     * @param string $input Formulardaten
     * @return list<array{name: string, type: string, code?: string, website?: string, appointment_url?: string}>
     */
    public static function searchByName(string $input): array
    {
        $query = mb_strtolower(trim($input));
        if (mb_strlen($query) < 2) {
            return [];
        }

        $matches = [];
        foreach (self::insurers() as $insurer) {
            $name = mb_strtolower((string) ($insurer['name'] ?? ''));
            $code = (string) ($insurer['code'] ?? '');
            if (
                str_contains($name, $query)
                || preg_match('/\b' . preg_quote($query, '/') . '/u', $name)
                || ($code !== '' && str_contains($code, $query))
            ) {
                $matches[] = [
                    'name' => (string) ($insurer['name'] ?? ''),
                    'type' => (string) ($insurer['type'] ?? 'gkv'),
                    'code' => $code,
                    'website' => (string) ($insurer['website'] ?? ''),
                    'appointment_url' => (string) ($insurer['appointment_url'] ?? ''),
                ];
            }
            if (count($matches) >= 10) {
                break;
            }
        }

        return $matches;
    }

        /**
     * suggestResponse
     * @param string $value Eingabewert
     * @return array<string, mixed>
     */
    public static function suggestResponse(string $value): array
    {
        return ['matches' => self::searchByName($value)];
    }
}
