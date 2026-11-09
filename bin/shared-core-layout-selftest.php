<?php
declare(strict_types=1);

/**
 * SC2c: Guard Shared-Core-Vorlagen + optionale Live-Pfade.
 *
 *   php bin/shared-core-layout-selftest.php
 *
 * Optional (GEX45):
 *   DG_SHARED_ROOT=/var/www/dg-crm/shared
 *   DG_INSTANCE_ROOT=/var/www/dg-crm/instances/ganz-om.de
 */
$root = dirname(__DIR__);
$fail = 0;

function sc_ok(bool $cond, string $msg): void
{
    global $fail;
    if ($cond) {
        echo "OK  {$msg}\n";
    } else {
        echo "FAIL {$msg}\n";
        $fail++;
    }
}

$example = $root . '/deploy/shared-core/public-index.php.example';
$readme = $root . '/deploy/shared-core/README.md';

sc_ok(is_readable($example), 'deploy/shared-core/public-index.php.example lesbar');
sc_ok(is_readable($readme), 'deploy/shared-core/README.md lesbar');

$body = is_readable($example) ? (string) file_get_contents($example) : '';
sc_ok(str_contains($body, 'DG_INSTANCE_ROOT'), 'Stub setzt DG_INSTANCE_ROOT');
sc_ok(str_contains($body, 'dirname(__DIR__)'), 'Stub: Instanz = Parent von public/');
sc_ok(str_contains($body, 'DG_SHARED_ROOT'), 'Stub erlaubt DG_SHARED_ROOT');
sc_ok(str_contains($body, '/var/www/dg-crm/shared'), 'Stub Default Shared-Pfad');
sc_ok(str_contains($body, 'require $sharedIndex'), 'Stub require shared/index.php');

$localsInDeploy = glob($root . '/deploy/shared-core/*.local.php') ?: [];
sc_ok($localsInDeploy === [], 'deploy/shared-core ohne *.local.php');

$sharedEnv = getenv('DG_SHARED_ROOT');
$instanceEnv = getenv('DG_INSTANCE_ROOT');

if ($sharedEnv === false || $sharedEnv === '') {
    echo "SKIP Live-Shared: DG_SHARED_ROOT nicht gesetzt\n";
} else {
    $shared = rtrim(str_replace('\\', '/', $sharedEnv), '/');
    sc_ok(is_dir($shared), "Shared-Verzeichnis existiert: {$shared}");
    sc_ok(
        is_readable($shared . '/bootstrap.php') || is_readable($shared . '/index.php'),
        'Shared hat bootstrap.php oder index.php'
    );
    $forbidden = glob($shared . '/config/*.local.php') ?: [];
    sc_ok($forbidden === [], 'Shared config/ ohne *.local.php');
    if (is_dir($shared . '/storage') && !is_link($shared . '/storage')) {
        $files = array_values(array_filter(
            glob($shared . '/storage/{*,.*}', GLOB_BRACE) ?: [],
            static fn (string $p): bool => !in_array(basename($p), ['.', '..'], true)
        ));
        sc_ok($files === [], 'Shared storage/ leer (Instanzdaten gehören auf NVMe B)');
    } else {
        echo "OK  Shared storage/ fehlt oder ist Symlink\n";
    }
}

if ($instanceEnv === false || $instanceEnv === '') {
    echo "SKIP Live-Instanz: DG_INSTANCE_ROOT nicht gesetzt\n";
} else {
    $inst = rtrim(str_replace('\\', '/', $instanceEnv), '/');
    sc_ok(is_dir($inst), "Instanz-Verzeichnis lesbar: {$inst}");
    sc_ok(
        is_dir($inst . '/public') || is_readable($inst . '/public/index.php'),
        'Instanz public/ vorhanden'
    );
    sc_ok(is_dir($inst . '/config'), 'Instanz config/ vorhanden');
}

if ($fail > 0) {
    fwrite(STDERR, "shared-core-layout-selftest: {$fail} Fehler\n");
    exit(1);
}

echo "shared-core-layout-selftest: OK\n";
exit(0);
