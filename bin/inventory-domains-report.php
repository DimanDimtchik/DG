<?php
declare(strict_types=1);

/**
 * Inventory domains under All-Inkl for printable report.
 * php inventory-domains-report.php > domains-report.json
 */
$root = '/www/htdocs/w0217246';

$purposeHints = [
    '__ROOT__' => 'Live-Website & CRM von ganz-soft.de (Document-Root des Accounts)',
    'dg.ganz-om.de' => 'CRM-Master, Update-Server, KDV-Zentrale',
    'ganz-soft.de' => 'CRM-Spiegelordner (Live-Traffic läuft über den Account-Root, nicht diesen Ordner)',
    'kontur-cosmetics.de' => 'Kunden-CRM Kontur Cosmetics',
    'shop.ganz-soft.de' => 'SaaS-Shop zum Verkauf der CRM-Tarife (PHP, Stripe folgt)',
    'ganz-om.de' => 'WordPress-Marke ganz om',
    'dietrichganz.de' => 'Nur Ordner im Webspace (All-Inkl-Platzhalter). Domain derzeit NICHT registriert / kein DNS – gehört Ihnen aktuell nicht als aktive Domain.',
    'cloud.ganz-om.de' => 'Nextcloud (Datei-/Cloud-Speicher)',
    'dg-user.ganz-soft.de' => 'License-Server / API (JSON), kein CRM-Frontend',
    'hr.ganz-soft.de' => 'WordPress HR/Personal (Alt oder Vorbereitung)',
    'kasse.ganz-soft.de' => 'WordPress Kasse/POS (Alt oder Vorbereitung)',
    'terminkalender.ganz-soft.de' => 'WordPress Terminkalender (Alt/Demo)',
    'wg.ganz-soft.de' => 'Wohngemeinschaft-App / Experimente',
    'ganz-soft-shared' => 'Gemeinsame Dateien (kein öffentlicher Dienst)',
    'cursor-transfer' => 'Deploy-Zwischenablage (kein öffentlicher Dienst)',
    'wp-backup-archive' => 'WordPress-Backup-Archiv',
    'admin' => 'Interne Admin-/Plugin-Ablage',
];

function isShopPhp(string $path): bool
{
    return is_file($path . '/config/plans.php')
        && is_file($path . '/bootstrap.php')
        && is_dir($path . '/src');
}

function isCrm(string $path): bool
{
    if (isShopPhp($path)) {
        return false;
    }

    return is_file($path . '/index.php')
        && is_file($path . '/bootstrap.php')
        && is_dir($path . '/src')
        && is_dir($path . '/views')
        && is_dir($path . '/config');
}

function isWordpress(string $path): bool
{
    return is_file($path . '/wp-config.php') || is_dir($path . '/wp-includes');
}

function isNextcloud(string $path): bool
{
    return is_file($path . '/status.php') && is_dir($path . '/apps') && is_dir($path . '/3rdparty');
}

function detectStack(string $path): string
{
    if (isShopPhp($path)) {
        return 'Shop (PHP)';
    }
    if (isCrm($path)) {
        return 'CRM';
    }
    if (isNextcloud($path)) {
        return 'Nextcloud';
    }
    if (isWordpress($path)) {
        return 'WordPress';
    }
    if (is_file($path . '/login.html') && is_file($path . '/index.php')) {
        return 'License-Server / API';
    }
    if (is_file($path . '/index.html') && is_file($path . '/index.htm')) {
        return 'Web-App / Platzhalter';
    }
    if (is_file($path . '/index.php') || is_file($path . '/index.html') || is_file($path . '/index.htm')) {
        return 'Web (sonstig)';
    }

    return 'Ordner / Infrastruktur';
}

function readVersion(string $path): string
{
    $file = $path . '/config/version.php';
    if (!is_readable($file)) {
        return '';
    }
    $v = @include $file;

    return is_string($v) ? $v : '';
}

/**
 * Login-capable CRM users via bootstrapping the instance.
 *
 * @return array{mode: string, count: int, source: string, users: list<array{username: string, role: string, label: string}>}
 */
function readCrmUsers(string $path): array
{
    if (!isCrm($path)) {
        return ['mode' => 'none', 'count' => 0, 'source' => '', 'users' => []];
    }

    $code = <<<'PHP'
<?php
declare(strict_types=1);
define('DG_ROOT', $argv[1]);
require DG_ROOT . '/bootstrap.php';
$out = [];
foreach (UserRepository::all() as $user) {
    if (!RoleResolver::canAccessCrm($user)) {
        continue;
    }
    $roles = method_exists($user, 'roles') ? $user->roles() : [];
    if (!is_array($roles)) {
        $roles = [];
    }
    $role = $roles[0] ?? (property_exists($user, 'role') ? (string) $user->role : '');
    if ($role === '' && method_exists($user, 'role')) {
        $role = (string) $user->role();
    }
    $out[] = [
        'username' => (string) $user->username,
        'role' => (string) $role,
        'label' => (string) ($user->displayName ?? $user->display_name ?? $user->username),
    ];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
PHP;

    $tmp = sys_get_temp_dir() . '/dg-user-list-' . md5($path) . '.php';
    file_put_contents($tmp, $code);
    $cmd = 'php ' . escapeshellarg($tmp) . ' ' . escapeshellarg($path) . ' 2>/dev/null';
    $json = shell_exec($cmd);
    @unlink($tmp);
    $list = json_decode((string) $json, true);
    if (!is_array($list)) {
        // fallback users.php
        return readUsersFile($path);
    }
    usort($list, static fn($a, $b) => strcasecmp($a['username'], $b['username']));
    $count = count($list);
    if ($count > 5) {
        return ['mode' => 'count_only', 'count' => $count, 'source' => 'crm', 'users' => []];
    }

    return ['mode' => 'list', 'count' => $count, 'source' => 'crm', 'users' => $list];
}

/**
 * @return array{mode: string, count: int, source: string, users: list<array{username: string, role: string, label: string}>}
 */
function readUsersFile(string $path): array
{
    $file = $path . '/config/users.php';
    if (!is_readable($file)) {
        return ['mode' => 'none', 'count' => 0, 'source' => '', 'users' => []];
    }
    $data = @include $file;
    if (!is_array($data)) {
        return ['mode' => 'none', 'count' => 0, 'source' => '', 'users' => []];
    }
    $items = isset($data['users']) && is_array($data['users']) ? $data['users'] : $data;
    $list = [];
    foreach ($items as $key => $row) {
        if (!is_array($row)) {
            continue;
        }
        $username = (string) ($row['username'] ?? (is_string($key) ? $key : ''));
        if ($username === '') {
            continue;
        }
        $roles = $row['roles'] ?? [];
        $role = is_array($roles) ? (string) ($roles[0] ?? '') : (string) ($row['role'] ?? '');
        $list[] = [
            'username' => $username,
            'role' => $role,
            'label' => (string) ($row['display_name'] ?? $row['name'] ?? $username),
        ];
    }
    usort($list, static fn($a, $b) => strcasecmp($a['username'], $b['username']));
    $count = count($list);
    if ($count > 5) {
        return ['mode' => 'count_only', 'count' => $count, 'source' => 'users.php', 'users' => []];
    }

    return ['mode' => 'list', 'count' => $count, 'source' => 'users.php', 'users' => $list];
}

function publicUrl(string $name): string
{
    if ($name === '__ROOT__') {
        return 'https://ganz-soft.de';
    }
    if (!str_contains($name, '.')) {
        return '–';
    }

    return 'https://' . $name;
}

$entries = [];

$entries[] = [
    'domain' => 'ganz-soft.de',
    'folder' => '__ROOT__',
    'path' => $root,
    'url' => 'https://ganz-soft.de',
    'stack' => detectStack($root),
    'crm' => isCrm($root),
    'version' => readVersion($root),
    'purpose' => $purposeHints['__ROOT__'],
    'users' => readCrmUsers($root),
    'kind' => 'domain',
];

foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
    $name = basename($dir);
    if (str_contains($name, "\r") || str_contains($name, "\n")) {
        continue;
    }
    $isDomainLike = str_contains($name, '.') || in_array($name, [
        'ganz-soft-shared', 'cursor-transfer', 'wp-backup-archive', 'admin',
    ], true);
    if (!$isDomainLike) {
        continue;
    }
    $crm = isCrm($dir);
    $entries[] = [
        'domain' => $name,
        'folder' => $name,
        'path' => $dir,
        'url' => publicUrl($name),
        'stack' => detectStack($dir),
        'crm' => $crm,
        'version' => $crm ? readVersion($dir) : (isShopPhp($dir) ? 'Shop Phase 1' : ''),
        'purpose' => $purposeHints[$name] ?? 'Zweck bitte ergänzen',
        'users' => $crm ? readCrmUsers($dir) : ['mode' => 'none', 'count' => 0, 'source' => '', 'users' => []],
        'kind' => str_contains($name, '.') ? 'domain' : 'infra',
    ];
}

usort($entries, static fn($a, $b) => strcasecmp($a['domain'] . $a['folder'], $b['domain'] . $b['folder']));

echo json_encode([
    'generated_at' => date('c'),
    'generated_at_de' => date('d.m.Y H:i') . ' Uhr',
    'account' => 'w0217246 (All-Inkl / Kasserver)',
    'ssh' => 'ssh-w0217246@w0217246.kasserver.com',
    'document_root' => $root,
    'entries' => $entries,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
