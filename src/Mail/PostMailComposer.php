<?php
declare(strict_types=1);

/** Versand aus dem Post-Modul (Antwort / neue Nachricht). */
final class PostMailComposer
{
    /**
     * Methode send from post.
     * @param User $actor
     * @param array $input
     * @return int
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public static function sendFromPost(User $actor, array $input): int
    {
        $mailboxId = (int) ($input['mailbox_id'] ?? 0);
        if ($mailboxId <= 0) {
            throw new InvalidArgumentException('Bitte ein Absender-Postfach wählen.');
        }

        $toRaw = trim((string) ($input['to'] ?? ''));
        $to = self::parseRecipients($toRaw);
        if ($to === []) {
            throw new InvalidArgumentException('Mindestens ein Empfänger erforderlich.');
        }

        $subject = trim((string) ($input['subject'] ?? ''));
        if ($subject === '') {
            throw new InvalidArgumentException('Betreff ist erforderlich.');
        }

        $body = trim((string) ($input['body'] ?? ''));
        if ($body === '') {
            throw new InvalidArgumentException('Nachrichtentext ist erforderlich.');
        }

        $replyToId = (int) ($input['reply_to_id'] ?? 0);
        $inReplyTo = null;
        $references = null;
        $contactId = null;

        if ($replyToId > 0) {
            $original = MailLogRepository::findById($replyToId);
            if ($original === null || ($original['direction'] ?? '') !== 'in') {
                throw new InvalidArgumentException('Antwortbezug nicht gefunden.');
            }
            if (!MailboxRepository::userCanAccess($actor, (int) ($original['mailbox_id'] ?? 0), 'read')) {
                throw new RuntimeException('Keine Berechtigung für die Originalnachricht.');
            }
            $msgId = trim((string) ($original['message_id'] ?? ''));
            if ($msgId !== '') {
                $wrapped = str_starts_with($msgId, '<') ? $msgId : '<' . $msgId . '>';
                $inReplyTo = $wrapped;
                $references = $wrapped;
            }
            $contactId = isset($original['contact_id']) ? (int) $original['contact_id'] : null;
            if ($contactId !== null && $contactId <= 0) {
                $contactId = null;
            }
        }

        $html = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));

        return MailService::send(
            new MailMessage(
                subject: $subject,
                htmlBody: $html,
                to: $to,
                textBody: $body,
                contactId: $contactId,
                inReplyTo: $inReplyTo,
                references: $references,
            ),
            $actor,
            $mailboxId,
        );
    }

    /**
     * Führt aus: parse recipients.
     * @param string $raw
     * @return array<string, mixed>
     */
    private static function parseRecipients(string $raw): array
    {
        $parts = preg_split('/[,;]/', $raw) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '' && filter_var($part, FILTER_VALIDATE_EMAIL)) {
                $out[] = $part;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Methode reply subject.
     * @param string $subject
     * @return string
     */
    public static function replySubject(string $subject): string
    {
        $subject = trim($subject);
        if ($subject === '') {
            return 'Re:';
        }
        if (preg_match('/^re:\s/i', $subject)) {
            return $subject;
        }

        return 'Re: ' . $subject;
    }
}
