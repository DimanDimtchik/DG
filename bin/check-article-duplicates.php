<?php
declare(strict_types=1);

/** CLI: Doppelte Leistungen in dg_calendar_articles finden. */
require dirname(__DIR__) . '/bootstrap.php';

if (!Database::isConfigured()) {
    fwrite(STDERR, "Datenbank nicht konfiguriert.\n");
    exit(1);
}

$pdo = Database::pdo();

$total = (int) $pdo->query('SELECT COUNT(*) FROM dg_calendar_articles')->fetchColumn();
echo "Leistungen gesamt: {$total}\n\n";

$dupTitles = $pdo->query(
    'SELECT title, COUNT(*) AS cnt, GROUP_CONCAT(id ORDER BY id) AS ids, GROUP_CONCAT(article_number ORDER BY id) AS numbers
     FROM dg_calendar_articles
     GROUP BY title
     HAVING cnt > 1
     ORDER BY cnt DESC, title'
)->fetchAll(PDO::FETCH_ASSOC);

$dupNumbers = $pdo->query(
    'SELECT article_number, COUNT(*) AS cnt, GROUP_CONCAT(id ORDER BY id) AS ids
     FROM dg_calendar_articles
     WHERE article_number <> \'\'
     GROUP BY article_number
     HAVING cnt > 1
     ORDER BY cnt DESC'
)->fetchAll(PDO::FETCH_ASSOC);

$impCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM dg_calendar_articles WHERE article_number LIKE 'IMP-%'"
)->fetchColumn();

$nonImpCount = $total - $impCount;

echo "Mit IMP-Nummer (Import ohne Artikelnr.): {$impCount}\n";
echo "Mit eigener Artikelnummer (z. B. K/M/P): {$nonImpCount}\n\n";

$impDupTitles = $pdo->query(
    "SELECT title, COUNT(*) AS cnt, GROUP_CONCAT(id ORDER BY id) AS ids, GROUP_CONCAT(article_number ORDER BY id) AS numbers
     FROM dg_calendar_articles
     WHERE article_number LIKE 'IMP-%'
     GROUP BY title
     HAVING cnt > 1
     ORDER BY cnt DESC, title"
)->fetchAll(PDO::FETCH_ASSOC);

echo 'Doppelte Bezeichnungen (nur IMP-/Datei 5): ' . (count($impDupTitles) === 0 ? 'keine' : count($impDupTitles) . " Gruppe(n)") . "\n";
foreach ($impDupTitles as $row) {
    echo '  - "' . $row['title'] . '" (' . $row['cnt'] . 'x) IDs ' . $row['ids'] . ' Nr. ' . $row['numbers'] . "\n";
}

echo "\n";

if ($dupTitles === []) {
    echo "Doppelte Bezeichnungen: keine\n";
} else {
    echo 'Doppelte Bezeichnungen: ' . count($dupTitles) . " Gruppe(n)\n";
    foreach ($dupTitles as $row) {
        echo '  - "' . $row['title'] . '" (' . $row['cnt'] . 'x) IDs ' . $row['ids'] . ' Nr. ' . $row['numbers'] . "\n";
    }
}

echo "\n";

if ($dupNumbers === []) {
    echo "Doppelte Artikelnummern: keine\n";
} else {
    echo 'Doppelte Artikelnummern: ' . count($dupNumbers) . " Gruppe(n)\n";
    foreach ($dupNumbers as $row) {
        echo '  - ' . $row['article_number'] . ' (' . $row['cnt'] . 'x) IDs ' . $row['ids'] . "\n";
    }
}

// Normalized title duplicates (collapsed whitespace, first 255 chars)
$all = $pdo->query('SELECT id, article_number, title FROM dg_calendar_articles ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$byNorm = [];
foreach ($all as $row) {
    $norm = CalendarArticleValidator::normalizeImportTitle((string) $row['title']);
    $byNorm[$norm][] = $row;
}
$fuzzyDupes = array_filter($byNorm, static fn (array $items): bool => count($items) > 1);

echo "\n";
if ($fuzzyDupes === []) {
    echo "Ähnliche Bezeichnungen (normalisiert): keine\n";
} else {
    echo 'Ähnliche Bezeichnungen (normalisiert): ' . count($fuzzyDupes) . " Gruppe(n)\n";
    foreach ($fuzzyDupes as $norm => $items) {
        echo '  - "' . $norm . "\"\n";
        foreach ($items as $item) {
            echo '      ID ' . $item['id'] . ' | ' . $item['article_number'] . ' | "' . $item['title'] . "\"\n";
        }
    }
}

exit(($dupTitles === [] && $dupNumbers === [] && $fuzzyDupes === []) ? 0 : 2);
