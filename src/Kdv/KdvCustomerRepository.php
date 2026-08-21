<?php
declare(strict_types=1);

/**
 * CRUD und Statistik für KDV-SaaS-Kunden (nicht CRM-Kontakte).
 */
final class KdvCustomerRepository
{
    public const STATUSES = [
        'neu'          => 'Neu',
        'dns_pending'  => 'DNS ausstehend',
        'installiert'  => 'Installiert',
        'aktiv'        => 'Aktiv',
        'gesperrt'     => 'Gesperrt',
        'gekuendigt'   => 'Gekündigt',
    ];

    public const TARIFFS = [
        'basic'      => 'Basic',
        'business'   => 'Business',
        'enterprise' => 'Enterprise',
    ];

    /** @return list<array<string, mixed>> */
    public static function list(): array
    {
        if (!Database::isConfigured()) return [];
        MigrationRunner::runPending();

        $stmt = Database::pdo()->query(
            'SELECT * FROM dg_kdv_customers ORDER BY company_name ASC'
        );
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /** @return array<string, mixed>|null */
    public static function findById(int $id): ?array
    {
        if ($id < 1 || !Database::isConfigured()) return null;
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare('SELECT * FROM dg_kdv_customers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public static function findByContactEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '' || !Database::isConfigured()) return null;
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_kdv_customers WHERE LOWER(contact_email) = :email ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public static function findByShopSession(string $token): ?array
    {
        $token = trim($token);
        if ($token === '' || !Database::isConfigured()) return null;
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_kdv_customers
             WHERE shop_session_token = :t
               AND shop_session_expires IS NOT NULL
               AND shop_session_expires > NOW()
             LIMIT 1'
        );
        $stmt->execute(['t' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array{total: int, active: int, revenue: float} */
    public static function stats(): array
    {
        if (!Database::isConfigured()) return ['total' => 0, 'active' => 0, 'revenue' => 0.0];
        MigrationRunner::runPending();

        $pdo = Database::pdo();
        $total = (int) $pdo->query('SELECT COUNT(*) FROM dg_kdv_customers')->fetchColumn();
        $active = (int) $pdo->query("SELECT COUNT(*) FROM dg_kdv_customers WHERE status = 'aktiv'")->fetchColumn();
        $revenue = (float) $pdo->query("SELECT COALESCE(SUM(monthly_price), 0) FROM dg_kdv_customers WHERE status = 'aktiv'")->fetchColumn();

        return ['total' => $total, 'active' => $active, 'revenue' => $revenue];
    }

    /**
     * @param array<string, mixed> $data
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public static function save(array $data, ?int $id = null): int
    {
        if (!Database::isConfigured()) throw new RuntimeException('Datenbank nicht verbunden.');
        MigrationRunner::runPending();

        $fields = [
            'company_name'  => trim((string) ($data['company_name'] ?? '')),
            'domain'        => trim((string) ($data['domain'] ?? '')),
            'db_name'       => trim((string) ($data['db_name'] ?? '')) ?: null,
            'contact_name'  => trim((string) ($data['contact_name'] ?? '')) ?: null,
            'contact_email' => trim((string) ($data['contact_email'] ?? '')) ?: null,
            'contact_phone' => trim((string) ($data['contact_phone'] ?? '')) ?: null,
            'kas_login'     => trim((string) ($data['kas_login'] ?? '')) ?: null,
            'crm_version'   => trim((string) ($data['crm_version'] ?? '')) ?: null,
            'status'        => isset(self::STATUSES[$data['status'] ?? '']) ? $data['status'] : 'neu',
            'contract_start'=> !empty($data['contract_start']) ? $data['contract_start'] : null,
            'contract_end'  => !empty($data['contract_end']) ? $data['contract_end'] : null,
            'tariff'        => isset(self::TARIFFS[$data['tariff'] ?? '']) ? $data['tariff'] : 'basic',
            'monthly_price' => max(0, (float) ($data['monthly_price'] ?? 0)),
            'billing_cycle' => in_array($data['billing_cycle'] ?? '', ['monatlich', 'jaehrlich'], true) ? $data['billing_cycle'] : 'monatlich',
            'notes'         => trim((string) ($data['notes'] ?? '')) ?: null,
        ];

        if ($fields['company_name'] === '') throw new InvalidArgumentException('Firmenname ist erforderlich.');
        if ($fields['domain'] === '') throw new InvalidArgumentException('Domain ist erforderlich.');

        $pdo = Database::pdo();

        if ($id !== null && $id > 0) {
            $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($fields)));
            $stmt = $pdo->prepare("UPDATE dg_kdv_customers SET $sets WHERE id = :id");
            $fields['id'] = $id;
            $stmt->execute($fields);
            self::maybeSetShopPassword($id, $data);
            return $id;
        }

        $cols = implode(', ', array_keys($fields));
        $placeholders = implode(', ', array_map(fn($k) => ":$k", array_keys($fields)));
        $stmt = $pdo->prepare("INSERT INTO dg_kdv_customers ($cols) VALUES ($placeholders)");
        $stmt->execute($fields);
        $newId = (int) $pdo->lastInsertId();
        self::maybeSetShopPassword($newId, $data);
        return $newId;
    }

    /** @param array<string, mixed> $data */
    private static function maybeSetShopPassword(int $id, array $data): void
    {
        $pw = (string) ($data['shop_password'] ?? '');
        if ($pw === '') {
            return;
        }
        PasswordPolicy::assertValid($pw, (string) ($data['shop_password_confirm'] ?? $pw));
        self::setShopPassword($id, $pw);
    }

    public static function setShopPassword(int $id, string $plainPassword): void
    {
        if ($id < 1 || !Database::isConfigured()) return;
        MigrationRunner::runPending();
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        Database::pdo()->prepare(
            'UPDATE dg_kdv_customers SET shop_password_hash = :h WHERE id = :id'
        )->execute(['h' => $hash, 'id' => $id]);
    }

    public static function setLicense(int $id, string $licenseKey, ?int $remoteId = null): void
    {
        if ($id < 1 || !Database::isConfigured()) return;
        MigrationRunner::runPending();
        Database::pdo()->prepare(
            'UPDATE dg_kdv_customers SET license_key = :k, license_remote_id = :r WHERE id = :id'
        )->execute([
            'k' => $licenseKey,
            'r' => $remoteId !== null && $remoteId > 0 ? $remoteId : null,
            'id' => $id,
        ]);
    }

    public static function setBlocked(int $id, string $reason, ?string $note = null): void
    {
        if ($id < 1 || !Database::isConfigured()) return;
        MigrationRunner::runPending();
        Database::pdo()->prepare(
            'UPDATE dg_kdv_customers
             SET status = \'gesperrt\', block_reason = :r, block_note = :n
             WHERE id = :id'
        )->execute([
            'r' => $reason,
            'n' => $note !== null && trim($note) !== '' ? trim($note) : null,
            'id' => $id,
        ]);
    }

    public static function clearBlocked(int $id): void
    {
        if ($id < 1 || !Database::isConfigured()) return;
        MigrationRunner::runPending();
        Database::pdo()->prepare(
            'UPDATE dg_kdv_customers
             SET status = \'aktiv\', block_reason = NULL, block_note = NULL
             WHERE id = :id'
        )->execute(['id' => $id]);
    }

    /** @return array{token: string, expires: string}|null */
    public static function createShopSession(int $id, int $ttlDays = 14): ?array
    {
        if ($id < 1 || !Database::isConfigured()) return null;
        MigrationRunner::runPending();
        $token = bin2hex(random_bytes(32));
        $expires = (new DateTimeImmutable('now'))->modify('+' . max(1, $ttlDays) . ' days')->format('Y-m-d H:i:s');
        Database::pdo()->prepare(
            'UPDATE dg_kdv_customers
             SET shop_session_token = :t, shop_session_expires = :e
             WHERE id = :id'
        )->execute(['t' => $token, 'e' => $expires, 'id' => $id]);
        return ['token' => $token, 'expires' => $expires];
    }

    public static function clearShopSession(int $id): void
    {
        if ($id < 1 || !Database::isConfigured()) return;
        Database::pdo()->prepare(
            'UPDATE dg_kdv_customers SET shop_session_token = NULL, shop_session_expires = NULL WHERE id = :id'
        )->execute(['id' => $id]);
    }

    /**
     * Öffentliche Sicht für Shop-Konto (ohne Secrets).
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function publicAccountView(array $row): array
    {
        $status = (string) ($row['status'] ?? '');
        $blocked = $status === 'gesperrt';
        $reason = $blocked ? (string) ($row['block_reason'] ?? '') : '';
        return [
            'id' => (int) ($row['id'] ?? 0),
            'company_name' => (string) ($row['company_name'] ?? ''),
            'domain' => (string) ($row['domain'] ?? ''),
            'contact_name' => (string) ($row['contact_name'] ?? ''),
            'contact_email' => (string) ($row['contact_email'] ?? ''),
            'tariff' => (string) ($row['tariff'] ?? ''),
            'tariff_label' => self::TARIFFS[$row['tariff'] ?? ''] ?? (string) ($row['tariff'] ?? ''),
            'billing_cycle' => (string) ($row['billing_cycle'] ?? ''),
            'status' => $status,
            'status_label' => self::STATUSES[$status] ?? $status,
            'blocked' => $blocked,
            'block_reason' => $reason !== '' ? $reason : null,
            'block_reason_label' => $reason !== '' ? KdvBlockReasons::label($reason) : null,
            'block_message' => $blocked ? KdvBlockReasons::customerMessage($reason, (string) ($row['block_note'] ?? '')) : null,
            'unlock_auto_rejected' => $blocked && KdvBlockReasons::autoReject($reason),
            'has_license' => trim((string) ($row['license_key'] ?? '')) !== '',
            'license_masked' => self::maskLicense((string) ($row['license_key'] ?? '')),
            'contract_start' => $row['contract_start'] ?? null,
            'contract_end' => $row['contract_end'] ?? null,
        ];
    }

    public static function maskLicense(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return '';
        }
        if (strlen($key) < 10) {
            return '••••';
        }
        return substr($key, 0, 7) . '••••••••' . substr($key, -4);
    }

    public static function setMailboxCredentials(int $id, string $email, string $password): void
    {
        if ($id < 1 || !Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
        Database::pdo()->prepare(
            'UPDATE dg_kdv_customers
             SET mailbox_email = :e, mailbox_password = :p, mailbox_created_at = NOW()
             WHERE id = :id'
        )->execute([
            'e' => trim($email),
            'p' => $password,
            'id' => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        if ($id < 1 || !Database::isConfigured()) return;
        MigrationRunner::runPending();
        Database::pdo()->prepare('DELETE FROM dg_kdv_customers WHERE id = :id')->execute(['id' => $id]);
    }

    public static function updateHeartbeat(int $id): void
    {
        if ($id < 1 || !Database::isConfigured()) return;
        Database::pdo()->prepare('UPDATE dg_kdv_customers SET last_heartbeat = NOW() WHERE id = :id')->execute(['id' => $id]);
    }

    public static function updateVersion(int $id, string $version): void
    {
        if ($id < 1 || !Database::isConfigured()) return;
        Database::pdo()->prepare('UPDATE dg_kdv_customers SET crm_version = :v WHERE id = :id')->execute(['v' => $version, 'id' => $id]);
    }
}
