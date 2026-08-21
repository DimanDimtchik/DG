<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');

$dbConfig = require __DIR__ . '/config.php';

try {
    $pdo = new PDO(
        'mysql:host=' . $dbConfig['host'] . ';dbname=' . $dbConfig['name'] . ';charset=utf8mb4',
        $dbConfig['user'],
        $dbConfig['pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['valid' => false, 'error' => 'db']);
    exit;
}

ensureTable($pdo);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path   = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');

// --- Admin routes (Bearer token) ---
$adminToken = trim((string) ($dbConfig['admin_token'] ?? ''));

if ($path === 'admin/licenses' && $method === 'GET' && authenticateAdmin($adminToken)) {
    $domain = strtolower(trim((string) ($_GET['domain'] ?? '')));
    $key = trim((string) ($_GET['key'] ?? ''));
    if ($domain !== '' && $key !== '') {
        $stmt = $pdo->prepare('SELECT * FROM lic_licenses WHERE domain = :d AND license_key = :k ORDER BY id DESC');
        $stmt->execute(['d' => $domain, 'k' => $key]);
    } elseif ($domain !== '') {
        $stmt = $pdo->prepare('SELECT * FROM lic_licenses WHERE domain = :d ORDER BY created_at DESC');
        $stmt->execute(['d' => $domain]);
    } elseif ($key !== '') {
        $stmt = $pdo->prepare('SELECT * FROM lic_licenses WHERE license_key = :k ORDER BY id DESC');
        $stmt->execute(['k' => $key]);
    } else {
        $stmt = $pdo->query('SELECT * FROM lic_licenses ORDER BY created_at DESC');
    }
    $rows = $stmt ? $stmt->fetchAll() : [];
    echo json_encode(['licenses' => $rows]);
    exit;
}

if ($path === 'admin/licenses' && $method === 'POST' && authenticateAdmin($adminToken)) {
    $input = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
    $domain   = strtolower(trim((string) ($input['domain'] ?? '')));
    $plan     = trim((string) ($input['plan'] ?? 'business'));
    $validTo  = trim((string) ($input['valid_to'] ?? ''));
    $note     = trim((string) ($input['note'] ?? ''));
    $licenseKey = strtoupper(trim((string) ($input['license_key'] ?? '')));

    if ($domain === '') {
        http_response_code(400);
        echo json_encode(['error' => 'domain required']);
        exit;
    }

    if ($licenseKey !== '') {
        if (!preg_match('/^GS-[A-Z0-9]{4}(-[A-Z0-9]{4}){3}$/', $licenseKey)) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid license_key format']);
            exit;
        }
        $exists = $pdo->prepare('SELECT id, domain, status FROM lic_licenses WHERE license_key = :k LIMIT 1');
        $exists->execute(['k' => $licenseKey]);
        $row = $exists->fetch();
        if ($row) {
            echo json_encode([
                'license_key' => $licenseKey,
                'id' => (int) $row['id'],
                'domain' => $row['domain'],
                'status' => $row['status'],
                'existing' => true,
            ]);
            exit;
        }
    } else {
        $licenseKey = generateLicenseKey();
    }

    $stmt = $pdo->prepare(
        'INSERT INTO lic_licenses (license_key, domain, plan, status, valid_to, note, created_at)
         VALUES (:key, :domain, :plan, :status, :valid_to, :note, NOW())'
    );
    $stmt->execute([
        'key'      => $licenseKey,
        'domain'   => $domain,
        'plan'     => $plan,
        'status'   => 'active',
        'valid_to' => $validTo !== '' ? $validTo : null,
        'note'     => $note !== '' ? $note : null,
    ]);

    echo json_encode([
        'license_key' => $licenseKey,
        'id' => (int) $pdo->lastInsertId(),
        'domain' => $domain,
        'status' => 'active',
    ]);
    exit;
}

if ($path === 'admin/licenses/by-key' && $method === 'PATCH' && authenticateAdmin($adminToken)) {
    $input = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
    $licenseKey = strtoupper(trim((string) ($input['license_key'] ?? '')));
    $status = trim((string) ($input['status'] ?? ''));
    if ($licenseKey === '' || !isValidLicenseStatus($status)) {
        http_response_code(400);
        echo json_encode(['error' => 'license_key and valid status required']);
        exit;
    }
    $stmt = $pdo->prepare('UPDATE lic_licenses SET status = :s WHERE license_key = :k');
    $stmt->execute(['s' => $status, 'k' => $licenseKey]);
    if ($stmt->rowCount() < 1) {
        http_response_code(404);
        echo json_encode(['error' => 'license not found']);
        exit;
    }
    echo json_encode(['updated' => true, 'license_key' => $licenseKey, 'status' => $status]);
    exit;
}

if (str_starts_with($path, 'admin/licenses/') && $method === 'PATCH' && authenticateAdmin($adminToken)) {
    $id = (int) substr($path, strlen('admin/licenses/'));
    $input = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
    $status = trim((string) ($input['status'] ?? ''));
    if ($id < 1 || !isValidLicenseStatus($status)) {
        http_response_code(400);
        echo json_encode(['error' => 'id and valid status required']);
        exit;
    }
    $stmt = $pdo->prepare('UPDATE lic_licenses SET status = :s WHERE id = :id');
    $stmt->execute(['s' => $status, 'id' => $id]);
    if ($stmt->rowCount() < 1) {
        http_response_code(404);
        echo json_encode(['error' => 'license not found']);
        exit;
    }
    echo json_encode(['updated' => true, 'id' => $id, 'status' => $status]);
    exit;
}

if (str_starts_with($path, 'admin/licenses/') && $method === 'DELETE' && authenticateAdmin($adminToken)) {
    $id = (int) substr($path, strlen('admin/licenses/'));
    $pdo->prepare('UPDATE lic_licenses SET status = :s WHERE id = :id')->execute(['s' => 'revoked', 'id' => $id]);
    echo json_encode(['revoked' => true]);
    exit;
}

// --- Public: license check ---
if ($path === 'check' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
    $domain     = strtolower(trim((string) ($input['domain'] ?? '')));
    $licenseKey = trim((string) ($input['license_key'] ?? ''));
    $version    = trim((string) ($input['version'] ?? ''));
    $ip         = $_SERVER['REMOTE_ADDR'] ?? '';

    if ($domain === '' || $licenseKey === '') {
        http_response_code(400);
        echo json_encode(['valid' => false, 'error' => 'missing fields']);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT * FROM lic_licenses WHERE license_key = :key AND domain = :domain LIMIT 1'
    );
    $stmt->execute(['key' => $licenseKey, 'domain' => $domain]);
    $license = $stmt->fetch();

    if (!$license) {
        logCheck($pdo, $domain, $licenseKey, $ip, false, 'not_found');
        echo json_encode(['valid' => false, 'reason' => 'not_found']);
        exit;
    }

    if ($license['status'] !== 'active') {
        logCheck($pdo, $domain, $licenseKey, $ip, false, 'status_' . $license['status']);
        echo json_encode(['valid' => false, 'reason' => 'inactive']);
        exit;
    }

    if ($license['valid_to'] !== null && $license['valid_to'] < date('Y-m-d')) {
        logCheck($pdo, $domain, $licenseKey, $ip, false, 'expired');
        echo json_encode(['valid' => false, 'reason' => 'expired']);
        exit;
    }

    $pdo->prepare(
        'UPDATE lic_licenses SET last_check_at = NOW(), last_ip = :ip, last_version = :v WHERE id = :id'
    )->execute(['ip' => $ip, 'v' => $version, 'id' => $license['id']]);

    logCheck($pdo, $domain, $licenseKey, $ip, true, 'ok');

    echo json_encode([
        'valid'  => true,
        'plan'   => $license['plan'],
        'domain' => $license['domain'],
    ]);
    exit;
}

// --- Fallback: fake login page ---
if ($path === '' || $path === '/' || $path === 'login') {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/login.html');
    exit;
}

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__ . '/login.html');

// --- Helpers ---

function authenticateAdmin(string $expectedToken): bool
{
    if ($expectedToken === '') {
        http_response_code(403);
        echo json_encode(['error' => 'no admin token configured']);
        exit;
    }
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (!str_starts_with($header, 'Bearer ')) {
        http_response_code(401);
        echo json_encode(['error' => 'unauthorized']);
        exit;
    }
    $token = substr($header, 7);
    if (!hash_equals($expectedToken, $token)) {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden']);
        exit;
    }
    return true;
}

function isValidLicenseStatus(string $status): bool
{
    return in_array($status, ['active', 'suspended', 'revoked', 'expired'], true);
}

function generateLicenseKey(): string
{
    $parts = [];
    for ($i = 0; $i < 4; $i++) {
        $parts[] = strtoupper(bin2hex(random_bytes(2)));
    }
    return 'GS-' . implode('-', $parts);
}

function logCheck(PDO $pdo, string $domain, string $key, string $ip, bool $valid, string $reason): void
{
    try {
        $pdo->prepare(
            'INSERT INTO lic_check_log (domain, license_key, ip, valid, reason, created_at)
             VALUES (:d, :k, :ip, :v, :r, NOW())'
        )->execute([
            'd'  => $domain,
            'k'  => $key,
            'ip' => $ip,
            'v'  => $valid ? 1 : 0,
            'r'  => $reason,
        ]);
    } catch (Throwable) {}
}

function ensureTable(PDO $pdo): void
{
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $pdo->exec("CREATE TABLE IF NOT EXISTS lic_licenses (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        license_key     VARCHAR(30)  NOT NULL UNIQUE,
        domain          VARCHAR(255) NOT NULL,
        plan            VARCHAR(50)  NOT NULL DEFAULT 'business',
        status          ENUM('active','suspended','revoked','expired') NOT NULL DEFAULT 'active',
        valid_to        DATE         NULL,
        note            TEXT         NULL,
        last_check_at   DATETIME     NULL,
        last_ip         VARCHAR(45)  NULL,
        last_version    VARCHAR(20)  NULL,
        created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_domain (domain),
        INDEX idx_key_domain (license_key, domain)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS lic_check_log (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        domain      VARCHAR(255) NOT NULL,
        license_key VARCHAR(30)  NOT NULL,
        ip          VARCHAR(45)  NOT NULL,
        valid       TINYINT(1)   NOT NULL,
        reason      VARCHAR(50)  NOT NULL DEFAULT '',
        created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_domain (domain),
        INDEX idx_cleanup (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
