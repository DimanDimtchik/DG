<?php
declare(strict_types=1);

/**
 * Number Range Formula.
 */
final class NumberRangeFormula
{
        /**
     * fingerprint
     * @param array $document
     * @return string
     */
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

        /**
     * pattern
     * @param array $document
     * @return string
     */
    public static function pattern(array $document): string
    {
        $prefix = trim((string) ($document['prefix'] ?? ''));
        $middle = trim((string) ($document['number_pattern'] ?? '{NR}'));

        return $prefix . ($middle !== '' ? $middle : '{NR}') . trim((string) ($document['suffix'] ?? ''));
    }

        /**
     * Liefert die Anzeigebezeichnung
     * @param array $document
     * @return string
     */
    public static function label(array $document): string
    {
        return self::pattern($document);
    }
}
