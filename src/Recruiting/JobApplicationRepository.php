<?php
declare(strict_types=1);

/** Bewerbungen inkl. Status-Pipeline und Ereignisprotokoll. */
final class JobApplicationRepository
{
    public const STATUS_RECEIVED = 'received';
    public const STATUS_SCREENING = 'screening';
    public const STATUS_INTERVIEW = 'interview';
    public const STATUS_OFFER = 'offer';
    public const STATUS_HIRED = 'hired';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_WITHDRAWN = 'withdrawn';

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_WEBSITE = 'website';
    public const SOURCE_XING = 'xing';
    public const SOURCE_LINKEDIN = 'linkedin';
    public const SOURCE_OTHER = 'other';

    /** Standard-Aufbewahrung nach Absage/Ende (Monate) — Überschreibbar via RecruitingPrivacySettings. */
    public const DEFAULT_RETENTION_MONTHS = 6;

    public static function retentionMonths(): int
    {
        if (class_exists('RecruitingPrivacySettings')) {
            return RecruitingPrivacySettings::retentionMonths();
        }

        return self::DEFAULT_RETENTION_MONTHS;
    }

    public static function ensureReady(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
    }

    public static function tableReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            Database::pdo()->query('SELECT 1 FROM dg_job_applications LIMIT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_RECEIVED => 'Eingegangen',
            self::STATUS_SCREENING => 'In Prüfung',
            self::STATUS_INTERVIEW => 'Gespräch',
            self::STATUS_OFFER => 'Angebot',
            self::STATUS_HIRED => 'Eingestellt',
            self::STATUS_REJECTED => 'Abgesagt',
            self::STATUS_WITHDRAWN => 'Zurückgezogen',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sourceOptions(): array
    {
        return [
            self::SOURCE_MANUAL => 'Manuell im CRM',
            self::SOURCE_WEBSITE => 'Bewerbungsseite',
            self::SOURCE_XING => 'Xing (Link)',
            self::SOURCE_LINKEDIN => 'LinkedIn (Link)',
            self::SOURCE_OTHER => 'Sonstiges',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listAll(int $limit = 200): array
    {
        self::ensureReady();
        if (!Database::isConfigured() || !self::tableReady()) {
            return [];
        }
        $limit = max(1, min(500, $limit));
        $stmt = Database::pdo()->query(
            'SELECT a.*,
                    ap.email AS applicant_email,
                    ap.first_name AS applicant_first_name,
                    ap.last_name AS applicant_last_name,
                    ap.phone AS applicant_phone,
                    p.title AS job_title,
                    p.status AS job_status
             FROM dg_job_applications a
             INNER JOIN dg_applicants ap ON ap.id = a.applicant_id
             INNER JOIN dg_job_postings p ON p.id = a.job_posting_id
             ORDER BY a.updated_at DESC
             LIMIT ' . $limit
        );
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        self::ensureReady();
        if (!Database::isConfigured() || !self::tableReady() || $id < 1) {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT a.*,
                    ap.email AS applicant_email,
                    ap.first_name AS applicant_first_name,
                    ap.last_name AS applicant_last_name,
                    ap.phone AS applicant_phone,
                    ap.city AS applicant_city,
                    ap.notes AS applicant_notes,
                    ap.contact_id AS applicant_contact_id,
                    ap.privacy_consent_at AS applicant_privacy_consent_at,
                    ap.retention_until AS applicant_retention_until,
                    p.title AS job_title
             FROM dg_job_applications a
             INNER JOIN dg_applicants ap ON ap.id = a.applicant_id
             INNER JOIN dg_job_postings p ON p.id = a.job_posting_id
             WHERE a.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function events(int $applicationId): array
    {
        self::ensureReady();
        if (!Database::isConfigured() || !self::tableReady() || $applicationId < 1) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_job_application_events WHERE application_id = :id ORDER BY id ASC'
        );
        $stmt->execute(['id' => $applicationId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function emptyForm(): array
    {
        return [
            'job_posting_id' => '',
            'applicant_id' => '',
            'email' => '',
            'first_name' => '',
            'last_name' => '',
            'phone' => '',
            'city' => '',
            'notes' => '',
            'status' => self::STATUS_RECEIVED,
            'cover_letter' => '',
            'source' => self::SOURCE_MANUAL,
            'retention_until' => '',
            'privacy_consent' => '0',
            'contact_id' => '0',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function formForId(int $id): array
    {
        $row = self::find($id);
        if ($row === null) {
            return self::emptyForm();
        }

        return [
            'job_posting_id' => (string) ($row['job_posting_id'] ?? ''),
            'applicant_id' => (string) ($row['applicant_id'] ?? ''),
            'email' => (string) ($row['applicant_email'] ?? ''),
            'first_name' => (string) ($row['applicant_first_name'] ?? ''),
            'last_name' => (string) ($row['applicant_last_name'] ?? ''),
            'phone' => (string) ($row['applicant_phone'] ?? ''),
            'city' => (string) ($row['applicant_city'] ?? ''),
            'notes' => (string) ($row['applicant_notes'] ?? ''),
            'status' => self::sanitizeStatus((string) ($row['status'] ?? self::STATUS_RECEIVED)),
            'cover_letter' => (string) ($row['cover_letter'] ?? ''),
            'source' => self::sanitizeSource((string) ($row['source'] ?? self::SOURCE_MANUAL)),
            'retention_until' => (string) ($row['retention_until'] ?? ''),
            'privacy_consent' => !empty($row['applicant_privacy_consent_at']) ? '1' : '0',
            'contact_id' => (string) ((int) ($row['applicant_contact_id'] ?? 0)),
        ];
    }

    /**
     * Speichert Bewerber + Bewerbung. Bei Statuswechsel Ereignis + Fristen setzen.
     *
     * @param array<string, mixed> $input
     */
    public static function save(array $input, ?int $id = null, ?int $userId = null): int
    {
        self::ensureReady();
        if (!Database::isConfigured() || !self::tableReady()) {
            throw new RuntimeException('Bewerbungs-Tabelle fehlt — Migration 136 ausführen.');
        }

        $jobId = (int) ($input['job_posting_id'] ?? 0);
        if ($jobId < 1 || JobPostingRepository::find($jobId) === null) {
            throw new InvalidArgumentException('Bitte eine Stelle wählen.');
        }

        $status = self::sanitizeStatus((string) ($input['status'] ?? self::STATUS_RECEIVED));
        $source = self::sanitizeSource((string) ($input['source'] ?? self::SOURCE_MANUAL));
        $cover = trim((string) ($input['cover_letter'] ?? ''));

        $applicantId = (int) ($input['applicant_id'] ?? 0);
        $applicantPayload = [
            'email' => (string) ($input['email'] ?? ''),
            'first_name' => (string) ($input['first_name'] ?? ''),
            'last_name' => (string) ($input['last_name'] ?? ''),
            'phone' => (string) ($input['phone'] ?? ''),
            'city' => (string) ($input['city'] ?? ''),
            'notes' => (string) ($input['notes'] ?? ''),
            'retention_until' => (string) ($input['applicant_retention_until'] ?? ''),
            'privacy_consent_at' => '',
        ];
        // contact_id nur setzen, wenn explizit übergeben — sonst bestehende Verknüpfung behalten
        if (array_key_exists('contact_id', $input)) {
            $applicantPayload['contact_id'] = (int) $input['contact_id'];
        }
        if (!empty($input['privacy_consent'])) {
            $applicantPayload['privacy_consent_at'] = date('Y-m-d H:i:s');
        } elseif ($applicantId > 0) {
            $existingAp = ApplicantRepository::find($applicantId);
            $applicantPayload['privacy_consent_at'] = (string) ($existingAp['privacy_consent_at'] ?? '');
        }

        $applicantId = ApplicantRepository::save($applicantPayload, $applicantId > 0 ? $applicantId : null);

        $pdo = Database::pdo();
        $now = date('Y-m-d H:i:s');

        if ($id !== null && $id > 0) {
            $existing = self::find($id);
            if ($existing === null) {
                throw new InvalidArgumentException('Bewerbung nicht gefunden.');
            }
            $prevStatus = (string) ($existing['status'] ?? '');
            if ((int) ($existing['applicant_id'] ?? 0) !== $applicantId
                || (int) ($existing['job_posting_id'] ?? 0) !== $jobId
            ) {
                // Duplikat-Schutz bei Wechsel Stelle/Person
                $dup = $pdo->prepare(
                    'SELECT id FROM dg_job_applications
                     WHERE job_posting_id = :job AND applicant_id = :ap AND id <> :id LIMIT 1'
                );
                $dup->execute(['job' => $jobId, 'ap' => $applicantId, 'id' => $id]);
                if ($dup->fetchColumn() !== false) {
                    throw new InvalidArgumentException('Für diese Stelle existiert bereits eine Bewerbung dieser Person.');
                }
            }

            $rejectedAt = $existing['rejected_at'] ?? null;
            $hiredAt = $existing['hired_at'] ?? null;
            $retentionUntil = self::nullableDate($input['retention_until'] ?? ($existing['retention_until'] ?? null));
            $statusChangedAt = (string) ($existing['status_changed_at'] ?? $now);

            if ($prevStatus !== $status) {
                $statusChangedAt = $now;
                if ($status === self::STATUS_REJECTED || $status === self::STATUS_WITHDRAWN) {
                    $rejectedAt = $now;
                    if ($retentionUntil === null) {
                        $retentionUntil = self::defaultRetentionDate();
                    }
                }
                if ($status === self::STATUS_HIRED) {
                    $hiredAt = $now;
                    $rejectedAt = null;
                }
                if (!in_array($status, [self::STATUS_REJECTED, self::STATUS_WITHDRAWN, self::STATUS_HIRED], true)) {
                    $rejectedAt = null;
                    $hiredAt = null;
                }
            }

            $stmt = $pdo->prepare(
                'UPDATE dg_job_applications SET
                    job_posting_id = :job_posting_id,
                    applicant_id = :applicant_id,
                    status = :status,
                    cover_letter = :cover_letter,
                    source = :source,
                    status_changed_at = :status_changed_at,
                    rejected_at = :rejected_at,
                    hired_at = :hired_at,
                    retention_until = :retention_until
                 WHERE id = :id'
            );
            $stmt->execute([
                'job_posting_id' => $jobId,
                'applicant_id' => $applicantId,
                'status' => $status,
                'cover_letter' => $cover,
                'source' => $source,
                'status_changed_at' => $statusChangedAt,
                'rejected_at' => $rejectedAt,
                'hired_at' => $hiredAt,
                'retention_until' => $retentionUntil,
                'id' => $id,
            ]);

            if ($prevStatus !== $status) {
                self::addEvent(
                    $id,
                    'status',
                    $prevStatus,
                    $status,
                    'Status: ' . (self::statusOptions()[$prevStatus] ?? $prevStatus)
                        . ' → ' . (self::statusOptions()[$status] ?? $status),
                    $userId
                );
                if (class_exists('RecruitingMailService')) {
                    RecruitingMailService::afterStatusChange($id, $prevStatus, $status);
                }
            }

            if ($status === self::STATUS_HIRED) {
                self::syncHire($id, $userId);
            }

            return $id;
        }

        $dup = $pdo->prepare(
            'SELECT id FROM dg_job_applications WHERE job_posting_id = :job AND applicant_id = :ap LIMIT 1'
        );
        $dup->execute(['job' => $jobId, 'ap' => $applicantId]);
        $existingId = (int) ($dup->fetchColumn() ?: 0);
        if ($existingId > 0) {
            throw new InvalidArgumentException('Für diese Stelle existiert bereits eine Bewerbung dieser Person.');
        }

        $rejectedAt = null;
        $hiredAt = null;
        $retentionUntil = self::nullableDate($input['retention_until'] ?? null);
        if ($status === self::STATUS_REJECTED || $status === self::STATUS_WITHDRAWN) {
            $rejectedAt = $now;
            $retentionUntil = $retentionUntil ?? self::defaultRetentionDate();
        }
        if ($status === self::STATUS_HIRED) {
            $hiredAt = $now;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO dg_job_applications (
                job_posting_id, applicant_id, status, cover_letter, source,
                status_changed_at, rejected_at, hired_at, retention_until, created_by
            ) VALUES (
                :job_posting_id, :applicant_id, :status, :cover_letter, :source,
                :status_changed_at, :rejected_at, :hired_at, :retention_until, :created_by
            )'
        );
        $stmt->execute([
            'job_posting_id' => $jobId,
            'applicant_id' => $applicantId,
            'status' => $status,
            'cover_letter' => $cover,
            'source' => $source,
            'status_changed_at' => $now,
            'rejected_at' => $rejectedAt,
            'hired_at' => $hiredAt,
            'retention_until' => $retentionUntil,
            'created_by' => ($userId !== null && $userId > 0) ? $userId : null,
        ]);
        $newId = (int) $pdo->lastInsertId();
        self::addEvent($newId, 'created', null, $status, 'Bewerbung erfasst', $userId);

        if ($status === self::STATUS_HIRED) {
            self::syncHire($newId, $userId);
        }

        return $newId;
    }

    private static function syncHire(int $applicationId, ?int $userId): void
    {
        if (!RecruitingHireService::contactsReady()) {
            return;
        }
        RecruitingHireService::ensureEmployeeFromApplication($applicationId, $userId);
    }

    public static function addEvent(
        int $applicationId,
        string $kind,
        ?string $fromStatus,
        ?string $toStatus,
        string $body,
        ?int $userId
    ): void {
        if ($applicationId < 1 || !Database::isConfigured() || !self::tableReady()) {
            return;
        }
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_job_application_events
                (application_id, event_kind, from_status, to_status, body, created_by)
             VALUES
                (:aid, :kind, :from_status, :to_status, :body, :by)'
        );
        $stmt->execute([
            'aid' => $applicationId,
            'kind' => mb_substr($kind, 0, 32),
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'body' => $body,
            'by' => ($userId !== null && $userId > 0) ? $userId : null,
        ]);
    }

    public static function sanitizeStatus(string $status): string
    {
        $opts = self::statusOptions();

        return isset($opts[$status]) ? $status : self::STATUS_RECEIVED;
    }

    public static function sanitizeSource(string $source): string
    {
        $opts = self::sourceOptions();

        return isset($opts[$source]) ? $source : self::SOURCE_MANUAL;
    }

    public static function defaultRetentionDate(): string
    {
        return date('Y-m-d', strtotime('+' . self::retentionMonths() . ' months') ?: time());
    }

    private static function nullableDate(mixed $value): ?string
    {
        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }
        $ts = strtotime($s);

        return $ts !== false ? date('Y-m-d', $ts) : null;
    }
}
