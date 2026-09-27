<?php
declare(strict_types=1);

/**
 * Getrennte Mitarbeiter-Zugangseinladungen: CRM-Passwort, Postfach, PIN, App.
 */
final class StaffAccessInviteService
{
    public const CRM_INVITE_TTL_SECONDS = 604800; // 7 Tage
    public const MAILBOX_INVITE_TTL_SECONDS = 172800; // 48 h
    public const DSGVO_HINT = 'Ich bestätige, dass dieses Geheimnis vertraulich übermittelt wird '
        . '(kein ungesicherter Kanal wie offene E-Mail/Chat) und der Mitarbeiter informiert wird.';

    public static function assertCanManage(User $actor, Contact $contact): void
    {
        if (!CrmRole::hasEmployeeProfile($contact->contactRole)) {
            throw new InvalidArgumentException('Nur für Mitarbeiter- oder Administrator-Kontakte.');
        }
        if (!RoleResolver::canEdit($actor)) {
            throw new RuntimeException('Keine Berechtigung.');
        }
        ContactAccessResolver::assertCanEdit($actor, $contact);
    }

    /**
     * @return array<string, array{kind: string, channel: string, created_at: string, detail: ?string}>
     */
    public static function status(int $contactId): array
    {
        return StaffAccessEventRepository::latestByKind($contactId);
    }

    public static function employeeEmail(Contact $contact): string
    {
        $email = OvertimeNotificationRecipients::employeeEmailForContact($contact->id);
        if ($email === null || $email === '') {
            throw new InvalidArgumentException('Kontakt hat keine gültige E-Mail-Adresse für die Einladung.');
        }

        return $email;
    }

    /** CRM-User anlegen/finden (Rolle Mitarbeiter, aktiv). */
    public static function ensureStaffUser(Contact $contact): User
    {
        $existingId = MailboxMemberResolver::findUserIdForContact($contact);
        if ($existingId !== null) {
            $user = UserRepository::findById($existingId);
            if ($user !== null) {
                return $user;
            }
        }

        $email = trim($contact->email);
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $email = self::employeeEmail($contact);
        }

        $byEmail = UserRepository::findByEmail($email);
        if ($byEmail !== null) {
            return $byEmail;
        }

        $login = trim($contact->login);
        if ($login !== '') {
            $byLogin = UserRepository::findByUsername($login);
            if ($byLogin !== null) {
                return $byLogin;
            }
        }

        return UserRepository::createStaffInviteUser($contact, $email);
    }

    public static function sendCrmInvite(User $actor, int $contactId): string
    {
        $contact = self::requireStaffContact($contactId);
        self::assertCanManage($actor, $contact);
        $email = self::employeeEmail($contact);
        $user = self::ensureStaffUser($contact);
        $token = PasswordResetService::issueInviteToken($user->id, self::CRM_INVITE_TTL_SECONDS);
        PasswordResetService::sendActivationInvite(
            $user,
            $email,
            $token,
            (int) (self::CRM_INVITE_TTL_SECONDS / 3600)
        );
        StaffAccessEventRepository::log(
            $contactId,
            StaffAccessEventRepository::KIND_CRM,
            StaffAccessEventRepository::CHANNEL_LINK,
            (int) ($actor->id ?? 0),
            'Einladung gesendet an ' . $email
        );

        return 'CRM-Einladung gesendet an ' . $email . '.';
    }

    public static function setCrmPasswordManual(
        User $actor,
        int $contactId,
        string $password,
        string $confirm,
        bool $dsgvoConfirmed
    ): string {
        if (!$dsgvoConfirmed) {
            throw new InvalidArgumentException('Bitte die DSGVO-Bestätigung für die manuelle Passwortvergabe aktivieren.');
        }
        $contact = self::requireStaffContact($contactId);
        self::assertCanManage($actor, $contact);
        PasswordPolicy::assertValid($password, $confirm);
        $user = self::ensureStaffUser($contact);
        UserRepository::updatePassword((int) $user->id, $password);
        StaffAccessEventRepository::log(
            $contactId,
            StaffAccessEventRepository::KIND_CRM,
            StaffAccessEventRepository::CHANNEL_MANUAL,
            (int) ($actor->id ?? 0),
            'Manuell gesetzt'
        );

        return 'CRM-Passwort manuell gesetzt.';
    }

    public static function sendPinInvite(User $actor, int $contactId): string
    {
        $contact = self::requireStaffContact($contactId);
        self::assertCanManage($actor, $contact);
        $email = self::employeeEmail($contact);
        TimeKioskService::invitePinSet($contactId, $actor, $email);
        StaffAccessEventRepository::log(
            $contactId,
            StaffAccessEventRepository::KIND_PIN,
            StaffAccessEventRepository::CHANNEL_LINK,
            (int) ($actor->id ?? 0),
            'PIN-Link an ' . $email
        );

        return 'PIN-Einladung gesendet an ' . $email . '.';
    }

    public static function setPinManual(
        User $actor,
        int $contactId,
        string $pin,
        bool $dsgvoConfirmed
    ): string {
        if (!$dsgvoConfirmed) {
            throw new InvalidArgumentException('Bitte die DSGVO-Bestätigung für die manuelle PIN-Vergabe aktivieren.');
        }
        $contact = self::requireStaffContact($contactId);
        self::assertCanManage($actor, $contact);
        TimeKioskService::validatePinFormat($pin);
        TimeKioskPinRepository::upsertPin(
            $contactId,
            password_hash(trim($pin), PASSWORD_DEFAULT),
            (int) ($actor->id ?? 0)
        );
        StaffAccessEventRepository::log(
            $contactId,
            StaffAccessEventRepository::KIND_PIN,
            StaffAccessEventRepository::CHANNEL_MANUAL,
            (int) ($actor->id ?? 0),
            'Manuell gesetzt'
        );

        return 'Stempeluhr-PIN manuell gesetzt.';
    }

    public static function sendMailboxInvite(User $actor, int $contactId): string
    {
        $contact = self::requireStaffContact($contactId);
        self::assertCanManage($actor, $contact);
        $email = self::employeeEmail($contact);
        $mailbox = self::requireMailboxForContact($contact);
        $token = MailboxPasswordTokenRepository::issue(
            $contactId,
            (int) $mailbox['id'],
            self::MAILBOX_INVITE_TTL_SECONDS,
            (int) ($actor->id ?? 0)
        );
        self::sendMailboxPasswordEmail($contact, $email, $token);
        StaffAccessEventRepository::log(
            $contactId,
            StaffAccessEventRepository::KIND_MAILBOX,
            StaffAccessEventRepository::CHANNEL_LINK,
            (int) ($actor->id ?? 0),
            'Postfach-Link an ' . $email
        );

        return 'Postfach-Passwort-Einladung gesendet an ' . $email . '.';
    }

    public static function setMailboxPasswordManual(
        User $actor,
        int $contactId,
        string $password,
        string $confirm,
        bool $dsgvoConfirmed
    ): string {
        if (!$dsgvoConfirmed) {
            throw new InvalidArgumentException('Bitte die DSGVO-Bestätigung für die manuelle Postfach-Passwortvergabe aktivieren.');
        }
        if ($password === '' || $password !== $confirm) {
            throw new InvalidArgumentException('Postfach-Passwort und Wiederholung müssen übereinstimmen.');
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Postfach-Passwort mindestens 8 Zeichen.');
        }
        $contact = self::requireStaffContact($contactId);
        self::assertCanManage($actor, $contact);
        $mailbox = self::requireMailboxForContact($contact);
        self::applyMailboxPassword($mailbox, $password);
        StaffAccessEventRepository::log(
            $contactId,
            StaffAccessEventRepository::KIND_MAILBOX,
            StaffAccessEventRepository::CHANNEL_MANUAL,
            (int) ($actor->id ?? 0),
            'Manuell gesetzt'
        );

        return 'Postfach-Passwort manuell gesetzt.';
    }

    public static function completeMailboxPasswordSet(string $plainToken, string $password, string $confirm): void
    {
        if ($password === '' || $password !== $confirm) {
            throw new InvalidArgumentException('Passwort und Wiederholung müssen übereinstimmen.');
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Passwort mindestens 8 Zeichen.');
        }
        $row = MailboxPasswordTokenRepository::consumeValid($plainToken);
        $mailbox = MailboxRepository::findById((int) ($row['mailbox_id'] ?? 0));
        if ($mailbox === null) {
            throw new InvalidArgumentException('Postfach nicht gefunden.');
        }
        self::applyMailboxPassword($mailbox, $password);
        StaffAccessEventRepository::log(
            (int) ($row['contact_id'] ?? 0),
            StaffAccessEventRepository::KIND_MAILBOX,
            StaffAccessEventRepository::CHANNEL_LINK,
            null,
            'Vom Mitarbeiter gesetzt'
        );
    }

    public static function sendAppInvite(User $actor, int $contactId): string
    {
        $contact = self::requireStaffContact($contactId);
        self::assertCanManage($actor, $contact);
        $email = self::employeeEmail($contact);
        MobileAppDownloadService::ensureDownloadsDir();
        $base = rtrim(App::publicBaseUrl(), '/');
        if ($base === '') {
            throw new RuntimeException('Öffentliche Basis-URL fehlt.');
        }
        $connectUrl = $base . '/app-connect?base=' . rawurlencode($base);
        $apkKal = $base . '/' . MobileAppDownloadService::KALENDER_APK;
        $apkMa = $base . '/' . MobileAppDownloadService::MITARBEITER_APK;
        $crmName = (string) App::config('crm_name', 'DG');
        $name = trim($contact->displayName) !== ''
            ? trim($contact->displayName)
            : trim($contact->firstName . ' ' . $contact->lastName);
        if ($name === '') {
            $name = 'Mitarbeiter';
        }

        $html = '<p>Sie wurden zur Mitarbeiter-App von ' . htmlspecialchars($crmName, ENT_QUOTES, 'UTF-8') . ' eingeladen.</p>'
            . '<p><strong>1.</strong> App installieren (Android):</p>'
            . '<ul>'
            . '<li><a href="' . htmlspecialchars($apkMa, ENT_QUOTES, 'UTF-8') . '">Mitarbeiter-App (Android)</a></li>'
            . '<li><a href="' . htmlspecialchars($apkKal, ENT_QUOTES, 'UTF-8') . '">Termin-App (Android, optional)</a></li>'
            . '</ul>'
            . '<p>iOS: Download folgt (TestFlight). Bis dahin können Sie die Web-Oberfläche im Browser nutzen.</p>'
            . '<p><strong>2.</strong> App mit dieser Firma verbinden (ohne Adresse tippen):</p>'
            . '<p><a href="' . htmlspecialchars($connectUrl, ENT_QUOTES, 'UTF-8') . '">App mit CRM verbinden</a></p>'
            . '<p>Anmeldung danach mit Kennung und Stempeluhr-PIN.</p>';

        $text = "Hallo {$name},\n\n"
            . "Mitarbeiter-App einrichten für {$crmName}:\n\n"
            . "Android Mitarbeiter-App: {$apkMa}\n"
            . "Android Termin-App: {$apkKal}\n"
            . "iOS: folgt.\n\n"
            . "Mit CRM verbinden: {$connectUrl}\n"
            . "Dann Kennung + Stempeluhr-PIN.\n";

        MailService::send(new MailMessage(
            subject: 'Mitarbeiter-App – ' . $crmName,
            htmlBody: $html,
            to: [$email],
            textBody: $text,
            contactId: $contactId,
        ));
        StaffAccessEventRepository::log(
            $contactId,
            StaffAccessEventRepository::KIND_APP,
            StaffAccessEventRepository::CHANNEL_LINK,
            (int) ($actor->id ?? 0),
            'App-Einladung an ' . $email
        );

        return 'App-Einladung gesendet an ' . $email . '.';
    }

    /**
     * Nach Neuanlage: optionale Einladungen aus POST-Flags.
     *
     * @return list<string>
     */
    public static function processCreateInvites(User $actor, int $contactId, array $post): array
    {
        $messages = [];
        $contact = ContactRepository::findById($contactId);
        if ($contact === null || !CrmRole::hasEmployeeProfile($contact->contactRole)) {
            return $messages;
        }
        try {
            if (!empty($post['invite_crm_link'])) {
                $messages[] = self::sendCrmInvite($actor, $contactId);
            }
            if (!empty($post['invite_pin_link'])) {
                $messages[] = self::sendPinInvite($actor, $contactId);
            }
            if (!empty($post['invite_mailbox_link'])) {
                $messages[] = self::sendMailboxInvite($actor, $contactId);
            }
            if (!empty($post['invite_app'])) {
                $messages[] = self::sendAppInvite($actor, $contactId);
            }
        } catch (Throwable $e) {
            $messages[] = 'Einladung: ' . $e->getMessage();
        }

        return $messages;
    }

    /** @param array<string, mixed> $mailbox */
    public static function applyMailboxPassword(array $mailbox, string $password): void
    {
        $kasLogin = trim((string) ($mailbox['kas_mail_login'] ?? ''));
        if ($kasLogin !== '' && !empty($mailbox['kas_provisioned']) && KasSettings::isConfigured()) {
            KasMailProvisioner::setMailboxPassword($kasLogin, $password);
        }
        $id = (int) ($mailbox['id'] ?? 0);
        if ($id < 1) {
            throw new RuntimeException('Postfach-ID fehlt.');
        }
        MailboxRepository::updateStoredPasswords($id, $password, $password);
    }

    private static function sendMailboxPasswordEmail(Contact $contact, string $email, string $token): void
    {
        if (!MailSettings::isConfigured()) {
            throw new RuntimeException('E-Mail-Versand ist nicht konfiguriert.');
        }
        $base = rtrim(App::publicBaseUrl(), '/');
        $url = $base . '/postfach-passwort?token=' . rawurlencode($token);
        $crmName = (string) App::config('crm_name', 'DG');
        $hours = (int) (self::MAILBOX_INVITE_TTL_SECONDS / 3600);
        $html = '<p>Bitte vergeben Sie selbst ein Passwort für Ihr geschäftliches Postfach ('
            . htmlspecialchars($crmName, ENT_QUOTES, 'UTF-8') . ').</p>'
            . '<p><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">Postfach-Passwort festlegen</a></p>'
            . '<p>Der Link ist ' . $hours . ' Stunden gültig. Es steht kein Passwort in dieser Mail.</p>';
        $text = "Postfach-Passwort festlegen:\n{$url}\n\nGültig {$hours} Stunden.\n";
        MailService::send(new MailMessage(
            subject: 'Postfach-Passwort einrichten – ' . $crmName,
            htmlBody: $html,
            to: [$email],
            textBody: $text,
            contactId: $contact->id,
        ));
    }

    private static function requireStaffContact(int $contactId): Contact
    {
        $contact = ContactRepository::findById($contactId);
        if ($contact === null) {
            throw new InvalidArgumentException('Kontakt nicht gefunden.');
        }
        if (!CrmRole::hasEmployeeProfile($contact->contactRole)) {
            throw new InvalidArgumentException('Nur Mitarbeiter-/Administrator-Kontakte.');
        }

        return $contact;
    }

    /** @return array<string, mixed> */
    private static function requireMailboxForContact(Contact $contact): array
    {
        $userId = MailboxMemberResolver::findUserIdForContact($contact);
        $mailbox = null;
        if ($userId !== null) {
            $mailbox = MailboxRepository::findPrivateForUser($userId);
        }
        if ($mailbox === null) {
            $mailbox = MailboxRepository::findPrivateForContact($contact->id);
        }
        if ($mailbox === null) {
            throw new InvalidArgumentException('Kein privates Postfach für diesen Kontakt. Zuerst Postfach anlegen.');
        }

        return $mailbox;
    }
}
