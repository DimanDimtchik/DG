<?php
declare(strict_types=1);

/** CTI: Anbieter (zuerst Fritzbox), Softphone-Wahl, Bridge-Token. */
final class CtiSettings
{
    public const STORE_KEY = 'cti';

    /** @return array{enabled: bool, provider: string, dial_scheme: string, bridge_token: string, fritz_host: string} */
    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'provider' => 'fritz',
            'dial_scheme' => 'sip',
            'bridge_token' => '',
            'fritz_host' => 'fritz.box',
        ];
    }

    /** @return array{enabled: bool, provider: string, dial_scheme: string, bridge_token: string, fritz_host: string} */
    public static function config(): array
    {
        $defaults = self::defaults();
        if (!class_exists('SettingsStore') || !Database::isConfigured()) {
            return $defaults;
        }
        try {
            $stored = SettingsStore::get(self::STORE_KEY, []);
        } catch (Throwable) {
            return $defaults;
        }
        if (!is_array($stored)) {
            return $defaults;
        }
        $provider = (string) ($stored['provider'] ?? $defaults['provider']);
        if (!in_array($provider, ['fritz', 'none'], true)) {
            $provider = 'fritz';
        }
        $scheme = (string) ($stored['dial_scheme'] ?? $defaults['dial_scheme']);
        if (!in_array($scheme, ['sip', 'tel'], true)) {
            $scheme = 'sip';
        }
        $token = trim((string) ($stored['bridge_token'] ?? ''));
        $host = trim((string) ($stored['fritz_host'] ?? $defaults['fritz_host']));
        if ($host === '') {
            $host = $defaults['fritz_host'];
        }

        return [
            'enabled' => !empty($stored['enabled']),
            'provider' => $provider,
            'dial_scheme' => $scheme,
            'bridge_token' => $token,
            'fritz_host' => $host,
        ];
    }

    public static function isEnabled(): bool
    {
        $c = self::config();

        return $c['enabled'] && $c['provider'] !== 'none';
    }

    /** @param array<string, mixed> $input */
    public static function saveFromPost(array $input): void
    {
        $current = self::config();
        $token = trim((string) ($input['bridge_token'] ?? $current['bridge_token']));
        if ($token === '' || !empty($input['bridge_token_rotate'])) {
            $token = bin2hex(random_bytes(16));
        }
        $provider = (string) ($input['provider'] ?? 'fritz');
        if (!in_array($provider, ['fritz', 'none'], true)) {
            $provider = 'fritz';
        }
        $scheme = (string) ($input['dial_scheme'] ?? 'sip');
        if (!in_array($scheme, ['sip', 'tel'], true)) {
            $scheme = 'sip';
        }
        $host = trim((string) ($input['fritz_host'] ?? 'fritz.box'));
        if ($host === '') {
            $host = 'fritz.box';
        }
        SettingsStore::set(self::STORE_KEY, [
            'enabled' => !empty($input['enabled']),
            'provider' => $provider,
            'dial_scheme' => $scheme,
            'bridge_token' => $token,
            'fritz_host' => $host,
        ]);
    }

    public static function dialHref(string $e164OrRaw): string
    {
        $e164 = PhoneNormalizer::toE164($e164OrRaw) ?? PhoneNormalizer::normalize($e164OrRaw);
        if ($e164 === '') {
            return '';
        }
        $scheme = self::config()['dial_scheme'];
        if ($scheme === 'tel') {
            return 'tel:' . $e164;
        }

        return 'sip:' . ltrim($e164, '+');
    }
}
