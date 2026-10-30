<?php
declare(strict_types=1);

/**
 * Hilfsfunktion für versionierte Asset-URLs (Cache-Busting via filemtime).
 */
final class Asset
{
    /**
     * @param string $path Relativer Pfad ab Webroot (z. B. /assets/css/dg.css).
     */
    public static function url(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        $file = DG_ROOT . $path;

        if (!is_readable($file)) {
            return $path;
        }

        return $path . '?v=' . filemtime($file);
    }

    /**
     * CRM-App-Stylesheet: bevorzugt minifizierte Datei, Fallback Quelle.
     * Offline-Stub `dg.min.css` absichtlich nicht verwenden.
     * Debug: DG_CSS_SOURCE=1 → immer dg.css
     */
    public static function crmStylesheetUrl(): string
    {
        $source = '/assets/css/dg.css';
        $min = '/assets/css/dg.app.min.css';

        if (getenv('DG_CSS_SOURCE') === '1') {
            return self::url($source);
        }

        $sourceFile = DG_ROOT . $source;
        $minFile = DG_ROOT . $min;
        if (
            is_readable($minFile)
            && is_readable($sourceFile)
            && filesize($minFile) > 1000
            && filemtime($minFile) >= filemtime($sourceFile)
        ) {
            return self::url($min);
        }

        return self::url($source);
    }
}
