<?php
declare(strict_types=1);

/**
 * LinkedIn Publish Phase 2-1: Settings, Crypto, OAuth-URL (SQLite, ohne echte LI-Calls).
 * php bin/linkedin-publish-selftest.php
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
require DG_ROOT . '/src/VideoPublish/LinkedInPublishSettings.php';
require DG_ROOT . '/src/VideoPublish/LinkedInOAuthClient.php';
require DG_ROOT . '/src/VideoPublish/LinkedInTokenService.php';

$fail = 0;

function expect_true(bool $cond, string $msg): void
{
    global $fail;
    if ($cond) {
        echo "OK  {$msg}\n";
    } else {
        echo "FAIL {$msg}\n";
        $fail++;
    }
}

Database::$pdo = new PDO('sqlite::memory:');
Database::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
Database::$pdo->exec(
    'CREATE TABLE dg_oauth_connections (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        platform TEXT NOT NULL UNIQUE,
        account_id TEXT NOT NULL DEFAULT "",
        account_label TEXT NOT NULL DEFAULT "",
        access_token_enc TEXT NULL,
        refresh_token_enc TEXT NULL,
        expires_at TEXT NULL,
        scopes TEXT NOT NULL DEFAULT "",
        status TEXT NOT NULL DEFAULT "disconnected",
        meta_json TEXT NULL,
        connected_at TEXT NULL,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )'
);

expect_true(OAuthConnectionRepository::tableReady(), 'oauth_connections bereit');

SettingsStore::set(LinkedInPublishSettings::STORE_KEY, [
    'enabled' => 1,
    'client_id' => 'li-client-id',
    'client_secret' => 'li-secret',
    'organization_id' => '123456',
    'review_tier' => 'development',
    'app_review_note' => 'Dev-Antrag 2026-11',
]);

expect_true(LinkedInPublishSettings::credentialsReady(), 'Credentials ready');
expect_true(
    LinkedInPublishSettings::organizationUrn() === 'urn:li:organization:123456',
    'Organization URN'
);
expect_true(
    LinkedInPublishSettings::redirectUri() === 'https://crm.example.test/oauth/linkedin/callback',
    'Redirect-URI'
);

$state = 'li-state-42';
$url = LinkedInOAuthClient::authorizationUrl($state);
expect_true(str_contains($url, 'linkedin.com/oauth/v2/authorization'), 'OAuth Host');
expect_true(str_contains($url, 'state=' . $state), 'OAuth state');
expect_true(str_contains($url, 'w_organization_social'), 'org write scope');
expect_true(str_contains($url, 'r_organization_social'), 'org read scope');
expect_true(str_contains($url, 'openid'), 'openid scope');

OAuthConnectionRepository::upsert(OAuthConnectionRepository::PLATFORM_LINKEDIN, [
    'account_id' => 'member-abc',
    'account_label' => 'Test Admin',
    'access_token' => 'li-access',
    'refresh_token' => '',
    'expires_at' => date('Y-m-d H:i:s', time() + 3600),
    'scopes' => implode(' ', LinkedInPublishSettings::scopes()),
    'status' => OAuthConnectionRepository::STATUS_CONNECTED,
    'connected_at' => date('Y-m-d H:i:s'),
]);
expect_true(
    OAuthConnectionRepository::isConnected(OAuthConnectionRepository::PLATFORM_LINKEDIN),
    'Connected ohne Refresh (LinkedIn)'
);
$row = OAuthConnectionRepository::findByPlatform(OAuthConnectionRepository::PLATFORM_LINKEDIN);
expect_true(!str_contains((string) ($row['access_token_enc'] ?? ''), 'li-access'), 'Access encrypted');
expect_true(
    OAuthConnectionRepository::decryptedAccessToken(OAuthConnectionRepository::PLATFORM_LINKEDIN) === 'li-access',
    'Access decrypt'
);

$form = LinkedInPublishSettings::forForm();
expect_true(!empty($form['connected']), 'forForm connected');
expect_true(($form['organization_id'] ?? '') === '123456', 'forForm org id');
expect_true(!empty($form['client_secret_set']), 'Secret nur Flag');
expect_true(($form['review_tier'] ?? '') === 'development', 'review_tier');

LinkedInTokenService::disconnect();
expect_true(
    !OAuthConnectionRepository::isConnected(OAuthConnectionRepository::PLATFORM_LINKEDIN),
    'Disconnect'
);

// YouTube-Regel: ohne Refresh nicht connected
OAuthConnectionRepository::upsert(OAuthConnectionRepository::PLATFORM_YOUTUBE, [
    'account_id' => 'UC_x',
    'account_label' => 'YT',
    'access_token' => 'yt-access',
    'refresh_token' => '',
    'expires_at' => date('Y-m-d H:i:s', time() + 3600),
    'status' => OAuthConnectionRepository::STATUS_CONNECTED,
]);
expect_true(
    !OAuthConnectionRepository::isConnected(OAuthConnectionRepository::PLATFORM_YOUTUBE),
    'YouTube ohne Refresh nicht connected'
);

echo $fail === 0 ? "\nAlle LinkedIn-Publish-Selftests OK.\n" : "\n{$fail} Fehler.\n";
exit($fail > 0 ? 1 : 0);
