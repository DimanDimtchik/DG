<?php
declare(strict_types=1);

/**
 * AP1 Guard: Migration, Autoload, Pack/Unpack, optional Live-CRUD (wenn DB konfiguriert).
 *
 * Usage: php bin/article-properties-ap1-selftest.php
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

function assertAp1(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

$migration = DG_ROOT . '/database/migrations/162_article_properties.sql';
assertAp1(is_readable($migration), 'Migration 162_article_properties.sql vorhanden');
$migBody = (string) file_get_contents($migration);
foreach ([
    'dg_article_property_groups',
    'dg_article_properties',
    'dg_article_property_values',
    'dg_instance_property_groups',
    'uq_article_property_value',
    'value_text',
    'value_integer',
    'value_decimal',
    'value_boolean',
    'value_date',
    'value_json',
    'ON DELETE RESTRICT',
] as $needle) {
    assertAp1(str_contains($migBody, $needle), "Migration enthält {$needle}");
}

$repoFile = DG_ROOT . '/src/Catalog/ArticlePropertyRepository.php';
assertAp1(is_readable($repoFile), 'ArticlePropertyRepository vorhanden');
$repoBody = (string) file_get_contents($repoFile);
foreach ([
    'function saveGroup',
    'function deleteGroup',
    'function setGroupActive',
    'function listActiveDefinitions',
    'function saveProperty',
    'function setValue',
    'function packValue',
    'Aktive Gruppe kann nicht gelöscht werden',
    'gespeicherte Werte und kann nicht gelöscht werden',
] as $needle) {
    assertAp1(str_contains($repoBody, $needle), "Repository: {$needle}");
}

$autoload = (string) file_get_contents(DG_ROOT . '/src/autoload.php');
assertAp1(str_contains($autoload, 'ArticlePropertyRepository.php'), 'Autoload Repository');

$runner = (string) file_get_contents(DG_ROOT . '/src/Database/MigrationRunner.php');
assertAp1(str_contains($runner, '162_article_properties.sql'), 'MigrationRunner kennt 162');

$plan = (string) file_get_contents(DG_ROOT . '/docs/ARTIKEL-EIGENSCHAFTEN-PLAN.md');
assertAp1(str_contains($plan, '**AP1**'), 'Plan erwähnt AP1');

// Pack / Unpack ohne DB
$packedDec = ArticlePropertyRepository::packValue('decimal', '12,5');
assertAp1($packedDec['empty'] === false, 'pack decimal nicht leer');
assertAp1($packedDec['value_decimal'] === '12.500000', 'pack decimal Komma→Punkt');

$packedBool = ArticlePropertyRepository::packValue('boolean', 'ja');
assertAp1($packedBool['value_boolean'] === 1, 'pack boolean ja');

$packedMulti = ArticlePropertyRepository::packValue('multi_select', ['A', 'B', 'A']);
assertAp1($packedMulti['empty'] === false, 'pack multi_select');
assertAp1($packedMulti['value_json'] === '["A","B"]', 'pack multi_select unique JSON');

$emptyText = ArticlePropertyRepository::packValue('text', '  ');
assertAp1($emptyText['empty'] === true, 'leerer Text → empty');

$unpacked = ArticlePropertyRepository::unpackValue([
    'data_type' => 'integer',
    'value_integer' => 42,
    'value_text' => null,
    'value_decimal' => null,
    'value_boolean' => null,
    'value_date' => null,
    'value_json' => null,
]);
assertAp1($unpacked === 42, 'unpack integer');

assertAp1(
    ArticlePropertyRepository::normalizeCode('KFZ Handel!') === 'kfz_handel',
    'normalizeCode'
);

if (!Database::isConfigured()) {
    echo "SKIP: Live-CRUD (keine DB-Konfiguration in dieser Umgebung)\n";
    echo "AP1 Selftest: Struktur + Pack OK\n";
    exit(0);
}

MigrationRunner::runPending();
assertAp1(ArticlePropertyRepository::tablesReady(), 'Tabellen nach Migration bereit');

$suffix = 'ap1_' . bin2hex(random_bytes(3));
$groupA = ArticlePropertyRepository::saveGroup([
    'code' => $suffix . '_gastro',
    'label' => 'Selftest Gastronomie',
    'sort_order' => 10,
]);
$groupB = ArticlePropertyRepository::saveGroup([
    'code' => $suffix . '_kfz',
    'label' => 'Selftest KFZ',
    'sort_order' => 20,
]);
assertAp1($groupA > 0 && $groupB > 0, 'Gruppen angelegt');

$propA = ArticlePropertyRepository::saveProperty([
    'group_id' => $groupA,
    'code' => 'allergene',
    'label' => 'Allergene',
    'data_type' => 'multi_select',
    'allowed_values' => ['Gluten', 'Milch'],
]);
$propB = ArticlePropertyRepository::saveProperty([
    'group_id' => $groupB,
    'code' => 'hubraum',
    'label' => 'Hubraum',
    'data_type' => 'decimal',
    'unit' => 'cm3',
]);
assertAp1($propA > 0 && $propB > 0, 'Eigenschaften angelegt');

ArticlePropertyRepository::setGroupActive($groupA, true);
ArticlePropertyRepository::setGroupActive($groupB, false);

$activeDefs = ArticlePropertyRepository::listActiveDefinitions();
$activeIds = array_map(static fn (array $r): int => (int) $r['id'], $activeDefs);
assertAp1(in_array($propA, $activeIds, true), 'Aktivierung: Gastro-Eigenschaft sichtbar');
assertAp1(!in_array($propB, $activeIds, true), 'Aktivierung: KFZ-Eigenschaft ausgeblendet');

$pdo = Database::pdo();
$articleNumber = 'TST-AP1-' . strtoupper(substr($suffix, -6));
$pdo->prepare(
    "INSERT INTO dg_calendar_articles
     (article_number, catalog_kind, title, unit, tax_type, price_gross, work_minutes, is_active)
     VALUES (:num, 'product', 'Selftest AP1', 'Stück', 'ust19', 1.00, 0, 1)"
)->execute(['num' => $articleNumber]);
$articleId = (int) $pdo->lastInsertId();
assertAp1($articleId > 0, 'Testartikel angelegt');

ArticlePropertyRepository::setValue($articleId, $propA, ['Gluten', 'Milch']);
ArticlePropertyRepository::setValue($articleId, $propB, '1598');

$activeValues = ArticlePropertyRepository::valuesForArticle($articleId, true);
$allValues = ArticlePropertyRepository::valuesForArticle($articleId, false);
assertAp1(count($activeValues) === 1, 'Werte-Filter: nur aktive Gruppe');
assertAp1(count($allValues) === 2, 'Werte ohne Filter: beide Gruppen');
assertAp1($activeValues[0]['value'] === ['Gluten', 'Milch'], 'Multi-Select-Wert korrekt');

$blockedActive = ArticlePropertyRepository::deleteGroup($groupA);
assertAp1($blockedActive['ok'] === false, 'Löschschutz: aktive Gruppe blockiert');
assertAp1(
    str_contains((string) ($blockedActive['error'] ?? ''), 'Aktive Gruppe'),
    'Löschschutz-Meldung aktiv'
);

ArticlePropertyRepository::setGroupActive($groupA, false);
$blockedValues = ArticlePropertyRepository::deleteGroup($groupA);
assertAp1($blockedValues['ok'] === false, 'Löschschutz: Gruppe mit Werten blockiert');
assertAp1(
    str_contains((string) ($blockedValues['error'] ?? ''), 'gespeicherte Werte'),
    'Löschschutz-Meldung Werte'
);

// Aufräumen: Werte → Eigenschaften → Aktivierung → Gruppen → Artikel
$pdo->prepare('DELETE FROM dg_article_property_values WHERE article_id = :id')->execute(['id' => $articleId]);
$pdo->prepare('DELETE FROM dg_article_properties WHERE id = :id')->execute(['id' => $propA]);
$pdo->prepare('DELETE FROM dg_article_properties WHERE id = :id')->execute(['id' => $propB]);
$pdo->prepare('DELETE FROM dg_instance_property_groups WHERE group_id = :id')->execute(['id' => $groupA]);
$pdo->prepare('DELETE FROM dg_instance_property_groups WHERE group_id = :id')->execute(['id' => $groupB]);
$delA = ArticlePropertyRepository::deleteGroup($groupA);
$delB = ArticlePropertyRepository::deleteGroup($groupB);
assertAp1($delA['ok'] === true && $delB['ok'] === true, 'Gruppen nach Cleanup löschbar');
$pdo->prepare('DELETE FROM dg_calendar_articles WHERE id = :id')->execute(['id' => $articleId]);

echo "AP1 Selftest: Struktur + Live-CRUD OK\n";
exit(0);
