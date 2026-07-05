<?php
declare(strict_types=1);

final class CalendarFrontendTheme
{
    /** @return array<string, string> */
    public static function colors(): array
    {
        return CalendarAppearanceSettings::forForm();
    }

    /** @return array<string, string> */
    public static function cssVariables(): array
    {
        $colors = self::colors();
        $primary = self::sanitizeHex($colors['primary_color']);
        $hover = self::sanitizeHex($colors['button_hover']);
        $onPrimary = self::contrastTextColor($primary);

        return [
            '--tk-cal-primary' => $primary,
            '--tk-cal-primary-hover' => $hover,
            '--tk-cal-on-primary' => $onPrimary,
            '--tk-cal-surface' => '#ffffff',
            '--tk-cal-surface-alt' => self::sanitizeHex($colors['slot_bg']),
            '--tk-cal-border' => self::mixHex($primary, '#e2e8f0', 0.12),
            '--tk-cal-text' => '#1e293b',
            '--tk-cal-muted' => '#64748b',
            '--tk-cal-today' => self::sanitizeHex($colors['slot_selected_bg']),
            '--tk-cal-radius' => '12px',
            '--tk-cal-shadow' => '0 8px 30px rgba(15, 23, 42, 0.08)',
            '--tk-slot-bg' => self::sanitizeHex($colors['slot_bg']),
            '--tk-slot-hover' => self::sanitizeHex($colors['slot_hover']),
            '--tk-slot-selected-bg' => self::sanitizeHex($colors['slot_selected_bg']),
            '--tk-slot-selected-border' => self::sanitizeHex($colors['slot_selected_border']),
            '--tk-slot-booked-bg' => self::sanitizeHex($colors['booked_bg']),
            '--tk-slot-text' => '#1e293b',
            '--tk-focus-ring' => self::mixHex($primary, '#ffffff', 0.35),
        ];
    }

    public static function wrapperSelectors(): string
    {
        return '.tk-cal, .tk-abs, .tk-book, .dg-cal-appearance-preview';
    }

    public static function inlineCss(): string
    {
        $decl = [];
        foreach (self::cssVariables() as $name => $value) {
            $decl[] = $name . ':' . $value;
        }

        return self::wrapperSelectors() . '{' . implode(';', $decl) . '}';
    }

    public static function wrapperStyleAttribute(): string
    {
        $parts = [];
        foreach (self::cssVariables() as $name => $value) {
            $parts[] = $name . ':' . $value;
        }

        return ' style="' . htmlspecialchars(implode(';', $parts), ENT_QUOTES, 'UTF-8') . '"';
    }

    public static function sanitizeHex(string $color, ?string $fallback = null): string
    {
        $color = trim($color);
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color) !== 1) {
            return $fallback ?? CalendarColorPresets::defaultColors()['primary_color'];
        }

        if (strlen($color) === 4) {
            $color = '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3];
        }

        return strtolower($color);
    }

    public static function contrastTextColor(string $hex): string
    {
        $hex = ltrim(self::sanitizeHex($hex), '#');
        if (strlen($hex) !== 6) {
            return '#ffffff';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminance > 0.58 ? '#1e293b' : '#ffffff';
    }

    public static function mixHex(string $hex1, string $hex2, float $weight): string
    {
        $weight = max(0.0, min(1.0, $weight));
        $c1 = self::hexToRgb(self::sanitizeHex($hex1));
        $c2 = self::hexToRgb(self::sanitizeHex($hex2));

        $r = (int) round($c1['r'] * $weight + $c2['r'] * (1 - $weight));
        $g = (int) round($c1['g'] * $weight + $c2['g'] * (1 - $weight));
        $b = (int) round($c1['b'] * $weight + $c2['b'] * (1 - $weight));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /** @return array{r: int, g: int, b: int} */
    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }
}
