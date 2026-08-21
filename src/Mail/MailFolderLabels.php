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
        'ENTWURF' => 'Entwürfe',
        'TRASH' => 'Papierkorb',
        'DELETED' => 'Papierkorb',
        'DELETED ITEMS' => 'Papierkorb',
        'DELETED MESSAGES' => 'Papierkorb',
        'PAPIERKORB' => 'Papierkorb',
        'GELÖSCHT' => 'Papierkorb',
        'GELOESCHT' => 'Papierkorb',
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
        $upper = strtoupper($name);

        return self::LABELS[$upper] ?? $name;
    }

    /**
     * Prüft: is inbox.
     * @param string $imapPath
     * @return bool
     */
    public static function isInbox(string $imapPath): bool
    {
        $upper = strtoupper(self::baseName(self::decodePath($imapPath)));

        return $upper === 'INBOX' || $upper === 'POSTEINGANG';
    }

    /**
     * Prüft: is sent.
     * @param string $imapPath
     * @return bool
     */
    public static function isSent(string $imapPath): bool
    {
        $upper = strtoupper(self::baseName(self::decodePath($imapPath)));

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
        if (function_exists('imap_utf7_decode')) {
            $decoded = @imap_utf7_decode($path);
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }

        return $path;
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
