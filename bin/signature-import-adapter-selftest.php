<?php
declare(strict_types=1);

/**
 * ES7b: Adapter Import → media + appendNew source=import.
 *
 * Usage: php bin/signature-import-adapter-selftest.php
 */

function assertEs7b(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);

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

    /** @var array<string, array{bin: string, mime: string, name: string}> */
    public static array $files = [];

    public static function storeBinary(string $mediaId, string $storedName, string $binary, string $mime): array
    {
        self::$files[$mediaId] = ['bin' => $binary, 'mime' => $mime, 'name' => $storedName];
        $ext = str_contains($mime, 'webp') ? 'webp' : 'png';

        return [
            'stored_name' => $storedName,
            'mime_type' => $mime,
            'extension' => $ext,
            'width' => 1,
            'height' => 1,
            'size_bytes' => strlen($binary),
        ];
    }

    public static function absolutePath(string $mediaId, string $storedName): string
    {
        return sys_get_temp_dir() . '/dg-es7b/' . $mediaId . '/' . $storedName;
    }

    public static function publicUrl(string $mediaId, ?int $version = null): string
    {
        return '/app/media?id=' . rawurlencode($mediaId);
    }
}

final class MediaRepository
{
    /** @var array<string, array<string, mixed>> */
    public static array $rows = [];

    public static function ensureTables(): void
    {
    }

    /** @param array<string, mixed> $stored */
    public static function insert(string $mediaId, array $stored, ?int $createdBy = null): void
    {
        self::$rows[$mediaId] = $stored;
    }

    public static function delete(string $mediaId): void
    {
        unset(self::$rows[$mediaId], MediaStorage::$files[$mediaId]);
    }

    /** @return array<string, mixed>|null */
    public static function find(string $mediaId): ?array
    {
        return self::$rows[$mediaId] ?? null;
    }
}

require $root . '/src/Media/MediaId.php';
require $root . '/src/User/UserSignatureStore.php';
require $root . '/src/User/SignatureImportProvider.php';
require $root . '/src/User/SignatureImportService.php';

assertEs7b(class_exists('SignatureImportService', false), 'SignatureImportService ladbar');
assertEs7b(
    (new ReflectionClass(UserSignatureStore::class))->hasMethod('appendFromImport'),
    'appendFromImport vorhanden'
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
assertEs7b(is_string($png) && str_starts_with($png, "\x89PNG"), 'PNG Fixture');

$threw = false;
try {
    UserSignatureStore::appendFromImport(1, 1, $png, 'image/jpeg', 'ext-1');
} catch (InvalidArgumentException $e) {
    $threw = str_contains($e->getMessage(), 'PNG') || str_contains($e->getMessage(), 'WebP');
}
assertEs7b($threw, 'appendFromImport lehnt JPEG ab');

$threw = false;
try {
    SignatureImportService::importPayload(1, 1, [
        'external_ref' => '',
        'mime' => 'image/png',
        'content' => $png,
    ]);
} catch (InvalidArgumentException $e) {
    $threw = true;
}
assertEs7b($threw, 'importPayload ohne external_ref wirft');

$id1 = SignatureImportService::importPayload(7, 7, [
    'external_ref' => 'ext-sig-001',
    'mime' => 'image/png',
    'content' => $png,
    'captured_at' => '2026-11-08 12:00:00',
], 7);
assertEs7b($id1['id'] > 0, 'erster Import speichert id');
assertEs7b($id1['source_ref'] === 'ext-sig-001', 'source_ref übernommen');
assertEs7b(MediaId::isValid($id1['media_id']), 'media_id gültig');

$active1 = UserSignatureStore::activeForUser(7);
assertEs7b($active1 !== null && $active1['source'] === 'import', 'aktive source=import');
assertEs7b($active1['source_ref'] === 'ext-sig-001', 'aktive source_ref');
assertEs7b($active1['valid_to'] === null, 'erste Zeile aktiv');

// Re-Import gleiche external_ref → neue Zeile (ES7c-Verhalten vorbereitet)
$id2 = SignatureImportService::importPayload(7, 7, [
    'external_ref' => 'ext-sig-001',
    'mime' => 'image/png',
    'content' => $png,
], 7);
assertEs7b($id2['id'] > $id1['id'], 'Re-Import neue id');
$active2 = UserSignatureStore::activeForUser(7);
assertEs7b((int) $active2['id'] === $id2['id'], 'zweite Zeile ist aktiv');
$list = UserSignatureStore::listForContact(7);
assertEs7b(count($list) === 2, 'Historie hat 2 Zeilen nach Re-Import');
assertEs7b($list[1]['valid_to'] !== null, 'alte Zeile geschlossen');

final class Es7bStubProvider implements SignatureImportProvider
{
    public bool $configured = true;
    /** @var array<string, mixed>|null */
    public ?array $payload = null;

    public function name(): string
    {
        return 'stub';
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function fetchLatestForUser(int $userId, int $contactId): ?array
    {
        return $this->payload;
    }
}

$stub = new Es7bStubProvider();
$stub->payload = null;
assertEs7b(
    SignatureImportService::importFromProvider($stub, 8, 8) === null,
    'Provider ohne Treffer → null'
);

$stub->payload = [
    'external_ref' => 'stub-ref-9',
    'mime' => 'image/png',
    'content' => $png,
];
$fromProv = SignatureImportService::importFromProvider($stub, 8, 8, 8);
assertEs7b(is_array($fromProv) && $fromProv['provider'] === 'stub', 'Provider-Import liefert provider=stub');
assertEs7b($fromProv['source_ref'] === 'stub-ref-9', 'Provider source_ref');

$stub->configured = false;
$threw = false;
try {
    SignatureImportService::importFromProvider($stub, 8, 8);
} catch (RuntimeException $e) {
    $threw = true;
}
assertEs7b($threw, 'unkonfigurierter Provider wirft');

$svcSrc = (string) file_get_contents($root . '/src/User/SignatureImportService.php');
assertEs7b(
    !preg_match('/docusign|adobe|signaturit|hello.?sign|skribble/i', $svcSrc),
    'kein Vendor-Hardcode im Adapter'
);

echo "ES7b Selftest bestanden.\n";
exit(0);
