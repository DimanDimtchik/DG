<?php
declare(strict_types=1);

final class MailService
{
    public static function send(MailMessage $message, ?User $actor = null, ?int $mailboxId = null): int
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank ist nicht konfiguriert.');
        }
        MigrationRunner::runPending();

        $mailbox = null;
        if ($mailboxId !== null && $mailboxId > 0) {
            $mailbox = MailboxRepository::findById($mailboxId);
            if ($mailbox === null || empty($mailbox['is_active'])) {
                throw new InvalidArgumentException('Postfach nicht gefunden.');
            }
            if ($actor !== null && !MailboxRepository::userCanAccess($actor, $mailboxId, 'send')) {
                throw new RuntimeException('Keine Berechtigung zum Senden über dieses Postfach.');
            }
        } elseif (!MailSettings::isConfigured()) {
            throw new RuntimeException('E-Mail-Versand ist nicht konfiguriert (Einstellungen → E-Mail / SMTP).');
        }

        $from = self::resolveFrom($message, $mailbox);
        $smtp = self::resolveSmtp($mailbox);

        $fromEmail = $message->fromEmail ?? $from['email'];
        $fromName = $message->fromName ?? $from['name'];
        $replyTo = $message->replyTo ?? ($from['reply_to'] !== '' ? $from['reply_to'] : null);

        $recipients = $message->allRecipients();
        if ($recipients === []) {
            throw new InvalidArgumentException('Keine gültigen Empfänger.');
        }

        $htmlBody = self::prepareHtmlBody($message->htmlBody, $mailbox, $actor);
        $textBody = $message->textBody;
        if ($mailbox !== null) {
            $footer = EmailLayoutSettings::resolvedFooter();
            $footer['signature'] = MailSignatureResolver::resolve($mailbox, $actor);
            $plainClosing = CalendarEmailLayout::closingPlainText($footer);
            $opening = trim((string) ($footer['opening_greeting'] ?? ''));
            $baseText = $textBody !== '' ? $textBody : strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $message->htmlBody));
            if ($opening !== '') {
                $baseText = $opening . "\n\n" . ltrim($baseText);
            }
            if ($plainClosing !== '') {
                $textBody = rtrim($baseText) . "\n\n" . $plainClosing;
            } else {
                $textBody = $baseText;
            }
        }
        $preview = MailMessage::bodyPreview($htmlBody);
        $logId = MailLogRepository::createQueued(
            $fromEmail,
            $fromName,
            $message->to,
            $message->cc,
            $message->bcc,
            $message->subject,
            $preview,
            $message->contactId,
            $actor?->id,
            $mailboxId,
        );

        $domain = substr(strrchr($fromEmail, '@') ?: '@localhost', 1) ?: 'localhost';
        $messageId = $logId . '.' . bin2hex(random_bytes(8)) . '@' . $domain;

        try {
            $outbound = new MailMessage(
                subject: $message->subject,
                htmlBody: $htmlBody,
                to: $message->to,
                cc: $message->cc,
                bcc: $message->bcc,
                textBody: $textBody,
                fromEmail: $fromEmail,
                fromName: $fromName,
                replyTo: $replyTo,
                contactId: $message->contactId,
                inReplyTo: $message->inReplyTo,
                references: $message->references,
            );
            $mime = $outbound->toMime($fromEmail, $fromName, $replyTo, $messageId);
            $archive = MailArchiveStorage::save($logId, $mime);
            MailLogRepository::markArchived($logId, $archive['path'], $archive['size'], $messageId);

            $client = new SmtpClient(
                $smtp['host'],
                $smtp['port'],
                $smtp['encryption'],
                $smtp['username'],
                $smtp['password'],
            );
            $client->sendMail($fromEmail, $recipients, $mime);
            MailLogRepository::markSent($logId);
        } catch (Throwable $e) {
            MailLogRepository::markFailed($logId, $e->getMessage());
            throw $e;
        }

        return $logId;
    }

    /**
     * @param array<string, mixed>|null $mailbox
     * @return array{email: string, name: string, reply_to: string}
     */
    private static function resolveFrom(MailMessage $message, ?array $mailbox): array
    {
        if ($mailbox !== null) {
            return [
                'email' => (string) $mailbox['email_address'],
                'name' => MailboxRepository::displayFromName($mailbox),
                'reply_to' => (string) $mailbox['email_address'],
            ];
        }

        $sender = MailSettings::sender();

        return [
            'email' => $sender['email'],
            'name' => $sender['name'],
            'reply_to' => $sender['reply_to'],
        ];
    }

    /**
     * @param array<string, mixed>|null $mailbox
     * @return array{host: string, port: int, encryption: string, username: string, password: string}
     */
    private static function resolveSmtp(?array $mailbox): array
    {
        if ($mailbox !== null) {
            $cfg = MailboxRepository::smtpConfig($mailbox);
            if ($cfg !== null) {
                return $cfg;
            }
        }

        if (!MailSettings::isConfigured()) {
            throw new RuntimeException(
                $mailbox !== null
                    ? 'Für dieses Postfach sind keine SMTP-Daten hinterlegt (Einstellungen → Postfächer).'
                    : 'SMTP ist nicht konfiguriert.'
            );
        }

        $global = MailSettings::config();

        return [
            'host' => (string) $global['smtp_host'],
            'port' => (int) $global['smtp_port'],
            'encryption' => (string) $global['smtp_encryption'],
            'username' => (string) $global['smtp_username'],
            'password' => (string) $global['smtp_password'],
        ];
    }

    private static function prepareHtmlBody(string $html, ?array $mailbox = null, ?User $actor = null): string
    {
        if (stripos($html, '<html') !== false) {
            return $html;
        }

        if ($mailbox !== null) {
            $footer = EmailLayoutSettings::resolvedFooter();
            $footer['signature'] = MailSignatureResolver::resolve($mailbox, $actor);

            return CalendarEmailLayout::renderPostMessage($html, $footer);
        }

        return AppearanceSettings::wrapEmailHtml($html);
    }

    public static function sendTest(string $to, ?User $sender = null): int
    {
        $to = trim($to);
        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Gültige Test-Empfänger-Adresse erforderlich.');
        }

        if (!CompanySettings::isConfiguredForMail()) {
            throw new RuntimeException('Firmendaten unvollständig — bitte Firmenname und E-Mail unter Einstellungen → Firmendaten eintragen.');
        }

        $companyName = CompanySettings::displayName();
        $name = $sender?->displayName ?? $companyName;
        $inner = '<p>Dies ist eine <strong>Test-E-Mail</strong> aus dem DG CRM.</p>'
            . '<p>SMTP-Versand und Archivierung funktionieren.</p>'
            . '<p style="color:#666;font-size:12px">Gesendet von ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ' (' . htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') . ')</p>';

        return self::send(new MailMessage(
            subject: $companyName !== '' ? $companyName . ' – SMTP-Test' : 'SMTP-Test',
            htmlBody: $inner,
            to: [$to],
        ), $sender);
    }
}
