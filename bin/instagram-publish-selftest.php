<?php
declare(strict_types=1);

/**
 * Instagram Publish Phase 3-1: Settings, OAuth-URL (ohne echte Meta-Calls).
 * php bin/instagram-publish-selftest.php
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
require DG_ROOT . '/src/VideoPublish/InstagramPublishSettings.php';
require DG_ROOT . '/src/VideoPublish/InstagramOAuthClient.php';
require DG_ROOT . '/src/VideoPublish/InstagramTokenService.php';

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

putenv('DG_VIDEO_PUBLISH_KEY=' . str_repeat('a', 32));
$_ENV['DG_VIDEO_PUBLISH_KEY'] = str_repeat('a', 32);

expect_true(InstagramPublishSettings::redirectUri() === 'https://crm.example.test/oauth/instagram/callback', 'redirect URI');
expect_true(!InstagramPublishSettings::credentialsReady(), 'credentials not ready by default');

InstagramPublishSettings::saveFromPost([
    'instagram_publish_enabled' => '1',
    'instagram_publish_app_id' => 'app-123',
    'instagram_publish_app_secret' => 'secret-xyz',
    'instagram_publish_login_path' => 'facebook',
    'instagram_publish_access_level' => 'advanced',
    'instagram_publish_facebook_page_id' => '99-abc',
    'instagram_publish_ig_user_id' => 'ig-55',
    'instagram_publish_app_review_note' => 'BV beantragt',
]);
$cfg = InstagramPublishSettings::config();
expect_true($cfg['enabled'] === true, 'enabled');
expect_true($cfg['app_id'] === 'app-123', 'app id');
expect_true($cfg['app_secret'] === 'secret-xyz', 'secret stored');
expect_true($cfg['login_path'] === 'facebook', 'login path');
expect_true($cfg['access_level'] === 'advanced', 'access level');
expect_true($cfg['facebook_page_id'] === '99', 'page id digits only');
expect_true($cfg['ig_user_id'] === '55', 'ig id digits only');
expect_true(InstagramPublishSettings::credentialsReady($cfg), 'credentials ready');

$scopes = InstagramPublishSettings::scopes($cfg);
expect_true(in_array('instagram_content_publish', $scopes, true), 'fb content_publish scope');
expect_true(in_array('pages_show_list', $scopes, true), 'pages_show_list scope');

$url = InstagramOAuthClient::authorizationUrl('state-test');
expect_true(str_contains($url, 'facebook.com/'), 'fb auth host');
expect_true(str_contains($url, 'client_id=app-123'), 'client id in auth url');
expect_true(str_contains($url, rawurlencode('https://crm.example.test/oauth/instagram/callback')), 'redirect in auth url');
expect_true(str_contains($url, 'instagram_content_publish'), 'scope in auth url');

InstagramPublishSettings::saveFromPost([
    'instagram_publish_enabled' => '1',
    'instagram_publish_app_id' => 'app-123',
    'instagram_publish_app_secret' => '',
    'instagram_publish_login_path' => 'instagram',
    'instagram_publish_access_level' => 'standard',
]);
$cfgIg = InstagramPublishSettings::config();
expect_true($cfgIg['app_secret'] === 'secret-xyz', 'secret retained when empty');
expect_true($cfgIg['login_path'] === 'instagram', 'ig login path');
$igScopes = InstagramPublishSettings::scopes($cfgIg);
expect_true(in_array('instagram_business_content_publish', $igScopes, true), 'ig business publish scope');
$urlIg = InstagramOAuthClient::authorizationUrl('state-2');
expect_true(str_contains($urlIg, 'instagram.com/oauth/authorize'), 'ig auth host');

$form = InstagramPublishSettings::forForm();
expect_true($form['credentials_ready'] === true, 'form credentials ready');
expect_true($form['connected'] === false, 'not connected yet');
expect_true(str_contains((string) $form['status_label'], 'noch nicht verbunden'), 'status label');

OAuthConnectionRepository::upsert(OAuthConnectionRepository::PLATFORM_INSTAGRAM, [
    'account_id' => '178414000',
    'account_label' => '@demo',
    'access_token' => 'tok',
    'refresh_token' => '',
    'expires_at' => date('Y-m-d H:i:s', time() + 3600),
    'scopes' => implode(',', $igScopes),
    'status' => OAuthConnectionRepository::STATUS_CONNECTED,
    'connected_at' => date('Y-m-d H:i:s'),
    'meta' => ['login_path' => 'instagram'],
]);
expect_true(OAuthConnectionRepository::isConnected(OAuthConnectionRepository::PLATFORM_INSTAGRAM), 'connected');
expect_true(InstagramTokenService::accessToken() === 'tok', 'access token');
InstagramTokenService::disconnect();
expect_true(!OAuthConnectionRepository::isConnected(OAuthConnectionRepository::PLATFORM_INSTAGRAM), 'disconnected');

if ($fail > 0) {
    fwrite(STDERR, "instagram-publish-selftest: $fail Fehler\n");
    exit(1);
}
echo "instagram-publish-selftest OK\n";
exit(0);
