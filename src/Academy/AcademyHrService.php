<?php
declare(strict_types=1);

final class AcademyHrService
{
    public static function ensurePendingCertificate(int $assignmentId): void
    {
        $assignment = AcademyRepository::findAssignmentById($assignmentId);
        if ($assignment === null || empty($assignment['certificate_enabled'])) {
            return;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT id FROM dg_academy_certificates WHERE assignment_id = :assignment_id LIMIT 1'
        );
        $stmt->execute(['assignment_id' => $assignmentId]);
        if ($stmt->fetchColumn()) {
            return;
        }

        $validDays = max(1, (int) ($assignment['certificate_valid_days'] ?? 365));
        $number = self::nextCertificateNumber();

        $insert = Database::pdo()->prepare(
            'INSERT INTO dg_academy_certificates
                (certificate_number, assignment_id, user_id, course_id, course_version, scope, valid_from, valid_until, status)
             VALUES
                (:number, :assignment_id, :user_id, :course_id, :version, :scope, CURDATE(), DATE_ADD(CURDATE(), INTERVAL :days DAY), \'pending\')'
        );
        $insert->execute([
            'number' => $number,
            'assignment_id' => $assignmentId,
            'user_id' => (int) ($assignment['user_id'] ?? 0),
            'course_id' => (int) ($assignment['course_id'] ?? 0),
            'version' => (string) ($assignment['course_version'] ?? '1.0'),
            'scope' => in_array(($assignment['certificate_scope'] ?? ''), ['platform', 'company'], true)
                ? (string) $assignment['certificate_scope'] : 'company',
            'days' => $validDays,
        ]);
    }

    public static function approve(int $assignmentId, int $reviewerId, string $note = ''): void
    {
        self::review($assignmentId, $reviewerId, 'approved', $note);
        self::notifyUser($assignmentId, true, $note);
    }

    public static function reject(int $assignmentId, int $reviewerId, string $note): void
    {
        if (trim($note) === '') {
            throw new InvalidArgumentException('Bitte eine Begründung für die Ablehnung angeben.');
        }
        self::review($assignmentId, $reviewerId, 'rejected', $note);
        self::notifyUser($assignmentId, false, $note);
    }

    private static function review(int $assignmentId, int $reviewerId, string $certStatus, string $note): void
    {
        $assignment = AcademyRepository::findAssignmentById($assignmentId);
        if ($assignment === null) {
            throw new InvalidArgumentException('Zuweisung nicht gefunden.');
        }

        $assignmentStatus = $certStatus === 'approved' ? 'completed' : 'rejected';

        Database::pdo()->prepare(
            'UPDATE dg_academy_assignments SET status = :status, updated_at = NOW() WHERE id = :id'
        )->execute(['status' => $assignmentStatus, 'id' => $assignmentId]);

        Database::pdo()->prepare(
            'UPDATE dg_academy_certificates SET status = :status, reviewed_by = :reviewer, reviewed_at = NOW(), review_note = :note
             WHERE assignment_id = :assignment_id'
        )->execute([
            'status' => $certStatus,
            'reviewer' => $reviewerId,
            'note' => trim($note),
            'assignment_id' => $assignmentId,
        ]);
    }

    private static function notifyUser(int $assignmentId, bool $approved, string $note): void
    {
        if (!class_exists('MailService') || !MailSettings::isConfigured()) {
            return;
        }

        $assignment = AcademyRepository::findAssignmentById($assignmentId);
        if ($assignment === null) {
            return;
        }

        $userId = (int) ($assignment['user_id'] ?? 0);
        $user = UserRepository::findById($userId);
        if ($user === null || trim($user->email) === '') {
            return;
        }

        $courseTitle = (string) ($assignment['course_title'] ?? 'Schulung');
        $link = App::publicBaseUrl() . '/app?page=akademie&view=meine';
        $name = htmlspecialchars($user->displayName, ENT_QUOTES, 'UTF-8');
        $titleEsc = htmlspecialchars($courseTitle, ENT_QUOTES, 'UTF-8');

        if ($approved) {
            $subject = 'Akademie: Schulung freigegeben — ' . $courseTitle;
            $html = '<p>Guten Tag ' . $name . ',</p>'
                . '<p>Ihre Schulung „' . $titleEsc . '“ wurde von HR freigegeben.</p>'
                . '<p>Das Zertifikat steht in der Akademie bereit.</p>'
                . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">Akademie öffnen</a></p>';
        } else {
            $subject = 'Akademie: Schulung — Nachbesserung erforderlich';
            $html = '<p>Guten Tag ' . $name . ',</p>'
                . '<p>Ihre Schulung „' . $titleEsc . '“ konnte nicht freigegeben werden.</p>'
                . '<p><strong>Hinweis:</strong> ' . htmlspecialchars(trim($note), ENT_QUOTES, 'UTF-8') . '</p>'
                . '<p>Bitte wiederholen Sie die Schulung oder wenden Sie sich an HR.</p>'
                . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">Akademie öffnen</a></p>';
        }

        MailService::send(new MailMessage(
            subject: $subject,
            htmlBody: $html,
            to: [$user->email],
        ));
    }

    private static function nextCertificateNumber(): string
    {
        $year = date('Y');
        $stmt = Database::pdo()->prepare(
            'SELECT certificate_number FROM dg_academy_certificates
             WHERE certificate_number LIKE :prefix ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['prefix' => 'AKD-' . $year . '-%']);
        $last = (string) ($stmt->fetchColumn() ?: '');
        $seq = 1;
        if ($last !== '' && preg_match('/AKD-' . $year . '-(\d+)/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return sprintf('AKD-%s-%06d', $year, $seq);
    }
}
