<?php

declare(strict_types=1);

/** Versand aus dem Post-Modul (neu / Antwort / Weiterleiten). */
final class PostMailComposer
{
    /**
     * @param array<string, mixed> $input
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
        $forwardFromId = (int) ($input['forward_from_id'] ?? 0);
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
            if ($msgId !== '' && !str_starts_with($msgId, 'imap:')) {
                $wrapped = str_starts_with($msgId, '<') ? $msgId : '<' . $msgId . '>';
                $inReplyTo = $wrapped;
                $references = $wrapped;
            }
            $contactId = isset($original['contact_id']) ? (int) $original['contact_id'] : null;
            if ($contactId !== null && $contactId <= 0) {
                $contactId = null;
            }
        } elseif ($forwardFromId > 0) {
            $original = MailLogRepository::findById($forwardFromId);
            if ($original === null) {
                throw new InvalidArgumentException('Weiterleitungsbezug nicht gefunden.');
            }
            if (!MailboxRepository::userCanAccess($actor, (int) ($original['mailbox_id'] ?? 0), 'read')) {
                throw new RuntimeException('Keine Berechtigung für die Originalnachricht.');
            }
            $contactId = isset($original['contact_id']) ? (int) $original['contact_id'] : null;
            if ($contactId !== null && $contactId <= 0) {
                $contactId = null;
            }
        }

        $html = nl2br(htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

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
     * @return list<string>
     */
    public static function parseRecipients(string $raw): array
    {
        $parts = preg_split('/[,;]/', $raw) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = EmailNormalizer::normalize(trim($part));
            if ($part !== '' && EmailNormalizer::isValidSyntax($part)) {
                $out[] = $part;
            }
        }

        return array_values(array_unique($out));
    }

    public static function replySubject(string $subject): string
    {
        $subject = trim($subject);
        if ($subject === '') {
            return 'Re:';
        }
        if (preg_match('/^re:\s/i', $subject) === 1) {
            return $subject;
        }

        return 'Re: ' . $subject;
    }

    public static function forwardSubject(string $subject): string
    {
        $subject = trim($subject);
        if ($subject === '') {
            return 'Wg:';
        }
        if (preg_match('/^(wg|fw|fwd):\s/i', $subject) === 1) {
            return $subject;
        }

        return 'Wg: ' . $subject;
    }

    /**
     * Zitatblock für Antwort/Weiterleitung (Klartext).
     *
     * @param array<string, mixed> $message
     */
    public static function quotedBody(array $message, string $mode = 'reply'): string
    {
        $from = trim((string) ($message['from_name'] ?? ''));
        $email = trim((string) ($message['from_address'] ?? ''));
        $when = trim((string) ($message['received_at'] ?? $message['created_at'] ?? ''));
        $who = $from !== '' && $email !== '' ? $from . ' <' . $email . '>' : ($email !== '' ? $email : $from);
        $header = $mode === 'forward'
            ? "---------- Weitergeleitete Nachricht ----------\nVon: {$who}\nDatum: {$when}\nBetreff: "
                . trim((string) ($message['subject'] ?? '')) . "\n\n"
            : "Am {$when} schrieb {$who}:\n\n";

        $text = trim((string) ($message['body_text'] ?? ''));
        if ($text === '') {
            $text = trim(strip_tags((string) ($message['body_html'] ?? '')));
        }
        if ($text === '') {
            $text = trim((string) ($message['body_preview'] ?? ''));
        }
        if ($text === '' && !empty($message['storage_path'])) {
            try {
                $path = MailArchiveStorage::absolutePath((string) $message['storage_path']);
                $parsed = MailMimeReader::parseFile($path);
                $text = trim((string) ($parsed['text'] ?? ''));
                if ($text === '') {
                    $text = trim(strip_tags((string) ($parsed['html'] ?? '')));
                }
            } catch (Throwable) {
                // optional
            }
        }
        if ($text === '') {
            return $header . '(Kein Text verfügbar)';
        }

        $quoted = preg_replace('/^/m', '> ', $text) ?? $text;

        return "\n\n" . $header . $quoted;
    }

    /**
     * Formularwerte für Antwort / Weiterleiten vorbereiten.
     *
     * @param array<string, mixed> $message
     * @return array{mailbox_id: string, to: string, subject: string, body: string, reply_to_id: string, forward_from_id: string, mode: string}
     */
    public static function composeFromMessage(array $message, string $mode): array
    {
        $id = (int) ($message['id'] ?? 0);
        $mailboxId = (string) (int) ($message['mailbox_id'] ?? 0);
        if ($mode === 'forward') {
            return [
                'mailbox_id' => $mailboxId,
                'to' => '',
                'subject' => self::forwardSubject((string) ($message['subject'] ?? '')),
                'body' => self::quotedBody($message, 'forward'),
                'reply_to_id' => '',
                'forward_from_id' => (string) $id,
                'mode' => 'forward',
            ];
        }

        return [
            'mailbox_id' => $mailboxId,
            'to' => (string) ($message['from_address'] ?? ''),
            'subject' => self::replySubject((string) ($message['subject'] ?? '')),
            'body' => self::quotedBody($message, 'reply'),
            'reply_to_id' => (string) $id,
            'forward_from_id' => '',
            'mode' => 'reply',
        ];
    }
}
