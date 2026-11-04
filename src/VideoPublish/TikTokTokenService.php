<?php
declare(strict_types=1);

/** Access-/Refresh-Token für TikTok Publish (Phase 4-1). Refresh-Rotation speichern. */
final class TikTokTokenService
{
    public static function accessToken(): string
    {
        $platform = OAuthConnectionRepository::PLATFORM_TIKTOK;
        $row = OAuthConnectionRepository::findByPlatform($platform);
        if ($row === null) {
            throw new RuntimeException('TikTok ist nicht verbunden.');
        }
        $status = (string) ($row['status'] ?? '');
        if ($status === OAuthConnectionRepository::STATUS_REAUTH) {
            throw new RuntimeException('TikTok erneut verbinden (Sitzung abgelaufen).');
        }
        if ($status !== OAuthConnectionRepository::STATUS_CONNECTED) {
            throw new RuntimeException('TikTok ist nicht verbunden.');
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
            throw new RuntimeException('Kein Refresh-Token — TikTok erneut verbinden.');
        }

        try {
            $tokens = TikTokOAuthClient::refresh($refresh);
        } catch (Throwable $e) {
            OAuthConnectionRepository::upsert($platform, [
                'status' => OAuthConnectionRepository::STATUS_REAUTH,
            ]);
            throw new RuntimeException('TikTok Token-Refresh fehlgeschlagen: ' . $e->getMessage(), 0, $e);
        }

        $expiresIn = max(60, (int) ($tokens['expires_in'] ?? 86400));
        $newRefresh = (string) ($tokens['refresh_token'] ?? '');
        // Rotation: neues Refresh-Token immer persistieren, wenn geliefert.
        OAuthConnectionRepository::upsert($platform, [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $newRefresh !== '' ? $newRefresh : $refresh,
            'expires_at' => date('Y-m-d H:i:s', time() + $expiresIn),
            'status' => OAuthConnectionRepository::STATUS_CONNECTED,
            'scopes' => $tokens['scope'] !== '' ? $tokens['scope'] : (string) ($row['scopes'] ?? ''),
            'meta' => array_merge(
                is_array($row['meta'] ?? null) ? $row['meta'] : [],
                [
                    'refresh_expires_in' => (int) ($tokens['refresh_expires_in'] ?? 0),
                    'last_refresh_at' => date('Y-m-d H:i:s'),
                ]
            ),
        ]);

        return $tokens['access_token'];
    }

    public static function completeOAuth(string $code): string
    {
        $tokens = TikTokOAuthClient::exchangeCode($code);
        $openId = (string) ($tokens['open_id'] ?? '');
        $label = $openId;
        $avatar = '';
        try {
            $profile = TikTokOAuthClient::fetchCreatorProfile($tokens['access_token']);
            $openId = $profile['open_id'] !== '' ? $profile['open_id'] : $openId;
            $label = $profile['display_name'];
            $avatar = $profile['avatar_url'];
        } catch (Throwable) {
            // open_id aus Token reicht für Connect; Profil optional.
        }
        if ($openId === '') {
            throw new RuntimeException('TikTok OAuth ohne open_id.');
        }

        $expiresIn = max(60, (int) ($tokens['expires_in'] ?? 86400));
        $cfg = TikTokPublishSettings::config();
        $hasRefresh = ($tokens['refresh_token'] ?? '') !== '';
        OAuthConnectionRepository::upsert(OAuthConnectionRepository::PLATFORM_TIKTOK, [
            'account_id' => $openId,
            'account_label' => $label,
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_at' => date('Y-m-d H:i:s', time() + $expiresIn),
            'scopes' => $tokens['scope'] !== ''
                ? $tokens['scope']
                : implode(',', TikTokPublishSettings::scopes($cfg)),
            'status' => OAuthConnectionRepository::STATUS_CONNECTED,
            'connected_at' => date('Y-m-d H:i:s'),
            'meta' => [
                'token_type' => $tokens['token_type'],
                'refresh_expires_in' => (int) ($tokens['refresh_expires_in'] ?? 0),
                'posting_mode' => (string) ($cfg['posting_mode'] ?? TikTokPublishSettings::MODE_INBOX),
                'audit_ok' => !empty($cfg['audit_ok']),
                'avatar_url' => $avatar,
            ],
        ]);

        $msg = 'TikTok verbunden: ' . $label;
        if (!$hasRefresh) {
            $msg .= ' (kein Refresh-Token — vor Ablauf erneut verbinden).';
        }
        $mode = (string) ($cfg['posting_mode'] ?? TikTokPublishSettings::MODE_INBOX);
        $msg .= $mode === TikTokPublishSettings::MODE_DIRECT
            ? ' · Direct Post'
            : ' · Inbox/Draft';

        return $msg;
    }

    public static function disconnect(): void
    {
        OAuthConnectionRepository::clearPlatform(OAuthConnectionRepository::PLATFORM_TIKTOK);
    }
}
