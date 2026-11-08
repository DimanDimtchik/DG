<?php
declare(strict_types=1);

/**
 * ES7c: Re-Import löscht keine Historie — immer neue Version.
 *
 * Usage: php bin/signature-import-reimport-selftest.php
 */

function assertEs7c(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);

$storeSrc = (string) file_get_contents($root . '/src/User/UserSignatureStore.php');
$svcSrc = (string) file_get_contents($root . '/src/User/SignatureImportService.php');

assertEs7c(
    !preg_match('/DELETE\s+FROM\s+dg_user_signatures/i', $storeSrc),
    'Store: kein DELETE FROM dg_user_signatures'
);

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

final class MediaStorage
{
    public const MAX_BYTES = 52_428_800;

    public static function storeBinary(string $mediaId, string $storedName, string $binary, string $mime): array
    {
        return [
            'stored_name' => $storedName,
            'mime_type' => $mime,
            'extension' => str_contains($mime, 'webp') ? 'webp' : 'png',
            'width' => 1,
            'height' => 1,
            'size_bytes' => strlen($binary),
        ];
    }
}

final class MediaRepository
{
    public static function ensureTables(): void
    {
    }

    /** @param array<string, mixed> $stored */
    public static function insert(string $mediaId, array $stored, ?int $createdBy = null): void
    {
    }

    public static function delete(string $mediaId): void
    {
    }
}

require $root . '/src/Media/MediaId.php';
require $root . '/src/User/UserSignatureStore.php';
require $root . '/src/User/SignatureImportProvider.php';
require $root . '/src/User/SignatureImportService.php';

assertEs7c(
    !(new ReflectionClass(UserSignatureStore::class))->hasMethod('delete'),
    'Store: keine delete-Methode'
);
assertEs7c(
    (new ReflectionClass(UserSignatureStore::class))->hasMethod('countForUser'),
    'countForUser vorhanden (ES7c Guard)'
);
assertEs7c(
    str_contains($svcSrc, 'countBefore') && str_contains($svcSrc, 'countAfter'),
    'Service prüft Historie-Zuwachs'
);
assertEs7c(
    !preg_match('/DELETE\s+FROM\s+dg_user_signatures/i', $svcSrc),
    'Service: kein DELETE auf Signatur-Tabelle'
);

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

$png = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAAFUlEQVR42mNk+M9Qz0AEYBxVSF+FABJADveWkH6aAAAAAElFTkSuQmCC',
    true
);
assertEs7c(is_string($png) && $png !== '', 'PNG Fixture');

$userId = 42;
$contactId = 42;
$ref = 'same-external-ref';
$ids = [];
$mediaIds = [];

for ($i = 1; $i <= 3; $i++) {
    $before = UserSignatureStore::countForUser($userId);
    $res = SignatureImportService::importPayload($userId, $contactId, [
        'external_ref' => $ref,
        'mime' => 'image/png',
        'content' => $png,
    ], $userId);
    $after = UserSignatureStore::countForUser($userId);
    assertEs7c($after === $before + 1, "Import #{$i}: Historie +1 ({$before}→{$after})");
    assertEs7c($res['history_count'] === $after, "Import #{$i}: history_count stimmt");
    $ids[] = $res['id'];
    $mediaIds[] = $res['media_id'];
}

assertEs7c(count(array_unique($ids)) === 3, 'drei verschiedene Signatur-IDs');
assertEs7c(UserSignatureStore::countForUser($userId) === 3, 'genau 3 Historienzeilen');

$list = UserSignatureStore::listForContact($contactId);
assertEs7c(count($list) === 3, 'listForContact liefert 3');
$closed = 0;
$open = 0;
foreach ($list as $row) {
    if (!empty($row['is_active'])) {
        $open++;
        assertEs7c($row['source_ref'] === $ref, 'aktive Zeile behält source_ref');
    } else {
        $closed++;
        assertEs7c($row['valid_to'] !== null && $row['valid_to'] !== '', 'geschlossene Zeile hat valid_to');
    }
    assertEs7c($row['source'] === 'import', 'alle Zeilen source=import');
}
assertEs7c($open === 1 && $closed === 2, '1 aktiv, 2 beendet — nichts gelöscht');

// Alle media_ids der Historie noch referenziert
$dbMedia = Database::pdo()->query(
    'SELECT media_id FROM dg_user_signatures WHERE user_id = ' . (int) $userId . ' ORDER BY id'
)->fetchAll(PDO::FETCH_COLUMN);
assertEs7c(count($dbMedia) === 3, '3 media_id-Referenzen in DB');
assertEs7c(count(array_unique($dbMedia)) === 3, '3 unterschiedliche media_ids (kein In-Place-Overwrite)');

echo "ES7c Selftest bestanden.\n";
exit(0);
