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
}
