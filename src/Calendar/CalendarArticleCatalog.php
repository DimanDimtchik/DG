<?php
declare(strict_types=1);

/** Artikel vs. Leistung im gemeinsamen Katalog. */
final class CalendarArticleCatalog
{
    public const KIND_SERVICE = 'service';
    public const KIND_PRODUCT = 'product';

    /** @return array<string, string> */
    public static function kinds(): array
    {
        return [
            self::KIND_SERVICE => 'Leistung',
            self::KIND_PRODUCT => 'Artikel',
        ];
    }

    public static function normalizeKind(string $value): string
    {
        $value = strtolower(trim($value));
        if (in_array($value, ['product', 'artikel', 'article', 'ware', 'material'], true)) {
            return self::KIND_PRODUCT;
        }

        return self::KIND_SERVICE;
    }

    public static function numberRangeType(string $kind): string
    {
        return self::normalizeKind($kind) === self::KIND_PRODUCT ? 'article' : 'service';
    }

    public static function kindLabel(string $kind): string
    {
        return self::kinds()[self::normalizeKind($kind)] ?? 'Leistung';
    }
}
