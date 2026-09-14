<?php
declare(strict_types=1);

final class AcademyAccessMode
{
    public const COMPARE = 'compare';
    public const SOFT = 'soft';
    public const HARD = 'hard';

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::COMPARE => 'Vergleich (parallel am CRM)',
            self::SOFT => 'Weich (Hinweis)',
            self::HARD => 'Hart (Modul gesperrt)',
        ];
    }

    public static function sanitize(string $mode): string
    {
        $mode = strtolower(trim($mode));

        return isset(self::labels()[$mode]) ? $mode : self::COMPARE;
    }
}
