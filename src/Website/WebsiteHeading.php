<?php
declare(strict_types=1);

/**
 * Überschriften-Blöcke: HTML-Ebene (h1–h6) und optionale Textgröße.
 *
 * Ebene und Optik sind getrennt — Suchmaschinen lesen das Tag,
 * die Schriftgröße steuert nur die Darstellung.
 */
final class WebsiteHeading
{
    public const LEVELS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

    public const PRESETS = ['', 'large', 'medium', 'small', 'custom'];

    /** @var array<string, float> Multiplikator relativ zur Standardgröße der Ebene */
    private const PRESET_FACTORS = [
        'large' => 1.25,
        'medium' => 1.0,
        'small' => 0.8,
    ];

    public static function normalizeLevel(mixed $level): string
    {
        $level = strtolower(trim((string) $level));
        if (in_array($level, self::LEVELS, true)) {
            return $level;
        }

        return 'h2';
    }

    public static function levelNumber(string $level): int
    {
        return (int) substr(self::normalizeLevel($level), 1);
    }

    /**
     * @param array<string, mixed> $block
     * @return array{size_preset: string, font_size: string, font_size_unit: string}
     */
    public static function normalizeSizeFields(array $block): array
    {
        $preset = strtolower(trim((string) ($block['size_preset'] ?? '')));
        if (!in_array($preset, self::PRESETS, true)) {
            $preset = '';
        }

        $unit = strtolower(trim((string) ($block['font_size_unit'] ?? '%')));
        if ($unit !== 'px' && $unit !== '%') {
            $unit = '%';
        }

        $raw = trim((string) ($block['font_size'] ?? ''));
        $size = '';
        if ($raw !== '' && is_numeric($raw)) {
            $num = (float) $raw;
            if ($num > 0 && $num <= 500) {
                $size = $unit === 'px'
                    ? (string) (int) round($num)
                    : rtrim(rtrim(number_format($num, 2, '.', ''), '0'), '.');
            }
        }

        if ($preset !== 'custom') {
            $size = '';
            $unit = '%';
        } elseif ($size === '') {
            $preset = '';
            $unit = '%';
        }

        return [
            'size_preset' => $preset,
            'font_size' => $size,
            'font_size_unit' => $unit,
        ];
    }

    /**
     * Inline-CSS für font-size, oder leer = Browser/CSS-Standard der Ebene.
     * Prozent bezieht sich auf --ws-heading-base der jeweiligen h1–h6.
     *
     * @param array<string, mixed> $block
     */
    public static function fontSizeStyle(array $block): string
    {
        $norm = self::normalizeSizeFields($block);
        $preset = $norm['size_preset'];

        if ($preset === '' || $preset === 'medium') {
            if ($preset === 'medium') {
                return 'font-size:var(--ws-heading-base)';
            }

            return '';
        }

        if (isset(self::PRESET_FACTORS[$preset]) && $preset !== 'medium') {
            $factor = self::PRESET_FACTORS[$preset];

            return 'font-size:calc(var(--ws-heading-base)*' . self::formatFactor($factor) . ')';
        }

        if ($preset === 'custom' && $norm['font_size'] !== '') {
            if ($norm['font_size_unit'] === 'px') {
                return 'font-size:' . $norm['font_size'] . 'px';
            }
            $pct = (float) $norm['font_size'];

            return 'font-size:calc(var(--ws-heading-base)*' . self::formatFactor($pct / 100) . ')';
        }

        return '';
    }

    /**
     * @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    public static function normalizeBlock(array $block): array
    {
        $block['level'] = self::normalizeLevel($block['level'] ?? 'h2');
        $size = self::normalizeSizeFields($block);
        if ($size['size_preset'] === '') {
            unset($block['size_preset'], $block['font_size'], $block['font_size_unit']);
        } else {
            $block['size_preset'] = $size['size_preset'];
            if ($size['size_preset'] === 'custom') {
                $block['font_size'] = $size['font_size'];
                $block['font_size_unit'] = $size['font_size_unit'];
            } else {
                unset($block['font_size'], $block['font_size_unit']);
            }
        }

        return $block;
    }

    private static function formatFactor(float $factor): string
    {
        $s = rtrim(rtrim(number_format($factor, 4, '.', ''), '0'), '.');

        return $s === '' ? '0' : $s;
    }
}
