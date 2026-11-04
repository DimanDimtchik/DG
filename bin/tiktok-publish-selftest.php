<?php
declare(strict_types=1);

/**
 * TikTok Publish Phase 4-1: Settings, OAuth-URL (ohne echte TikTok-Calls).
 * php bin/tiktok-publish-selftest.php
 */

final class Database
{
    public static PDO $pdo;

    public static function isConfigured(): bool
    {
        return true;
    }

    public static function pdo(): PDO
    {
        return self::$pdo;
    }
}

final class MigrationRunner
{
    public static function runPending(): void
    {
    }
}

final class App
{
    public static function publicBaseUrl(): string
    {
        return 'https://crm.example.test';
    }
}

final class SettingsStore
{
    /** @var array<string, array<string, mixed>> */
    public static array $data = [];

    public static function get(string $key, array $defaults = []): array
    {
        return array_merge($defaults, self::$data[$key] ?? []);
    }

    public static function set(string $key, array $value): void
    {
        self::$data[$key] = $value;
    }
}

if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}

require DG_ROOT . '/src/VideoPublish/VideoPublishCrypto.php';
require DG_ROOT . '/src/VideoPublish/OAuthConnectionRepository.php';
require DG_ROOT . '/src/VideoPublish/TikTokPublishSettings.php';
require DG_ROOT . '/src/VideoPublish/TikTokOAuthClient.php';
require DG_ROOT . '/src/VideoPublish/TikTokTokenService.php';

$fail = 0;

function expect_true(bool $cond, string $msg): void
{
    global $fail;
    if ($cond) {
        echo "OK: $msg\n";
    } else {
        echo "FAIL: $msg\n";
        $fail++;
    }
}

Database::$pdo = new PDO('sqlite::memory:');
Database::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
Database::$pdo->exec(
    'CREATE TABLE dg_oauth_connections (
        platform TEXT PRIMARY KEY,
        account_id TEXT,
        account_label TEXT,
        access_token_enc TEXT,
        refresh_token_enc TEXT,
        expires_at TEXT,
        scopes TEXT,
        status TEXT,
        connected_at TEXT,
        meta_json TEXT
    )'
);

expect_true(TikTokPublishSettings::redirectUri() === 'https://crm.example.test/oauth/tiktok/callback', 'redirect URI');
expect_true(!TikTokPublishSettings::credentialsReady(), 'credentials not ready by default');

TikTokPublishSettings::saveFromPost([
    'tiktok_publish_enabled' => '1',
    'tiktok_publish_client_key' => 'key-abc',
    'tiktok_publish_client_secret' => 'secret-xyz',
    'tiktok_publish_posting_mode' => 'inbox',
    'tiktok_publish_app_review_note' => 'Dev sandbox',
]);
$cfg = TikTokPublishSettings::config();
expect_true($cfg['enabled'] === true, 'enabled');
expect_true($cfg['client_key'] === 'key-abc', 'client key');
expect_true($cfg['client_secret'] === 'secret-xyz', 'client secret');
expect_true($cfg['posting_mode'] === 'inbox', 'inbox mode');
expect_true(TikTokPublishSettings::credentialsReady($cfg), 'credentials ready');

$scopes = TikTokPublishSettings::scopes($cfg);
expect_true(in_array('user.info.basic', $scopes, true), 'basic scope');
expect_true(in_array('video.upload', $scopes, true), 'upload scope');
expect_true(!in_array('video.publish', $scopes, true), 'no publish without audit');

$url = TikTokOAuthClient::authorizationUrl('state-tt');
expect_true(str_contains($url, 'tiktok.com/v2/auth/authorize'), 'auth host');
expect_true(str_contains($url, 'client_key=key-abc'), 'client key in url');
expect_true(str_contains($url, rawurlencode('https://crm.example.test/oauth/tiktok/callback')), 'redirect in auth url');
expect_true(str_contains($url, 'state=state-tt'), 'state in url');

// Direct ohne Audit → effektiv Inbox
TikTokPublishSettings::saveFromPost([
    'tiktok_publish_enabled' => '1',
    'tiktok_publish_client_key' => 'key-abc',
    'tiktok_publish_client_secret' => '',
    'tiktok_publish_posting_mode' => 'direct',
]);
$cfgDirectBlocked = TikTokPublishSettings::config();
expect_true($cfgDirectBlocked['posting_mode'] === 'inbox', 'direct without audit → inbox');
expect_true($cfgDirectBlocked['client_secret'] === 'secret-xyz', 'secret retained when empty');

$formBlocked = TikTokPublishSettings::forForm();
expect_true(!empty($formBlocked['direct_blocked']), 'form marks direct blocked');
expect_true($formBlocked['posting_mode'] === 'direct', 'stored mode still direct');
expect_true($formBlocked['effective_mode'] === 'inbox', 'effective mode inbox');

TikTokPublishSettings::saveFromPost([
    'tiktok_publish_enabled' => '1',
    'tiktok_publish_client_key' => 'key-abc',
    'tiktok_publish_client_secret' => '',
    'tiktok_publish_posting_mode' => 'direct',
    'tiktok_publish_audit_ok' => '1',
]);
$cfgDirect = TikTokPublishSettings::config();
expect_true($cfgDirect['posting_mode'] === 'direct', 'direct with audit');
$directScopes = TikTokPublishSettings::scopes($cfgDirect);
expect_true(in_array('video.publish', $directScopes, true), 'publish scope with audit');
expect_true(!in_array('video.upload', $directScopes, true), 'no upload scope in direct');

$form = TikTokPublishSettings::forForm();
expect_true($form['credentials_ready'] === true, 'form credentials ready');
expect_true($form['connected'] === false, 'not connected yet');
expect_true(str_contains((string) $form['status_label'], 'Bereit'), 'status ready');

OAuthConnectionRepository::upsert(OAuthConnectionRepository::PLATFORM_TIKTOK, [
    'account_id' => 'oid-1',
    'account_label' => 'Demo Creator',
    'access_token' => 'access-tt',
    'refresh_token' => 'refresh-tt',
    'expires_at' => date('Y-m-d H:i:s', time() + 3600),
    'scopes' => implode(',', $directScopes),
    'status' => OAuthConnectionRepository::STATUS_CONNECTED,
    'connected_at' => date('Y-m-d H:i:s'),
    'meta' => ['posting_mode' => 'direct'],
]);
expect_true(OAuthConnectionRepository::isConnected(OAuthConnectionRepository::PLATFORM_TIKTOK), 'connected');
$token = TikTokTokenService::accessToken();
expect_true($token === 'access-tt', 'access token readable');

TikTokTokenService::disconnect();
expect_true(!OAuthConnectionRepository::isConnected(OAuthConnectionRepository::PLATFORM_TIKTOK), 'disconnected');

if ($fail > 0) {
    fwrite(STDERR, "tiktok-publish-selftest: $fail Fehler\n");
    exit(1);
}
echo "tiktok-publish-selftest OK\n";
