<?php
declare(strict_types=1);

final class Asset
{
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
