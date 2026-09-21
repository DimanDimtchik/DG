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
    private const BORDER_SIDES = ['top', 'right', 'bottom', 'left'];
    private const BG_SIZE_ENUM = ['cover', 'contain', 'auto'];
    private const BG_REPEAT_VALUES = ['no-repeat', 'repeat', 'repeat-x', 'repeat-y'];
    private const BG_POS_KEYWORDS = ['center', 'top', 'bottom', 'left', 'right'];
    private const TEXT_DECORATION_VALUES = ['none', 'underline', 'line-through', 'overline'];
    private const INTERACTION_COLOR_KEYS = ['color', 'background', 'borderColor'];

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
            $col = self::sanitizeColor((string) ($colorsIn[$colorKey] ?? ''));
            if ($col !== '') {
                $colors[$colorKey] = $col;
            }
        }
        $bgImage = self::sanitizeBackgroundImageUrl((string) ($colorsIn['backgroundImage'] ?? ''));
        if ($bgImage !== '') {
            $colors['backgroundImage'] = $bgImage;
        }
        $bgSize = self::sanitizeBackgroundSize((string) ($colorsIn['backgroundSize'] ?? ''));
        if ($bgSize !== '') {
            $colors['backgroundSize'] = $bgSize;
        }
        $bgPos = self::sanitizeBackgroundPosition((string) ($colorsIn['backgroundPosition'] ?? ''));
        if ($bgPos !== '') {
            $colors['backgroundPosition'] = $bgPos;
        }
        $bgRepeat = self::pickEnum(
            strtolower(trim((string) ($colorsIn['backgroundRepeat'] ?? ''))),
            self::BG_REPEAT_VALUES
        );
        if ($bgRepeat !== '') {
            $colors['backgroundRepeat'] = $bgRepeat;
        }
        if ($colors !== []) {
            $out['colors'] = $colors;
        }

        $borderIn = is_array($advanced['border'] ?? null) ? $advanced['border'] : [];
        $border = self::normalizeBorder($borderIn);
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

        foreach (['hover', 'visited'] as $state) {
            $stateIn = is_array($advanced[$state] ?? null) ? $advanced[$state] : [];
            $stateOut = self::normalizeInteractionState($stateIn);
            if ($stateOut !== []) {
                $out[$state] = $stateOut;
            }
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
        if (!empty($colors['backgroundImage'])) {
            $parts[] = 'background-image:url("' . $colors['backgroundImage'] . '")';
        }
        if (!empty($colors['backgroundSize'])) {
            $parts[] = 'background-size:' . $colors['backgroundSize'];
        }
        if (!empty($colors['backgroundPosition'])) {
            $parts[] = 'background-position:' . $colors['backgroundPosition'];
        }
        if (!empty($colors['backgroundRepeat'])) {
            $parts[] = 'background-repeat:' . $colors['backgroundRepeat'];
        }

        $border = is_array($advanced['border'] ?? null) ? $advanced['border'] : [];
        $hasSide = false;
        foreach (self::BORDER_SIDES as $side) {
            $sw = (string) ($border[$side . 'Width'] ?? '');
            $ss = (string) ($border[$side . 'Style'] ?? '');
            $sc = (string) ($border[$side . 'Color'] ?? '');
            if ($sw === '' && $ss === '' && $sc === '') {
                continue;
            }
            $hasSide = true;
            if ($ss === 'none') {
                $parts[] = 'border-' . $side . ':none';
                continue;
            }
            $w = $sw !== '' ? $sw : '1px';
            $s = $ss !== '' ? $ss : 'solid';
            $c = $sc !== '' ? $sc : '#000000';
            $parts[] = 'border-' . $side . ':' . $w . ' ' . $s . ' ' . $c;
        }

        if (!$hasSide) {
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
        }

        $corners = [
            (string) ($border['radiusTL'] ?? ''),
            (string) ($border['radiusTR'] ?? ''),
            (string) ($border['radiusBR'] ?? ''),
            (string) ($border['radiusBL'] ?? ''),
        ];
        $hasCorner = false;
        foreach ($corners as $corner) {
            if ($corner !== '') {
                $hasCorner = true;
                break;
            }
        }
        if ($hasCorner) {
            $parts[] = 'border-radius:' . ($corners[0] !== '' ? $corners[0] : '0') . ' '
                . ($corners[1] !== '' ? $corners[1] : '0') . ' '
                . ($corners[2] !== '' ? $corners[2] : '0') . ' '
                . ($corners[3] !== '' ? $corners[3] : '0');
        } elseif (!empty($border['radius'])) {
            $parts[] = 'border-radius:' . $border['radius'];
        }

        return implode(';', $parts);
    }

    /**
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
     * @param array<string, mixed> $advanced already normalized
     */
    public static function hasInteractionStyles(array $advanced): bool
    {
        $hover = is_array($advanced['hover'] ?? null) ? $advanced['hover'] : [];
        $visited = is_array($advanced['visited'] ?? null) ? $advanced['visited'] : [];

        return $hover !== [] || $visited !== [];
    }

    /**
     * Generierte Klasse für Hover/Visited (W5). Leer, wenn keine Interaction-Styles.
     *
     * @param array<string, mixed> $advanced already normalized
     */
    public static function interactionClassName(string $blockId, array $advanced): string
    {
        if (!self::hasInteractionStyles($advanced)) {
            return '';
        }
        $id = preg_replace('/[^A-Za-z0-9_-]/', '', $blockId) ?? '';
        if ($id === '') {
            $payload = json_encode([
                'hover' => $advanced['hover'] ?? [],
                'visited' => $advanced['visited'] ?? [],
            ], JSON_UNESCAPED_SLASHES);
            $id = substr(hash('sha256', is_string($payload) ? $payload : ''), 0, 10);
        }
        if (strlen($id) > 40) {
            $id = substr($id, 0, 40);
        }

        return 'ws-adv-h-' . $id;
    }

    /**
     * Sicheres CSS für Hover/Visited auf Links/Buttons innerhalb des Blocks.
     *
     * @param array<string, mixed> $advanced already normalized
     */
    public static function toInteractionCss(string $className, array $advanced): string
    {
        if (preg_match('/^ws-adv-h-[A-Za-z0-9_-]+$/', $className) !== 1) {
            return '';
        }
        $chunks = [];
        $hover = is_array($advanced['hover'] ?? null) ? $advanced['hover'] : [];
        $visited = is_array($advanced['visited'] ?? null) ? $advanced['visited'] : [];
        $hoverDecl = self::interactionDeclarations($hover);
        $visitedDecl = self::interactionDeclarations($visited);
        $hoverSel = '.' . $className . ' a:hover,.' . $className . ' .ws-btn:hover,.' . $className . ' .dg-website-block__btn:hover';
        $visitedSel = '.' . $className . ' a:visited,.' . $className . ' .ws-btn:visited,.' . $className . ' .dg-website-block__btn:visited';
        if ($hoverDecl !== '') {
            $chunks[] = $hoverSel . '{' . $hoverDecl . '}';
        }
        if ($visitedDecl !== '') {
            $chunks[] = $visitedSel . '{' . $visitedDecl . '}';
        }

        return implode('', $chunks);
    }

    /**
     * @param array<string, mixed> $advanced already normalized
     */
    public static function extraClassNames(array $advanced): string
    {
        $attrs = is_array($advanced['attrs'] ?? null) ? $advanced['attrs'] : [];

        return (string) ($attrs['className'] ?? '');
    }

    /**
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
     * @param array<string, mixed> $in
     * @return array<string, string>
     */
    private static function normalizeInteractionState(array $in): array
    {
        $out = [];
        foreach (self::INTERACTION_COLOR_KEYS as $colorKey) {
            $col = self::sanitizeColor((string) ($in[$colorKey] ?? ''));
            if ($col !== '') {
                $out[$colorKey] = $col;
            }
        }
        $td = self::pickEnum((string) ($in['textDecoration'] ?? ''), self::TEXT_DECORATION_VALUES);
        if ($td !== '') {
            $out['textDecoration'] = $td;
        }
        $opacity = self::sanitizeOpacity((string) ($in['opacity'] ?? ''));
        if ($opacity !== '') {
            $out['opacity'] = $opacity;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function interactionDeclarations(array $state): string
    {
        $parts = [];
        if (!empty($state['color'])) {
            $parts[] = 'color:' . $state['color'];
        }
        if (!empty($state['background'])) {
            $parts[] = 'background-color:' . $state['background'];
        }
        if (!empty($state['borderColor'])) {
            $parts[] = 'border-color:' . $state['borderColor'];
        }
        if (!empty($state['textDecoration'])) {
            $parts[] = 'text-decoration:' . $state['textDecoration'];
        }
        if (isset($state['opacity']) && $state['opacity'] !== '') {
            $parts[] = 'opacity:' . $state['opacity'];
        }

        return implode(';', $parts);
    }

    /**
     * @param array<string, mixed> $borderIn
     * @return array<string, string>
     */
    private static function normalizeBorder(array $borderIn): array
    {
        $border = [];
        $bWidth = self::sanitizeCssLength((string) ($borderIn['width'] ?? ''));
        if ($bWidth !== '') {
            $border['width'] = $bWidth;
        }
        $bStyle = self::pickEnum((string) ($borderIn['style'] ?? ''), self::BORDER_STYLES);
        $bColor = self::sanitizeColor((string) ($borderIn['color'] ?? ''));
        if ($bStyle !== '' && $bStyle !== 'none') {
            $border['style'] = $bStyle;
        } elseif ($bStyle === 'none' && ($bWidth !== '' || $bColor !== '')) {
            $border['style'] = 'none';
        }
        if ($bColor !== '') {
            $border['color'] = $bColor;
        }

        $radius = self::sanitizeCssLength((string) ($borderIn['radius'] ?? ''));
        if ($radius !== '') {
            $border['radius'] = $radius;
        }
        foreach (['radiusTL', 'radiusTR', 'radiusBR', 'radiusBL'] as $rk) {
            $rv = self::sanitizeCssLength((string) ($borderIn[$rk] ?? ''));
            if ($rv !== '') {
                $border[$rk] = $rv;
            }
        }

        foreach (self::BORDER_SIDES as $side) {
            $sw = self::sanitizeCssLength((string) ($borderIn[$side . 'Width'] ?? ''));
            if ($sw !== '') {
                $border[$side . 'Width'] = $sw;
            }
            $ss = self::pickEnum((string) ($borderIn[$side . 'Style'] ?? ''), self::BORDER_STYLES);
            $sc = self::sanitizeColor((string) ($borderIn[$side . 'Color'] ?? ''));
            if ($ss !== '' && $ss !== 'none') {
                $border[$side . 'Style'] = $ss;
            } elseif ($ss === 'none' && ($sw !== '' || $sc !== '')) {
                $border[$side . 'Style'] = 'none';
            }
            if ($sc !== '') {
                $border[$side . 'Color'] = $sc;
            }
        }

        return $border;
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

    /**
     * Hintergrundbild-URL: /media…, /app/media… oder http(s) ohne Quotes/JS.
     */
    private static function sanitizeBackgroundImageUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^url\(\s*[\'"]?(.*?)[\'"]?\s*\)$/i', $value, $m) === 1) {
            $value = trim($m[1]);
        }
        if ($value === '' || preg_match('/[\s\'"<>\\\\]|javascript:/i', $value) === 1) {
            return '';
        }
        if (strlen($value) > 500) {
            return '';
        }
        if (str_starts_with($value, '/media/') || str_starts_with($value, '/app/media')) {
            return $value;
        }
        if (preg_match('#^https?://[a-z0-9.-]+(?::\d+)?(?:/[^\s]*)?$#i', $value) === 1) {
            return $value;
        }

        return '';
    }

    private static function sanitizeBackgroundSize(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $lower = strtolower($value);
        if (in_array($lower, self::BG_SIZE_ENUM, true)) {
            return $lower;
        }
        $parts = preg_split('/\s+/', $value) ?: [];
        if ($parts === [] || count($parts) > 2) {
            return '';
        }
        $clean = [];
        foreach ($parts as $part) {
            $len = self::sanitizeCssLength($part);
            if ($len === '' || str_contains($len, ' ')) {
                return '';
            }
            $clean[] = $len;
        }

        return implode(' ', $clean);
    }

    private static function sanitizeBackgroundPosition(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $parts = preg_split('/\s+/', strtolower($value)) ?: [];
        if ($parts === [] || count($parts) > 2) {
            return '';
        }
        $clean = [];
        foreach ($parts as $part) {
            if (in_array($part, self::BG_POS_KEYWORDS, true)) {
                $clean[] = $part;
                continue;
            }
            $len = self::sanitizeCssLength($part);
            if ($len === '' || str_contains($len, ' ')) {
                return '';
            }
            $clean[] = $len;
        }

        return implode(' ', $clean);
    }

    /**
     * Hex (#rgb / #rrggbb / #rrggbbaa), rgb(), rgba().
     */
    private static function sanitizeColor(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value) === 1) {
            return strtolower($value);
        }
        if (preg_match(
            '/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*(0|1|0?\.\d+)\s*)?\)$/i',
            $value,
            $m
        ) === 1) {
            $r = (int) $m[1];
            $g = (int) $m[2];
            $b = (int) $m[3];
            if ($r > 255 || $g > 255 || $b > 255) {
                return '';
            }
            if (isset($m[4]) && $m[4] !== '') {
                $a = (float) $m[4];
                if ($a < 0.0 || $a > 1.0) {
                    return '';
                }
                $aFmt = rtrim(rtrim(sprintf('%.3f', $a), '0'), '.');
                if ($aFmt === '') {
                    $aFmt = '0';
                }

                return 'rgba(' . $r . ',' . $g . ',' . $b . ',' . $aFmt . ')';
            }

            return 'rgb(' . $r . ',' . $g . ',' . $b . ')';
        }

        return '';
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
