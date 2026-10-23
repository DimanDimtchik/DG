<?php
declare(strict_types=1);

/** Deutsche Anzeigenamen für gängige IMAP-Ordner (All-Inkl, Gmail, …). */
final class MailFolderLabels
{
    /** @var array<string, string> */
    private const LABELS = [
        'INBOX' => 'Posteingang',
        'SENT' => 'Gesendet',
        'SENT ITEMS' => 'Gesendet',
        'SENT MESSAGES' => 'Gesendet',
        'GESENDET' => 'Gesendet',
        'GESENDETE OBJEKTE' => 'Gesendet',
        'DRAFTS' => 'Entwürfe',
        'DRAFT' => 'Entwürfe',
        'ENTWUERFE' => 'Entwürfe',
        'ENTWÜRFE' => 'Entwürfe',
        'ENTWURF' => 'Entwürfe',
        'TRASH' => 'Papierkorb',
        'DELETED' => 'Papierkorb',
        'DELETED ITEMS' => 'Papierkorb',
        'DELETED MESSAGES' => 'Papierkorb',
        'PAPIERKORB' => 'Papierkorb',
        'GELÖSCHT' => 'Papierkorb',
        'GELOESCHT' => 'Papierkorb',
        'GELÖSCHTE OBJEKTE' => 'Papierkorb',
        'JUNK' => 'Spam',
        'SPAM' => 'Spam',
        'BULK' => 'Spam',
        'ARCHIVE' => 'Archiv',
        'ARCHIV' => 'Archiv',
        'OUTBOX' => 'Postausgang',
    ];

    /**
     * Anzeigename für IMAP-Pfad (Modified UTF-7 oder UTF-8).
     */
    public static function labelForPath(string $imapPath): string
    {
        $imapPath = self::decodePath($imapPath);
        $name = self::baseName($imapPath);
        $upper = mb_strtoupper($name, 'UTF-8');
        if (isset(self::LABELS[$upper])) {
            return self::LABELS[$upper];
        }
        // IMAP liefert oft Umlaute; Lookup auch ohne Umlaute (ENTWÜRFE → ENTWUERFE)
        $ascii = self::foldUmlauts($upper);
        if ($ascii !== $upper && isset(self::LABELS[$ascii])) {
            return self::LABELS[$ascii];
        }

        return $name;
    }

    public static function isInbox(string $imapPath): bool
    {
        $upper = mb_strtoupper(self::baseName(self::decodePath($imapPath)), 'UTF-8');

        return $upper === 'INBOX' || $upper === 'POSTEINGANG';
    }

    public static function isSent(string $imapPath): bool
    {
        $upper = mb_strtoupper(self::baseName(self::decodePath($imapPath)), 'UTF-8');

        return in_array($upper, ['SENT', 'GESENDET', 'SENT ITEMS', 'SENT MESSAGES', 'GESENDETE OBJEKTE'], true);
    }

    public static function isDrafts(string $imapPath): bool
    {
        $upper = self::foldUmlauts(mb_strtoupper(self::baseName(self::decodePath($imapPath)), 'UTF-8'));

        return in_array($upper, ['DRAFTS', 'DRAFT', 'ENTWUERFE', 'ENTWURF'], true);
    }

    /**
     * Modified UTF-7 / fehlerhafte imap_utf7_decode-Ausgabe → UTF-8.
     *
     * Hinweis: PHP imap_utf7_decode liefert auf manchen Builds gemischt 8-Bit + NUL+Byte
     * (z. B. Entw\0\xFCrfe). Das darf nicht als UTF-16LE gelesen werden (sonst CJK-Müll).
     */
    public static function decodePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return 'INBOX';
        }

        $decoded = $path;
        if (str_contains($path, '&')) {
            // Eigene Dekodierung zuerst — liefert zuverlässig UTF-8
            $decoded = self::decodeModifiedUtf7($path);
            if ($decoded === $path || self::stillLooksLikeModifiedUtf7($decoded)) {
                if (function_exists('imap_utf7_decode')) {
                    $try = @imap_utf7_decode($path);
                    if (is_string($try) && $try !== '') {
                        $decoded = self::normalizeImapUtf7Binary($try);
                    }
                }
            }
        } else {
            $decoded = self::normalizeImapUtf7Binary($path);
        }

        if ($decoded !== '' && !mb_check_encoding($decoded, 'UTF-8')) {
            $utf8 = @mb_convert_encoding($decoded, 'UTF-8', 'ISO-8859-1');
            if (is_string($utf8) && $utf8 !== '') {
                $decoded = $utf8;
            }
        }

        // Restliche NULs entfernen (dürfen in Ordnernamen nicht stehen)
        if (str_contains($decoded, "\0")) {
            $decoded = str_replace("\0", '', $decoded);
        }

        return $decoded !== '' ? $decoded : $path;
    }

    /**
     * UTF-8-Ordnername → Modified UTF-7 für imap_open (falls nötig).
     */
    public static function encodePathForImap(string $path): string
    {
        $path = trim($path);
        if ($path === '' || $path === 'INBOX') {
            return $path !== '' ? $path : 'INBOX';
        }
        if ($path === '__sent__') {
            return 'Sent';
        }
        // Schon Modified UTF-7
        if (self::stillLooksLikeModifiedUtf7($path)) {
            return $path;
        }
        // Reines ASCII
        if (preg_match('/^[\x20-\x7E]+$/', $path) === 1) {
            return $path;
        }

        return self::encodeModifiedUtf7($path);
    }

    public static function baseName(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return 'INBOX';
        }
        if (str_contains($path, '.')) {
            $parts = explode('.', $path);

            return (string) end($parts);
        }
        if (str_contains($path, '/')) {
            $parts = explode('/', $path);

            return (string) end($parts);
        }

        return $path;
    }

    private static function foldUmlauts(string $upper): string
    {
        return strtr($upper, [
            'Ä' => 'AE',
            'Ö' => 'OE',
            'Ü' => 'UE',
            'ß' => 'SS',
        ]);
    }

    private static function stillLooksLikeModifiedUtf7(string $value): bool
    {
        return (bool) preg_match('/&[A-Za-z0-9+,]+-/', $value);
    }

    /**
     * imap_utf7_decode-Quirk: „Entw\0\xFCrfe“ → „Entwürfe“ (ISO-8859-1-Bytes).
     */
    private static function normalizeImapUtf7Binary(string $value): string
    {
        if (!str_contains($value, "\0")) {
            return $value;
        }
        $stripped = preg_replace('/\0(.)/s', '$1', $value);
        if (is_string($stripped) && $stripped !== '') {
            return $stripped;
        }

        return str_replace("\0", '', $value);
    }

    /**
     * IMAP Modified UTF-7 → UTF-8 (RFC 3501), ohne php-imap.
     */
    private static function decodeModifiedUtf7(string $value): string
    {
        $out = preg_replace_callback(
            '/&([^-]*)-/',
            static function (array $m): string {
                if ($m[1] === '') {
                    return '&';
                }
                $b64 = strtr($m[1], ',', '/');
                $pad = strlen($b64) % 4;
                if ($pad > 0) {
                    $b64 .= str_repeat('=', 4 - $pad);
                }
                $raw = base64_decode($b64, true);
                if ($raw === false || $raw === '') {
                    return $m[0];
                }
                $utf8 = @mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE');

                return is_string($utf8) && $utf8 !== '' ? $utf8 : $m[0];
            },
            $value
        );

        return is_string($out) && $out !== '' ? $out : $value;
    }

    /**
     * UTF-8 → IMAP Modified UTF-7 (RFC 3501).
     */
    private static function encodeModifiedUtf7(string $utf8): string
    {
        $out = '';
        $len = mb_strlen($utf8, 'UTF-8');
        $buffer = '';
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($utf8, $i, 1, 'UTF-8');
            $ord = strlen($ch) === 1 ? ord($ch) : 0;
            $isAsciiPrintable = strlen($ch) === 1 && $ord >= 0x20 && $ord <= 0x7e && $ch !== '&';
            if ($isAsciiPrintable) {
                if ($buffer !== '') {
                    $out .= self::flushUtf7Buffer($buffer);
                    $buffer = '';
                }
                $out .= $ch;
                continue;
            }
            if ($ch === '&') {
                if ($buffer !== '') {
                    $out .= self::flushUtf7Buffer($buffer);
                    $buffer = '';
                }
                $out .= '&-';
                continue;
            }
            $buffer .= $ch;
        }
        if ($buffer !== '') {
            $out .= self::flushUtf7Buffer($buffer);
        }

        return $out !== '' ? $out : $utf8;
    }

    private static function flushUtf7Buffer(string $utf8Chunk): string
    {
        $raw = @mb_convert_encoding($utf8Chunk, 'UTF-16BE', 'UTF-8');
        if (!is_string($raw) || $raw === '') {
            return $utf8Chunk;
        }
        $b64 = base64_encode($raw);
        $b64 = rtrim($b64, '=');
        $b64 = strtr($b64, '/', ',');

        return '&' . $b64 . '-';
    }
}
