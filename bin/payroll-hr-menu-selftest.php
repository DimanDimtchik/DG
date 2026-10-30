<?php
declare(strict_types=1);

/**
 * Menü: Lohn-Punkte unter HR, keine eigene Lohn-Sektion.
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

function assertHrMenu(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

assertHrMenu(method_exists(MenuRegistry::class, 'lohnMenuItems'), 'lohnMenuItems existiert');
assertHrMenu(method_exists(MenuRegistry::class, 'hrSection'), 'hrSection existiert');

$ref = new ReflectionMethod(MenuRegistry::class, 'lohnSection');
assertHrMenu($ref->getDocComment() !== false && str_contains((string) $ref->getDocComment(), 'deprecated'), 'lohnSection als deprecated dokumentiert');

$src = (string) file_get_contents(DG_ROOT . '/src/Menu/MenuRegistry.php');
assertHrMenu(str_contains($src, 'foreach (self::lohnMenuItems($user) as $lohnItem)'), 'hrSection hängt Lohn-Items ein');
assertHrMenu(
    (bool) preg_match('/function lohnSection\(User \$user\): \?array\s*\{\s*unset\(\$user\);\s*return null;\s*\}/s', $src),
    'lohnSection gibt immer null zurück'
);

$layout = (string) file_get_contents(DG_ROOT . '/views/layout/app.php');
assertHrMenu(!str_contains($layout, 'dg-sidebar-lohn'), 'Layout ohne eigene Lohn-Sektion');
assertHrMenu(str_contains($layout, 'dg-sidebar-hr'), 'Layout behält HR-Sektion');

echo "HR-Lohn-Menü Selftest bestanden.\n";
