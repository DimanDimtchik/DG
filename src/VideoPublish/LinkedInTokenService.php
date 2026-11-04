<?php
declare(strict_types=1);

/** Access-Token für LinkedIn Video-Publish (Member-OAuth). */
final class LinkedInTokenService
{
    public static function accessToken(): string
    {
        $platform = OAuthConnectionRepository::PLATFORM_LINKEDIN;
        $row = OAuthConnectionRepository::findByPlatform($platform);
        if ($row === null) {
            throw new RuntimeException('LinkedIn ist nicht verbunden.');
        }
        $status = (string) ($row['status'] ?? '');
        if ($status === OAuthConnectionRepository::STATUS_REAUTH) {
            throw new RuntimeException('LinkedIn erneut verbinden (Sitzung abgelaufen).');
        }
        if ($status !== OAuthConnectionRepository::STATUS_CONNECTED) {
            throw new RuntimeException('LinkedIn ist nicht verbunden.');
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
            throw new RuntimeException(
                'Kein Refresh-Token (LinkedIn oft nur ~60 Tage Access). Bitte erneut verbinden.'
            );
        }

        try {
            $tokens = LinkedInOAuthClient::refresh($refresh);
        } catch (Throwable $e) {
            OAuthConnectionRepository::upsert($platform, [
                'status' => OAuthConnectionRepository::STATUS_REAUTH,
            ]);
            throw new RuntimeException('Token-Refresh fehlgeschlagen: ' . $e->getMessage(), 0, $e);
        }

        $expiresIn = max(60, (int) ($tokens['expires_in'] ?? 5184000));
        OAuthConnectionRepository::upsert($platform, [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] !== '' ? $tokens['refresh_token'] : $refresh,
            'expires_at' => date('Y-m-d H:i:s', time() + $expiresIn),
            'status' => OAuthConnectionRepository::STATUS_CONNECTED,
            'scopes' => $tokens['scope'] !== '' ? $tokens['scope'] : (string) ($row['scopes'] ?? ''),
        ]);

        return $tokens['access_token'];
    }

    public static function completeOAuth(string $code): string
    {
        $tokens = LinkedInOAuthClient::exchangeCode($code);
        $member = LinkedInOAuthClient::fetchMemberProfile($tokens['access_token']);
        $cfg = LinkedInPublishSettings::config();
        $orgCheck = LinkedInOAuthClient::verifyOrganizationAdmin(
            $tokens['access_token'],
            (string) ($cfg['organization_id'] ?? '')
        );
        if (empty($orgCheck['ok'])) {
            throw new RuntimeException((string) $orgCheck['message']);
        }

        $expiresIn = max(60, (int) ($tokens['expires_in'] ?? 5184000));
        $meta = [
            'token_type' => $tokens['token_type'],
            'organization_id' => (string) ($cfg['organization_id'] ?? ''),
            'organization_urn' => LinkedInPublishSettings::organizationUrn($cfg),
            'org_check' => (string) ($orgCheck['message'] ?? ''),
        ];
        // Ohne Refresh markieren wir connected trotzdem — Re-Auth vor Ablauf nötig.
        $hasRefresh = $tokens['refresh_token'] !== '';
        OAuthConnectionRepository::upsert(OAuthConnectionRepository::PLATFORM_LINKEDIN, [
            'account_id' => $member['member_id'],
            'account_label' => $member['name'],
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_at' => date('Y-m-d H:i:s', time() + $expiresIn),
            'scopes' => $tokens['scope'] !== '' ? $tokens['scope'] : implode(' ', LinkedInPublishSettings::scopes()),
            'status' => OAuthConnectionRepository::STATUS_CONNECTED,
            'connected_at' => date('Y-m-d H:i:s'),
            'meta' => $meta,
        ]);

        $msg = 'LinkedIn verbunden: ' . $member['name'];
        if (!$hasRefresh) {
            $msg .= ' (kein Refresh-Token — vor Ablauf erneut verbinden).';
        }
        if (($orgCheck['message'] ?? '') !== '') {
            $msg .= ' · ' . $orgCheck['message'];
        }

        return $msg;
    }

    public static function disconnect(): void
    {
        OAuthConnectionRepository::clearPlatform(OAuthConnectionRepository::PLATFORM_LINKEDIN);
    }
}
