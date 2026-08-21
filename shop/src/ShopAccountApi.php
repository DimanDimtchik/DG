<?php

declare(strict_types=1);

/**
 * Ruft die KDV Shop-Konto-API auf dem Master (dg.ganz-om.de) auf.
 */
final class ShopAccountApi
{
    public static function baseUrl(): string
    {
        $url = trim((string) ShopApp::config('kdv_account_url', ''));
        if ($url === '') {
            $url = 'https://dg.ganz-om.de/api/kdv/account';
        }
        return rtrim($url, '/');
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array{ok: bool, status: int, data: array<string, mixed>, error?: string}
     */
    public static function request(string $method, string $path, ?array $body = null, ?string $token = null): array
    {
        $url = self::baseUrl() . '/' . ltrim($path, '/');
        $headers = ['Accept: application/json'];
        if ($token !== null && $token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        $content = null;
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $content = json_encode($body, JSON_UNESCAPED_UNICODE);
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $content,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => true],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }
        if ($raw === false) {
            return ['ok' => false, 'status' => $status, 'data' => [], 'error' => 'Konto-Server nicht erreichbar.'];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $data = [];
        }
        $ok = $status >= 200 && $status < 300 && (($data['ok'] ?? false) === true || $status === 200);
        if (isset($data['ok'])) {
            $ok = (bool) $data['ok'] && $status >= 200 && $status < 300;
        }
        return [
            'ok' => $ok,
            'status' => $status,
            'data' => $data,
            'error' => $ok ? null : (string) ($data['error'] ?? 'Anfrage fehlgeschlagen.'),
        ];
    }

    /**
     * @return array{ok: bool, token?: string, account?: array<string, mixed>, error?: string}
     */
    public static function login(string $email, string $password): array
    {
        $res = self::request('POST', 'login', ['email' => $email, 'password' => $password]);
        if (!$res['ok']) {
            return ['ok' => false, 'error' => $res['error'] ?? 'Login fehlgeschlagen.'];
        }
        return [
            'ok' => true,
            'token' => (string) ($res['data']['token'] ?? ''),
            'account' => is_array($res['data']['account'] ?? null) ? $res['data']['account'] : [],
        ];
    }

    /**
     * @return array{ok: bool, account?: array<string, mixed>, error?: string}
     */
    public static function me(string $token): array
    {
        $res = self::request('GET', 'me', null, $token);
        if (!$res['ok']) {
            return ['ok' => false, 'error' => $res['error'] ?? 'Sitzung ungültig.'];
        }
        return [
            'ok' => true,
            'account' => is_array($res['data']['account'] ?? null) ? $res['data']['account'] : [],
        ];
    }

    /**
     * @return array{ok: bool, accepted?: bool, message?: string, reason?: string, error?: string}
     */
    public static function unlockRequest(string $token, string $message): array
    {
        $res = self::request('POST', 'unlock-request', ['message' => $message], $token);
        if (!$res['ok'] && ($res['status'] ?? 0) >= 400 && ($res['status'] ?? 0) < 500) {
            return ['ok' => false, 'error' => $res['error'] ?? 'Anfrage abgelehnt.'];
        }
        if (!$res['ok']) {
            return ['ok' => false, 'error' => $res['error'] ?? 'Anfrage fehlgeschlagen.'];
        }
        return [
            'ok' => true,
            'accepted' => (bool) ($res['data']['accepted'] ?? false),
            'message' => (string) ($res['data']['message'] ?? ''),
            'reason' => (string) ($res['data']['reason'] ?? ''),
        ];
    }

    public static function logout(string $token): void
    {
        self::request('POST', 'logout', [], $token);
    }

    /**
     * @return array{ok: bool, message?: string, error?: string}
     */
    public static function requestPasswordReset(string $email): array
    {
        $res = self::request('POST', 'password-reset/request', ['email' => $email]);
        if (!$res['ok']) {
            return ['ok' => false, 'error' => $res['error'] ?? 'Anfrage fehlgeschlagen.'];
        }
        return [
            'ok' => true,
            'message' => (string) ($res['data']['message'] ?? 'Falls ein Konto existiert, erhalten Sie eine E-Mail.'),
        ];
    }

    /**
     * @return array{ok: bool, message?: string, error?: string}
     */
    public static function confirmPasswordReset(string $token, string $password, string $confirm): array
    {
        $res = self::request('POST', 'password-reset/confirm', [
            'token' => $token,
            'password' => $password,
            'password_confirm' => $confirm,
        ]);
        if (!$res['ok']) {
            return ['ok' => false, 'error' => $res['error'] ?? 'Passwort konnte nicht gesetzt werden.'];
        }
        return [
            'ok' => true,
            'message' => (string) ($res['data']['message'] ?? 'Passwort geändert.'),
        ];
    }
}
