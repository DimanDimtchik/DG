<?php
declare(strict_types=1);

/** Garantie / Gewährleistung an Katalogartikeln. */
final class ArticleWarranty
{
    public const KIND_NONE = 'none';
    public const KIND_GEWAEHRLEISTUNG = 'gewaehrleistung';
    public const KIND_GARANTIE = 'garantie';
    public const KIND_BOTH = 'both';

    /**
     * @return array<string, string>
     */
    public static function kindOptions(): array
    {
        return [
            self::KIND_NONE => 'Keine Angabe',
            self::KIND_GEWAEHRLEISTUNG => 'Gewährleistung (gesetzlich)',
            self::KIND_GARANTIE => 'Garantie (zusätzliche Zusage)',
            self::KIND_BOTH => 'Gewährleistung und Garantie',
        ];
    }

    public static function hint(): string
    {
        return 'Gewährleistung = gesetzliche Rechte bei Mängeln. '
            . 'Garantie = zusätzliche, freiwillige Zusage des Verkäufers/Herstellers (Dauer und Umfang im Freitext).';
    }

    public static function sanitizeKind(string $kind): string
    {
        $kind = strtolower(trim($kind));

        return isset(self::kindOptions()[$kind]) ? $kind : self::KIND_NONE;
    }

    public static function label(string $kind): string
    {
        return self::kindOptions()[self::sanitizeKind($kind)] ?? $kind;
    }
}
