<?php
declare(strict_types=1);

final class ThemeColor
{
    public static function sanitizeHex(string $color, string $fallback): string
    {
        $color = trim($color);
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color) !== 1) {
            return strtolower($fallback);
        }

        if (strlen($color) === 4) {
            $color = '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3];
        }

        return strtolower($color);
    }

    public static function darken(string $hex, float $amount): string
    {
        $amount = max(0.0, min(1.0, $amount));

        return self::mixHex($hex, '#000000', 1.0 - $amount);
    }

    public static function mixHex(string $hex1, string $hex2, float $weight): string
    {
        $weight = max(0.0, min(1.0, $weight));
        $c1 = self::hexToRgb(self::sanitizeHex($hex1, '#000000'));
        $c2 = self::hexToRgb(self::sanitizeHex($hex2, '#000000'));

        $r = (int) round($c1['r'] * $weight + $c2['r'] * (1 - $weight));
        $g = (int) round($c1['g'] * $weight + $c2['g'] * (1 - $weight));
        $b = (int) round($c1['b'] * $weight + $c2['b'] * (1 - $weight));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /** @return array{r: int, g: int, b: int} */
    public static function hexToRgb(string $hex): array
    {
        $hex = ltrim(self::sanitizeHex($hex, '#000000'), '#');

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }

    public static function withAlpha(string $hex, float $alpha): string
    {
        $alpha = max(0.0, min(1.0, $alpha));
        $rgb = self::hexToRgb($hex);

        return sprintf('rgba(%d, %d, %d, %s)', $rgb['r'], $rgb['g'], $rgb['b'], rtrim(rtrim(number_format($alpha, 2, '.', ''), '0'), '.'));
    }

    public static function focusRing(string $primaryHex): string
    {
        return self::withAlpha($primaryHex, 0.25);
    }
}
