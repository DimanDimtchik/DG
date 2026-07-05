<?php
declare(strict_types=1);

/** Kurzzeit-Cache für IMAP-Ordnerlisten (Session). */
final class MailImapCache
{
    private const HEADER_TTL = 45;

    /** @return list<array<string, mixed>>|null */
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

    /** @param list<array<string, mixed>> $rows */
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

    public static function shouldBypass(): bool
    {
        return !empty($_GET['refresh']);
    }

    private static function headerKey(int $mailboxId, string $folderPath): string
    {
        return $mailboxId . '|' . strtolower(trim($folderPath));
    }

    private static function sessionActive(): bool
    {
        return isset($_SESSION) && session_status() === PHP_SESSION_ACTIVE;
    }
}
