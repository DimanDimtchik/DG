<?php
declare(strict_types=1);

/** Liest Text/HTML aus archivierten .eml-Dateien. */
final class MailMimeReader
{
    /**
     * Methode bodies from file.
     * @param string $absolutePath
     * @return array<string, mixed>
     */
    public static function bodiesFromFile(string $absolutePath): array
    {
        if (!is_readable($absolutePath)) {
            return ['html' => '', 'text' => ''];
        }

        $mime = file_get_contents($absolutePath);
        if ($mime === false || $mime === '') {
            return ['html' => '', 'text' => ''];
        }

        return self::bodiesFromMime($mime);
    }

    /**
     * Methode bodies from mime.
     * @param string $mime
     * @return array<string, mixed>
     */
    public static function bodiesFromMime(string $mime): array
    {
        $html = '';
        $text = '';
        $normalized = str_replace("\r\n", "\n", $mime);

        if (preg_match('/boundary="?([^"\s;]+)"?/i', $normalized, $match) === 1) {
            $boundary = $match[1];
            foreach (explode('--' . $boundary, $normalized) as $section) {
                $section = trim($section);
                if ($section === '' || $section === '--') {
                    continue;
                }

                $part = self::decodeMimePart($section);
                if ($part === null) {
                    continue;
                }

                $contentType = strtolower($part['content_type']);
                if (str_contains($contentType, 'text/html') && $html === '') {
                    $html = $part['body'];
                } elseif (str_contains($contentType, 'text/plain') && $text === '') {
                    $text = $part['body'];
                }
            }
        } elseif (preg_match('/Content-Type:\s*text\/html/i', $normalized) === 1) {
            $html = self::bodyAfterHeaders($normalized);
        } elseif (preg_match('/Content-Type:\s*text\/plain/i', $normalized) === 1) {
            $text = self::bodyAfterHeaders($normalized);
        } elseif (stripos($normalized, '<html') !== false) {
            $html = $normalized;
        }

        return [
            'html' => trim($html),
            'text' => trim($text),
        ];
    }

    /**
     * Methode decode mime part.
     * @param string $section
     * @return array|null
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

        foreach (explode("\n", $headerBlock) as $line) {
            if (preg_match('/^Content-Type:\s*([^;\s]+)/i', trim($line), $match) === 1) {
                $contentType = strtolower(trim($match[1]));
            }
            if (preg_match('/^Content-Transfer-Encoding:\s*(\S+)/i', trim($line), $match) === 1) {
                $encoding = strtolower(trim($match[1]));
            }
        }

        $body = trim($body);
        if ($encoding === 'base64') {
            $decoded = base64_decode(preg_replace('/\s+/', '', $body) ?? '', true);
            $body = $decoded !== false ? $decoded : $body;
        } elseif ($encoding === 'quoted-printable') {
            $body = quoted_printable_decode($body);
        }

        return [
            'content_type' => $contentType,
            'body' => $body,
        ];
    }

    /**
     * Methode body after headers.
     * @param string $mime
     * @return string
     */
    private static function bodyAfterHeaders(string $mime): string
    {
        $parts = preg_split("/\n\n/", $mime, 2);

        return is_array($parts) && isset($parts[1]) ? trim($parts[1]) : '';
    }
}
