<?php
declare(strict_types=1);

/** Ordnerliste pro Postfach (IMAP + lokale CRM-Daten). */
final class MailFolderCatalog
{
    private const CACHE_TTL = 300;

    /**
     * Methode folders for view.
     * @param array $mailboxes
     * @param int $mailboxFilter
     * @return list<array{path: string, label: string, source: string}>
     */
    public static function foldersForView(array $mailboxes, int $mailboxFilter): array
    {
        if ($mailboxFilter <= 0) {
            return [
                ['path' => 'INBOX', 'label' => 'Posteingang', 'source' => 'local'],
                ['path' => '__sent__', 'label' => 'Gesendet', 'source' => 'local'],
            ];
        }

        $mailbox = null;
        foreach ($mailboxes as $box) {
            if ((int) ($box['id'] ?? 0) === $mailboxFilter) {
                $mailbox = $box;
                break;
            }
        }
        if ($mailbox === null) {
            return self::defaultFolders();
        }

        return self::foldersForMailbox($mailbox);
    }

    /**
     * Methode folders for mailbox.
     * @param array $mailbox
     * @return list<array{path: string, label: string, source: string}>
     */
    public static function foldersForMailbox(array $mailbox): array
    {
        $mailboxId = (int) ($mailbox['id'] ?? 0);
        if ($mailboxId <= 0) {
            return self::defaultFolders();
        }

        $cached = self::cacheGet($mailboxId);
        if ($cached !== null) {
            return $cached;
        }

        if (self::refreshFoldersFromImap($mailbox)) {
            $cached = self::cacheGet($mailboxId);
            if ($cached !== null) {
                return $cached;
            }
        }

        return self::defaultFolders();
    }

    /**
     * Methode refresh folders from imap.
     * @param array $mailbox
     * @return bool
     */
    public static function refreshFoldersFromImap(array $mailbox): bool
    {
        $mailboxId = (int) ($mailbox['id'] ?? 0);
        if ($mailboxId <= 0 || !ImapMailboxClient::hasCredentials($mailbox)) {
            return false;
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(60);
        }

        $folders = ImapMailboxClient::listFolders($mailbox);
        if ($folders === []) {
            return false;
        }

        $rows = [];
        foreach ($folders as $folder) {
            $path = (string) ($folder['path'] ?? '');
            if ($path === '') {
                continue;
            }
            $rows[] = [
                'path' => $path,
                'label' => (string) ($folder['label'] ?? MailFolderLabels::labelForPath($path)),
                'source' => 'imap',
            ];
        }

        if ($rows === []) {
            return false;
        }

        self::storeFoldersCache($mailboxId, $rows);

        return true;
    }

    /**
     * Führt aus: store folders cache.
     * @param int $mailboxId Postfach-ID
     * @param array $folders
     * @return void
     */
    public static function storeFoldersCache(int $mailboxId, array $folders): void
    {
        self::cacheSet($mailboxId, $folders);
    }

    /**
     * Methode uses local inbox.
     * @param string $folderPath
     * @return bool
     */
    public static function usesLocalInbox(string $folderPath): bool
    {
        return $folderPath === '' || MailFolderLabels::isInbox($folderPath);
    }

    /**
     * Methode uses local sent.
     * @param string $folderPath
     * @return bool
     */
    public static function usesLocalSent(string $folderPath): bool
    {
        return $folderPath === '__sent__' || MailFolderLabels::isSent($folderPath);
    }

    /**
     * Methode uses imap live.
     * @param string $folderPath
     * @param int $mailboxFilter
     * @param array|null $mailbox
     * @return bool
     */
    public static function usesImapLive(string $folderPath, int $mailboxFilter, ?array $mailbox = null): bool
    {
        if ($mailboxFilter <= 0 || $mailbox === null) {
            return false;
        }

        return ImapMailboxClient::hasCredentials($mailbox);
    }

    /**
     * Methode imap path for view.
     * @param string $folderPath
     * @param array $mailbox
     * @return string
     */
    public static function imapPathForView(string $folderPath, array $mailbox): string
    {
        if (self::usesLocalInbox($folderPath)) {
            return 'INBOX';
        }

        if ($folderPath === '__sent__') {
            foreach (self::foldersForMailbox($mailbox) as $folder) {
                if (MailFolderLabels::isSent((string) ($folder['path'] ?? ''))) {
                    return (string) $folder['path'];
                }
            }

            return 'Sent';
        }

        if (self::usesLocalSent($folderPath)) {
            return $folderPath;
        }

        return $folderPath;
    }

    /**
     * Methode default folders.
     * @return array<string, mixed>
     */
    private static function defaultFolders(): array
    {
        return [
            ['path' => 'INBOX', 'label' => 'Posteingang', 'source' => 'local'],
            ['path' => '__sent__', 'label' => 'Gesendet', 'source' => 'local'],
        ];
    }

    /**
     * Methode cache get.
     * @param int $mailboxId
     * @return array|null
     */
    private static function cacheGet(int $mailboxId): ?array
    {
        $bucket = $_SESSION['dg_mail_folders'][$mailboxId] ?? null;
        if (!is_array($bucket)) {
            return null;
        }
        $at = (int) ($bucket['at'] ?? 0);
        if ($at <= 0 || (time() - $at) > self::CACHE_TTL) {
            return null;
        }
        $folders = $bucket['folders'] ?? null;

        return is_array($folders) ? $folders : null;
    }

    /**
     * Methode cache set.
     * @param int $mailboxId Postfach-ID
     * @param array $folders
     * @return void
     */
    private static function cacheSet(int $mailboxId, array $folders): void
    {
        if (!isset($_SESSION) || session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        if (!isset($_SESSION['dg_mail_folders']) || !is_array($_SESSION['dg_mail_folders'])) {
            $_SESSION['dg_mail_folders'] = [];
        }
        $_SESSION['dg_mail_folders'][$mailboxId] = [
            'at' => time(),
            'folders' => $folders,
        ];
    }
}
