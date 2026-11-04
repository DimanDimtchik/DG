<?php
declare(strict_types=1);

/**
 * Video Phase 1: Schema, Crypto, OAuth-URL, Job-States (SQLite in-memory, ohne Google-HTTP).
 * php bin/video-publish-selftest.php
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

expect_true(VideoAssetRepository::tableReady(), 'dg_video_assets bereit');
expect_true(OAuthConnectionRepository::tableReady(), 'dg_oauth_connections bereit');
expect_true(PublishJobRepository::tableReady(), 'dg_publish_jobs bereit');
expect_true(PublishPostRepository::tableReady(), 'dg_publish_posts bereit');

$plain = 'yt-refresh-token-secret-äöü';
$enc = VideoPublishCrypto::encrypt($plain);
expect_true(str_starts_with($enc, 'v1:'), 'Crypto Prefix v1:');
expect_true($enc !== $plain, 'Ciphertext ≠ Plaintext');
expect_true(VideoPublishCrypto::decrypt($enc) === $plain, 'Crypto Roundtrip');
expect_true(VideoPublishCrypto::decrypt('garbage') === '', 'Ungültiger Ciphertext → leer');

SettingsStore::set(YouTubePublishSettings::STORE_KEY, [
    'enabled' => 1,
    'client_id' => 'test-client-id.apps.googleusercontent.com',
    'client_secret' => 'test-secret',
    'oauth_mode' => 'testing',
]);
expect_true(YouTubePublishSettings::credentialsReady(), 'Credentials ready');
expect_true(
    YouTubePublishSettings::redirectUri() === 'https://crm.example.test/oauth/youtube/callback',
    'Redirect-URI'
);

$state = 'abc123state';
$url = YouTubeOAuthClient::authorizationUrl($state, true);
expect_true(str_contains($url, 'accounts.google.com'), 'OAuth Host');
expect_true(str_contains($url, 'access_type=offline'), 'access_type=offline');
expect_true(str_contains($url, 'prompt=consent'), 'prompt=consent');
expect_true(str_contains($url, 'state=' . $state), 'OAuth state');
expect_true(str_contains($url, rawurlencode(YouTubePublishSettings::SCOPE_UPLOAD)), 'upload scope');

expect_true(YouTubeApiClient::sanitizePrivacy('PUBLIC') === 'public', 'Privacy public');
expect_true(YouTubeApiClient::sanitizePrivacy('weird') === 'private', 'Privacy Fallback private');

VideoAssetStorage::ensureDir();
expect_true(is_dir(VideoAssetStorage::baseDir()), 'storage/video-publish angelegt');
expect_true(is_file(VideoAssetStorage::baseDir() . '/.htaccess'), '.htaccess deny vorhanden');

$stored = 'selftest-' . bin2hex(random_bytes(4)) . '.mp4';
$path = VideoAssetStorage::absolutePath($stored);
file_put_contents($path, str_repeat('0', 1024));
$sha = hash_file('sha256', $path);
$assetId = VideoAssetRepository::create([
    'stored_name' => $stored,
    'original_name' => 'demo.mp4',
    'mime_type' => 'video/mp4',
    'size_bytes' => 1024,
    'sha256' => is_string($sha) ? $sha : '',
], 1);
expect_true($assetId > 0, 'Asset angelegt');
expect_true(VideoAssetRepository::find($assetId) !== null, 'Asset lesbar');

$jobId = PublishJobRepository::create($assetId, PublishJobRepository::PLATFORM_YOUTUBE, [
    'title' => 'Selftest Video',
    'description' => 'Nur Test',
    'privacy' => 'private',
], 1);
expect_true($jobId > 0, 'Job pending angelegt');
$job = PublishJobRepository::find($jobId);
expect_true(($job['state'] ?? '') === PublishJobRepository::STATE_PENDING, 'Job state=pending');

expect_true(!OAuthConnectionRepository::isConnected(OAuthConnectionRepository::PLATFORM_YOUTUBE), 'Nicht connected');

$step = YouTubeResumableUploadService::processJob($jobId);
expect_true($step['ok'] === false, 'Upload ohne Token schlägt fehl');
expect_true(!str_contains(strtolower($step['message']), 'ya29'), 'Kein Access-Token in Fehlermeldung');
$job2 = PublishJobRepository::find($jobId);
expect_true(
    in_array((string) ($job2['state'] ?? ''), [
        PublishJobRepository::STATE_PENDING,
        PublishJobRepository::STATE_FAILED,
    ], true),
    'Job nach Fehler pending/failed'
);
expect_true(trim((string) ($job2['last_error'] ?? '')) !== '', 'last_error gesetzt');

PublishJobRepository::update($jobId, [
    'state' => PublishJobRepository::STATE_UPLOADING,
    'upload_session_url' => 'https://example.test/session',
    'upload_offset' => 512,
]);
$job3 = PublishJobRepository::find($jobId);
expect_true(($job3['state'] ?? '') === PublishJobRepository::STATE_UPLOADING, 'State → uploading');
expect_true((int) ($job3['upload_offset'] ?? 0) === 512, 'upload_offset gespeichert');

PublishJobRepository::update($jobId, [
    'state' => PublishJobRepository::STATE_PUBLISHED,
    'external_id' => 'ytVideoSelftest1',
    'upload_offset' => 1024,
]);
PublishPostRepository::upsertFromJob($jobId, [
    'platform' => 'youtube',
    'external_id' => 'ytVideoSelftest1',
    'title' => 'Selftest Video',
    'description' => 'Nur Test',
    'privacy' => 'private',
    'published_at' => date('Y-m-d H:i:s'),
]);
$post = PublishPostRepository::findByJob($jobId);
expect_true($post !== null && ($post['external_id'] ?? '') === 'ytVideoSelftest1', 'publish_posts Snapshot');

OAuthConnectionRepository::upsert(OAuthConnectionRepository::PLATFORM_YOUTUBE, [
    'account_id' => 'UC_SELFTEST',
    'account_label' => 'Selftest Channel',
    'access_token' => 'access-plain',
    'refresh_token' => 'refresh-plain',
    'expires_at' => date('Y-m-d H:i:s', time() + 3600),
    'scopes' => implode(' ', YouTubePublishSettings::scopes()),
    'status' => OAuthConnectionRepository::STATUS_CONNECTED,
    'connected_at' => date('Y-m-d H:i:s'),
]);
expect_true(OAuthConnectionRepository::isConnected(OAuthConnectionRepository::PLATFORM_YOUTUBE), 'Connected nach Upsert');
$row = OAuthConnectionRepository::findByPlatform(OAuthConnectionRepository::PLATFORM_YOUTUBE);
expect_true(!str_contains((string) ($row['access_token_enc'] ?? ''), 'access-plain'), 'Access encrypted in DB');
expect_true(OAuthConnectionRepository::decryptedAccessToken(OAuthConnectionRepository::PLATFORM_YOUTUBE) === 'access-plain', 'Access decrypt');
expect_true(OAuthConnectionRepository::decryptedRefreshToken(OAuthConnectionRepository::PLATFORM_YOUTUBE) === 'refresh-plain', 'Refresh decrypt');

$form = YouTubePublishSettings::forForm();
expect_true(!empty($form['connected']), 'forForm connected');
expect_true(($form['channel_id'] ?? '') === 'UC_SELFTEST', 'forForm channel_id');
expect_true(!empty($form['client_secret_set']), 'Secret nur als Flag');

YouTubeTokenService::disconnect();
expect_true(!OAuthConnectionRepository::isConnected(OAuthConnectionRepository::PLATFORM_YOUTUBE), 'Disconnect löscht Tokens');

@unlink($path);

echo $fail === 0 ? "\nAlle Video-Publish-Selftests OK.\n" : "\n{$fail} Fehler.\n";
exit($fail > 0 ? 1 : 0);
