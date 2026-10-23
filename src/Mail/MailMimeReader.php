<?php

declare(strict_types=1);

/**
 * Liest Text/HTML und Anhänge aus MIME (.eml / IMAP RAW).
 */
final class MailMimeReader
{
    /**
     * @return array{html: string, text: string}
     */
    public static function bodiesFromFile(string $absolutePath): array
    {
        $parsed = self::parseFile($absolutePath);

        return ['html' => $parsed['html'], 'text' => $parsed['text']];
    }

    /**
     * @return array{html: string, text: string}
     */
    public static function bodiesFromMime(string $mime): array
    {
        $parsed = self::parse($mime);

        return ['html' => $parsed['html'], 'text' => $parsed['text']];
    }

    /**
     * @return array{
     *   html: string,
     *   text: string,
     *   attachments: list<array{filename: string, mime: string, content: string, size: int}>
     * }
     */
    public static function parseFile(string $absolutePath): array
    {
        if (!is_readable($absolutePath)) {
            return ['html' => '', 'text' => '', 'attachments' => []];
        }
        $mime = file_get_contents($absolutePath);
        if ($mime === false || $mime === '') {
            return ['html' => '', 'text' => '', 'attachments' => []];
        }

        return self::parse($mime);
    }

    /**
     * @return array{
     *   html: string,
     *   text: string,
     *   attachments: list<array{filename: string, mime: string, content: string, size: int}>
     * }
     */
    public static function parse(string $mime): array
    {
        $html = '';
        $text = '';
        $attachments = [];
        self::walkParts($mime, $html, $text, $attachments, true);

        return [
            'html' => trim($html),
            'text' => trim($text),
            'attachments' => $attachments,
        ];
    }

    /**
     * @param list<array{filename: string, mime: string, content: string, size: int}> $attachments
     */
    private static function walkParts(
        string $mime,
        string &$html,
        string &$text,
        array &$attachments,
        bool $isRoot,
    ): void {
        $normalized = str_replace("\r\n", "\n", $mime);

        if (preg_match('/boundary="?([^"\s;]+)"?/i', $normalized, $match) === 1) {
            $boundary = $match[1];
            foreach (explode('--' . $boundary, $normalized) as $i => $section) {
                if ($i === 0) {
                    continue;
                }
                $section = trim($section);
                if ($section === '' || $section === '--') {
                    continue;
                }
                if (preg_match('/boundary="?([^"\s;]+)"?/i', $section) === 1) {
                    self::walkParts($section, $html, $text, $attachments, false);
                    continue;
                }
                $part = self::decodeMimePart($section);
                if ($part === null) {
                    continue;
                }
                self::collectPart($part, $html, $text, $attachments);
            }

            return;
        }

        if ($isRoot) {
            $part = self::decodeMimePart($normalized);
            if ($part !== null) {
                self::collectPart($part, $html, $text, $attachments);

                return;
            }
            if (preg_match('/Content-Type:\s*text\/html/i', $normalized) === 1) {
                $html = self::bodyAfterHeaders($normalized);
            } elseif (preg_match('/Content-Type:\s*text\/plain/i', $normalized) === 1) {
                $text = self::bodyAfterHeaders($normalized);
            } elseif (stripos($normalized, '<html') !== false) {
                $html = $normalized;
            } else {
                $text = self::bodyAfterHeaders($normalized);
            }
        }
    }

    /**
     * @param array{content_type: string, body: string, filename: string, is_attachment: bool} $part
     * @param list<array{filename: string, mime: string, content: string, size: int}> $attachments
     */
    private static function collectPart(array $part, string &$html, string &$text, array &$attachments): void
    {
        $contentType = strtolower($part['content_type']);
        if ($part['is_attachment'] || (!str_starts_with($contentType, 'text/') && $contentType !== 'message/rfc822')) {
            if ($part['body'] === '' && !$part['is_attachment']) {
                return;
            }
            $name = $part['filename'] !== '' ? $part['filename'] : 'anhang.bin';
            $attachments[] = [
                'filename' => $name,
                'mime' => $contentType !== '' ? $contentType : 'application/octet-stream',
                'content' => $part['body'],
                'size' => strlen($part['body']),
            ];

            return;
        }

        if (str_contains($contentType, 'text/html') && $html === '') {
            $html = $part['body'];
        } elseif (str_contains($contentType, 'text/plain') && $text === '') {
            $text = $part['body'];
        }
    }

    /**
     * @return array{content_type: string, body: string, filename: string, is_attachment: bool}|null
     */
    private static function decodeMimePart(string $section): ?array
    {
        $parts = preg_split("/\n\n/", $section, 2);
        if (!is_array($parts) || count($parts) < 2) {
            return null;
        }

        [$headerBlock, $body] = $parts;
        $contentType = 'text/plain';
        $encoding = '';
        $filename = '';
        $disposition = '';

        $headerBlock = preg_replace("/\n[ \t]+/", ' ', $headerBlock) ?? $headerBlock;
        foreach (explode("\n", $headerBlock) as $line) {
            $line = trim($line);
            if (preg_match('/^Content-Type:\s*([^;\s]+)/i', $line, $match) === 1) {
                $contentType = strtolower(trim($match[1]));
                if (preg_match('/name\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $line, $m) === 1) {
                    $filename = self::decodeFilename(trim($m[1]));
                }
            }
            if (preg_match('/^Content-Transfer-Encoding:\s*(\S+)/i', $line, $match) === 1) {
                $encoding = strtolower(trim($match[1]));
            }
            if (preg_match('/^Content-Disposition:\s*([^;\s]+)/i', $line, $match) === 1) {
                $disposition = strtolower(trim($match[1]));
                if (preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $line, $m) === 1) {
                    $filename = self::decodeFilename(trim($m[1]));
                }
            }
        }

        $body = trim($body);
        if ($encoding === 'base64') {
            $decoded = base64_decode(preg_replace('/\s+/', '', $body) ?? '', true);
            $body = $decoded !== false ? $decoded : $body;
        } elseif ($encoding === 'quoted-printable') {
            $body = quoted_printable_decode($body);
        }

        $isAttachment = $disposition === 'attachment'
            || ($disposition === 'inline' && $filename !== '' && !str_starts_with($contentType, 'text/'));

        return [
            'content_type' => $contentType,
            'body' => $body,
            'filename' => $filename,
            'is_attachment' => $isAttachment,
        ];
    }

    private static function decodeFilename(string $name): string
    {
        $name = rawurldecode(str_replace(["\r", "\n"], '', $name));
        if (preg_match('/^=\?.*\?=$/', $name) === 1 && function_exists('iconv_mime_decode')) {
            $decoded = @iconv_mime_decode($name, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            if (is_string($decoded) && $decoded !== '') {
                $name = $decoded;
            }
        }

        return preg_replace('/[^\w.\- ()äöüÄÖÜß]+/u', '_', $name) ?: 'anhang.bin';
    }

    private static function bodyAfterHeaders(string $mime): string
    {
        $parts = preg_split("/\n\n/", $mime, 2);

        return is_array($parts) && isset($parts[1]) ? trim($parts[1]) : '';
    }
}
