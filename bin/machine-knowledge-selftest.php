<?php
declare(strict_types=1);

/**
 * Guard E2 Maschinenwissen: Migration, Repository, POST, UI-Suchen.
 * Kein Live-Upload-E2E.
 *
 * Usage: php bin/machine-knowledge-selftest.php
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

function assertMk(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

$migration = DG_ROOT . '/database/migrations/143_machine_knowledge_links.sql';
assertMk(is_readable($migration), 'Migration 143_machine_knowledge_links.sql vorhanden');
$migBody = (string) file_get_contents($migration);
assertMk(str_contains($migBody, 'dg_machine_knowledge_links'), 'Tabelle dg_machine_knowledge_links');
assertMk(str_contains($migBody, 'error_codes_json'), 'Spalte error_codes_json');
assertMk(str_contains($migBody, 'fk_machine_knowledge_work_center'), 'FK Work Center');
assertMk(str_contains($migBody, 'fk_machine_knowledge_academy_module'), 'FK Akademie-Modul');

$repoFile = DG_ROOT . '/src/Recipe/MachineKnowledgeRepository.php';
assertMk(is_readable($repoFile), 'MachineKnowledgeRepository vorhanden');
$repoBody = (string) file_get_contents($repoFile);
assertMk(str_contains($repoBody, 'function save'), 'save vorhanden');
assertMk(str_contains($repoBody, 'function searchByErrorCode'), 'searchByErrorCode vorhanden');
assertMk(str_contains($repoBody, 'function deactivate'), 'deactivate vorhanden');
assertMk(str_contains($repoBody, "storage/"), 'PDF-Pfad storage/ geprüft');

$autoload = (string) file_get_contents(DG_ROOT . '/src/autoload.php');
assertMk(str_contains($autoload, 'MachineKnowledgeRepository.php'), 'Autoload Repository');

$index = (string) file_get_contents(DG_ROOT . '/index.php');
assertMk(str_contains($index, "isset(\$_POST['machine_knowledge_save'])"), 'POST machine_knowledge_save');
assertMk(str_contains($index, "isset(\$_POST['machine_knowledge_deactivate'])"), 'POST machine_knowledge_deactivate');
assertMk(
    (bool) preg_match(
        '/machine_knowledge_save[\s\S]{0,600}?MenuRegistry::canAccess\(\$user, \'rezeptur-maschine-form\'\)/',
        $index
    ) || (bool) preg_match(
        '/MenuRegistry::canAccess\(\$user, \'rezeptur-maschine-form\'\)[\s\S]{0,500}?machine_knowledge_save/',
        $index
    ),
    'POST hinter rezeptur-maschine-form-Gate'
);

$formView = (string) file_get_contents(DG_ROOT . '/views/modules/rezeptur-maschine-form.php');
assertMk(str_contains($formView, 'id="dg-machine-knowledge"'), 'UI Wissen & Störungen an Maschinen-Form');
assertMk(str_contains($formView, 'machine_knowledge_save'), 'Speichern-Button Wissen');
assertMk(str_contains($formView, 'knowledge_error'), 'Fehlercode-Filter an Form');

$listView = (string) file_get_contents(DG_ROOT . '/views/modules/rezeptur-maschinen.php');
assertMk(str_contains($listView, 'id="dg-machine-knowledge-search"'), 'Zentrale Fehlercode-Suche');
assertMk(str_contains($listView, 'Untertitel') || str_contains($listView, 'VTT'), 'Akademie-Clip-Hinweis');

// Normalize / Suche ohne DB
$row = MachineKnowledgeRepository::normalize([
    'id' => 1,
    'title' => 'Hydraulik',
    'content_type' => 'manual_pdf',
    'storage_path' => 'storage/manuals/x.pdf',
    'error_codes_json' => '["err_hydraulik_404","E42"]',
    'is_active' => 1,
]);
assertMk($row['error_codes'] === ['ERR_HYDRAULIK_404', 'E42'], 'normalize Fehlercodes uppercase');
assertMk(in_array('manual_pdf', MachineKnowledgeRepository::contentTypes(), true), 'contentTypes enthält manual_pdf');

$hitsEmpty = MachineKnowledgeRepository::searchByErrorCode('', null, true);
assertMk($hitsEmpty === [], 'leerer Fehlercode → keine Treffer');

$listNoDb = MachineKnowledgeRepository::list(1, true);
assertMk(is_array($listNoDb), 'list ohne DB liefert Array');

echo "Machine-Knowledge Selftest bestanden.\n";
