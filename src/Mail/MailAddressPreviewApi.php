<?php
declare(strict_types=1);

/**
 * Mail Address Preview Api.
 */
final class MailAddressPreviewApi
{
    /**
     * HTTP-API-Einstieg.
     * @return void
     */
    public static function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $user = AuthService::user();
        if ($user === null || !RoleResolver::isAdmin($user)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Forbidden'], JSON_THROW_ON_ERROR);
            return;
        }

        $firstName = trim((string) ($_GET['first_name'] ?? 'Max'));
        $lastName = trim((string) ($_GET['last_name'] ?? 'Mustermann'));
        $login = trim((string) ($_GET['login'] ?? 'maxm'));
        $preset = trim((string) ($_GET['preset'] ?? ''));
        $separator = trim((string) ($_GET['separator'] ?? '.'));
        $pattern = trim((string) ($_GET['local_pattern'] ?? ''));

        if ($preset !== '' && isset(MailAddressTokens::presets()[$preset])) {
            $pattern = MailAddressTokens::presets()[$preset];
        }
        if ($pattern === '') {
            $pattern = MailAddressSettings::config()['local_pattern'];
        }

        try {
            $local = MailAddressTokens::resolveLocalPart($pattern, [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'login' => $login,
                'separator' => $separator !== '' ? $separator : MailAddressSettings::config()['separator'],
            ], 0);
            $domain = MailAddressSettings::effectiveDomain();
            $email = $domain !== '' ? $local . '@' . $domain : $local;

            echo json_encode([
                'ok' => true,
                'local_part' => $local,
                'email' => $email,
                'domain' => $domain,
            ], JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_THROW_ON_ERROR);
        }
    }
}
