<?php
declare(strict_types=1);

/**
 * TikTok Publish Phase 4-1/4-2: Settings, OAuth-URL, Chunk-Plan, Scheduling (ohne Live-API).
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
require DG_ROOT . '/src/VideoPublish/VideoAssetStorage.php';
require DG_ROOT . '/src/VideoPublish/VideoAssetRepository.php';
require DG_ROOT . '/src/VideoPublish/OAuthConnectionRepository.php';
require DG_ROOT . '/src/VideoPublish/PublishJobRepository.php';
require DG_ROOT . '/src/VideoPublish/PublishPostRepository.php';
require DG_ROOT . '/src/VideoPublish/TikTokPublishSettings.php';
require DG_ROOT . '/src/VideoPublish/TikTokOAuthClient.php';
require DG_ROOT . '/src/VideoPublish/TikTokTokenService.php';
require DG_ROOT . '/src/VideoPublish/TikTokApiClient.php';
require DG_ROOT . '/src/VideoPublish/TikTokPublishService.php';

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
Database::$pdo->exec(
    'CREATE TABLE dg_video_assets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        stored_name TEXT,
        original_name TEXT,
        mime_type TEXT,
        size_bytes INTEGER,
        sha256 TEXT,
        created_by INTEGER,
        created_at TEXT
    )'
);
Database::$pdo->exec(
    'CREATE TABLE dg_publish_jobs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        asset_id INTEGER,
        platform TEXT,
        state TEXT,
        attempts INTEGER DEFAULT 0,
        last_error TEXT,
        payload_json TEXT,
        upload_session_url TEXT,
        upload_offset INTEGER DEFAULT 0,
        external_id TEXT,
        created_by INTEGER,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        stored_name TEXT,
        size_bytes INTEGER,
        mime_type TEXT
    )'
);
Database::$pdo->exec(
    'CREATE TABLE dg_publish_posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        job_id INTEGER,
        platform TEXT,
        external_id TEXT,
        title TEXT,
        description TEXT,
        privacy TEXT,
        published_at TEXT,
        editable_flags TEXT
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

$planSmall = TikTokApiClient::chunkPlan(2048);
expect_true($planSmall['total_chunk_count'] === 1 && $planSmall['chunk_size'] === 2048, 'small file single chunk');
$planBig = TikTokApiClient::chunkPlan(25_000_000);
expect_true($planBig['chunk_size'] >= TikTokApiClient::MIN_CHUNK, 'big chunk >= 5MB');
expect_true($planBig['total_chunk_count'] >= 2, 'big file multi chunk');
expect_true(TikTokApiClient::contentTypeForPath('x.mp4') === 'video/mp4', 'mp4 content-type');
expect_true(TikTokApiClient::contentTypeForPath('x.mov') === 'video/quicktime', 'mov content-type');

VideoAssetStorage::ensureDir();
$demoPath = VideoAssetStorage::absolutePath('selftest-tt.mp4');
file_put_contents($demoPath, str_repeat('0', 2048));
Database::$pdo->exec(
    "INSERT INTO dg_video_assets (stored_name, original_name, mime_type, size_bytes, sha256, created_by, created_at)
     VALUES ('selftest-tt.mp4', 'selftest-tt.mp4', 'video/mp4', 2048, 'abc', 1, datetime('now'))"
);
$assetId = (int) Database::$pdo->lastInsertId();
$jobId = PublishJobRepository::create($assetId, PublishJobRepository::PLATFORM_TIKTOK, [
    'title' => 'Test',
    'caption' => 'Hallo',
    'tt_phase' => 'init',
    'scheduled_at' => date('Y-m-d H:i:s', time() + 3600),
], 1);
$step = TikTokPublishService::processJob($jobId);
expect_true($step['ok'] === true && $step['done'] === false, 'scheduled job waits');
expect_true(str_contains($step['message'], 'Geplant'), 'scheduled message');

TikTokTokenService::disconnect();
expect_true(!OAuthConnectionRepository::isConnected(OAuthConnectionRepository::PLATFORM_TIKTOK), 'disconnected');
@unlink($demoPath);

if ($fail > 0) {
    fwrite(STDERR, "tiktok-publish-selftest: $fail Fehler\n");
    exit(1);
}
echo "tiktok-publish-selftest OK\n";
