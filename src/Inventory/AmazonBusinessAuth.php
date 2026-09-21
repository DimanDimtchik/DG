<?php
declare(strict_types=1);

/** Login with Amazon — Access-Token aus Refresh-Token. */
final class AmazonBusinessAuth
{
    private const TOKEN_URL = 'https://api.amazon.com/auth/o2/token';

    /**
     * Gültigen Access-Token liefern (Cache in Settings, sonst Refresh).
     *
     * @throws RuntimeException
     */
    public static function accessToken(): string
    {
        $cfg = AmazonBusinessSettings::config();
        if (!AmazonBusinessSettings::isReady($cfg)) {
            throw new RuntimeException('Amazon Business ist nicht konfiguriert (Client-ID, Secret, Refresh-Token).');
        }

        $cached = trim((string) ($cfg['access_token'] ?? ''));
        $expiresAt = (int) ($cfg['access_token_expires_at'] ?? 0);
        if ($cached !== '' && $expiresAt > time() + 60) {
            return $cached;
        }

        return self::refresh($cfg);
    }

    /**
     * @param array<string, mixed> $cfg
     * @throws RuntimeException
     */
    private static function refresh(array $cfg): string
    {
        $body = http_build_query([
            'grant_type' => 'refresh_token',
            'refresh_token' => (string) $cfg['refresh_token'],
            'client_id' => (string) $cfg['client_id'],
            'client_secret' => (string) $cfg['client_secret'],
        ]);

        $raw = self::httpPost(self::TOKEN_URL, $body, [
            'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
        ]);
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['access_token'])) {
            $hint = is_array($data) ? (string) ($data['error_description'] ?? $data['error'] ?? '') : '';
            throw new RuntimeException(
                'Amazon-Token konnte nicht erneuert werden.'
                . ($hint !== '' ? ' ' . $hint : '')
            );
        }

        $accessToken = (string) $data['access_token'];
        $expiresIn = max(60, (int) ($data['expires_in'] ?? 3600));
        AmazonBusinessSettings::storeAccessToken($accessToken, time() + $expiresIn);

        return $accessToken;
    }

    /**
     * Verbindungstest: Token holen.
     *
     * @return array{ok: bool, message: string}
     */
    public static function testConnection(): array
    {
        try {
            self::accessToken();

            return ['ok' => true, 'message' => 'Verbindung OK — Access-Token erhalten.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param list<string> $headers
     */
    private static function httpPost(string $url, string $body, array $headers): string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL ist auf dem Server nicht verfügbar.');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('cURL-Init fehlgeschlagen.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || !is_string($raw)) {
            throw new RuntimeException('Amazon-Auth-HTTP-Fehler: ' . ($error !== '' ? $error : 'unbekannt'));
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Amazon-Auth HTTP ' . $status . ': ' . mb_substr($raw, 0, 300));
        }

        return $raw;
    }
}
