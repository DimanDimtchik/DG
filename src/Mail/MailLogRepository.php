<?php
declare(strict_types=1);

final class MailLogRepository
{
    /**
     * @param list<string> $to
     * @param list<string> $cc
     * @param list<string> $bcc
     */
    public static function createQueued(
        string $fromEmail,
        string $fromName,
        array $to,
        array $cc,
        array $bcc,
        string $subject,
        string $bodyPreview,
        ?int $contactId,
        ?int $userId,
        ?int $mailboxId = null,
    ): int {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO dg_mail_log
            (direction, mailbox_id, status, from_address, from_name, to_addresses, cc_addresses, bcc_addresses,
             subject, body_preview, contact_id, user_id)
             VALUES
            (\'out\', :mailbox_id, \'queued\', :from_address, :from_name, :to_addresses, :cc_addresses, :bcc_addresses,
             :subject, :body_preview, :contact_id, :user_id)'
        );
        $stmt->execute([
            'mailbox_id' => $mailboxId,
            'from_address' => $fromEmail,
            'from_name' => $fromName,
            'to_addresses' => json_encode(array_values($to), JSON_UNESCAPED_UNICODE),
            'cc_addresses' => $cc !== [] ? json_encode(array_values($cc), JSON_UNESCAPED_UNICODE) : null,
            'bcc_addresses' => $bcc !== [] ? json_encode(array_values($bcc), JSON_UNESCAPED_UNICODE) : null,
            'subject' => mb_substr($subject, 0, 500),
            'body_preview' => mb_substr($bodyPreview, 0, 500),
            'contact_id' => $contactId,
            'user_id' => $userId,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * @param list<string> $toAddresses
     * @param list<string> $ccAddresses
     */
    public static function createInbound(
        int $mailboxId,
        string $fromEmail,
        string $fromName,
        array $toAddresses,
        array $ccAddresses,
        string $subject,
        string $bodyPreview,
        ?string $messageId,
        ?int $contactId,
    ): int {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO dg_mail_log
            (direction, mailbox_id, imap_folder, status, is_read, from_address, from_name, to_addresses, cc_addresses,
             subject, body_preview, contact_id, message_id)
             VALUES
            (\'in\', :mailbox_id, :imap_folder, \'queued\', 0, :from_address, :from_name, :to_addresses, :cc_addresses,
             :subject, :body_preview, :contact_id, :message_id)'
        );
        $stmt->execute([
            'mailbox_id' => $mailboxId,
            'imap_folder' => 'INBOX',
            'from_address' => $fromEmail,
            'from_name' => $fromName,
            'to_addresses' => json_encode(array_values($toAddresses), JSON_UNESCAPED_UNICODE),
            'cc_addresses' => $ccAddresses !== [] ? json_encode(array_values($ccAddresses), JSON_UNESCAPED_UNICODE) : null,
            'subject' => mb_substr($subject, 0, 500),
            'body_preview' => mb_substr($bodyPreview, 0, 500),
            'contact_id' => $contactId,
            'message_id' => $messageId,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function markReceived(int $id): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'UPDATE dg_mail_log SET status = \'received\', received_at = NOW(), error_message = NULL WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    public static function markRead(int $id, bool $read = true): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE dg_mail_log SET is_read = :is_read WHERE id = :id');
        $stmt->execute(['is_read' => $read ? 1 : 0, 'id' => $id]);
    }

    public static function inboundExists(string $messageId, int $mailboxId): bool
    {
        if ($messageId === '') {
            return false;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT id FROM dg_mail_log WHERE message_id = :message_id AND mailbox_id = :mailbox_id LIMIT 1'
        );
        $stmt->execute(['message_id' => $messageId, 'mailbox_id' => $mailboxId]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $header
     */
    public static function upsertFromImapHeader(int $mailboxId, string $imapFolder, array $header): int
    {
        MigrationRunner::runPending();
        $uid = (int) ($header['imap_uid'] ?? 0);
        if ($uid <= 0) {
            return 0;
        }

        $messageId = sprintf('imap:%d:%s:%d', $mailboxId, $imapFolder, $uid);
        $fromEmail = strtolower(trim((string) ($header['from_address'] ?? '')));
        $fromName = trim((string) ($header['from_name'] ?? ''));
        $subject = trim((string) ($header['subject'] ?? ''));
        $isRead = !empty($header['is_read']);
        $receivedAt = self::parseMailDate((string) ($header['received_at'] ?? $header['created_at'] ?? ''));

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id FROM dg_mail_log WHERE message_id = :message_id AND mailbox_id = :mailbox_id LIMIT 1');
        $stmt->execute(['message_id' => $messageId, 'mailbox_id' => $mailboxId]);
        $existingId = (int) ($stmt->fetchColumn() ?: 0);

        if ($existingId > 0) {
            $contactId = self::guessContactId($fromEmail);
            $upd = $pdo->prepare(
                'UPDATE dg_mail_log SET is_read = :is_read, subject = :subject, from_address = :from_address,
                 from_name = :from_name, received_at = COALESCE(:received_at, received_at),
                 contact_id = COALESCE(contact_id, :contact_id)
                 WHERE id = :id'
            );
            $upd->execute([
                'is_read' => $isRead ? 1 : 0,
                'subject' => mb_substr($subject, 0, 500),
                'from_address' => $fromEmail,
                'from_name' => mb_substr($fromName, 0, 191),
                'received_at' => $receivedAt,
                'contact_id' => $contactId,
                'id' => $existingId,
            ]);

            return $existingId;
        }

        $contactId = self::guessContactId($fromEmail);
        $insert = $pdo->prepare(
            'INSERT INTO dg_mail_log
            (direction, mailbox_id, imap_folder, status, is_read, from_address, from_name, to_addresses,
             subject, body_preview, contact_id, message_id, received_at)
             VALUES
            (\'in\', :mailbox_id, :imap_folder, \'received\', :is_read, :from_address, :from_name, :to_addresses,
             :subject, :body_preview, :contact_id, :message_id, COALESCE(:received_at, NOW()))'
        );
        $insert->execute([
            'mailbox_id' => $mailboxId,
            'imap_folder' => mb_substr($imapFolder, 0, 191),
            'is_read' => $isRead ? 1 : 0,
            'from_address' => $fromEmail,
            'from_name' => mb_substr($fromName, 0, 191),
            'to_addresses' => '[]',
            'subject' => mb_substr($subject, 0, 500),
            'body_preview' => '',
            'contact_id' => $contactId,
            'message_id' => $messageId,
            'received_at' => $receivedAt,
        ]);

        return (int) $pdo->lastInsertId();
    }

    private static function parseMailDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);

        return $ts !== false ? date('Y-m-d H:i:s', $ts) : null;
    }

    public static function imapMessageId(int $mailboxId, string $imapFolder, int $uid): string
    {
        return sprintf('imap:%d:%s:%d', $mailboxId, $imapFolder, $uid);
    }

    public static function guessContactId(string $fromEmail): ?int
    {
        $fromEmail = strtolower(trim($fromEmail));
        if ($fromEmail === '' || !Database::isConfigured()) {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            "SELECT id FROM dg_contacts WHERE LOWER(TRIM(email)) = :email OR LOWER(TRIM(email_2)) = :email2 LIMIT 1"
        );
        $stmt->execute(['email' => $fromEmail, 'email2' => $fromEmail]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int) $id;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT person_contact_id FROM dg_contact_company_links WHERE LOWER(TRIM(work_email)) = :email LIMIT 1'
        );
        $stmt->execute(['email' => $fromEmail]);
        $id = $stmt->fetchColumn();

        return $id ? (int) $id : null;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function enrichPartyLabels(array $rows): array
    {
        return array_map(static function (array $row): array {
            $row['party_display_name'] = MailPartyLabel::forMessageRow($row);

            return $row;
        }, $rows);
    }

    /**
     * @param list<int> $mailboxIds
     * @return list<array<string, mixed>>
     */
    public static function inboxForMailboxes(array $mailboxIds, int $limit = 50, ?int $mailboxFilter = null): array
    {
        return self::folderMessagesForMailboxes($mailboxIds, 'INBOX', $limit, $mailboxFilter);
    }

    /**
     * @param list<int> $mailboxIds
     * @return list<array<string, mixed>>
     */
    public static function sentForMailboxes(array $mailboxIds, int $limit = 50, ?int $mailboxFilter = null): array
    {
        if ($mailboxIds === [] || !Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();
        $placeholders = implode(',', array_fill(0, count($mailboxIds), '?'));
        $sql = "SELECT id, mailbox_id, status, is_read, sent_at, created_at, from_address, from_name,
                       to_addresses, subject, body_preview, contact_id
                FROM dg_mail_log
                WHERE direction = 'out' AND status = 'sent' AND mailbox_id IN ({$placeholders})";
        $params = $mailboxIds;
        if ($mailboxFilter !== null && $mailboxFilter > 0) {
            $sql .= ' AND mailbox_id = ?';
            $params[] = $mailboxFilter;
        }
        $sql .= ' ORDER BY COALESCE(sent_at, created_at) DESC LIMIT ?';
        $params[] = max(1, $limit);

        $stmt = Database::pdo()->prepare($sql);
        foreach ($params as $i => $value) {
            $stmt->bindValue($i + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        return self::enrichPartyLabels(array_map(static function (array $row): array {
            $to = json_decode((string) ($row['to_addresses'] ?? '[]'), true);
            $toFirst = is_array($to) && $to !== [] ? (string) $to[0] : '';
            $row['from_address'] = $toFirst;
            $row['from_name'] = '';
            $row['received_at'] = $row['sent_at'] ?? $row['created_at'] ?? '';
            $row['source'] = 'local';

            return $row;
        }, $rows));
    }

    /**
     * @param list<int> $mailboxIds
     * @return list<array<string, mixed>>
     */
    public static function folderMessagesForMailboxes(
        array $mailboxIds,
        string $folderPath,
        int $limit = 50,
        ?int $mailboxFilter = null,
    ): array {
        if (MailFolderCatalog::usesLocalSent($folderPath)) {
            return self::sentForMailboxes($mailboxIds, $limit, $mailboxFilter);
        }

        if ($mailboxIds === [] || !Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();
        $placeholders = implode(',', array_fill(0, count($mailboxIds), '?'));
        $sql = "SELECT id, mailbox_id, status, is_read, received_at, created_at, from_address, from_name,
                       subject, body_preview, contact_id
                FROM dg_mail_log
                WHERE direction = 'in' AND status = 'received' AND mailbox_id IN ({$placeholders})";
        $params = $mailboxIds;
        if ($mailboxFilter !== null && $mailboxFilter > 0) {
            $sql .= ' AND mailbox_id = ?';
            $params[] = $mailboxFilter;
        }
        if (MailFolderLabels::isInbox($folderPath) || $folderPath === 'INBOX') {
            $sql .= " AND (imap_folder = 'INBOX' OR imap_folder = '' OR imap_folder IS NULL)";
        } elseif ($folderPath !== '' && $folderPath !== '__sent__') {
            $sql .= ' AND imap_folder = ?';
            $params[] = $folderPath;
        }
        $sql .= ' ORDER BY COALESCE(received_at, created_at) DESC LIMIT ?';
        $params[] = max(1, $limit);

        $stmt = Database::pdo()->prepare($sql);
        foreach ($params as $i => $value) {
            $stmt->bindValue($i + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        return self::enrichPartyLabels(array_map(static function (array $row): array {
            $row['source'] = 'local';

            return $row;
        }, $rows));
    }

    public static function countUnreadForMailboxes(array $mailboxIds): int
    {
        if ($mailboxIds === [] || !Database::isConfigured()) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($mailboxIds), '?'));
        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM dg_mail_log
             WHERE direction = 'in' AND status = 'received' AND is_read = 0
               AND mailbox_id IN ({$placeholders})"
        );
        foreach ($mailboxIds as $i => $id) {
            $stmt->bindValue($i + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public static function markArchived(int $id, string $storagePath, int $sizeBytes, string $messageId): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'UPDATE dg_mail_log SET storage_path = :storage_path, size_bytes = :size_bytes, message_id = :message_id WHERE id = :id'
        );
        $stmt->execute([
            'storage_path' => $storagePath,
            'size_bytes' => $sizeBytes,
            'message_id' => $messageId,
            'id' => $id,
        ]);
    }

    public static function markSent(int $id): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'UPDATE dg_mail_log SET status = \'sent\', sent_at = NOW(), error_message = NULL WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    public static function markFailed(int $id, string $error): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'UPDATE dg_mail_log SET status = \'failed\', error_message = :error_message WHERE id = :id'
        );
        $stmt->execute([
            'error_message' => mb_substr($error, 0, 2000),
            'id' => $id,
        ]);
    }

    /** @return array<string, mixed>|null */
    public static function findById(int $id): ?array
    {
        if (!Database::isConfigured()) {
            return null;
        }
        try {
            $pdo = Database::pdo();
            $stmt = $pdo->prepare('SELECT * FROM dg_mail_log WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return list<array<string, mixed>> */
    public static function recent(int $limit = 15): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        try {
            MigrationRunner::runPending();
            $pdo = Database::pdo();
            $stmt = $pdo->prepare(
                'SELECT id, status, sent_at, created_at, from_address, to_addresses, subject, size_bytes, error_message
                 FROM dg_mail_log WHERE direction = \'out\'
                 ORDER BY id DESC LIMIT :limit'
            );
            $stmt->bindValue('limit', max(1, $limit), PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        } catch (Throwable) {
            return [];
        }
    }
}
