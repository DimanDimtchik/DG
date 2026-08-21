<?php
declare(strict_types=1);

/**
 * Multi-step installation wizard.
 * Access via browser: https://example.com/install.php
 * After successful installation the script locks itself.
 */

define('DG_ROOT', dirname(__DIR__));

$lockFile = DG_ROOT . '/storage/.installed';
if (is_file($lockFile)) {
    http_response_code(403);
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><title>Gesperrt</title></head>';
    echo '<body style="font-family:sans-serif;padding:3rem;text-align:center">';
    echo '<h1>Installation bereits abgeschlossen</h1>';
    echo '<p>Diese Seite ist gesperrt. <a href="/login">Zum Login</a></p></body></html>';
    exit;
}

session_start();

// ── Wizard state ────────────────────────────────────────────────

$wizard = $_SESSION['install_wizard'] ?? [];
$step = max(1, min(5, (int) ($_POST['step'] ?? $_GET['step'] ?? $wizard['current_step'] ?? 1)));
$errors = [];
$hints = [];

// ── Step processing ─────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedStep = (int) ($_POST['step'] ?? 1);

    if ($postedStep === 1) {
        $wizard['db'] = [
            'kas_login' => trim($_POST['kas_login'] ?? ''),
            'kas_pass'  => trim($_POST['kas_pass'] ?? ''),
            'db_host'   => 'localhost',
            'db_name'   => '',
            'db_user'   => '',
            'db_pass'   => '',
        ];
        $wizard['smtp'] = [
            'email'    => trim($_POST['smtp_email'] ?? ''),
            'password' => trim($_POST['smtp_password'] ?? ''),
        ];

        $wizard['db']['use_existing'] = ($_POST['use_existing_db'] ?? '') === '1';
        $wizard['db']['existing_db_name'] = trim($_POST['existing_db_name'] ?? '');
        $wizard['db']['existing_db_pass'] = trim($_POST['existing_db_pass'] ?? '');

        $db = $wizard['db'];
        if ($db['kas_login'] === '') {
            $errors[] = 'KAS-Login ist erforderlich.';
        }
        if ($db['kas_pass'] === '') {
            $errors[] = 'KAS-Passwort ist erforderlich.';
        }

        if (empty($errors)) {
            if (!empty($db['use_existing']) && $db['existing_db_name'] !== '') {
                // Use existing database
                $wizard['db']['db_name'] = $db['existing_db_name'];
                $wizard['db']['db_user'] = $db['existing_db_name'];
                $wizard['db']['db_pass'] = $db['existing_db_pass'];
                $db = $wizard['db'];
            } else {
                // Create new database via KAS-API
                $kasResult = kasCreateDatabase($db['kas_login'], $db['kas_pass']);
                if ($kasResult === null) {
                    $errors[] = 'KAS-API: Datenbank konnte nicht erstellt werden. Bitte prüfen Sie Ihre KAS-Zugangsdaten.';
                } else {
                    $wizard['db']['db_name'] = $kasResult['database'];
                    $wizard['db']['db_user'] = $kasResult['database'];
                    $wizard['db']['db_pass'] = $kasResult['password'];
                    $db = $wizard['db'];
                }
            }
        }

        if (empty($errors)) {
            try {
                $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $db['db_host'], $db['db_name']);
                $testPdo = new PDO($dsn, $db['db_user'], $db['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                // Check if tables already exist
                $existingTables = $testPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($existingTables)) {
                    $wizard['db']['has_existing_tables'] = true;
                    $wizard['db']['table_count'] = count($existingTables);
                }
            } catch (Throwable $e) {
                $errors[] = 'DB-Verbindung fehlgeschlagen: ' . $e->getMessage();
            }
        }

        if (empty($errors)) {
            $step = 2;
        } else {
            $step = 1;
        }
    }

    if ($postedStep === 2) {
        $wizard['migration'] = [
            'has_domain'      => ($_POST['has_domain'] ?? '') === '1',
            'existing_domain' => trim($_POST['existing_domain'] ?? ''),
            'domain_transfer' => ($_POST['domain_transfer'] ?? '') === '1',
            'has_email'       => ($_POST['has_email'] ?? '') === '1',
            'email_migrate'   => ($_POST['email_migrate'] ?? '') === '1',
            'old_imap_host'   => trim($_POST['old_imap_host'] ?? ''),
            'old_imap_user'   => trim($_POST['old_imap_user'] ?? ''),
            'old_imap_pass'   => trim($_POST['old_imap_pass'] ?? ''),
            'email_addresses' => trim($_POST['email_addresses'] ?? ''),
            'provider'        => '',
        ];

        $mig = $wizard['migration'];
        if ($mig['has_domain'] && $mig['existing_domain'] !== '') {
            $wizard['migration']['provider'] = DomainMigrationService::detectProvider($mig['existing_domain']);
            $wizard['migration']['domain_resolves'] = DomainMigrationService::domainExists($mig['existing_domain']);
            $wizard['migration']['has_mx'] = DomainMigrationService::hasMxRecords($mig['existing_domain']);
        }

        $step = 3;
    }

    if ($postedStep === 3) {
        $wizard['company'] = [
            'name'          => trim($_POST['company_name'] ?? ''),
            'legal_name'    => trim($_POST['legal_name'] ?? ''),
            'company_type'  => trim($_POST['company_type'] ?? ''),
            'business_kind' => $_POST['business_kind'] ?? [],
            'industry'      => trim($_POST['industry'] ?? ''),
            'email'         => trim($_POST['company_email'] ?? ''),
            'phone'         => trim($_POST['company_phone'] ?? ''),
            'street'        => trim($_POST['street'] ?? ''),
            'postal'        => trim($_POST['postal'] ?? ''),
            'city'          => trim($_POST['city'] ?? ''),
            'country'       => trim($_POST['country'] ?? 'DE') ?: 'DE',
        ];

        $c = $wizard['company'];
        if ($c['name'] === '') { $errors[] = 'Firmenname ist erforderlich.'; }
        if ($c['company_type'] === '') { $errors[] = 'Rechtsform ist erforderlich.'; }
        if (empty($c['business_kind'])) { $errors[] = 'Bitte wählen Sie mindestens eine Geschäftsart.'; }
        if ($c['email'] === '' || !filter_var($c['email'], FILTER_VALIDATE_EMAIL)) { $errors[] = 'Gültige Firmen-E-Mail ist erforderlich.'; }
        if ($c['street'] === '' || $c['postal'] === '' || $c['city'] === '') { $errors[] = 'Vollständige Adresse ist erforderlich.'; }

        $step = empty($errors) ? 4 : 3;
    }

    if ($postedStep === 4) {
        $wizard['legal'] = [
            'owners' => [],
            'tax_number'     => trim($_POST['tax_number'] ?? ''),
            'vat_id'         => trim($_POST['vat_id'] ?? ''),
            'tax_id'         => trim($_POST['tax_id'] ?? ''),
            'register_court' => trim($_POST['register_court'] ?? ''),
            'register_number'=> trim($_POST['register_number'] ?? ''),
            'authority_name' => trim($_POST['authority_name'] ?? ''),
            'authority_url'  => trim($_POST['authority_url'] ?? ''),
            'chamber_name'   => trim($_POST['chamber_name'] ?? ''),
            'job_title'      => trim($_POST['job_title'] ?? ''),
            'job_title_country' => trim($_POST['job_title_country'] ?? 'Deutschland'),
        ];

        $ownerNames = $_POST['owner_name'] ?? [];
        $ownerRoles = $_POST['owner_role'] ?? [];
        foreach ($ownerNames as $i => $name) {
            $name = trim($name);
            $role = trim($ownerRoles[$i] ?? '');
            if ($name !== '') {
                $wizard['legal']['owners'][] = ['name' => $name, 'role' => $role];
            }
        }

        if (empty($wizard['legal']['owners'])) {
            $errors[] = 'Mindestens ein Inhaber/Geschäftsführer ist erforderlich.';
        }

        $l = $wizard['legal'];
        if ($l['tax_number'] === '') {
            $hints[] = ['field' => 'Steuernummer', 'text' => 'Beantragen Sie diese beim Finanzamt Ihres Betriebssitzes. Die Steuernummer wird automatisch bei der Gewerbeanmeldung oder Anmeldung beim Finanzamt (Fragebogen zur steuerlichen Erfassung) vergeben.'];
        }
        if ($l['vat_id'] === '') {
            $hints[] = ['field' => 'USt-IdNr.', 'text' => 'Beantragen Sie diese beim Bundeszentralamt für Steuern: <a href="https://www.bzst.de" target="_blank">www.bzst.de</a>. Pflicht bei innergemeinschaftlichem Handel.'];
        }
        if ($l['tax_id'] === '') {
            $hints[] = ['field' => 'Steuer-ID (Steuerliche Identifikationsnummer)', 'text' => 'Die 11-stellige Steuer-ID erhalten Sie automatisch vom BZSt. Falls nicht vorhanden: <a href="https://www.bzst.de/DE/Privatpersonen/SteuerlicheIdentifikationsnummer/steuerlicheidentifikationsnummer_node.html" target="_blank">Hier erneut anfordern</a>.'];
        }

        $companyType = $wizard['company']['company_type'] ?? '';
        $needsRegister = in_array($companyType, ['gmbh', 'gmbh_igr', 'gmbh_co_kg', 'ug', 'ug_igr', 'ag', 'kg', 'ohg', 'ek', 'eg', 'ggmbh'], true);
        if ($needsRegister && $l['register_number'] === '') {
            $hints[] = ['field' => 'Handelsregister', 'text' => 'Für ' . ($companyType === 'ev' ? 'Vereinsregister' : 'Handelsregister') . '-pflichtige Rechtsformen: Eintragung erfolgt über das zuständige Amtsgericht.'];
        }

        $needsAuthority = in_array($companyType, ['praxis', 'kanzlei'], true);
        if ($needsAuthority && $l['authority_name'] === '') {
            $hints[] = ['field' => 'Aufsichtsbehörde', 'text' => 'Pflicht für zulassungspflichtige Berufe. Geben Sie die zuständige Kammer oder Aufsichtsbehörde an (z.B. Ärztekammer, Rechtsanwaltskammer).'];
        }

        $step = empty($errors) ? 5 : 4;
        $wizard['hints'] = $hints;
    }

    if ($postedStep === 5) {
        $wizard['users'] = [];
        $userEmails = $_POST['user_email'] ?? [];
        $userNames  = $_POST['user_display_name'] ?? [];
        $userRoles  = $_POST['user_role'] ?? [];

        foreach ($userEmails as $i => $email) {
            $email = trim($email);
            $displayName = trim($userNames[$i] ?? '');
            $role = trim($userRoles[$i] ?? 'administrator');
            if ($email !== '') {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Ungültige E-Mail: $email";
                } else {
                    $wizard['users'][] = [
                        'email'        => $email,
                        'display_name' => $displayName,
                        'role'         => $role,
                    ];
                }
            }
        }

        if (empty($wizard['users'])) {
            $errors[] = 'Mindestens ein Benutzer ist erforderlich.';
        }

        if (empty($errors)) {
            $result = performInstallation($wizard);
            if ($result['success']) {
                $wizard['done'] = true;
                $wizard['invited'] = $result['invited'];
            } else {
                $errors[] = $result['error'];
            }
        }

        if (!empty($errors)) {
            $step = 5;
        }
    }

    $wizard['current_step'] = $step;
    $_SESSION['install_wizard'] = $wizard;
}

// ── Installation logic ──────────────────────────────────────────

function performInstallation(array $wizard): array
{
    try {
        $db = $wizard['db'];
        $company = $wizard['company'];
        $legal = $wizard['legal'];
        $users = $wizard['users'];

        $configDir = DG_ROOT . '/config';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        // database.local.php
        file_put_contents($configDir . '/database.local.php', sprintf(
            "<?php\ndeclare(strict_types=1);\n\nreturn [\n    'database' => %s,\n    'username' => %s,\n    'password' => %s,\n];\n",
            var_export($db['db_name'], true),
            var_export($db['db_user'], true),
            var_export($db['db_pass'], true)
        ));

        // app.local.php
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $baseUrl = ($https ? 'https' : 'http') . '://' . $host;
        $sessionName = preg_replace('/[^a-z0-9_]/', '_', strtolower($company['name'])) . '_session';

        file_put_contents($configDir . '/app.local.php', sprintf(
            "<?php\ndeclare(strict_types=1);\n\nreturn [\n    'name' => %s,\n    'crm_name' => %s,\n    'home_url' => %s,\n    'session_name' => %s,\n];\n",
            var_export($company['name'], true),
            var_export($company['name'], true),
            var_export($baseUrl, true),
            var_export($sessionName, true)
        ));

        // Storage dirs
        $storageDirs = ['storage/logs', 'storage/media', 'storage/contacts', 'storage/vouchers',
                        'storage/mail/sent', 'storage/mail/inbox', 'tmp-upload'];
        foreach ($storageDirs as $d) {
            $path = DG_ROOT . '/' . $d;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
        file_put_contents(DG_ROOT . '/storage/.htaccess', "Deny from all\n");

        // Boot the app for migrations and settings
        require_once DG_ROOT . '/src/autoload.php';
        require_once DG_ROOT . '/src/App.php';
        App::reloadConfig();
        MigrationRunner::runPending();

        // Save company settings
        CompanySettings::save([
            'name'       => $company['name'],
            'email'      => $company['email'],
            'phone'      => $company['phone'],
            'website'    => $baseUrl,
            'street'     => $company['street'],
            'postal'     => $company['postal'],
            'city'       => $company['city'],
            'country'    => $company['country'],
            'tax_number' => $legal['tax_number'],
            'vat_id'     => $legal['vat_id'],
        ]);

        // Save extended settings
        $owners = [];
        foreach ($legal['owners'] as $o) {
            $owners[] = ['name' => $o['name'], 'share_percent' => '', 'user_id' => '0'];
        }

        $extendedData = [
            'legal_name'    => $company['legal_name'],
            'company_type'  => $company['company_type'],
            'industry'      => $company['industry'],
            'tax_numbers'   => [
                'est'            => $legal['tax_number'],
                'ust'            => $legal['vat_id'],
                'steuer_id'      => $legal['tax_id'],
                'gst' => '', 'kst' => '', 'wirtschafts_id' => '',
            ],
            'trade_register' => [
                'court'  => $legal['register_court'],
                'number' => $legal['register_number'],
            ],
            'owners' => $owners,
        ];

        if (!empty($legal['authority_name'])) {
            $extendedData['professional_chambers'] = [[
                'name'      => $legal['authority_name'],
                'member_no' => '',
                'contact'   => '',
                'phone'     => '',
                'email'     => '',
            ]];
        }

        SettingsStore::set(CompanyExtendedSettings::STORE_KEY, $extendedData);

        // Save business kind for AGB/Impressum generator
        SettingsStore::set('install_business_kind', $company['business_kind']);

        // Configure SMTP from KAS-Login
        $smtp = $wizard['smtp'] ?? [];
        $kasLogin = $wizard['db']['kas_login'] ?? '';
        if (!empty($smtp['email']) && $kasLogin !== '') {
            MailSettings::save([
                'smtp_host'       => $kasLogin . '.kasserver.com',
                'smtp_port'       => 465,
                'smtp_encryption' => 'ssl',
                'smtp_username'   => $smtp['email'],
                'smtp_password'   => $smtp['password'],
                'sender_name'     => $company['name'],
                'sender_email'    => $smtp['email'],
                'reply_to'        => $company['email'],
            ]);
        }

        // Create users with email verification tokens
        $invited = [];
        $userId = 1;
        $usersConfig = [];

        foreach ($users as $u) {
            $token = bin2hex(random_bytes(32));

            $pdo = Database::pdo();
            $stmt = $pdo->prepare(
                'INSERT INTO dg_password_reset_tokens (user_id, token_hash, expires_at)
                 VALUES (:uid, :hash, DATE_ADD(NOW(), INTERVAL 7 DAY))'
            );
            $stmt->execute([
                'uid'  => $userId,
                'hash' => hash('sha256', $token),
            ]);

            $roleMap = [
                'administrator'       => ['administrator'],
                'dg_eigenmitarbeiter' => ['dg_eigenmitarbeiter'],
            ];

            $usersConfig[$userId] = [
                'id'              => $userId,
                'username'        => strtolower(explode('@', $u['email'])[0]),
                'display_name'    => $u['display_name'] ?: explode('@', $u['email'])[0],
                'email'           => $u['email'],
                'password_hash'   => '',
                'roles'           => $roleMap[$u['role']] ?? ['administrator'],
                'employee_active' => true,
            ];

            $invited[] = [
                'email' => $u['email'],
                'name'  => $usersConfig[$userId]['display_name'],
                'token' => $token,
                'role'  => $u['role'],
            ];

            $userId++;
        }

        // Write users.php
        $usersPhp = "<?php\ndeclare(strict_types=1);\n\nreturn [\n    'department_members' => [],\n    'users' => [\n";
        foreach ($usersConfig as $id => $u) {
            $usersPhp .= "        $id => [\n";
            $usersPhp .= "            'id' => $id,\n";
            $usersPhp .= "            'username' => " . var_export($u['username'], true) . ",\n";
            $usersPhp .= "            'display_name' => " . var_export($u['display_name'], true) . ",\n";
            $usersPhp .= "            'email' => " . var_export($u['email'], true) . ",\n";
            $usersPhp .= "            'password_hash' => '',\n";
            $usersPhp .= "            'roles' => " . var_export($u['roles'], true) . ",\n";
            $usersPhp .= "            'employee_active' => " . ($u['employee_active'] ? 'true' : 'false') . ",\n";
            $usersPhp .= "        ],\n";
        }
        $usersPhp .= "    ],\n];\n";
        file_put_contents($configDir . '/users.php', $usersPhp);

        // Send invitation emails
        foreach ($invited as $inv) {
            sendInvitationEmail($inv['email'], $inv['name'], $inv['token'], $company['name'], $baseUrl);
        }

        // Save hints
        if (!empty($wizard['hints'])) {
            SettingsStore::set('install_hints', $wizard['hints']);
        }

        // Lock installer
        file_put_contents(DG_ROOT . '/storage/.installed', json_encode([
            'installed_at' => date('Y-m-d H:i:s'),
            'version'      => is_readable(DG_ROOT . '/config/version.php') ? (string) require DG_ROOT . '/config/version.php' : '?',
            'company'      => $company['name'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return ['success' => true, 'invited' => $invited];

    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function sendInvitationEmail(string $email, string $name, string $token, string $companyName, string $baseUrl): void
{
    $activateUrl = $baseUrl . '/konto-aktivieren?token=' . rawurlencode($token);
    $subject = "Ihr Zugang zu $companyName";

    $html = '<div style="font-family:system-ui,sans-serif;max-width:500px;margin:0 auto;padding:2rem">'
        . '<h2 style="color:#1e293b">Willkommen bei ' . htmlspecialchars($companyName) . '</h2>'
        . '<p>Hallo ' . htmlspecialchars($name) . ',</p>'
        . '<p>Ihr Benutzerkonto wurde angelegt. Bitte bestätigen Sie Ihre E-Mail-Adresse und vergeben Sie ein Passwort:</p>'
        . '<p style="margin:24px 0"><a href="' . htmlspecialchars($activateUrl) . '" '
        . 'style="display:inline-block;padding:12px 24px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;font-weight:600">'
        . 'E-Mail bestätigen &amp; Passwort vergeben</a></p>'
        . '<p style="font-size:13px;color:#64748b">Dieser Link ist 7 Tage gültig.</p>'
        . '</div>';

    $text = "Willkommen bei $companyName\n\n"
        . "Hallo $name,\n\n"
        . "Ihr Benutzerkonto wurde angelegt. Bestätigen Sie Ihre E-Mail und vergeben Sie ein Passwort:\n"
        . "$activateUrl\n\n"
        . "Der Link ist 7 Tage gültig.\n";

    $headers = "MIME-Version: 1.0\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "From: noreply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n";

    @mail($email, $subject, $html, $headers);
}

// ── KAS API ─────────────────────────────────────────────────────

function kasCreateDatabase(string $kasLogin, string $kasPassword): ?array
{
    $dbPassword = bin2hex(random_bytes(12));
    $params = json_encode([
        'kas_login'        => $kasLogin,
        'kas_auth_type'    => 'plain',
        'kas_auth_data'    => $kasPassword,
        'kas_action'       => 'add_database',
        'KasRequestParams' => ['database_password' => $dbPassword],
    ]);
    $ctx = stream_context_create(['http' => [
        'method' => 'POST', 'header' => "Content-Type: application/json\r\n",
        'content' => $params, 'timeout' => 30,
    ]]);
    $response = @file_get_contents('https://kasapi.kasserver.com/json-api', false, $ctx);
    if ($response === false) { return null; }
    $data = json_decode($response, true);
    if (!is_array($data) || empty($data['Response']['ReturnInfo'])) { return null; }
    return ['database' => $data['Response']['ReturnInfo'], 'password' => $dbPassword];
}

// ── System checks ───────────────────────────────────────────────

function systemChecks(): array
{
    $checks = [];
    $checks['PHP >= 8.1'] = version_compare(PHP_VERSION, '8.1.0', '>=');
    $checks['PDO MySQL']  = extension_loaded('pdo_mysql');
    $checks['mbstring']   = extension_loaded('mbstring');
    $checks['json']       = extension_loaded('json');
    $checks['ZipArchive'] = class_exists('ZipArchive');
    $checks['config/ beschreibbar'] = is_writable(DG_ROOT . '/config') || is_writable(DG_ROOT);
    $checks['storage/ beschreibbar'] = is_writable(DG_ROOT . '/storage') || (!is_dir(DG_ROOT . '/storage') && is_writable(DG_ROOT));
    $checks['mail() verfügbar'] = function_exists('mail');
    return $checks;
}

$checks = systemChecks();
$allPassed = !in_array(false, $checks, true);

// ── Business kind options ───────────────────────────────────────

$businessKinds = [
    'products'    => 'Warenverkauf / Online-Shop',
    'services'    => 'Dienstleistungen',
    'both'        => 'Waren und Dienstleistungen',
    'association' => 'Verein / gemeinnützige Organisation',
    'law'         => 'Kanzlei (Rechtsanwalt, Steuerberater, Notar)',
    'medical'     => 'Praxis (Arzt, Therapeut, Heilpraktiker)',
    'consulting'  => 'Beratung / Coaching',
    'crafts'      => 'Handwerk',
    'gastro'      => 'Gastronomie / Hotel',
    'it'          => 'IT / Software / Agentur',
];

// ── Company types ───────────────────────────────────────────────

$companyTypes = [
    'einzelunternehmen' => 'Einzelunternehmen',
    'freiberufler' => 'Freiberufler',
    'gbr' => 'GbR',
    'ohg' => 'OHG',
    'kg' => 'KG',
    'gmbh' => 'GmbH',
    'gmbh_igr' => 'GmbH i. Gr.',
    'gmbh_co_kg' => 'GmbH & Co. KG',
    'ug' => 'UG (haftungsbeschränkt)',
    'ug_igr' => 'UG i. Gr.',
    'ag' => 'Aktiengesellschaft (AG)',
    'ek' => 'Eingetragener Kaufmann (e.K.)',
    'ev' => 'eingetragener Verein (e.V.)',
    'ggmbh' => 'gemeinnützige GmbH (gGmbH)',
    'stiftung' => 'Stiftung',
    'partg' => 'Partnerschaftsgesellschaft (PartG)',
    'praxis' => 'Praxis',
    'kanzlei' => 'Kanzlei',
    'koerperschaft' => 'Körperschaft des öffentlichen Rechts',
    'gewerbetreibender' => 'Gewerbetreibender',
];

// ── User role options ───────────────────────────────────────────

$userRoles = [
    'administrator'       => 'Geschäftsführer / Inhaber (voller Zugriff)',
    'dg_eigenmitarbeiter' => 'Mitarbeiter (Buchhalter, IT, Personal)',
];

// ── Render ───────────────────────────────────────────────────────

$done = !empty($wizard['done']);
$version = is_readable(DG_ROOT . '/config/version.php') ? (string) require DG_ROOT . '/config/version.php' : '?';

?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>CRM Installation – Schritt <?= $step ?> von 5</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,sans-serif;background:#f4f5f7;color:#1e293b;line-height:1.6}
.wrap{max-width:640px;margin:2rem auto;padding:0 1rem}
.card{background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:2rem;margin-bottom:1.5rem}
h1{font-size:1.5rem;margin-bottom:.25rem}
h2{font-size:1.1rem;margin:1.5rem 0 .75rem;color:#475569;border-bottom:1px solid #e2e8f0;padding-bottom:.4rem}
h2:first-child{margin-top:0}
label{display:block;font-size:.875rem;font-weight:600;margin-bottom:.25rem;color:#334155}
input[type=text],input[type=email],input[type=password],input[type=tel],select{
  width:100%;padding:.5rem .75rem;border:1px solid #cbd5e1;border-radius:6px;font-size:.9rem;margin-bottom:.75rem}
input:focus,select:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.15)}
.hint{font-size:.8rem;color:#64748b;margin-top:-.5rem;margin-bottom:.75rem}
.btn{display:inline-block;padding:.6rem 1.5rem;background:#2563eb;color:#fff;border:none;border-radius:6px;font-size:.95rem;cursor:pointer;font-weight:600}
.btn:hover{background:#1d4ed8}
.btn-secondary{background:#64748b}.btn-secondary:hover{background:#475569}
.btn:disabled{opacity:.5;cursor:not-allowed}
.check{display:flex;align-items:center;gap:.5rem;padding:.3rem 0;font-size:.9rem}
.check .ok{color:#16a34a}
.check .fail{color:#dc2626}
.errors{background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:1rem;margin-bottom:1rem}
.errors li{color:#dc2626;font-size:.9rem;margin-left:1rem}
.success{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:1.5rem;text-align:center}
.success h2{color:#16a34a;border:none;margin:0 0 .5rem}
.hint-box{background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:1rem;margin-bottom:1rem}
.hint-box h3{font-size:.9rem;color:#92400e;margin-bottom:.5rem}
.hint-box p{font-size:.85rem;color:#78350f;margin-bottom:.5rem}
.hint-box a{color:#2563eb}
.steps{display:flex;gap:0;margin-bottom:1.5rem}
.step-indicator{flex:1;text-align:center;padding:.5rem;font-size:.8rem;font-weight:600;color:#94a3b8;border-bottom:3px solid #e2e8f0}
.step-indicator.active{color:#2563eb;border-bottom-color:#2563eb}
.step-indicator.done{color:#16a34a;border-bottom-color:#16a34a}
.checkbox-group{display:grid;grid-template-columns:1fr 1fr;gap:.25rem .75rem;margin-bottom:.75rem}
.checkbox-group label{display:flex;align-items:center;gap:.4rem;font-weight:400;font-size:.875rem;cursor:pointer}
.owner-row,.user-row{display:grid;grid-template-columns:1fr 1fr auto;gap:.5rem;align-items:start;margin-bottom:.5rem}
.user-row{grid-template-columns:1fr 1fr 1fr auto}
.remove-btn{background:none;border:none;color:#dc2626;cursor:pointer;font-size:1.2rem;padding:.5rem;line-height:1}
.add-btn{background:none;border:none;color:#2563eb;cursor:pointer;font-size:.9rem;font-weight:600;padding:.25rem 0}
.add-btn:hover{text-decoration:underline}
.nav-buttons{display:flex;gap:1rem;margin-top:1.5rem}
</style>
</head>
<body>
<div class="wrap">

<div class="card" style="padding:1.25rem 2rem">
<h1>CRM Installation</h1>
<p style="color:#64748b;font-size:.85rem">Version <?= htmlspecialchars($version) ?></p>
</div>

<?php if (!$done): ?>
<div class="steps">
    <div class="step-indicator <?= $step === 1 ? 'active' : ($step > 1 ? 'done' : '') ?>">1. Datenbank</div>
    <div class="step-indicator <?= $step === 2 ? 'active' : ($step > 2 ? 'done' : '') ?>">2. Firma</div>
    <div class="step-indicator <?= $step === 3 ? 'active' : ($step > 3 ? 'done' : '') ?>">3. Rechtliches</div>
    <div class="step-indicator <?= $step === 4 ? 'active' : '' ?>">4. Benutzer</div>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="errors"><ul>
<?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
</ul></div>
<?php endif; ?>

<?php if ($done): ?>
<div class="success">
    <h2>Installation erfolgreich!</h2>
    <p style="margin-bottom:1rem">Einladungen wurden an folgende E-Mail-Adressen gesendet:</p>
    <?php foreach ($wizard['invited'] ?? [] as $inv): ?>
    <p><strong><?= htmlspecialchars($inv['name']) ?></strong> – <?= htmlspecialchars($inv['email']) ?></p>
    <?php endforeach; ?>
    <p style="margin-top:1rem;font-size:.85rem;color:#64748b">Jeder eingeladene Benutzer erhält eine E-Mail mit einem Bestätigungslink. Nach Klick auf den Link wird ein Passwort vergeben und das Konto aktiviert.</p>

    <?php if (!empty($wizard['hints'])): ?>
    <div class="hint-box" style="text-align:left;margin-top:1.5rem">
        <h3>Fehlende Angaben – bitte später ergänzen</h3>
        <?php foreach ($wizard['hints'] as $h): ?>
        <p><strong><?= htmlspecialchars($h['field']) ?>:</strong> <?= $h['text'] ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <p style="margin-top:1.5rem"><a href="/login" class="btn">Zum Login</a></p>
</div>

<?php elseif ($step === 1): ?>

<div class="card">
<h2>Systemprüfung</h2>
<?php foreach ($checks as $label => $ok): ?>
<div class="check">
    <span class="<?= $ok ? 'ok' : 'fail' ?>"><?= $ok ? '&#10003;' : '&#10007;' ?></span>
    <?= htmlspecialchars($label) ?>
</div>
<?php endforeach; ?>
</div>

<form method="post" class="card">
<input type="hidden" name="step" value="1">
<h2>Datenbank (KAS-API)</h2>
<p class="hint">Die Datenbank wird automatisch über die All-Inkl KAS-API erstellt. Geben Sie Ihre KAS-Zugangsdaten ein.</p>

<label for="kas_login">KAS-Login *</label>
<input type="text" id="kas_login" name="kas_login" value="<?= htmlspecialchars($wizard['db']['kas_login'] ?? '') ?>" required>
<p class="hint">Ihr All-Inkl Benutzername (z.B. w0217246)</p>

<label for="kas_pass">KAS-Passwort *</label>
<input type="password" id="kas_pass" name="kas_pass" required>

<h2 style="margin-top:24px;">Datenbank</h2>
<label>
    <input type="radio" name="use_existing_db" value="0" <?= empty($wizard['db']['use_existing']) ? 'checked' : '' ?>
           onchange="document.getElementById('existing-db').style.display='none'">
    Neue Datenbank automatisch erstellen
</label>
<label>
    <input type="radio" name="use_existing_db" value="1" <?= !empty($wizard['db']['use_existing']) ? 'checked' : '' ?>
           onchange="document.getElementById('existing-db').style.display='block'">
    Bestehende Datenbank verwenden
</label>
<div id="existing-db" style="<?= empty($wizard['db']['use_existing']) ? 'display:none;' : '' ?> margin-top:12px;">
    <label for="existing_db_name">Datenbankname</label>
    <input type="text" id="existing_db_name" name="existing_db_name" placeholder="d047f810"
           value="<?= htmlspecialchars($wizard['db']['existing_db_name'] ?? '') ?>">
    <label for="existing_db_pass">Datenbank-Passwort</label>
    <input type="password" id="existing_db_pass" name="existing_db_pass"
           value="<?= htmlspecialchars($wizard['db']['existing_db_pass'] ?? '') ?>">
    <p class="hint">Die bestehende Datenbank wird weiterverwendet. Vorhandene CRM-Tabellen werden aktualisiert.</p>
</div>

<h2 style="margin-top:24px;">E-Mail-Versand (SMTP)</h2>
<p class="hint">Damit das CRM E-Mails senden kann (Aktivierung, Passwort-Reset, Benachrichtigungen). Die SMTP-Daten werden automatisch aus dem KAS-Login abgeleitet.</p>

<label for="smtp_email">Absender-E-Mail *</label>
<input type="email" id="smtp_email" name="smtp_email" placeholder="info@kundenname.de" value="<?= htmlspecialchars($wizard['smtp']['email'] ?? '') ?>" required>
<p class="hint">Muss als Postfach im KAS existieren (z.B. info@kundenname.de)</p>

<label for="smtp_password">E-Mail-Passwort *</label>
<input type="password" id="smtp_password" name="smtp_password" value="<?= htmlspecialchars($wizard['smtp']['password'] ?? '') ?>" required>
<p class="hint">Das Passwort des E-Mail-Postfachs (nicht das KAS-Passwort)</p>

<div class="nav-buttons">
    <button type="submit" class="btn" <?= $allPassed ? '' : 'disabled' ?>>Weiter →</button>
</div>
</form>

<?php elseif ($step === 2): ?>

<form method="post" class="card">
<input type="hidden" name="step" value="2">
<h2>Bestehende Website & E-Mail</h2>

<p>Haben Sie bereits eine Webadresse (Domain) oder geschäftliche E-Mail-Adressen?</p>

<fieldset>
<legend>Webadresse</legend>

<label>
    <input type="radio" name="has_domain" value="0" <?= empty($wizard['migration']['has_domain']) ? 'checked' : '' ?>
           onchange="document.getElementById('domain-details').style.display='none'">
    Nein, ich brauche eine neue Domain
</label>

<label>
    <input type="radio" name="has_domain" value="1" <?= !empty($wizard['migration']['has_domain']) ? 'checked' : '' ?>
           onchange="document.getElementById('domain-details').style.display='block'">
    Ja, ich habe bereits eine Domain
</label>

<div id="domain-details" style="<?= empty($wizard['migration']['has_domain']) ? 'display:none' : '' ?>; margin-top:12px;">
    <label>Aktuelle Domain
        <input type="text" name="existing_domain" placeholder="beispiel.de"
               value="<?= htmlspecialchars($wizard['migration']['existing_domain'] ?? '') ?>">
    </label>

    <label style="margin-top:8px;">
        <input type="checkbox" name="domain_transfer" value="1" <?= !empty($wizard['migration']['domain_transfer']) ? 'checked' : '' ?>>
        Domain komplett zu uns transferieren (empfohlen)
    </label>
    <small>Alternativ können Sie die Domain beim bisherigen Provider belassen und nur die DNS-Einträge ändern.</small>

    <?php
    $mig = $wizard['migration'] ?? [];
    if (!empty($mig['provider']) && $mig['provider'] !== 'unbekannt') {
        echo '<div class="alert" style="margin-top:8px;">';
        echo '<strong>Erkannter Provider:</strong> ' . htmlspecialchars($mig['provider']);
        if ($mig['provider'] === 'All-Inkl') {
            echo ' — Die Domain ist bereits bei All-Inkl, kein Transfer notwendig.';
        }
        echo '</div>';
    }
    ?>
</div>
</fieldset>

<fieldset style="margin-top:16px;">
<legend>Geschäftliche E-Mail</legend>

<label>
    <input type="radio" name="has_email" value="0" <?= empty($wizard['migration']['has_email']) ? 'checked' : '' ?>
           onchange="document.getElementById('email-details').style.display='none'">
    Nein, ich richte neue E-Mail-Adressen ein
</label>

<label>
    <input type="radio" name="has_email" value="1" <?= !empty($wizard['migration']['has_email']) ? 'checked' : '' ?>
           onchange="document.getElementById('email-details').style.display='block'">
    Ja, ich habe bestehende E-Mail-Adressen
</label>

<div id="email-details" style="<?= empty($wizard['migration']['has_email']) ? 'display:none' : '' ?>; margin-top:12px;">
    <label>E-Mail-Adressen (eine pro Zeile)
        <textarea name="email_addresses" rows="3" placeholder="info@beispiel.de&#10;kontakt@beispiel.de"><?= htmlspecialchars($wizard['migration']['email_addresses'] ?? '') ?></textarea>
    </label>

    <label style="margin-top:8px;">
        <input type="checkbox" name="email_migrate" value="1" <?= !empty($wizard['migration']['email_migrate']) ? 'checked' : '' ?>
               onchange="document.getElementById('imap-details').style.display=this.checked?'block':'none'">
        Bestehende E-Mails zum neuen Server übertragen
    </label>

    <div id="imap-details" style="<?= empty($wizard['migration']['email_migrate']) ? 'display:none' : '' ?>; margin-top:12px; padding:12px; background:#f8f8f8; border-radius:6px;">
        <p><strong>IMAP-Zugangsdaten des alten Providers</strong></p>
        <label>IMAP-Server
            <input type="text" name="old_imap_host" placeholder="imap.alterprovider.de"
                   value="<?= htmlspecialchars($wizard['migration']['old_imap_host'] ?? '') ?>">
        </label>
        <label>Benutzername
            <input type="text" name="old_imap_user" placeholder="info@beispiel.de"
                   value="<?= htmlspecialchars($wizard['migration']['old_imap_user'] ?? '') ?>">
        </label>
        <label>Passwort
            <input type="password" name="old_imap_pass">
        </label>
        <small>Die Zugangsdaten finden Sie in Ihrem E-Mail-Programm oder beim alten Provider.</small>
    </div>
</div>
</fieldset>

<?php
$mig = $wizard['migration'] ?? [];
if (!empty($mig['provider']) && $mig['provider'] !== 'unbekannt') {
    $hasDomain = !empty($mig['has_domain']);
    $transfer = !empty($mig['domain_transfer']);
    $hasEmail = !empty($mig['has_email']);
    $emailMigrate = !empty($mig['email_migrate']);

    if ($hasDomain || $hasEmail) {
        echo '<div style="margin-top:20px; padding:16px; background:#f0f7ff; border:1px solid #cde; border-radius:6px;">';
        echo '<h3 style="margin:0 0 8px;">Umzugsplan</h3>';

        if ($hasDomain) {
            $steps = DomainMigrationService::domainMigrationSteps($mig['provider'], $transfer);
            echo '<h4>Domain</h4><ol>';
            foreach ($steps as $s) {
                $icon = $s['automated'] ? '⚙️' : '📋';
                $tag = $s['automated'] ? '<span style="color:#090;font-size:0.85em;">(automatisch)</span>' : '<span style="color:#c60;font-size:0.85em;">(manuell erforderlich)</span>';
                echo "<li><strong>{$s['title']}</strong> $tag<br><small>{$s['description']}</small></li>";
            }
            echo '</ol>';
        }

        if ($hasEmail) {
            $emailSteps = DomainMigrationService::emailMigrationSteps($emailMigrate);
            echo '<h4>E-Mail</h4><ol>';
            foreach ($emailSteps as $s) {
                $tag = $s['automated'] ? '<span style="color:#090;font-size:0.85em;">(automatisch)</span>' : '<span style="color:#c60;font-size:0.85em;">(manuell erforderlich)</span>';
                echo "<li><strong>{$s['title']}</strong> $tag<br><small>{$s['description']}</small></li>";
            }
            echo '</ol>';
        }
        echo '</div>';
    }
}
?>

<div style="margin-top:16px; display:flex; gap:8px;">
    <button type="submit" class="btn">Weiter →</button>
</div>
</form>

<?php elseif ($step === 3): ?>

<form method="post" class="card">
<input type="hidden" name="step" value="3">
<h2>Firmendaten</h2>

<label>Firmenname *</label>
<input type="text" name="company_name" value="<?= htmlspecialchars($wizard['company']['name'] ?? '') ?>" required>

<label>Offizieller Name (falls abweichend)</label>
<input type="text" name="legal_name" value="<?= htmlspecialchars($wizard['company']['legal_name'] ?? '') ?>" placeholder="z.B. Kontur Cosmetics GmbH">
<p class="hint">Wie im Handelsregister eingetragen</p>

<label>Rechtsform *</label>
<select name="company_type" required>
    <option value="">– bitte wählen –</option>
    <?php foreach ($companyTypes as $key => $label): ?>
    <option value="<?= $key ?>" <?= ($wizard['company']['company_type'] ?? '') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
    <?php endforeach; ?>
</select>

<label>Geschäftsart *</label>
<div class="checkbox-group">
<?php foreach ($businessKinds as $key => $label): ?>
    <label><input type="checkbox" name="business_kind[]" value="<?= $key ?>" <?= in_array($key, $wizard['company']['business_kind'] ?? []) ? 'checked' : '' ?>> <?= htmlspecialchars($label) ?></label>
<?php endforeach; ?>
</div>

<label>Branche</label>
<input type="text" name="industry" value="<?= htmlspecialchars($wizard['company']['industry'] ?? '') ?>" placeholder="z.B. Kosmetik, Handwerk, IT">

<h2>Adresse</h2>
<label>Straße + Hausnummer *</label>
<input type="text" name="street" value="<?= htmlspecialchars($wizard['company']['street'] ?? '') ?>" required>

<div style="display:grid;grid-template-columns:120px 1fr;gap:.5rem">
    <div>
        <label>PLZ *</label>
        <input type="text" name="postal" value="<?= htmlspecialchars($wizard['company']['postal'] ?? '') ?>" required>
    </div>
    <div>
        <label>Ort *</label>
        <input type="text" name="city" value="<?= htmlspecialchars($wizard['company']['city'] ?? '') ?>" required>
    </div>
</div>

<label>Land</label>
<input type="text" name="country" value="<?= htmlspecialchars($wizard['company']['country'] ?? 'DE') ?>">

<h2>Kontakt</h2>
<label>E-Mail *</label>
<input type="email" name="company_email" value="<?= htmlspecialchars($wizard['company']['email'] ?? '') ?>" required>

<label>Telefon</label>
<input type="tel" name="company_phone" value="<?= htmlspecialchars($wizard['company']['phone'] ?? '') ?>">

<div class="nav-buttons">
    <a href="?step=1" class="btn btn-secondary">← Zurück</a>
    <button type="submit" class="btn">Weiter →</button>
</div>
</form>

<?php elseif ($step === 4): ?>

<form method="post" class="card">
<input type="hidden" name="step" value="4">

<h2>Inhaber / Geschäftsführer</h2>
<p class="hint">Mindestens eine Person ist erforderlich (Impressumspflicht)</p>

<div id="owners">
<?php
$owners = $wizard['legal']['owners'] ?? [['name' => '', 'role' => '']];
if (empty($owners)) { $owners = [['name' => '', 'role' => '']]; }
foreach ($owners as $i => $o):
?>
<div class="owner-row">
    <div>
        <label>Name *</label>
        <input type="text" name="owner_name[]" value="<?= htmlspecialchars($o['name']) ?>" placeholder="Vor- und Nachname">
    </div>
    <div>
        <label>Funktion</label>
        <input type="text" name="owner_role[]" value="<?= htmlspecialchars($o['role']) ?>" placeholder="z.B. Geschäftsführer, Inhaber">
    </div>
    <button type="button" class="remove-btn" onclick="this.closest('.owner-row').remove()" title="Entfernen">×</button>
</div>
<?php endforeach; ?>
</div>
<button type="button" class="add-btn" onclick="addOwner()">+ Weiteren Inhaber hinzufügen</button>

<h2>Steuerdaten</h2>

<label>Steuernummer</label>
<input type="text" name="tax_number" value="<?= htmlspecialchars($wizard['legal']['tax_number'] ?? '') ?>" placeholder="z.B. 12/345/67890">
<p class="hint">Falls noch nicht vorhanden: wird beim Finanzamt Ihres Betriebssitzes beantragt</p>

<label>USt-IdNr.</label>
<input type="text" name="vat_id" value="<?= htmlspecialchars($wizard['legal']['vat_id'] ?? '') ?>" placeholder="z.B. DE123456789">
<p class="hint">Pflicht bei innergemeinschaftlichem Handel. Beantragung: <a href="https://www.bzst.de" target="_blank">www.bzst.de</a></p>

<label>Steuerliche Identifikationsnummer (Steuer-ID)</label>
<input type="text" name="tax_id" value="<?= htmlspecialchars($wizard['legal']['tax_id'] ?? '') ?>" placeholder="11-stellige Nummer">
<p class="hint">Wird automatisch vom BZSt zugewiesen. Falls nicht vorhanden: <a href="https://www.bzst.de/DE/Privatpersonen/SteuerlicheIdentifikationsnummer/steuerlicheidentifikationsnummer_node.html" target="_blank">erneut anfordern</a></p>

<h2>Handelsregister</h2>
<p class="hint">Nur für eingetragene Gesellschaften (GmbH, UG, AG, e.K., etc.)</p>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem">
    <div>
        <label>Registergericht</label>
        <input type="text" name="register_court" value="<?= htmlspecialchars($wizard['legal']['register_court'] ?? '') ?>" placeholder="z.B. Amtsgericht Köln">
    </div>
    <div>
        <label>Registernummer</label>
        <input type="text" name="register_number" value="<?= htmlspecialchars($wizard['legal']['register_number'] ?? '') ?>" placeholder="z.B. HRB 12345">
    </div>
</div>

<h2>Aufsichtsbehörde / Kammer</h2>
<p class="hint">Pflicht für Freiberufler mit Kammerzugehörigkeit (Ärzte, Anwälte, Steuerberater etc.)</p>

<label>Zuständige Kammer / Aufsichtsbehörde</label>
<input type="text" name="authority_name" value="<?= htmlspecialchars($wizard['legal']['authority_name'] ?? '') ?>" placeholder="z.B. Ärztekammer Nordrhein">

<label>Website der Aufsichtsbehörde</label>
<input type="text" name="authority_url" value="<?= htmlspecialchars($wizard['legal']['authority_url'] ?? '') ?>" placeholder="https://...">

<label>Berufsbezeichnung</label>
<input type="text" name="job_title" value="<?= htmlspecialchars($wizard['legal']['job_title'] ?? '') ?>" placeholder="z.B. Rechtsanwalt, Arzt">

<label>Berufsbezeichnung verliehen in</label>
<input type="text" name="job_title_country" value="<?= htmlspecialchars($wizard['legal']['job_title_country'] ?? 'Deutschland') ?>">

<label>Berufsrechtliche Regelung / Kammer-Website</label>
<input type="text" name="chamber_name" value="<?= htmlspecialchars($wizard['legal']['chamber_name'] ?? '') ?>" placeholder="z.B. BRAO, StBerG – www.brak.de">

<div class="nav-buttons">
    <a href="?step=2" class="btn btn-secondary">← Zurück</a>
    <button type="submit" class="btn">Weiter →</button>
</div>
</form>

<script>
function addOwner(){
    const c=document.getElementById('owners');
    const row=document.createElement('div');row.className='owner-row';
    row.innerHTML='<div><label>Name *</label><input type="text" name="owner_name[]" placeholder="Vor- und Nachname"></div>'
        +'<div><label>Funktion</label><input type="text" name="owner_role[]" placeholder="z.B. Geschäftsführer"></div>'
        +'<button type="button" class="remove-btn" onclick="this.closest(\'.owner-row\').remove()" title="Entfernen">×</button>';
    c.appendChild(row);
}
</script>

<?php elseif ($step === 5): ?>

<?php if (!empty($wizard['hints'])): ?>
<div class="hint-box">
    <h3>Hinweise zu fehlenden Angaben</h3>
    <?php foreach ($wizard['hints'] as $h): ?>
    <p><strong><?= htmlspecialchars($h['field']) ?>:</strong> <?= $h['text'] ?></p>
    <?php endforeach; ?>
    <p style="font-size:.8rem;margin-top:.5rem;color:#92400e">Diese Daten können Sie nach der Installation in den Einstellungen ergänzen.</p>
</div>
<?php endif; ?>

<form method="post" class="card">
<input type="hidden" name="step" value="5">

<h2>Hauptbenutzer anlegen (Pflicht)</h2>
<p class="hint">Dieser Benutzer erhält vollen Administratorzugang. Eine Einladungs-E-Mail mit Bestätigungslink wird gesendet.</p>

<?php
$primaryUser = $wizard['users'][0] ?? ['email' => '', 'display_name' => '', 'role' => 'administrator'];
?>
<div style="margin-bottom:1.5rem">
    <label>E-Mail des Hauptbenutzers *</label>
    <input type="email" name="user_email[]" value="<?= htmlspecialchars($primaryUser['email']) ?>" required>
    <label>Name *</label>
    <input type="text" name="user_display_name[]" value="<?= htmlspecialchars($primaryUser['display_name']) ?>" placeholder="Vor- und Nachname" required>
    <input type="hidden" name="user_role[]" value="administrator">
</div>

<h2>Weitere Benutzer (optional)</h2>
<p class="hint">Weitere Mitarbeiter können hier eingeladen oder später im CRM angelegt werden.</p>

<div id="users">
<?php
$extraUsers = array_slice($wizard['users'] ?? [], 1);
foreach ($extraUsers as $i => $u):
?>
<div class="user-row">
    <div>
        <label>E-Mail</label>
        <input type="email" name="user_email[]" value="<?= htmlspecialchars($u['email']) ?>">
    </div>
    <div>
        <label>Name</label>
        <input type="text" name="user_display_name[]" value="<?= htmlspecialchars($u['display_name']) ?>" placeholder="Vor- und Nachname">
    </div>
    <div>
        <label>Rolle</label>
        <select name="user_role[]">
        <?php foreach ($userRoles as $rk => $rl): ?>
            <option value="<?= $rk ?>" <?= ($u['role'] ?? '') === $rk ? 'selected' : '' ?>><?= htmlspecialchars($rl) ?></option>
        <?php endforeach; ?>
        </select>
    </div>
    <button type="button" class="remove-btn" onclick="this.closest('.user-row').remove()" title="Entfernen">×</button>
</div>
<?php endforeach; ?>
</div>
<button type="button" class="add-btn" onclick="addUser()">+ Weiteren Benutzer hinzufügen</button>

<div class="nav-buttons">
    <a href="?step=3" class="btn btn-secondary">← Zurück</a>
    <button type="submit" class="btn">Installation abschließen</button>
</div>
</form>

<script>
function addUser(){
    const c=document.getElementById('users');
    const row=document.createElement('div');row.className='user-row';
    row.innerHTML='<div><label>E-Mail *</label><input type="email" name="user_email[]" required></div>'
        +'<div><label>Name</label><input type="text" name="user_display_name[]" placeholder="Vor- und Nachname"></div>'
        +'<div><label>Rolle</label><select name="user_role[]">'
        <?php foreach ($userRoles as $rk => $rl): ?>+'<option value="<?= $rk ?>"><?= htmlspecialchars($rl) ?></option>'<?php endforeach; ?>
        +'</select></div>'
        +'<button type="button" class="remove-btn" onclick="this.closest(\'.user-row\').remove()" title="Entfernen">×</button>';
    c.appendChild(row);
}
</script>

<?php endif; ?>

</div>
</body>
</html>
