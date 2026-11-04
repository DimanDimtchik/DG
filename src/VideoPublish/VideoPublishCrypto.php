<?php
declare(strict_types=1);

/** AES-256-CBC Token-/Secret-Verschlüsselung für Video-Publish OAuth. */
final class VideoPublishCrypto
{
    public static function encrypt(string $plain): string
    {
        if ($plain === '') {
            return '';
        }
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', self::cryptoKey(), OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new RuntimeException('Verschlüsselung fehlgeschlagen.');
        }

        return 'v1:' . base64_encode($iv . $cipher);
    }

    public static function decrypt(string $payload): string
    {
        if ($payload === '' || !str_starts_with($payload, 'v1:')) {
            return '';
        }
        $raw = base64_decode(substr($payload, 3), true);
        if ($raw === false || strlen($raw) < 17) {
            return '';
        }
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', self::cryptoKey(), OPENSSL_RAW_DATA, $iv);

        return is_string($plain) ? $plain : '';
    }

    private static function cryptoKey(): string
    {
        $seed = defined('DG_ROOT') ? (string) DG_ROOT : __DIR__;

        return hash('sha256', $seed . '|video-publish-oauth', true);
    }
}
