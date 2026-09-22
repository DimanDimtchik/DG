<?php
declare(strict_types=1);

/** Persistenz Kunden-App-Accounts (ohne CRM-User). */
final class MobileCustomerAccountRepository
{
    public static function tableReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            $r = Database::pdo()->query("SHOW TABLES LIKE 'dg_mobile_customer_accounts'");

            return $r !== false && $r->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '' || !self::tableReady()) {
            return null;
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_mobile_customer_accounts WHERE email = :e LIMIT 1'
        );
        $stmt->execute(['e' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findById(int $id): ?array
    {
        if ($id < 1 || !self::tableReady()) {
            return null;
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_mobile_customer_accounts WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByContactId(int $contactId): ?array
    {
        if ($contactId < 1 || !self::tableReady()) {
            return null;
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_mobile_customer_accounts WHERE contact_id = :c LIMIT 1'
        );
        $stmt->execute(['c' => $contactId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public static function create(int $contactId, string $email, string $passwordHash): int
    {
        MigrationRunner::runPending();
        if (!self::tableReady()) {
            throw new RuntimeException('Mobile-Account-Tabelle fehlt — Migration 102.');
        }
        $email = strtolower(trim($email));
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_mobile_customer_accounts (contact_id, email, password_hash, verified_at)
             VALUES (:c, :e, :h, NOW())'
        );
        $stmt->execute([
            'c' => $contactId,
            'e' => $email,
            'h' => $passwordHash,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }
}
