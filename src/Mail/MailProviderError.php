<?php

declare(strict_types=1);

/**
 * Technische IMAP/SMTP-Fehler in kurze, verständliche DE-Texte übersetzen.
 *
 * @phpstan-type Explained array{message: string, hint: ?string, code: string}
 */
final class MailProviderError
{
    /**
     * @return array{message: string, hint: ?string, code: string}
     */
    public static function explain(string $raw, string $context = 'imap'): array
    {
        $raw = trim($raw);
        $lower = strtolower($raw);

        if ($raw === '' || str_contains($lower, 'unvollständig') || str_contains($lower, 'incomplete')) {
            return [
                'message' => 'Zugangsdaten unvollständig (Host, Benutzer oder Passwort fehlen).',
                'hint' => 'Unter Einstellungen → Postfächer IMAP/SMTP prüfen und speichern.',
                'code' => 'creds',
            ];
        }

        if (
            str_contains($lower, 'auth')
            || str_contains($lower, 'authenticationfailed')
            || str_contains($lower, 'invalid credentials')
            || str_contains($lower, 'login failed')
            || str_contains($lower, 'anmeldung')
            || str_contains($lower, 'password')
        ) {
            return [
                'message' => 'Anmeldung am Mailserver fehlgeschlagen (Benutzer oder Passwort).',
                'hint' => 'Passwort und Benutzername prüfen. Bei All-Inkl ggf. „IMAP-Passwort zurücksetzen“.',
                'code' => 'auth',
            ];
        }

        if (
            str_contains($lower, 'certificate')
            || str_contains($lower, 'ssl')
            || str_contains($lower, 'tls')
            || str_contains($lower, 'handshake')
            || str_contains($lower, 'secure')
        ) {
            return [
                'message' => 'Verschlüsselte Verbindung (SSL/TLS) fehlgeschlagen.',
                'hint' => 'Port und Verschlüsselung (SSL/TLS) zum Anbieter abstimmen — oft IMAP 993/SSL, SMTP 587/TLS.',
                'code' => 'ssl',
            ];
        }

        if (
            str_contains($lower, 'timed out')
            || str_contains($lower, 'timeout')
            || str_contains($lower, 'zeitüberschreitung')
        ) {
            return [
                'message' => 'Zeitüberschreitung beim Mailserver.',
                'hint' => 'Später erneut versuchen; Host/Port prüfen, Firewall/VPN beachten.',
                'code' => 'timeout',
            ];
        }

        if (
            str_contains($lower, 'no such mailbox')
            || str_contains($lower, 'mailbox does not exist')
            || str_contains($lower, 'unknown mailbox')
            || str_contains($lower, 'ordner')
            || str_contains($lower, 'folder')
        ) {
            return [
                'message' => 'Ordner auf dem Mailserver nicht gefunden.',
                'hint' => 'Ordnerliste aktualisieren oder einen anderen Ordner wählen (z. B. INBOX).',
                'code' => 'folder',
            ];
        }

        if (
            str_contains($lower, 'could not connect')
            || str_contains($lower, 'connection refused')
            || str_contains($lower, 'getaddrinfo')
            || str_contains($lower, 'name or service not known')
            || str_contains($lower, 'unreachable')
        ) {
            return [
                'message' => 'Mailserver nicht erreichbar.',
                'hint' => 'Hostnamen und Port prüfen (Tippfehler, Provider-Ausfall).',
                'code' => 'connect',
            ];
        }

        $prefix = $context === 'smtp' ? 'SMTP' : 'IMAP';
        $message = $raw !== ''
            ? $prefix . ': ' . mb_substr($raw, 0, 240)
            : $prefix . '-Fehler ohne Details.';

        return [
            'message' => $message,
            'hint' => 'Details unter Einstellungen → Postfächer prüfen oder erneut abrufen.',
            'code' => 'other',
        ];
    }

    public static function userMessage(string $raw, string $context = 'imap'): string
    {
        return self::explain($raw, $context)['message'];
    }
}
