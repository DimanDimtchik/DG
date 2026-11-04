<?php
declare(strict_types=1);

/**
 * Recruiting Phase A: Stellen, Bewerber, Pipeline, Dateien (SQLite in-memory).
 */
final class Database
{
    public static PDO $pdo;

    public static function isConfigured(): bool
    {
        return true;
    }

    public static function pdo(): PDO
    {
        return self::$pdo;
    }
}

final class MigrationRunner
{
    public static function runPending(): void
    {
    }
}

final class App
{
    public static function publicBaseUrl(): string
    {
        return 'https://example.test';
    }

    public static function config(string $key, mixed $default = null): mixed
    {
        return $default;
    }
}

require dirname(__DIR__) . '/src/Recruiting/JobPostingRepository.php';
require dirname(__DIR__) . '/src/Recruiting/ApplicantRepository.php';
require dirname(__DIR__) . '/src/Recruiting/JobApplicationRepository.php';
require dirname(__DIR__) . '/src/Recruiting/RecruitingFileStorage.php';
require dirname(__DIR__) . '/src/Recruiting/RecruitingPublicService.php';
require dirname(__DIR__) . '/src/Recruiting/RecruitingRetentionService.php';

function assertRecruit(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}

Database::$pdo = new PDO('sqlite::memory:');
Database::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
Database::$pdo->exec('PRAGMA foreign_keys = ON');

Database::$pdo->exec(
    'CREATE TABLE dg_job_postings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        summary TEXT NULL,
        body TEXT NULL,
        location TEXT NOT NULL DEFAULT "",
        employment_type TEXT NOT NULL DEFAULT "full_time",
        status TEXT NOT NULL DEFAULT "draft",
        department_id TEXT NULL,
        published_at TEXT NULL,
        closed_at TEXT NULL,
        created_by INTEGER NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )'
);
Database::$pdo->exec(
    'CREATE TABLE dg_applicants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE,
        first_name TEXT NOT NULL DEFAULT "",
        last_name TEXT NOT NULL DEFAULT "",
        phone TEXT NOT NULL DEFAULT "",
        city TEXT NOT NULL DEFAULT "",
        notes TEXT NULL,
        contact_id INTEGER NOT NULL DEFAULT 0,
        privacy_consent_at TEXT NULL,
        retention_until TEXT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )'
);
Database::$pdo->exec(
    'CREATE TABLE dg_job_applications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        job_posting_id INTEGER NOT NULL,
        applicant_id INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT "received",
        cover_letter TEXT NULL,
        source TEXT NOT NULL DEFAULT "manual",
        status_changed_at TEXT DEFAULT CURRENT_TIMESTAMP,
        rejected_at TEXT NULL,
        hired_at TEXT NULL,
        retention_until TEXT NULL,
        created_by INTEGER NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (job_posting_id, applicant_id)
    )'
);
Database::$pdo->exec(
    'CREATE TABLE dg_job_application_events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        application_id INTEGER NOT NULL,
        event_kind TEXT NOT NULL DEFAULT "note",
        from_status TEXT NULL,
        to_status TEXT NULL,
        body TEXT NULL,
        created_by INTEGER NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )'
);
Database::$pdo->exec(
    'CREATE TABLE dg_job_application_files (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        application_id INTEGER NOT NULL,
        file_kind TEXT NOT NULL DEFAULT "other",
        original_name TEXT NOT NULL,
        stored_name TEXT NOT NULL,
        mime TEXT NOT NULL DEFAULT "",
        size_bytes INTEGER NOT NULL DEFAULT 0,
        created_by INTEGER NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )'
);

$jobId = JobPostingRepository::save([
    'title' => 'Lagerhelfer (m/w/d)',
    'summary' => 'Unterstützung im Lager',
    'body' => 'Vollzeit in Musterstadt',
    'location' => 'Musterstadt',
    'employment_type' => 'full_time',
    'status' => 'published',
], null, 1);
assertRecruit($jobId > 0, 'Stelle angelegt');
$job = JobPostingRepository::find($jobId);
assertRecruit(is_array($job) && ($job['slug'] ?? '') === 'lagerhelfer-m-w-d', 'Slug aus Titel erzeugt');
assertRecruit(($job['status'] ?? '') === 'published', 'Status veröffentlicht');
assertRecruit(!empty($job['published_at']), 'published_at gesetzt');

$appId = JobApplicationRepository::save([
    'job_posting_id' => $jobId,
    'email' => 'bewerber@example.com',
    'first_name' => 'Anna',
    'last_name' => 'Beispiel',
    'phone' => '0123',
    'city' => 'Musterstadt',
    'status' => 'received',
    'source' => 'manual',
    'cover_letter' => 'Ich bewerbe mich.',
    'privacy_consent' => '1',
], null, 1);
assertRecruit($appId > 0, 'Bewerbung angelegt');

$row = JobApplicationRepository::find($appId);
assertRecruit(is_array($row) && ($row['applicant_email'] ?? '') === 'bewerber@example.com', 'Bewerber per Join lesbar');
assertRecruit(($row['status'] ?? '') === 'received', 'Startstatus Eingegangen');

$events = JobApplicationRepository::events($appId);
assertRecruit($events !== [] && ($events[0]['event_kind'] ?? '') === 'created', 'Ereignis created');

JobApplicationRepository::save([
    'job_posting_id' => $jobId,
    'applicant_id' => (int) ($row['applicant_id'] ?? 0),
    'email' => 'bewerber@example.com',
    'first_name' => 'Anna',
    'last_name' => 'Beispiel',
    'status' => 'rejected',
    'source' => 'manual',
    'cover_letter' => 'Ich bewerbe mich.',
    'privacy_consent' => '1',
], $appId, 1);
$row2 = JobApplicationRepository::find($appId);
assertRecruit(($row2['status'] ?? '') === 'rejected', 'Status Absage');
assertRecruit(!empty($row2['rejected_at']), 'rejected_at gesetzt');
assertRecruit(!empty($row2['retention_until']), 'Löschfrist bei Absage gesetzt');

$events2 = JobApplicationRepository::events($appId);
$hasStatus = false;
foreach ($events2 as $ev) {
    if (($ev['event_kind'] ?? '') === 'status') {
        $hasStatus = true;
        break;
    }
}
assertRecruit($hasStatus, 'Statuswechsel protokolliert');

$tmp = tempnam(sys_get_temp_dir(), 'dgrec');
assertRecruit($tmp !== false, 'Tempdatei');
file_put_contents($tmp, '%PDF-1.4 test');
$savedFiles = RecruitingFileStorage::storeUploads($appId, [
    'name' => 'lebenslauf.pdf',
    'type' => 'application/pdf',
    'tmp_name' => $tmp,
    'error' => UPLOAD_ERR_OK,
    'size' => (int) filesize($tmp),
], 1, RecruitingFileStorage::KIND_CV);
assertRecruit($savedFiles === 1, 'Datei gespeichert');
$files = RecruitingFileStorage::listForApplication($appId);
assertRecruit(count($files) === 1 && ($files[0]['file_kind'] ?? '') === 'cv', 'Datei in Liste');
$fileId = (int) ($files[0]['id'] ?? 0);
$path = RecruitingFileStorage::absolutePath($files[0]);
assertRecruit(is_file($path), 'Datei physisch vorhanden');
assertRecruit(RecruitingFileStorage::delete($fileId, 1), 'Datei gelöscht');
assertRecruit(RecruitingFileStorage::listForApplication($appId) === [], 'Dateiliste leer');

$dupFailed = false;
try {
    JobApplicationRepository::save([
        'job_posting_id' => $jobId,
        'email' => 'bewerber@example.com',
        'first_name' => 'Anna',
        'last_name' => 'Beispiel',
        'status' => 'received',
        'source' => 'manual',
    ], null, 1);
} catch (InvalidArgumentException) {
    $dupFailed = true;
}
assertRecruit($dupFailed, 'Doppelbewerbung gleiche Stelle/E-Mail verhindert');

$published = JobPostingRepository::listPublished();
assertRecruit(count($published) === 1, 'listPublished findet veröffentlichte Stelle');
$bySlug = JobPostingRepository::findPublishedBySlug((string) ($job['slug'] ?? ''));
assertRecruit(is_array($bySlug), 'findPublishedBySlug');
assertRecruit(JobPostingRepository::publicUrl('lagerhelfer-m-w-d') === 'https://example.test/rekrut/lagerhelfer-m-w-d', 'publicUrl');

$pubId = RecruitingPublicService::submitApplication([
    'job_posting_id' => $jobId,
    'email' => 'online@example.com',
    'first_name' => 'Otto',
    'last_name' => 'Online',
    'cover_letter' => 'Online-Bewerbung',
    'privacy_consent' => '1',
], []);
assertRecruit($pubId > 0, 'Öffentliche Bewerbung angelegt');
$pubRow = JobApplicationRepository::find($pubId);
assertRecruit(($pubRow['source'] ?? '') === 'website', 'Quelle website');

$noConsent = false;
try {
    RecruitingPublicService::submitApplication([
        'job_posting_id' => $jobId,
        'email' => 'x@example.com',
        'first_name' => 'X',
        'last_name' => 'Y',
    ], []);
} catch (InvalidArgumentException) {
    $noConsent = true;
}
assertRecruit($noConsent, 'Ohne Datenschutz-Einwilligung abgelehnt');

// Retention: fällige Absage löschen inkl. Datei + verwaister Bewerber
$retJobId = JobPostingRepository::save([
    'title' => 'Test Retention',
    'summary' => 'x',
    'body' => 'y',
    'location' => 'Ort',
    'employment_type' => 'full_time',
    'status' => 'published',
], null, 1);
$retAppId = JobApplicationRepository::save([
    'job_posting_id' => $retJobId,
    'email' => 'retention@example.com',
    'first_name' => 'Ret',
    'last_name' => 'Ension',
    'status' => 'rejected',
    'source' => 'manual',
    'privacy_consent' => '1',
    'retention_until' => '2020-01-01',
], null, 1);
$tmpRet = tempnam(sys_get_temp_dir(), 'dgrec');
assertRecruit($tmpRet !== false, 'Tempdatei Retention');
file_put_contents($tmpRet, '%PDF-1.4 retention');
RecruitingFileStorage::storeUploads($retAppId, [
    'name' => 'cv.pdf',
    'type' => 'application/pdf',
    'tmp_name' => $tmpRet,
    'error' => UPLOAD_ERR_OK,
    'size' => (int) filesize($tmpRet),
], 1, RecruitingFileStorage::KIND_CV);
$retFiles = RecruitingFileStorage::listForApplication($retAppId);
assertRecruit($retFiles !== [], 'Retention-Datei vorhanden');
$retPath = RecruitingFileStorage::absolutePath($retFiles[0]);
$retApplicantId = (int) (JobApplicationRepository::find($retAppId)['applicant_id'] ?? 0);

$keepAppId = JobApplicationRepository::save([
    'job_posting_id' => $retJobId,
    'email' => 'active@example.com',
    'first_name' => 'Akt',
    'last_name' => 'Iv',
    'status' => 'screening',
    'source' => 'manual',
    'privacy_consent' => '1',
    'retention_until' => '2020-01-01',
], null, 1);
$hiredAppId = JobApplicationRepository::save([
    'job_posting_id' => JobPostingRepository::save([
        'title' => 'Hired Spot',
        'summary' => 'x',
        'body' => 'y',
        'location' => 'Ort',
        'employment_type' => 'full_time',
        'status' => 'published',
    ], null, 1),
    'email' => 'hired@example.com',
    'first_name' => 'Hi',
    'last_name' => 'Red',
    'status' => 'hired',
    'source' => 'manual',
    'privacy_consent' => '1',
    'retention_until' => '2020-01-01',
], null, 1);

$purge = RecruitingRetentionService::runAutomatic('2026-11-04');
assertRecruit($purge['deleted_applications'] >= 1, 'Fällige Absage gelöscht');
assertRecruit(JobApplicationRepository::find($retAppId) === null, 'Absage-Akte weg');
assertRecruit(!is_file($retPath), 'Absage-Datei weg');
assertRecruit(ApplicantRepository::find($retApplicantId) === null, 'Verwaister Bewerber gelöscht');
assertRecruit(JobApplicationRepository::find($keepAppId) !== null, 'Laufendes Verfahren bleibt');
assertRecruit(JobApplicationRepository::find($hiredAppId) !== null, 'Eingestellt bleibt');
assertRecruit(
    RecruitingRetentionService::listDueApplications('2026-11-04') === [],
    'Keine weiteren fälligen Absagen'
);

echo "Recruiting Phase-A Selftest bestanden.\n";
