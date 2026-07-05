<?php
declare(strict_types=1);

final class NumberRangeFormula
{
    /** @param array<string, mixed> $document */
    public static function fingerprint(array $document): string
    {
        $parts = [
            'prefix' => trim((string) ($document['prefix'] ?? '')),
            'number_pattern' => trim((string) ($document['number_pattern'] ?? '{NR}')),
            'suffix' => trim((string) ($document['suffix'] ?? '')),
            'number_display' => (string) ($document['number_display'] ?? 'decimal'),
            'number_pad' => (int) ($document['number_pad'] ?? 0),
            'country_code' => strtoupper(trim((string) ($document['country_code'] ?? 'DE'))),
        ];

        return hash('sha256', json_encode($parts, JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $document */
    public static function pattern(array $document): string
    {
        $prefix = trim((string) ($document['prefix'] ?? ''));
        $middle = trim((string) ($document['number_pattern'] ?? '{NR}'));

        return $prefix . ($middle !== '' ? $middle : '{NR}') . trim((string) ($document['suffix'] ?? ''));
    }

    /** @param array<string, mixed> $document */
    public static function label(array $document): string
    {
        return self::pattern($document);
    }
}
