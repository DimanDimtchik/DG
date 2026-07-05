<?php
declare(strict_types=1);

/** Legt private Postfächer bei All-Inkl (KAS) an und verknüpft Kontakt-E-Mail. */
final class MailboxProvisioner
{
    /**
     * @return array{mailbox_id: int, email: string, kas_provisioned: bool, message: string, skipped?: bool}
     */
    public static function createPrivateForContact(Contact $contact): array
    {
        if (!CrmRole::hasEmployeeProfile($contact->contactRole)) {
            throw new InvalidArgumentException('Postfächer werden nur für Mitarbeiter und Administratoren angelegt.');
        }
        if (!MailAddressSettings::config()['enabled']) {
            throw new RuntimeException('Automatische Mail-Adressen sind in den Einstellungen deaktiviert.');
        }

        $ownerUserId = MailboxMemberResolver::findUserIdForContact($contact);
        if ($ownerUserId !== null) {
            $existing = MailboxRepository::findPrivateForUser($ownerUserId);
            if ($existing !== null) {
                return [
                    'mailbox_id' => (int) $existing['id'],
                    'email' => (string) $existing['email_address'],
                    'kas_provisioned' => !empty($existing['kas_provisioned']),
                    'message' => 'Privates Postfach existiert bereits.',
                ];
            }
        }

        $allocation = MailAddressBuilder::evaluateAutoCreate($contact);
        if (!$allocation['ok']) {
            return [
                'mailbox_id' => 0,
                'email' => (string) $allocation['email'],
                'kas_provisioned' => false,
                'skipped' => true,
                'message' => 'Neue Adresse nicht angelegt: ' . $allocation['reason'],
            ];
        }

        $email = (string) $allocation['email'];
        [$local, $domain] = explode('@', $email, 2);

        if (!KasSettings::isConfigured()) {
            return [
                'mailbox_id' => 0,
                'email' => $email,
                'kas_provisioned' => false,
                'skipped' => true,
                'message' => 'Neue Adresse nicht angelegt: KAS-API nicht konfiguriert (config/kas.local.php auf dem Server). Ohne KAS wird kein Postfach bei All-Inkl erzeugt.',
            ];
        }

        $kasProvisioned = false;
        $imapHost = 'imap.' . $domain;
        $imapUser = $local . '##' . $domain;
        $imapPassword = '';
        $kasLogin = null;
        $kas = [];

        $mailPassword = KasMailProvisioner::generatePassword();
        $kas = KasMailProvisioner::createMailbox($local, $domain, $mailPassword);
        $kasProvisioned = true;
        $imapHost = (string) ($kas['imap_host'] ?? $imapHost);
        $imapUser = (string) ($kas['imap_username'] ?? $imapUser);
        $imapPassword = (string) ($kas['imap_password'] ?? $mailPassword);
        $kasLogin = (string) ($kas['mail_login'] ?? $imapUser);

        $displayName = trim($contact->displayName) !== ''
            ? trim($contact->displayName)
            : trim($contact->firstName . ' ' . $contact->lastName);

        $preset = $kasProvisioned ? 'kasserver' : 'manual';

        $mailboxId = MailboxRepository::save([
            'type' => 'private',
            'name' => $displayName !== '' ? $displayName : $email,
            'email_address' => $email,
            'owner_user_id' => $ownerUserId,
            'contact_id' => $contact->id,
            'kas_mail_login' => $kasLogin,
            'kas_provisioned' => $kasProvisioned,
            'provider_preset' => $preset,
            'imap_host' => $imapHost,
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => $imapUser,
            'imap_password' => $imapPassword,
            'smtp_host' => $kasProvisioned ? (string) ($kas['smtp_host'] ?? 'smtp.' . $domain) : '',
            'smtp_port' => $kasProvisioned ? (int) ($kas['smtp_port'] ?? 587) : 587,
            'smtp_encryption' => $kasProvisioned ? (string) ($kas['smtp_encryption'] ?? 'tls') : 'tls',
            'smtp_username' => $kasProvisioned ? (string) ($kas['smtp_username'] ?? $imapUser) : '',
            'smtp_password' => $kasProvisioned ? (string) ($kas['smtp_password'] ?? $imapPassword) : '',
            'from_name' => $displayName,
            'is_active' => true,
        ]);

        return [
            'mailbox_id' => $mailboxId,
            'email' => $email,
            'kas_provisioned' => $kasProvisioned,
            'message' => 'Postfach angelegt (KAS) — Aktivierung kann einige Minuten dauern.',
        ];
    }

    /**
     * Legt ein im CRM erfasstes Postfach nachträglich bei All-Inkl per KAS-API an.
     *
     * @return array{email: string, message: string}
     */
    public static function provisionKasForMailbox(int $mailboxId): array
    {
        if (!KasSettings::isConfigured()) {
            throw new RuntimeException('KAS-API ist nicht konfiguriert (config/kas.local.php).');
        }

        $mailbox = MailboxRepository::findById($mailboxId);
        if ($mailbox === null) {
            throw new InvalidArgumentException('Postfach nicht gefunden.');
        }
        if (!empty($mailbox['kas_provisioned'])) {
            throw new RuntimeException('Postfach ist bereits bei All-Inkl angelegt.');
        }

        $email = strtolower(trim((string) ($mailbox['email_address'] ?? '')));
        if ($email === '' || !str_contains($email, '@')) {
            throw new InvalidArgumentException('Ungültige Postfach-Adresse.');
        }

        [$local, $domain] = explode('@', $email, 2);
        $kasLogin = null;
        $imapHost = 'imap.' . $domain;
        $imapUser = $local . '##' . $domain;
        $imapPassword = '';

        try {
            $mailPassword = KasMailProvisioner::generatePassword();
            $kas = KasMailProvisioner::createMailbox($local, $domain, $mailPassword);
            $imapHost = (string) ($kas['imap_host'] ?? $imapHost);
            $imapUser = (string) ($kas['imap_username'] ?? $imapUser);
            $imapPassword = (string) ($kas['imap_password'] ?? $mailPassword);
            $kasLogin = (string) ($kas['mail_login'] ?? $imapUser);
        } catch (RuntimeException $e) {
            if (!str_contains(strtolower($e->getMessage()), 'email_already_exists')) {
                throw $e;
            }
            $existingKas = KasMailProvisioner::findMailAccountByEmail($email);
            if ($existingKas === null) {
                sleep(3);
                $existingKas = KasMailProvisioner::findMailAccountByEmail($email);
            }
            if ($existingKas === null) {
                throw $e;
            }
            $kasLogin = (string) ($existingKas['mail_login'] ?? '');
            if ($kasLogin === '') {
                throw $e;
            }
            $imapPassword = trim((string) ($existingKas['mail_password'] ?? ''));
            if ($imapPassword === '') {
                $imapPassword = KasMailProvisioner::resetMailboxPassword($kasLogin);
            }
            $kasHost = KasMailProvisioner::kasServerHost();
            $imapHost = $kasHost;
        }

        $kasHost = KasMailProvisioner::kasServerHost();
        if (!str_contains($imapHost, 'kasserver')) {
            $imapHost = $kasHost;
        }

        MailboxRepository::save([
            'type' => (string) ($mailbox['type'] ?? 'shared'),
            'name' => (string) ($mailbox['name'] ?? $email),
            'email_address' => $email,
            'owner_user_id' => (int) ($mailbox['owner_user_id'] ?? 0),
            'contact_id' => (int) ($mailbox['contact_id'] ?? 0),
            'kas_mail_login' => $kasLogin,
            'kas_provisioned' => true,
            'provider_preset' => 'kasserver',
            'imap_host' => $imapHost,
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => $imapUser,
            'imap_password' => $imapPassword,
            'smtp_host' => $kasHost,
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => $imapUser,
            'smtp_password' => $imapPassword,
            'from_name' => (string) ($mailbox['from_name'] ?? ''),
            'inbound_webhook_token' => (string) ($mailbox['inbound_webhook_token'] ?? ''),
            'is_active' => !empty($mailbox['is_active']),
        ], $mailboxId);

        return [
            'email' => $email,
            'message' => 'Postfach bei All-Inkl angelegt — Aktivierung kann einige Minuten dauern.',
        ];
    }

    /**
     * Setzt das Postfach-Passwort bei All-Inkl per KAS zurück und speichert es im CRM.
     *
     * @return array{email: string, message: string}
     */
    public static function repairKasImapPassword(int $mailboxId): array
    {
        if (!KasSettings::isConfigured()) {
            throw new RuntimeException('KAS-API ist nicht konfiguriert (config/kas.local.php).');
        }

        $mailbox = MailboxRepository::findById($mailboxId);
        if ($mailbox === null) {
            throw new InvalidArgumentException('Postfach nicht gefunden.');
        }
        if (empty($mailbox['kas_provisioned'])) {
            throw new RuntimeException('Nur für bei All-Inkl angelegte Postfächer verfügbar.');
        }

        $email = strtolower(trim((string) ($mailbox['email_address'] ?? '')));
        [$local, $domain] = explode('@', $email, 2);
        $kasLogin = trim((string) ($mailbox['kas_mail_login'] ?? ''));
        if ($kasLogin === '') {
            $kasRow = KasMailProvisioner::findMailAccountByEmail($email);
            $kasLogin = trim((string) ($kasRow['mail_login'] ?? ''));
        }
        if ($kasLogin === '') {
            throw new RuntimeException('KAS mail_login für dieses Postfach nicht gefunden.');
        }

        $newPassword = KasMailProvisioner::resetMailboxPassword($kasLogin);
        $imapUser = self::discoverImapUsername($mailbox, $newPassword, $kasLogin);
        $kasHost = KasMailProvisioner::kasServerHost();

        MailboxRepository::save([
            'type' => (string) ($mailbox['type'] ?? 'shared'),
            'name' => (string) ($mailbox['name'] ?? $email),
            'email_address' => $email,
            'owner_user_id' => (int) ($mailbox['owner_user_id'] ?? 0),
            'contact_id' => (int) ($mailbox['contact_id'] ?? 0),
            'kas_mail_login' => $kasLogin,
            'kas_provisioned' => true,
            'provider_preset' => 'kasserver',
            'imap_host' => $kasHost,
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => $imapUser,
            'imap_password' => $newPassword,
            'smtp_host' => $kasHost,
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => $imapUser,
            'smtp_password' => $newPassword,
            'from_name' => (string) ($mailbox['from_name'] ?? ''),
            'inbound_webhook_token' => (string) ($mailbox['inbound_webhook_token'] ?? ''),
            'is_active' => !empty($mailbox['is_active']),
        ], $mailboxId);

        return [
            'email' => $email,
            'message' => 'IMAP-Passwort bei All-Inkl zurückgesetzt und im CRM gespeichert.',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param list<int> $memberUserIds
     */
    public static function createSharedFromForm(array $input, array $memberUserIds): int
    {
        $input = self::normalizePostInput($input);
        $email = (string) $input['email_address'];
        $provisionKas = !empty($input['provision_kas']);

        if ($email === '') {
            throw new InvalidArgumentException('E-Mail-Adresse für das allgemeine Postfach ist erforderlich.');
        }

        [$local, $domain] = explode('@', $email, 2);
        $kasProvisioned = false;
        $imapHost = trim((string) ($input['imap_host'] ?? ''));
        $imapUser = trim((string) ($input['imap_username'] ?? ''));
        $imapPassword = trim((string) ($input['imap_password'] ?? ''));
        $kasLogin = null;

        if ($provisionKas && KasSettings::isConfigured()) {
            $mailPassword = $imapPassword !== '' ? $imapPassword : KasMailProvisioner::generatePassword();
            $kas = KasMailProvisioner::createMailbox($local, $domain, $mailPassword);
            $kasProvisioned = true;
            $imapHost = (string) ($kas['imap_host'] ?? $imapHost);
            $imapUser = (string) ($kas['imap_username'] ?? $imapUser);
            $imapPassword = (string) ($kas['imap_password'] ?? $mailPassword);
            $kasLogin = (string) ($kas['mail_login'] ?? $imapUser);
            $input['smtp_host'] = (string) ($kas['smtp_host'] ?? '');
            $input['smtp_port'] = (int) ($kas['smtp_port'] ?? 587);
            $input['smtp_encryption'] = (string) ($kas['smtp_encryption'] ?? 'tls');
            $input['smtp_username'] = (string) ($kas['smtp_username'] ?? $imapUser);
            $input['smtp_password'] = (string) ($kas['smtp_password'] ?? $mailPassword);
            $input['provider_preset'] = 'kasserver';
        }

        $input['type'] = 'shared';
        $input['kas_mail_login'] = $kasLogin;
        $input['kas_provisioned'] = $kasProvisioned;
        if ($imapHost !== '') {
            $input['imap_host'] = $imapHost;
        }
        if ($imapUser !== '') {
            $input['imap_username'] = $imapUser;
        }
        if ($imapPassword !== '') {
            $input['imap_password'] = $imapPassword;
        }
        $input['is_active'] = true;

        return MailboxRepository::save($input, null, $memberUserIds);
    }

    /** @return array<string, mixed> */
    public static function normalizePostInput(array $post, ?array $existing = null): array
    {
        return [
            'name' => trim((string) ($post['mailbox_name'] ?? $existing['name'] ?? '')),
            'email_address' => strtolower(trim((string) ($post['mailbox_email'] ?? $existing['email_address'] ?? ''))),
            'provider_preset' => (string) ($post['provider_preset'] ?? $existing['provider_preset'] ?? 'manual'),
            'provision_kas' => $post['provision_kas'] ?? '',
            'imap_host' => (string) ($post['imap_host'] ?? ''),
            'imap_port' => (int) ($post['imap_port'] ?? 993),
            'imap_encryption' => (string) ($post['imap_encryption'] ?? 'ssl'),
            'imap_username' => (string) ($post['imap_username'] ?? ''),
            'imap_password' => (string) ($post['imap_password'] ?? ''),
            'smtp_host' => (string) ($post['smtp_host'] ?? ''),
            'smtp_port' => (int) ($post['smtp_port'] ?? 587),
            'smtp_encryption' => (string) ($post['smtp_encryption'] ?? 'tls'),
            'smtp_username' => (string) ($post['smtp_username'] ?? ''),
            'smtp_password' => (string) ($post['smtp_password'] ?? ''),
            'from_name' => (string) ($post['from_name'] ?? ''),
            'type' => ($existing['type'] ?? '') !== '' ? $existing['type'] : 'shared',
            'kas_mail_login' => $existing['kas_mail_login'] ?? null,
            'kas_provisioned' => $existing['kas_provisioned'] ?? false,
            'inbound_webhook_token' => $existing['inbound_webhook_token'] ?? '',
            'is_active' => true,
        ];
    }

    /**
     * @param array<string, mixed> $mailbox
     */
    private static function discoverImapUsername(array $mailbox, string $password, string $kasLogin): string
    {
        $email = strtolower(trim((string) ($mailbox['email_address'] ?? '')));
        [$local, $domain] = explode('@', $email, 2);
        $default = $local . '##' . $domain;
        $candidates = array_values(array_unique(array_filter([
            trim((string) ($mailbox['imap_username'] ?? '')),
            $default,
            $kasLogin,
            $kasLogin !== '' ? $kasLogin . '##' . $domain : '',
            $email,
        ])));

        $base = $mailbox;
        $base['imap_password'] = $password;
        $base['imap_host'] = KasMailProvisioner::kasServerHost();

        foreach ($candidates as $candidate) {
            $test = $base;
            $test['imap_username'] = $candidate;
            ImapMailboxClient::releaseConnections();
            if (ImapMailboxClient::probeInbox($test)) {
                return $candidate;
            }
        }

        return $default;
    }
}
