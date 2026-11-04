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
require dirname(__DIR__) . '/src/Recruiting/RecruitingHireService.php';

final class SettingsStore
{
    /** @var array<string, array<string, mixed>> */
    public static array $data = [];

    public static function get(string $key, array $defaults = []): array
    {
        return array_merge($defaults, self::$data[$key] ?? []);
    }

    public static function set(string $key, array $value): void
    {
        self::$data[$key] = $value;
    }
}

final class CompanySettings
{
    public static function config(): array
    {
        return ['name' => 'Test GmbH', 'email' => 'hr@example.test'];
    }

    public static function displayName(): string
    {
        return trim((string) (self::config()['name'] ?? ''));
    }

    public static function mailEmail(): string
    {
        $email = trim((string) (self::config()['email'] ?? ''));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }
}

require dirname(__DIR__) . '/src/Recruiting/RecruitingMailSettings.php';
require dirname(__DIR__) . '/src/Recruiting/RecruitingMailService.php';
require dirname(__DIR__) . '/src/Recruiting/RecruitingPrivacySettings.php';
require dirname(__DIR__) . '/src/Recruiting/XingRecruitingSettings.php';
require dirname(__DIR__) . '/src/Recruiting/XingRecruitingClient.php';
require dirname(__DIR__) . '/src/Recruiting/XingRecruitingPublishService.php';
require dirname(__DIR__) . '/src/Recruiting/LinkedInRecruitingSettings.php';
require dirname(__DIR__) . '/src/Recruiting/LinkedInRecruitingClient.php';
require dirname(__DIR__) . '/src/Recruiting/LinkedInRecruitingPublishService.php';

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
        export_xing INTEGER NOT NULL DEFAULT 0,
        export_linkedin INTEGER NOT NULL DEFAULT 0,
        xing_publish_status TEXT NOT NULL DEFAULT "none",
        linkedin_publish_status TEXT NOT NULL DEFAULT "none",
        xing_published_at TEXT NULL,
        linkedin_published_at TEXT NULL,
        xing_external_ref TEXT NOT NULL DEFAULT "",
        linkedin_external_ref TEXT NOT NULL DEFAULT "",
        xing_last_message TEXT NOT NULL DEFAULT "",
        linkedin_last_message TEXT NOT NULL DEFAULT "",
        published_at TEXT NULL,
        closed_at TEXT NULL,
        created_by INTEGER NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )'
);
Database::$pdo->exec(
    'CREATE TABLE dg_job_posting_publish_events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        job_posting_id INTEGER NOT NULL,
        channel TEXT NOT NULL,
        event_kind TEXT NOT NULL DEFAULT "note",
        from_status TEXT NULL,
        to_status TEXT NULL,
        body TEXT NULL,
        created_by INTEGER NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
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

assertRecruit(!RecruitingHireService::contactsReady(), 'Hire ohne dg_contacts überspringt');

$keepApplicantId = (int) (JobApplicationRepository::find($keepAppId)['applicant_id'] ?? 0);
ApplicantRepository::linkContact($keepApplicantId, 99);
$linked = ApplicantRepository::find($keepApplicantId);
assertRecruit((int) ($linked['contact_id'] ?? 0) === 99, 'linkContact setzt contact_id');
ApplicantRepository::save([
    'email' => 'active@example.com',
    'first_name' => 'Akt',
    'last_name' => 'Iv',
    'phone' => '',
    'city' => '',
    'notes' => 'update ohne contact_id',
    'privacy_consent_at' => date('Y-m-d H:i:s'),
], $keepApplicantId);
$linked2 = ApplicantRepository::find($keepApplicantId);
assertRecruit((int) ($linked2['contact_id'] ?? 0) === 99, 'contact_id bleibt ohne explizites Feld');

$formHired = JobApplicationRepository::formForId($hiredAppId);
assertRecruit(($formHired['status'] ?? '') === 'hired', 'Formular Status hired');
assertRecruit(array_key_exists('contact_id', $formHired), 'Formular enthält contact_id');


assertRecruit(JobPostingRepository::publishChannelsReady(), 'Publish-Kanäle Schema bereit');
$exportJobId = JobPostingRepository::save([
    'title' => 'Export Test',
    'summary' => 'x',
    'body' => 'y',
    'location' => 'Ort',
    'employment_type' => 'full_time',
    'status' => 'published',
    'export_xing' => '1',
    'export_linkedin' => '1',
], null, 1);
$exportRow = JobPostingRepository::find($exportJobId);
assertRecruit(!empty($exportRow['export_xing']), 'Xing-Export gesetzt');
assertRecruit(!empty($exportRow['export_linkedin']), 'LinkedIn-Export gesetzt');
assertRecruit(($exportRow['xing_publish_status'] ?? '') === JobPostingRepository::PUBLISH_QUEUED, 'Xing vorgemerkt');
assertRecruit(($exportRow['linkedin_publish_status'] ?? '') === JobPostingRepository::PUBLISH_QUEUED, 'LinkedIn vorgemerkt');
$pubEvents = JobPostingRepository::publishEvents($exportJobId);
assertRecruit(count($pubEvents) >= 2, 'Publish-Protokoll Einträge');

JobPostingRepository::save([
    'title' => 'Export Test',
    'summary' => 'x',
    'body' => 'y',
    'location' => 'Ort',
    'employment_type' => 'full_time',
    'status' => 'published',
    'export_xing' => '0',
    'export_linkedin' => '1',
], $exportJobId, 1);
$exportRow2 = JobPostingRepository::find($exportJobId);
assertRecruit(empty($exportRow2['export_xing']), 'Xing-Export abgewählt');
assertRecruit(($exportRow2['xing_publish_status'] ?? '') === JobPostingRepository::PUBLISH_WITHDRAWN, 'Xing zurückgezogen');
assertRecruit(($exportRow2['linkedin_publish_status'] ?? '') === JobPostingRepository::PUBLISH_QUEUED, 'LinkedIn bleibt vorgemerkt');

$draftPlan = JobPostingRepository::planChannelState(
    true,
    JobPostingRepository::STATUS_DRAFT,
    JobPostingRepository::PUBLISH_NONE,
    false,
    'Xing'
);
assertRecruit($draftPlan['status'] === JobPostingRepository::PUBLISH_NONE, 'Ohne Veröffentlichung kein Queue');

assertRecruit(XingRecruitingClient::mapJobType('internship') === 'INTERN', 'Xing job_type INTERN');
assertRecruit(!XingRecruitingSettings::isReady(), 'Xing ohne Config nicht bereit');
SettingsStore::set(XingRecruitingSettings::STORE_KEY, [
    'enabled' => true,
    'use_test_contract' => true,
    'auto_activate' => true,
    'auto_publish_queued' => false,
    'organization_id' => '',
    'order_id' => '',
    'company_name' => '',
    'company_profile_url' => '',
    'consumer_key' => 'ck',
    'consumer_secret' => 'cs',
    'access_token' => 'at',
    'access_token_secret' => 'ats',
]);
assertRecruit(XingRecruitingSettings::isReady(), 'Xing mit Test-Vertrag + OAuth bereit');
assertRecruit(XingRecruitingSettings::effectiveOrganizationId(XingRecruitingSettings::config()) === '5160', 'Test Org-ID');
$payload = XingRecruitingClient::buildCreatePayload([
    'id' => 7,
    'title' => 'Lagerhelfer',
    'summary' => 'Kurz',
    'body' => "Absatz 1\n\nAbsatz 2",
    'location' => 'Berlin',
    'slug' => 'lagerhelfer',
    'employment_type' => 'full_time',
]);
assertRecruit(($payload['reply_setting'] ?? '') === 'url', 'Xing reply_setting url');
assertRecruit(($payload['reply_url'] ?? '') === 'https://example.test/rekrut/lagerhelfer', 'Xing reply_url /rekrut');
assertRecruit(($payload['company_name'] ?? '') === 'Test GmbH', 'Xing Firmenname');
assertRecruit((int) ($payload['organization_id'] ?? 0) === 5160, 'Payload Org-ID');
$oauth = XingRecruitingClient::oauthQueryParams(XingRecruitingSettings::config());
assertRecruit(($oauth['oauth_signature_method'] ?? '') === 'PLAINTEXT', 'OAuth PLAINTEXT');
assertRecruit(($oauth['oauth_signature'] ?? '') === 'cs&ats', 'OAuth Signatur');
SettingsStore::set(XingRecruitingSettings::STORE_KEY, array_merge(XingRecruitingSettings::config(), [
    'enabled' => true,
    'use_test_contract' => false,
    'organization_id' => '',
    'order_id' => '',
]));
assertRecruit(!XingRecruitingSettings::isReady(), 'Ohne Vertrag nicht bereit');
$blocked = XingRecruitingPublishService::publishJob(1, 1);
assertRecruit($blocked['ok'] === false, 'Publish ohne Vertrag blockiert');
assertRecruit(str_contains($blocked['message'], 'Jobvertrag') || str_contains($blocked['message'], 'Vertrag'), 'Hinweis Vertragspflicht');

assertRecruit(LinkedInRecruitingClient::mapEmploymentStatus('internship') === 'INTERNSHIP', 'LinkedIn employment INTERNSHIP');
assertRecruit(LinkedInRecruitingClient::externalJobPostingId(42) === 'dg-42', 'LinkedIn external id');
assertRecruit(!LinkedInRecruitingSettings::isReady(), 'LinkedIn ohne Config nicht bereit');
SettingsStore::set(LinkedInRecruitingSettings::STORE_KEY, [
    'enabled' => true,
    'app_review_approved' => false,
    'auto_publish_queued' => false,
    'listing_type' => 'BASIC',
    'company_id' => '12345',
    'client_id' => 'cid',
    'client_secret' => 'csec',
    'access_token' => '',
    'access_token_expires_at' => 0,
]);
assertRecruit(!LinkedInRecruitingSettings::isReady(), 'Ohne App-Review nicht bereit');
$blockedLi = LinkedInRecruitingPublishService::publishJob(1, 1);
assertRecruit($blockedLi['ok'] === false, 'Publish ohne App-Review blockiert');
assertRecruit(str_contains($blockedLi['message'], 'App-Review'), 'Hinweis App-Review');
SettingsStore::set(LinkedInRecruitingSettings::STORE_KEY, array_merge(LinkedInRecruitingSettings::config(), [
    'app_review_approved' => true,
]));
assertRecruit(LinkedInRecruitingSettings::isReady(), 'LinkedIn mit Review + Creds bereit');
assertRecruit(LinkedInRecruitingSettings::companyUrn() === 'urn:li:company:12345', 'Company URN');
$liPayload = LinkedInRecruitingClient::buildJobElement([
    'id' => 9,
    'title' => 'Entwickler',
    'summary' => 'Kurz',
    'body' => 'Lang',
    'location' => 'München',
    'slug' => 'entwickler',
    'employment_type' => 'full_time',
], 'CREATE');
assertRecruit(($liPayload['jobPostingOperationType'] ?? '') === 'CREATE', 'LinkedIn CREATE');
assertRecruit(($liPayload['companyApplyUrl'] ?? '') === 'https://example.test/rekrut/entwickler', 'LinkedIn apply URL');
assertRecruit(($liPayload['company'] ?? '') === 'urn:li:company:12345', 'LinkedIn company URN in payload');
assertRecruit(($liPayload['externalJobPostingId'] ?? '') === 'dg-9', 'LinkedIn external in payload');

assertRecruit(RecruitingMailSettings::notifyHrEmail() === 'hr@example.test', 'HR-Mail Fallback Firmen-E-Mail');
SettingsStore::set(RecruitingMailSettings::STORE_KEY, [
    'send_hr_on_apply' => 1,
    'send_applicant_confirmation' => 1,
    'send_applicant_status' => 1,
    'notify_hr_email' => 'personal@example.test',
]);
assertRecruit(RecruitingMailSettings::notifyHrEmail() === 'personal@example.test', 'HR-Mail Override');
$formMail = RecruitingMailSettings::forForm();
assertRecruit(!empty($formMail['send_hr_on_apply']), 'Mail-Form HR an');
assertRecruit($formMail['effective_hr_email'] === 'personal@example.test', 'Mail-Form effective');
RecruitingMailSettings::saveFromPost([
    'recruiting_send_hr_on_apply' => '1',
    'recruiting_send_applicant_confirmation' => '',
    'recruiting_send_applicant_status' => '1',
    'recruiting_notify_hr_email' => '',
]);
$savedMail = RecruitingMailSettings::config();
assertRecruit(!empty($savedMail['send_hr_on_apply']), 'Mail save HR an');
assertRecruit(empty($savedMail['send_applicant_confirmation']), 'Mail save Bestätigung aus');
assertRecruit(RecruitingMailSettings::notifyHrEmail() === 'hr@example.test', 'Mail save Fallback wieder Firma');
assertRecruit(RecruitingMailService::sendHrNewApplication($keepAppId) === false, 'Ohne SMTP kein HR-Versand');
assertRecruit(RecruitingMailService::sendApplicantConfirmation($keepAppId) === false, 'Ohne SMTP keine Bestätigung');
assertRecruit(RecruitingMailService::sendApplicantStatus($keepAppId, 'received', 'interview') === false, 'Ohne SMTP kein Status-Mail');

assertRecruit(JobApplicationRepository::retentionMonths() === 6, 'Standard-Löschfrist 6 Monate');
assertRecruit(str_contains(RecruitingPublicService::publicRetentionHint(), '6 Monaten'), 'Öffentlicher Hinweis 6 Monate');
SettingsStore::set(RecruitingPrivacySettings::STORE_KEY, [
    'retention_months' => 3,
    'public_retention_hint' => '',
    'public_consent_label' => 'Test-Einwilligung *',
    'dsb_signed_off' => 1,
    'dsb_signed_off_at' => '2026-11-04',
    'dsb_note' => 'Freigabe Test',
]);
assertRecruit(JobApplicationRepository::retentionMonths() === 3, 'Löschfrist aus Settings');
assertRecruit(str_contains(RecruitingPublicService::publicRetentionHint(), '3 Monaten'), 'Hinweis folgt Einstellungen');
assertRecruit(RecruitingPublicService::publicConsentLabel() === 'Test-Einwilligung *', 'Consent-Text Override');
$policy = RecruitingPrivacySettings::privacyPolicyParagraphHtml();
assertRecruit(str_contains($policy, 'Bewerbungen'), 'Datenschutz-Absatz Bewerbungen');
assertRecruit(str_contains($policy, '3 Monaten'), 'Datenschutz-Absatz Frist');
RecruitingPrivacySettings::saveFromPost([
    'recruiting_retention_months' => '12',
    'recruiting_public_retention_hint' => '',
    'recruiting_public_consent_label' => '',
    'recruiting_dsb_signed_off' => '1',
    'recruiting_dsb_note' => 'ok',
]);
assertRecruit(RecruitingPrivacySettings::retentionMonths() === 12, 'Privacy save 12 Monate');
assertRecruit(!empty(RecruitingPrivacySettings::forForm()['dsb_signed_off']), 'DSB-Freigabe gesetzt');

echo "Recruiting Phase-A Selftest bestanden.\n";