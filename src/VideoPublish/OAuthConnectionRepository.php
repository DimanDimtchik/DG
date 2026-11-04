<?php
declare(strict_types=1);

final class OAuthConnectionRepository
{
    public const PLATFORM_YOUTUBE = 'youtube';

    public const STATUS_DISCONNECTED = 'disconnected';
    public const STATUS_CONNECTED = 'connected';
    public const STATUS_REAUTH = 'reauth_required';

    public static function ensureReady(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
    }

    public static function tableReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            Database::pdo()->query('SELECT 1 FROM dg_oauth_connections LIMIT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByPlatform(string $platform): ?array
    {
        self::ensureReady();
        if (!self::tableReady()) {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_oauth_connections WHERE platform = :p LIMIT 1'
        );
        $stmt->execute(['p' => $platform]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function upsert(string $platform, array $data): void
    {
        self::ensureReady();
        if (!self::tableReady()) {
            throw new RuntimeException('OAuth-Tabelle fehlt — Migration 138 ausführen.');
        }
        $existing = self::findByPlatform($platform);
        $access = array_key_exists('access_token', $data)
            ? VideoPublishCrypto::encrypt((string) $data['access_token'])
            : (string) ($existing['access_token_enc'] ?? '');
        $refresh = array_key_exists('refresh_token', $data)
            ? ((string) $data['refresh_token'] !== ''
                ? VideoPublishCrypto::encrypt((string) $data['refresh_token'])
                : (string) ($existing['refresh_token_enc'] ?? ''))
            : (string) ($existing['refresh_token_enc'] ?? '');

        $meta = $data['meta'] ?? null;
        $metaJson = null;
        if (is_array($meta)) {
            $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);
        } elseif (array_key_exists('meta_json', $data)) {
            $metaJson = $data['meta_json'];
        } else {
            $metaJson = $existing['meta_json'] ?? null;
        }

        $params = [
            'platform' => $platform,
            'account_id' => mb_substr((string) ($data['account_id'] ?? ($existing['account_id'] ?? '')), 0, 191),
            'account_label' => mb_substr((string) ($data['account_label'] ?? ($existing['account_label'] ?? '')), 0, 255),
            'access_token_enc' => $access !== '' ? $access : null,
            'refresh_token_enc' => $refresh !== '' ? $refresh : null,
            'expires_at' => $data['expires_at'] ?? ($existing['expires_at'] ?? null),
            'scopes' => mb_substr((string) ($data['scopes'] ?? ($existing['scopes'] ?? '')), 0, 500),
            'status' => (string) ($data['status'] ?? ($existing['status'] ?? self::STATUS_DISCONNECTED)),
            'meta_json' => is_string($metaJson) ? $metaJson : null,
            'connected_at' => $data['connected_at'] ?? ($existing['connected_at'] ?? null),
        ];

        if ($existing === null) {
            $stmt = Database::pdo()->prepare(
                'INSERT INTO dg_oauth_connections (
                    platform, account_id, account_label, access_token_enc, refresh_token_enc,
                    expires_at, scopes, status, meta_json, connected_at
                 ) VALUES (
                    :platform, :account_id, :account_label, :access_token_enc, :refresh_token_enc,
                    :expires_at, :scopes, :status, :meta_json, :connected_at
                 )'
            );
            $stmt->execute($params);

            return;
        }

        $params['id'] = (int) $existing['id'];
        $stmt = Database::pdo()->prepare(
            'UPDATE dg_oauth_connections SET
                account_id = :account_id,
                account_label = :account_label,
                access_token_enc = :access_token_enc,
                refresh_token_enc = :refresh_token_enc,
                expires_at = :expires_at,
                scopes = :scopes,
                status = :status,
                meta_json = :meta_json,
                connected_at = :connected_at
             WHERE id = :id'
        );
        unset($params['platform']);
        $stmt->execute($params);
    }

    public static function clearPlatform(string $platform): void
    {
        self::ensureReady();
        if (!self::tableReady()) {
            return;
        }
        Database::pdo()->prepare('DELETE FROM dg_oauth_connections WHERE platform = :p')->execute(['p' => $platform]);
    }

    public static function decryptedAccessToken(string $platform): string
    {
        $row = self::findByPlatform($platform);
        if ($row === null) {
            return '';
        }

        return VideoPublishCrypto::decrypt((string) ($row['access_token_enc'] ?? ''));
    }

    public static function decryptedRefreshToken(string $platform): string
    {
        $row = self::findByPlatform($platform);
        if ($row === null) {
            return '';
        }

        return VideoPublishCrypto::decrypt((string) ($row['refresh_token_enc'] ?? ''));
    }

    public static function isConnected(string $platform): bool
    {
        $row = self::findByPlatform($platform);

        return $row !== null
            && (string) ($row['status'] ?? '') === self::STATUS_CONNECTED
            && self::decryptedRefreshToken($platform) !== '';
    }
}
