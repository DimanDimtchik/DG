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
     * Methode label for path.
     * @param string $imapPath
     * @return string
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
        $ascii = strtr($upper, [
            'Ä' => 'AE',
            'Ö' => 'OE',
            'Ü' => 'UE',
            'ß' => 'SS',
        ]);
        if ($ascii !== $upper && isset(self::LABELS[$ascii])) {
            return self::LABELS[$ascii];
        }

        return $name;
    }

    /**
     * Prüft: is inbox.
     * @param string $imapPath
     * @return bool
     */
    public static function isInbox(string $imapPath): bool
    {
        $upper = mb_strtoupper(self::baseName(self::decodePath($imapPath)), 'UTF-8');

        return $upper === 'INBOX' || $upper === 'POSTEINGANG';
    }

    /**
     * Prüft: is sent.
     * @param string $imapPath
     * @return bool
     */
    public static function isSent(string $imapPath): bool
    {
        $upper = mb_strtoupper(self::baseName(self::decodePath($imapPath)), 'UTF-8');

        return in_array($upper, ['SENT', 'GESENDET', 'SENT ITEMS', 'SENT MESSAGES', 'GESENDETE OBJEKTE'], true);
    }

    /**
     * Methode decode path.
     * @param string $path
     * @return string
     */
    public static function decodePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return 'INBOX';
        }

        $decoded = $path;
        if (str_contains($path, '&')) {
            // Modified UTF-7 (IMAP): z. B. Entw&APw-rfe → Entwürfe
            if (function_exists('imap_utf7_decode')) {
                $try = @imap_utf7_decode($path);
                if (is_string($try) && $try !== '' && $try !== $path) {
                    $decoded = $try;
                }
            }
            // Fallback / Nachbesserung: eigene UTF-7→UTF-8 (ohne IMAP-Extension)
            if ($decoded === $path || str_contains($decoded, '&')) {
                $decoded = self::decodeModifiedUtf7($path);
            }
        }

        // imap_utf7_decode liefert oft ISO-8859-1 — für die UI nach UTF-8
        if ($decoded !== '' && !mb_check_encoding($decoded, 'UTF-8')) {
            $utf8 = @mb_convert_encoding($decoded, 'UTF-8', 'ISO-8859-1');
            if (is_string($utf8) && $utf8 !== '') {
                $decoded = $utf8;
            }
        }

        // Manche Builds liefern UTF-16 mit Nullbytes
        if (str_contains($decoded, "\0")) {
            $utf8 = @mb_convert_encoding($decoded, 'UTF-8', 'UTF-16LE');
            if (is_string($utf8) && $utf8 !== '' && !str_contains($utf8, "\0")) {
                $decoded = $utf8;
            } else {
                $decoded = str_replace("\0", '', $decoded);
            }
        }

        return $decoded !== '' ? $decoded : $path;
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
     * Methode base name.
     * @param string $path
     * @return string
     */
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
}
