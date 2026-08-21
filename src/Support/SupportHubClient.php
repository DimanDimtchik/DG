<?php
declare(strict_types=1);

/**
 * Meldet Support-Freigaben an den KDV-Hub (Master).
 */
final class SupportHubClient
{
    public static function hubBaseUrl(): string
    {
        $configured = '';
        if (class_exists('KdvConfig')) {
            $configured = KdvConfig::get('support_hub_url', '');
        }
        if ($configured === '') {
            $configured = (string) App::config('support_hub_url', 'https://dg.ganz-om.de');
        }

        return rtrim($configured !== '' ? $configured : 'https://dg.ganz-om.de', '/');
    }

    /**
     * @param array<string, mixed> $grant
     */
    public static function reportStart(string $token, array $grant): void
    {
        self::post([
            'action' => 'start',
            'domain' => self::currentDomain(),
            'company_name' => self::companyName(),
            'license_key' => self::licenseKey(),
            'token' => $token,
            'expires_at' => (string) ($grant['expires_at'] ?? ''),
            'duration_hours' => (int) ($grant['duration_hours'] ?? 24),
        ]);
    }

    /**
     * @param array<string, mixed> $grant
     */
    public static function reportStop(array $grant): void
    {
        self::post([
            'action' => 'stop',
            'domain' => self::currentDomain(),
            'license_key' => self::licenseKey(),
            'expires_at' => (string) ($grant['expires_at'] ?? ''),
        ]);
    }

    /** @param array<string, mixed> $payload */
    private static function post(array $payload): void
    {
        $url = self::hubBaseUrl() . '/api/kdv/support-grant';
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            return;
        }
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $body,
                'timeout' => 6,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        @file_get_contents($url, false, $ctx);
    }

    private static function currentDomain(): string
    {
        if (class_exists('LicenseGuard')) {
            $d = LicenseGuard::currentDomain();
            if ($d !== '') {
                return $d;
            }
        }
        return strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    }

    private static function licenseKey(): string
    {
        return class_exists('LicenseGuard') ? LicenseGuard::licenseKey() : '';
    }

    private static function companyName(): string
    {
        if (class_exists('CompanySettings')) {
            $cfg = CompanySettings::config();
            $name = trim((string) ($cfg['name'] ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        return (string) App::config('crm_name', 'CRM');
    }
}
