<?php
declare(strict_types=1);

/**
 * Prüft: Registry-Aliase → KichelNavIndex trifft Backup/Telefon/…
 * Usage: php bin/kichel-nav-alias-selftest.php
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

$adminRole = (string) App::config('roles.admin', 'administrator');
$user = new User(1, 'admin', 'Administrator', '', [$adminRole], true);
KichelNavIndex::clearCache();

$cases = [
    'Wo finde ich Backup?' => 'settings:backup',
    'Datensicherung' => 'settings:backup',
    'Fritzbox anrufen' => 'telefon',
    'Brief schreiben' => 'dokumentenverfasser',
    'Anschaffungsrechner Leasing' => 'buchhaltung-anschaffungsrechner',
    'UStVA' => 'buchhaltung-ustva',
    'Software Design Farben' => 'settings:crm-darstellung',
];

$failed = 0;
foreach ($cases as $query => $expectId) {
    $tokens = KichelKnowledge::tokenize($query);
    $hits = KichelNavIndex::search($user, $query, $tokens, 5);
    $topId = (string) ($hits[0]['entry']['id'] ?? '');
    $ok = $topId === $expectId;
    if (!$ok) {
        $failed++;
    }
    $ids = array_map(static fn (array $h): string => (string) ($h['entry']['id'] ?? ''), $hits);
    echo ($ok ? 'OK  ' : 'FAIL') . '  ' . json_encode([
        'query' => $query,
        'expect' => $expectId,
        'top' => $topId,
        'hits' => $ids,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}

$backupTab = SettingsRegistry::allTabs()['backup'] ?? [];
$aliases = $backupTab['kichel_aliases'] ?? [];
$hasBackupAlias = in_array('backup', $aliases, true);
echo ($hasBackupAlias ? 'OK  ' : 'FAIL') . '  backup tab has kichel_aliases incl. backup'
    . ' → ' . json_encode($aliases, JSON_UNESCAPED_UNICODE) . "\n";
if (!$hasBackupAlias) {
    $failed++;
}

$menu = MenuRegistry::kichelAliases();
$hasTelefon = isset($menu['telefon']) && $menu['telefon'] !== [];
echo ($hasTelefon ? 'OK  ' : 'FAIL') . "  MenuRegistry::kichelAliases() has telefon\n";
if (!$hasTelefon) {
    $failed++;
}

exit($failed === 0 ? 0 : 1);
