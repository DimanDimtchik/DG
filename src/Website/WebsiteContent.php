<?php
declare(strict_types=1);

/**
 * Normalizes website builder text that may still contain HTML from imports/seeds.
 *
 * W4 Entscheidung: Inhalt bleibt Plaintext mit wenigen Markern —
 * `**fett**` und `[text](url)` — die hier zu sicherem HTML werden.
 * Erlaubte Links: http(s):, mailto:, relative Pfade ab `/` (nicht `//`).
 */
final class WebsiteContent
{
    /**
     * Plain UTF-8 text for storage/display (entities decoded, breaks as newlines, tags removed).
     */
    public static function normalizePlainText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (str_contains($text, '&') && preg_match('/&(?:#\d+|#x[\da-fA-F]+|[a-zA-Z]+);/', $text) === 1) {
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text) ?? $text;
        $text = strip_tags($text);
        // Normalize exotic whitespace, keep intentional blank lines.
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * Erlaubte href-Ziele für Content-Links (W4).
     */
    public static function isSafeHref(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        if (preg_match('#^https?://#i', $url) === 1) {
            return true;
        }
        if (preg_match('#^mailto:[^\s<>\"\']+$#i', $url) === 1) {
            return true;
        }
        // Relativ ab Slash, aber kein Protocol-relative //evil.example
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return true;
        }

        return false;
    }

    /**
     * Safe HTML paragraph body for the public website (Marker + Zeilenumbrüche).
     */
    public static function renderTextHtml(string $text): string
    {
        return nl2br(self::renderInlineMarkup(self::normalizePlainText($text)), false);
    }

    /**
     * Safe HTML for headings (Inline-Marker, keine <br>).
     */
    public static function renderHeadingText(string $text): string
    {
        return self::renderInlineMarkup(self::normalizePlainText($text));
    }

    /**
     * Plaintext-Marker → sicheres HTML: **fett**, [label](url).
     */
    public static function renderInlineMarkup(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $out = '';
        $offset = 0;
        $length = strlen($text);
        // Non-greedy bold; link label without ]; URL without )
        $pattern = '/\*\*(.+?)\*\*|\[([^\[\]]+)\]\(([^)]+)\)/su';

        while ($offset < $length && preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $matchStart = (int) $m[0][1];
            $matchLen = strlen($m[0][0]);
            if ($matchStart > $offset) {
                $out .= View::escape(substr($text, $offset, $matchStart - $offset));
            }

            if ($m[1][1] >= 0 && $m[1][0] !== '') {
                $out .= '<strong>' . View::escape($m[1][0]) . '</strong>';
            } elseif (isset($m[2][0], $m[3][0]) && $m[2][1] >= 0) {
                $label = $m[2][0];
                $href = trim($m[3][0]);
                if (self::isSafeHref($href)) {
                    $out .= '<a href="' . View::escape($href) . '">' . View::escape($label) . '</a>';
                } else {
                    $out .= View::escape($label);
                }
            } else {
                $out .= View::escape($m[0][0]);
            }

            $offset = $matchStart + $matchLen;
        }

        if ($offset < $length) {
            $out .= View::escape(substr($text, $offset));
        }

        return $out;
    }

    /**
     * Walk layout blocks and normalize heading/text fields in place.
     *
     * @param array<string, mixed> $layout
     * @return array<string, mixed>
     */
    public static function normalizeLayout(array $layout): array
    {
        $rows = $layout['rows'] ?? null;
        if (!is_array($rows)) {
            return $layout;
        }
        foreach ($rows as $r => $row) {
            if (!is_array($row)) {
                continue;
            }
            $columns = $row['columns'] ?? null;
            if (!is_array($columns)) {
                continue;
            }
            foreach ($columns as $c => $col) {
                if (!is_array($col)) {
                    continue;
                }
                $blocks = $col['blocks'] ?? null;
                if (!is_array($blocks)) {
                    continue;
                }
                foreach ($blocks as $b => $block) {
                    if (!is_array($block)) {
                        continue;
                    }
                    $type = (string) ($block['type'] ?? '');
                    if (($type === 'text' || $type === 'heading') && isset($block['text']) && is_string($block['text'])) {
                        $block['text'] = self::normalizePlainText($block['text']);
                    }
                    if ($type === 'text') {
                        if (!empty($block['quote'])) {
                            $block['quote'] = true;
                        } else {
                            unset($block['quote']);
                        }
                    }
                    if ($type === 'button') {
                        if (isset($block['label']) && is_string($block['label'])) {
                            $block['label'] = self::normalizePlainText($block['label']);
                        }
                        if (isset($block['text']) && is_string($block['text'])) {
                            $block['text'] = self::normalizePlainText($block['text']);
                        }
                    }
                    $advanced = WebsiteBlockAdvanced::normalize($block['advanced'] ?? null);
                    if ($advanced === []) {
                        unset($block['advanced']);
                    } else {
                        $block['advanced'] = $advanced;
                    }
                    $blocks[$b] = $block;
                }
                $col['blocks'] = $blocks;
                $columns[$c] = $col;
            }
            $row['columns'] = $columns;
            $rows[$r] = $row;
        }
        $layout['rows'] = $rows;

        return $layout;
    }
}
