<?php
declare(strict_types=1);

/**
 * ES7a: SignatureImportProvider Platzhalter + Doc.
 *
 * Usage: php bin/signature-import-provider-selftest.php
 */

function assertEs7a(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$iface = $root . '/src/User/SignatureImportProvider.php';
$doc = $root . '/docs/SIGNATURE-IMPORT-PROVIDER.md';
$autoload = $root . '/src/autoload.php';

assertEs7a(is_file($iface), 'SignatureImportProvider.php vorhanden');
assertEs7a(is_file($doc), 'SIGNATURE-IMPORT-PROVIDER.md vorhanden');

$ifaceSrc = (string) file_get_contents($iface);
$docSrc = (string) file_get_contents($doc);
$autoSrc = (string) file_get_contents($autoload);

assertEs7a(str_contains($ifaceSrc, 'interface SignatureImportProvider'), 'interface SignatureImportProvider');
assertEs7a(str_contains($ifaceSrc, 'function name()'), 'Methode name()');
assertEs7a(str_contains($ifaceSrc, 'function isConfigured()'), 'Methode isConfigured()');
assertEs7a(str_contains($ifaceSrc, 'function fetchLatestForUser'), 'Methode fetchLatestForUser()');
assertEs7a(str_contains($ifaceSrc, 'ES7b') || str_contains($ifaceSrc, 'appendNew'), 'Verweis auf Store/ES7b');
assertEs7a(
    !preg_match('/function\s+(import|save|append|store)\s*\(/i', $ifaceSrc),
    'kein Speicher-/Adapter-Methode im Interface (ES7b)'
);

assertEs7a(str_contains($docSrc, 'source_ref'), 'Doc nennt source_ref');
assertEs7a(str_contains($docSrc, 'source') && str_contains($docSrc, 'import'), 'Doc nennt source=import');
assertEs7a(str_contains($docSrc, 'appendNew'), 'Doc nennt appendNew');
assertEs7a(str_contains($docSrc, 'ES7b'), 'Doc grenzt ES7b ab');

assertEs7a(
    str_contains($autoSrc, "'SignatureImportProvider'"),
    'Autoload-Eintrag SignatureImportProvider'
);

require $iface;
assertEs7a(interface_exists('SignatureImportProvider', false), 'Interface ladbar');
$ref = new ReflectionClass(SignatureImportProvider::class);
assertEs7a($ref->isInterface(), 'Reflection: Interface');
assertEs7a($ref->hasMethod('fetchLatestForUser'), 'Reflection: fetchLatestForUser');

require $root . '/src/Media/MediaId.php';
require $root . '/src/User/UserSignatureStore.php';
assertEs7a(in_array('import', UserSignatureStore::SOURCES, true), 'Store SOURCES enthält import');

echo "ES7a Selftest bestanden.\n";
exit(0);
