#!/usr/bin/env php
<?php
declare(strict_types=1);

if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

$errors = [];

if (!Database::isConfigured()) {
    fwrite(STDERR, "academy-selftest: DB nicht konfiguriert.\n");
    exit(1);
}

MigrationRunner::runPending();

$pdo = Database::pdo();

if (!$pdo->query("SHOW TABLES LIKE 'dg_academy_courses'")->fetchColumn()) {
    $errors[] = 'Migration 070 nicht angewendet (dg_academy_courses fehlt).';
}

if (!$pdo->query("SHOW TABLES LIKE 'dg_academy_course_modules'")->fetchColumn()) {
    $errors[] = 'Migration 071 nicht angewendet (dg_academy_course_modules fehlt).';
}

$departments = AcademyRepository::allDepartments();
if ($departments === []) {
    $errors[] = 'Keine CRM-Abteilungen — Kursliste leer.';
}

$course = AcademyRepository::findCourseBySlug('lager-platz-check');
if ($course === null) {
    $errors[] = 'Seed-Kurs lager-platz-check fehlt.';
} else {
    $modules = AcademyRepository::modulesForCourse((int) $course['id']);
    if (count($modules) < 2) {
        $errors[] = 'Seed-Kurs sollte mindestens 2 Module haben.';
    }
}

if (!AcademyTier::allows(AcademyTier::STARTER)) {
    $errors[] = 'Tier-Logik Starter fehlgeschlagen.';
}

if ($errors !== []) {
    foreach ($errors as $err) {
        fwrite(STDERR, 'FAIL: ' . $err . "\n");
    }
    exit(1);
}

echo "academy-selftest: OK\n";
