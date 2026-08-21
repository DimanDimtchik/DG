<?php
declare(strict_types=1);

/**
 * Hub-Speicher aktiver Support-Sessions (KDV / Master).
 */
final class KdvSupportSessionRepository
{
    public static function ensureTables(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function upsertStart(array $data): void
    {
        self::ensureTables();
        $domain = strtolower(trim((string) ($data['domain'] ?? '')));
        $token = trim((string) ($data['token'] ?? ''));
        $expiresAt = trim((string) ($data['expires_at'] ?? ''));
        if ($domain === '' || $token === '' || $expiresAt === '') {
            throw new InvalidArgumentException('Domain, Token und Ablaufzeit erforderlich.');
        }

        $customerId = null;
        $company = trim((string) ($data['company_name'] ?? ''));
        $licenseKey = trim((string) ($data['license_key'] ?? ''));
        if ($licenseKey !== '' || $domain !== '') {
            $customer = self::findCustomer($domain, $licenseKey);
            if ($customer !== null) {
                $customerId = (int) $customer['id'];
                if ($company === '') {
                    $company = (string) ($customer['company_name'] ?? '');
                }
            }
        }

        $pdo = Database::pdo();
        $pdo->prepare(
            "UPDATE dg_kdv_support_sessions SET status = 'ended', updated_at = NOW()
             WHERE domain = :d AND status = 'active'"
        )->execute(['d' => $domain]);

        $stmt = $pdo->prepare(
            'INSERT INTO dg_kdv_support_sessions
             (customer_id, domain, company_name, token, expires_at, status)
             VALUES (:cid, :domain, :company, :token, :exp, \'active\')'
        );
        $stmt->execute([
            'cid' => $customerId,
            'domain' => $domain,
            'company' => $company !== '' ? mb_substr($company, 0, 191) : null,
            'token' => mb_substr($token, 0, 128),
            'exp' => $expiresAt,
        ]);
    }

    public static function markStopped(string $domain): void
    {
        self::ensureTables();
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            return;
        }
        Database::pdo()->prepare(
            "UPDATE dg_kdv_support_sessions SET status = 'ended', updated_at = NOW()
             WHERE domain = :d AND status = 'active'"
        )->execute(['d' => $domain]);
    }

    /** @return list<array<string, mixed>> */
    public static function listActive(): array
    {
        self::ensureTables();
        self::expireStale();
        $stmt = Database::pdo()->query(
            "SELECT * FROM dg_kdv_support_sessions
             WHERE status = 'active' AND expires_at > NOW()
             ORDER BY created_at DESC"
        );

        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public static function expireStale(): void
    {
        try {
            Database::pdo()->exec(
                "UPDATE dg_kdv_support_sessions
                 SET status = 'expired', updated_at = NOW()
                 WHERE status = 'active' AND expires_at <= NOW()"
            );
        } catch (Throwable) {
        }
    }

    /** @return array<string, mixed>|null */
    private static function findCustomer(string $domain, string $licenseKey): ?array
    {
        if (!class_exists('KdvCustomerRepository')) {
            return null;
        }
        if ($licenseKey !== '') {
            foreach (KdvCustomerRepository::list() as $row) {
                if (strcasecmp((string) ($row['license_key'] ?? ''), $licenseKey) === 0) {
                    return $row;
                }
            }
        }
        foreach (KdvCustomerRepository::list() as $row) {
            if (strcasecmp((string) ($row['domain'] ?? ''), $domain) === 0) {
                return $row;
            }
        }

        return null;
    }
}
