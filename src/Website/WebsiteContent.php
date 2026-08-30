<?php
declare(strict_types=1);

/**
 * Normalizes website builder text that may still contain HTML from imports/seeds.
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
     * Safe HTML paragraph body for the public website.
     */
    public static function renderTextHtml(string $text): string
    {
        return nl2br(View::escape(self::normalizePlainText($text)), false);
    }

    /**
     * Safe plain text for headings (no line breaks as HTML).
     */
    public static function renderHeadingText(string $text): string
    {
        return View::escape(self::normalizePlainText($text));
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
                    if ($type === 'button') {
                        if (isset($block['label']) && is_string($block['label'])) {
                            $block['label'] = self::normalizePlainText($block['label']);
                        }
                        if (isset($block['text']) && is_string($block['text'])) {
                            $block['text'] = self::normalizePlainText($block['text']);
                        }
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
