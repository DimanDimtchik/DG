<?php
declare(strict_types=1);

/**
 * Exportiert Akademie-Seiten als HTML für Academy-Screenshots.
 *
 * php bin/academy-export-akademie-html.php [--base=https://ganz-soft.de] [--user-id=9]
 */

if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}

require_once DG_ROOT . '/src/autoload.php';

MigrationRunner::runPending();

$baseUrl = 'https://ganz-soft.de';
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
if ($user === null || !MenuRegistry::canAccess($user, 'akademie')) {
    fwrite(STDERR, "Kein Admin mit Akademie-Zugriff.\n");
    exit(1);
}

$outDir = DG_ROOT . '/storage/media/training/akademie';
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

/**
 * @param array<string, mixed> $layoutData
 * @param array<string, mixed> $extra
 */
function exportHtml(
    string $baseUrl,
    string $path,
    array $layoutData,
    string $contentTemplate,
    string $title,
    string $currentPage,
    array $extra = []
): string {
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

    // Cookie-Banner und Störhinweise entfernen (auch fragmentierte Reste)
    $html = preg_replace('/<style>\s*\.dg-cc-overlay[\s\S]*?<\/style>/i', '', $html) ?? $html;
    $html = preg_replace('/<div[^>]*id="dg-cookie-consent"[^>]*>[\s\S]*?<\/div>\s*<script>window\.dgCookieConsent[\s\S]*?<\/script>/i', '', $html) ?? $html;
    $html = preg_replace('/<div class="dg-cc-details"[\s\S]*?<div class="dg-cc-actions">[\s\S]*?<\/div>\s*<\/div>\s*<\/div>\s*<script>window\.dgCookieConsent[\s\S]*?<\/script>/i', '', $html) ?? $html;
    $html = preg_replace('/<script>window\.dgCookieConsent[\s\S]*?<\/script>/i', '', $html) ?? $html;

    file_put_contents($path, $html);
    echo "HTML: {$path}\n";

    return $html;
}

$layout = layoutVars($user);
$canManageAcademy = RoleResolver::isAdmin($user);
$canAcademyHr = RoleResolver::isAdmin($user);
$academyTierPlan = AcademyTier::currentPlan();
$academyAreas = AcademyRepository::allDepartments();
$academyDepartments = $academyAreas;
$academyCatalog = array_values(array_filter(
    AcademyRepository::publishedCoursesWithPlayableModules(),
    static fn (array $c): bool => AcademyTier::allows((string) ($c['min_tier'] ?? AcademyTier::STARTER))
));
$academyPendingHr = [];
$academyAllCourses = $canManageAcademy ? AcademyRepository::allCourses() : [];
$academyLibraryVideos = $canManageAcademy ? AcademyRepository::libraryVideos(true, true) : [];
$academyCoursesByDepartment = $canManageAcademy ? AcademyRepository::coursesGroupedByDepartment() : [];
$academyUserOptions = $canManageAcademy ? AcademyRepository::userOptions() : [];
$academyAssignments = AcademyRepository::assignmentsForUser((int) $user->id);

// Demo-Zuweisung, wenn leer — nur für Screenshot, keine DB-Schreiberei
if ($academyAssignments === [] && $academyCatalog !== []) {
    $demo = $academyCatalog[0];
    $academyAssignments = [[
        'course_title' => (string) ($demo['title'] ?? 'CRM-Einstieg'),
        'course_slug' => (string) ($demo['slug'] ?? 'crm-dashboard'),
        'area_label' => (string) ($demo['area_label'] ?? 'Allgemein'),
        'status' => 'in_progress',
        'access_mode' => AcademyAccessMode::COMPARE,
    ]];
}

$demoCourse = AcademyRepository::findCourseBySlug('kontakte')
    ?? AcademyRepository::findCourseBySlug('lager')
    ?? ($academyCatalog[0] ?? null);
if ($demoCourse === null) {
    fwrite(STDERR, "Kein veröffentlichter Kurs mit Videos gefunden.\n");
    exit(1);
}

$demoCourseId = (int) ($demoCourse['id'] ?? 0);
$demoAssignment = AcademyRepository::findAssignment((int) $user->id, $demoCourseId);
if ($demoAssignment === null) {
    $demoAssignment = AcademyRepository::ensureAssignment((int) $user->id, $demoCourseId, (int) $user->id);
}
$demoSummary = $demoAssignment !== null
    ? AcademyProgressService::assignmentSummary((int) $demoAssignment['id'])
    : null;

$demoModules = is_array($demoSummary['modules'] ?? null) ? $demoSummary['modules'] : [];
$demoModule = $demoModules[0] ?? null;
$demoModuleId = (int) ($demoModule['id'] ?? 0);
if ($demoModuleId < 1) {
    $moduleIds = AcademyRepository::moduleIdsForCourse($demoCourseId);
    $demoModuleId = (int) ($moduleIds[0] ?? 0);
    $demoModule = $demoModuleId > 0 ? AcademyRepository::findModule($demoModuleId) : null;
} else {
    $demoModule = AcademyRepository::findModule($demoModuleId);
}

$commonBase = [
    'academyAreas' => $academyAreas,
    'academyDepartments' => $academyDepartments,
    'academyCatalog' => $academyCatalog,
    'academyPendingHr' => $academyPendingHr,
    'academyTierPlan' => $academyTierPlan,
    'canManageAcademy' => $canManageAcademy,
    'canAcademyHr' => $canAcademyHr,
    'academyAdminTab' => 'kurse',
    'academyAdminDepartmentId' => '',
    'academyAdminCourse' => null,
    'academyAdminVideo' => null,
    'academyAllCourses' => $academyAllCourses,
    'academyUserOptions' => $academyUserOptions,
    'academyCoursesByDepartment' => $academyCoursesByDepartment,
    'academyLibraryVideos' => $academyLibraryVideos,
    'academyAllVideos' => $academyLibraryVideos,
    'academyCourseModuleIds' => [],
    'academyCourseDepartmentIds' => [],
    'academyModuleDepartmentIds' => [],
    'dbConnected' => true,
];

exportHtml(
    $baseUrl,
    $outDir . '/akademie-meine.html',
    $layout,
    'modules/akademie',
    'Akademie',
    'akademie',
    array_merge($commonBase, [
        'academyView' => 'meine',
        'academyAssignments' => $academyAssignments,
        'academyCourse' => null,
        'academyModule' => null,
        'academyAssignment' => null,
        'academySummary' => null,
        'academyRulesAccepted' => false,
    ])
);

exportHtml(
    $baseUrl,
    $outDir . '/akademie-katalog.html',
    $layout,
    'modules/akademie',
    'Akademie — Katalog',
    'akademie',
    array_merge($commonBase, [
        'academyView' => 'katalog',
        'academyAssignments' => $academyAssignments,
        'academyCourse' => null,
        'academyModule' => null,
        'academyAssignment' => null,
        'academySummary' => null,
        'academyRulesAccepted' => false,
    ])
);

exportHtml(
    $baseUrl,
    $outDir . '/akademie-hr.html',
    $layout,
    'modules/akademie',
    'Akademie — HR-Prüfung',
    'akademie',
    array_merge($commonBase, [
        'academyView' => 'hr',
        'academyAssignments' => $academyAssignments,
        'academyCourse' => null,
        'academyModule' => null,
        'academyAssignment' => null,
        'academySummary' => null,
        'academyRulesAccepted' => false,
        'academyPendingHr' => [],
    ])
);

exportHtml(
    $baseUrl,
    $outDir . '/akademie-admin.html',
    $layout,
    'modules/akademie',
    'Akademie — Verwaltung',
    'akademie',
    array_merge($commonBase, [
        'academyView' => 'admin',
        'academyAdminTab' => 'kurse',
        'academyAssignments' => $academyAssignments,
        'academyCourse' => null,
        'academyModule' => null,
        'academyAssignment' => null,
        'academySummary' => null,
        'academyRulesAccepted' => false,
    ])
);

exportHtml(
    $baseUrl,
    $outDir . '/akademie-kurs-regeln.html',
    $layout,
    'modules/akademie',
    'Akademie — Schulungsregeln',
    'akademie',
    array_merge($commonBase, [
        'academyView' => 'kurs',
        'academyAssignments' => $academyAssignments,
        'academyCourse' => $demoCourse,
        'academyModule' => null,
        'academyAssignment' => $demoAssignment,
        'academySummary' => null,
        'academyRulesAccepted' => false,
    ])
);

exportHtml(
    $baseUrl,
    $outDir . '/akademie-kurs-module.html',
    $layout,
    'modules/akademie',
    'Akademie — Kursmodule',
    'akademie',
    array_merge($commonBase, [
        'academyView' => 'kurs',
        'academyAssignments' => $academyAssignments,
        'academyCourse' => $demoCourse,
        'academyModule' => null,
        'academyAssignment' => $demoAssignment,
        'academySummary' => $demoSummary,
        'academyRulesAccepted' => true,
    ])
);

if ($demoModule !== null && $demoAssignment !== null) {
    exportHtml(
        $baseUrl,
        $outDir . '/akademie-modul-player.html',
        $layout,
        'modules/akademie',
        'Akademie — Modul',
        'akademie',
        array_merge($commonBase, [
            'academyView' => 'modul',
            'academyAssignments' => $academyAssignments,
            'academyCourse' => $demoCourse,
            'academyModule' => $demoModule,
            'academyAssignment' => $demoAssignment,
            'academySummary' => $demoSummary,
            'academyRulesAccepted' => true,
        ])
    );
} else {
    fwrite(STDERR, "Warnung: Kein Modul für Player-Export.\n");
}

file_put_contents($outDir . '/akademie-export.meta.json', json_encode([
    'base_url' => $baseUrl,
    'user_id' => $user->id,
    'demo_course_slug' => (string) ($demoCourse['slug'] ?? ''),
    'demo_module_id' => $demoModuleId,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Akademie-Export fertig.\n";
