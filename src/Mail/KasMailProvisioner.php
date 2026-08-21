<?php
declare(strict_types=1);

/** Postfach bei Kasserver per KAS-API anlegen (add_mailaccount). */
final class KasMailProvisioner
{
    private const WSDL = 'https://kasapi.kasserver.com/soap/wsdl/KasApi.wsdl';

    /**
     * Führt aus: create mailbox.
     * @param string $localPart
     * @param string $domainPart
     * @param string $password
     * @return array<string, mixed>
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public static function createMailbox(string $localPart, string $domainPart, string $password): array
    {
        if (!KasSettings::isConfigured()) {
            throw new RuntimeException('KAS-API ist nicht konfiguriert (config/kas.local.php).');
        }

        $localPart = strtolower(trim($localPart));
        $domainPart = strtolower(trim($domainPart));
        if ($localPart === '' || $domainPart === '') {
            throw new InvalidArgumentException('Lokal- und Domainteil der Adresse sind erforderlich.');
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Mail-Passwort mindestens 8 Zeichen.');
        }

        $response = self::call('add_mailaccount', [
            'local_part' => $localPart,
            'domain_part' => $domainPart,
            'mail_password' => $password,
        ]);

        $imapUser = $localPart . '##' . $domainPart;
        $kasHost = self::kasServerHost();
        $kasMailLogin = self::extractMailLogin($response);
        if ($kasMailLogin === '') {
            $existing = self::findMailAccountByEmail($localPart . '@' . $domainPart);
            $kasMailLogin = trim((string) ($existing['mail_login'] ?? ''));
        }

        return [
            'mail_login' => $kasMailLogin !== '' ? $kasMailLogin : $imapUser,
            'kas_mail_login' => $kasMailLogin,
            'imap_host' => $kasHost,
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => $imapUser,
            'imap_password' => $password,
            'smtp_host' => $kasHost,
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => $imapUser,
            'smtp_password' => $password,
            'kas_response' => $response,
        ];
    }

    /**
     * Methode generate password.
     * @return string
     */
    public static function generatePassword(): string
    {
        return bin2hex(random_bytes(12));
    }

    /**
     * Methode reset mailbox password.
     * @param string $kasMailLogin
     * @return string
     * @throws InvalidArgumentException
     */
    public static function resetMailboxPassword(string $kasMailLogin): string
    {
        $kasMailLogin = trim($kasMailLogin);
        if ($kasMailLogin === '') {
            throw new InvalidArgumentException('KAS mail_login fehlt.');
        }

        $password = self::generatePassword();
        self::call('update_mailaccount', [
            'mail_login' => $kasMailLogin,
            'mail_new_password' => $password,
        ]);

        return $password;
    }

    /**
     * Methode kas server host.
     * @return string
     */
    public static function kasServerHost(): string
    {
        $cfg = KasSettings::config();
        $login = trim($cfg['kas_login']);
        if ($login !== '' && preg_match('/^w\d+/i', $login) === 1) {
            return strtolower($login) . '.kasserver.com';
        }

        return 'w0217246.kasserver.com';
    }

    /**
     * Methode call.
     * @param string $action
     * @param array $params
     * @return mixed
     * @throws RuntimeException
     */
    private static function call(string $action, array $params): mixed
    {
        $cfg = KasSettings::config();
        $client = new SoapClient(self::WSDL, [
            'exceptions' => true,
            'connection_timeout' => 20,
        ]);

        $payload = json_encode([
            'kas_login' => $cfg['kas_login'],
            'kas_auth_type' => $cfg['kas_auth_type'],
            'kas_auth_data' => $cfg['kas_auth_data'],
            'kas_action' => $action,
            'KasRequestParams' => $params,
        ], JSON_THROW_ON_ERROR);

        $raw = $client->KasApi($payload);
        $decoded = self::decodeResponse($raw);
        if (!is_array($decoded)) {
            return $decoded;
        }
        if (isset($decoded['Response']['ReturnString']) && strtoupper((string) $decoded['Response']['ReturnString']) === 'FALSE') {
            $msg = (string) ($decoded['Response']['ReturnInfo'] ?? 'KAS-API Fehler');
            throw new RuntimeException($msg);
        }

        return $decoded;
    }

    /**
     * Methode list mail accounts.
     * @return array<string, mixed>
     */
    public static function listMailAccounts(): array
    {
        $decoded = self::call('get_mailaccounts', []);
        if (!is_array($decoded)) {
            return [];
        }

        $rows = $decoded['Response']['ReturnInfo'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, static fn(mixed $row): bool => is_array($row)));
    }

    /**
     * Liefert mail account by email.
     * @param string $email
     * @return array|null
     */
    public static function findMailAccountByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        foreach (self::listMailAccounts() as $row) {
            $addresses = strtolower(trim((string) ($row['mail_addresses'] ?? $row['mail_adresses'] ?? '')));
            if ($addresses === $email) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Methode decode response.
     * @param mixed $raw
     * @return mixed
     */
    private static function decodeResponse(mixed $raw): mixed
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw)) {
            return $raw;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : $raw;
    }

    /**
     * Methode extract mail login.
     * @param mixed $response
     * @return string
     */
    private static function extractMailLogin(mixed $response): string
    {
        if (!is_array($response)) {
            return '';
        }

        $info = $response['Response']['ReturnInfo'] ?? null;
        if (is_array($info)) {
            $login = trim((string) ($info['mail_login'] ?? ''));
            if ($login !== '') {
                return $login;
            }
        }
        if (is_string($info) && $info !== '') {
            return trim($info);
        }

        return '';
    }
}
