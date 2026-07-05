<?php
declare(strict_types=1);

final class UserRepository
{
    public static function findByUsername(string $username): ?User
    {
        if (self::useDatabase()) {
            $stmt = Database::pdo()->prepare(
                'SELECT id, username, display_name, email, role, employee_active
                 FROM dg_users WHERE username = :username LIMIT 1'
            );
            $stmt->execute(['username' => $username]);
            $record = $stmt->fetch();

            return $record ? self::mapDb($record) : null;
        }

        foreach (self::loadFile()['users'] as $record) {
            if (strcasecmp((string) $record['username'], $username) === 0) {
                return self::mapFile($record);
            }
        }

        return null;
    }

    public static function findByEmail(string $email): ?User
    {
        $email = trim($email);
        if ($email === '') {
            return null;
        }

        if (self::useDatabase()) {
            $stmt = Database::pdo()->prepare(
                'SELECT id, username, display_name, email, role, employee_active
                 FROM dg_users WHERE LOWER(email) = LOWER(:email) LIMIT 1'
            );
            $stmt->execute(['email' => $email]);
            $record = $stmt->fetch();

            return $record ? self::mapDb($record) : null;
        }

        foreach (self::loadFile()['users'] as $record) {
            if (strcasecmp((string) $record['email'], $email) === 0) {
                return self::mapFile($record);
            }
        }

        return null;
    }

    public static function findByEmailOrUsername(string $identifier): ?User
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        if (str_contains($identifier, '@')) {
            return self::findByEmail($identifier) ?? self::findByUsername($identifier);
        }

        return self::findByUsername($identifier) ?? self::findByEmail($identifier);
    }

    public static function updatePassword(int $userId, string $password): void
    {
        if ($userId < 1 || $password === '') {
            throw new InvalidArgumentException('Ungültige Passwortänderung.');
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Passwort mindestens 8 Zeichen.');
        }

        if (!self::useDatabase()) {
            throw new RuntimeException('Passwortänderung nur mit Datenbankverbindung möglich.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = Database::pdo()->prepare(
            'UPDATE dg_users SET password_hash = :password_hash WHERE id = :id'
        );
        $stmt->execute([
            'password_hash' => $hash,
            'id' => $userId,
        ]);

        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('Passwort konnte nicht gespeichert werden.');
        }
    }

    public static function findById(int $id): ?User
    {
        if (self::useDatabase()) {
            $stmt = Database::pdo()->prepare(
                'SELECT id, username, display_name, email, role, employee_active
                 FROM dg_users WHERE id = :id LIMIT 1'
            );
            $stmt->execute(['id' => $id]);
            $record = $stmt->fetch();

            return $record ? self::mapDb($record) : null;
        }

        $record = self::loadFile()['users'][$id] ?? null;

        return $record ? self::mapFile($record) : null;
    }

    public static function verifyPassword(User $user, string $password): bool
    {
        if (self::useDatabase()) {
            $stmt = Database::pdo()->prepare(
                'SELECT password_hash FROM dg_users WHERE id = :id LIMIT 1'
            );
            $stmt->execute(['id' => $user->id]);
            $hash = $stmt->fetchColumn();

            return is_string($hash) && password_verify($password, $hash);
        }

        $record = self::loadFile()['users'][$user->id] ?? null;
        if (!$record) {
            return false;
        }

        return password_verify($password, (string) $record['password_hash']);
    }

    /** @return list<array{id: string, name: string, member_role: string}> */
    public static function departmentsForUser(int $userId): array
    {
        if (self::useDatabase()) {
            $stmt = Database::pdo()->prepare(
                'SELECT d.id, d.name, m.member_role
                 FROM dg_department_members m
                 INNER JOIN dg_departments d ON d.id = m.department_id
                 WHERE m.user_id = :user_id
                 ORDER BY d.sort_order, d.name'
            );
            $stmt->execute(['user_id' => $userId]);

            $result = [];
            while ($row = $stmt->fetch()) {
                $result[] = [
                    'id' => (string) $row['id'],
                    'name' => (string) $row['name'],
                    'member_role' => (string) $row['member_role'],
                ];
            }

            return $result;
        }

        $result = [];

        foreach (DefaultDepartments::withModulesAndMembers(DefaultDepartments::membersFromConfigFile()) as $department) {
            foreach ($department['members'] as $member) {
                if ((int) ($member['user_id'] ?? 0) === $userId) {
                    $result[] = [
                        'id' => (string) $department['id'],
                        'name' => (string) $department['name'],
                        'member_role' => (string) ($member['role'] ?? 'member'),
                    ];
                }
            }
        }

        return $result;
    }

    /** @return list<User> */
    public static function all(): array
    {
        if (!self::useDatabase()) {
            $users = [];
            foreach (self::loadFile()['users'] as $record) {
                $users[] = self::mapFile($record);
            }

            return $users;
        }

        $stmt = Database::pdo()->query(
            'SELECT id, username, display_name, email, role, employee_active
             FROM dg_users ORDER BY display_name ASC, username ASC'
        );
        $users = [];
        while ($row = $stmt->fetch()) {
            $users[] = self::mapDb($row);
        }

        return $users;
    }

    public static function usernameExists(string $username): bool
    {
        return self::findByUsername($username) !== null;
    }

    public static function register(string $username, string $email, string $displayName, string $password): User
    {
        $username = trim($username);
        $email = trim($email);
        $displayName = trim($displayName);

        if ($username === '' || $email === '' || $displayName === '' || $password === '') {
            throw new InvalidArgumentException('Alle Felder sind Pflichtfelder.');
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Passwort mindestens 8 Zeichen.');
        }
        if (self::usernameExists($username)) {
            throw new InvalidArgumentException('Benutzername ist bereits vergeben.');
        }

        $role = (string) App::config('roles.customer', 'dg_kunde');
        $hash = password_hash($password, PASSWORD_DEFAULT);

        if (self::useDatabase()) {
            $pdo = Database::pdo();
            $stmt = $pdo->prepare(
                'INSERT INTO dg_users (username, password_hash, email, display_name, role, employee_active)
                 VALUES (:username, :password_hash, :email, :display_name, :role, 0)'
            );
            $stmt->execute([
                'username' => $username,
                'password_hash' => $hash,
                'email' => $email,
                'display_name' => $displayName,
                'role' => $role,
            ]);
            $user = self::findById((int) $pdo->lastInsertId());
            if (!$user) {
                throw new RuntimeException('Registrierung fehlgeschlagen.');
            }

            return $user;
        }

        throw new RuntimeException('Registrierung nur mit Datenbankverbindung möglich.');
    }

  /** @param array<string, mixed> $record */
    private static function mapFile(array $record): User
    {
        return new User(
            (int) $record['id'],
            (string) $record['username'],
            (string) $record['display_name'],
            (string) $record['email'],
            array_values(array_map('strval', $record['roles'] ?? [])),
            (bool) ($record['employee_active'] ?? false),
        );
    }

  /** @param array<string, mixed> $record */
    private static function mapDb(array $record): User
    {
        return new User(
            (int) $record['id'],
            (string) $record['username'],
            (string) $record['display_name'],
            (string) $record['email'],
            [(string) $record['role']],
            (bool) ($record['employee_active'] ?? false),
        );
    }

    private static function useDatabase(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }

        try {
            Database::pdo()->query('SELECT 1 FROM dg_users LIMIT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

  /** @var array{users: array<int, array<string, mixed>>, departments: list<array<string, mixed>>}|null */
    private static ?array $fileData = null;

  /** @return array{users: array<int, array<string, mixed>>, departments: list<array<string, mixed>>} */
    private static function loadFile(): array
    {
        if (self::$fileData === null) {
            self::$fileData = require DG_ROOT . '/config/users.php';
        }

        return self::$fileData;
    }
}
