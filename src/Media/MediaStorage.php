<?php
declare(strict_types=1);

final class MediaStorage
{
    public const MAX_BYTES = 52_428_800;

    /** @var array<string, string> */
    private const EXT_MIME = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
    ];

    public static function baseDir(): string
    {
        return DG_ROOT . '/storage/media';
    }

    /** @return list<string> */
    public static function allowedExtensions(): array
    {
        return array_keys(self::EXT_MIME);
    }

    public static function mimeForExtension(string $ext): ?string
    {
        $ext = strtolower($ext);

        return self::EXT_MIME[$ext] ?? null;
    }

    public static function isAllowedImageUpload(string $originalName, string $clientMime): bool
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!isset(self::EXT_MIME[$ext])) {
            return false;
        }

        $mime = self::mimeForExtension($ext);
        if ($mime === null) {
            return false;
        }

        if ($clientMime !== '' && !str_starts_with($clientMime, 'image/') && $clientMime !== 'image/svg+xml') {
            return false;
        }

        return true;
    }

    public static function absolutePath(string $mediaId, string $storedName): string
    {
        if (!MediaId::isValid($mediaId)) {
            throw new InvalidArgumentException('Ungültige Medien-ID.');
        }

        $safeName = basename($storedName);
        if ($safeName === '' || $safeName !== $storedName) {
            throw new InvalidArgumentException('Ungültiger Dateiname.');
        }

        return self::baseDir() . '/' . $mediaId . '/' . $safeName;
    }

    public static function ensureMediaDir(string $mediaId): string
    {
        $dir = self::baseDir() . '/' . $mediaId;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Medienverzeichnis konnte nicht erstellt werden.');
        }

        return $dir;
    }

    /** @param array<string, mixed> $file */
    public static function storeUpload(string $mediaId, array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException('Keine Datei hochgeladen.');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Upload fehlgeschlagen (Fehlercode ' . $error . ').');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new InvalidArgumentException('Datei zu groß (max. ' . (int) (self::MAX_BYTES / 1_048_576) . ' MB).');
        }

        $original = (string) ($file['name'] ?? 'upload');
        $clientMime = (string) ($file['type'] ?? '');
        if (!self::isAllowedImageUpload($original, $clientMime)) {
            throw new InvalidArgumentException('Nur Bilddateien erlaubt (JPG, PNG, WebP, GIF, SVG). Keine PDFs oder Office-Dateien.');
        }

        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $mime = self::mimeForExtension($ext);
        if ($mime === null) {
            throw new InvalidArgumentException('Dateityp nicht erlaubt.');
        }

        $storedName = 'original.' . $ext;
        $target = self::ensureMediaDir($mediaId) . '/' . $storedName;

        if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $target)) {
            throw new RuntimeException('Datei konnte nicht gespeichert werden.');
        }

        self::applyReadablePermissions($target);

        [$width, $height] = MediaImageProcessor::readDimensions($target, $mime);

        return [
            'stored_name' => $storedName,
            'mime_type' => $mime,
            'extension' => $ext,
            'width' => $width,
            'height' => $height,
            'size_bytes' => (int) filesize($target),
            'original_name' => $original,
        ];
    }

    public static function storeBinary(string $mediaId, string $storedName, string $binary, string $mime): array
    {
        if (!MediaId::isValid($mediaId)) {
            throw new InvalidArgumentException('Ungültige Medien-ID.');
        }

        $ext = self::extensionFromMime($mime);
        if ($ext === null) {
            throw new InvalidArgumentException('MIME-Typ nicht unterstützt.');
        }

        $safeName = basename($storedName);
        if (!str_contains($safeName, '.')) {
            $safeName = 'edited.' . $ext;
        }

        $target = self::ensureMediaDir($mediaId) . '/' . $safeName;
        if (file_put_contents($target, $binary) === false) {
            throw new RuntimeException('Bearbeitete Datei konnte nicht gespeichert werden.');
        }

        self::applyReadablePermissions($target);

        [$width, $height] = MediaImageProcessor::readDimensions($target, $mime);

        return [
            'stored_name' => $safeName,
            'mime_type' => $mime,
            'extension' => $ext,
            'width' => $width,
            'height' => $height,
            'size_bytes' => (int) filesize($target),
        ];
    }

    public static function extensionFromMime(string $mime): ?string
    {
        foreach (self::EXT_MIME as $ext => $allowed) {
            if ($allowed === $mime) {
                return $ext;
            }
        }

        return null;
    }

    public static function deleteMediaFiles(string $mediaId): void
    {
        if (!MediaId::isValid($mediaId)) {
            return;
        }

        $dir = self::baseDir() . '/' . $mediaId;
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($dir);
    }

    public static function publicUrl(string $mediaId): string
    {
        return '/app/media?id=' . rawurlencode($mediaId);
    }

    /** Admin-Vorschau über eingeloggte /app-Route (zuverlässiger als /app/media für Medienbibliothek). */
    public static function adminPreviewUrl(string $mediaId, ?int $version = null): string
    {
        $url = '/app?page=bilder&action=preview&id=' . rawurlencode($mediaId);
        if ($version !== null && $version > 0) {
            $url .= '&v=' . $version;
        }

        return $url;
    }

    public static function applyReadablePermissions(string $filePath): void
    {
        if (!is_file($filePath)) {
            return;
        }

        @chmod($filePath, 0644);
        $dir = dirname($filePath);
        while (str_starts_with($dir, self::baseDir()) && is_dir($dir)) {
            @chmod($dir, 0755);
            if ($dir === self::baseDir()) {
                break;
            }
            $dir = dirname($dir);
        }
    }

    public static function faviconPath(string $mediaId, int $size): string
    {
        self::ensureMediaDir($mediaId);

        return self::baseDir() . '/' . $mediaId . '/favicon-' . $size . '.png';
    }

    public static function faviconSvgPath(string $mediaId): string
    {
        self::ensureMediaDir($mediaId);

        return self::baseDir() . '/' . $mediaId . '/favicon.svg';
    }

    /** @return array{path: string, mime: string}|null */
    public static function resolveFaviconFile(string $mediaId, int $size): ?array
    {
        if (!MediaId::isValid($mediaId)) {
            return null;
        }

        $svg = self::faviconSvgPath($mediaId);
        if (is_file($svg)) {
            return ['path' => $svg, 'mime' => 'image/svg+xml'];
        }

        $size = max(16, min(48, $size));
        foreach ([$size, 32, 48, 16] as $trySize) {
            $path = self::faviconPath($mediaId, $trySize);
            if (is_file($path)) {
                return ['path' => $path, 'mime' => 'image/png'];
            }
        }

        return null;
    }

    public static function faviconUsesSvg(string $mediaId): bool
    {
        return is_file(self::faviconSvgPath($mediaId));
    }
}
