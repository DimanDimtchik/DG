<?php
declare(strict_types=1);

final class MediaFaviconGenerator
{
    /** @var list<int> */
    private const SIZES = [16, 32, 48];

    public static function generateForMedia(string $mediaId): void
    {
        if (!MediaId::isValid($mediaId)) {
            throw new InvalidArgumentException('Ungültige Medien-ID.');
        }

        $item = MediaRepository::find($mediaId);
        if ($item === null) {
            throw new InvalidArgumentException('Bild nicht gefunden.');
        }

        $mime = (string) $item['mime_type'];
        $path = MediaStorage::absolutePath($mediaId, (string) $item['stored_name']);

        if ($mime === 'image/svg+xml') {
            self::storeSvgFavicon($mediaId, $path);

            return;
        }

        if (!MediaImageProcessor::gdAvailable()) {
            throw new RuntimeException('PHP GD ist für Favicon-Erzeugung nicht verfügbar.');
        }

        $image = self::loadRaster($path, $mime);
        if ($image === null) {
            throw new RuntimeException('Favicon-Quelle konnte nicht geladen werden.');
        }

        $srcW = imagesx($image);
        $srcH = imagesy($image);

        foreach (self::SIZES as $size) {
            $target = MediaStorage::faviconPath($mediaId, $size);
            $resized = imagecreatetruecolor($size, $size);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            if ($transparent !== false) {
                imagefilledrectangle($resized, 0, 0, $size, $size, $transparent);
            }

            $scale = min($size / max(1, $srcW), $size / max(1, $srcH));
            $drawW = max(1, (int) round($srcW * $scale));
            $drawH = max(1, (int) round($srcH * $scale));
            $offsetX = (int) floor(($size - $drawW) / 2);
            $offsetY = (int) floor(($size - $drawH) / 2);

            imagecopyresampled($resized, $image, $offsetX, $offsetY, 0, 0, $drawW, $drawH, $srcW, $srcH);
            if (!imagepng($resized, $target, 6)) {
                imagedestroy($resized);
                imagedestroy($image);
                throw new RuntimeException('Favicon konnte nicht gespeichert werden.');
            }
            imagedestroy($resized);
        }

        imagedestroy($image);
    }

    public static function publicUrl(int $size = 32): string
    {
        $id = AppearanceSettings::faviconMediaId();
        if ($id === '') {
            return '';
        }

        return '/app/favicon?size=' . max(16, min(48, $size));
    }

    private static function storeSvgFavicon(string $mediaId, string $sourcePath): void
    {
        if (!is_file($sourcePath)) {
            throw new RuntimeException('SVG-Quelle fehlt.');
        }

        $target = MediaStorage::faviconSvgPath($mediaId);
        if (!copy($sourcePath, $target)) {
            throw new RuntimeException('SVG-Favicon konnte nicht gespeichert werden.');
        }
    }

    private static function loadRaster(string $path, string $mime): ?\GdImage
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path) ?: null,
            'image/png' => @imagecreatefrompng($path) ?: null,
            'image/webp' => function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($path) ?: null) : null,
            'image/gif' => @imagecreatefromgif($path) ?: null,
            default => null,
        };
    }
}
