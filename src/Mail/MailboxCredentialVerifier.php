<?php

declare(strict_types=1);

/**
 * Prüft IMAP/SMTP-Zugangsdaten live — vor dem Speichern eines Postfachs.
 */
final class MailboxCredentialVerifier
{
    /**
     * Wirft RuntimeException mit verständlicher Meldung, wenn Anmeldung fehlschlägt.
     *
     * @param array<string, mixed> $mailbox Bereits gemergte Felder inkl. Passwort
     */
    public static function assertCanConnect(array $mailbox): void
    {
        self::assertImap($mailbox);
        self::assertSmtp($mailbox);
    }

    /**
     * @param array<string, mixed> $mailbox
     */
    private static function assertImap(array $mailbox): void
    {
        $host = trim((string) ($mailbox['imap_host'] ?? ''));
        $user = trim((string) ($mailbox['imap_username'] ?? ''));
        $pass = (string) ($mailbox['imap_password'] ?? '');
        $any = $host !== '' || $user !== '' || trim($pass) !== '';
        if (!$any) {
            return;
        }

        if (!ImapMailboxClient::isAvailable()) {
            throw new RuntimeException(
                'PHP-IMAP fehlt auf dem Server — Anmeldung kann beim Speichern nicht geprüft werden.'
            );
        }

        if (!ImapMailboxClient::hasCredentials($mailbox)) {
            throw new RuntimeException(self::formatError('Zugangsdaten unvollständig', 'imap'));
        }

        ImapMailboxClient::releaseConnections();
        if (ImapMailboxClient::probeInbox($mailbox)) {
            ImapMailboxClient::releaseConnections();

            return;
        }

        $raw = ImapMailboxClient::lastError();
        ImapMailboxClient::releaseConnections();
        throw new RuntimeException(
            self::formatError($raw !== '' ? $raw : 'authentication failed', 'imap')
        );
    }

    /**
     * @param array<string, mixed> $mailbox
     */
    private static function assertSmtp(array $mailbox): void
    {
        $host = trim((string) ($mailbox['smtp_host'] ?? ''));
        $user = trim((string) ($mailbox['smtp_username'] ?? ''));
        $pass = (string) ($mailbox['smtp_password'] ?? '');
        $any = $host !== '' || $user !== '' || trim($pass) !== '';
        if (!$any) {
            return;
        }

        if ($host === '' || $user === '' || trim($pass) === '') {
            throw new RuntimeException(self::formatError('Zugangsdaten unvollständig', 'smtp'));
        }

        $client = new SmtpClient(
            $host,
            max(1, min(65535, (int) ($mailbox['smtp_port'] ?? 587))),
            (string) ($mailbox['smtp_encryption'] ?? 'tls'),
            $user,
            $pass,
            20
        );
        $report = $client->diagnose();
        if (!empty($report['ok'])) {
            return;
        }

        $raw = trim((string) ($report['summary'] ?? ''));
        if ($raw === '' && !empty($report['steps']) && is_array($report['steps'])) {
            foreach (array_reverse($report['steps']) as $step) {
                if (is_array($step) && empty($step['ok'])) {
                    $raw = trim((string) ($step['detail'] ?? $step['label'] ?? ''));
                    if ($raw !== '') {
                        break;
                    }
                }
            }
        }

        throw new RuntimeException(
            self::formatError($raw !== '' ? $raw : 'authentication failed', 'smtp')
        );
    }

    private static function formatError(string $raw, string $context): string
    {
        $explained = MailProviderError::explain($raw, $context);
        $msg = (string) ($explained['message'] ?? 'Mailserver-Fehler');
        $hint = trim((string) ($explained['hint'] ?? ''));

        return $hint !== '' ? $msg . ' ' . $hint : $msg;
    }
}
