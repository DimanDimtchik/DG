<?php
declare(strict_types=1);

/**
 * ES1d: UserSignatureStore — append schließt alte Zeile; keine delete-API.
 * Ohne CRM-DB: SQLite in-memory + Stub Database/MigrationRunner (wie m0b).
 *
 * Usage: php bin/user-signature-selftest.php
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
        // Selftest legt Schema selbst an.
    }
}

require dirname(__DIR__) . '/src/Media/MediaId.php';
require dirname(__DIR__) . '/src/User/UserSignatureStore.php';

function assertEs1d(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$ref = new ReflectionClass(UserSignatureStore::class);
assertEs1d($ref->hasMethod('listForContact'), 'Methode listForContact vorhanden');
assertEs1d($ref->hasMethod('activeForUser'), 'Methode activeForUser vorhanden');
assertEs1d($ref->hasMethod('appendNew'), 'Methode appendNew vorhanden');
assertEs1d(!$ref->hasMethod('delete'), 'keine delete-Methode (API fehlt)');

Database::$pdo = new PDO('sqlite::memory:');
Database::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
Database::$pdo->exec('PRAGMA foreign_keys = ON');

foreach ([
    'CREATE TABLE dg_users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL,
        password_hash TEXT NOT NULL DEFAULT "",
        email TEXT NOT NULL DEFAULT "",
        display_name TEXT NOT NULL DEFAULT ""
    )',
    'CREATE TABLE dg_contacts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        login TEXT NOT NULL,
        display_name TEXT NOT NULL DEFAULT ""
    )',
    'CREATE TABLE dg_media (
        media_id TEXT PRIMARY KEY,
        original_name TEXT NOT NULL DEFAULT "",
        stored_name TEXT NOT NULL DEFAULT "",
        mime_type TEXT NOT NULL DEFAULT "image/png",
        extension TEXT NOT NULL DEFAULT "png",
        size_bytes INTEGER NOT NULL DEFAULT 0,
        status TEXT NOT NULL DEFAULT "active"
    )',
    'CREATE TABLE dg_user_signatures (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        contact_id INTEGER NOT NULL,
        media_id TEXT NOT NULL,
        source TEXT NOT NULL,
        source_ref TEXT NOT NULL DEFAULT "",
        valid_from TEXT NOT NULL,
        valid_to TEXT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_by_user_id INTEGER NULL,
        FOREIGN KEY (user_id) REFERENCES dg_users (id) ON DELETE RESTRICT,
        FOREIGN KEY (contact_id) REFERENCES dg_contacts (id) ON DELETE RESTRICT,
        FOREIGN KEY (media_id) REFERENCES dg_media (media_id) ON DELETE RESTRICT
    )',
] as $sql) {
    Database::pdo()->exec($sql);
}

$pdo = Database::pdo();
$pdo->exec("INSERT INTO dg_users (username, display_name) VALUES ('es1d-user', 'ES1d Tester')");
$userId = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO dg_contacts (login, display_name) VALUES ('es1d-contact', 'ES1d Kontakt')");
$contactId = (int) $pdo->lastInsertId();

$mediaA = '20261108120000_aaaa01';
$mediaB = '20261108120001_bbbb02';
assertEs1d(MediaId::isValid($mediaA) && MediaId::isValid($mediaB), 'Fixture-media_ids gültig');
$pdo->prepare(
    'INSERT INTO dg_media (media_id, original_name, stored_name, mime_type, extension, size_bytes)
     VALUES (:id, :name, :stored, :mime, :ext, :size)'
)->execute([
    'id' => $mediaA,
    'name' => 'sig-a.png',
    'stored' => 'sig-a.png',
    'mime' => 'image/png',
    'ext' => 'png',
    'size' => 100,
]);
$pdo->prepare(
    'INSERT INTO dg_media (media_id, original_name, stored_name, mime_type, extension, size_bytes)
     VALUES (:id, :name, :stored, :mime, :ext, :size)'
)->execute([
    'id' => $mediaB,
    'name' => 'sig-b.png',
    'stored' => 'sig-b.png',
    'mime' => 'image/png',
    'ext' => 'png',
    'size' => 120,
]);

$threw = false;
try {
    UserSignatureStore::appendNew([
        'user_id' => $userId,
        'contact_id' => $contactId,
        'media_id' => 'not-a-media-id',
        'source' => 'upload',
    ]);
} catch (InvalidArgumentException $e) {
    $threw = true;
}
assertEs1d($threw, 'appendNew wirft bei ungültiger media_id');

$threw = false;
try {
    UserSignatureStore::appendNew([
        'user_id' => $userId,
        'contact_id' => $contactId,
        'media_id' => $mediaA,
        'source' => 'qes',
    ]);
} catch (InvalidArgumentException $e) {
    $threw = true;
}
assertEs1d($threw, 'appendNew wirft bei ungültiger source');

$id1 = UserSignatureStore::appendNew([
    'user_id' => $userId,
    'contact_id' => $contactId,
    'media_id' => $mediaA,
    'source' => 'upload',
    'source_ref' => 'es1d-first',
    'valid_from' => '2026-11-08 10:00:00',
]);
assertEs1d($id1 > 0, 'erste Signatur angelegt');

$active1 = UserSignatureStore::activeForUser($userId);
assertEs1d($active1 !== null && (int) $active1['id'] === $id1, 'erste Signatur ist aktiv');
assertEs1d($active1['valid_to'] === null && $active1['is_active'] === true, 'aktive Zeile: valid_to NULL');
assertEs1d($active1['media_id'] === $mediaA && $active1['source'] === 'upload', 'aktive Felder stimmen');

$id2 = UserSignatureStore::appendNew([
    'user_id' => $userId,
    'contact_id' => $contactId,
    'media_id' => $mediaB,
    'source' => 'canvas',
    'source_ref' => 'es1d-second',
    'valid_from' => '2026-11-08 12:00:00',
]);
assertEs1d($id2 > 0 && $id2 !== $id1, 'zweite Signatur angelegt');

$active2 = UserSignatureStore::activeForUser($userId);
assertEs1d($active2 !== null && (int) $active2['id'] === $id2, 'zweite Signatur ist aktiv');
assertEs1d($active2['media_id'] === $mediaB && $active2['source'] === 'canvas', 'zweite Felder stimmen');
assertEs1d($active2['valid_to'] === null, 'zweite valid_to bleibt NULL');

$list = UserSignatureStore::listForContact($contactId);
assertEs1d(count($list) === 2, 'listForContact liefert 2 Zeilen');
assertEs1d((int) $list[0]['id'] === $id2 && (int) $list[1]['id'] === $id1, 'Chronologie neueste zuerst');

$closed = null;
foreach ($list as $row) {
    if ((int) $row['id'] === $id1) {
        $closed = $row;
        break;
    }
}
assertEs1d($closed !== null, 'erste Zeile noch in Historie');
assertEs1d($closed['valid_to'] === '2026-11-08 12:00:00', 'append schließt alte valid_to');
assertEs1d($closed['is_active'] === false, 'geschlossene Zeile nicht aktiv');

$openCount = (int) $pdo->query(
    'SELECT COUNT(*) FROM dg_user_signatures WHERE user_id = ' . (int) $userId . ' AND valid_to IS NULL'
)->fetchColumn();
assertEs1d($openCount === 1, 'genau eine aktive Zeile pro User');

$refUpload = $ref->hasMethod('appendFromUpload');
assertEs1d($refUpload, 'Methode appendFromUpload vorhanden (ES2b)');
$threw = false;
try {
    UserSignatureStore::appendFromUpload($userId, $contactId, [
        'name' => 'sig.jpg',
        'error' => UPLOAD_ERR_OK,
        'size' => 10,
        'tmp_name' => '/tmp/x',
        'type' => 'image/jpeg',
    ], $userId);
} catch (InvalidArgumentException $e) {
    $threw = str_contains($e->getMessage(), 'PNG') || str_contains($e->getMessage(), 'WebP');
}
assertEs1d($threw, 'appendFromUpload lehnt JPG ab');

echo "ES1d Selftest bestanden.\n";
exit(0);
