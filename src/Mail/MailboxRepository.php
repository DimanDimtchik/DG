<?php
declare(strict_types=1);

final class MailboxRepository
{
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

    /** @return array<string, mixed>|null */
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

    /** @return array<string, mixed>|null */
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

    /** @return array<string, mixed>|null */
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

    /** @return list<array<string, mixed>> */
    public static function allForAdmin(): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->query(
            "SELECT * FROM dg_mailboxes ORDER BY type ASC, name ASC, email_address ASC"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string, mixed>> */
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

    /** @return list<int> */
    public static function memberUserIds(int $mailboxId): array
    {
        if ($mailboxId <= 0 || !Database::isConfigured()) {
            return [];
        }
        $stmt = Database::pdo()->prepare('SELECT user_id FROM dg_mailbox_members WHERE mailbox_id = :id');
        $stmt->execute(['id' => $mailboxId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

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

    /** @return list<array<string, mixed>> */
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

    public static function smtpIsConfigured(array $mailbox): bool
    {
        return trim((string) ($mailbox['smtp_host'] ?? '')) !== ''
            && trim((string) ($mailbox['smtp_username'] ?? '')) !== ''
            && trim((string) ($mailbox['smtp_password'] ?? '')) !== '';
    }

    /** @return array{host: string, port: int, encryption: string, username: string, password: string}|null */
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

    public static function displayFromName(array $mailbox): string
    {
        $name = trim((string) ($mailbox['from_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        return trim((string) ($mailbox['name'] ?? ''));
    }

    /**
     * @param array<string, mixed> $data
     * @param list<int> $memberUserIds
     */
    public static function save(array $data, ?int $id = null, array $memberUserIds = []): int
    {
        MigrationRunner::runPending();
        $pdo = Database::pdo();

        $existing = ($id !== null && $id > 0) ? self::findById($id) : null;
        $data = MailboxProviderPresets::mergeFormInput($data, $existing);

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

    /** @param list<int> $userIds */
    public static function syncMembers(int $mailboxId, array $userIds): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM dg_mailbox_members WHERE mailbox_id = :id')->execute(['id' => $mailboxId]);

        $validIds = [];
        foreach ($userIds as $userId) {
            $userId = (int) $userId;
            if ($userId > 0 && MailboxMemberResolver::isActiveStaffUser($userId)) {
                $validIds[$userId] = true;
            }
        }

        if ($validIds === []) {
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO dg_mailbox_members (mailbox_id, user_id, can_read, can_send) VALUES (:mailbox_id, :user_id, 1, 1)'
        );
        foreach (array_keys($validIds) as $userId) {
            $stmt->execute(['mailbox_id' => $mailboxId, 'user_id' => $userId]);
        }
    }

    public static function inboundWebhookUrl(array $mailbox): string
    {
        $token = (string) ($mailbox['inbound_webhook_token'] ?? '');

        return App::publicBaseUrl() . '/api/mail-inbound?token=' . rawurlencode($token);
    }

    /** @return array{0: string, 1: string} */
    private static function splitEmail(string $email): array
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException('Ungültige E-Mail-Adresse.');
        }

        return [strtolower(trim($parts[0])), strtolower(trim($parts[1]))];
    }
}
