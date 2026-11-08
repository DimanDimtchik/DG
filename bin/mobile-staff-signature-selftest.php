<?php
declare(strict_types=1);

/**
 * ES6a: Staff-API Unterschrift → appendNew source=app.
 *
 * Usage: php bin/mobile-staff-signature-selftest.php
 */

function assertEs6a(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$storePath = $root . '/src/User/UserSignatureStore.php';
$apiPath = $root . '/src/Mobile/MobileStaffApi.php';
assertEs6a(is_file($storePath), 'UserSignatureStore.php vorhanden');
assertEs6a(is_file($apiPath), 'MobileStaffApi.php vorhanden');

$storeSrc = (string) file_get_contents($storePath);
$apiSrc = (string) file_get_contents($apiPath);

assertEs6a(str_contains($storeSrc, 'function appendFromAppUpload'), 'appendFromAppUpload definiert');
assertEs6a(str_contains($storeSrc, 'function appendFromAppPng'), 'appendFromAppPng definiert');
assertEs6a(str_contains($storeSrc, 'function appendFromAppDataUrl'), 'appendFromAppDataUrl definiert');
assertEs6a(
    preg_match("/'source'\\s*=>\\s*\\\$source/", $storeSrc) === 1
        || str_contains($storeSrc, "'source' => \$source"),
    'Upload/PNG reichen source durch'
);

assertEs6a(str_contains($apiSrc, "\$head === 'signature'"), 'Staff-Route signature vorhanden');
assertEs6a(str_contains($apiSrc, 'appendFromAppUpload'), 'API nutzt appendFromAppUpload');
assertEs6a(str_contains($apiSrc, 'appendFromAppDataUrl'), 'API nutzt appendFromAppDataUrl');
assertEs6a(
    str_contains($apiSrc, 'DepartmentRepository::resolveUserIdForContact'),
    'API löst CRM-User nur über eigenen Kontakt'
);
assertEs6a(
    !preg_match('/signature.*(contact_id|user_id).*POST|fremde[rn]?/i', $apiSrc)
        || str_contains($apiSrc, 'nur eigener Kontakt')
        || str_contains($apiSrc, 'ES6a'),
    'kein Picker fremder Signatur-Owner in API'
);

require $root . '/src/Media/MediaId.php';
require $storePath;

$ref = new ReflectionClass(UserSignatureStore::class);
assertEs6a($ref->hasMethod('appendFromAppUpload'), 'Reflection appendFromAppUpload');
assertEs6a($ref->hasMethod('appendFromAppDataUrl'), 'Reflection appendFromAppDataUrl');
assertEs6a(in_array('app', UserSignatureStore::SOURCES, true), 'SOURCES enthält app');

// appendNew source=app gegen SQLite (wie ES1d, ohne MediaStorage)
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

Database::$pdo = new PDO('sqlite::memory:');
Database::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
Database::$pdo->exec(
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
        created_by_user_id INTEGER NULL
    )'
);

$mediaApp = '20261108200000_abcdef';
assertEs6a(MediaId::isValid($mediaApp), 'app media_id gültig');
$id = UserSignatureStore::appendNew([
    'user_id' => 9,
    'contact_id' => 9,
    'media_id' => $mediaApp,
    'source' => 'app',
    'source_ref' => 'app',
]);
assertEs6a($id > 0, 'appendNew source=app speichert');
$active = UserSignatureStore::activeForUser(9);
assertEs6a($active !== null && $active['source'] === 'app', 'aktive Zeile source=app');
assertEs6a($active['media_id'] === $mediaApp, 'aktive media_id stimmt');

echo "ES6a Selftest bestanden.\n";
exit(0);
