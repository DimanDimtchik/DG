<?php
declare(strict_types=1);

/**
 * Kuratierte erweiterte Block-Styles/Attribute für den Website-Builder.
 * Allowlist only — kein freies Custom-CSS.
 */
final class WebsiteBlockAdvanced
{
    private const DISPLAY_VALUES = ['block', 'inline', 'inline-block', 'flex', 'none'];
    private const VISIBILITY_VALUES = ['visible', 'hidden'];
    private const TEXT_ALIGN_VALUES = ['left', 'center', 'right', 'justify'];
    private const BORDER_STYLES = ['none', 'solid', 'dashed', 'dotted'];

    /**
     * @param mixed $advanced
     * @return array{
     *   display?: array<string, string>,
     *   colors?: array<string, string>,
     *   border?: array<string, string>,
     *   attrs?: array<string, string>
     * }
     */
    public static function normalize(mixed $advanced): array
    {
        if (!is_array($advanced)) {
            return [];
        }

        $out = [];

        $displayIn = is_array($advanced['display'] ?? null) ? $advanced['display'] : [];
        $display = [];
        $displayVal = self::pickEnum((string) ($displayIn['display'] ?? ''), self::DISPLAY_VALUES);
        if ($displayVal !== '') {
            $display['display'] = $displayVal;
        }
        $visibility = self::pickEnum((string) ($displayIn['visibility'] ?? ''), self::VISIBILITY_VALUES);
        if ($visibility !== '') {
            $display['visibility'] = $visibility;
        }
        $opacity = self::sanitizeOpacity((string) ($displayIn['opacity'] ?? ''));
        if ($opacity !== '') {
            $display['opacity'] = $opacity;
        }
        foreach (['width', 'maxWidth', 'margin', 'padding'] as $lenKey) {
            $len = self::sanitizeCssLength((string) ($displayIn[$lenKey] ?? ''));
            if ($len !== '') {
                $display[$lenKey] = $len;
            }
        }
        $textAlign = self::pickEnum((string) ($displayIn['textAlign'] ?? ''), self::TEXT_ALIGN_VALUES);
        if ($textAlign !== '') {
            $display['textAlign'] = $textAlign;
        }
        if ($display !== []) {
            $out['display'] = $display;
        }

        $colorsIn = is_array($advanced['colors'] ?? null) ? $advanced['colors'] : [];
        $colors = [];
        foreach (['color', 'background'] as $colorKey) {
            $hex = self::sanitizeHexColor((string) ($colorsIn[$colorKey] ?? ''));
            if ($hex !== '') {
                $colors[$colorKey] = $hex;
            }
        }
        if ($colors !== []) {
            $out['colors'] = $colors;
        }

        $borderIn = is_array($advanced['border'] ?? null) ? $advanced['border'] : [];
        $border = [];
        $bWidth = self::sanitizeCssLength((string) ($borderIn['width'] ?? ''));
        if ($bWidth !== '') {
            $border['width'] = $bWidth;
        }
        $bStyle = self::pickEnum((string) ($borderIn['style'] ?? ''), self::BORDER_STYLES);
        if ($bStyle !== '' && $bStyle !== 'none') {
            $border['style'] = $bStyle;
        } elseif ($bStyle === 'none' && ($bWidth !== '' || self::sanitizeHexColor((string) ($borderIn['color'] ?? '')) !== '')) {
            $border['style'] = 'none';
        }
        $bColor = self::sanitizeHexColor((string) ($borderIn['color'] ?? ''));
        if ($bColor !== '') {
            $border['color'] = $bColor;
        }
        $radius = self::sanitizeCssLength((string) ($borderIn['radius'] ?? ''));
        if ($radius !== '') {
            $border['radius'] = $radius;
        }
        if ($border !== []) {
            $out['border'] = $border;
        }

        $attrsIn = is_array($advanced['attrs'] ?? null) ? $advanced['attrs'] : [];
        $attrs = [];
        $id = self::sanitizeHtmlId((string) ($attrsIn['id'] ?? ''));
        if ($id !== '') {
            $attrs['id'] = $id;
        }
        $className = self::sanitizeClassList((string) ($attrsIn['className'] ?? ''));
        if ($className !== '') {
            $attrs['className'] = $className;
        }
        $title = self::sanitizePlainAttr((string) ($attrsIn['title'] ?? ''), 200);
        if ($title !== '') {
            $attrs['title'] = $title;
        }
        $ariaLabel = self::sanitizePlainAttr((string) ($attrsIn['ariaLabel'] ?? ''), 200);
        if ($ariaLabel !== '') {
            $attrs['ariaLabel'] = $ariaLabel;
        }
        if ($attrs !== []) {
            $out['attrs'] = $attrs;
        }

        return $out;
    }

    public static function isEmpty(array $advanced): bool
    {
        return $advanced === [];
    }

    /**
     * @param array<string, mixed> $advanced already normalized
     */
    public static function toInlineCss(array $advanced): string
    {
        $parts = [];
        $display = is_array($advanced['display'] ?? null) ? $advanced['display'] : [];
        if (!empty($display['display'])) {
            $parts[] = 'display:' . $display['display'];
        }
        if (!empty($display['visibility'])) {
            $parts[] = 'visibility:' . $display['visibility'];
        }
        if (isset($display['opacity']) && $display['opacity'] !== '') {
            $parts[] = 'opacity:' . $display['opacity'];
        }
        if (!empty($display['width'])) {
            $parts[] = 'width:' . $display['width'];
        }
        if (!empty($display['maxWidth'])) {
            $parts[] = 'max-width:' . $display['maxWidth'];
        }
        if (!empty($display['margin'])) {
            $parts[] = 'margin:' . $display['margin'];
        }
        if (!empty($display['padding'])) {
            $parts[] = 'padding:' . $display['padding'];
        }
        if (!empty($display['textAlign'])) {
            $parts[] = 'text-align:' . $display['textAlign'];
        }

        $colors = is_array($advanced['colors'] ?? null) ? $advanced['colors'] : [];
        if (!empty($colors['color'])) {
            $parts[] = 'color:' . $colors['color'];
        }
        if (!empty($colors['background'])) {
            $parts[] = 'background-color:' . $colors['background'];
        }

        $border = is_array($advanced['border'] ?? null) ? $advanced['border'] : [];
        $bStyle = (string) ($border['style'] ?? '');
        $bWidth = (string) ($border['width'] ?? '');
        $bColor = (string) ($border['color'] ?? '');
        if ($bStyle === 'none') {
            $parts[] = 'border:none';
        } elseif ($bWidth !== '' || $bStyle !== '' || $bColor !== '') {
            $w = $bWidth !== '' ? $bWidth : '1px';
            $s = $bStyle !== '' ? $bStyle : 'solid';
            $c = $bColor !== '' ? $bColor : '#000000';
            $parts[] = 'border:' . $w . ' ' . $s . ' ' . $c;
        }
        if (!empty($border['radius'])) {
            $parts[] = 'border-radius:' . $border['radius'];
        }

        return implode(';', $parts);
    }

    /**
     * HTML-Attribute ohne class (class separat mergen).
     *
     * @param array<string, mixed> $advanced already normalized
     * @return array{id?: string, title?: string, aria-label?: string}
     */
    public static function toAttributeMap(array $advanced): array
    {
        $attrs = is_array($advanced['attrs'] ?? null) ? $advanced['attrs'] : [];
        $map = [];
        if (!empty($attrs['id'])) {
            $map['id'] = (string) $attrs['id'];
        }
        if (!empty($attrs['title'])) {
            $map['title'] = (string) $attrs['title'];
        }
        if (!empty($attrs['ariaLabel'])) {
            $map['aria-label'] = (string) $attrs['ariaLabel'];
        }

        return $map;
    }

    /**
     * Zusätzliche CSS-Klassen (bereits sanitisiert).
     *
     * @param array<string, mixed> $advanced already normalized
     */
    public static function extraClassNames(array $advanced): string
    {
        $attrs = is_array($advanced['attrs'] ?? null) ? $advanced['attrs'] : [];

        return (string) ($attrs['className'] ?? '');
    }

    /**
     * Escapte Attribute-Zeichenkette inkl. class-Merge-Hinweis: ohne class.
     * Für einfachen Echo: id/title/aria-label.
     *
     * @param array<string, mixed> $advanced already normalized
     */
    public static function toHtmlAttributes(array $advanced): string
    {
        $html = '';
        foreach (self::toAttributeMap($advanced) as $name => $value) {
            $html .= ' ' . $name . '="' . View::escape($value) . '"';
        }

        return $html;
    }

    /**
     * @param list<string> $allowed
     */
    private static function pickEnum(string $value, array $allowed): string
    {
        $value = trim($value);

        return in_array($value, $allowed, true) ? $value : '';
    }

    private static function sanitizeOpacity(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (!is_numeric($value)) {
            return '';
        }
        $n = (float) $value;
        if ($n < 0 || $n > 1) {
            return '';
        }
        $formatted = rtrim(rtrim(sprintf('%.3f', $n), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    private static function sanitizeCssLength(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (strcasecmp($value, 'auto') === 0) {
            return 'auto';
        }
        if (strcasecmp($value, '0') === 0) {
            return '0';
        }
        // Single length or up to 4 space-separated (margin/padding shorthand)
        $parts = preg_split('/\s+/', $value) ?: [];
        if ($parts === [] || count($parts) > 4) {
            return '';
        }
        $clean = [];
        foreach ($parts as $part) {
            if (strcasecmp($part, 'auto') === 0) {
                $clean[] = 'auto';
                continue;
            }
            if (strcasecmp($part, '0') === 0) {
                $clean[] = '0';
                continue;
            }
            if (preg_match('/^(-?\d+(?:\.\d+)?)(px|%|rem|em)$/i', $part, $m) !== 1) {
                return '';
            }
            $clean[] = $m[1] . strtolower($m[2]);
        }

        return implode(' ', $clean);
    }

    private static function sanitizeHexColor(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value) !== 1) {
            return '';
        }

        return strtolower($value);
    }

    private static function sanitizeHtmlId(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^[A-Za-z][A-Za-z0-9_:-]*$/', $value) !== 1) {
            return '';
        }
        if (strlen($value) > 64) {
            return '';
        }

        return $value;
    }

    private static function sanitizeClassList(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $tokens = preg_split('/\s+/', $value) ?: [];
        $clean = [];
        foreach ($tokens as $token) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_-]*$/', $token) !== 1) {
                continue;
            }
            if (strlen($token) > 40) {
                continue;
            }
            $clean[] = $token;
        }
        $clean = array_values(array_unique($clean));
        if (count($clean) > 8) {
            $clean = array_slice($clean, 0, 8);
        }

        return implode(' ', $clean);
    }

    private static function sanitizePlainAttr(string $value, int $maxLen): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value) ?? $value;
        if ($value === '') {
            return '';
        }
        if (strlen($value) > $maxLen) {
            $value = substr($value, 0, $maxLen);
        }

        return $value;
    }
}
