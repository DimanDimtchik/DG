<?php
declare(strict_types=1);

/** IMAP-Ordner → dg_mail_log (Hintergrund-Sync für schnelle Post-Ansicht). */
final class MailImapSync
{
    private const SYNC_TTL = 90;

    /**
     * Führt aus: sync folder.
     * @param array $mailbox
     * @param string $viewFolder
     * @param bool $force
     * @return int
     * @throws RuntimeException
     */
    public static function syncFolder(array $mailbox, string $viewFolder, bool $force = false): int
    {
        if (!ImapMailboxClient::hasCredentials($mailbox)) {
            return 0;
        }

        $mailboxId = (int) ($mailbox['id'] ?? 0);
        if ($mailboxId <= 0) {
            return 0;
        }

        $imapPath = MailFolderCatalog::imapPathForView($viewFolder, $mailbox);
        if (!$force && self::syncedRecently($mailboxId, $imapPath)) {
            return 0;
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        if (!ImapMailboxClient::probeInbox($mailbox)) {
            if (ImapMailboxClient::isAuthFailure()) {
                throw new RuntimeException(
                    'IMAP-Anmeldung fehlgeschlagen — das gespeicherte Passwort stimmt nicht mit All-Inkl überein.'
                );
            }
            if (ImapMailboxClient::lastError() !== '') {
                throw new RuntimeException(ImapMailboxClient::lastError());
            }
        }

        self::refreshFolderCache($mailbox);

        $headers = ImapMailboxClient::fetchHeaders($mailbox, $imapPath, 50);
        if ($headers === [] && ImapMailboxClient::isAuthFailure()) {
            throw new RuntimeException(
                'IMAP-Anmeldung fehlgeschlagen — das gespeicherte Passwort stimmt nicht mit All-Inkl überein.'
            );
        }
        foreach ($headers as $header) {
            MailLogRepository::upsertFromImapHeader($mailboxId, $imapPath, $header);
        }

        self::markSynced($mailboxId, $imapPath);

        return count($headers);
    }

    /**
     * Methode refresh folder cache.
     * @param array $mailbox
     * @return void
     */
    private static function refreshFolderCache(array $mailbox): void
    {
        MailFolderCatalog::refreshFoldersFromImap($mailbox);
    }

    /**
     * Führt aus: synced recently.
     * @param int $mailboxId
     * @param string $imapPath
     * @return bool
     */
    private static function syncedRecently(int $mailboxId, string $imapPath): bool
    {
        if (!isset($_SESSION) || session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        $key = $mailboxId . '|' . strtolower(trim($imapPath));
        $at = (int) ($_SESSION['dg_mail_imap_sync'][$key] ?? 0);

        return $at > 0 && (time() - $at) < self::SYNC_TTL;
    }

    /**
     * Methode mark synced.
     * @param int $mailboxId
     * @param string $imapPath
     * @return void
     */
    private static function markSynced(int $mailboxId, string $imapPath): void
    {
        if (!isset($_SESSION) || session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        if (!isset($_SESSION['dg_mail_imap_sync']) || !is_array($_SESSION['dg_mail_imap_sync'])) {
            $_SESSION['dg_mail_imap_sync'] = [];
        }

        $_SESSION['dg_mail_imap_sync'][$mailboxId . '|' . strtolower(trim($imapPath))] = time();
    }
}
