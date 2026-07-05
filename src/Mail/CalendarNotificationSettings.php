<?php
declare(strict_types=1);

/** Versand-Optionen für Kalender-Benachrichtigungen. */
final class CalendarNotificationSettings
{
    public const STORE_KEY = 'calendar_notification_delivery';

    /** @return array{send_customer_email: bool, send_admin_email: bool, notify_admin_email: string} */
    public static function defaults(): array
    {
        return [
            'send_customer_email' => true,
            'send_admin_email' => true,
            'notify_admin_email' => '',
        ];
    }

    /** @return array{send_customer_email: bool, send_admin_email: bool, notify_admin_email: string} */
    public static function config(): array
    {
        if (!Database::isConfigured()) {
            return self::defaults();
        }

        $stored = SettingsStore::get(self::STORE_KEY, self::defaults());

        return [
            'send_customer_email' => !empty($stored['send_customer_email']),
            'send_admin_email' => !array_key_exists('send_admin_email', $stored) || !empty($stored['send_admin_email']),
            'notify_admin_email' => trim((string) ($stored['notify_admin_email'] ?? '')),
        ];
    }

    /** @return array{send_customer_email: bool, send_admin_email: bool, notify_admin_email: string} */
    public static function forForm(): array
    {
        return self::config();
    }

    public static function notifyAdminEmail(): string
    {
        $configured = trim(self::config()['notify_admin_email']);
        if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
            return $configured;
        }

        return CompanySettings::mailEmail();
    }

    /** @param array<string, mixed> $input */
    public static function saveFromPost(array $input): void
    {
        SettingsStore::set(self::STORE_KEY, [
            'send_customer_email' => !empty($input['calendar_send_customer_email']) ? 1 : 0,
            'send_admin_email' => !empty($input['calendar_send_admin_email']) ? 1 : 0,
            'notify_admin_email' => trim((string) ($input['calendar_notify_admin_email'] ?? '')),
        ]);
    }
}
