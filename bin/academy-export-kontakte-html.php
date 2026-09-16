#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Exportiert Kontakte-Seiten (Liste, Neu, Bearbeiten) als HTML für Academy-Screenshots.
 *
 * php bin/academy-export-kontakte-html.php [--base=https://ganz-soft.de] [--user-id=9] [--contact-id=ID]
 */

if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}

require_once DG_ROOT . '/src/autoload.php';

MigrationRunner::runPending();

$baseUrl = 'https://ganz-soft.de';
$userId = 0;
$contactId = 0;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--base=')) {
        $baseUrl = rtrim(substr($arg, 7), '/') . '/';
    } elseif (str_starts_with($arg, '--user-id=')) {
        $userId = (int) substr($arg, 10);
    } elseif (str_starts_with($arg, '--contact-id=')) {
        $contactId = (int) substr($arg, 13);
    }
}

if (!Database::isConfigured()) {
    fwrite(STDERR, "Keine DB-Konfiguration.\n");
    exit(1);
}

$user = $userId > 0 ? UserRepository::findById($userId) : null;
if ($user === null) {
    $foundId = (int) (Database::pdo()->query(
        "SELECT id FROM dg_users WHERE role IN ('administrator', 'admin') ORDER BY id LIMIT 1"
    )->fetchColumn() ?: 0);
    $user = $foundId > 0 ? UserRepository::findById($foundId) : null;
}
if ($user === null || !MenuRegistry::canAccess($user, 'kontakte')) {
    fwrite(STDERR, "Kein Admin mit Kontakte-Zugriff.\n");
    exit(1);
}

$outDir = DG_ROOT . '/storage/media/training/kontakte';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}

/** @return array<string, mixed> */
function layoutVars(User $user): array
{
    return [
        'user' => $user,
        'navMode' => RoleResolver::navMode($user),
        'departments' => RoleResolver::departmentsFor($user),
        'menuItems' => MenuRegistry::modules($user),
        'settingsItem' => MenuRegistry::settingsItem($user),
        'buchhaltungSection' => MenuRegistry::buchhaltungSection($user),
        'websiteSection' => MenuRegistry::websiteSection($user),
        'kdvSection' => MenuRegistry::kdvSection($user),
        'sidebarItems' => MenuRegistry::sidebarItems($user),
        'flash' => null,
        'canEdit' => RoleResolver::canEdit($user),
        'dbConfig' => DatabaseSettings::forForm(),
        'dbConnected' => true,
        'settingsNav' => null,
        'settingsSelection' => null,
        'area' => null,
        'dept' => null,
    ];
}

function exportHtml(string $baseUrl, string $path, array $layoutData, string $contentTemplate, string $title, string $currentPage, array $extra = []): void
{
    $layoutData['contentTemplate'] = $contentTemplate;
    $layoutData['title'] = $title;
    $layoutData['currentPage'] = $currentPage;
    $layoutData = array_merge($layoutData, $extra);

    ob_start();
    View::render('layout/app', $layoutData);
    $html = ob_get_clean();

    if (!str_contains($html, '<base ')) {
        $html = preg_replace(
            '/<head>/i',
            '<head>' . "\n" . '  <base href="' . htmlspecialchars($baseUrl, ENT_QUOTES) . '">',
            $html,
            1
        ) ?? $html;
    }

    file_put_contents($path, $html);
    echo "HTML: {$path}\n";
}

$layout = layoutVars($user);
$allowedContactRoles = ContactAccessResolver::allowedContactRoleOptions($user);

// Demo-Kontakt für Bearbeiten-Ansicht
$demoContact = null;
if ($contactId > 0) {
    $demoContact = ContactRepository::findById($contactId);
}
if ($demoContact === null) {
    $stmt = Database::pdo()->query(
        "SELECT id FROM dg_contacts WHERE login LIKE 'akademie.%' OR login LIKE 'demo.%' ORDER BY id LIMIT 1"
    );
    $fallbackId = (int) ($stmt->fetchColumn() ?: 0);
    if ($fallbackId > 0) {
        $demoContact = ContactRepository::findById($fallbackId);
    }
}
if ($demoContact === null) {
    $list = ContactRepository::paginate('', 1, $user);
    $demoContact = $list['items'][0] ?? null;
}

// Liste
exportHtml(
    $baseUrl,
    $outDir . '/kontakte-list.html',
    $layout,
    'modules/kontakte',
    'Kontakte',
    'kontakte',
    [
        'contactList' => ContactRepository::paginate('', 1, $user),
        'contactSearch' => '',
    ]
);

// Neu
$form = ContactRepository::emptyForm();
$form['salutation'] = 'Herr';
$form['first_name'] = 'Max';
$form['last_name'] = 'Mustermann';
$form['contact_role'] = 'dg_kunde';
$linkNew = ContactCompanyLinkRepository::formContext(null, []);
exportHtml(
    $baseUrl,
    $outDir . '/kontakte-new.html',
    $layout,
    'modules/kontakte-form',
    'Neuer Kontakt',
    'kontakte',
    array_merge([
        'contactId' => null,
        'form' => $form,
        'formError' => null,
        'bankAccounts' => ContactRepository::defaultBankAccounts(),
        'employeeData' => EmployeeData::empty(),
        'employeeFiles' => ContactFileStorage::emptyFiles(),
        'showEmployeeFields' => false,
        'allowedContactRoles' => $allowedContactRoles,
        'canDeleteContact' => false,
        'kontakteReturnTo' => '',
    ], $linkNew)
);

// Bearbeiten
if ($demoContact === null) {
    fwrite(STDERR, "Kein Kontakt für Bearbeiten-Export — bitte --contact-id setzen.\n");
    exit(1);
}
$linkEdit = ContactCompanyLinkRepository::formContext($demoContact, []);
exportHtml(
    $baseUrl,
    $outDir . '/kontakte-edit.html',
    $layout,
    'modules/kontakte-form',
    'Kontakt bearbeiten',
    'kontakte',
    array_merge([
        'contactId' => $demoContact->id,
        'form' => ContactRepository::toForm($demoContact),
        'formError' => null,
        'bankAccounts' => $demoContact->bankAccounts !== [] ? $demoContact->bankAccounts : ContactRepository::defaultBankAccounts(),
        'employeeData' => $demoContact->employeeData,
        'employeeFiles' => $demoContact->employeeFiles,
        'showEmployeeFields' => ContactAccessResolver::canViewEmployeeHrData($user, $demoContact),
        'allowedContactRoles' => $allowedContactRoles,
        'canDeleteContact' => ContactAccessResolver::canDeleteContact($user, $demoContact),
        'kontakteReturnTo' => '',
    ], $linkEdit)
);

// Firma (Anrede Firma — Block „Mitarbeiter der Firma“ sichtbar)
$formFirma = ContactRepository::emptyForm();
$formFirma['salutation'] = 'Firma';
$formFirma['company_name'] = 'Demo Akademie GmbH';
$formFirma['login'] = 'akademie.demo.firma';
$formFirma['contact_role'] = 'dg_kunde';
$linkFirma = ContactCompanyLinkRepository::formContext(null, []);
exportHtml(
    $baseUrl,
    $outDir . '/kontakte-edit-firma.html',
    $layout,
    'modules/kontakte-form',
    'Kontakt bearbeiten — Firma',
    'kontakte',
    array_merge([
        'contactId' => $demoContact->id,
        'form' => $formFirma,
        'formError' => null,
        'bankAccounts' => ContactRepository::defaultBankAccounts(),
        'employeeData' => EmployeeData::empty(),
        'employeeFiles' => ContactFileStorage::emptyFiles(),
        'showEmployeeFields' => false,
        'allowedContactRoles' => $allowedContactRoles,
        'canDeleteContact' => false,
        'kontakteReturnTo' => '',
        'companyEmployees' => [ContactCompanyLinkRepository::emptyEmployeeRow()],
    ], $linkFirma)
);

// Mitarbeiter (HR-Felder sichtbar)
$formEmployee = ContactRepository::toForm($demoContact);
$formEmployee['contact_role'] = 'dg_mitarbeiter';
$formEmployee['salutation'] = 'Herr';
$employeeDemo = $demoContact->employeeData !== [] ? $demoContact->employeeData : EmployeeData::empty();
$employeeDemo['employment_relationship'] = $employeeDemo['employment_relationship'] ?: 'Vollzeit';
$employeeDemo['social_security_status'] = $employeeDemo['social_security_status'] ?: 'pending';
$employeeDemo['social_filing_office'] = $employeeDemo['social_filing_office'] ?: 'kk_employee';
exportHtml(
    $baseUrl,
    $outDir . '/kontakte-edit-mitarbeiter.html',
    $layout,
    'modules/kontakte-form',
    'Kontakt bearbeiten — Mitarbeiter',
    'kontakte',
    array_merge([
        'contactId' => $demoContact->id,
        'form' => $formEmployee,
        'formError' => null,
        'bankAccounts' => $demoContact->bankAccounts !== [] ? $demoContact->bankAccounts : ContactRepository::defaultBankAccounts(),
        'employeeData' => $employeeDemo,
        'employeeFiles' => $demoContact->employeeFiles,
        'showEmployeeFields' => true,
        'allowedContactRoles' => $allowedContactRoles,
        'canDeleteContact' => false,
        'kontakteReturnTo' => '',
    ], $linkEdit)
);

file_put_contents($outDir . '/kontakte-export.meta.json', json_encode([
    'base_url' => $baseUrl,
    'demo_contact_id' => $demoContact->id,
    'demo_contact_login' => $demoContact->login,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Demo-Kontakt: id={$demoContact->id} login={$demoContact->login}\n";
