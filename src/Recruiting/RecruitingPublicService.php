<?php
declare(strict_types=1);

/** Öffentliche Bewerbungsseite /rekrut (pro Instanz). */
final class RecruitingPublicService
{
    /** @deprecated Nutze retentionHintMonths() — folgt Instanz-Einstellung. */
    public const RETENTION_HINT_MONTHS = JobApplicationRepository::DEFAULT_RETENTION_MONTHS;

    public static function retentionHintMonths(): int
    {
        return JobApplicationRepository::retentionMonths();
    }

    public static function publicRetentionHint(): string
    {
        if (class_exists('RecruitingPrivacySettings')) {
            return RecruitingPrivacySettings::publicRetentionHint();
        }

        return 'Ihre Angaben speichern wir nur für dieses Bewerbungsverfahren. '
            . 'Nach Absage oder Verfahrensende löschen wir sie in der Regel nach '
            . self::retentionHintMonths() . ' Monaten (laufende Verfahren ausgenommen).';
    }

    public static function publicConsentLabel(): string
    {
        if (class_exists('RecruitingPrivacySettings')) {
            return RecruitingPrivacySettings::publicConsentLabel();
        }

        return 'Ich willige ein, dass meine Bewerbungsdaten für dieses Bewerbungsverfahren gespeichert und verarbeitet werden. *';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function publishedJobs(): array
    {
        return JobPostingRepository::listPublished();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function publishedJobBySlug(string $slug): ?array
    {
        return JobPostingRepository::findPublishedBySlug($slug);
    }

    public static function privacyPageUrl(): string
    {
        if (Database::isConfigured() && class_exists('WebsitePageRepository')) {
            try {
                $page = WebsitePageRepository::findBySlug('datenschutz');
                if (is_array($page) && ($page['status'] ?? '') === WebsitePageRepository::STATUS_PUBLISHED) {
                    return rtrim(App::publicBaseUrl(), '/') . '/datenschutz';
                }
            } catch (Throwable) {
                // Fallback unten
            }
        }

        return rtrim(App::publicBaseUrl(), '/') . '/datenschutz';
    }

    /**
     * Öffentliche Bewerbung: Bewerber + Bewerbung (Quelle website), optional Dateien.
     *
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files $_FILES['recruiting_files'] o. Ä.
     */
    public static function submitApplication(array $post, array $files = []): int
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Bewerbung derzeit nicht möglich.');
        }
        MigrationRunner::runPending();

        $jobId = (int) ($post['job_posting_id'] ?? 0);
        $job = JobPostingRepository::find($jobId);
        if ($job === null || (string) ($job['status'] ?? '') !== JobPostingRepository::STATUS_PUBLISHED) {
            throw new InvalidArgumentException('Diese Stelle ist nicht (mehr) veröffentlicht.');
        }

        if (empty($post['privacy_consent'])) {
            throw new InvalidArgumentException('Bitte der Speicherung Ihrer Daten zustimmen.');
        }

        $payload = [
            'job_posting_id' => $jobId,
            'email' => (string) ($post['email'] ?? ''),
            'first_name' => (string) ($post['first_name'] ?? ''),
            'last_name' => (string) ($post['last_name'] ?? ''),
            'phone' => (string) ($post['phone'] ?? ''),
            'city' => (string) ($post['city'] ?? ''),
            'cover_letter' => (string) ($post['cover_letter'] ?? ''),
            'status' => JobApplicationRepository::STATUS_RECEIVED,
            'source' => JobApplicationRepository::SOURCE_WEBSITE,
            'privacy_consent' => '1',
            'notes' => '',
        ];

        $applicationId = JobApplicationRepository::save($payload, null, null);

        if ($files !== []) {
            $kind = RecruitingFileStorage::sanitizeKind((string) ($post['file_kind'] ?? RecruitingFileStorage::KIND_CV));
            RecruitingFileStorage::storeUploads($applicationId, $files, null, $kind);
        }

        if (class_exists('RecruitingMailService')) {
            RecruitingMailService::afterPublicApplication($applicationId);
        }

        return $applicationId;
    }

    public static function companyLabel(): string
    {
        try {
            if (class_exists('CompanySettings')) {
                $name = trim((string) CompanySettings::displayName());
                if ($name !== '') {
                    return $name;
                }
            }
        } catch (Throwable) {
            // fallback
        }

        return (string) (App::config('crm_name') ?: 'Karriere');
    }
}
