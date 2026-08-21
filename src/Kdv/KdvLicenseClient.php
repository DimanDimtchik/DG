<?php
declare(strict_types=1);

/**
 * HTTP-Client für den Lizenzserver (Admin-API).
 */
final class KdvLicenseClient
{
    private const DEFAULT_BASE = 'https://dg-user.ganz-soft.de';

    public static function baseUrl(): string
    {
        return KdvConfig::licenseServerUrl();
    }

    public static function adminToken(): string
    {
        return KdvConfig::licenseAdminToken();
    }

    public static function isConfigured(): bool
    {
        return self::adminToken() !== '';
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, data: array<string, mixed>, error?: string}
     */
    public static function request(string $method, string $path, array $body = []): array
    {
        $token = self::adminToken();
        if ($token === '') {
            return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'Lizenzserver-Admin-Token fehlt (KDV-Dashboard).'];
        }

        $url = self::baseUrl() . '/' . ltrim($path, '/');
        $headers = [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ];
        $content = null;
        if ($method !== 'GET' && $method !== 'DELETE') {
            $headers[] = 'Content-Type: application/json';
            $content = json_encode($body, JSON_UNESCAPED_UNICODE);
        } elseif ($method === 'GET' && $body !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($body);
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
            return ['ok' => false, 'status' => $status, 'data' => [], 'error' => 'Lizenzserver nicht erreichbar.'];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $data = [];
        }
        $ok = $status >= 200 && $status < 300;
        return [
            'ok' => $ok,
            'status' => $status,
            'data' => $data,
            'error' => $ok ? null : (string) ($data['error'] ?? ('HTTP ' . $status)),
        ];
    }

    /**
     * @return array{ok: bool, license_key?: string, id?: int, status?: string, error?: string}
     */
    public static function createLicense(string $domain, string $plan, ?string $validTo = null, ?string $note = null, ?string $licenseKey = null): array
    {
        $body = [
            'domain' => strtolower(trim($domain)),
            'plan' => $plan,
            'valid_to' => $validTo,
            'note' => $note,
        ];
        if ($licenseKey !== null && trim($licenseKey) !== '') {
            $body['license_key'] = trim($licenseKey);
        }
        $res = self::request('POST', 'admin/licenses', $body);
        if (!$res['ok']) {
            return ['ok' => false, 'error' => $res['error'] ?? 'Anlage fehlgeschlagen'];
        }
        return [
            'ok' => true,
            'license_key' => (string) ($res['data']['license_key'] ?? ''),
            'id' => (int) ($res['data']['id'] ?? 0),
            'status' => (string) ($res['data']['status'] ?? 'active'),
        ];
    }

    /**
     * @return array{ok: bool, licenses?: list<array<string, mixed>>, error?: string}
     */
    public static function findLicenses(?string $domain = null, ?string $key = null): array
    {
        $q = [];
        if ($domain !== null && $domain !== '') {
            $q['domain'] = strtolower(trim($domain));
        }
        if ($key !== null && $key !== '') {
            $q['key'] = trim($key);
        }
        $res = self::request('GET', 'admin/licenses', $q);
        if (!$res['ok']) {
            return ['ok' => false, 'error' => $res['error'] ?? 'Abfrage fehlgeschlagen'];
        }
        $list = $res['data']['licenses'] ?? [];
        return ['ok' => true, 'licenses' => is_array($list) ? $list : []];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public static function setStatusById(int $id, string $status): array
    {
        if ($id < 1) {
            return ['ok' => false, 'error' => 'Ungültige Lizenz-ID'];
        }
        $res = self::request('PATCH', 'admin/licenses/' . $id, ['status' => $status]);
        if (!$res['ok']) {
            return ['ok' => false, 'error' => $res['error'] ?? 'Statusänderung fehlgeschlagen'];
        }
        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public static function setStatusByKey(string $licenseKey, string $status): array
    {
        $res = self::request('PATCH', 'admin/licenses/by-key', [
            'license_key' => trim($licenseKey),
            'status' => $status,
        ]);
        if (!$res['ok']) {
            return ['ok' => false, 'error' => $res['error'] ?? 'Statusänderung fehlgeschlagen'];
        }
        return ['ok' => true];
    }
}
