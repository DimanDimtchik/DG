<?php
declare(strict_types=1);

/**
 * CRUD und Zugriffsrechte für CRM-Postfächer.
 */
final class MailboxRepository
{
    /**
     * Prüft, ob die E-Mail-Adresse bereits als Postfach existiert.
     * @param string $email
     * @return bool
     */
    public static function emailExists(string $email): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare('SELECT id FROM dg_mailboxes WHERE email_address = :email LIMIT 1');
        $stmt->execute(['email' => strtolower(trim($email))]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Findet einen Datensatz anhand der ID.
     * @param int $id
     * @return array|null
     */
    public static function findById(int $id): ?array
    {
        if (!Database::isConfigured() || $id <= 0) {
            return null;
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_mailboxes WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Findet ein Postfach anhand des Inbound-Webhook-Tokens.
     * @param string $token
     * @return array|null
     */
    public static function findByWebhookToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '' || !Database::isConfigured()) {
            return null;
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_mailboxes WHERE inbound_webhook_token = :token AND is_active = 1 LIMIT 1'
        );
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Findet das private Postfach eines Benutzers.
     * @param int $userId
     * @return array|null
     */
    public static function findPrivateForUser(int $userId): ?array
    {
        if ($userId <= 0 || !Database::isConfigured()) {
            return null;
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM dg_mailboxes WHERE type = 'private' AND owner_user_id = :user_id AND is_active = 1 LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public static function findPrivateForContact(int $contactId): ?array
    {
        if ($contactId <= 0 || !Database::isConfigured()) {
            return null;
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM dg_mailboxes WHERE type = 'private' AND contact_id = :cid AND is_active = 1 LIMIT 1"
        );
        $stmt->execute(['cid' => $contactId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public static function updateStoredPasswords(int $mailboxId, string $imapPassword, string $smtpPassword): void
    {
        if ($mailboxId < 1) {
            throw new InvalidArgumentException('Postfach-ID fehlt.');
        }
        MigrationRunner::runPending();
        $mailbox = self::findById($mailboxId);
        if ($mailbox === null) {
            throw new InvalidArgumentException('Postfach nicht gefunden.');
        }
        $mailbox['imap_password'] = $imapPassword;
        $mailbox['smtp_password'] = $smtpPassword;
        MailboxCredentialVerifier::assertCanConnect($mailbox);
        $stmt = Database::pdo()->prepare(
            'UPDATE dg_mailboxes SET imap_password = :imap, smtp_password = :smtp WHERE id = :id'
        );
        $stmt->execute([
            'imap' => $imapPassword,
            'smtp' => $smtpPassword,
            'id' => $mailboxId,
        ]);
    }

    /**
     * Entfernt das Postfach aus dem CRM (Mitglieder per CASCADE).
     * Mail-Log behält Einträge, mailbox_id wird auf NULL gesetzt.
     * Provider-Postfach (KAS) wird nicht gelöscht.
     */
    public static function deleteById(int $mailboxId): void
    {
        if ($mailboxId < 1) {
            throw new InvalidArgumentException('Postfach-ID fehlt.');
        }
        MigrationRunner::runPending();
        $pdo = Database::pdo();
        $mailbox = self::findById($mailboxId);
        if ($mailbox === null) {
            throw new InvalidArgumentException('Postfach nicht gefunden.');
        }

        $pdo->beginTransaction();
        try {
            if (self::mailLogHasMailboxColumn($pdo)) {
                $pdo->prepare('UPDATE dg_mail_log SET mailbox_id = NULL WHERE mailbox_id = :id')
                    ->execute(['id' => $mailboxId]);
            }
            try {
                $pdo->prepare('DELETE FROM dg_mailbox_password_tokens WHERE mailbox_id = :id')
                    ->execute(['id' => $mailboxId]);
            } catch (Throwable) {
                // Tabelle optional / ältere Installationen
            }
            $pdo->prepare('DELETE FROM dg_mailboxes WHERE id = :id')->execute(['id' => $mailboxId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function mailLogHasMailboxColumn(PDO $pdo): bool
    {
        static $has = null;
        if ($has !== null) {
            return $has;
        }
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM dg_mail_log LIKE 'mailbox_id'");
            $has = $stmt !== false && $stmt->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (Throwable) {
            $has = false;
        }

        return $has;
    }

    /**
     * Listet alle Postfächer für Administratoren.
     * @return array<string, mixed>
     */
    public static function allForAdmin(): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->query(
            "SELECT * FROM dg_mailboxes WHERE is_active = 1 ORDER BY type ASC, name ASC, email_address ASC"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Listet für den Benutzer zugängliche Postfächer.
     * @param User $user
     * @return array<string, mixed>
     */
    public static function accessibleForUser(User $user): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();

        if (RoleResolver::isAdmin($user)) {
            return self::allForAdmin();
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            "SELECT m.* FROM dg_mailboxes m
             LEFT JOIN dg_mailbox_members mm ON mm.mailbox_id = m.id AND mm.user_id = :user_id AND mm.can_read = 1
             WHERE m.is_active = 1
               AND (m.type = 'private' AND m.owner_user_id = :user_id2
                    OR m.type = 'shared' AND mm.user_id IS NOT NULL)
             ORDER BY m.type ASC, m.name ASC"
        );
        $stmt->execute(['user_id' => $user->id, 'user_id2' => $user->id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Liefert Benutzer-IDs der Postfach-Mitglieder.
     *
     * @return list<int>
     */
    public static function memberUserIds(int $mailboxId): array
    {
        return array_map(
            static fn(array $row): int => (int) $row['user_id'],
            self::membersWithPermissions($mailboxId)
        );
    }

    /**
     * Mitglieder inkl. Lesen/Senden-Rechte (Shared-Postfach / Vertretung).
     *
     * @return list<array{user_id: int, can_read: bool, can_send: bool}>
     */
    public static function membersWithPermissions(int $mailboxId): array
    {
        if ($mailboxId <= 0 || !Database::isConfigured()) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT user_id, can_read, can_send FROM dg_mailbox_members WHERE mailbox_id = :id ORDER BY user_id'
        );
        $stmt->execute(['id' => $mailboxId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[] = [
                'user_id' => (int) ($row['user_id'] ?? 0),
                'can_read' => !empty($row['can_read']),
                'can_send' => !empty($row['can_send']),
            ];
        }

        return $out;
    }

    /**
     * Mitglieder aus POST (Lesen/Senden-Checkboxen; Legacy: mailbox_members[]).
     *
     * @param array<string, mixed> $post
     * @return list<array{user_id: int, can_read: bool, can_send: bool}>
     */
    public static function membersFromPost(array $post): array
    {
        $reads = [];
        foreach ((array) ($post['mailbox_member_read'] ?? []) as $raw) {
            $id = (int) $raw;
            if ($id > 0) {
                $reads[$id] = true;
            }
        }
        $sends = [];
        foreach ((array) ($post['mailbox_member_send'] ?? []) as $raw) {
            $id = (int) $raw;
            if ($id > 0) {
                $sends[$id] = true;
            }
        }
        $legacy = [];
        foreach ((array) ($post['mailbox_members'] ?? []) as $raw) {
            $id = (int) $raw;
            if ($id > 0) {
                $legacy[$id] = true;
            }
        }

        $ids = array_unique(array_merge(array_keys($reads), array_keys($sends), array_keys($legacy)));
        $out = [];
        $hasExplicitRights = $reads !== [] || $sends !== [] || isset($post['mailbox_member_read']) || isset($post['mailbox_member_send']);
        foreach ($ids as $userId) {
            $canSend = isset($sends[$userId]) || (!$hasExplicitRights && isset($legacy[$userId]));
            $canRead = isset($reads[$userId]) || $canSend || (!$hasExplicitRights && isset($legacy[$userId]));
            if (!$canRead && !$canSend) {
                continue;
            }
            if ($canSend) {
                $canRead = true;
            }
            $out[] = [
                'user_id' => $userId,
                'can_read' => $canRead,
                'can_send' => $canSend,
            ];
        }

        return $out;
    }

    /**
     * Prüft, ob der Benutzer auf das Postfach zugreifen darf.
     * @param User $user
     * @param int $mailboxId
     * @param string $permission
     * @return bool
     */
    public static function userCanAccess(User $user, int $mailboxId, string $permission = 'read'): bool
    {
        if ($mailboxId <= 0) {
            return false;
        }
        if (RoleResolver::isAdmin($user)) {
            return true;
        }

        $box = self::findById($mailboxId);
        if ($box === null || empty($box['is_active'])) {
            return false;
        }

        if (($box['type'] ?? '') === 'private') {
            return (int) ($box['owner_user_id'] ?? 0) === $user->id;
        }

        $col = $permission === 'send' ? 'can_send' : 'can_read';
        $stmt = Database::pdo()->prepare(
            "SELECT 1 FROM dg_mailbox_members WHERE mailbox_id = :mailbox_id AND user_id = :user_id AND {$col} = 1 LIMIT 1"
        );
        $stmt->execute(['mailbox_id' => $mailboxId, 'user_id' => $user->id]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Listet Postfächer, über die der Benutzer senden darf.
     * @param User $user Benutzer
     * @return list<array<string, mixed>>
     */
    public static function sendableForUser(User $user): array
    {
        $boxes = self::accessibleForUser($user);
        $globalSmtp = MailSettings::isConfigured();
        $out = [];
        foreach ($boxes as $box) {
            $mailboxId = (int) ($box['id'] ?? 0);
            if ($mailboxId <= 0) {
                continue;
            }
            if (!self::userCanAccess($user, $mailboxId, 'send')) {
                continue;
            }
            if (!self::smtpIsConfigured($box) && !$globalSmtp) {
                continue;
            }
            $out[] = $box;
        }

        return $out;
    }

    /**
     * Prüft, ob SMTP für das Postfach konfiguriert ist.
     * @param array $mailbox
     * @return bool
     */
    public static function smtpIsConfigured(array $mailbox): bool
    {
        return trim((string) ($mailbox['smtp_host'] ?? '')) !== ''
            && trim((string) ($mailbox['smtp_username'] ?? '')) !== ''
            && trim((string) ($mailbox['smtp_password'] ?? '')) !== '';
    }

    /**
     * Liefert die SMTP-Zugangsdaten des Postfachs.
     * @param array $mailbox
     * @return array{host: string, port: int, encryption: string, username: string, password: string}|null
     */
    public static function smtpConfig(array $mailbox): ?array
    {
        if (!self::smtpIsConfigured($mailbox)) {
            return null;
        }

        return [
            'host' => (string) $mailbox['smtp_host'],
            'port' => (int) ($mailbox['smtp_port'] ?? 587),
            'encryption' => (string) ($mailbox['smtp_encryption'] ?? 'tls'),
            'username' => (string) $mailbox['smtp_username'],
            'password' => (string) $mailbox['smtp_password'],
        ];
    }

    /**
     * Liefert den Anzeige-Absendernamen.
     * @param array $mailbox
     * @return string
     */
    public static function displayFromName(array $mailbox): string
    {
        $name = trim((string) ($mailbox['from_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        return trim((string) ($mailbox['name'] ?? ''));
    }

    /**
     * Methode save.
     * @param array $data
     * @param int|null $id Datensatz-ID
     * @param list<int|array{user_id: int, can_read?: bool, can_send?: bool}> $memberUserIds
     * @return int
     * @throws InvalidArgumentException
     */
    public static function save(array $data, ?int $id = null, array $memberUserIds = []): int
    {
        MigrationRunner::runPending();
        $pdo = Database::pdo();

        $existing = ($id !== null && $id > 0) ? self::findById($id) : null;
        $data = MailboxProviderPresets::mergeFormInput($data, $existing);
        MailboxCredentialVerifier::assertCanConnect($data);

        $type = ($data['type'] ?? '') === 'private' ? 'private' : 'shared';
        $name = trim((string) ($data['name'] ?? ''));
        $email = strtolower(trim((string) ($data['email_address'] ?? '')));
        $ownerUserId = max(0, (int) ($data['owner_user_id'] ?? 0));
        $contactId = max(0, (int) ($data['contact_id'] ?? 0));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Gültige E-Mail-Adresse erforderlich.');
        }

        [$local, $domain] = self::splitEmail($email);
        $token = trim((string) ($data['inbound_webhook_token'] ?? ''));
        if ($token === '') {
            $token = bin2hex(random_bytes(16));
        }

        if ($id === null || $id <= 0) {
            if (self::emailExists($email)) {
                throw new InvalidArgumentException('Diese E-Mail-Adresse ist bereits als Postfach hinterlegt.');
            }
            $stmt = $pdo->prepare(
                "INSERT INTO dg_mailboxes
                (type, name, email_address, local_part, domain_part, owner_user_id, contact_id,
                 kas_mail_login, kas_provisioned, provider_preset,
                 imap_host, imap_port, imap_encryption, imap_username, imap_password,
                 smtp_host, smtp_port, smtp_encryption, smtp_username, smtp_password, from_name,
                 inbound_webhook_token, is_active)
                 VALUES
                (:type, :name, :email_address, :local_part, :domain_part, :owner_user_id, :contact_id,
                 :kas_mail_login, :kas_provisioned, :provider_preset,
                 :imap_host, :imap_port, :imap_encryption, :imap_username, :imap_password,
                 :smtp_host, :smtp_port, :smtp_encryption, :smtp_username, :smtp_password, :from_name,
                 :inbound_webhook_token, :is_active)"
            );
        } else {
            $stmt = $pdo->prepare(
                "UPDATE dg_mailboxes SET
                    type = :type, name = :name, email_address = :email_address,
                    local_part = :local_part, domain_part = :domain_part,
                    owner_user_id = :owner_user_id, contact_id = :contact_id,
                    kas_mail_login = :kas_mail_login, kas_provisioned = :kas_provisioned,
                    provider_preset = :provider_preset,
                    imap_host = :imap_host, imap_port = :imap_port, imap_encryption = :imap_encryption,
                    imap_username = :imap_username, imap_password = :imap_password,
                    smtp_host = :smtp_host, smtp_port = :smtp_port, smtp_encryption = :smtp_encryption,
                    smtp_username = :smtp_username, smtp_password = :smtp_password, from_name = :from_name,
                    inbound_webhook_token = :inbound_webhook_token, is_active = :is_active
                 WHERE id = :id"
            );
        }

        $params = [
            'type' => $type,
            'name' => $name !== '' ? $name : $email,
            'email_address' => $email,
            'local_part' => $local,
            'domain_part' => $domain,
            'owner_user_id' => $type === 'private' && $ownerUserId > 0 ? $ownerUserId : null,
            'contact_id' => $contactId > 0 ? $contactId : null,
            'kas_mail_login' => trim((string) ($data['kas_mail_login'] ?? '')) ?: null,
            'kas_provisioned' => !empty($data['kas_provisioned']) ? 1 : 0,
            'provider_preset' => (string) ($data['provider_preset'] ?? 'manual'),
            'imap_host' => trim((string) ($data['imap_host'] ?? '')),
            'imap_port' => max(1, min(65535, (int) ($data['imap_port'] ?? 993))),
            'imap_encryption' => in_array(($data['imap_encryption'] ?? 'ssl'), ['ssl', 'tls', ''], true)
                ? (string) ($data['imap_encryption'] ?? 'ssl') : 'ssl',
            'imap_username' => trim((string) ($data['imap_username'] ?? '')),
            'imap_password' => (string) ($data['imap_password'] ?? ''),
            'smtp_host' => trim((string) ($data['smtp_host'] ?? '')),
            'smtp_port' => max(1, min(65535, (int) ($data['smtp_port'] ?? 587))),
            'smtp_encryption' => in_array(($data['smtp_encryption'] ?? 'tls'), ['ssl', 'tls', ''], true)
                ? (string) ($data['smtp_encryption'] ?? 'tls') : 'tls',
            'smtp_username' => trim((string) ($data['smtp_username'] ?? '')),
            'smtp_password' => (string) ($data['smtp_password'] ?? ''),
            'from_name' => trim((string) ($data['from_name'] ?? '')),
            'inbound_webhook_token' => $token,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ];
        if ($id !== null && $id > 0) {
            $params['id'] = $id;
        }

        $stmt->execute($params);

        $mailboxId = ($id !== null && $id > 0) ? $id : (int) $pdo->lastInsertId();

        if ($type === 'shared') {
            self::syncMembers($mailboxId, $memberUserIds);
        } else {
            $pdo->prepare('DELETE FROM dg_mailbox_members WHERE mailbox_id = :id')->execute(['id' => $mailboxId]);
        }

        return $mailboxId;
    }

    /**
     * Synchronisiert die Mitglieder eines Shared-Postfachs.
     *
     * @param list<int|array{user_id: int, can_read?: bool, can_send?: bool}> $members
     */
    public static function syncMembers(int $mailboxId, array $members): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM dg_mailbox_members WHERE mailbox_id = :id')->execute(['id' => $mailboxId]);

        /** @var array<int, array{can_read: bool, can_send: bool}> $valid */
        $valid = [];
        foreach ($members as $entry) {
            if (is_int($entry) || is_string($entry)) {
                $userId = (int) $entry;
                $canRead = true;
                $canSend = true;
            } elseif (is_array($entry)) {
                $userId = (int) ($entry['user_id'] ?? 0);
                $canSend = !empty($entry['can_send']);
                $canRead = !empty($entry['can_read']) || $canSend;
            } else {
                continue;
            }
            if ($userId <= 0 || !MailboxMemberResolver::isActiveStaffUser($userId)) {
                continue;
            }
            if (!$canRead && !$canSend) {
                continue;
            }
            $valid[$userId] = [
                'can_read' => $canRead || $canSend,
                'can_send' => $canSend,
            ];
        }

        if ($valid === []) {
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO dg_mailbox_members (mailbox_id, user_id, can_read, can_send)
             VALUES (:mailbox_id, :user_id, :can_read, :can_send)'
        );
        foreach ($valid as $userId => $flags) {
            $stmt->execute([
                'mailbox_id' => $mailboxId,
                'user_id' => $userId,
                'can_read' => $flags['can_read'] ? 1 : 0,
                'can_send' => $flags['can_send'] ? 1 : 0,
            ]);
        }
    }

    /**
     * Erzeugt die öffentliche Inbound-Webhook-URL.
     * @param array $mailbox
     * @return string
     */
    public static function inboundWebhookUrl(array $mailbox): string
    {
        $token = (string) ($mailbox['inbound_webhook_token'] ?? '');

        return App::publicBaseUrl() . '/api/mail-inbound?token=' . rawurlencode($token);
    }

    /**
     * Zerlegt eine E-Mail-Adresse in Local- und Domain-Teil.
     * @param string $email E-Mail-Adresse
     * @return array{0: string, 1: string}
     * @throws InvalidArgumentException
     */
    private static function splitEmail(string $email): array
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException('Ungültige E-Mail-Adresse.');
        }

        return [strtolower(trim($parts[0])), strtolower(trim($parts[1]))];
    }
}
