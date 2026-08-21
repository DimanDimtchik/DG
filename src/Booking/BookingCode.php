<?php

declare(strict_types=1);

/**
 * Generates and normalizes public booking reference codes (e.g. DG-7K2M9P4Q).
 */
final class BookingCode
{
    public const PREFIX = 'DG-';

    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function generate(): string
    {
        $code = self::PREFIX;
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < 8; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }

    /** Normalize user input; empty string if invalid. */
    public static function normalize(string $input): string
    {
        $raw = strtoupper(trim($input));
        $raw = str_replace([' ', '_'], ['', '-'], $raw);
        if ($raw === '') {
            return '';
        }
        if (!str_starts_with($raw, self::PREFIX)) {
            $raw = self::PREFIX . ltrim($raw, '-');
        }
        if (!preg_match('/^DG-[A-Z0-9]{8}$/', $raw)) {
            return '';
        }

        return $raw;
    }

    public static function isValid(string $code): bool
    {
        return self::normalize($code) !== '';
    }
}
