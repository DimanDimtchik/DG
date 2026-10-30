<?php
declare(strict_types=1);

/**
 * Lohn L7c Selftest — UI-Hilfen Checkliste/Rollen (ohne DB).
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

function assertL7c(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

$labels = PayrollFibuService::roleLabels();
assertL7c(count($labels) === 5, '5 Kontenrollen');
assertL7c(isset($labels['expense_wages'], $labels['liability_tax']), 'Rollen Keys');

$list = PayrollFibuService::auditorChecklist();
assertL7c(count($list) >= 5, 'Checkliste Einträge');
$joined = implode(' ', $list);
assertL7c(str_contains($joined, 'locked'), 'Checkliste locked');
assertL7c(str_contains($joined, 'Journal') || str_contains($joined, 'Freigabe'), 'Checkliste Freigabe/Journal');
assertL7c(str_contains($joined, 'USt') || str_contains($joined, 'SEPA'), 'Checkliste Abgrenzung');

assertL7c(method_exists('PayrollRunAccess', 'canPostFibu'), 'canPostFibu');

$lohn = PayrollEmployerSettings::normalizeLohn([
    'payroll_fibu_live' => false,
    'fibu_accounts' => [
        'expense_wages' => '4120',
        'liability_sv' => '1742',
    ],
]);
assertL7c($lohn['payroll_fibu_live'] === false, 'Live default aus');
assertL7c(($lohn['fibu_accounts']['expense_wages'] ?? '') === '4120', 'Settings Override Form');

echo "L7c Selftest bestanden.\n";
exit(0);
