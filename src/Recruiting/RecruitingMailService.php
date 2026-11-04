<?php
declare(strict_types=1);

/**
 * Recruiting-Mails: HR bei neuer Bewerbung, Bewerber-Eingangsbestätigung und Statuswechsel.
 * Versand scheitert still (Log) — Speichern der Bewerbung bleibt möglich.
 */
final class RecruitingMailService
{
    /** Status, bei denen der Bewerber informiert wird (nicht: received/screening/withdrawn). */
    private const APPLICANT_STATUS_NOTIFY = [
        JobApplicationRepository::STATUS_INTERVIEW,
        JobApplicationRepository::STATUS_OFFER,
        JobApplicationRepository::STATUS_HIRED,
        JobApplicationRepository::STATUS_REJECTED,
    ];

    public static function afterPublicApplication(int $applicationId): void
    {
        if ($applicationId < 1) {
            return;
        }

        try {
            self::sendHrNewApplication($applicationId);
            self::sendApplicantConfirmation($applicationId);
        } catch (Throwable $e) {
            error_log('DG CRM RecruitingMailService (public apply #' . $applicationId . '): ' . $e->getMessage());
        }
    }

    public static function afterStatusChange(int $applicationId, string $fromStatus, string $toStatus): void
    {
        if ($applicationId < 1 || $fromStatus === $toStatus) {
            return;
        }
        if (!in_array($toStatus, self::APPLICANT_STATUS_NOTIFY, true)) {
            return;
        }

        try {
            self::sendApplicantStatus($applicationId, $fromStatus, $toStatus);
        } catch (Throwable $e) {
            error_log('DG CRM RecruitingMailService (status #' . $applicationId . '): ' . $e->getMessage());
        }
    }

    public static function sendHrNewApplication(int $applicationId): bool
    {
        $cfg = RecruitingMailSettings::config();
        if (empty($cfg['send_hr_on_apply'])) {
            return false;
        }

        $to = RecruitingMailSettings::notifyHrEmail($cfg);
        if ($to === '' || !self::mailReady()) {
            return false;
        }

        $app = JobApplicationRepository::find($applicationId);
        if ($app === null) {
            return false;
        }

        $company = self::companyLabel();
        $name = self::applicantDisplayName($app);
        $jobTitle = trim((string) ($app['job_title'] ?? 'Stelle'));
        $email = trim((string) ($app['applicant_email'] ?? ''));
        $crmUrl = self::crmApplicationUrl($applicationId);

        $subject = 'Neue Bewerbung: ' . $name . ' — ' . $jobTitle;
        $html = '<p>Es ist eine neue Bewerbung bei <strong>' . self::h($company) . '</strong> eingegangen.</p>'
            . '<table style="border-collapse:collapse;width:100%;max-width:640px;">'
            . self::row('Stelle', $jobTitle)
            . self::row('Name', $name)
            . self::row('E-Mail', $email)
            . self::row('Telefon', (string) ($app['applicant_phone'] ?? ''))
            . self::row('Ort', (string) ($app['applicant_city'] ?? ''))
            . self::row('Quelle', JobApplicationRepository::sourceOptions()[(string) ($app['source'] ?? '')] ?? (string) ($app['source'] ?? ''))
            . '</table>'
            . '<p><a href="' . self::h($crmUrl) . '">Bewerbung im CRM öffnen</a></p>';

        $ok = self::send($subject, $html, [$to], $email !== '' ? $email : null);
        if ($ok) {
            self::logEvent($applicationId, 'mail_hr', 'HR-Benachrichtigung gesendet an ' . $to);
        }

        return $ok;
    }

    public static function sendApplicantConfirmation(int $applicationId): bool
    {
        $cfg = RecruitingMailSettings::config();
        if (empty($cfg['send_applicant_confirmation'])) {
            return false;
        }
        if (!self::mailReady()) {
            return false;
        }

        $app = JobApplicationRepository::find($applicationId);
        if ($app === null) {
            return false;
        }

        $to = self::applicantEmail($app);
        if ($to === '') {
            return false;
        }

        $company = self::companyLabel();
        $name = self::applicantDisplayName($app);
        $jobTitle = trim((string) ($app['job_title'] ?? 'Stelle'));
        $first = trim((string) ($app['applicant_first_name'] ?? ''));
        $greeting = $first !== '' ? $first : $name;

        $subject = 'Bewerbung eingegangen — ' . $jobTitle;
        $html = '<p>Guten Tag ' . self::h($greeting) . ',</p>'
            . '<p>vielen Dank für Ihre Bewerbung bei <strong>' . self::h($company) . '</strong> '
            . 'auf die Stelle <strong>' . self::h($jobTitle) . '</strong>.</p>'
            . '<p>Wir haben Ihre Unterlagen erhalten und melden uns, sobald wir sie geprüft haben.</p>'
            . '<p>Mit freundlichen Grüßen<br>' . self::h($company) . '</p>';

        $ok = self::send($subject, $html, [$to], null);
        if ($ok) {
            self::logEvent($applicationId, 'mail_applicant', 'Eingangsbestätigung gesendet an ' . $to);
        }

        return $ok;
    }

    public static function sendApplicantStatus(int $applicationId, string $fromStatus, string $toStatus): bool
    {
        $cfg = RecruitingMailSettings::config();
        if (empty($cfg['send_applicant_status'])) {
            return false;
        }
        if (!self::mailReady()) {
            return false;
        }

        $app = JobApplicationRepository::find($applicationId);
        if ($app === null) {
            return false;
        }

        $to = self::applicantEmail($app);
        if ($to === '') {
            return false;
        }

        $company = self::companyLabel();
        $jobTitle = trim((string) ($app['job_title'] ?? 'Stelle'));
        $first = trim((string) ($app['applicant_first_name'] ?? ''));
        $name = self::applicantDisplayName($app);
        $greeting = $first !== '' ? $first : $name;
        $statusLabel = JobApplicationRepository::statusOptions()[$toStatus] ?? $toStatus;

        $body = self::statusBodyHtml($toStatus, $greeting, $company, $jobTitle, $statusLabel);
        $subject = self::statusSubject($toStatus, $jobTitle, $company);

        $ok = self::send($subject, $body, [$to], null);
        if ($ok) {
            $fromLabel = JobApplicationRepository::statusOptions()[$fromStatus] ?? $fromStatus;
            self::logEvent(
                $applicationId,
                'mail_applicant',
                'Status-Mail (' . $fromLabel . ' → ' . $statusLabel . ') an ' . $to
            );
        }

        return $ok;
    }

    private static function statusSubject(string $status, string $jobTitle, string $company): string
    {
        return match ($status) {
            JobApplicationRepository::STATUS_INTERVIEW => 'Einladung zum Gespräch — ' . $jobTitle,
            JobApplicationRepository::STATUS_OFFER => 'Angebot — ' . $jobTitle,
            JobApplicationRepository::STATUS_HIRED => 'Zusage — ' . $jobTitle,
            JobApplicationRepository::STATUS_REJECTED => 'Rückmeldung zu Ihrer Bewerbung — ' . $jobTitle,
            default => 'Aktualisierung Ihrer Bewerbung — ' . $company,
        };
    }

    private static function statusBodyHtml(
        string $status,
        string $greeting,
        string $company,
        string $jobTitle,
        string $statusLabel
    ): string {
        $intro = '<p>Guten Tag ' . self::h($greeting) . ',</p>'
            . '<p>zu Ihrer Bewerbung bei <strong>' . self::h($company) . '</strong> '
            . 'auf die Stelle <strong>' . self::h($jobTitle) . '</strong> gibt es eine Rückmeldung.</p>';

        $detail = match ($status) {
            JobApplicationRepository::STATUS_INTERVIEW =>
                '<p>Wir möchten Sie gerne zu einem Gespräch einladen. '
                . 'Die Details (Termin, Ort oder Videolink) erhalten Sie in Kürze separat oder telefonisch.</p>',
            JobApplicationRepository::STATUS_OFFER =>
                '<p>Wir freuen uns, Ihnen ein Angebot unterbreiten zu können. '
                . 'Die Einzelheiten teilen wir Ihnen gesondert mit.</p>',
            JobApplicationRepository::STATUS_HIRED =>
                '<p>Herzlich willkommen im Team — wir freuen uns auf die Zusammenarbeit. '
                . 'Weitere Schritte zur Einstellung folgen gesondert.</p>',
            JobApplicationRepository::STATUS_REJECTED =>
                '<p>Leider können wir Ihre Bewerbung diesmal nicht weiterverfolgen. '
                . 'Wir danken Ihnen für Ihr Interesse und wünschen Ihnen für Ihren weiteren Weg alles Gute.</p>',
            default =>
                '<p>Neuer Status: <strong>' . self::h($statusLabel) . '</strong>.</p>',
        };

        return $intro . $detail
            . '<p>Mit freundlichen Grüßen<br>' . self::h($company) . '</p>';
    }

    /**
     * @param list<string> $to
     */
    private static function send(string $subject, string $html, array $to, ?string $replyTo): bool
    {
        if ($to === [] || !self::mailReady()) {
            return false;
        }

        try {
            MailService::send(new MailMessage(
                subject: $subject,
                htmlBody: $html,
                to: $to,
                replyTo: $replyTo
            ));

            return true;
        } catch (Throwable $e) {
            error_log('DG CRM RecruitingMailService send: ' . $e->getMessage());

            return false;
        }
    }

    private static function mailReady(): bool
    {
        return class_exists('MailService')
            && class_exists('MailMessage')
            && class_exists('MailSettings')
            && MailSettings::isConfigured();
    }

    /**
     * @param array<string, mixed> $app
     */
    private static function applicantEmail(array $app): string
    {
        $email = trim((string) ($app['applicant_email'] ?? ''));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return '';
        }

        return $email;
    }

    /**
     * @param array<string, mixed> $app
     */
    private static function applicantDisplayName(array $app): string
    {
        $name = trim(
            trim((string) ($app['applicant_first_name'] ?? '')) . ' '
            . trim((string) ($app['applicant_last_name'] ?? ''))
        );
        if ($name !== '') {
            return $name;
        }
        $email = trim((string) ($app['applicant_email'] ?? ''));

        return $email !== '' ? $email : 'Bewerber';
    }

    private static function companyLabel(): string
    {
        if (class_exists('RecruitingPublicService')) {
            return RecruitingPublicService::companyLabel();
        }
        if (class_exists('CompanySettings') && method_exists('CompanySettings', 'displayName')) {
            $name = trim((string) CompanySettings::displayName());
            if ($name !== '') {
                return $name;
            }
        }

        return 'Arbeitgeber';
    }

    private static function crmApplicationUrl(int $applicationId): string
    {
        $base = class_exists('App') ? rtrim(App::publicBaseUrl(), '/') : '';

        return $base . '/app?page=hr-bewerber-form&action=edit&id=' . $applicationId;
    }

    private static function row(string $label, string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return '<tr><th style="text-align:left;padding:6px 10px;border-bottom:1px solid #eee;">'
            . self::h($label)
            . '</th><td style="padding:6px 10px;border-bottom:1px solid #eee;">'
            . self::h($value)
            . '</td></tr>';
    }

    private static function logEvent(int $applicationId, string $kind, string $body): void
    {
        try {
            JobApplicationRepository::addEvent($applicationId, $kind, null, null, $body, null);
        } catch (Throwable) {
            // Protokoll optional
        }
    }

    private static function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
