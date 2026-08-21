<?php
declare(strict_types=1);

/**
 * Skalare KDV-Einstellungen (SettingsStore speichert nur Arrays).
 */
final class KdvConfig
{
    public static function get(string $key, string $default = ''): string
    {
        try {
            $data = SettingsStore::get('kdv.' . $key, ['value' => $default]);
        } catch (Throwable) {
            return $default;
        }
        $value = $data['value'] ?? $default;
        return is_string($value) ? $value : $default;
    }

    public static function set(string $key, string $value): void
    {
        SettingsStore::set('kdv.' . $key, ['value' => $value]);
    }

    public static function shopPublicUrl(): string
    {
        $url = self::get('shop_public_url', 'https://shop.ganz-soft.de');
        return rtrim($url !== '' ? $url : 'https://shop.ganz-soft.de', '/');
    }

    public static function supportEmail(): string
    {
        $email = self::get('support_email', 'info@ganz-soft.de');
        return $email !== '' ? $email : 'info@ganz-soft.de';
    }

    public static function licenseServerUrl(): string
    {
        $url = self::get('license_server_url', 'https://dg-user.ganz-soft.de');
        return rtrim($url !== '' ? $url : 'https://dg-user.ganz-soft.de', '/');
    }

    public static function licenseAdminToken(): string
    {
        return self::get('license_admin_token', '');
    }
}
