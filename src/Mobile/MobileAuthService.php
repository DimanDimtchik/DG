<?php
declare(strict_types=1);

/**
 * Mobile-Auth: Kunden-Account (Kontakt) und Mitarbeiter (PIN) → Bearer-Token.
 * Kein CRM-User (`dg_users`).
 */
final class MobileAuthService
{
    public const MIN_PASSWORD = 8;

    /**
     * @return array{token: string, expires_at: string, subject_type: string, contact: array<string, mixed>}
     */
    public static function registerCustomer(
        string $email,
        string $password,
        string $name = '',
        string $phone = '',
    ): array {
        MobileAuthThrottle::assertAllowed('customer_register');
        $email = strtolower(trim($email));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Gültige E-Mail erforderlich.');
        }
        if (strlen($password) < self::MIN_PASSWORD) {
            throw new InvalidArgumentException('Passwort mindestens ' . self::MIN_PASSWORD . ' Zeichen.');
        }
        if (MobileCustomerAccountRepository::findByEmail($email) !== null) {
            throw new InvalidArgumentException('Für diese E-Mail existiert bereits ein App-Konto.');
        }

        $contact = ContactRepository::findByEmailMatch($email);
        if ($contact === null) {
            $contactId = self::createCustomerContact($email, $name, $phone);
        } else {
            $contactId = $contact->id;
            $existingAcc = MobileCustomerAccountRepository::findByContactId($contactId);
            if ($existingAcc !== null) {
                throw new InvalidArgumentException('Dieser Kontakt hat bereits ein App-Konto.');
            }
        }

        $accountId = MobileCustomerAccountRepository::create(
            $contactId,
            $email,
            password_hash($password, PASSWORD_DEFAULT)
        );
        $issued = MobileTokenRepository::issue('customer', $accountId, $contactId);

        return [
            'token' => $issued['token'],
            'expires_at' => $issued['expires_at'],
            'subject_type' => 'customer',
            'contact' => self::contactPublic($contactId),
        ];
    }

    /**
     * @return array{token: string, expires_at: string, subject_type: string, contact: array<string, mixed>}
     */
    public static function loginCustomer(string $email, string $password): array
    {
        MobileAuthThrottle::assertAllowed('customer_login');
        $email = strtolower(trim($email));
        $row = MobileCustomerAccountRepository::findByEmail($email);
        $hash = is_array($row) ? (string) ($row['password_hash'] ?? '') : '';
        if ($row === null || $hash === '' || !password_verify($password, $hash)) {
            throw new InvalidArgumentException('E-Mail oder Passwort ungültig.');
        }
        $accountId = (int) ($row['id'] ?? 0);
        $contactId = (int) ($row['contact_id'] ?? 0);
        $issued = MobileTokenRepository::issue('customer', $accountId, $contactId);

        return [
            'token' => $issued['token'],
            'expires_at' => $issued['expires_at'],
            'subject_type' => 'customer',
            'contact' => self::contactPublic($contactId),
        ];
    }

    /**
     * @return array{token: string, expires_at: string, subject_type: string, contact: array<string, mixed>}
     */
    public static function loginStaff(string $identifier, string $pin): array
    {
        MobileAuthThrottle::assertAllowed('staff_login');
        $contact = TimeKioskService::resolveStaffByIdentifier($identifier);
        if ($contact === null) {
            throw new InvalidArgumentException('Mitarbeiter nicht gefunden.');
        }
        if (!TimeKioskPinRepository::hasPin($contact->id)) {
            throw new InvalidArgumentException('Keine PIN gesetzt — bitte HR oder CRM nutzen.');
        }
        if (!TimeKioskService::verifyPin($contact->id, $pin)) {
            throw new InvalidArgumentException('PIN ungültig.');
        }
        $issued = MobileTokenRepository::issue('staff', $contact->id, $contact->id);

        return [
            'token' => $issued['token'],
            'expires_at' => $issued['expires_at'],
            'subject_type' => 'staff',
            'contact' => self::contactPublic($contact->id),
        ];
    }

    public static function logout(?string $bearer): void
    {
        if ($bearer !== null && $bearer !== '') {
            MobileTokenRepository::revokeByPlain($bearer);
        }
    }

    /**
     * @return array{token: string, expires_at: string, subject_type: string, contact: array<string, mixed>}
     */
    public static function refresh(string $bearer): array
    {
        $row = MobileTokenRepository::findValidByPlain($bearer);
        if ($row === null) {
            throw new InvalidArgumentException('Token ungültig oder abgelaufen.');
        }
        MobileTokenRepository::revokeByPlain($bearer);
        $type = (string) ($row['subject_type'] ?? '');
        $subjectId = (int) ($row['subject_id'] ?? 0);
        $contactId = (int) ($row['contact_id'] ?? 0);
        $issued = MobileTokenRepository::issue($type, $subjectId, $contactId);

        return [
            'token' => $issued['token'],
            'expires_at' => $issued['expires_at'],
            'subject_type' => $type,
            'contact' => self::contactPublic($contactId),
        ];
    }

    /**
     * @return array{subject_type: string, subject_id: int, contact_id: int, contact: array<string, mixed>}
     */
    public static function requireAuth(?string $expectedType = null): array
    {
        $bearer = self::bearerFromRequest();
        if ($bearer === null) {
            throw new RuntimeException('Authorization Bearer erforderlich.');
        }
        $row = MobileTokenRepository::findValidByPlain($bearer);
        if ($row === null) {
            throw new RuntimeException('Token ungültig oder abgelaufen.');
        }
        $type = (string) ($row['subject_type'] ?? '');
        if ($expectedType !== null && $type !== $expectedType) {
            throw new RuntimeException('Falscher App-Kontotyp für diesen Endpunkt.');
        }
        $contactId = (int) ($row['contact_id'] ?? 0);

        return [
            'subject_type' => $type,
            'subject_id' => (int) ($row['subject_id'] ?? 0),
            'contact_id' => $contactId,
            'contact' => self::contactPublic($contactId),
            'bearer' => $bearer,
        ];
    }

    public static function bearerFromRequest(): ?string
    {
        $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $header, $m)) {
            return $m[1];
        }
        $alt = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));

        return $alt !== '' ? $alt : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function contactPublic(int $contactId): array
    {
        $c = ContactRepository::findById($contactId);
        if ($c === null) {
            return ['id' => $contactId, 'label' => '#' . $contactId];
        }

        return [
            'id' => $c->id,
            'label' => $c->listLabel(),
            'email' => (string) ($c->email ?? ''),
            'phone' => (string) ($c->phone1 ?? ''),
            'display_name' => (string) ($c->displayName ?? ''),
        ];
    }

    private static function createCustomerContact(string $email, string $name, string $phone): int
    {
        $name = trim($name);
        $parts = preg_split('/\s+/', $name !== '' ? $name : 'App Kunde') ?: ['App', 'Kunde'];
        $first = (string) ($parts[0] ?? 'App');
        $last = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'Kunde';
        $login = 'app_' . substr(hash('sha256', $email . microtime(true)), 0, 12);
        while (ContactRepository::loginExists($login, null)) {
            $login = 'app_' . bin2hex(random_bytes(6));
        }

        return ContactRepository::save([
            'login' => $login,
            'salutation' => '',
            'first_name' => $first,
            'last_name' => $last,
            'display_name' => $name !== '' ? $name : trim($first . ' ' . $last),
            'email' => $email,
            'phone_1' => trim($phone),
            'contact_role' => 'dg_kunde',
        ]);
    }
}
