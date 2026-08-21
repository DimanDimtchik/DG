<?php
declare(strict_types=1);

/**
 * Outgoing e-mail message with MIME serialization.
 */
final class MailMessage
{
    /**
     * Konstruktor.
     * @param string $subject
     * @param string $htmlBody
     * @param list<string> $to
     * @param list<string> $cc
     * @param list<string> $bcc
     * @param string $textBody
     * @param string|null $fromEmail
     * @param string|null $fromName
     * @param string|null $replyTo
     * @param int|null $contactId
     * @param string|null $inReplyTo
     * @param string|null $references
     * @param list<array{filename: string, content: string, mime?: string}> $attachments
     */
    public function __construct(
        public readonly string $subject,
        public readonly string $htmlBody,
        public readonly array $to,
        public readonly array $cc = [],
        public readonly array $bcc = [],
        public readonly string $textBody = '',
        public readonly ?string $fromEmail = null,
        public readonly ?string $fromName = null,
        public readonly ?string $replyTo = null,
        public readonly ?int $contactId = null,
        public readonly ?string $inReplyTo = null,
        public readonly ?string $references = null,
        public readonly array $attachments = [],
    ) {
    }

    /**
     * Liest eine Datei als Anhang (fehlend = leere Liste).
     *
     * @return list<array{filename: string, content: string, mime?: string}>
     */
    public static function attachmentFromFile(string $path, ?string $filename = null, ?string $mime = null): array
    {
        if (!is_readable($path)) {
            return [];
        }
        $content = file_get_contents($path);
        if ($content === false || $content === '') {
            return [];
        }
        $name = $filename ?? basename($path);
        $entry = ['filename' => $name, 'content' => $content];
        if ($mime !== null && $mime !== '') {
            $entry['mime'] = $mime;
        }

        return [$entry];
    }

    /**
     * Returns all recipient addresses (to, cc, bcc) deduplicated.
     *
     * @return list<string>
     */
    public function allRecipients(): array
    {
        return array_values(array_unique(array_merge($this->to, $this->cc, $this->bcc)));
    }

    /**
     * Builds a multipart MIME message string.
     * @param string $fromEmail
     * @param string $fromName
     * @param string|null $replyTo
     * @param string $messageId
     * @return string
     * @throws InvalidArgumentException
     */
    public function toMime(string $fromEmail, string $fromName, ?string $replyTo, string $messageId): string
    {
        $to = self::formatAddressList($this->to);
        if ($to === '') {
            throw new InvalidArgumentException('Mindestens ein Empfnger erforderlich.');
        }

        $text = $this->textBody !== '' ? $this->textBody : self::htmlToText($this->htmlBody);
        $altBoundary = 'dg_alt_' . bin2hex(random_bytes(10));
        $mixedBoundary = 'dg_mix_' . bin2hex(random_bytes(10));
        $hasAttachments = $this->attachments !== [];
        $date = gmdate('D, d M Y H:i:s') . ' +0000';
        $encodedSubject = self::encodeHeader($this->subject);
        $encodedFrom = self::encodeAddress($fromEmail, $fromName);

        $headers = [
            'Date: ' . $date,
            'Message-ID: <' . $messageId . '>',
            'From: ' . $encodedFrom,
            'To: ' . $to,
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'X-Mailer: DG-CRM',
        ];

        if ($this->cc !== []) {
            $headers[] = 'Cc: ' . self::formatAddressList($this->cc);
        }
        if ($replyTo !== null && $replyTo !== '') {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        if ($this->inReplyTo !== null && $this->inReplyTo !== '') {
            $headers[] = 'In-Reply-To: ' . $this->inReplyTo;
        }
        if ($this->references !== null && $this->references !== '') {
            $headers[] = 'References: ' . $this->references;
        }

        $altBody = "--{$altBoundary}\r\n";
        $altBody .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $altBody .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $altBody .= chunk_split(base64_encode($text)) . "\r\n";
        $altBody .= "--{$altBoundary}\r\n";
        $altBody .= "Content-Type: text/html; charset=UTF-8\r\n";
        $altBody .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $altBody .= chunk_split(base64_encode($this->htmlBody)) . "\r\n";
        $altBody .= "--{$altBoundary}--\r\n";

        if (!$hasAttachments) {
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $altBoundary . '"';

            return implode("\r\n", $headers) . "\r\n\r\n" . $altBody;
        }

        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $mixedBoundary . '"';
        $body = "--{$mixedBoundary}\r\n";
        $body .= "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"\r\n\r\n";
        $body .= $altBody . "\r\n";

        foreach ($this->attachments as $attachment) {
            $filename = self::safeAttachmentName((string) ($attachment['filename'] ?? 'anhang.bin'));
            $content = (string) ($attachment['content'] ?? '');
            if ($content === '') {
                continue;
            }
            $mime = (string) ($attachment['mime'] ?? 'application/octet-stream');
            if ($mime === '') {
                $mime = 'application/octet-stream';
            }
            $body .= "--{$mixedBoundary}\r\n";
            $body .= 'Content-Type: ' . $mime . '; name="' . $filename . "\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= 'Content-Disposition: attachment; filename="' . $filename . "\"\r\n\r\n";
            $body .= chunk_split(base64_encode($content)) . "\r\n";
        }
        $body .= "--{$mixedBoundary}--\r\n";

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    /** Dateiname für MIME-Header (ASCII-sicher). */
    private static function safeAttachmentName(string $filename): string
    {
        $filename = str_replace(['"', "\r", "\n", '/', '\\'], '', $filename);
        $filename = trim($filename);
        if ($filename === '') {
            return 'anhang.bin';
        }
        if (!preg_match('/^[\x20-\x7E]+$/', $filename)) {
            return '=?UTF-8?B?' . base64_encode($filename) . '?=';
        }

        return $filename;
    }

    /**
     * Formats and validates a list of e-mail addresses.
     *
     * @param list<string> $addresses
     * @return string
     */
    private static function formatAddressList(array $addresses): string
    {
        $valid = [];
        foreach ($addresses as $address) {
            $address = trim($address);
            if ($address !== '' && filter_var($address, FILTER_VALIDATE_EMAIL)) {
                $valid[] = $address;
            }
        }

        return implode(', ', $valid);
    }

    /**
     * Encodes a display name and e-mail address for MIME headers.
     *
     * @param string $email
     * @param string $name
     * @return string
     */
    private static function encodeAddress(string $email, string $name): string
    {
        if ($name === '') {
            return $email;
        }

        return self::encodeHeader($name) . ' <' . $email . '>';
    }

    /**
     * Encodes a header value as UTF-8 base64 when non-ASCII.
     *
     * @param string $value
     * @return string
     */
    private static function encodeHeader(string $value): string
    {
        if ($value === '' || preg_match('/^[\x20-\x7E]+$/', $value)) {
            return $value;
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    /**
     * Converts HTML to a plain-text preview.
     *
     * @param string $html
     * @return string
     */
    public static function bodyPreview(string $html): string
    {
        return self::htmlToText($html);
    }

    /**
     * Strips HTML tags and normalizes whitespace to plain text.
     *
     * @param string $html
     * @return string
     */
    private static function htmlToText(string $html): string
    {
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html) ?? $html;
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<head\b[^>]*>.*?<\/head>/is', '', $html) ?? $html;
        $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $html)));
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
