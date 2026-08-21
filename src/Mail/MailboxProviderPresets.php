<?php
declare(strict_types=1);

/** IMAP/SMTP-Vorlagen für gängige Mail-Anbieter (DE + international). */
final class MailboxProviderPresets
{
    /**
     * Methode definitions.
     * @return array<string, mixed>
     */
    private static function definitions(): array
    {
        $appPassword = 'App-/Anwendungspasswort falls 2FA aktiv';

        return [
            'manual' => self::def('Manuell / anderer Anbieter', 'general', '', '', autoEmailUsername: false, note: 'Serverdaten manuell eintragen.'),
            'kasserver' => self::def(
                'Kasserver / All-Inkl',
                'hosting_de',
                'imap.{domain}',
                'smtp.{domain}',
                imapUserHint: 'login##domain.de (Kasserver)',
                smtpUserHint: 'login##domain.de (Kasserver)',
                autoEmailUsername: false,
            ),
            'ionos' => self::def(
                'IONOS / 1&1',
                'hosting_de',
                'imap.ionos.de',
                'smtp.ionos.de',
                smtpPort: 465,
                smtpEncryption: 'ssl',
                smtpUserHint: 'E-Mail-Passwort (ggf. separates Postfach-Passwort bei IONOS)',
                note: 'Bei IONOS oft separates E-Mail-Passwort im Kundenkonto setzen.',
            ),
            'strato' => self::def('STRATO', 'hosting_de', 'imap.strato.de', 'smtp.strato.de', smtpPort: 465, smtpEncryption: 'ssl'),
            'gmx' => self::def(
                'GMX (gmx.de, gmx.net, …)',
                'freemail_de',
                'imap.gmx.net',
                'mail.gmx.net',
                note: 'IMAP/POP3 in den GMX-Kontoeinstellungen freischalten.',
            ),
            'webde' => self::def(
                'WEB.DE',
                'freemail_de',
                'imap.web.de',
                'smtp.web.de',
                note: 'IMAP/POP3 im WEB.DE-Postfach unter Einstellungen aktivieren.',
            ),
            'mailcom' => self::def(
                'mail.com',
                'freemail_de',
                'imap.mail.com',
                'smtp.mail.com',
                smtpPort: 465,
                smtpEncryption: 'ssl',
                note: 'IMAP in den mail.com-Kontoeinstellungen freischalten.',
            ),
            'mailde' => self::def('Mail.de', 'freemail_de', 'imap.mail.de', 'smtp.mail.de', smtpPort: 465, smtpEncryption: 'ssl'),
            'freenet' => self::def(
                'freenetMail (freenet.de)',
                'freemail_de',
                'mx.freenet.de',
                'mx.freenet.de',
                note: 'POP3/IMAP/SMTP im freenet-Postfach aktivieren; SMTP ggf. extra freischalten.',
            ),
            'tonline' => self::def(
                'Telekom / T-Online / Magenta',
                'freemail_de',
                'secureimap.t-online.de',
                'securesmtp.t-online.de',
                smtpPort: 465,
                smtpEncryption: 'ssl',
                smtpUserHint: $appPassword,
                note: 'Oft App-Passwort statt Login-Passwort (Telekom-Hilfe).',
            ),
            'arcor_vodafone' => self::def(
                'Vodafone / Arcor / vodafonemail.de',
                'freemail_de',
                'imap.vodafonemail.de',
                'smtp.vodafonemail.de',
                smtpPort: 465,
                smtpEncryption: 'ssl',
                smtpUserHint: $appPassword,
            ),
            'posteo' => self::def(
                'Posteo',
                'privacy_de',
                'posteo.de',
                'posteo.de',
                smtpPort: 465,
                smtpEncryption: 'ssl',
                note: 'Zusatzschutz im Posteo-Konto ggf. deaktivieren für externe Clients.',
            ),
            'mailboxorg' => self::def(
                'mailbox.org',
                'privacy_de',
                'imap.mailbox.org',
                'smtp.mailbox.org',
                smtpPort: 465,
                smtpEncryption: 'ssl',
                smtpUserHint: $appPassword,
            ),
            'gmail' => self::def(
                'Google Gmail / Workspace',
                'international',
                'imap.gmail.com',
                'smtp.gmail.com',
                smtpUserHint: 'App-Passwort (Google-Konto → Sicherheit)',
            ),
            'microsoft365' => self::def('Microsoft 365 / Outlook', 'international', 'outlook.office365.com', 'smtp.office365.com'),
            'yahoo' => self::def(
                'Yahoo Mail',
                'international',
                'imap.mail.yahoo.com',
                'smtp.mail.yahoo.com',
                smtpPort: 465,
                smtpEncryption: 'ssl',
                smtpUserHint: $appPassword,
            ),
            'icloud' => self::def(
                'Apple iCloud',
                'international',
                'imap.mail.me.com',
                'smtp.mail.me.com',
                smtpUserHint: $appPassword,
            ),
        ];
    }

    /**
     * Methode labels.
     * @return array<string, mixed>
     */
    public static function labels(): array
    {
        $out = [];
        foreach (self::definitions() as $id => $def) {
            $out[$id] = $def['label'];
        }

        return $out;
    }

    /**
     * Methode group labels.
     * @return array<string, mixed>
     */
    public static function groupLabels(): array
    {
        return [
            'general' => 'Allgemein',
            'hosting_de' => 'Hosting (Deutschland)',
            'freemail_de' => 'Freemail (Deutschland)',
            'privacy_de' => 'Privacy-Mail (Deutschland)',
            'international' => 'International',
        ];
    }

    /**
     * Methode grouped labels.
     * @return array<string, mixed>
     */
    public static function groupedLabels(): array
    {
        $groups = [];
        foreach (self::definitions() as $id => $def) {
            $groups[$def['group']][$id] = $def['label'];
        }

        return $groups;
    }

    /**
     * Prüft, ob der Wert gültig ist.
     * @param string $preset
     * @return bool
     */
    public static function isValid(string $preset): bool
    {
        return isset(self::definitions()[$preset]);
    }

    /**
     * Methode preset note.
     * @param string $preset
     * @return string
     */
    public static function presetNote(string $preset): string
    {
        if (!self::isValid($preset)) {
            return '';
        }

        return self::definitions()[$preset]['note'];
    }

    /**
     * Methode connection defaults.
     * @param string $preset
     * @param string $emailAddress
     * @return array<string, mixed>
     */
    public static function connectionDefaults(string $preset, string $emailAddress = ''): array
    {
        $emailAddress = strtolower(trim($emailAddress));
        $domain = '';
        if (str_contains($emailAddress, '@')) {
            $domain = substr($emailAddress, strpos($emailAddress, '@') + 1);
        }

        $def = self::definitions()[self::isValid($preset) ? $preset : 'manual'];
        $imapHost = self::resolveHost((string) $def['imap_host'], $domain);
        $smtpHost = self::resolveHost((string) $def['smtp_host'], $domain);

        return [
            'imap_host' => $imapHost,
            'imap_port' => (int) $def['imap_port'],
            'imap_encryption' => (string) $def['imap_encryption'],
            'smtp_host' => $smtpHost,
            'smtp_port' => (int) $def['smtp_port'],
            'smtp_encryption' => (string) $def['smtp_encryption'],
            'imap_username_hint' => (string) $def['imap_username_hint'],
            'smtp_username_hint' => (string) $def['smtp_username_hint'],
            'note' => (string) $def['note'],
        ];
    }

    /**
     * Methode merge form input.
     * @param array $input
     * @param array|null $existing
     * @return array<string, mixed>
     */
    public static function mergeFormInput(array $input, ?array $existing = null): array
    {
        $preset = trim((string) ($input['provider_preset'] ?? 'manual'));
        if (!self::isValid($preset)) {
            $preset = 'manual';
        }

        $email = strtolower(trim((string) ($input['email_address'] ?? $existing['email_address'] ?? '')));
        $defaults = self::connectionDefaults($preset, $email);
        $def = self::definitions()[$preset];
        $autoEmailUser = !empty($def['auto_email_username']);

        $imapUser = trim((string) ($input['imap_username'] ?? ''));
        $smtpUser = trim((string) ($input['smtp_username'] ?? ''));
        if ($imapUser === '' && $email !== '' && $autoEmailUser) {
            $imapUser = $email;
        }
        if ($smtpUser === '' && $email !== '' && $autoEmailUser) {
            $smtpUser = $email;
        }
        if ($imapUser === '' && $existing !== null) {
            $imapUser = (string) ($existing['imap_username'] ?? '');
        }
        if ($smtpUser === '' && $existing !== null) {
            $smtpUser = (string) ($existing['smtp_username'] ?? '');
        }

        $imapPass = trim((string) ($input['imap_password'] ?? ''));
        if ($imapPass === '' && $existing !== null) {
            $imapPass = (string) ($existing['imap_password'] ?? '');
        }
        $smtpPass = trim((string) ($input['smtp_password'] ?? ''));
        if ($smtpPass === '' && $existing !== null) {
            $smtpPass = (string) ($existing['smtp_password'] ?? '');
        }

        $imapHost = trim((string) ($input['imap_host'] ?? ''));
        $smtpHost = trim((string) ($input['smtp_host'] ?? ''));
        if ($imapHost === '' && $preset !== 'manual') {
            $imapHost = $defaults['imap_host'];
        }
        if ($smtpHost === '' && $preset !== 'manual') {
            $smtpHost = $defaults['smtp_host'];
        }
        if ($imapHost === '' && $existing !== null) {
            $imapHost = (string) ($existing['imap_host'] ?? '');
        }
        if ($smtpHost === '' && $existing !== null) {
            $smtpHost = (string) ($existing['smtp_host'] ?? '');
        }

        return array_merge($input, [
            'provider_preset' => $preset,
            'imap_host' => $imapHost,
            'imap_port' => max(1, min(65535, (int) ($input['imap_port'] ?? $defaults['imap_port']))),
            'imap_encryption' => in_array(($input['imap_encryption'] ?? $defaults['imap_encryption']), ['ssl', 'tls', ''], true)
                ? (string) ($input['imap_encryption'] ?? $defaults['imap_encryption']) : 'ssl',
            'imap_username' => $imapUser,
            'imap_password' => $imapPass,
            'smtp_host' => $smtpHost,
            'smtp_port' => max(1, min(65535, (int) ($input['smtp_port'] ?? $defaults['smtp_port']))),
            'smtp_encryption' => in_array(($input['smtp_encryption'] ?? $defaults['smtp_encryption']), ['ssl', 'tls', ''], true)
                ? (string) ($input['smtp_encryption'] ?? $defaults['smtp_encryption']) : 'tls',
            'smtp_username' => $smtpUser,
            'smtp_password' => $smtpPass,
            'from_name' => trim((string) ($input['from_name'] ?? $existing['from_name'] ?? $input['name'] ?? '')),
        ]);
    }

    /**
     * Führt aus: resolve host.
     * @param string $pattern
     * @param string $domain
     * @return string
     */
    private static function resolveHost(string $pattern, string $domain): string
    {
        if ($pattern === '') {
            return '';
        }
        if (str_contains($pattern, '{domain}')) {
            return $domain !== '' ? str_replace('{domain}', $domain, $pattern) : '';
        }

        return $pattern;
    }

    /**
     * Methode def.
     * @param string $label
     * @param string $group
     * @param string $imapHost
     * @param string $smtpHost
     * @param int $imapPort
     * @param string $imapEncryption
     * @param int $smtpPort
     * @param string $smtpEncryption
     * @param string $imapUserHint
     * @param string $smtpUserHint
     * @param bool $autoEmailUsername
     * @param string $note
     * @return array<string, mixed>
     */
    private static function def(
        string $label,
        string $group,
        string $imapHost,
        string $smtpHost,
        int $imapPort = 993,
        string $imapEncryption = 'ssl',
        int $smtpPort = 587,
        string $smtpEncryption = 'tls',
        string $imapUserHint = 'volle@adresse.de',
        string $smtpUserHint = 'volle@adresse.de',
        bool $autoEmailUsername = true,
        string $note = '',
    ): array {
        return [
            'label' => $label,
            'group' => $group,
            'imap_host' => $imapHost,
            'imap_port' => $imapPort,
            'imap_encryption' => $imapEncryption,
            'smtp_host' => $smtpHost,
            'smtp_port' => $smtpPort,
            'smtp_encryption' => $smtpEncryption,
            'imap_username_hint' => $imapUserHint,
            'smtp_username_hint' => $smtpUserHint,
            'auto_email_username' => $autoEmailUsername,
            'note' => $note,
        ];
    }
}
