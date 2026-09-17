<?php
declare(strict_types=1);

/** KDV-Organisationen (Multi-Firma Phase 0 — Rechnungsempfänger / Login-Träger). */
final class KdvOrgRepository
{
    public static function tableReady(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            $ready = Database::pdo()->query("SHOW TABLES LIKE 'dg_kdv_orgs'")->fetchColumn() !== false;
        } catch (Throwable) {
            $ready = false;
        }

        return $ready;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function list(): array
    {
        if (!self::tableReady()) {
            return [];
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->query(
            'SELECT o.*,
                    (SELECT COUNT(*) FROM dg_kdv_customers c WHERE c.org_id = o.id) AS firm_count
             FROM dg_kdv_orgs o
             ORDER BY o.name ASC'
        );

        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /** @return array<string, mixed>|null */
    public static function findById(int $id): ?array
    {
        if ($id < 1 || !self::tableReady()) {
            return null;
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_kdv_orgs WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function save(array $data, ?int $id = null): int
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }
        MigrationRunner::runPending();
        if (!self::tableReady()) {
            throw new RuntimeException('Tabelle dg_kdv_orgs fehlt — Migration 082 ausführen.');
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Organisationsname ist erforderlich.');
        }
        $fields = [
            'name' => mb_substr($name, 0, 191),
            'billing_email' => self::emailOrNull($data['billing_email'] ?? null),
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ];

        $pdo = Database::pdo();
        if ($id !== null && $id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE dg_kdv_orgs SET name = :name, billing_email = :billing_email, notes = :notes WHERE id = :id'
            );
            $fields['id'] = $id;
            $stmt->execute($fields);

            return $id;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO dg_kdv_orgs (name, billing_email, notes) VALUES (:name, :billing_email, :notes)'
        );
        $stmt->execute($fields);

        return (int) $pdo->lastInsertId();
    }

    /** Legt bei Bedarf eine Org anhand des Namens an und liefert die ID. */
    public static function ensureByName(string $name, ?string $billingEmail = null): int
    {
        $name = trim($name);
        if ($name === '') {
            return 0;
        }
        if (!self::tableReady()) {
            return 0;
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            'SELECT id FROM dg_kdv_orgs WHERE name = :name LIMIT 1'
        );
        $stmt->execute(['name' => mb_substr($name, 0, 191)]);
        $id = (int) ($stmt->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }

        return self::save([
            'name' => $name,
            'billing_email' => $billingEmail,
        ]);
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public static function options(): array
    {
        $out = [];
        foreach (self::list() as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $count = (int) ($row['firm_count'] ?? 0);
            $label = (string) ($row['name'] ?? '');
            if ($count > 0) {
                $label .= ' (' . $count . ' Firma' . ($count === 1 ? '' : 'en') . ')';
            }
            $out[] = ['id' => $id, 'label' => $label];
        }

        return $out;
    }

    private static function emailOrNull(mixed $value): ?string
    {
        $email = strtolower(trim((string) $value));
        if ($email === '') {
            return null;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
