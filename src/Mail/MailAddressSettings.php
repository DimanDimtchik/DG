<?php
declare(strict_types=1);

/**
 * Mail Address Settings.
 */
final class MailAddressSettings
{
    public const STORE_KEY = 'mail_address';

    /**
     * Liefert die Standardwerte.
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled' => true,
            'auto_on_contact_create' => true,
            'domain' => '',
            'local_pattern' => '{V1}{TRENNER}{NN}',
            'separator' => '.',
            'preset' => 'v1_dot_nn',
        ];
    }

    /**
     * Liefert die aktuelle Konfiguration.
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        $cfg = SettingsStore::get(self::STORE_KEY, self::defaults());
        $preset = (string) ($cfg['preset'] ?? 'v1_dot_nn');
        if (!isset(MailAddressTokens::presets()[$preset])) {
            $preset = 'v1_dot_nn';
        }

        return [
            'enabled' => !empty($cfg['enabled']),
            'auto_on_contact_create' => !array_key_exists('auto_on_contact_create', $cfg) || !empty($cfg['auto_on_contact_create']),
            'domain' => self::normalizeDomain((string) ($cfg['domain'] ?? '')),
            'local_pattern' => trim((string) ($cfg['local_pattern'] ?? '{V1}{TRENNER}{NN}')) ?: '{V1}{TRENNER}{NN}',
            'separator' => self::normalizeSeparator((string) ($cfg['separator'] ?? '.')),
            'preset' => $preset,
        ];
    }

    /**
     * Methode for form.
     * @return array<string, mixed>
     */
    public static function forForm(): array
    {
        $cfg = self::config();
        $domain = $cfg['domain'];
        if ($domain === '') {
            $domain = self::domainFromCompanyEmail();
        }

        return array_merge($cfg, [
            'effective_domain' => $domain,
            'presets' => MailAddressTokens::presetLabels(),
            'token_groups' => MailAddressTokens::referenceGroups(),
        ]);
    }

    /**
     * Methode effective domain.
     * @return string
     */
    public static function effectiveDomain(): string
    {
        $domain = self::config()['domain'];
        if ($domain !== '') {
            return $domain;
        }

        return self::domainFromCompanyEmail();
    }

    /**
     * Methode domain from company email.
     * @return string
     */
    public static function domainFromCompanyEmail(): string
    {
        $email = CompanySettings::mailEmail();
        if ($email === '') {
            return '';
        }

        $parts = explode('@', $email, 2);

        return self::normalizeDomain($parts[1] ?? '');
    }

    /**
     * Methode save.
     * @param array $input
     * @return void
     */
    public static function save(array $input): void
    {
        $preset = trim((string) ($input['preset'] ?? 'v1_dot_nn'));
        if ($preset !== '' && isset(MailAddressTokens::presets()[$preset])) {
            $localPattern = MailAddressTokens::presets()[$preset];
        } else {
            $localPattern = trim((string) ($input['local_pattern'] ?? '{V1}{TRENNER}{NN}'));
        }

        SettingsStore::set(self::STORE_KEY, [
            'enabled' => !empty($input['enabled']),
            'auto_on_contact_create' => !empty($input['auto_on_contact_create']),
            'domain' => self::normalizeDomain(trim((string) ($input['domain'] ?? ''))),
            'local_pattern' => $localPattern !== '' ? $localPattern : '{V1}{TRENNER}{NN}',
            'separator' => self::normalizeSeparator(trim((string) ($input['separator'] ?? '.'))),
            'preset' => isset(MailAddressTokens::presets()[$preset]) ? $preset : 'custom',
        ]);
    }

    /**
     * Führt aus: normalize domain.
     * @param string $domain
     * @return string
     */
    private static function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('/^@+/', '', $domain) ?? $domain;

        return $domain;
    }

    /**
     * Führt aus: normalize separator.
     * @param string $separator
     * @return string
     */
    private static function normalizeSeparator(string $separator): string
    {
        $separator = trim($separator);
        if ($separator === '') {
            return '.';
        }
        if (strlen($separator) > 3) {
            return '.';
        }

        return $separator;
    }
}
