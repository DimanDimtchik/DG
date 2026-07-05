<?php
declare(strict_types=1);

/** IMAP-Zugriff für Ordnerliste und Nachrichtenköpfe (Provider wie All-Inkl). */
final class ImapMailboxClient
{
    /** @var array<string, mixed|false> */
    private static array $connections = [];

    private static string $lastError = '';

    private static bool $shutdownRegistered = false;

    public static function isAvailable(): bool
    {
        return function_exists('imap_open');
    }

    public static function lastError(): string
    {
        return self::$lastError;
    }

    public static function isAuthFailure(): bool
    {
        $error = strtolower(self::$lastError);

        return str_contains($error, 'auth')
            || str_contains($error, 'authenticationfailed')
            || str_contains($error, 'invalid credentials');
    }

    /**
     * @param array<string, mixed> $mailbox
     */
    public static function probeInbox(array $mailbox): bool
    {
        if (!self::isAvailable() || !self::hasCredentials($mailbox)) {
            self::$lastError = 'IMAP-Zugangsdaten unvollständig.';

            return false;
        }

        $key = (string) (int) ($mailbox['id'] ?? 0) . '|probe';
        if (isset(self::$connections[$key]) && self::$connections[$key] !== false) {
            @imap_close(self::$connections[$key]);
            unset(self::$connections[$key]);
        }

        self::clearImapErrors();
        $connection = self::acquire($mailbox, 'INBOX');

        return $connection !== false;
    }

    /** @param array<string, mixed> $mailbox */
    public static function hasCredentials(array $mailbox): bool
    {
        return trim((string) ($mailbox['imap_host'] ?? '')) !== ''
            && trim((string) ($mailbox['imap_username'] ?? '')) !== ''
            && trim((string) ($mailbox['imap_password'] ?? '')) !== '';
    }

    /**
     * @param array<string, mixed> $mailbox
     * @return list<array{path: string, label: string}>
     */
    public static function listFolders(array $mailbox): array
    {
        if (!self::isAvailable()) {
            return self::fallbackFolders();
        }

        $connection = self::acquire($mailbox, 'INBOX');
        if ($connection === false) {
            return self::fallbackFolders();
        }

        $ref = self::serverRef($mailbox);
        $raw = @imap_list($connection, $ref, '*') ?: [];
        $folders = [];
        foreach ($raw as $full) {
            if (!is_string($full) || $full === '') {
                continue;
            }
            $path = self::pathFromFullMailbox($full, $ref);
            if ($path === '') {
                continue;
            }
            $folders[$path] = [
                'path' => $path,
                'label' => MailFolderLabels::labelForPath($path),
            ];
        }

        if ($folders === []) {
            return self::fallbackFolders();
        }

        uasort($folders, static function (array $a, array $b): int {
            return self::sortRank($a['path']) <=> self::sortRank($b['path'])
                ?: strcasecmp($a['label'], $b['label']);
        });

        return array_values($folders);
    }

    /**
     * @param array<string, mixed> $mailbox
     * @return list<array<string, mixed>>
     */
    public static function fetchHeaders(array $mailbox, string $folder, int $limit = 50): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        $connection = self::acquire($mailbox, $folder);
        if ($connection === false) {
            return [];
        }

        $count = (int) @imap_num_msg($connection);
        if ($count <= 0) {
            return [];
        }

        $start = max(1, $count - max(1, $limit) + 1);
        $rows = [];
        for ($msgNo = $count; $msgNo >= $start; $msgNo--) {
            $header = @imap_headerinfo($connection, $msgNo);
            if ($header === false) {
                continue;
            }
            $from = $header->from[0] ?? null;
            $fromEmail = '';
            $fromName = '';
            if ($from !== null) {
                $mailboxPart = (string) ($from->mailbox ?? '');
                $hostPart = (string) ($from->host ?? '');
                $fromEmail = $mailboxPart !== '' && $hostPart !== '' ? $mailboxPart . '@' . $hostPart : '';
                $fromName = isset($from->personal) ? self::decodeMime((string) $from->personal) : '';
            }

            $subject = isset($header->subject) ? self::decodeMime((string) $header->subject) : '';
            $date = isset($header->date) ? (string) $header->date : '';
            $uid = function_exists('imap_uid') ? (int) @imap_uid($connection, $msgNo) : $msgNo;
            $isRead = empty($header->Unseen) && empty($header->Recent);

            $rows[] = [
                'id' => 0,
                'imap_uid' => $uid,
                'imap_folder' => $folder,
                'mailbox_id' => (int) ($mailbox['id'] ?? 0),
                'from_address' => $fromEmail,
                'from_name' => $fromName,
                'subject' => $subject,
                'received_at' => $date,
                'created_at' => $date,
                'is_read' => $isRead ? 1 : 0,
                'source' => 'imap',
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $mailbox
     * @return array<string, mixed>|null
     */
    public static function fetchMessage(array $mailbox, string $folder, int $uid): ?array
    {
        if (!self::isAvailable() || $uid <= 0) {
            return null;
        }

        $connection = self::acquire($mailbox, $folder);
        if ($connection === false) {
            return null;
        }

        $msgNo = function_exists('imap_msgno') ? (int) @imap_msgno($connection, $uid) : 0;
        if ($msgNo < 1) {
            return null;
        }

        $header = @imap_headerinfo($connection, $msgNo);
        if ($header === false) {
            return null;
        }

        $from = $header->from[0] ?? null;
        $fromEmail = '';
        $fromName = '';
        if ($from !== null) {
            $mailboxPart = (string) ($from->mailbox ?? '');
            $hostPart = (string) ($from->host ?? '');
            $fromEmail = $mailboxPart !== '' && $hostPart !== '' ? $mailboxPart . '@' . $hostPart : '';
            $fromName = isset($from->personal) ? self::decodeMime((string) $from->personal) : '';
        }

        $body = (string) @imap_body($connection, (string) $msgNo);
        if ($body === '') {
            $body = (string) @imap_fetchbody($connection, $msgNo, '1');
        }

        return [
            'id' => 0,
            'imap_uid' => $uid,
            'imap_folder' => $folder,
            'mailbox_id' => (int) ($mailbox['id'] ?? 0),
            'direction' => 'in',
            'from_address' => $fromEmail,
            'from_name' => $fromName,
            'subject' => isset($header->subject) ? self::decodeMime((string) $header->subject) : '',
            'body_preview' => trim(strip_tags($body)),
            'received_at' => isset($header->date) ? (string) $header->date : '',
            'created_at' => isset($header->date) ? (string) $header->date : '',
            'source' => 'imap',
        ];
    }

    public static function releaseConnections(): void
    {
        foreach (self::$connections as $connection) {
            if ($connection !== false) {
                @imap_close($connection);
            }
        }
        self::$connections = [];
    }

    /** @return list<array{path: string, label: string}> */
    public static function fallbackFolders(): array
    {
        return [
            ['path' => 'INBOX', 'label' => 'Posteingang'],
            ['path' => 'Sent', 'label' => 'Gesendet'],
            ['path' => 'Drafts', 'label' => 'Entwürfe'],
            ['path' => 'Trash', 'label' => 'Papierkorb'],
            ['path' => 'Junk', 'label' => 'Spam'],
        ];
    }

    /** @param array<string, mixed> $mailbox */
    private static function acquire(array $mailbox, string $folder): mixed
    {
        self::configureTimeouts();
        self::registerShutdown();

        $host = trim((string) ($mailbox['imap_host'] ?? ''));
        $user = trim((string) ($mailbox['imap_username'] ?? ''));
        $pass = (string) ($mailbox['imap_password'] ?? '');
        if ($host === '' || $user === '' || $pass === '') {
            return false;
        }

        $folder = trim($folder) !== '' ? trim($folder) : 'INBOX';
        $key = (string) (int) ($mailbox['id'] ?? 0) . '|' . $host . '|' . $user;
        $target = self::mailboxString($mailbox, $folder);

        if (isset(self::$connections[$key]) && self::$connections[$key] !== false) {
            if (@imap_reopen(self::$connections[$key], $target)) {
                return self::$connections[$key];
            }
            @imap_close(self::$connections[$key]);
            unset(self::$connections[$key]);
        }

        $connection = @imap_open($target, $user, $pass, 0, 1);
        if ($connection !== false) {
            self::$connections[$key] = $connection;
            self::$lastError = '';

            return $connection;
        }

        self::recordImapError();

        return false;
    }

    private static function clearImapErrors(): void
    {
        if (function_exists('imap_errors')) {
            @imap_errors();
        }
        if (function_exists('imap_alerts')) {
            @imap_alerts();
        }
    }

    private static function recordImapError(): void
    {
        $parts = [];
        if (function_exists('imap_last_error')) {
            $last = (string) @imap_last_error();
            if ($last !== '') {
                $parts[] = $last;
            }
        }
        if (function_exists('imap_errors')) {
            $errors = @imap_errors();
            if (is_array($errors)) {
                foreach ($errors as $error) {
                    if (is_string($error) && $error !== '') {
                        $parts[] = $error;
                    }
                }
            }
        }

        self::$lastError = $parts !== [] ? implode(' — ', array_unique($parts)) : 'IMAP-Verbindung fehlgeschlagen.';
    }

    private static function configureTimeouts(): void
    {
        static $configured = false;
        if ($configured || !function_exists('imap_timeout')) {
            return;
        }
        $configured = true;
        imap_timeout(IMAP_OPENTIMEOUT, 8);
        imap_timeout(IMAP_READTIMEOUT, 12);
        imap_timeout(IMAP_WRITETIMEOUT, 12);
        imap_timeout(IMAP_CLOSETIMEOUT, 5);
    }

    private static function registerShutdown(): void
    {
        if (self::$shutdownRegistered) {
            return;
        }
        self::$shutdownRegistered = true;
        register_shutdown_function([self::class, 'releaseConnections']);
    }

    /** @param array<string, mixed> $mailbox */
    private static function mailboxString(array $mailbox, string $folder): string
    {
        return self::serverRef($mailbox) . $folder;
    }

    /** @param array<string, mixed> $mailbox */
    private static function serverRef(array $mailbox): string
    {
        $port = max(1, min(65535, (int) ($mailbox['imap_port'] ?? 993)));
        $encryption = (string) ($mailbox['imap_encryption'] ?? 'ssl');
        $flags = '/imap' . ($encryption === 'tls' ? '/tls' : '/ssl') . '/novalidate-cert';

        return '{' . trim((string) ($mailbox['imap_host'] ?? '')) . ':' . $port . $flags . '}';
    }

    private static function pathFromFullMailbox(string $full, string $ref): string
    {
        if (str_starts_with($full, $ref)) {
            return MailFolderLabels::decodePath(substr($full, strlen($ref)));
        }

        return MailFolderLabels::decodePath($full);
    }

    private static function sortRank(string $path): int
    {
        if (MailFolderLabels::isInbox($path)) {
            return 0;
        }
        if (MailFolderLabels::isSent($path)) {
            return 1;
        }
        $upper = strtoupper(MailFolderLabels::baseName($path));
        if (in_array($upper, ['DRAFTS', 'ENTWUERFE', 'ENTWURF'], true)) {
            return 2;
        }
        if (in_array($upper, ['ARCHIVE', 'ARCHIV'], true)) {
            return 3;
        }
        if (in_array($upper, ['JUNK', 'SPAM'], true)) {
            return 8;
        }
        if (in_array($upper, ['TRASH', 'PAPIERKORB', 'DELETED'], true)) {
            return 9;
        }

        return 5;
    }

    private static function decodeMime(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (function_exists('imap_mime_header_decode')) {
            $parts = @imap_mime_header_decode($value);
            if (is_array($parts)) {
                $out = '';
                foreach ($parts as $part) {
                    $out .= (string) ($part->text ?? '');
                }
                if ($out !== '') {
                    return $out;
                }
            }
        }

        return $value;
    }
}
