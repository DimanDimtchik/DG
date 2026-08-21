<?php
declare(strict_types=1);

/**
 * Invoice Number Builder.
 */
final class InvoiceNumberBuilder
{
        /**
     * Erzeugt DG-Hinweise für ein SKR-Konto
     * @param array $document
     * @param array $context
     * @return string
     */
    public static function build(array $document, array $context = []): string
    {
        $sequence = isset($context['sequence'])
            ? max(0, (int) $context['sequence'])
            : self::sequenceCounter($document);

        $resolveContext = array_merge($context, [
            'number_display' => $document['number_display'] ?? 'decimal',
            'number_pad' => (int) ($document['number_pad'] ?? 0),
        ]);

        $prefix = InvoiceNumberTokens::resolveString((string) ($document['prefix'] ?? ''), $resolveContext, $sequence);
        $middle = InvoiceNumberTokens::resolveString((string) ($document['number_pattern'] ?? '{NR}'), $resolveContext, $sequence);
        $suffix = InvoiceNumberTokens::resolveString((string) ($document['suffix'] ?? ''), $resolveContext, $sequence);

        return $prefix . $middle . $suffix;
    }

    /**
     * @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    public static function previewContext(array $document): array
    {
        $country = strtoupper(trim((string) ($document['country_code'] ?? '')));
        if (strlen($country) !== 2) {
            $company = CompanySettings::config();
            $country = strtoupper(trim((string) ($company['country'] ?? 'DE')));
        }
        if (strlen($country) !== 2) {
            $country = 'DE';
        }

        return [
            'timestamp' => time(),
            'country_code' => $country,
            'preview' => true,
            'customer_id' => '',
            'company_id' => CompanySettings::numberRangeCompanyId(),
            'number_display' => $document['number_display'] ?? 'decimal',
            'number_pad' => (int) ($document['number_pad'] ?? 0),
        ];
    }

        /**
     * allocationContext
     * @param array $document
     * @param array $overrides
     * @return array<string, mixed>
     */
    public static function allocationContext(array $document, array $overrides = []): array
    {
        return array_merge(self::previewContext($document), $overrides, [
            'preview' => false,
            'company_id' => CompanySettings::numberRangeCompanyId(),
        ]);
    }

        /**
     * sequenceCounter
     * @param array $document
     * @return int
     */
    public static function sequenceCounter(array $document): int
    {
        $raw = $document['counter'] ?? $document['number'] ?? '1';
        if (is_numeric($raw)) {
            return max(0, (int) $raw);
        }

        return max(0, (int) preg_replace('/\D/', '', (string) $raw));
    }

    /**
     * sanitizeSequenceCounter
     * @param mixed $input Formulardaten
     * @return string
     */
    public static function sanitizeSequenceCounter(mixed $input): string
    {
        if ($input === '' || $input === null) {
            return '1';
        }

        return (string) max(0, (int) $input);
    }

    /**
     * formatSequenceValue
     * @param int $value Eingabewert
     * @param string $base
     * @param int $pad
     * @return string
     */
    public static function formatSequenceValue(int $value, string $base = 'decimal', int $pad = 0): string
    {
        $int = max(0, $value);
        $base = in_array($base, ['decimal', 'hex', 'octal'], true) ? $base : 'decimal';

        return match ($base) {
            'hex' => strtoupper(dechex($int)),
            'octal' => decoct($int),
            default => $pad > 0 ? str_pad((string) $int, $pad, '0', STR_PAD_LEFT) : (string) $int,
        };
    }

    /**
     * incrementSequence
     * @param int $current
     * @return int
     */
    public static function incrementSequence(int $current): int
    {
        return max(0, $current) + 1;
    }

        /**
     * peekNext
     * @param array $document
     * @param bool $preview
     * @return array{number: string, sequence: int, sequence_display: string, next_sequence: int}
     */
    public static function peekNext(array $document, bool $preview = true): array
    {
        $sequence = self::sequenceCounter($document);
        $base = (string) ($document['number_display'] ?? 'decimal');
        $pad = max(0, (int) ($document['number_pad'] ?? 0));
        $context = $preview
            ? self::previewContext($document)
            : self::allocationContext($document);

        return [
            'number' => self::build($document, array_merge($context, ['sequence' => $sequence])),
            'sequence' => $sequence,
            'sequence_display' => self::formatSequenceValue($sequence, $base, $pad),
            'next_sequence' => self::incrementSequence($sequence),
        ];
    }
}
