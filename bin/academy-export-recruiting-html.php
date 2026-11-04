#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Exportiert Recruiting-Seiten (Stellen/Bewerber Liste + Formular) als HTML für Academy-Screenshots.
 *
 * php bin/academy-export-recruiting-html.php [--base=https://ganz-om.de] [--user-id=9]
 */

if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}

require_once DG_ROOT . '/src/autoload.php';

MigrationRunner::runPending();

$baseUrl = 'https://ganz-om.de';
$userId = 0;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--base=')) {
        $baseUrl = rtrim(substr($arg, 7), '/') . '/';
    } elseif (str_starts_with($arg, '--user-id=')) {
        $userId = (int) substr($arg, 10);
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
if ($user === null || !MenuRegistry::canAccess($user, 'hr-stellen')) {
    fwrite(STDERR, "Kein Benutzer mit Stellen-Zugriff.\n");
    exit(1);
}

$outDir = DG_ROOT . '/storage/media/training/recruiting';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}

/** @return array<string, mixed> */
function recruitingLayoutVars(User $user): array
{
    return [
        'user' => $user,
        'navMode' => RoleResolver::navMode($user),
        'departments' => RoleResolver::departmentsFor($user),
        'menuItems' => MenuRegistry::modules($user),
        'settingsItem' => MenuRegistry::settingsItem($user),
        'dienstleistungenSection' => MenuRegistry::dienstleistungenSection($user),
        'hrSection' => MenuRegistry::hrSection($user),
        'lohnSection' => MenuRegistry::lohnSection($user),
        'warenwirtschaftSection' => MenuRegistry::warenwirtschaftSection($user),
        'buchhaltungSection' => MenuRegistry::buchhaltungSection($user),
        'websiteSection' => MenuRegistry::websiteSection($user),
        'kommunikationSection' => MenuRegistry::kommunikationSection($user),
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

/**
 * @param array<string, mixed> $layoutData
 * @param array<string, mixed> $extra
 */
function recruitingExportHtml(
    string $baseUrl,
    string $path,
    array $layoutData,
    string $contentTemplate,
    string $title,
    string $currentPage,
    array $extra = []
): void {
    $layoutData['contentTemplate'] = $contentTemplate;
    $layoutData['title'] = $title;
    $layoutData['currentPage'] = $currentPage;
    $layoutData = array_merge($layoutData, $extra);

    ob_start();
    View::render('layout/app', $layoutData);
    $html = (string) ob_get_clean();

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

$layout = recruitingLayoutVars($user);

JobPostingRepository::ensureReady();
JobApplicationRepository::ensureReady();

$demoJobId = 0;
foreach (JobPostingRepository::listAll(50) as $row) {
    $title = (string) ($row['title'] ?? '');
    if (str_contains(mb_strtolower($title), 'akademie') || str_contains(mb_strtolower($title), 'demo')) {
        $demoJobId = (int) ($row['id'] ?? 0);
        break;
    }
}
if ($demoJobId < 1) {
    $demoJobId = JobPostingRepository::save([
        'title' => 'Akademie Demo Stelle',
        'summary' => 'Demo für Schulungsvideo — keine echte Ausschreibung.',
        'body' => "Wir suchen Verstärkung im Team.\n\nAufgaben: Organisation und Kundenkontakt.",
        'location' => 'Musterstadt',
        'employment_type' => 'full_time',
        'status' => JobPostingRepository::STATUS_PUBLISHED,
        'export_xing' => '0',
        'export_linkedin' => '0',
    ], null, $user->id);
}

$jobForm = JobPostingRepository::formForId($demoJobId);
$departments = [];
if (class_exists('DepartmentRepository') && method_exists('DepartmentRepository', 'optionsForSelect')) {
    try {
        $departments = DepartmentRepository::optionsForSelect();
    } catch (Throwable) {
        $departments = [];
    }
}

recruitingExportHtml(
    $baseUrl,
    $outDir . '/hr-stellen.html',
    $layout,
    'modules/hr-stellen',
    'Stellen',
    'hr-stellen',
    [
        'jobPostings' => JobPostingRepository::listAll(),
        'dbConnected' => true,
    ]
);

recruitingExportHtml(
    $baseUrl,
    $outDir . '/hr-stelle-form.html',
    $layout,
    'modules/hr-stelle-form',
    'Stelle bearbeiten',
    'hr-stellen',
    [
        'jobForm' => $jobForm,
        'jobPostingId' => $demoJobId,
        'formError' => null,
        'departmentOptions' => $departments,
        'publishEvents' => JobPostingRepository::publishEvents($demoJobId),
        'dbConnected' => true,
    ]
);

$demoAppId = 0;
foreach (JobApplicationRepository::listAll(50) as $app) {
    $email = (string) ($app['applicant_email'] ?? '');
    if (str_contains($email, 'akademie.demo') || str_contains($email, 'demo.bewerber')) {
        $demoAppId = (int) ($app['id'] ?? 0);
        break;
    }
}
if ($demoAppId < 1) {
    $demoAppId = JobApplicationRepository::save([
        'job_posting_id' => $demoJobId,
        'email' => 'akademie.demo@example.test',
        'first_name' => 'Alex',
        'last_name' => 'Beispiel',
        'phone' => '0123 456789',
        'city' => 'Musterstadt',
        'cover_letter' => 'Demo-Bewerbung für das Schulungsvideo.',
        'status' => JobApplicationRepository::STATUS_SCREENING,
        'source' => JobApplicationRepository::SOURCE_WEBSITE,
        'privacy_consent' => '1',
        'notes' => 'Academy-Demo',
    ], null, $user->id);
}

$appForm = JobApplicationRepository::formForId($demoAppId);
$jobOptions = [];
foreach (JobPostingRepository::listAll(100) as $j) {
    $jobOptions[] = [
        'id' => (int) ($j['id'] ?? 0),
        'title' => (string) ($j['title'] ?? ''),
    ];
}

recruitingExportHtml(
    $baseUrl,
    $outDir . '/hr-bewerber.html',
    $layout,
    'modules/hr-bewerber',
    'Bewerber',
    'hr-bewerber',
    [
        'jobApplications' => JobApplicationRepository::listAll(),
        'dbConnected' => true,
    ]
);

recruitingExportHtml(
    $baseUrl,
    $outDir . '/hr-bewerber-form.html',
    $layout,
    'modules/hr-bewerber-form',
    'Bewerbung bearbeiten',
    'hr-bewerber',
    [
        'applicationForm' => $appForm,
        'applicationId' => $demoAppId,
        'formError' => null,
        'jobPostingOptions' => $jobOptions,
        'applicationEvents' => JobApplicationRepository::events($demoAppId),
        'applicationFiles' => RecruitingFileStorage::listForApplication($demoAppId),
        'dbConnected' => true,
    ]
);

$meta = [
    'exported_at' => date('c'),
    'base_url' => $baseUrl,
    'user_id' => $user->id,
    'kind' => 'recruiting',
    'demo_job_id' => $demoJobId,
    'demo_application_id' => $demoAppId,
];
file_put_contents(
    $outDir . '/recruiting-export.meta.json',
    json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);
echo "Meta: {$outDir}/recruiting-export.meta.json\n";
