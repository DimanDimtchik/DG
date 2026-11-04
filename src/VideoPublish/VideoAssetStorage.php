<?php
declare(strict_types=1);

/** Dateiablage unter storage/video-publish/ (nicht Media-Library). */
final class VideoAssetStorage
{
    public const MAX_BYTES = 104_857_600; // 100 MiB MVP Webhosting

    /** @var list<string> */
    private const ALLOWED_MIME = [
        'video/mp4',
        'video/webm',
        'video/quicktime',
        'application/octet-stream',
    ];

    public static function baseDir(): string
    {
        return DG_ROOT . '/storage/video-publish';
    }

    public static function ensureDir(): void
    {
        $dir = self::baseDir();
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Video-Ablage konnte nicht angelegt werden.');
        }
        if (!is_file($dir . '/.htaccess')) {
            @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
        }
        if (!is_file($dir . '/index.html')) {
            @file_put_contents($dir . '/index.html', '');
        }
    }

    public static function absolutePath(string $storedName): string
    {
        $storedName = basename($storedName);
        if ($storedName === '' || $storedName === '.' || $storedName === '..') {
            throw new InvalidArgumentException('Ungültiger Dateiname.');
        }

        return self::baseDir() . '/' . $storedName;
    }

    /**
     * @param array<string, mixed> $file $_FILES entry
     * @return array{stored_name: string, original_name: string, mime_type: string, size_bytes: int, sha256: string}
     */
    public static function storeUpload(array $file): array
    {
        self::ensureDir();
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Upload fehlgeschlagen (Code ' . $error . ').');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new InvalidArgumentException('Keine gültige Upload-Datei.');
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size < 1) {
            throw new InvalidArgumentException('Datei ist leer.');
        }
        if ($size > self::MAX_BYTES) {
            throw new InvalidArgumentException('Video zu groß (max. 100 MB auf diesem Hosting).');
        }
        $original = basename((string) ($file['name'] ?? 'video.mp4'));
        $clientMime = strtolower(trim((string) ($file['type'] ?? '')));
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = 'mp4';
        }
        if (!in_array($ext, ['mp4', 'webm', 'mov', 'm4v'], true)) {
            throw new InvalidArgumentException('Erlaubt sind MP4, WebM oder MOV.');
        }
        if ($clientMime !== '' && !in_array($clientMime, self::ALLOWED_MIME, true) && !str_starts_with($clientMime, 'video/')) {
            throw new InvalidArgumentException('Dateityp nicht erlaubt.');
        }
        $mime = $clientMime !== '' && str_starts_with($clientMime, 'video/')
            ? $clientMime
            : match ($ext) {
                'webm' => 'video/webm',
                'mov' => 'video/quicktime',
                default => 'video/mp4',
            };

        $hash = hash_file('sha256', $tmp);
        if (!is_string($hash) || $hash === '') {
            throw new RuntimeException('Prüfsumme konnte nicht berechnet werden.');
        }
        $stored = date('YmdHis') . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = self::absolutePath($stored);
        if (!move_uploaded_file($tmp, $dest)) {
            throw new RuntimeException('Datei konnte nicht gespeichert werden.');
        }
        @chmod($dest, 0640);

        return [
            'stored_name' => $stored,
            'original_name' => mb_substr($original, 0, 255),
            'mime_type' => mb_substr($mime, 0, 120),
            'size_bytes' => $size,
            'sha256' => $hash,
        ];
    }

    public static function deleteFile(string $storedName): void
    {
        $path = self::absolutePath($storedName);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
