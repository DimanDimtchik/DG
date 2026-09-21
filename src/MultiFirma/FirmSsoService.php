<?php
declare(strict_types=1);

/**
 * Multi-Firma MF5b: Handoff-Token ausstellen/prüfen (Spec §13 MF5a).
 *
 * Payload-Feldreihenfolge fest: v, iss, aud, sub, iat, exp, jti, uid
 * Kodierung: Base64URL(JSON) . Base64URL(HMAC-SHA256)
 */
final class FirmSsoService
{
    private const STORE_KEY = 'firm_sso_jti';
    private const VERSION = 1;
    private const DEFAULT_TTL = 60;
    private const SKEW = 10;
    private const MIN_SECRET_LEN = 32;

    /** @var array<string, mixed>|null */
    private static ?array $configCache = null;

    public static function isEnabled(): bool
    {
        $secret = self::sharedSecret();

        return $secret !== null && strlen($secret) >= self::MIN_SECRET_LEN;
    }

    /**
     * @return array{shared_secret: string, ttl_seconds: int, allowed_domains: list<string>}|null
     */
    public static function config(): ?array
    {
        if (self::$configCache !== null) {
            return self::$configCache === [] ? null : self::$configCache;
        }
        $file = DG_ROOT . '/config/firm-sso.local.php';
        if (!is_readable($file)) {
            self::$configCache = [];

            return null;
        }
        /** @var mixed $raw */
        $raw = require $file;
        if (!is_array($raw)) {
            self::$configCache = [];

            return null;
        }
        $secret = (string) ($raw['shared_secret'] ?? '');
        $ttl = (int) ($raw['ttl_seconds'] ?? self::DEFAULT_TTL);
        if ($ttl < 15 || $ttl > 300) {
            $ttl = self::DEFAULT_TTL;
        }
        $allowed = [];
        if (is_array($raw['allowed_domains'] ?? null)) {
            foreach ($raw['allowed_domains'] as $d) {
                $n = FirmSwitcherService::normalizeHost((string) $d);
                if ($n !== '') {
                    $allowed[] = $n;
                }
            }
        }
        self::$configCache = [
            'shared_secret' => $secret,
            'ttl_seconds' => $ttl,
            'allowed_domains' => $allowed,
        ];

        return self::$configCache;
    }

    public static function sharedSecret(): ?string
    {
        $cfg = self::config();
        if ($cfg === null) {
            return null;
        }
        $secret = $cfg['shared_secret'];

        return strlen($secret) >= self::MIN_SECRET_LEN ? $secret : null;
    }

    /**
     * Stellt Token aus und liefert Ziel-URL inkl. firm_sso (oder null).
     */
    public static function issueRedirectUrl(User $user, string $targetDomain): ?string
    {
        if (!self::isEnabled()) {
            return null;
        }
        if (class_exists('SupportSession') && SupportSession::isActive()) {
            return null;
        }

        $iss = FirmSwitcherService::normalizeHost((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $aud = FirmSwitcherService::normalizeHost($targetDomain);
        if ($iss === '' || $aud === '' || $iss === $aud) {
            return null;
        }
        if (!self::domainAllowed($iss) || !self::domainAllowed($aud)) {
            return null;
        }

        $sub = strtolower(trim($user->email));
        if ($sub === '' || !filter_var($sub, FILTER_VALIDATE_EMAIL)) {
            $sub = trim($user->username);
        }
        if ($sub === '') {
            return null;
        }

        $ttl = self::config()['ttl_seconds'] ?? self::DEFAULT_TTL;
        $now = time();
        $payload = [
            'v' => self::VERSION,
            'iss' => $iss,
            'aud' => $aud,
            'sub' => $sub,
            'iat' => $now,
            'exp' => $now + $ttl,
            'jti' => bin2hex(random_bytes(16)),
            'uid' => $user->id > 0 ? $user->id : null,
        ];
        $token = self::encode($payload);
        if ($token === null) {
            return null;
        }

        return 'https://' . $aud . '/login?firm_sso=' . rawurlencode($token);
    }

    /**
     * Prüft Token für aktuelle Instanz.
     *
     * @return array{ok: true, user: User}|array{ok: false, message: string}
     */
    public static function consume(string $token): array
    {
        $ip = class_exists('Firewall') ? Firewall::clientIp() : '0.0.0.0';
        if (!self::isEnabled()) {
            return ['ok' => false, 'message' => 'Firmenwechsel abgelaufen oder ungültig.'];
        }
        if (class_exists('SupportSession') && SupportSession::isActive()) {
            return ['ok' => false, 'message' => 'Firmenwechsel abgelaufen oder ungültig.'];
        }

        $throttleMsg = LoginThrottle::check($ip);
        if ($throttleMsg !== null) {
            return ['ok' => false, 'message' => $throttleMsg];
        }

        $payload = self::decodeAndVerify($token);
        if ($payload === null) {
            LoginThrottle::recordFailure($ip, 'firm_sso');

            return ['ok' => false, 'message' => 'Firmenwechsel abgelaufen oder ungültig.'];
        }

        $host = FirmSwitcherService::normalizeHost((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $aud = (string) ($payload['aud'] ?? '');
        $iss = (string) ($payload['iss'] ?? '');
        if ($host === '' || $aud !== $host || $iss === $aud) {
            LoginThrottle::recordFailure($ip, 'firm_sso');

            return ['ok' => false, 'message' => 'Firmenwechsel abgelaufen oder ungültig.'];
        }
        if (!self::domainAllowed($iss) || !self::domainAllowed($aud)) {
            LoginThrottle::recordFailure($ip, 'firm_sso');

            return ['ok' => false, 'message' => 'Firmenwechsel abgelaufen oder ungültig.'];
        }

        $jti = (string) ($payload['jti'] ?? '');
        if ($jti === '' || self::jtiSeen($jti)) {
            LoginThrottle::recordFailure($ip, 'firm_sso');

            return ['ok' => false, 'message' => 'Firmenwechsel abgelaufen oder ungültig.'];
        }

        $sub = trim((string) ($payload['sub'] ?? ''));
        if ($sub === '') {
            LoginThrottle::recordFailure($ip, 'firm_sso');

            return ['ok' => false, 'message' => 'Firmenwechsel abgelaufen oder ungültig.'];
        }

        $user = UserRepository::findByEmailOrUsername($sub);
        if ($user === null) {
            LoginThrottle::recordFailure($ip, $sub);

            return ['ok' => false, 'message' => 'Firmenwechsel abgelaufen oder ungültig.'];
        }

        self::markJti($jti, (int) ($payload['exp'] ?? time()));
        LoginThrottle::recordSuccess($ip, $sub);

        return ['ok' => true, 'user' => $user];
    }

    public static function domainAllowed(string $domain): bool
    {
        $domain = FirmSwitcherService::normalizeHost($domain);
        if ($domain === '') {
            return false;
        }
        $known = FirmSwitcherService::siblingDomains();
        if (!in_array($domain, $known, true)) {
            return false;
        }
        $cfg = self::config();
        $extra = ($cfg !== null && is_array($cfg['allowed_domains'] ?? null))
            ? $cfg['allowed_domains']
            : [];
        if ($extra !== [] && !in_array($domain, $extra, true)) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function encode(array $payload): ?string
    {
        $secret = self::sharedSecret();
        if ($secret === null) {
            return null;
        }
        // Feste Feldreihenfolge laut Spec MF5a/MF5b
        $ordered = [
            'v' => (int) ($payload['v'] ?? self::VERSION),
            'iss' => (string) ($payload['iss'] ?? ''),
            'aud' => (string) ($payload['aud'] ?? ''),
            'sub' => (string) ($payload['sub'] ?? ''),
            'iat' => (int) ($payload['iat'] ?? 0),
            'exp' => (int) ($payload['exp'] ?? 0),
            'jti' => (string) ($payload['jti'] ?? ''),
            'uid' => $payload['uid'] ?? null,
        ];
        $json = json_encode($ordered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return null;
        }
        $payloadB64 = self::b64urlEncode($json);
        $sig = hash_hmac('sha256', $payloadB64, $secret, true);

        return $payloadB64 . '.' . self::b64urlEncode($sig);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decodeAndVerify(string $token): ?array
    {
        $secret = self::sharedSecret();
        if ($secret === null) {
            return null;
        }
        $token = trim($token);
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }
        [$payloadB64, $sigB64] = $parts;
        $expected = self::b64urlEncode(hash_hmac('sha256', $payloadB64, $secret, true));
        if (!hash_equals($expected, $sigB64)) {
            return null;
        }
        $json = self::b64urlDecode($payloadB64);
        if ($json === null) {
            return null;
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }
        if ((int) ($data['v'] ?? 0) !== self::VERSION) {
            return null;
        }
        $iat = (int) ($data['iat'] ?? 0);
        $exp = (int) ($data['exp'] ?? 0);
        $now = time();
        if ($iat <= 0 || $exp <= 0) {
            return null;
        }
        if ($now < $iat - self::SKEW || $now > $exp + self::SKEW) {
            return null;
        }

        return $data;
    }

    private static function b64urlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $b64): ?string
    {
        $pad = 4 - (strlen($b64) % 4);
        if ($pad < 4) {
            $b64 .= str_repeat('=', $pad);
        }
        $raw = base64_decode(strtr($b64, '-_', '+/'), true);

        return $raw === false ? null : $raw;
    }

    private static function jtiSeen(string $jti): bool
    {
        if (!class_exists('SettingsStore') || !Database::isConfigured()) {
            return false;
        }
        self::purgeJti();
        $store = SettingsStore::get(self::STORE_KEY, ['used' => []]);
        $used = is_array($store['used'] ?? null) ? $store['used'] : [];

        return isset($used[$jti]);
    }

    private static function markJti(string $jti, int $exp): void
    {
        if (!class_exists('SettingsStore') || !Database::isConfigured()) {
            return;
        }
        self::purgeJti();
        $store = SettingsStore::get(self::STORE_KEY, ['used' => []]);
        $used = is_array($store['used'] ?? null) ? $store['used'] : [];
        $used[$jti] = max($exp, time()) + 86400;
        // Cap size
        if (count($used) > 500) {
            asort($used);
            $used = array_slice($used, -400, null, true);
        }
        SettingsStore::set(self::STORE_KEY, ['used' => $used]);
    }

    private static function purgeJti(): void
    {
        if (!class_exists('SettingsStore') || !Database::isConfigured()) {
            return;
        }
        $store = SettingsStore::get(self::STORE_KEY, ['used' => []]);
        $used = is_array($store['used'] ?? null) ? $store['used'] : [];
        $now = time();
        $changed = false;
        foreach ($used as $id => $until) {
            if ((int) $until < $now) {
                unset($used[$id]);
                $changed = true;
            }
        }
        if ($changed) {
            SettingsStore::set(self::STORE_KEY, ['used' => $used]);
        }
    }
}
