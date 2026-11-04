<?php
declare(strict_types=1);

/** E-Mail-Benachrichtigungen Recruiting (HR + Bewerber). */
final class RecruitingMailSettings
{
    public const STORE_KEY = 'recruiting_mail';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'send_hr_on_apply' => true,
            'send_applicant_confirmation' => true,
            'send_applicant_status' => true,
            'notify_hr_email' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        if (!class_exists('SettingsStore') || !Database::isConfigured()) {
            return self::defaults();
        }

        $stored = SettingsStore::get(self::STORE_KEY, self::defaults());
        $defaults = self::defaults();

        return [
            'send_hr_on_apply' => array_key_exists('send_hr_on_apply', $stored)
                ? !empty($stored['send_hr_on_apply'])
                : !empty($defaults['send_hr_on_apply']),
            'send_applicant_confirmation' => array_key_exists('send_applicant_confirmation', $stored)
                ? !empty($stored['send_applicant_confirmation'])
                : !empty($defaults['send_applicant_confirmation']),
            'send_applicant_status' => array_key_exists('send_applicant_status', $stored)
                ? !empty($stored['send_applicant_status'])
                : !empty($defaults['send_applicant_status']),
            'notify_hr_email' => trim((string) ($stored['notify_hr_email'] ?? '')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forForm(): array
    {
        $cfg = self::config();

        return [
            'send_hr_on_apply' => !empty($cfg['send_hr_on_apply']),
            'send_applicant_confirmation' => !empty($cfg['send_applicant_confirmation']),
            'send_applicant_status' => !empty($cfg['send_applicant_status']),
            'notify_hr_email' => (string) $cfg['notify_hr_email'],
            'effective_hr_email' => self::notifyHrEmail($cfg),
            'mail_ready' => class_exists('MailSettings') && MailSettings::isConfigured(),
        ];
    }

    /**
     * @param array<string, mixed>|null $cfg
     */
    public static function notifyHrEmail(?array $cfg = null): string
    {
        $cfg ??= self::config();
        $configured = trim((string) ($cfg['notify_hr_email'] ?? ''));
        if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
            return $configured;
        }

        if (class_exists('CompanySettings') && method_exists('CompanySettings', 'mailEmail')) {
            $fallback = trim((string) CompanySettings::mailEmail());
            if ($fallback !== '' && filter_var($fallback, FILTER_VALIDATE_EMAIL)) {
                return $fallback;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function saveFromPost(array $input): void
    {
        $email = trim((string) ($input['recruiting_notify_hr_email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Bitte eine gültige HR-E-Mail-Adresse eingeben oder das Feld leer lassen.');
        }

        SettingsStore::set(self::STORE_KEY, [
            'send_hr_on_apply' => !empty($input['recruiting_send_hr_on_apply']) ? 1 : 0,
            'send_applicant_confirmation' => !empty($input['recruiting_send_applicant_confirmation']) ? 1 : 0,
            'send_applicant_status' => !empty($input['recruiting_send_applicant_status']) ? 1 : 0,
            'notify_hr_email' => $email,
        ]);
    }
}
