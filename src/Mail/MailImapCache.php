<?php
declare(strict_types=1);

/** Kurzzeit-Cache für IMAP-Ordnerlisten (Session). */
final class MailImapCache
{
    private const HEADER_TTL = 45;

    /**
     * Methode headers.
     * @param int $mailboxId
     * @param string $folderPath
     * @return array|null
     */
    public static function headers(int $mailboxId, string $folderPath): ?array
    {
        if ($mailboxId <= 0 || !self::sessionActive()) {
            return null;
        }

        $bucket = $_SESSION['dg_mail_imap_headers'][self::headerKey($mailboxId, $folderPath)] ?? null;
        if (!is_array($bucket)) {
            return null;
        }
        if ((time() - (int) ($bucket['at'] ?? 0)) > self::HEADER_TTL) {
            return null;
        }

        $rows = $bucket['rows'] ?? null;

        return is_array($rows) ? $rows : null;
    }

    /**
     * Führt aus: store headers.
     * @param int $mailboxId
     * @param string $folderPath
     * @param array $rows
     * @return void
     */
    public static function storeHeaders(int $mailboxId, string $folderPath, array $rows): void
    {
        if ($mailboxId <= 0 || !self::sessionActive()) {
            return;
        }
        if (!isset($_SESSION['dg_mail_imap_headers']) || !is_array($_SESSION['dg_mail_imap_headers'])) {
            $_SESSION['dg_mail_imap_headers'] = [];
        }

        $_SESSION['dg_mail_imap_headers'][self::headerKey($mailboxId, $folderPath)] = [
            'at' => time(),
            'rows' => $rows,
        ];
    }

    /**
     * Methode should bypass.
     * @return bool
     */
    public static function shouldBypass(): bool
    {
        return !empty($_GET['refresh']);
    }

    /**
     * Methode header key.
     * @param int $mailboxId
     * @param string $folderPath
     * @return string
     */
    private static function headerKey(int $mailboxId, string $folderPath): string
    {
        return $mailboxId . '|' . strtolower(trim($folderPath));
    }

    /**
     * Methode session active.
     * @return bool
     */
    private static function sessionActive(): bool
    {
        return isset($_SESSION) && session_status() === PHP_SESSION_ACTIVE;
    }
}
