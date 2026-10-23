<?php

declare(strict_types=1);

/**
 * Entfernt gefährliche HTML-Teile für die E-Mail-Vorschau (iframe/srcdoc).
 */
final class MailHtmlSanitizer
{
    public static function clean(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        // Scripts / Styles mit JS / Event-Handler
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? $html;
        $html = preg_replace('#<iframe\b[^>]*>.*?</iframe>#is', '', $html) ?? $html;
        $html = preg_replace('#<object\b[^>]*>.*?</object>#is', '', $html) ?? $html;
        $html = preg_replace('#<embed\b[^>]*>#is', '', $html) ?? $html;
        $html = preg_replace('#<form\b[^>]*>.*?</form>#is', '', $html) ?? $html;
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/\s(href|src)\s*=\s*([\'"]?)\s*javascript:[^\'"\s>]*/i', ' $1=$2#', $html) ?? $html;
        $html = preg_replace('/\s(href|src)\s*=\s*([\'"]?)\s*data:text\/html[^\'"\s>]*/i', ' $1=$2#', $html) ?? $html;

        // Meta-Refresh
        $html = preg_replace('#<meta[^>]+http-equiv\s*=\s*[\'"]?refresh[\'"]?[^>]*>#i', '', $html) ?? $html;

        return $html;
    }
}
