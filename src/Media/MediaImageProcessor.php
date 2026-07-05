<?php
declare(strict_types=1);

final class MediaImageProcessor
{
    /** @return array{0: ?int, 1: ?int} */
    public static function readDimensions(string $path, string $mime): array
    {
        if ($mime === 'image/svg+xml') {
            return [null, null];
        }

        if (!is_file($path)) {
            return [null, null];
        }

        $info = @getimagesize($path);
        if (!is_array($info)) {
            return [null, null];
        }

        return [(int) ($info[0] ?? 0) ?: null, (int) ($info[1] ?? 0) ?: null];
    }

    public static function gdAvailable(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatetruecolor');
    }

    /**
     * @return array{stored_name: string, mime_type: string, extension: string, width: ?int, height: ?int, size_bytes: int}
     */
    public static function transform(
        string $sourcePath,
        string $sourceMime,
        string $targetFormat,
        ?int $targetWidth,
        ?int $targetHeight,
        bool $keepAspectRatio = true
    ): array {
        if ($sourceMime === 'image/svg+xml') {
            throw new InvalidArgumentException('SVG kann hier nicht per GD umgewandelt werden.');
        }

        if (!self::gdAvailable()) {
            throw new RuntimeException('PHP GD ist auf dem Server nicht verfügbar.');
        }

        $image = self::loadRaster($sourcePath, $sourceMime);
        if ($image === null) {
            throw new RuntimeException('Bild konnte nicht geladen werden.');
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($targetWidth !== null && $targetWidth > 0 || $targetHeight !== null && $targetHeight > 0) {
            [$newW, $newH] = self::resolveTargetSize($width, $height, $targetWidth, $targetHeight, $keepAspectRatio);
            if ($newW !== $width || $newH !== $height) {
                $resized = imagecreatetruecolor($newW, $newH);
                self::preserveAlpha($resized, $sourceMime);
                imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $width, $height);
                imagedestroy($image);
                $image = $resized;
                $width = $newW;
                $height = $newH;
            }
        }

        $format = self::normalizeFormat($targetFormat);
        $mime = self::mimeForFormat($format);
        $ext = $format === 'jpeg' ? 'jpg' : $format;

        $dir = dirname($sourcePath);
        $storedName = 'edited.' . $ext;
        $target = $dir . '/' . $storedName;

        self::saveRaster($image, $target, $format, $sourceMime);
        imagedestroy($image);

        return [
            'stored_name' => $storedName,
            'mime_type' => $mime,
            'extension' => $ext,
            'width' => $width,
            'height' => $height,
            'size_bytes' => (int) filesize($target),
        ];
    }

    private static function normalizeFormat(string $format): string
    {
        $format = strtolower(trim($format));
        if (in_array($format, ['jpg', 'jpeg'], true)) {
            return 'jpeg';
        }
        if (in_array($format, ['png', 'webp', 'gif'], true)) {
            return $format;
        }

        return 'webp';
    }

    private static function mimeForFormat(string $format): string
    {
        return match ($format) {
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            default => 'image/webp',
        };
    }

    /** @return array{0: int, 1: int} */
    private static function resolveTargetSize(
        int $width,
        int $height,
        ?int $targetWidth,
        ?int $targetHeight,
        bool $keepAspectRatio
    ): array {
        if ($keepAspectRatio) {
            if ($targetWidth !== null && $targetWidth > 0 && $targetHeight !== null && $targetHeight > 0) {
                $targetHeight = (int) max(1, round($height * $targetWidth / $width));

                return [$targetWidth, $targetHeight];
            }
            if ($targetWidth !== null && $targetWidth > 0) {
                return [$targetWidth, (int) max(1, round($height * $targetWidth / $width))];
            }
            if ($targetHeight !== null && $targetHeight > 0) {
                return [(int) max(1, round($width * $targetHeight / $height)), $targetHeight];
            }

            return [$width, $height];
        }

        $newW = ($targetWidth !== null && $targetWidth > 0) ? $targetWidth : $width;
        $newH = ($targetHeight !== null && $targetHeight > 0) ? $targetHeight : $height;

        return [$newW, $newH];
    }

    private static function preserveAlpha(\GdImage $image, string $sourceMime): void
    {
        if ($sourceMime === 'image/png' || $sourceMime === 'image/webp' || $sourceMime === 'image/gif') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
            if ($transparent !== false) {
                imagefilledrectangle($image, 0, 0, imagesx($image), imagesy($image), $transparent);
            }
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

    private static function saveRaster(\GdImage $image, string $path, string $format, string $sourceMime): void
    {
        $ok = match ($format) {
            'jpeg' => imagejpeg($image, $path, 88),
            'png' => imagepng($image, $path, 6),
            'gif' => imagegif($image, $path),
            default => function_exists('imagewebp') ? imagewebp($image, $path, 85) : false,
        };

        if (!$ok) {
            throw new RuntimeException('Zielformat wird auf dem Server nicht unterstützt: ' . $format);
        }
    }
}
