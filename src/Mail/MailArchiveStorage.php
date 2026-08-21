<?php
declare(strict_types=1);

/**
 * Mail Archive Storage.
 */
final class MailArchiveStorage
{
    /**
     * Methode base dir.
     * @return string
     */
    public static function baseDir(): string
    {
        return DG_ROOT . '/storage/mail/sent';
    }

    /**
     * Methode inbox base dir.
     * @return string
     */
    public static function inboxBaseDir(): string
    {
        return DG_ROOT . '/storage/mail/inbox';
    }

    /**
     * Methode relative path.
     * @param int $logId
     * @return string
     */
    public static function relativePath(int $logId): string
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin'));

        return 'storage/mail/sent/' . $now->format('Y/m/d') . '/' . $logId . '.eml';
    }

    /**
     * Methode relative inbound path.
     * @param int $logId
     * @return string
     */
    public static function relativeInboundPath(int $logId): string
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin'));

        return 'storage/mail/inbox/' . $now->format('Y/m/d') . '/' . $logId . '.eml';
    }

    /**
     * Methode absolute path.
     * @param string $relativePath
     * @return string
     * @throws InvalidArgumentException
     */
    public static function absolutePath(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if (!str_starts_with($relativePath, 'storage/mail/')) {
            throw new InvalidArgumentException('Ungültiger Archivpfad.');
        }

        return DG_ROOT . '/' . $relativePath;
    }

    /**
     * Methode save.
     * @param int $logId
     * @param string $mimeData
     * @return array{path: string, size: int}
     * @throws RuntimeException
     */
    public static function save(int $logId, string $mimeData): array
    {
        $relative = self::relativePath($logId);
        $absolute = self::absolutePath($relative);
        $dir = dirname($absolute);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Mail-Archivordner konnte nicht angelegt werden.');
        }

        $bytes = file_put_contents($absolute, $mimeData);
        if ($bytes === false) {
            throw new RuntimeException('E-Mail-Kopie konnte nicht gespeichert werden.');
        }

        return [
            'path' => $relative,
            'size' => (int) $bytes,
        ];
    }

    /**
     * Speichert inbound.
     * @param int $logId
     * @param string $mimeData
     * @return array{path: string, size: int}
     * @throws RuntimeException
     */
    public static function saveInbound(int $logId, string $mimeData): array
    {
        $relative = self::relativeInboundPath($logId);
        $absolute = self::absolutePath($relative);
        $dir = dirname($absolute);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Mail-Archivordner konnte nicht angelegt werden.');
        }

        $bytes = file_put_contents($absolute, $mimeData);
        if ($bytes === false) {
            throw new RuntimeException('E-Mail-Kopie konnte nicht gespeichert werden.');
        }

        return [
            'path' => $relative,
            'size' => (int) $bytes,
        ];
    }
}
