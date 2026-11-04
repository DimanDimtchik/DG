<?php
declare(strict_types=1);

/** Access-Token für Instagram Publish (Phase 3-1 Connect). */
final class InstagramTokenService
{
    public static function accessToken(): string
    {
        $platform = OAuthConnectionRepository::PLATFORM_INSTAGRAM;
        $row = OAuthConnectionRepository::findByPlatform($platform);
        if ($row === null) {
            throw new RuntimeException('Instagram ist nicht verbunden.');
        }
        $status = (string) ($row['status'] ?? '');
        if ($status === OAuthConnectionRepository::STATUS_REAUTH) {
            throw new RuntimeException('Instagram erneut verbinden (Sitzung abgelaufen).');
        }
        if ($status !== OAuthConnectionRepository::STATUS_CONNECTED) {
            throw new RuntimeException('Instagram ist nicht verbunden.');
        }

        $access = OAuthConnectionRepository::decryptedAccessToken($platform);
        if ($access === '') {
            OAuthConnectionRepository::upsert($platform, [
                'status' => OAuthConnectionRepository::STATUS_REAUTH,
            ]);
            throw new RuntimeException('Kein Access-Token — Instagram erneut verbinden.');
        }

        $expiresAt = (string) ($row['expires_at'] ?? '');
        $expTs = $expiresAt !== '' ? strtotime($expiresAt) : false;
        if ($expTs !== false && $expTs <= time() + 90) {
            OAuthConnectionRepository::upsert($platform, [
                'status' => OAuthConnectionRepository::STATUS_REAUTH,
            ]);
            throw new RuntimeException('Instagram-Token abgelaufen — bitte erneut verbinden.');
        }

        return $access;
    }

    public static function completeOAuth(string $code): string
    {
        $result = InstagramOAuthClient::completeAuthorization($code);
        $expiresIn = max(60, (int) ($result['expires_in'] ?? 5184000));
        OAuthConnectionRepository::upsert(OAuthConnectionRepository::PLATFORM_INSTAGRAM, [
            'account_id' => $result['account_id'],
            'account_label' => $result['account_label'],
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'expires_at' => date('Y-m-d H:i:s', time() + $expiresIn),
            'scopes' => implode(',', InstagramPublishSettings::scopes()),
            'status' => OAuthConnectionRepository::STATUS_CONNECTED,
            'connected_at' => date('Y-m-d H:i:s'),
            'meta' => $result['meta'],
        ]);

        return 'Instagram verbunden: ' . $result['account_label'];
    }

    public static function disconnect(): void
    {
        OAuthConnectionRepository::clearPlatform(OAuthConnectionRepository::PLATFORM_INSTAGRAM);
    }
}
