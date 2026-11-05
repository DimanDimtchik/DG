<?php
declare(strict_types=1);

/**
 * Extracts and rewrites simple SVG presentation values (colors, stroke-width).
 */
final class MediaSvgEditor
{
    private const COLOR_ATTRS = ['fill', 'stroke', 'stop-color', 'flood-color', 'lighting-color', 'color'];
    private const SKIP_COLOR_VALUES = [
        'none',
        'transparent',
        'currentcolor',
        'inherit',
        'initial',
        'unset',
        'context-fill',
        'context-stroke',
    ];

    public const SIZE_MIN = 16;
    public const SIZE_MAX = 4096;

    /** @var list<int> */
    public const SIZE_PRESETS = [64, 150, 300];

    /**
     * @return array{
     *   colors: list<array{id: string, value: string, hex: ?string, count: int}>,
     *   stroke_widths: list<array{id: string, value: string, count: int}>,
     *   size: array{width: ?int, height: ?int, width_raw: ?string, height_raw: ?string, view_box: ?string}
     * }
     */
    public static function analyze(string $svg): array
    {
        $colors = [];
        $widths = [];

        foreach (self::findColorMatches($svg) as $match) {
            $raw = $match['value'];
            $key = self::colorKey($raw);
            if ($key === null) {
                continue;
            }
            if (!isset($colors[$key])) {
                $colors[$key] = [
                    'id' => $key,
                    'value' => $raw,
                    'hex' => self::toHex($raw),
                    'count' => 0,
                ];
            }
            $colors[$key]['count']++;
        }

        foreach (self::findStrokeWidthMatches($svg) as $match) {
            $raw = trim($match['value']);
            if ($raw === '') {
                continue;
            }
            $key = mb_strtolower($raw);
            if (!isset($widths[$key])) {
                $widths[$key] = [
                    'id' => $key,
                    'value' => $raw,
                    'count' => 0,
                ];
            }
            $widths[$key]['count']++;
        }

        return [
            'colors' => array_values($colors),
            'stroke_widths' => array_values($widths),
            'size' => self::readRootSize($svg),
        ];
    }

    /**
     * @param array<string, string> $colorMap original value (or id) => new color
     * @param array<string, string> $strokeWidthMap original value (or id) => new width
     * @param array{width?: int|string, height?: int|string}|null $size display size in px
     */
    public static function apply(string $svg, array $colorMap, array $strokeWidthMap, ?array $size = null): string
    {
        $normalizedColors = [];
        foreach ($colorMap as $from => $to) {
            $from = trim((string) $from);
            $to = trim((string) $to);
            if ($from === '' || $to === '') {
                continue;
            }
            if (!self::isWritableColor($to)) {
                throw new InvalidArgumentException('Ungültige Zielfarbe: ' . $to);
            }
            $normalizedColors[$from] = $to;
            $hex = self::toHex($from);
            if ($hex !== null) {
                $normalizedColors[$hex] = $to;
                $normalizedColors[strtoupper($hex)] = $to;
            }
        }

        $normalizedWidths = [];
        foreach ($strokeWidthMap as $from => $to) {
            $from = trim((string) $from);
            $to = trim((string) $to);
            if ($from === '' || $to === '') {
                continue;
            }
            if (!preg_match('/^[0-9]*\.?[0-9]+(%|px|em|rem|pt)?$/i', $to)) {
                throw new InvalidArgumentException('Ungültige Linienbreite: ' . $to);
            }
            $normalizedWidths[mb_strtolower($from)] = $to;
            $normalizedWidths[$from] = $to;
        }

        if ($normalizedColors !== []) {
            uksort($normalizedColors, static fn ($a, $b): int => mb_strlen((string) $b) <=> mb_strlen((string) $a));
            foreach ($normalizedColors as $from => $to) {
                $svg = self::replaceColorToken($svg, (string) $from, (string) $to);
            }
        }

        if ($normalizedWidths !== []) {
            uksort($normalizedWidths, static fn ($a, $b): int => mb_strlen((string) $b) <=> mb_strlen((string) $a));
            foreach ($normalizedWidths as $from => $to) {
                $svg = self::replaceStrokeWidthToken($svg, (string) $from, (string) $to);
            }
        }

        if ($size !== null) {
            $svg = self::applyRootSize($svg, $size);
        }

        return $svg;
    }

    /**
     * Display size of the root &lt;svg&gt; (width/height attrs, else viewBox).
     *
     * @return array{width: ?int, height: ?int, width_raw: ?string, height_raw: ?string, view_box: ?string}
     */
    public static function readRootSize(string $svg): array
    {
        $tag = self::rootSvgOpenTag($svg);
        if ($tag === null) {
            return [
                'width' => null,
                'height' => null,
                'width_raw' => null,
                'height_raw' => null,
                'view_box' => null,
            ];
        }

        $widthRaw = self::attrValue($tag, 'width');
        $heightRaw = self::attrValue($tag, 'height');
        $viewBox = self::attrValue($tag, 'viewBox') ?? self::attrValue($tag, 'viewbox');
        $width = self::parseLengthPx($widthRaw);
        $height = self::parseLengthPx($heightRaw);

        if (($width === null || $height === null) && is_string($viewBox)) {
            $parts = preg_split('/[\s,]+/', trim($viewBox)) ?: [];
            if (count($parts) >= 4) {
                $vbW = (int) round((float) $parts[2]);
                $vbH = (int) round((float) $parts[3]);
                if ($width === null && $vbW > 0) {
                    $width = $vbW;
                }
                if ($height === null && $vbH > 0) {
                    $height = $vbH;
                }
            }
        }

        return [
            'width' => $width,
            'height' => $height,
            'width_raw' => $widthRaw,
            'height_raw' => $heightRaw,
            'view_box' => $viewBox,
        ];
    }

    /**
     * @param array{width?: int|string, height?: int|string} $size
     */
    private static function applyRootSize(string $svg, array $size): string
    {
        $width = self::normalizeSizePx($size['width'] ?? null);
        $height = self::normalizeSizePx($size['height'] ?? null);
        if ($width === null || $height === null) {
            throw new InvalidArgumentException(
                'SVG-Größe muss zwischen ' . self::SIZE_MIN . ' und ' . self::SIZE_MAX . ' Pixel liegen.'
            );
        }

        $current = self::readRootSize($svg);
        if ($current['width'] === $width && $current['height'] === $height
            && $current['width_raw'] !== null && $current['height_raw'] !== null) {
            return $svg;
        }

        return (string) preg_replace_callback(
            '/<svg\b[^>]*>/i',
            static function (array $m) use ($width, $height, $current): string {
                $tag = $m[0];
                $viewBox = $current['view_box'];
                if ($viewBox === null || trim($viewBox) === '') {
                    $vbW = $current['width'] ?? $width;
                    $vbH = $current['height'] ?? $height;
                    $viewBox = '0 0 ' . $vbW . ' ' . $vbH;
                    $tag = self::setOrAddAttr($tag, 'viewBox', $viewBox);
                }
                $tag = self::setOrAddAttr($tag, 'width', (string) $width);
                $tag = self::setOrAddAttr($tag, 'height', (string) $height);

                return $tag;
            },
            $svg,
            1
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function normalizeSizePx(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        $n = (int) round((float) $value);
        if ($n < self::SIZE_MIN || $n > self::SIZE_MAX) {
            return null;
        }

        return $n;
    }

    private static function rootSvgOpenTag(string $svg): ?string
    {
        if (preg_match('/<svg\b[^>]*>/i', $svg, $m) !== 1) {
            return null;
        }

        return $m[0];
    }

    private static function attrValue(string $tag, string $name): ?string
    {
        if (preg_match('/\b' . preg_quote($name, '/') . '\s*=\s*("|\')([^"\']*)\1/i', $tag, $m) !== 1) {
            return null;
        }
        $value = trim((string) $m[2]);

        return $value !== '' ? $value : null;
    }

    private static function parseLengthPx(?string $raw): ?int
    {
        if ($raw === null) {
            return null;
        }
        if (preg_match('/^([0-9]*\.?[0-9]+)\s*(px)?$/i', trim($raw), $m) !== 1) {
            return null;
        }
        $n = (int) round((float) $m[1]);

        return $n > 0 ? $n : null;
    }

    private static function setOrAddAttr(string $tag, string $name, string $value): string
    {
        $quoted = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if (preg_match('/\b' . preg_quote($name, '/') . '\s*=\s*("|\')[^"\']*\1/i', $tag) === 1) {
            return (string) preg_replace(
                '/\b' . preg_quote($name, '/') . '\s*=\s*("|\')[^"\']*\1/i',
                $name . '="' . $quoted . '"',
                $tag,
                1
            );
        }

        return (string) preg_replace('/<svg\b/i', '<svg ' . $name . '="' . $quoted . '"', $tag, 1);
    }

    /**
     * Read SVG markup from disk.
     *
     * @throws RuntimeException When the file is missing or empty
     */
    public static function readFile(string $path): string
    {
        $content = @file_get_contents($path);
        if ($content === false || trim($content) === '') {
            throw new RuntimeException('SVG-Datei konnte nicht gelesen werden.');
        }

        return $content;
    }

    /**
     * @return list<array{value: string}>
     */
    private static function findColorMatches(string $svg): array
    {
        $found = [];
        $attrList = implode('|', array_map(static fn (string $a): string => preg_quote($a, '/'), self::COLOR_ATTRS));

        if (preg_match_all('/\b(?:' . $attrList . ')\s*=\s*["\']([^"\']+)["\']/i', $svg, $m)) {
            foreach ($m[1] as $value) {
                $found[] = ['value' => trim((string) $value)];
            }
        }

        if (preg_match_all('/(?:^|[;{"\s])(?:' . $attrList . ')\s*:\s*([^;}"\']+)/i', $svg, $m2)) {
            foreach ($m2[1] as $value) {
                $found[] = ['value' => trim((string) $value)];
            }
        }

        return $found;
    }

    /**
     * @return list<array{value: string}>
     */
    private static function findStrokeWidthMatches(string $svg): array
    {
        $found = [];
        if (preg_match_all('/\bstroke-width\s*=\s*["\']([^"\']+)["\']/i', $svg, $m)) {
            foreach ($m[1] as $value) {
                $found[] = ['value' => trim((string) $value)];
            }
        }
        if (preg_match_all('/(?:^|[;{"\s])stroke-width\s*:\s*([^;}"\']+)/i', $svg, $m2)) {
            foreach ($m2[1] as $value) {
                $found[] = ['value' => trim((string) $value)];
            }
        }

        return $found;
    }

    /**
     * Stable key for a color value (hex when possible); null for none/url()/keywords.
     */
    private static function colorKey(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (str_starts_with(mb_strtolower($raw), 'url(')) {
            return null;
        }
        if (in_array(mb_strtolower($raw), self::SKIP_COLOR_VALUES, true)) {
            return null;
        }
        $hex = self::toHex($raw);

        return $hex ?? mb_strtolower($raw);
    }

    /**
     * Whether $value is acceptable as a replacement color.
     */
    private static function isWritableColor(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }
        if (in_array(mb_strtolower($value), self::SKIP_COLOR_VALUES, true)) {
            return true;
        }
        if (self::toHex($value) !== null) {
            return true;
        }

        return (bool) preg_match('/^[a-z]{3,20}$/i', $value);
    }

    /**
     * Normalize #rgb / #rrggbb / rgb() to lowercase #rrggbb.
     */
    private static function toHex(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value, $m) === 1) {
            $hex = strtolower($m[1]);
            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }
            if (strlen($hex) === 8) {
                $hex = substr($hex, 0, 6);
            }

            return '#' . $hex;
        }

        if (preg_match('/^rgba?\(\s*([0-9.]+)\s*,\s*([0-9.]+)\s*,\s*([0-9.]+)/i', $value, $m) === 1) {
            $r = max(0, min(255, (int) round((float) $m[1])));
            $g = max(0, min(255, (int) round((float) $m[2])));
            $b = max(0, min(255, (int) round((float) $m[3])));

            return sprintf('#%02x%02x%02x', $r, $g, $b);
        }

        return null;
    }

    /**
     * Replace a color token in presentation attributes and inline styles.
     */
    private static function replaceColorToken(string $svg, string $from, string $to): string
    {
        $quoted = preg_quote($from, '/');
        $attrList = implode('|', array_map(static fn (string $a): string => preg_quote($a, '/'), self::COLOR_ATTRS));

        $svg = preg_replace_callback(
            '/(\b(?:' . $attrList . ')\s*=\s*)(["\'])(' . $quoted . ')\2/i',
            static fn (array $m): string => $m[1] . $m[2] . $to . $m[2],
            $svg
        ) ?? $svg;

        $svg = preg_replace_callback(
            '/((?:^|[;{"\s])(?:' . $attrList . ')\s*:\s*)(' . $quoted . ')(?=\s*[;}"\']|$)/i',
            static fn (array $m): string => $m[1] . $to,
            $svg
        ) ?? $svg;

        return $svg;
    }

    /**
     * Replace a stroke-width token in attributes and inline styles.
     */
    private static function replaceStrokeWidthToken(string $svg, string $from, string $to): string
    {
        $quoted = preg_quote($from, '/');

        $svg = preg_replace_callback(
            '/(\bstroke-width\s*=\s*)(["\'])(' . $quoted . ')\2/i',
            static fn (array $m): string => $m[1] . $m[2] . $to . $m[2],
            $svg
        ) ?? $svg;

        $svg = preg_replace_callback(
            '/((?:^|[;{"\s])stroke-width\s*:\s*)(' . $quoted . ')(?=\s*[;}"\']|$)/i',
            static fn (array $m): string => $m[1] . $to,
            $svg
        ) ?? $svg;

        return $svg;
    }
}
