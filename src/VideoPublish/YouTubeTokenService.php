<?php
declare(strict_types=1);

/** Access-Token mit Offline-Refresh für YouTube. */
final class YouTubeTokenService
{
    public static function accessToken(): string
    {
        $platform = OAuthConnectionRepository::PLATFORM_YOUTUBE;
        $row = OAuthConnectionRepository::findByPlatform($platform);
        if ($row === null) {
            throw new RuntimeException('YouTube ist nicht verbunden.');
        }
        $status = (string) ($row['status'] ?? '');
        if ($status === OAuthConnectionRepository::STATUS_REAUTH) {
            throw new RuntimeException('YouTube erneut verbinden (Sitzung abgelaufen).');
        }
        if ($status !== OAuthConnectionRepository::STATUS_CONNECTED) {
            throw new RuntimeException('YouTube ist nicht verbunden.');
        }

        $expiresAt = (string) ($row['expires_at'] ?? '');
        $access = OAuthConnectionRepository::decryptedAccessToken($platform);
        $skew = time() + 90;
        $expTs = $expiresAt !== '' ? strtotime($expiresAt) : false;
        if ($access !== '' && $expTs !== false && $expTs > $skew) {
            return $access;
        }

        $refresh = OAuthConnectionRepository::decryptedRefreshToken($platform);
        if ($refresh === '') {
            OAuthConnectionRepository::upsert($platform, [
                'status' => OAuthConnectionRepository::STATUS_REAUTH,
            ]);
            throw new RuntimeException('Kein Refresh-Token — YouTube erneut verbinden.');
        }

        try {
            $tokens = YouTubeOAuthClient::refresh($refresh);
        } catch (Throwable $e) {
            OAuthConnectionRepository::upsert($platform, [
                'status' => OAuthConnectionRepository::STATUS_REAUTH,
            ]);
            throw new RuntimeException('Token-Refresh fehlgeschlagen: ' . $e->getMessage(), 0, $e);
        }

        $expiresIn = max(60, (int) ($tokens['expires_in'] ?? 3600));
        OAuthConnectionRepository::upsert($platform, [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] !== '' ? $tokens['refresh_token'] : $refresh,
            'expires_at' => date('Y-m-d H:i:s', time() + $expiresIn),
            'status' => OAuthConnectionRepository::STATUS_CONNECTED,
            'scopes' => $tokens['scope'] !== '' ? $tokens['scope'] : (string) ($row['scopes'] ?? ''),
        ]);

        return $tokens['access_token'];
    }

    public static function completeOAuth(string $code): void
    {
        $tokens = YouTubeOAuthClient::exchangeCode($code);
        if ($tokens['refresh_token'] === '') {
            $existing = OAuthConnectionRepository::decryptedRefreshToken(OAuthConnectionRepository::PLATFORM_YOUTUBE);
            if ($existing === '') {
                throw new RuntimeException(
                    'Google lieferte keinen Refresh-Token. Bitte in den Einstellungen trennen und mit Zustimmung erneut verbinden.'
                );
            }
            $tokens['refresh_token'] = $existing;
        }
        $channel = YouTubeOAuthClient::fetchMyChannel($tokens['access_token']);
        $expiresIn = max(60, (int) ($tokens['expires_in'] ?? 3600));
        OAuthConnectionRepository::upsert(OAuthConnectionRepository::PLATFORM_YOUTUBE, [
            'account_id' => $channel['channel_id'],
            'account_label' => $channel['title'],
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_at' => date('Y-m-d H:i:s', time() + $expiresIn),
            'scopes' => $tokens['scope'],
            'status' => OAuthConnectionRepository::STATUS_CONNECTED,
            'connected_at' => date('Y-m-d H:i:s'),
            'meta' => ['token_type' => $tokens['token_type']],
        ]);
    }

    public static function disconnect(): void
    {
        OAuthConnectionRepository::clearPlatform(OAuthConnectionRepository::PLATFORM_YOUTUBE);
    }
}
