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
require DG_ROOT . '/src/VideoPublish/VideoAssetStorage.php';
require DG_ROOT . '/src/VideoPublish/VideoAssetRepository.php';
require DG_ROOT . '/src/VideoPublish/OAuthConnectionRepository.php';
require DG_ROOT . '/src/VideoPublish/PublishJobRepository.php';
require DG_ROOT . '/src/VideoPublish/PublishPostRepository.php';
require DG_ROOT . '/src/VideoPublish/LinkedInPublishSettings.php';
require DG_ROOT . '/src/VideoPublish/LinkedInOAuthClient.php';
require DG_ROOT . '/src/VideoPublish/LinkedInTokenService.php';
require DG_ROOT . '/src/VideoPublish/LinkedInApiClient.php';
require DG_ROOT . '/src/VideoPublish/LinkedInResumableUploadService.php';
require DG_ROOT . '/src/VideoPublish/YouTubePublishSettings.php';
require DG_ROOT . '/src/VideoPublish/YouTubeOAuthClient.php';
require DG_ROOT . '/src/VideoPublish/YouTubeTokenService.php';
require DG_ROOT . '/src/VideoPublish/YouTubeApiClient.php';
require DG_ROOT . '/src/VideoPublish/YouTubeResumableUploadService.php';
require DG_ROOT . '/src/VideoPublish/VideoPublishQueueService.php';

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
Database::$pdo->exec('PRAGMA foreign_keys = ON');
Database::$pdo->exec(
    'CREATE TABLE dg_video_assets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        stored_name TEXT NOT NULL,
        original_name TEXT NOT NULL DEFAULT "",
        mime_type TEXT NOT NULL DEFAULT "video/mp4",
        size_bytes INTEGER NOT NULL DEFAULT 0,
        sha256 TEXT NOT NULL DEFAULT "",
        created_by INTEGER NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )'
);
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
Database::$pdo->exec(
    'CREATE TABLE dg_publish_jobs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        asset_id INTEGER NOT NULL,
        platform TEXT NOT NULL,
        state TEXT NOT NULL DEFAULT "pending",
        attempts INTEGER NOT NULL DEFAULT 0,
        last_error TEXT NOT NULL DEFAULT "",
        payload_json TEXT NULL,
        upload_session_url TEXT NULL,
        upload_offset INTEGER NOT NULL DEFAULT 0,
        external_id TEXT NOT NULL DEFAULT "",
        created_by INTEGER NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (asset_id) REFERENCES dg_video_assets(id) ON DELETE CASCADE
    )'
);
Database::$pdo->exec(
    'CREATE TABLE dg_publish_posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        job_id INTEGER NOT NULL UNIQUE,
        platform TEXT NOT NULL,
        external_id TEXT NOT NULL DEFAULT "",
        title TEXT NOT NULL DEFAULT "",
        description TEXT NULL,
        privacy TEXT NOT NULL DEFAULT "private",
        published_at TEXT NULL,
        editable_flags TEXT NOT NULL DEFAULT "title,description,privacy,delete",
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (job_id) REFERENCES dg_publish_jobs(id) ON DELETE CASCADE
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

VideoAssetStorage::ensureDir();
$stored = 'li-selftest-' . bin2hex(random_bytes(4)) . '.mp4';
$path = VideoAssetStorage::absolutePath($stored);
file_put_contents($path, str_repeat('1', 80_000));
$assetId = VideoAssetRepository::create([
    'stored_name' => $stored,
    'original_name' => 'li-demo.mp4',
    'mime_type' => 'video/mp4',
    'size_bytes' => 80_000,
    'sha256' => hash_file('sha256', $path) ?: '',
], 1);

$future = date('Y-m-d H:i:s', time() + 3600);
$jobId = PublishJobRepository::create($assetId, PublishJobRepository::PLATFORM_LINKEDIN, [
    'title' => 'LI Selftest',
    'commentary' => 'Kommentar',
    'scheduled_at' => $future,
    'li_phase' => 'init',
], 1);
$step = LinkedInResumableUploadService::processJob($jobId);
expect_true(!empty($step['ok']) && empty($step['done']), 'Geplanter Job wartet');
expect_true(str_contains($step['message'], 'Geplant'), 'Geplant-Meldung');

PublishJobRepository::update($jobId, [
    'payload_json' => json_encode([
        'title' => 'LI Selftest',
        'commentary' => 'Kommentar',
        'scheduled_at' => '',
        'li_phase' => 'init',
    ], JSON_UNESCAPED_UNICODE),
]);
$step2 = LinkedInResumableUploadService::processJob($jobId);
expect_true($step2['ok'] === false, 'Ohne Token schlägt Upload fehl');
expect_true(!str_contains(strtolower($step2['message']), 'bearer'), 'Kein Token in Fehlermeldung');

$queue = VideoPublishQueueService::runAutomatic(5, 2);
expect_true(is_array($queue) && isset($queue['processed']), 'Queue läuft ohne Crash');

@unlink($path);

echo $fail === 0 ? "\nAlle LinkedIn-Publish-Selftests OK.\n" : "\n{$fail} Fehler.\n";
exit($fail > 0 ? 1 : 0);
