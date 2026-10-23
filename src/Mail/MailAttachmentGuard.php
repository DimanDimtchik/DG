<?php

declare(strict_types=1);

/**
 * Sichere Auslieferung von Mail-Anhängen (.eml / MIME-Parts).
 * Größenlimit, Dateiname, MIME-Whitelist, nosniff.
 */
final class MailAttachmentGuard
{
    /** Max. Anhanggröße beim Download (15 MiB). */
    public const MAX_BYTES = 15_728_640;

    /** @var list<string> */
    private const ALLOWED_MIME = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/rtf',
        'application/zip',
        'text/plain',
        'text/csv',
        'text/calendar',
        'message/rfc822',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/tiff',
    ];

    /** @var array<string, string> */
    private const EXT_MIME = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'rtf' => 'application/rtf',
        'zip' => 'application/zip',
        'txt' => 'text/plain',
        'csv' => 'text/csv',
        'ics' => 'text/calendar',
        'eml' => 'message/rfc822',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'tif' => 'image/tiff',
        'tiff' => 'image/tiff',
    ];

    public static function sendContent(string $filename, string $mime, string $content): void
    {
        $size = strlen($content);
        if ($size > self::MAX_BYTES) {
            http_response_code(413);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Anhang zu groß (max. ' . self::formatMb(self::MAX_BYTES) . ').';
            exit;
        }

        $safeName = self::safeFilename($filename);
        $safeMime = self::safeMime($mime, $safeName);
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $safeMime);
        header('Content-Disposition: attachment; filename="' . $safeName . '"');
        header('Content-Length: ' . (string) $size);
        echo $content;
        exit;
    }

    public static function sendFile(string $absolutePath, string $filename, string $mime = 'application/octet-stream'): void
    {
        if (!is_readable($absolutePath)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Datei nicht gefunden.';
            exit;
        }
        $size = (int) filesize($absolutePath);
        if ($size > self::MAX_BYTES) {
            http_response_code(413);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Anhang zu groß (max. ' . self::formatMb(self::MAX_BYTES) . ').';
            exit;
        }

        $safeName = self::safeFilename($filename);
        $safeMime = self::safeMime($mime, $safeName);
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $safeMime);
        header('Content-Disposition: attachment; filename="' . $safeName . '"');
        header('Content-Length: ' . (string) $size);
        readfile($absolutePath);
        exit;
    }

    public static function safeFilename(string $filename): string
    {
        $filename = str_replace(["\0", '"', "\r", "\n", '/', '\\'], '', $filename);
        $filename = trim($filename);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            return 'anhang.bin';
        }
        if (strlen($filename) > 180) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $base = pathinfo($filename, PATHINFO_FILENAME);
            $filename = mb_substr($base, 0, 160) . ($ext !== '' ? '.' . mb_substr($ext, 0, 15) : '');
        }

        return $filename;
    }

    public static function safeMime(string $mime, string $filename): string
    {
        $mime = strtolower(trim(explode(';', $mime)[0] ?? ''));
        if ($mime !== '' && in_array($mime, self::ALLOWED_MIME, true)) {
            return $mime;
        }
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext !== '' && isset(self::EXT_MIME[$ext])) {
            return self::EXT_MIME[$ext];
        }

        return 'application/octet-stream';
    }

    private static function formatMb(int $bytes): string
    {
        return number_format($bytes / 1048576, 0, ',', '.') . ' MB';
    }
}
