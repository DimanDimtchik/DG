<?php
declare(strict_types=1);

/** CLI: Datei-Parsing für Leistungs-Import testen (ohne DB-Schreiben). */
require dirname(__DIR__) . '/bootstrap.php';

$path = $argv[1] ?? '';
if ($path === '' || !is_readable($path)) {
    fwrite(STDERR, "Usage: php bin/dry-run-article-import.php <file>\n");
    exit(1);
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$rows = CalendarArticleImportReader::readFile($path, $ext);

$ref = new ReflectionClass(CalendarArticleImporter::class);
$normHeader = $ref->getMethod('normalizeHeader');
$normHeader->setAccessible(true);
$mapCols = $ref->getMethod('mapImportColumns');
$mapCols->setAccessible(true);
$sanitize = $ref->getMethod('sanitizeImportRow');
$sanitize->setAccessible(true);
$headerUsesNet = $ref->getMethod('headerUsesNetPrice');
$headerUsesNet->setAccessible(true);
$isEmpty = $ref->getMethod('isEmptyRow');
$isEmpty->setAccessible(true);

$header = array_map(static fn (string $v): string => $normHeader->invoke(null, $v), $rows[0]);
$map = $mapCols->invoke(null, $header);
$priceIsNet = $headerUsesNet->invoke(null, $header);

echo 'Datei: ' . basename($path) . ' (' . $ext . ')' . PHP_EOL;
echo 'Zeilen gesamt: ' . count($rows) . ' (inkl. Kopfzeile)' . PHP_EOL;
echo 'Preis-Spalte: ' . ($priceIsNet ? 'Netto' : 'Brutto') . PHP_EOL;

$autoNumber = 0;
$ok = 0;
$errors = [];

for ($i = 1, $count = count($rows); $i < $count; $i++) {
    $line = $rows[$i];
    if ($isEmpty->invoke(null, $line)) {
        continue;
    }
    $rawRow = [];
    foreach ($map as $field => $colIndex) {
        if ($colIndex !== null && isset($line[$colIndex])) {
            $rawRow[$field] = $line[$colIndex];
        }
    }
    try {
        $sanitize->invokeArgs(null, [$rawRow, &$autoNumber, $priceIsNet, 0]);
        $ok++;
    } catch (Throwable $e) {
        $errors[] = 'Zeile ' . ($i + 1) . ': ' . $e->getMessage();
    }
}

echo "Verarbeitbar: {$ok}" . PHP_EOL;
echo 'Fehler: ' . count($errors) . PHP_EOL;
foreach (array_slice($errors, 0, 10) as $err) {
    echo '  - ' . $err . PHP_EOL;
}

exit($errors === [] ? 0 : 1);
