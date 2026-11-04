<?php
declare(strict_types=1);

/** Bewerberstammdaten (eine Person, mehrere Bewerbungen möglich). */
final class ApplicantRepository
{
    public static function ensureReady(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
    }

    public static function tableReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            Database::pdo()->query('SELECT 1 FROM dg_applicants LIMIT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        self::ensureReady();
        if (!Database::isConfigured() || !self::tableReady() || $id < 1) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_applicants WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByEmail(string $email): ?array
    {
        self::ensureReady();
        $email = self::normalizeEmail($email);
        if ($email === '' || !Database::isConfigured() || !self::tableReady()) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_applicants WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public static function displayName(array $row): string
    {
        $first = trim((string) ($row['first_name'] ?? ''));
        $last = trim((string) ($row['last_name'] ?? ''));
        $name = trim($first . ' ' . $last);
        if ($name !== '') {
            return $name;
        }
        $email = trim((string) ($row['email'] ?? ''));

        return $email !== '' ? $email : 'Bewerber';
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function save(array $input, ?int $id = null): int
    {
        self::ensureReady();
        if (!Database::isConfigured() || !self::tableReady()) {
            throw new RuntimeException('Bewerber-Tabelle fehlt — Migration 136 ausführen.');
        }

        $email = self::normalizeEmail((string) ($input['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Gültige E-Mail-Adresse ist erforderlich.');
        }

        $first = mb_substr(trim((string) ($input['first_name'] ?? '')), 0, 120);
        $last = mb_substr(trim((string) ($input['last_name'] ?? '')), 0, 120);
        if ($first === '' && $last === '') {
            throw new InvalidArgumentException('Vor- oder Nachname ist erforderlich.');
        }

        $existingByEmail = self::findByEmail($email);
        if ($existingByEmail !== null) {
            $existingId = (int) ($existingByEmail['id'] ?? 0);
            if ($id === null || $id < 1) {
                $id = $existingId;
            } elseif ($existingId !== $id) {
                throw new InvalidArgumentException('Diese E-Mail ist bereits einem anderen Bewerber zugeordnet.');
            }
        }

        $fields = [
            'email' => mb_substr($email, 0, 191),
            'first_name' => $first,
            'last_name' => $last,
            'phone' => mb_substr(trim((string) ($input['phone'] ?? '')), 0, 64),
            'city' => mb_substr(trim((string) ($input['city'] ?? '')), 0, 120),
            'notes' => trim((string) ($input['notes'] ?? '')),
            'contact_id' => max(0, (int) ($input['contact_id'] ?? 0)),
            'privacy_consent_at' => self::nullableDateTime($input['privacy_consent_at'] ?? null),
            'retention_until' => self::nullableDate($input['retention_until'] ?? null),
        ];

        $pdo = Database::pdo();
        if ($id !== null && $id > 0) {
            if (self::find($id) === null) {
                throw new InvalidArgumentException('Bewerber nicht gefunden.');
            }
            $fields['id'] = $id;
            $stmt = $pdo->prepare(
                'UPDATE dg_applicants SET
                    email = :email, first_name = :first_name, last_name = :last_name,
                    phone = :phone, city = :city, notes = :notes, contact_id = :contact_id,
                    privacy_consent_at = :privacy_consent_at, retention_until = :retention_until
                 WHERE id = :id'
            );
            $stmt->execute($fields);

            return $id;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO dg_applicants (
                email, first_name, last_name, phone, city, notes, contact_id,
                privacy_consent_at, retention_until
            ) VALUES (
                :email, :first_name, :last_name, :phone, :city, :notes, :contact_id,
                :privacy_consent_at, :retention_until
            )'
        );
        $stmt->execute($fields);

        return (int) $pdo->lastInsertId();
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }

    private static function nullableDateTime(mixed $value): ?string
    {
        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }
        $ts = strtotime($s);

        return $ts !== false ? date('Y-m-d H:i:s', $ts) : null;
    }

    private static function nullableDate(mixed $value): ?string
    {
        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }
        $ts = strtotime($s);

        return $ts !== false ? date('Y-m-d', $ts) : null;
    }
}
