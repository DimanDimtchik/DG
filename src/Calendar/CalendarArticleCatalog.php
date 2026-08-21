<?php
declare(strict_types=1);

/** Artikel vs. Leistung im gemeinsamen Katalog. */
final class CalendarArticleCatalog
{
    public const KIND_SERVICE = 'service';
    public const KIND_PRODUCT = 'product';

    /**
     * Methode kinds.
     * @return array<string, mixed>
     */
    public static function kinds(): array
    {
        return [
            self::KIND_SERVICE => 'Leistung',
            self::KIND_PRODUCT => 'Artikel',
        ];
    }

    /**
     * Führt aus: normalize kind.
     * @param string $value
     * @return string
     */
    public static function normalizeKind(string $value): string
    {
        $value = strtolower(trim($value));
        if (in_array($value, ['product', 'artikel', 'article', 'ware', 'material'], true)) {
            return self::KIND_PRODUCT;
        }

        return self::KIND_SERVICE;
    }

    /**
     * Methode number range type.
     * @param string $kind
     * @return string
     */
    public static function numberRangeType(string $kind): string
    {
        return self::normalizeKind($kind) === self::KIND_PRODUCT ? 'article' : 'service';
    }

    /**
     * Methode kind label.
     * @param string $kind
     * @return string
     */
    public static function kindLabel(string $kind): string
    {
        return self::kinds()[self::normalizeKind($kind)] ?? 'Leistung';
    }
}
