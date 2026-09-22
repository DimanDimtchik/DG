<?php
declare(strict_types=1);

/** `/api/mobile/auth/*` */
final class MobileAuthApi
{
    /**
     * @param list<string> $parts
     * @return never
     */
    public static function handle(array $parts): void
    {
        $a = $parts[0] ?? '';
        $b = $parts[1] ?? '';

        if ($a === 'customer' && $b === 'register') {
            self::requirePost();
            $body = MobileApi::jsonBody();
            $res = MobileAuthService::registerCustomer(
                (string) ($body['email'] ?? ''),
                (string) ($body['password'] ?? ''),
                (string) ($body['name'] ?? ''),
                (string) ($body['phone'] ?? '')
            );
            MobileApi::ok($res);
        }
        if ($a === 'customer' && $b === 'login') {
            self::requirePost();
            $body = MobileApi::jsonBody();
            MobileApi::ok(MobileAuthService::loginCustomer(
                (string) ($body['email'] ?? ''),
                (string) ($body['password'] ?? '')
            ));
        }
        if ($a === 'staff' && $b === 'login') {
            self::requirePost();
            $body = MobileApi::jsonBody();
            MobileApi::ok(MobileAuthService::loginStaff(
                (string) ($body['identifier'] ?? ''),
                (string) ($body['pin'] ?? '')
            ));
        }
        if ($a === 'logout') {
            self::requirePost();
            MobileAuthService::logout(MobileAuthService::bearerFromRequest());
            MobileApi::ok(['logged_out' => true]);
        }
        if ($a === 'refresh') {
            self::requirePost();
            $bearer = MobileAuthService::bearerFromRequest();
            if ($bearer === null) {
                throw new RuntimeException('Authorization Bearer erforderlich.');
            }
            MobileApi::ok(MobileAuthService::refresh($bearer));
        }
        if ($a === 'me') {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
                MobileApi::fail(405, 'Nur GET erlaubt.', 'method_not_allowed');
            }
            $auth = MobileAuthService::requireAuth();
            MobileApi::ok([
                'subject_type' => $auth['subject_type'],
                'subject_id' => $auth['subject_id'],
                'contact' => $auth['contact'],
            ]);
        }

        MobileApi::fail(404, 'Unbekannter Auth-Endpunkt.', 'not_found');
    }

    private static function requirePost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            MobileApi::fail(405, 'Nur POST erlaubt.', 'method_not_allowed');
        }
    }
}
